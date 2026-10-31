<?php

namespace KW\MashaFeedly\Tests\Functional;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Logging\MashaFeedlyErrorHandler;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryRelation;
use KW\MashaFeedly\Service\MashaFeedlyAttachmentService;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Security\Security;

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
        $config->MashaFeedlyPollingEnabled = true;
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
        $activityResponse = $this->get('/__masha-feedly/listEntries?activityOnly=1');
        $this->assertSame(200, $activityResponse->getStatusCode());
        $activityIDs = array_column(json_decode($activityResponse->getBody(), true)['entries'], 'id');
        $this->assertContains((int)$this->ordinary->ID, $activityIDs);
        $this->assertNotContains((int)$this->technical->ID, $activityIDs);
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

    /** Der Betreiber kann Systemmeldungen im Widget und CMS schließen, reine technische Freigaben reichen nicht. */
    public function testOnlyConfiguredOperatorCanConfirmSystemReports(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyErrorsMemberIDs = json_encode([$this->customer->ID]);
        $config->write();
        $this->technical->AssignedMembers()->add($this->customer);
        $this->assertTrue($this->technical->canView($this->customer));
        $this->assertFalse($this->technical->canConfirmCompletion($this->customer));

        $this->logInWithPermission('ADMIN');
        $operator = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $operator);
        $this->assertFalse($this->technical->canConfirmCompletion($operator), 'ADMIN allein bestätigt keine Systemmeldung.');
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$operator->Email]);
        $config->MashaFeedlyAllowedMemberIDs = json_encode([$this->customer->ID, $operator->ID]);
        $config->write();
        $this->assertTrue($this->technical->canConfirmCompletion($operator));
        $this->assertFalse($this->ordinary->canConfirmCompletion($operator), 'Die Betreiberrolle umgeht nicht die Bestätigung normaler Meldungen.');
        MashaFeedlyCategory::ensureDefaultCategories();
        $done = MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first();
        $this->assertNotNull($done);
        $response = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$this->technical->ID,
            'CategoryID' => (int)$done->ID,
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['sentToFeedback']);
        $this->assertTrue($data['categoryIsClosed']);
        $this->assertSame((int)$done->ID, (int)MashaFeedlyEntry::get()->byID($this->technical->ID)->CategoryID);

        // Ein anderer Übergang prüft zusätzlich den Modellschutz beim direkten Speichern im CMS.
        $entry = MashaFeedlyEntry::get()->byID($this->technical->ID);
        $entry->CategoryID = (int)MashaFeedlyCategory::defaultCategory()->ID;
        $entry->write();
        $entry->CategoryID = (int)$done->ID;
        $entry->write();
        $this->assertSame((int)$done->ID, (int)MashaFeedlyEntry::get()->byID($entry->ID)->CategoryID);
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

    /** Der Erstellungsendpunkt speichert ohne gültigen Ort keinen Eintrag und gibt einen verständlichen Fehler zurück. */
    public function testCreationRequiresValidLocation(): void
    {
        $count = MashaFeedlyEntry::get()->count();
        foreach (['', 'javascript:alert(1)', '//foreign.example.test/path'] as $location) {
            $response = $this->post('/__masha-feedly/createEntry', [
                'SecurityID' => SecurityToken::getSecurityID(), 'Content' => 'Meldung ohne gültigen Ort',
                'PageURL' => $location, 'CategoryID' => $this->ordinary->CategoryID, 'PriorityID' => $this->ordinary->PriorityID,
            ]);
            $this->assertSame(400, $response->getStatusCode());
            $this->assertSame(\SilverStripe\i18n\i18n::_t('KW\\MashaFeedly\\Translations.ENTRY_LOCATION_REQUIRED',
                'Wähle einen gültigen Ort für die Meldung. Ohne Seitenadresse kann sie nicht gespeichert werden.'),
                json_decode($response->getBody(), true)['message']);
            $this->assertSame($count, MashaFeedlyEntry::get()->count());
        }
    }

    /** Das CMS-Board bietet auch bei älteren technischen Meldungen ohne gespeicherte Seite einen anklickbaren Titel. */
    public function testLegacyTechnicalReportHasClickableAdminLink(): void
    {
        DB::prepared_query('UPDATE "MashaFeedlyEntry" SET "PageURL" = ? WHERE "ID" = ?', ['', (int)$this->technical->ID]);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyErrorsMemberIDs = json_encode([$this->customer->ID]);
        $config->write();
        $response = $this->get('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry');
        $this->assertSame(200, $response->getStatusCode());
        $body = str_replace(['\\u003C', '\\u003E', '\\u0022'], ['<', '>', '"'], $response->getBody());
        $this->assertMatchesRegularExpression('~href="[^"<>]*/admin\?masha-feedly-entry=' . $this->technical->ID
            . '\#masha-feedly-entry=' . $this->technical->ID . '"~', $body);
        $details = $this->get('/__masha-feedly/entryDetails?EntryID=' . $this->technical->ID);
        $this->assertSame('/admin', json_decode($details->getBody(), true)['entry']['pageURL']);
    }
}
