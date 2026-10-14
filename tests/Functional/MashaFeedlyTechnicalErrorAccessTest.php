<?php

namespace KW\MashaFeedly\Tests\Functional;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Logging\MashaFeedlyErrorHandler;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryRelation;
use KW\MashaFeedly\Service\MashaFeedlyAttachmentService;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;

/** Prüft den technischen Zugriff über reale Controlleraufrufe statt nur über Modellmethoden. */
class MashaFeedlyTechnicalErrorAccessTest extends FunctionalTest
{
    protected static $fixture_file = '../fixtures/MashaFeedly.yml';
    private string $directory;
    private Member $customer;
    private MashaFeedlyEntry $technical;
    private MashaFeedlyEntry $ordinary;

    /** Legt sichtbare und technische Meldungen ohne echten Mailversand an. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = TEMP_PATH . '/feedly-error-access-test-' . bin2hex(random_bytes(8));
        Config::modify()->set(MashaFeedlyErrorHandler::class, 'spool_directory', $this->directory);
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', []);
        $this->customer = $this->objFromFixture(Member::class, 'allowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([$this->customer->ID]);
        $config->write();
        $this->technical = MashaFeedlyEntry::create([
            'Content' => 'Vertraulicher technischer Fehler', 'ErrorFingerprint' => hash('sha256', 'privat'),
            'ErrorOccurrences' => 42, 'ErrorFirstSeen' => '2026-10-14 10:00:00', 'ErrorLastSeen' => '2026-10-14 11:00:00',
        ]);
        $this->technical->deferErrorNotification = true;
        $this->technical->write();
        $this->ordinary = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $this->logInAs($this->customer);
    }

    /** Entfernt nur die private Ablage dieses Tests. */
    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) unlink($path);
        if (is_dir($this->directory)) rmdir($this->directory);
        parent::tearDown();
    }

    /** Technische Meldungen fehlen in Listen, Details, Verlauf und direkten Schreibaufrufen. */
    public function testCustomerCannotReadOrModifyTechnicalReports(): void
    {
        $response = $this->get('/__masha-feedly/listEntries?mode=all');
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $ids = array_map('intval', array_column($data['entries'], 'id'));
        $this->assertContains((int)$this->ordinary->ID, $ids);
        $this->assertNotContains((int)$this->technical->ID, $ids);
        foreach (['entryDetails', 'entryHistory'] as $action) {
            $response = $this->get('/__masha-feedly/' . $action . '?EntryID=' . $this->technical->ID);
            $this->assertSame(404, $response->getStatusCode());
            $this->assertStringNotContainsString('Vertraulicher', $response->getBody());
        }
        foreach (['markEntryRead', 'updateEntry'] as $action) {
            $response = $this->post('/__masha-feedly/' . $action, [
                'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => $this->technical->ID,
            ]);
            $this->assertSame(404, $response->getStatusCode());
        }
        $response = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => $this->technical->ID, 'CommentText' => 'Unbefugt',
        ]);
        $this->assertSame(404, $response->getStatusCode());
    }

    /** Verknüpfungen einer sichtbaren Meldung verraten keine technischen Titel oder IDs. */
    public function testRelationsDoNotRevealHiddenReports(): void
    {
        MashaFeedlyEntryRelation::create(['EntryID' => $this->ordinary->ID, 'RelatedEntryID' => $this->technical->ID, 'LinkType' => 'related'])->write();
        MashaFeedlyEntryRelation::create(['EntryID' => $this->technical->ID, 'RelatedEntryID' => $this->ordinary->ID, 'LinkType' => 'related'])->write();
        $response = $this->get('/__masha-feedly/entryDetails?EntryID=' . $this->ordinary->ID);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Vertraulicher', $response->getBody());
        $data = json_decode($response->getBody(), true);
        $this->assertEmpty($data['entry']['relations']);
    }

    /** Gefälschte Kommentar-IDs umgehen die Eintragsfreigabe nicht. */
    public function testCommentIDCannotBypassTechnicalAccess(): void
    {
        $comment = MashaFeedlyComment::create([
            'EntryID' => $this->technical->ID, 'AuthorMemberID' => $this->customer->ID,
            'AuthorName' => 'Test', 'CommentText' => 'Vertraulich', 'IsApproved' => true,
        ]);
        $comment->write();
        $response = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => $this->ordinary->ID,
            'CommentID' => $comment->ID, 'CommentAction' => 'edit', 'CommentText' => 'Manipuliert',
        ]);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Vertraulich', MashaFeedlyComment::get()->byID($comment->ID)->CommentText);
    }

    /** Eine ausdrücklich zusätzliche Freigabe gewährt Zugriff, ihre Entfernung entzieht ihn wieder. */
    public function testAdditionalAccessCanBeGrantedAndRevoked(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyErrorsMemberIDs = json_encode([$this->customer->ID]);
        $config->write();
        $response = $this->get('/__masha-feedly/entryDetails?EntryID=' . $this->technical->ID);
        $this->assertSame(200, $response->getStatusCode());
        $entry = json_decode($response->getBody(), true)['entry'];
        $this->assertTrue($entry['isTechnicalError']);
        $this->assertStringContainsString('42', $entry['errorSummary']);
        $config->MashaFeedlyErrorsMemberIDs = '[]';
        $config->write();
        $this->assertSame(404, $this->get('/__masha-feedly/entryDetails?EntryID=' . $this->technical->ID)->getStatusCode());
    }

    /** Auch ein direkter Dateilink darf den technischen Zugriff nicht umgehen. */
    public function testDirectAttachmentURLRequiresTechnicalAccess(): void
    {
        TestAssetStore::activate('masha-feedly-technical-download-test');
        $temporary = tempnam(TEMP_PATH, 'feedly-technical-');
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII=');
        file_put_contents($temporary, $bytes);
        try {
            $attachment = MashaFeedlyAttachmentService::attachUploads([
                'name' => 'technical.png', 'type' => 'image/png', 'tmp_name' => $temporary,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($temporary),
            ], $this->technical)[0];
            $file = File::get()->byID((int)$attachment->FileID);
            $this->assertFalse($file->canView($this->customer));
            $this->assertNotSame($bytes, $this->get($file->getURL())->getBody());
            $this->logOut();
            $this->assertNotSame($bytes, $this->get($file->getURL())->getBody());
            $config = MashaFeedlyConfigExtension::currentSiteConfig();
            $config->MashaFeedlyErrorsMemberIDs = json_encode([$this->customer->ID]);
            $config->write();
            $this->logInAs($this->customer);
            $this->assertTrue($file->canView($this->customer));
            $response = $this->get($file->getURL());
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($bytes, $response->getBody());
        } finally {
            TestAssetStore::reset();
            unlink($temporary);
        }
    }


}
