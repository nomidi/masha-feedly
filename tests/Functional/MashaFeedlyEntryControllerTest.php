<?php

namespace KW\MashaFeedly\Tests\Functional;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyCommentReaction;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use KW\MashaFeedly\Model\MashaFeedlyEntryRelation;
use KW\MashaFeedly\Model\MashaFeedlyPriority;
use KW\MashaFeedly\Model\MashaFeedlySavedView;
use KW\MashaFeedly\Service\MashaFeedlyAttachmentService;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\i18n\i18n;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/** Prüft das Erstellen eines Eintrags aus der Masha-Feedly-Auswahl.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyEntryControllerTest extends FunctionalTest
{
    protected static $fixture_file = '../fixtures/MashaFeedly.yml';

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('de_DE');
    }

    /** Nur Schätzungsmanager erhalten Warteschlangen-Zähler und dürfen ihre gefilterten Listen abrufen. */
    public function testEstimateQueuesAreCountedAndRestrictedToEstimateManagers(): void
    {
        $this->logInWithPermission('ADMIN');
        $manager = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $manager);
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([(int)$manager->ID]);
        $siteConfig->write();
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$manager->Email]);
        MashaFeedlyCategory::ensureDefaultCategories();
        $pending = MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_pending')->first();
        $approved = MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_approved')->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($approved);
        $siteConfig->MashaFeedlyHourlyRate = 100;
        $siteConfig->write();

        $pendingEntry = MashaFeedlyEntry::create([
            'Content' => 'Wartet auf Freigabe – Testwarteschlange',
            'CategoryID' => (int)$pending->ID,
            'EstimatedCostDuration' => '2 Stunden',
            'EstimatedCostAmount' => 200,
        ]);
        $pendingEntry->write();
        $approvedEntry = MashaFeedlyEntry::create([
            'Content' => 'Freigegeben und noch offen – Testwarteschlange',
            'CategoryID' => (int)$approved->ID,
            'EstimatedCostDuration' => '1 Stunde',
            'EstimatedCostAmount' => 100,
        ]);
        $approvedEntry->write();

        $pendingResponse = $this->get('/__masha-feedly/listEntries?mode=estimate-pending');
        $pendingData = json_decode($pendingResponse->getBody(), true);
        $this->assertSame(200, $pendingResponse->getStatusCode());
        $this->assertSame(1, $pendingData['estimatePendingCount']);
        $this->assertSame(1, $pendingData['estimateApprovedCount']);
        $this->assertSame('estimate-pending', $pendingData['mode']);
        $this->assertSame([(int)$pendingEntry->ID], array_map('intval', array_column($pendingData['entries'], 'id')));

        $approvedResponse = $this->get('/__masha-feedly/listEntries?mode=estimate-approved');
        $approvedData = json_decode($approvedResponse->getBody(), true);
        $this->assertSame(200, $approvedResponse->getStatusCode());
        $this->assertSame('estimate-approved', $approvedData['mode']);
        $this->assertSame([(int)$approvedEntry->ID], array_map('intval', array_column($approvedData['entries'], 'id')));

        $ordinaryMember = $this->objFromFixture(Member::class, 'notAllowed');
        $approver = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($approver);
        $approver->MashaFeedlyCanManageEstimates = true;
        $approver->write();
        $this->logInAs($approver);
        $approverQueue = json_decode($this->get('/__masha-feedly/listEntries?mode=estimate-pending')->getBody(), true);
        $this->assertSame('estimate-pending', $approverQueue['mode']);
        $this->assertSame(1, $approverQueue['estimatePendingCount']);
        $this->assertFalse($approverQueue['canManageEstimate']);
        $this->assertTrue($approverQueue['canApproveEstimate']);

        $this->allowMember($ordinaryMember);
        $this->logInAs($ordinaryMember);
        $denied = $this->get('/__masha-feedly/listEntries?mode=estimate-pending');
        $this->assertSame(403, $denied->getStatusCode());
        $ordinaryData = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $this->assertSame(0, $ordinaryData['estimatePendingCount']);
        $this->assertSame(0, $ordinaryData['estimateApprovedCount']);
    }

    /** Kostenschätzungen samt Freigabestatus und Betrag sind nur Superadmin und einzeln freigegebenen Mitgliedern zugänglich. */
    public function testEstimateDataAndCategoriesAreHiddenFromUnprivilegedFeedlyMembers(): void
    {
        $this->logInWithPermission('ADMIN');
        $superadmin = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $superadmin);
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$superadmin->Email]);

        $approvedMember = $this->objFromFixture(Member::class, 'allowed');
        $unapprovedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($approvedMember, $unapprovedMember, $superadmin);
        $this->assertNotNull($approvedMember->getCMSFields()->dataFieldByName('MashaFeedlyCanManageEstimates'));
        $approvedMember->MashaFeedlyCanManageEstimates = true;
        $approvedMember->write();

        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyHourlyRate = 120;
        $config->write();
        $pending = MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_pending')->first();
        $this->assertNotNull($pending);

        $entry = MashaFeedlyEntry::create([
            'Content' => 'Interner Kostenschätzungsdatensatz',
            'CategoryID' => (int)$pending->ID,
            'EstimatedCostDuration' => '2 Stunden',
            'EstimatedCostAmount' => 240,
            'EstimatedCostAmountMax' => 240,
            'EstimatedCostCurrency' => 'EUR',
            'EstimatedCostNote' => 'Nur für berechtigte Personen',
        ]);
        $entry->write();
        MashaFeedlyEntryHistory::record($entry, 'estimate', '1 Stunde · 120 €', '2 Stunden · 240 €', $superadmin);

        $this->logInAs($unapprovedMember);
        $response = $this->get('/__masha-feedly/listEntries?mode=all');
        $data = json_decode($response->getBody(), true);
        $listed = array_values(array_filter($data['entries'], static fn(array $item): bool => (int)$item['id'] === (int)$entry->ID))[0];
        $this->assertFalse($data['canManageEstimate']);
        $this->assertNull($data['estimateHourlyRate']);
        $this->assertNotContains('estimate_pending', array_column($data['categories'], 'systemKey'));
        $this->assertNotContains('estimate_approved', array_column($data['categories'], 'systemKey'));
        $this->assertSame((string)$pending->Title, $listed['categoryTitle'], 'Der tatsächliche Status bleibt am Eintrag sichtbar.');
        $this->assertSame('restricted_estimate', $listed['categoryRole']);
        $this->assertNotContains((string)$pending->Title, array_column($data['categories'], 'title'), 'Die geschützte Kategorie bleibt aus der Auswahl ausgeblendet.');
        foreach (['estimateAmount', 'estimateAmountMax', 'estimateDuration', 'estimateCurrency', 'estimateNote'] as $field) {
            $this->assertArrayNotHasKey($field, $listed);
        }
        $this->assertNotContains('estimate', array_column($listed['history'], 'type'));
        $deniedChange = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_approved')->first()->ID,
        ]);
        $this->assertSame(403, $deniedChange->getStatusCode());
        $forgedEntry = MashaFeedlyEntry::create([
            'Content' => 'Unberechtigte direkte ORM-Freigabe',
            'CategoryID' => (int)$pending->ID,
            'EstimatedCostDuration' => '99 Stunden',
            'EstimatedCostAmount' => 1,
        ]);
        $forgedEntry->write();
        $this->assertSame('backlog', (string)$forgedEntry->Category()->SystemKey);
        $this->assertSame('', (string)$forgedEntry->EstimatedCostDuration);
        $this->assertSame(0.0, (float)$forgedEntry->EstimatedCostAmount);
        $unapprovedMember->MashaFeedlyCanManageEstimates = true;
        $unapprovedMember->write();
        $this->assertFalse((bool)$unapprovedMember->MashaFeedlyCanManageEstimates, 'A member cannot grant estimate permissions to themselves with a forged CMS field.');

        $this->logInAs($approvedMember);
        $allowedData = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $allowedEntry = array_values(array_filter($allowedData['entries'], static fn(array $item): bool => (int)$item['id'] === (int)$entry->ID))[0];
        $this->assertFalse($allowedData['canManageEstimate'], 'Eine Freigabeperson darf die Schätzung nicht bearbeiten.');
        $this->assertTrue($allowedData['canApproveEstimate']);
        $this->assertNull($allowedData['estimateHourlyRate'], 'Der interne Stundensatz bleibt für Freigabepersonen verborgen.');
        $this->assertSame('estimate_pending', $allowedEntry['categoryRole']);
        $this->assertSame('240', $allowedEntry['estimateAmount']);
        $this->assertSame('2 Stunden', $allowedEntry['estimateDuration']);
        $this->assertSame('Nur für berechtigte Personen', $allowedEntry['estimateNote'], 'Die Erläuterung wird nur lesend angezeigt.');
        $this->assertNotContains('estimate', array_column($allowedEntry['history'], 'type'), 'Die Freigabeperson sieht die Schätzung, aber nicht die Historie mit Änderungsdetails.');

        $forbiddenEstimateEdit = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$pending->ID,
            'EstimatedCostDuration' => '99 Stunden',
            'EstimatedCostNote' => 'Manipulierter Eintrag',
        ]);
        $this->assertSame(403, $forbiddenEstimateEdit->getStatusCode(), 'Freigabepersonen können Dauer und Text auch per manipuliertem Request nicht ändern.');
        $approveResponse = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_approved')->first()->ID,
        ]);
        $this->assertSame(200, $approveResponse->getStatusCode());
        $approvedResponseData = json_decode($approveResponse->getBody(), true);
        $this->assertSame('estimate_approved', $approvedResponseData['categoryRole']);
        $this->assertFalse($approvedResponseData['canManageEstimate']);
        $this->assertSame('2 Stunden', $approvedResponseData['estimateDuration']);

        $this->logInAs($superadmin);

        $backlogEntry = MashaFeedlyEntry::create([
            'Content' => 'Kostenschätzung nur im passenden Status',
            'CategoryID' => (int)MashaFeedlyCategory::defaultCategory()->ID,
        ]);
        $backlogEntry->write();
        $requestWithoutEstimate = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$backlogEntry->ID,
            'CategoryID' => (int)$pending->ID,
        ]);
        $this->assertSame(400, $requestWithoutEstimate->getStatusCode());
        $this->assertSame('backlog', (string)MashaFeedlyEntry::get()->byID($backlogEntry->ID)->Category()->SystemKey);

        $savedEstimate = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$backlogEntry->ID,
            'CategoryID' => (int)$pending->ID,
            'EstimatedCostDuration' => '3 Stunden',
            'EstimatedCostNote' => 'Schätzung zur Freigabe',
        ]);
        $this->assertSame(200, $savedEstimate->getStatusCode());
        $savedEstimateData = json_decode($savedEstimate->getBody(), true);
        $this->assertSame('estimate_pending', $savedEstimateData['categoryRole']);
        $this->assertSame('3 Stunden', $savedEstimateData['estimateDuration']);
        $this->assertSame(360.0, (float)$savedEstimateData['estimateAmount']);
        $reloadedEstimateEntry = MashaFeedlyEntry::get()->byID($backlogEntry->ID);
        $this->assertSame((int)$pending->ID, (int)$reloadedEstimateEntry->CategoryID);
        $this->assertSame('estimate_pending', (string)$reloadedEstimateEntry->Category()->SystemKey);
        $this->assertSame('3 Stunden', (string)$reloadedEstimateEntry->EstimatedCostDuration);
        $this->assertSame(360.0, (float)$reloadedEstimateEntry->EstimatedCostAmount);
        $estimateHistory = MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$backlogEntry->ID,
            'ChangeType' => 'estimate',
        ])->first();
        $this->assertNotNull($estimateHistory, 'Die Dauer, der berechnete Betrag und die Erläuterung müssen protokolliert werden.');
        $this->assertStringContainsString('3 Stunden', (string)$estimateHistory->NewValue);
        $this->assertStringContainsString('360,00 €', (string)$estimateHistory->NewValue);
        $this->assertStringContainsString('Schätzung zur Freigabe', (string)$estimateHistory->NewValue);
        $statusHistory = MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$backlogEntry->ID,
            'ChangeType' => 'status',
        ])->first();
        $this->assertNotNull($statusHistory, 'Das Anfordern der Kostenschätzung muss mit Statuswechsel protokolliert werden.');
        $this->assertSame((string)$pending->Title, (string)$statusHistory->NewValue);

        $wrongCategoryEntry = MashaFeedlyEntry::create([
            'Content' => 'Kostenschätzung darf im Backlog nicht gespeichert werden',
            'CategoryID' => (int)MashaFeedlyCategory::defaultCategory()->ID,
        ]);
        $wrongCategoryEntry->write();
        $wrongCategoryEstimate = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$wrongCategoryEntry->ID,
            'CategoryID' => (int)MashaFeedlyCategory::defaultCategory()->ID,
            'EstimatedCostDuration' => '3 Stunden',
            'EstimatedCostNote' => 'Darf im Backlog nicht gespeichert werden',
        ]);
        $this->assertSame(409, $wrongCategoryEstimate->getStatusCode());
        $this->assertSame('backlog', (string)MashaFeedlyEntry::get()->byID($wrongCategoryEntry->ID)->Category()->SystemKey);
        $this->assertSame('', (string)MashaFeedlyEntry::get()->byID($wrongCategoryEntry->ID)->EstimatedCostDuration);
    }

    /** Die Widgetdaten zeigen die Meldeperson, während technischer Ersteller und Erstellungszeit erhalten bleiben. */
    public function testEntryResponseUsesReportedByOverrideAndKeepsCreationAudit(): void
    {
        $this->logInWithPermission('ADMIN');
        $manager = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $manager);
        $this->allowMember($manager);
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$manager->Email]);
        $reporter = $this->objFromFixture(Member::class, 'notAllowed');
        MashaFeedlyCategory::ensureDefaultCategories();
        $this->assertTrue(MashaFeedlyEntry::canManageReporter($manager));

        $entry = MashaFeedlyEntry::create(['Content' => 'Manuell übertragener älterer Fehler']);
        $entry->write();
        $creationEvent = MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => 'created',
        ])->first();
        $entry->ReportedByID = (int)$reporter->ID;
        $entry->write();

        $response = $this->get('/__masha-feedly/listEntries?mode=all');
        $data = json_decode($response->getBody(), true);
        $listedEntry = array_values(array_filter($data['entries'], static fn(array $item): bool => (int)$item['id'] === (int)$entry->ID))[0];

        $this->assertSame($reporter->getName(), $listedEntry['createdBy']);
        $this->assertSame($reporter->getName(), $listedEntry['reportedByName']);
        $listedCreationEvent = array_values(array_filter($listedEntry['history'], static fn(array $item): bool => $item['type'] === 'created'))[0];
        $this->assertSame((string)$creationEvent->ActorName, $listedCreationEvent['actor']);
        $this->assertSame($listedCreationEvent['created'], $listedEntry['createdAt']);
        $this->assertSame((int)$manager->ID, $entry->creatorMemberID());
        $this->assertTrue((bool)array_filter($listedEntry['history'], static fn(array $item): bool => $item['type'] === 'reported_by'));
    }

    /** Das allgemeine CMS-ADMIN-Recht darf Kundenadmins keinen Zugriff auf die Melder-Auswahl geben. */
    public function testCmsAdminWithoutConfiguredEmailCannotSeeOrChangeReporter(): void
    {
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', ['allowed@example.test']);
        $reporter = $this->objFromFixture(Member::class, 'allowed');
        $this->logInWithPermission('ADMIN');
        $cmsAdmin = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $cmsAdmin);
        $this->assertTrue(Permission::checkMember($cmsAdmin, 'ADMIN'));
        $this->assertFalse(MashaFeedlyEntry::canManageReporter($cmsAdmin));

        $entry = MashaFeedlyEntry::create(['Content' => 'Eintrag eines Kundenadmins']);
        $entry->write();
        $this->assertNull($entry->getCMSFields()->dataFieldByName('ReportedByID'));
        $entry->ReportedByID = (int)$reporter->ID;
        $entry->write();
        $this->assertSame(0, (int)$entry->ReportedByID);
        $this->assertSame(0, MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => 'reported_by',
        ])->count());
    }

    /** Prüft, dass ein berechtigter Benutzer Formularwerte und Elementkontext speichern kann. */
    public function testAllowedMemberCanCreateEntryWithSelectedPageContext(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $blockedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        $category = MashaFeedlyCategory::defaultCategory();
        $this->logInAs($member);

        $response = $this->post('/__masha-feedly/createEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => 'Der Button ist abgeschnitten 😅. https://example.test/ablauf',
            'CategoryID' => (int)$category->ID,
            'EntryDate' => '2026-10-01T10:30',
            'DueDate' => '2026-10-10',
            'PageURL' => 'https://example.test/kontakt/?campaign=mailing#formular',
            'ElementSelector' => 'main > button.primary',
            'ElementText' => 'Absenden',
            'ElementPositionX' => '0.12500',
            'ElementPositionY' => '0.87500',
            'OperatingSystem' => 'Mac OS 10.15.7',
            'Browser' => 'Chrome 152.0.0.0',
            'UserAgent' => 'Mozilla/5.0 Mac OS X 10_15_7 Chrome/152.0.0.0',
            'Resolution' => '2560 × 1440 px',
            'BrowserWindow' => '1943 × 1294 px',
            'ColorDepth' => '24',
            'AssignedMemberIDs' => [(int)$member->ID, (int)$blockedMember->ID],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('2026-10-10', $data['dueDate']);
        $this->assertSame([], $data['attachments']);
        $entry = MashaFeedlyEntry::get()->byID((int)$data['entryID']);
        $this->assertNotNull($entry);
        $this->assertSame('Der Button ist abgeschnitten 😅. https://example.test/ablauf', strip_tags((string)$entry->Content));
        $this->assertSame('https://example.test/kontakt', (string)$entry->PageURL);
        $this->assertSame('main > button.primary', (string)$entry->ElementSelector);
        $this->assertSame('Absenden', (string)$entry->ElementText);
        $this->assertSame('0.12500', (string)$entry->ElementPositionX);
        $this->assertSame('0.87500', (string)$entry->ElementPositionY);
        $this->assertSame('Mac OS 10.15.7', (string)$entry->OperatingSystem);
        $this->assertSame('Chrome 152.0.0.0', (string)$entry->Browser);
        $this->assertSame('Mozilla/5.0 Mac OS X 10_15_7 Chrome/152.0.0.0', (string)$entry->UserAgent);
        $this->assertSame('2560 × 1440 px', (string)$entry->Resolution);
        $this->assertSame('1943 × 1294 px', (string)$entry->BrowserWindow);
        $this->assertSame(24, (int)$entry->ColorDepth);
        $this->assertSame((int)$category->ID, (int)$entry->CategoryID);
        $this->assertSame('2026-10-10', (string)$entry->DueDate);
        $this->assertSame([(int)$member->ID], array_map('intval', $entry->AssignedMembers()->column('ID')));

        $listResponse = $this->get('/__masha-feedly/listEntries?mode=all&PageURL=' . rawurlencode('https://example.test/kontakt'));
        $listData = json_decode($listResponse->getBody(), true);
        $listedEntry = $listData['entries'][0];
        $this->assertSame('Mac OS 10.15.7', $listedEntry['operatingSystem']);
        $this->assertSame('Chrome 152.0.0.0', $listedEntry['browser']);
        $this->assertSame('main > button.primary', $listedEntry['selector']);
        $this->assertSame('0.12500', $listedEntry['elementPositionX']);
        $this->assertSame('0.87500', $listedEntry['elementPositionY']);
        $this->assertSame('2560 × 1440 px', $listedEntry['resolution']);
        $this->assertSame('1943 × 1294 px', $listedEntry['browserWindow']);
        $this->assertSame(24, $listedEntry['colorDepth']);
        $this->assertNotEmpty($listedEntry['loggedAt']);
        $this->assertSame([], $listedEntry['attachments']);
        $this->assertSame('2026-10-10', $listedEntry['dueDate']);

        $updated = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$category->ID,
            'DueDate' => '2026-10-12',
        ]);
        $this->assertSame(200, $updated->getStatusCode());
        $history = MashaFeedlyEntryHistory::get()->filter(['EntryID' => (int)$entry->ID, 'ChangeType' => 'due_date'])->first();
        $this->assertNotNull($history);
        $this->assertSame('2026-10-10', (string)$history->OldValue);
        $this->assertSame('2026-10-12', (string)$history->NewValue);
    }

    /** Ungültige Kalenderdaten dürfen nicht als Fälligkeit gespeichert werden. */
    public function testInvalidDueDateIsRejected(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        $this->logInAs($member);
        $before = MashaFeedlyEntry::get()->count();
        $response = $this->post('/__masha-feedly/createEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => 'Ungültiger Fälligkeitstest',
            'DueDate' => '2026-02-30',
        ]);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse(json_decode($response->getBody(), true)['success']);
        $this->assertSame($before, MashaFeedlyEntry::get()->count());
    }

    /** Prüft, dass der Feedback-Filter nur wartende Einträge liefert und die Anzahl berechnet. */
    public function testFeedbackModeListsOnlyEntriesWaitingForFeedback(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        $feedback = MashaFeedlyCategory::get()->filter('Title', 'Feedback')->first();
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entry->CategoryID = (int)$feedback->ID;
        $entry->PageURL = 'https://example.test/feedback';
        $entry->write();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $done = MashaFeedlyCategory::get()->filter('Title', 'Done')->first();
        $closedEntry = MashaFeedlyEntry::create([
            'Content' => 'Bereits erledigt',
            'PageURL' => 'https://example.test/feedback',
            'CategoryID' => (int)$done->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $closedEntry->write();
        $this->logInAs($member);

        $response = $this->get('/__masha-feedly/listEntries?mode=feedback');
        $data = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('feedback', $data['mode']);
        $this->assertSame(1, $data['feedbackCount']);
        $this->assertSame([(int)$entry->ID], array_column($data['entries'], 'id'));

        $pageOpen = json_decode($this->get('/__masha-feedly/listEntries?mode=page-open&PageURL=' . rawurlencode('https://example.test/feedback'))->getBody(), true);
        $this->assertSame('page-open', $pageOpen['mode']);
        $this->assertSame([(int)$entry->ID], array_column($pageOpen['entries'], 'id'));

        $this->logOut();
        $forbidden = $this->get('/__masha-feedly/listEntries?mode=feedback');
        $this->assertSame(403, $forbidden->getStatusCode());
    }

    /** Prüft, dass ein Mitglied ohne Freigabe keinen Eintrag über die Widget-Schnittstelle anlegen kann. */
    public function testMemberWithoutAccessCannotCreateEntry(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $other = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowed);
        $this->logInAs($other);

        $response = $this->post('/__masha-feedly/createEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => 'Nicht erlaubt',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $listResponse = $this->get('/__masha-feedly/listEntries?mode=page&PageURL=' . rawurlencode('https://example.test/kontakt'));
        $this->assertSame(403, $listResponse->getStatusCode());
        $baseResponse = $this->get('/__masha-feedly');
        $this->assertSame(403, $baseResponse->getStatusCode());
        $allEntriesResponse = $this->get('/__masha-feedly/listEntries?mode=all');
        $this->assertSame(403, $allEntriesResponse->getStatusCode());
        $this->assertStringNotContainsString('Sichtbarer Testeintrag', $allEntriesResponse->getBody());
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $category = MashaFeedlyCategory::get()->first();
        $updateResponse = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$category->ID,
        ]);
        $this->assertSame(403, $updateResponse->getStatusCode());
        $this->assertSame((int)$this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry')->CategoryID, (int)MashaFeedlyEntry::get()->byID((int)$entry->ID)->CategoryID);
        $this->assertSame(1, MashaFeedlyEntry::get()->count());
    }

    /** Speichert HTML-Payloads in Einträgen codiert und liefert nur ungefährlichen Text zurück. */
    public function testMaliciousMarkupInEntryIsEncodedAndReturnedAsText(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)> <svg onload=alert(3)> javascript:alert(4) https://safe.example/path';
        $this->logInAs($member);

        $created = $this->post('/__masha-feedly/createEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => $payload,
            'PageURL' => 'https://example.test/xss-test',
        ]);

        $this->assertSame(200, $created->getStatusCode());
        $entryID = (int)json_decode($created->getBody(), true)['entryID'];
        $entry = MashaFeedlyEntry::get()->byID($entryID);
        $this->assertStringNotContainsString('<script>', (string)$entry->Content);
        $this->assertStringNotContainsString('<img', (string)$entry->Content);
        $this->assertStringContainsString('&lt;script&gt;', (string)$entry->Content);

        $list = $this->get('/__masha-feedly/listEntries?mode=all');
        $data = json_decode($list->getBody(), true);
        $listed = array_values(array_filter($data['entries'], static fn(array $item): bool => (int)$item['id'] === $entryID))[0];
        $this->assertSame($payload, $listed['content']);
    }

    /** Speichert Kommentar-Payloads als Text in JSON und nicht als HTML-Antwort. */
    public function testMaliciousMarkupInCommentIsReturnedAsPlainTextJSON(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)> <svg onload=alert(3)> javascript:alert(4) https://safe.example/path';
        $this->logInAs($member);

        $response = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CommentText' => $payload,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', (string)$response->getHeader('Content-Type'));
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertSame($payload, $data['comment']['text']);
        $stored = MashaFeedlyComment::get()->byID((int)$data['comment']['id']);
        $this->assertSame($payload, (string)$stored->CommentText);
    }

    /** Prüft Standardprioritäten, Speicherung über beide Endpunkte und den unveränderlichen Prioritätsverlauf. */
    public function testPriorityDefaultsCanBeChangedAndChangesAreRecorded(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $this->assertSame(['Sofort bearbeiten', 'Zeitnah bearbeiten', 'Normal', 'Bei Gelegenheit', 'Info'], array_values(MashaFeedlyPriority::get()->sort('Sort ASC')->column('Title')));
        $this->assertNotEmpty((string)MashaFeedlyPriority::get()->filter('Title', 'Sofort bearbeiten')->first()->Description);
        $this->assertSame('warning', (string)MashaFeedlyPriority::get()->filter('Title', 'Sofort bearbeiten')->first()->IconType);
        $this->assertSame('info', (string)MashaFeedlyPriority::get()->filter('Title', 'Info')->first()->IconType);
        $this->logInAs($member);

        $created = $this->post('/__masha-feedly/createEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => 'Suche liefert keine Ergebnisse.',
            'PageURL' => 'https://example.test/priorities',
        ]);
        $this->assertSame(200, $created->getStatusCode());
        $entryID = (int)json_decode($created->getBody(), true)['entryID'];
        $entry = MashaFeedlyEntry::get()->byID($entryID);
        $normal = MashaFeedlyPriority::get()->filter('Title', 'Normal')->first();
        $critical = MashaFeedlyPriority::get()->filter('Title', 'Sofort bearbeiten')->first();
        $this->assertSame((int)$normal->ID, (int)$entry->PriorityID);

        $updated = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => $entryID,
            'CategoryID' => (int)$entry->CategoryID,
            'PriorityID' => (int)$critical->ID,
        ]);
        $this->assertSame(200, $updated->getStatusCode());
        $this->assertSame((int)$critical->ID, (int)MashaFeedlyEntry::get()->byID($entryID)->PriorityID);
        $history = MashaFeedlyEntryHistory::get()->filter(['EntryID' => $entryID, 'ChangeType' => 'priority'])->first();
        $this->assertNotNull($history);
        $this->assertSame('Normal', (string)$history->OldValue);
        $this->assertSame('Sofort bearbeiten', (string)$history->NewValue);
        $this->assertSame('Erika Muster', (string)$history->ActorName);
        $data = json_decode($updated->getBody(), true);
        $this->assertSame('Sofort bearbeiten', $data['history'][0]['newValue']);

        $listed = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $payload = array_values(array_filter($listed['entries'], static fn(array $item): bool => (int)$item['id'] === $entryID))[0];
        $this->assertSame((int)$critical->ID, $payload['priorityID']);
        $this->assertSame('Sofort bearbeiten', $payload['priorityTitle']);
        $this->assertNotEmpty($payload['priorityColor']);
        $this->assertSame('warning', $payload['priorityIconType']);
        $this->assertSame(['Sofort bearbeiten', 'Zeitnah bearbeiten', 'Normal', 'Bei Gelegenheit', 'Info'], array_column($listed['priorities'], 'title'));
        $this->assertSame('info', $listed['priorities'][4]['iconType']);

        $highCreated = $this->post('/__masha-feedly/createEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => 'Ein wichtiger Fehler mit Umgehung.',
            'PageURL' => 'https://example.test/priorities',
            'PriorityID' => (int)MashaFeedlyPriority::get()->filter('Title', 'Zeitnah bearbeiten')->first()->ID,
        ]);
        $highID = (int)json_decode($highCreated->getBody(), true)['entryID'];
        $ordered = json_decode($this->get('/__masha-feedly/listEntries?mode=page&PageURL=' . rawurlencode('https://example.test/priorities'))->getBody(), true);
        $this->assertSame([$entryID, $highID], array_column($ordered['entries'], 'id'));

        $invalid = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => $entryID,
            'CategoryID' => (int)$entry->CategoryID,
            'PriorityID' => 999999,
        ]);
        $this->assertSame(400, $invalid->getStatusCode());
        $this->assertSame((int)$critical->ID, (int)MashaFeedlyEntry::get()->byID($entryID)->PriorityID);
    }

    /** Ohne Sitzung dürfen weder Einträge noch Kommentare angelegt oder verändert werden. */
    public function testAnonymousRequestsCannotCreateUpdateOrComment(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($allowed);
        MashaFeedlyCategory::ensureDefaultCategories();
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $comment = MashaFeedlyComment::create([
            'AuthorName' => 'Erika Muster',
            'CommentText' => 'Geschützter Ausgangskommentar',
            'IsApproved' => true,
            'EntryID' => (int)$entry->ID,
            'AuthorMemberID' => (int)$allowed->ID,
        ]);
        $comment->write();
        $originalEntryCount = MashaFeedlyEntry::get()->count();
        $originalCommentCount = MashaFeedlyComment::get()->count();
        $originalHistoryCount = MashaFeedlyEntryHistory::get()->count();
        $originalCategoryID = (int)$entry->CategoryID;
        $otherCategory = MashaFeedlyCategory::get()->filter('ID:not', $originalCategoryID)->first();
        $this->assertNotNull($otherCategory);
        $this->logOut();

        $create = $this->post('/__masha-feedly/createEntry', ['Content' => 'Anonymer Eintrag ohne Anmeldung']);
        $update = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$otherCategory->ID,
        ]);
        $commentCreate = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CommentText' => 'Anonymer Kommentar',
        ]);
        $commentEdit = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CommentAction' => 'edit',
            'CommentID' => (int)$comment->ID,
            'CommentText' => 'Manipulierter Text',
        ]);
        $commentDelete = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CommentAction' => 'delete',
            'CommentID' => (int)$comment->ID,
        ]);
        $privateList = $this->get('/__masha-feedly/listEntries?mode=all');

        foreach ([$create, $update, $commentCreate, $commentEdit, $commentDelete, $privateList] as $response) {
            $this->assertSame(403, $response->getStatusCode());
        }
        $this->assertSame($originalEntryCount, MashaFeedlyEntry::get()->count());
        $this->assertSame($originalCommentCount, MashaFeedlyComment::get()->count());
        $this->assertSame($originalHistoryCount, MashaFeedlyEntryHistory::get()->count());
        $this->assertSame($originalCategoryID, (int)MashaFeedlyEntry::get()->byID((int)$entry->ID)->CategoryID);
        $this->assertSame('Geschützter Ausgangskommentar', (string)MashaFeedlyComment::get()->byID((int)$comment->ID)->CommentText);
        $this->assertStringNotContainsString('Geschützter Ausgangskommentar', $privateList->getBody());
    }

    /** Prüft die Zugriffsgrenze aller JSON-Endpunkte für anonyme und nicht freigegebene Mitglieder. */
    public function testAccessMatrixDeniesAnonymousAndUnapprovedMembersAcrossEveryEndpoint(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $blocked = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowed);
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entry->Content = 'GESCHUETZTER-MATRIX-EINTRAG';
        $entry->write();
        $comment = MashaFeedlyComment::create([
            'AuthorName' => 'Matrix Test',
            'CommentText' => 'GESCHUETZTER-MATRIX-KOMMENTAR',
            'IsApproved' => true,
            'EntryID' => (int)$entry->ID,
            'AuthorMemberID' => (int)$allowed->ID,
        ]);
        $comment->write();
        $savedView = MashaFeedlySavedView::create([
            'Title' => 'GESCHUETZTE-MATRIX-ANSICHT',
            'Mode' => 'open',
            'MemberID' => (int)$allowed->ID,
        ]);
        $savedView->write();
        $otherCategory = MashaFeedlyCategory::get()->filter('ID:not', (int)$entry->CategoryID)->first();
        $this->assertNotNull($otherCategory);

        $entryCount = MashaFeedlyEntry::get()->count();
        $commentCount = MashaFeedlyComment::get()->count();
        $historyCount = MashaFeedlyEntryHistory::get()->count();
        $savedViewCount = MashaFeedlySavedView::get()->count();
        $readCount = MashaFeedlyEntryRead::get()->count();
        $relationCount = MashaFeedlyEntryRelation::get()->count();
        $attachmentCount = \KW\MashaFeedly\Model\MashaFeedlyAttachment::get()->count();
        $originalCategoryID = (int)$entry->CategoryID;
        $originalCommentText = (string)$comment->CommentText;
        $originalOnboarding = [(bool)$blocked->MashaFeedlyOnboardingCompleted, (bool)$blocked->MashaFeedlyShowOnboarding];

        $getRoutes = [
            '/__masha-feedly',
            '/__masha-feedly/savedViews',
            '/__masha-feedly/createEntry',
            '/__masha-feedly/updateEntry',
            '/__masha-feedly/findSimilarEntries?Content=GESCHUETZTER-MATRIX-EINTRAG',
            '/__masha-feedly/completeOnboarding',
            '/__masha-feedly/restartOnboarding',
            '/__masha-feedly/saveView',
            '/__masha-feedly/deleteView',
            '/__masha-feedly/markEntryRead',
            '/__masha-feedly-comment',
        ];
        // Jede öffentliche Listenansicht muss dieselbe Berechtigungsgrenze haben.
        // Dabei sind auch manager-exklusive Warteschlangen und ungelesene Aktivitäten enthalten.
        foreach ([
            'all', 'open', 'closed', 'page', 'page-open', 'mine', 'feedback', 'unread',
            'estimate-pending', 'estimate-approved',
        ] as $mode) {
            $getRoutes[] = '/__masha-feedly/listEntries?mode=' . $mode;
        }

        foreach (['anonymous', 'unapproved'] as $audience) {
            if ($audience === 'anonymous') {
                $this->logOut();
            } else {
                $this->logInAs($blocked);
            }
            $securityID = SecurityToken::getSecurityID();
            foreach ($getRoutes as $route) {
                $response = $this->get($route);
                $this->assertSame(403, $response->getStatusCode(), "$audience GET $route muss gesperrt sein.");
                $this->assertStringNotContainsString('GESCHUETZTER-MATRIX-', $response->getBody());
            }

            $postRoutes = [
                ['/__masha-feedly', ['ViewAction' => 'save', 'Title' => 'Unbefugt', 'Mode' => 'all']],
                ['/__masha-feedly', ['ViewAction' => 'delete', 'ViewID' => (string)$savedView->ID]],
                ['/__masha-feedly/savedViews', ['ViewAction' => 'save', 'Title' => 'Unbefugt', 'Mode' => 'all']],
                ['/__masha-feedly/saveView', ['Title' => 'Unbefugt', 'Mode' => 'all']],
                ['/__masha-feedly/deleteView', ['ViewID' => (string)$savedView->ID]],
                ['/__masha-feedly/createEntry', ['Content' => 'Unbefugter Eintrag']],
                ['/__masha-feedly/updateEntry', [
                    'EntryID' => (int)$entry->ID,
                    'CategoryID' => (int)$otherCategory->ID,
                    'RelationType' => 'related',
                    'RelatedEntryIDs' => [(int)$entry->ID + 1000],
                ]],
                ['/__masha-feedly/findSimilarEntries', ['Content' => 'GESCHUETZTER-MATRIX-EINTRAG', 'PageURL' => 'https://example.test/']],
                ['/__masha-feedly/completeOnboarding', []],
                ['/__masha-feedly/restartOnboarding', []],
                ['/__masha-feedly/markEntryRead', ['EntryID' => (int)$entry->ID]],
                ['/__masha-feedly-comment', ['EntryID' => (int)$entry->ID, 'CommentText' => 'Unbefugter Kommentar']],
                ['/__masha-feedly-comment', [
                    'EntryID' => (int)$entry->ID,
                    'CommentID' => (int)$comment->ID,
                    'CommentAction' => 'edit',
                    'CommentText' => 'Manipulierter Matrixkommentar',
                ]],
                ['/__masha-feedly-comment', [
                    'EntryID' => (int)$entry->ID,
                    'CommentID' => (int)$comment->ID,
                    'CommentAction' => 'delete',
                ]],
                ['/__masha-feedly-comment', [
                    'EntryID' => (int)$entry->ID,
                    'CommentID' => (int)$comment->ID,
                    'CommentAction' => 'react',
                    'ReactionEmoji' => '👍',
                ]],
            ];
            foreach ($postRoutes as [$route, $fields]) {
                $response = $this->post($route, ['SecurityID' => $securityID] + $fields);
                $this->assertSame(403, $response->getStatusCode(), "$audience POST $route muss gesperrt sein.");
                $this->assertStringNotContainsString('GESCHUETZTER-MATRIX-', $response->getBody());
            }

            // Uploads dürfen die Berechtigungsprüfung nicht umgehen oder Datensätze anlegen.
            $temporaryUpload = tempnam(sys_get_temp_dir(), 'masha-feedly-denied-upload-');
            file_put_contents($temporaryUpload, 'not-an-image');
            try {
                $_FILES['Attachments'] = [
                    'name' => ['unauthorized.png'],
                    'type' => ['image/png'],
                    'tmp_name' => [$temporaryUpload],
                    'error' => [UPLOAD_ERR_OK],
                    'size' => [filesize($temporaryUpload)],
                ];
                foreach ([
                    ['/__masha-feedly/createEntry', ['Content' => 'Unbefugter Upload-Eintrag']],
                    ['/__masha-feedly/updateEntry', [
                        'EntryID' => (int)$entry->ID,
                        'CategoryID' => (int)$otherCategory->ID,
                    ]],
                ] as [$route, $fields]) {
                    $response = $this->post($route, ['SecurityID' => $securityID] + $fields);
                    $this->assertSame(403, $response->getStatusCode(), "$audience POST $route mit Anhang muss gesperrt sein.");
                }
            } finally {
                unset($_FILES['Attachments']);
                unlink($temporaryUpload);
            }
        }

        $this->assertSame($entryCount, MashaFeedlyEntry::get()->count());
        $this->assertSame($commentCount, MashaFeedlyComment::get()->count());
        $this->assertSame($historyCount, MashaFeedlyEntryHistory::get()->count());
        $this->assertSame($savedViewCount, MashaFeedlySavedView::get()->count());
        $this->assertSame($readCount, MashaFeedlyEntryRead::get()->count());
        $this->assertSame($relationCount, MashaFeedlyEntryRelation::get()->count());
        $this->assertSame($attachmentCount, \KW\MashaFeedly\Model\MashaFeedlyAttachment::get()->count());
        $this->assertSame($originalCategoryID, (int)MashaFeedlyEntry::get()->byID((int)$entry->ID)->CategoryID);
        $this->assertSame($originalCommentText, (string)MashaFeedlyComment::get()->byID((int)$comment->ID)->CommentText);
        $blockedAfterRequests = Member::get()->byID((int)$blocked->ID);
        $this->assertSame($originalOnboarding, [
            (bool)$blockedAfterRequests->MashaFeedlyOnboardingCompleted,
            (bool)$blockedAfterRequests->MashaFeedlyShowOnboarding,
        ]);
    }

    /** Prüft den direkten Dateiabruf über HTTP zusätzlich zur Berechtigungsprüfung am DataObject. */
    public function testPrivateAttachmentURLIsDeniedOverHttpToAnonymousAndUnapprovedMembers(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $blocked = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowed);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        TestAssetStore::activate('masha-feedly-private-download-matrix-test');
        $temporaryImage = tempnam(sys_get_temp_dir(), 'masha-feedly-private-');
        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII=');
        file_put_contents($temporaryImage, $imageBytes);

        try {
            $attachment = MashaFeedlyAttachmentService::attachUploads([
                'name' => 'private-matrix.png',
                'type' => 'image/png',
                'tmp_name' => $temporaryImage,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($temporaryImage),
            ], $entry)[0];
            $file = File::get()->byID((int)$attachment->FileID);
            $this->assertNotNull($file);
            $this->assertTrue($file->canView($allowed));

            // Check unauthorized sessions first: a permitted protected-file request
            // can grant the URL to that same session in SilverStripe's asset store.
            $this->logInAs($blocked);
            $blockedResponse = $this->get($file->getURL());
            $this->assertNotSame($imageBytes, $blockedResponse->getBody());

            $this->logOut();
            $anonymousResponse = $this->get($file->getURL());
            $this->assertNotSame($imageBytes, $anonymousResponse->getBody());

            $this->logInAs($allowed);
            $allowedResponse = $this->get($file->getURL());
            $this->assertSame(200, $allowedResponse->getStatusCode());
            $this->assertSame($imageBytes, $allowedResponse->getBody());
        } finally {
            TestAssetStore::reset();
            unlink($temporaryImage);
        }
    }

    /** Prüft zulässige Uploadgrenzen sowie ungültige und manipulierte Dateitypen. */
    public function testAttachmentValidationRejectsInvalidTypesAndEnforcesExactSizeLimits(): void
    {
        $temporaryFiles = [];
        $makeFile = static function (string $contents) use (&$temporaryFiles): string {
            $path = tempnam(sys_get_temp_dir(), 'masha-feedly-upload-validation-');
            file_put_contents($path, $contents);
            $temporaryFiles[] = $path;
            return $path;
        };
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII=');
        $smallPng = $makeFile($pngBytes);

        try {
            $invalidExtension = MashaFeedlyAttachmentService::validateUploads([
                'name' => 'script.php', 'type' => 'application/x-php', 'tmp_name' => $smallPng,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($smallPng),
            ]);
            $this->assertStringContainsString('Erlaubt sind Bilder', (string)$invalidExtension);

            $spoofedExtension = MashaFeedlyAttachmentService::validateUploads([
                'name' => 'bild.pdf', 'type' => 'application/pdf', 'tmp_name' => $smallPng,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($smallPng),
            ]);
            $this->assertStringContainsString('Dateityp passt nicht', (string)$spoofedExtension);

            $exactLimitPng = $makeFile($pngBytes . str_repeat(' ', 10_485_760 - strlen($pngBytes)));
            $singleAtLimit = MashaFeedlyAttachmentService::validateUploads([
                'name' => 'grenze.png', 'type' => 'image/png', 'tmp_name' => $exactLimitPng,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($exactLimitPng),
            ]);
            $this->assertNull($singleAtLimit, 'Eine einzelne Datei mit genau 10 MB ist zulässig.');

            $tooLarge = $makeFile($pngBytes . str_repeat(' ', 10_485_761 - strlen($pngBytes)));
            $singleOverLimit = MashaFeedlyAttachmentService::validateUploads([
                'name' => 'zu-gross.png', 'type' => 'image/png', 'tmp_name' => $tooLarge,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($tooLarge),
            ]);
            $this->assertStringContainsString('höchstens 10 MB', (string)$singleOverLimit);

            $exactAggregateLimit = MashaFeedlyAttachmentService::validateUploads([
                [
                    'name' => 'eins.png', 'type' => 'image/png', 'tmp_name' => $exactLimitPng,
                    'error' => UPLOAD_ERR_OK, 'size' => filesize($exactLimitPng),
                ],
                [
                    'name' => 'zwei.png', 'type' => 'image/png', 'tmp_name' => $exactLimitPng,
                    'error' => UPLOAD_ERR_OK, 'size' => filesize($exactLimitPng),
                ],
            ]);
            $this->assertNull($exactAggregateLimit, 'Zwei Dateien mit zusammen genau 20 MB sind zulässig.');

            $overAggregateLimit = MashaFeedlyAttachmentService::validateUploads([
                [
                    'name' => 'eins.png', 'type' => 'image/png', 'tmp_name' => $exactLimitPng,
                    'error' => UPLOAD_ERR_OK, 'size' => filesize($exactLimitPng),
                ],
                [
                    'name' => 'zwei.png', 'type' => 'image/png', 'tmp_name' => $exactLimitPng,
                    'error' => UPLOAD_ERR_OK, 'size' => filesize($exactLimitPng),
                ],
                [
                    'name' => 'drei.png', 'type' => 'image/png', 'tmp_name' => $smallPng,
                    'error' => UPLOAD_ERR_OK, 'size' => filesize($smallPng),
                ],
            ]);
            $this->assertStringContainsString('insgesamt maximal 20 MB', (string)$overAggregateLimit);
        } finally {
            foreach ($temporaryFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /** Ungültige Uploads müssen Create und Update vor jeder Datenänderung abbrechen. */
    public function testCreateAndUpdateRejectSpoofedAttachmentWithoutPersistingChanges(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $originalContent = (string)$entry->Content;
        $entryCount = MashaFeedlyEntry::get()->count();
        $attachmentCount = \KW\MashaFeedly\Model\MashaFeedlyAttachment::get()->count();
        $temporaryImage = tempnam(sys_get_temp_dir(), 'masha-feedly-spoofed-upload-');
        file_put_contents($temporaryImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII='));
        $originalFiles = $_FILES;
        $_FILES['Attachments'] = [
            'name' => ['umbenannt.pdf'],
            'type' => ['application/pdf'],
            'tmp_name' => [$temporaryImage],
            'error' => [UPLOAD_ERR_OK],
            'size' => [filesize($temporaryImage)],
        ];

        try {
            $this->logInAs($member);
            $create = $this->post('/__masha-feedly/createEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'Content' => 'Eintrag mit gefälschtem PDF-Anhang',
            ]);
            $update = $this->post('/__masha-feedly/updateEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'EntryID' => (int)$entry->ID,
                'CategoryID' => (int)$entry->CategoryID,
            ]);
        } finally {
            $_FILES = $originalFiles;
            unlink($temporaryImage);
        }

        foreach ([$create, $update] as $response) {
            $this->assertSame(400, $response->getStatusCode());
            $data = json_decode($response->getBody(), true);
            $this->assertFalse($data['success']);
            $this->assertStringContainsString('Dateityp passt nicht', $data['message']);
        }
        $this->assertSame($entryCount, MashaFeedlyEntry::get()->count());
        $this->assertSame($attachmentCount, \KW\MashaFeedly\Model\MashaFeedlyAttachment::get()->count());
        $this->assertSame($originalContent, (string)MashaFeedlyEntry::get()->byID((int)$entry->ID)->Content);
        $this->assertCount(0, $entry->Attachments());
    }

    /** Ein geteilter Direktlink liefert den Bug nur für Mitglieder mit Masha-Feedly-Freigabe. */
    public function testSharedBugLinkRequiresMashaFeedlyAccess(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $blocked = $this->objFromFixture(Member::class, 'notAllowed');
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entry->PageURL = 'https://example.test/kontakt';
        $entry->write();
        $this->allowMember($allowed);
        $sharedPath = '/__masha-feedly/listEntries?mode=page&PageURL=' . rawurlencode((string)$entry->PageURL)
            . '&masha-feedly-entry=' . (int)$entry->ID;

        $this->logInAs($allowed);
        $permitted = $this->get($sharedPath);
        $this->assertSame(200, $permitted->getStatusCode());
        $payload = json_decode($permitted->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame((int)$entry->ID, (int)$payload['entries'][0]['id']);

        $this->logInAs($blocked);
        $forbidden = $this->get($sharedPath);
        $this->assertSame(403, $forbidden->getStatusCode());
        $this->assertStringNotContainsString((string)$entry->ID, $forbidden->getBody());
    }

    /** Prüft Datei-Upload über den echten Erstellungsendpunkt und die Zugriffsbeschränkung. */
    public function testAllowedMemberCanCreateEntryWithPrivateImageAttachment(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $blockedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $otherAllowedMember = $this->objFromFixture(Member::class, 'normalize');
        $this->allowMember($member);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID, (int)$otherAllowedMember->ID]);
        $config->write();
        MashaFeedlyCategory::ensureDefaultCategories();
        TestAssetStore::activate('masha-feedly-entry-attachment-functional-test');
        $temporaryImage = tempnam(sys_get_temp_dir(), 'masha-feedly-entry-attachment-');
        file_put_contents($temporaryImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII='));
        $originalFiles = $_FILES;
        $_FILES['Attachments'] = [
            'name' => ['screenshot.png'],
            'type' => ['image/png'],
            'tmp_name' => [$temporaryImage],
            'error' => [UPLOAD_ERR_OK],
            'size' => [filesize($temporaryImage)],
        ];
        $otherUserList = [];

        try {
            $this->logInAs($member);
            $response = $this->post('/__masha-feedly/createEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'Content' => 'Bild mit privatem Anhang',
            ]);
            $data = json_decode($response->getBody(), true);
            $this->logInAs($otherAllowedMember);
            $otherUserList = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        } finally {
            $_FILES = $originalFiles;
            TestAssetStore::reset();
            unlink($temporaryImage);
        }

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['attachments']);
        $this->assertSame('image/png', $data['attachments'][0]['mimeType']);
        $this->assertNotEmpty($data['attachments'][0]['url']);
        $entry = MashaFeedlyEntry::get()->byID((int)$data['entryID']);
        $this->assertCount(1, $entry->Attachments());
        $this->assertSame('screenshot.png', (string)$entry->Attachments()->first()->OriginalName);
        $file = File::get()->byID((int)$entry->Attachments()->first()->FileID);
        $this->assertTrue($file->canView($member));
        $this->assertTrue($file->canView($otherAllowedMember));
        $this->assertFalse($file->canView($blockedMember));
        $this->logOut();
        $this->assertFalse($file->canView(), 'Anhänge dürfen ohne angemeldetes, freigegebenes Mitglied nicht sichtbar sein.');
        $otherUserEntry = array_values(array_filter(
            $otherUserList['entries'],
            static fn(array $item): bool => (int)$item['id'] === (int)$entry->ID
        ))[0];
        $this->assertSame('image/png', $otherUserEntry['attachments'][0]['mimeType']);
        $this->assertSame($data['attachments'][0]['url'], $otherUserEntry['attachments'][0]['url']);
        $attachmentEvents = array_values(array_filter($otherUserEntry['history'], static fn(array $item): bool => $item['type'] === 'attachment'));
        $this->assertCount(1, $attachmentEvents);
        $this->assertSame('screenshot.png', $attachmentEvents[0]['newValue']);
        $this->assertSame('Erika Muster', $attachmentEvents[0]['actor']);
    }

    /** Unerlaubte Benutzer dürfen über Create und Update keine Dateien einschleusen. */
    public function testMemberWithoutAccessCannotUploadAttachments(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $blocked = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowed);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entryCount = MashaFeedlyEntry::get()->count();
        $attachmentCount = \KW\MashaFeedly\Model\MashaFeedlyAttachment::get()->count();
        $historyCount = MashaFeedlyEntryHistory::get()->count();
        TestAssetStore::activate('masha-feedly-denied-upload-functional-test');
        $temporaryImage = tempnam(sys_get_temp_dir(), 'masha-feedly-denied-upload-');
        file_put_contents($temporaryImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII='));
        $originalFiles = $_FILES;
        $_FILES['Attachments'] = [
            'name' => ['nicht-erlaubt.png'],
            'type' => ['image/png'],
            'tmp_name' => [$temporaryImage],
            'error' => [UPLOAD_ERR_OK],
            'size' => [filesize($temporaryImage)],
        ];

        try {
            $this->logInAs($blocked);
            $create = $this->post('/__masha-feedly/createEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'Content' => 'Unerlaubter Eintrag mit Upload',
            ]);
            $update = $this->post('/__masha-feedly/updateEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'EntryID' => (int)$entry->ID,
                'CategoryID' => (int)$entry->CategoryID,
            ]);
        } finally {
            $_FILES = $originalFiles;
            TestAssetStore::reset();
            unlink($temporaryImage);
        }

        $this->assertSame(403, $create->getStatusCode());
        $this->assertSame(403, $update->getStatusCode());
        $this->assertSame($entryCount, MashaFeedlyEntry::get()->count());
        $this->assertSame($attachmentCount, \KW\MashaFeedly\Model\MashaFeedlyAttachment::get()->count());
        $this->assertSame($historyCount, MashaFeedlyEntryHistory::get()->count());
        $this->assertCount(0, $entry->Attachments());
    }

    /** Prüft, dass bestehende Einträge im Update-Endpunkt zusätzliche Anhänge erhalten. */
    public function testAllowedMemberCanAddAnotherAttachmentToExistingEntry(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $blockedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($member);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        TestAssetStore::activate('masha-feedly-append-attachment-functional-test');
        $temporaryImage = tempnam(sys_get_temp_dir(), 'masha-feedly-append-attachment-');
        file_put_contents($temporaryImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII='));
        $originalFiles = $_FILES;
        $_FILES['Attachments'] = [
            'name' => ['nachtrag.png'],
            'type' => ['image/png'],
            'tmp_name' => [$temporaryImage],
            'error' => [UPLOAD_ERR_OK],
            'size' => [filesize($temporaryImage)],
        ];

        try {
            $this->logInAs($member);
            $response = $this->post('/__masha-feedly/updateEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'EntryID' => (int)$entry->ID,
                'CategoryID' => (int)$entry->CategoryID,
            ]);
        } finally {
            $_FILES = $originalFiles;
            TestAssetStore::reset();
            unlink($temporaryImage);
        }

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['attachments']);
        $this->assertSame('image/png', $data['attachments'][0]['mimeType']);
        $this->assertNotEmpty($data['attachments'][0]['url']);
        $savedEntry = MashaFeedlyEntry::get()->byID((int)$entry->ID);
        $this->assertCount(1, $savedEntry->Attachments());
        $this->assertSame('nachtrag.png', (string)$savedEntry->Attachments()->first()->OriginalName);
        $file = File::get()->byID((int)$savedEntry->Attachments()->first()->FileID);
        $this->assertTrue($file->canView($member));
        $this->assertFalse($file->canView($blockedMember));
        $listData = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $listedEntry = array_values(array_filter($listData['entries'], static fn(array $item): bool => (int)$item['id'] === (int)$entry->ID))[0];
        $attachmentEvents = array_values(array_filter($listedEntry['history'], static fn(array $item): bool => $item['type'] === 'attachment'));
        $this->assertCount(1, $attachmentEvents);
        $this->assertSame('nachtrag.png', $attachmentEvents[0]['newValue']);
    }

    /** Prüft den Seitenfilter, den persönlichen Filter und die sortierten Kategorien der Widget-Übersicht. */
    public function testEntryListSeparatesCurrentPageAndPersonalAssignments(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $category = MashaFeedlyCategory::defaultCategory();
        $priority = MashaFeedlyPriority::defaultPriority();
        $onPageAssigned = MashaFeedlyEntry::create([
            'Content' => 'Fehler auf aktueller Seite',
            'CategoryID' => (int)$category->ID,
            'PriorityID' => (int)$priority->ID,
            'PageURL' => 'https://example.test/kontakt',
            'EntryDate' => '2026-10-01 10:00:00',
            'ElementSelector' => '#contact-button',
        ]);
        $onPageAssigned->write();
        $onPageAssigned->AssignedMembers()->setByIDList([(int)$member->ID]);
        $onPageUnassigned = MashaFeedlyEntry::create([
            'Content' => 'Noch nicht zugewiesen',
            'CategoryID' => (int)$category->ID,
            'PageURL' => 'https://example.test/kontakt',
            'EntryDate' => '2026-10-01 09:00:00',
        ]);
        $onPageUnassigned->write();
        $elsewhereAssigned = MashaFeedlyEntry::create([
            'Content' => 'Eintrag von einer anderen Seite',
            'CategoryID' => (int)$category->ID,
            'PageURL' => 'https://example.test/start',
            'EntryDate' => '2026-10-01 11:00:00',
        ]);
        $elsewhereAssigned->write();
        $elsewhereAssigned->AssignedMembers()->setByIDList([(int)$member->ID]);
        $this->logInAs($member);

        $pageResponse = $this->get('/__masha-feedly/listEntries?mode=page&PageURL=' . rawurlencode('https://example.test/kontakt/?campaign=mailing#formular'));
        $pageData = json_decode($pageResponse->getBody(), true);
        $this->assertSame(200, $pageResponse->getStatusCode());
        $this->assertSame(2, $pageData['pageCount']);
        $this->assertSame(2, $pageData['pageOpenCount']);
        $this->assertSame((int)$pageData['totalCount'], $pageData['openCount']);
        $this->assertSame((int)MashaFeedlyEntry::get()->count(), $pageData['totalCount']);
        $this->assertCount(2, $pageData['entries']);
        $this->assertSame((int)$onPageAssigned->ID, $pageData['entries'][0]['id']);
        $this->assertSame('#contact-button', $pageData['entries'][0]['selector']);
        $this->assertSame([(int)$member->ID], $pageData['entries'][0]['assignedMemberIDs']);
        $this->assertFalse($pageData['entries'][0]['isClosed']);
        $this->assertSame((string)$category->Title, $pageData['entries'][0]['categoryTitle']);
        $this->assertSame((string)$priority->Title, $pageData['entries'][0]['priorityTitle']);
        $creationEvent = array_values(array_filter(
            $pageData['entries'][0]['history'],
            static fn(array $item): bool => $item['type'] === 'created'
        ))[0];
        $this->assertSame($creationEvent['actor'], $pageData['entries'][0]['createdBy']);
        $this->assertSame($creationEvent['created'], $pageData['entries'][0]['createdAt']);
        $creator = Member::get()->byID($onPageAssigned->creatorMemberID());
        $this->assertArrayHasKey('createdByInitials', $pageData['entries'][0]);
        $this->assertArrayHasKey('createdByColor', $pageData['entries'][0]);
        $this->assertArrayHasKey('createdByImageURL', $pageData['entries'][0]);
        if ($creator) {
            $this->assertSame($creator->getMashaFeedlyInitials(), $pageData['entries'][0]['createdByInitials']);
            $this->assertSame($creator->getMashaFeedlyDisplayColor(), $pageData['entries'][0]['createdByColor']);
        }
        $this->assertSame($member->getName(), $pageData['entries'][0]['assignees'][0]['name']);
        $this->assertSame('EM', $pageData['entries'][0]['assignees'][0]['initials']);
        $this->assertNotEmpty($pageData['entries'][0]['assignees'][0]['color']);

        $mineResponse = $this->get('/__masha-feedly/listEntries?mode=mine&PageURL=' . rawurlencode('https://example.test/kontakt/?campaign=mailing#formular'));
        $mineData = json_decode($mineResponse->getBody(), true);
        $this->assertSame(200, $mineResponse->getStatusCode());
        $this->assertSame(2, $mineData['mineCount']);
        $this->assertCount(2, $mineData['entries']);
        $this->assertSame((int)$elsewhereAssigned->ID, $mineData['entries'][0]['id']);
        $categoryRoles = array_column($mineData['categories'], 'systemKey');
        sort($categoryRoles);
        $this->assertSame([
            'archive',
            'backlog',
            'doing',
            'done',
            'feedback',
            'restricted_estimate',
            'restricted_estimate',
            'todo',
        ], $categoryRoles);
        $doneCategoryData = array_values(array_filter(
            $mineData['categories'],
            static fn(array $category): bool => $category['title'] === 'Done'
        ))[0];
        $this->assertTrue($doneCategoryData['isClosed']);

        // Der Wechsel eines Seiteneintrags nach Done entfernt ihn aus der offenen Fehlerzahl.
        $doneCategory = MashaFeedlyCategory::get()->filter('Title', 'Done')->first();
        $onPageUnassigned->CategoryID = (int)$doneCategory->ID;
        $onPageUnassigned->write();
        $updatedResponse = $this->get('/__masha-feedly/listEntries?mode=page&PageURL=' . rawurlencode('https://example.test/kontakt'));
        $updatedData = json_decode($updatedResponse->getBody(), true);
        $this->assertSame(1, $updatedData['pageOpenCount']);
        $this->assertSame((int)$updatedData['totalCount'] - 1, $updatedData['openCount']);
        $updatedEntryData = array_values(array_filter(
            $updatedData['entries'],
            static fn(array $item): bool => (int)$item['id'] === (int)$onPageUnassigned->ID
        ))[0];
        $this->assertTrue($updatedEntryData['isClosed']);

        $allResponse = $this->get('/__masha-feedly/listEntries?mode=all&PageURL=' . rawurlencode('https://example.test/kontakt'));
        $allData = json_decode($allResponse->getBody(), true);
        $this->assertSame(200, $allResponse->getStatusCode());
        $this->assertSame('all', $allData['mode']);
        $this->assertSame((int)MashaFeedlyEntry::get()->count(), count($allData['entries']));

        $openResponse = $this->get('/__masha-feedly/listEntries?mode=open&PageURL=' . rawurlencode('https://example.test/kontakt'));
        $openData = json_decode($openResponse->getBody(), true);
        $this->assertSame(200, $openResponse->getStatusCode());
        $this->assertSame('open', $openData['mode']);
        $this->assertSame($updatedData['openCount'], $openData['openCount']);
        $this->assertNotEmpty($openData['entries']);
        $this->assertNotContains(true, array_column($openData['entries'], 'isClosed'), 'Der Offen-Filter darf keine abgeschlossenen Einträge ausliefern.');

        $closedResponse = $this->get('/__masha-feedly/listEntries?mode=closed&PageURL=' . rawurlencode('https://example.test/kontakt'));
        $closedData = json_decode($closedResponse->getBody(), true);
        $this->assertSame(200, $closedResponse->getStatusCode());
        $this->assertSame('closed', $closedData['mode']);
        $this->assertSame($updatedData['totalCount'], $closedData['totalCount']);
        $this->assertNotEmpty($closedData['entries']);
        $this->assertNotContains(false, array_column($closedData['entries'], 'isClosed'), 'Der Filter für abgeschlossene Einträge darf keine offenen Einträge ausliefern.');
        $this->assertSame([], array_intersect(array_column($openData['entries'], 'id'), array_column($closedData['entries'], 'id')));
    }

    /** Prüft, dass Status und Zuständigkeit änderbar sind, während der Meldungstext erhalten bleibt. */
    public function testAllowedMemberCanUpdateStatusAndAssigneesWithoutChangingContent(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $blockedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        $categories = MashaFeedlyCategory::get()->sort('Sort ASC')->toArray();
        $entry = MashaFeedlyEntry::create([
            'Content' => 'Originaler Fehlertext bleibt unverändert.',
            'CategoryID' => (int)$categories[0]->ID,
            'PageURL' => 'https://example.test/kontakt',
        ]);
        $entry->write();
        $this->logInAs($member);
        $doneCategory = MashaFeedlyCategory::get()->filter('Title', 'Done')->first();
        $this->assertNotNull($doneCategory);

        $response = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$doneCategory->ID,
            'AssignedMemberIDs' => [(int)$member->ID, (int)$blockedMember->ID],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getBody(), true)['success']);
        $entry = MashaFeedlyEntry::get()->byID((int)$entry->ID);
        $this->assertSame((int)$doneCategory->ID, (int)$entry->CategoryID);
        $this->assertSame('Originaler Fehlertext bleibt unverändert.', strip_tags((string)$entry->Content));
        $this->assertSame([(int)$member->ID], array_map('intval', $entry->AssignedMembers()->column('ID')));
        $listData = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $entryPayload = array_values(array_filter(
            $listData['entries'],
            fn(array $item): bool => (int)$item['id'] === (int)$entry->ID
        ))[0];
        $history = $entryPayload['history'];
        $this->assertCount(3, $history);
        $this->assertSame('assignees', $history[0]['type']);
        $this->assertSame('', $history[0]['oldValue']);
        $this->assertSame('Erika Muster', $history[0]['newValue']);
        $this->assertSame('Erika Muster', $history[0]['actor']);
        $this->assertNotEmpty($history[0]['created']);
        $this->assertMatchesRegularExpression('/Z$/', $history[0]['created']);
        $this->assertSame('status', $history[1]['type']);
        $this->assertSame('Backlog', $history[1]['oldValue']);
        $this->assertSame('Done', $history[1]['newValue']);
        $this->assertSame('created', $history[2]['type']);
    }

    /** Reicht Done fremder Einträge zur Bestätigung ein; die erstellende Person kann danach freigeben. */
    public function testAnotherMembersDoneEntryWaitsForCreatorFeedback(): void
    {
        $creator = $this->objFromFixture(Member::class, 'allowed');
        $reviewer = $this->objFromFixture(Member::class, 'notAllowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$creator->ID, (int)$reviewer->ID]);
        $config->write();
        MashaFeedlyCategory::ensureDefaultCategories();
        $backlog = MashaFeedlyCategory::get()->filter('Title', 'Backlog')->first();
        $done = MashaFeedlyCategory::get()->filter('Title', 'Done')->first();
        $feedback = MashaFeedlyCategory::get()->filter('Title', 'Feedback')->first();
        $feedback->Title = 'Rückmeldung';
        $feedback->write();

        $this->logInAs($creator);
        $entry = MashaFeedlyEntry::create([
            'Content' => 'Fehler durch Erstellerin angelegt',
            'CategoryID' => (int)$backlog->ID,
        ]);
        $entry->write();
        $this->assertSame((int)$creator->ID, $entry->creatorMemberID());

        $this->logInAs($reviewer);
        $doing = MashaFeedlyCategory::get()->filter('Title', 'Doing')->first();
        $ordinaryUpdate = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$doing->ID,
        ]);
        $this->assertSame((int)$doing->ID, json_decode($ordinaryUpdate->getBody(), true)['categoryID']);

        $response = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$done->ID,
        ]);
        $data = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame((int)$feedback->ID, $data['categoryID']);
        $this->assertSame('Rückmeldung', $data['categoryTitle']);
        $this->assertFalse($data['categoryIsClosed']);
        $this->assertFalse($data['celebrateCompletion']);
        $this->assertSame('Der Eintrag wartet jetzt auf die Freigabe durch die erstellende Person.', $data['message']);
        $entry = MashaFeedlyEntry::get()->byID((int)$entry->ID);
        $this->assertSame((int)$feedback->ID, (int)$entry->CategoryID);
        $statusChange = MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => 'status',
            'NewValue' => 'Rückmeldung',
        ])->first();
        $this->assertSame('Doing', (string)$statusChange->OldValue);
        $this->assertSame('Rückmeldung', (string)$statusChange->NewValue);
        $this->assertSame((int)$reviewer->ID, (int)$statusChange->ActorMemberID);

        $this->logInAs($creator);
        $approval = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$done->ID,
        ]);
        $approvalData = json_decode($approval->getBody(), true);
        $this->assertSame(200, $approval->getStatusCode());
        $this->assertSame((int)$done->ID, $approvalData['categoryID']);
        $this->assertTrue($approvalData['celebrateCompletion']);
        $this->assertSame((int)$done->ID, (int)MashaFeedlyEntry::get()->byID((int)$entry->ID)->CategoryID);
    }

    /** Ein selbst erstellter Eintrag darf direkt abgeschlossen werden. */
    public function testCreatorCanCloseOwnEntryWithoutFeedbackRoundTrip(): void
    {
        $creator = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($creator);
        MashaFeedlyCategory::ensureDefaultCategories();
        $this->logInAs($creator);
        $entry = MashaFeedlyEntry::create([
            'Content' => 'Mein eigener Fehler',
            'CategoryID' => (int)MashaFeedlyCategory::get()->filter('Title', 'Backlog')->first()->ID,
        ]);
        $entry->write();
        $done = MashaFeedlyCategory::get()->filter('Title', 'Done')->first();
        $done->Title = 'Fertig';
        $done->write();

        $response = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$done->ID,
        ]);
        $data = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame((int)$done->ID, $data['categoryID']);
        $this->assertSame('Fertig', $data['categoryTitle']);
        $this->assertTrue($data['celebrateCompletion']);
        $this->assertSame((int)$done->ID, (int)MashaFeedlyEntry::get()->byID((int)$entry->ID)->CategoryID);
    }

    /** Prüft das Speichern von Kommentaren und die sichere Ausgabe freigegebener Kommentare. */
    public function testAllowedMemberCanCommentOnEntry(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $other = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($member, $other);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        MashaFeedlyEntryRead::markAsSeen($entry, $member);
        MashaFeedlyEntryRead::markAsSeen($entry, $other);
        $this->logInAs($member);

        $response = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CommentText' => "Bitte den Abstand prüfen 😅. Siehe https://example.test/doku\nDanke! 👍",
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Erika Muster', $data['comment']['author']);
        $this->assertSame("Bitte den Abstand prüfen 😅. Siehe https://example.test/doku\nDanke! 👍", $data['comment']['text']);
        $this->assertNotEmpty($data['comment']['created']);
        $comment = MashaFeedlyComment::get()->byID((int)$data['comment']['id']);
        $this->assertTrue((bool)$comment->IsApproved);
        $this->assertSame((int)$entry->ID, (int)$comment->EntryID);
        $this->assertSame((int)$member->ID, (int)$comment->AuthorMemberID);
        $this->assertNotContains((int)$entry->ID, MashaFeedlyEntryRead::unreadEntryIDs($member), 'Der Kommentator hat die eigene Aktivität bereits gesehen.');
        $this->assertContains((int)$entry->ID, MashaFeedlyEntryRead::unreadEntryIDs($other), 'Der andere freigegebene Benutzer soll den neuen Kommentar als ungelesene Aktivität sehen.');

        $list = $this->get('/__masha-feedly/listEntries?mode=all');
        $listedEntry = json_decode($list->getBody(), true)['entries'][0];
        $this->assertCount(1, $listedEntry['comments']);
        $this->assertSame($data['comment']['id'], $listedEntry['comments'][0]['id']);
        $this->assertTrue($listedEntry['comments'][0]['canManage']);
        $this->assertSame($data['comment']['text'], $listedEntry['comments'][0]['text']);
        $commentEvents = array_values(array_filter($listedEntry['history'], static fn(array $item): bool => $item['type'] === 'comment'));
        $this->assertCount(1, $commentEvents);
        $this->assertSame($data['comment']['text'], $commentEvents[0]['newValue']);
        $this->assertSame('Erika Muster', $commentEvents[0]['actor']);

        $this->logInAs($other);
        $forbidden = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID,
            'CommentAction' => 'edit', 'CommentID' => (int)$comment->ID, 'CommentText' => 'Fremder Text',
        ]);
        $this->assertSame(403, $forbidden->getStatusCode());

        $this->logInAs($member);
        $edited = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID,
            'CommentAction' => 'edit', 'CommentID' => (int)$comment->ID, 'CommentText' => 'Aktualisierter Kommentar',
        ]);
        $this->assertSame(200, $edited->getStatusCode());
        $this->assertSame('Aktualisierter Kommentar', json_decode($edited->getBody(), true)['comment']['text']);
        $this->assertTrue(json_decode($edited->getBody(), true)['comment']['edited']);
        $this->assertTrue((bool)MashaFeedlyComment::get()->byID((int)$comment->ID)->WasEdited);
        $editedHistory = MashaFeedlyEntryHistory::get()->filter(['EntryID' => (int)$entry->ID, 'ChangeType' => 'comment_edited'])->first();
        $this->assertSame("Bitte den Abstand prüfen 😅. Siehe https://example.test/doku\nDanke! 👍", (string)$editedHistory->OldValue);
        $this->assertSame('Aktualisierter Kommentar', (string)$editedHistory->NewValue);
        $legacyComment = MashaFeedlyComment::get()->byID((int)$comment->ID);
        $legacyComment->WasEdited = false;
        $legacyComment->Created = date('Y-m-d H:i:s', time() - 120);
        $legacyComment->LastEdited = date('Y-m-d H:i:s');
        $legacyComment->write();
        $reloadedEntries = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true)['entries'];
        $reloadedComment = $reloadedEntries[0]['comments'][0];
        $this->assertTrue($reloadedComment['edited']);

        $deleted = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID,
            'CommentAction' => 'delete', 'CommentID' => (int)$comment->ID,
        ]);
        $this->assertSame(200, $deleted->getStatusCode());
        $this->assertNull(MashaFeedlyComment::get()->byID((int)$comment->ID));
        $deletedHistory = MashaFeedlyEntryHistory::get()->filter(['EntryID' => (int)$entry->ID, 'ChangeType' => 'comment_deleted'])->first();
        $this->assertSame('Aktualisierter Kommentar', (string)$deletedHistory->OldValue);
    }

    /** Prüft Reaktionsauswahl, Umschalten, Berechtigungen und sichere Zuordnung zum Kommentar. */
    public function testCommentReactionsArePersonalToggleableAndRestricted(): void
    {
        $author = $this->objFromFixture(Member::class, 'allowed');
        $reactor = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($author, $reactor);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $comment = MashaFeedlyComment::create([
            'EntryID' => (int)$entry->ID,
            'AuthorMemberID' => (int)$author->ID,
            'AuthorName' => $author->getName(),
            'CommentText' => 'Das prüfen wir gleich 🙂',
            'IsApproved' => true,
        ]);
        $comment->write();

        $this->logInAs($reactor);
        $send = fn(string $emoji, int $entryID = 0) => $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => $entryID ?: (int)$entry->ID,
            'CommentAction' => 'react',
            'CommentID' => (int)$comment->ID,
            'ReactionEmoji' => $emoji,
        ]);

        $added = $send('👍');
        $this->assertSame(200, $added->getStatusCode());
        $summary = json_decode($added->getBody(), true)['reactions'];
        $thumbsUp = array_values(array_filter($summary, static fn(array $item): bool => $item['emoji'] === '👍'))[0];
        $this->assertSame(1, $thumbsUp['count']);
        $this->assertTrue($thumbsUp['selected']);
        $this->assertSame(1, MashaFeedlyCommentReaction::get()->count());
        $addedHistory = MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => 'comment_reaction',
        ])->first();
        $this->assertSame('', (string)$addedHistory->OldValue);
        $this->assertSame('👍', (string)$addedHistory->NewValue);
        $this->assertSame((int)$comment->ID, (int)$addedHistory->RelatedID);
        $this->assertSame($reactor->getName(), (string)$addedHistory->ActorName);

        $replaced = $send('❤️');
        $replacementSummary = json_decode($replaced->getBody(), true)['reactions'];
        $thumbsUp = array_values(array_filter($replacementSummary, static fn(array $item): bool => $item['emoji'] === '👍'))[0];
        $heart = array_values(array_filter($replacementSummary, static fn(array $item): bool => $item['emoji'] === '❤️'))[0];
        $this->assertSame(0, $thumbsUp['count'], 'Die vorherige eigene Reaktion wird beim Wechsel entfernt.');
        $this->assertFalse($thumbsUp['selected']);
        $this->assertSame(1, $heart['count']);
        $this->assertTrue($heart['selected']);
        $this->assertSame(1, MashaFeedlyCommentReaction::get()->count(), 'Pro Person und Kommentar bleibt genau eine Reaktion gespeichert.');
        $switchedHistory = MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => 'comment_reaction',
        ])->sort('ID DESC')->first();
        $this->assertSame('👍', (string)$switchedHistory->OldValue);
        $this->assertSame('❤️', (string)$switchedHistory->NewValue);

        $removed = $send('❤️');
        $heart = array_values(array_filter(json_decode($removed->getBody(), true)['reactions'], static fn(array $item): bool => $item['emoji'] === '❤️'))[0];
        $this->assertSame(0, $heart['count']);
        $this->assertFalse($heart['selected']);
        $removedHistory = MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => 'comment_reaction',
        ])->sort('ID DESC')->first();
        $this->assertSame('❤️', (string)$removedHistory->OldValue);
        $this->assertSame('', (string)$removedHistory->NewValue);
        $send('😂');
        $this->assertSame(1, MashaFeedlyCommentReaction::get()->count());
        $invalid = $send('<script>');
        $this->assertSame(400, $invalid->getStatusCode());

        $this->logInAs($author);
        $visible = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true)['entries'];
        $listed = array_values(array_filter($visible, static fn(array $item): bool => $item['id'] === (int)$entry->ID))[0]['comments'][0];
        $this->assertCount(6, $listed['reactions']);
        $authorHeart = array_values(array_filter($listed['reactions'], static fn(array $item): bool => $item['emoji'] === '❤️'))[0];
        $this->assertSame(0, $authorHeart['count']);
        $this->assertFalse($authorHeart['selected'], 'Die Auswahl eines anderen Mitglieds bleibt persönlich.');
        $laugh = array_values(array_filter($listed['reactions'], static fn(array $item): bool => $item['emoji'] === '😂'))[0];
        $this->assertSame(1, $laugh['count']);
        $this->assertFalse($laugh['selected']);

        $wrongEntry = MashaFeedlyEntry::create([
            'Content' => 'Anderer Eintrag', 'PageURL' => 'https://example.test/',
            'CategoryID' => (int)$entry->CategoryID, 'PriorityID' => (int)$entry->PriorityID,
        ]);
        $wrongEntry->write();
        $mismatch = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$wrongEntry->ID,
            'CommentAction' => 'react', 'CommentID' => (int)$comment->ID, 'ReactionEmoji' => '🙏',
        ]);
        $this->assertSame(404, $mismatch->getStatusCode(), 'Ein Kommentar darf nicht über einen fremden Eintrag adressiert werden.');
        $this->assertSame(1, MashaFeedlyCommentReaction::get()->count());

        $authorReaction = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID,
            'CommentAction' => 'react', 'CommentID' => (int)$comment->ID, 'ReactionEmoji' => '🙏',
        ]);
        $this->assertSame(200, $authorReaction->getStatusCode());
        $deleted = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID,
            'CommentAction' => 'delete', 'CommentID' => (int)$comment->ID,
        ]);
        $this->assertSame(200, $deleted->getStatusCode());
        $this->assertSame(0, MashaFeedlyCommentReaction::get()->count(), 'Beim Löschen des Kommentars werden seine Reaktionen entfernt.');
    }

    /** Prüft, dass Kommentaraktivität im Frontend-Listenpayload erscheint und dort gelesen werden kann. */
    public function testFrontendListShowsUnreadCommentActivityUntilEntryIsOpened(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        foreach (MashaFeedlyEntry::get() as $visibleEntry) {
            MashaFeedlyEntryRead::markAsSeen($visibleEntry, $member);
        }
        MashaFeedlyEntryHistory::record($entry, 'comment', '', 'Neue Rückfrage zum Fehler', $member, 987);
        $commentHistory = MashaFeedlyEntryHistory::get()->filter(['EntryID' => (int)$entry->ID, 'RelatedID' => 987])->first();
        $commentHistory->Created = date('Y-m-d H:i:s', time() + 60);
        $commentHistory->write();
        $this->logInAs($member);

        $listed = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $entryData = array_values(array_filter($listed['entries'], static fn(array $item): bool => $item['id'] === (int)$entry->ID))[0];
        $this->assertTrue($entryData['isUnread'], 'Das rechte Frontend-Panel braucht ein sichtbares Aktivitätskennzeichen.');
        $unreadList = json_decode($this->get('/__masha-feedly/listEntries?mode=unread')->getBody(), true);
        $this->assertSame(1, $unreadList['unreadCount']);
        $this->assertSame(1, $unreadList['unreadCommentCount'], 'Das Neuigkeiten-Summary muss neue Kommentare separat zählen.');
        $this->assertSame([(int)$entry->ID], array_map(static fn(array $item): int => (int)$item['id'], $unreadList['entries']));

        $readResponse = $this->post('/__masha-feedly/markEntryRead', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
        ]);
        $this->assertSame(200, $readResponse->getStatusCode());
        $this->assertTrue(json_decode($readResponse->getBody(), true)['success']);
        $this->assertSame(0, json_decode($readResponse->getBody(), true)['unreadCount']);
        $this->assertSame(0, json_decode($readResponse->getBody(), true)['unreadCommentCount']);
        $reloaded = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $entryData = array_values(array_filter($reloaded['entries'], static fn(array $item): bool => $item['id'] === (int)$entry->ID))[0];
        $this->assertFalse($entryData['isUnread'], 'Nach dem Öffnen muss der Hinweis verschwinden.');
        $unreadAfterRead = json_decode($this->get('/__masha-feedly/listEntries?mode=unread')->getBody(), true);
        $this->assertSame([], $unreadAfterRead['entries']);
    }

    /** Prüft den realen Ablauf: Ersteller legt an, andere Person kommentiert, Ersteller sieht Aktivität. */
    public function testEntryCreatorSeesCommentFromAnotherMemberInFrontendList(): void
    {
        $creator = $this->objFromFixture(Member::class, 'allowed');
        $commenter = $this->objFromFixture(Member::class, 'notAllowed');
        foreach ([$creator, $commenter] as $member) {
            $member->MashaFeedlyEmailNotifications = false;
            $member->write();
        }
        $this->allowMember($creator, $commenter);
        MashaFeedlyCategory::ensureDefaultCategories();
        $category = MashaFeedlyCategory::defaultCategory();
        $this->logInAs($creator);
        $created = $this->post('/__masha-feedly/createEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => 'Eintrag von User 2 ohne Zuständigkeit',
            'CategoryID' => (int)$category->ID,
            'PageURL' => 'https://example.test/neuer-eintrag',
        ]);
        $entryID = (int)json_decode($created->getBody(), true)['entryID'];
        $entry = MashaFeedlyEntry::get()->byID($entryID);
        $this->assertSame((int)$creator->ID, $entry->creatorMemberID());
        $this->assertSame([], $entry->AssignedMembers()->column('ID'), 'Der Testeintrag darf keiner Person zugewiesen sein.');

        $this->logInAs($commenter);
        $commentResponse = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => $entryID,
            'CommentText' => 'User 1 hat einen Kommentar ergänzt.',
        ]);
        $this->assertSame(200, $commentResponse->getStatusCode());
        $this->assertNotContains($entryID, MashaFeedlyEntryRead::unreadEntryIDs($commenter), 'Die kommentierende Person soll den eigenen Kommentar nicht als ungelesen sehen.');

        $this->logInAs($creator);
        $list = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $entryData = array_values(array_filter($list['entries'], static fn(array $item): bool => (int)$item['id'] === $entryID))[0];
        $this->assertTrue($entryData['isUnread'], 'Der Ersteller muss die neue Kommentaraktivität trotz fehlender Zuständigkeit sehen.');
        $this->assertSame('User 1 hat einen Kommentar ergänzt.', $entryData['comments'][0]['text']);
        $news = json_decode($this->get('/__masha-feedly/listEntries?mode=unread')->getBody(), true);
        $this->assertContains($entryID, array_map(static fn(array $item): int => (int)$item['id'], $news['entries']));
    }

    /** Prüft, dass der Lese-Endpunkt unberechtigte und fremde Requests konsequent blockiert. */
    public function testMarkEntryReadRejectsUnauthorizedAndInvalidRequests(): void
    {
        $member = $this->objFromFixture(Member::class, 'notAllowed');
        $this->logInAs($member);
        $this->assertSame(403, $this->post('/__masha-feedly/markEntryRead', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => 1,
        ])->getStatusCode());

        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $this->logInAs($member);
        $this->assertSame(404, $this->post('/__masha-feedly/markEntryRead', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => 999999,
        ])->getStatusCode());
    }

    /** Prüft Berechtigung, Pflichttext und maximale Kommentarlänge am Kommentar-Endpunkt. */
    public function testCommentEndpointRejectsUnauthorizedAndInvalidComments(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $other = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowed);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $this->logInAs($other);
        $readResponse = $this->get('/__masha-feedly-comment?EntryID=' . (int)$entry->ID);
        $this->assertSame(403, $readResponse->getStatusCode());
        $this->assertStringNotContainsString('Kommentar', $readResponse->getBody());
        $forbidden = $this->post('/__masha-feedly-comment', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID, 'CommentText' => 'Nicht erlaubt',
        ]);
        $this->assertSame(403, $forbidden->getStatusCode());
        $this->assertSame(0, MashaFeedlyComment::get()->count());

        $this->logInAs($allowed);
        $comment = MashaFeedlyComment::create([
            'AuthorName' => $allowed->getName(), 'CommentText' => 'Original', 'IsApproved' => true,
            'EntryID' => (int)$entry->ID, 'AuthorMemberID' => (int)$allowed->ID,
        ]);
        $comment->write();
        $this->logInAs($other);
        foreach (['edit', 'delete'] as $action) {
            $blocked = $this->post('/__masha-feedly-comment', [
                'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID,
                'CommentAction' => $action, 'CommentID' => (int)$comment->ID, 'CommentText' => 'Unberechtigt',
            ]);
            $this->assertSame(403, $blocked->getStatusCode());
        }
        $persisted = MashaFeedlyComment::get()->byID((int)$comment->ID);
        $this->assertNotNull($persisted);
        $this->assertSame('Original', (string)$persisted->CommentText);

        $this->logInAs($allowed);
        foreach (['   ', str_repeat('x', 5001)] as $invalidText) {
            $invalid = $this->post('/__masha-feedly-comment', [
                'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID, 'CommentText' => $invalidText,
            ]);
            $this->assertSame(400, $invalid->getStatusCode());
        }
        $this->assertSame(1, MashaFeedlyComment::get()->count());
    }

    /** Ein Mailserverfehler darf den Erstellungsrequest nicht abbrechen oder den Eintrag verhindern. */
    public function testEntryIsSavedWhenNewEntryNotificationFails(): void
    {
        $author = $this->objFromFixture(Member::class, 'allowed');
        $recipient = $this->objFromFixture(Member::class, 'normalize');
        $recipient->MashaFeedlyEmailNotifications = true;
        $recipient->MashaFeedlyNotifyNewEntries = true;
        $recipient->write();
        $this->allowMember($author);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$author->ID, (int)$recipient->ID]);
        $config->write();
        $this->logInAs($author);

        $mailer = new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new \RuntimeException('Simulierter SMTP-Ausfall.');
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        try {
            $response = $this->post('/__masha-feedly/createEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'Content' => 'Eintrag trotz Mailserverfehler speichern.',
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $data = json_decode($response->getBody(), true);
            $this->assertTrue($data['success']);
            $this->assertNotNull(MashaFeedlyEntry::get()->byID((int)$data['entryID']));
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft den echten Kommentar-Endpunkt einschließlich E-Mail an eine zugewiesene Person. */
    public function testPostingCommentEmailsAssignedMember(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyEmailTestSucceeded = true;
        $config->write();
        $author = $this->objFromFixture(Member::class, 'allowed');
        $assignee = $this->objFromFixture(Member::class, 'normalize');
        $author->MashaFeedlyEmailNotifications = true;
        $assignee->MashaFeedlyEmailNotifications = true;
        $assignee->MashaFeedlyNotifyComments = true;
        $author->MashaFeedlyNotifyNewEntries = false;
        $assignee->MashaFeedlyNotifyNewEntries = false;
        $author->write();
        $assignee->write();
        $this->allowMember($author);
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$author->ID, (int)$assignee->ID]);
        $config->write();

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] */
            public array $messages = [];
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        try {
            $this->logInAs($author);
            $entry = MashaFeedlyEntry::create(['Content' => 'Fehler mit der Suche.', 'PageURL' => 'https://example.test']);
            $entry->write();
            $entry->AssignedMembers()->setByIDList([(int)$author->ID, (int)$assignee->ID]);
            $mailer->messages = [];

            $response = $this->post('/__masha-feedly-comment', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'EntryID' => (int)$entry->ID,
                'CommentText' => 'Die Suche findet keine Ergebnisse.',
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertCount(1, $mailer->messages);
            $this->assertSame('normalize@example.test', $mailer->messages[0]->getTo()[0]->getAddress());
            $this->assertStringContainsString('Die Suche findet keine Ergebnisse.', (string)$mailer->messages[0]->getTextBody());
            $listData = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
            $entryPayload = array_values(array_filter(
                $listData['entries'],
                fn(array $item): bool => (int)$item['id'] === (int)$entry->ID
            ))[0];
            $history = $entryPayload['history'];
            $this->assertCount(2, $history);
            $this->assertSame('comment', $history[0]['type']);
            $this->assertSame('Die Suche findet keine Ergebnisse.', $history[0]['newValue']);
            $this->assertSame('Erika Muster', $history[0]['actor']);
            $this->assertNotEmpty($history[0]['created']);
            $this->assertSame('created', $history[1]['type']);
            $this->assertSame('Erika Muster', $history[1]['actor']);
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft, dass bestehende Einträge und freigegebene Kommentare beim Build in den Verlauf übernommen werden. */
    public function testLegacyEntryAndCommentAreBackfilledIntoHistory(): void
    {
        $author = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($author);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        MashaFeedlyEntryHistory::get()->filter('EntryID', (int)$entry->ID)->removeAll();
        $comment = MashaFeedlyComment::create([
            'AuthorName' => 'Erika Muster', 'CommentText' => 'Historischer Kommentar', 'IsApproved' => true,
            'EntryID' => (int)$entry->ID, 'AuthorMemberID' => (int)$author->ID,
        ]);
        $comment->write();
        $attachment = \KW\MashaFeedly\Model\MashaFeedlyAttachment::create([
            'EntryID' => (int)$entry->ID,
            'OriginalName' => 'alt screenshot.png',
            'MimeType' => 'image/png',
        ]);
        $attachment->write();

        (new MashaFeedlyEntryHistory())->requireDefaultRecords();
        $history = MashaFeedlyEntryHistory::get()->filter('EntryID', (int)$entry->ID)->sort('ID ASC');
        $this->assertCount(3, $history);
        $events = $history->toArray();
        $this->assertSame('created', (string)$events[0]->ChangeType);
        $this->assertSame('comment', (string)$events[1]->ChangeType);
        $this->assertSame('Erika Muster', (string)$events[1]->ActorName);
        $this->assertSame('Historischer Kommentar', (string)$events[1]->NewValue);
        $this->assertSame((int)$comment->ID, (int)$events[1]->RelatedID);
        $this->assertNotEmpty((string)$events[1]->Created);
        $this->assertSame('attachment', (string)$events[2]->ChangeType);
        $this->assertSame('alt screenshot.png', (string)$events[2]->NewValue);
    }

    /** Erledigt die Einweisung dauerhaft nur für freigegebene Mitglieder. */
    public function testOnboardingCompletionRequiresAccessAndPersists(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $blocked = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowed);

        $this->logInAs($blocked);
        $denied = $this->post('/__masha-feedly/completeOnboarding', ['SecurityID' => SecurityToken::getSecurityID()]);
        $this->assertSame(403, $denied->getStatusCode());
        $this->assertFalse((bool)$blocked->MashaFeedlyOnboardingCompleted);

        $this->logInWithPermission('ADMIN');
        $adminDenied = $this->post('/__masha-feedly/completeOnboarding', ['SecurityID' => SecurityToken::getSecurityID()]);
        $this->assertSame(403, $adminDenied->getStatusCode(), 'Admins ohne Freigabe dürfen den Onboarding-Status nicht ändern.');

        $this->logInAs($allowed);
        $allowed->MashaFeedlyShowOnboarding = true;
        $allowed->write();
        $completed = $this->post('/__masha-feedly/completeOnboarding', ['SecurityID' => SecurityToken::getSecurityID()]);
        $this->assertSame(200, $completed->getStatusCode());
        $this->assertTrue((bool)Member::get()->byID((int)$allowed->ID)->MashaFeedlyOnboardingCompleted);
        $this->assertFalse((bool)Member::get()->byID((int)$allowed->ID)->MashaFeedlyShowOnboarding);
        $body = json_decode($completed->getBody(), true);
        $this->assertTrue($body['success']);
    }

    /** Der Neustart der Einführung ist geschützt und speichert den erneut angeforderten Status. */
    public function testOnboardingCanBeRestartedOnlyByAllowedMembers(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $blocked = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowed);
        $allowed->MashaFeedlyOnboardingCompleted = true;
        $allowed->MashaFeedlyShowOnboarding = false;
        $allowed->write();

        $this->logInAs($blocked);
        $denied = $this->post('/__masha-feedly/restartOnboarding', ['SecurityID' => SecurityToken::getSecurityID()]);
        $this->assertSame(403, $denied->getStatusCode());
        $this->assertTrue((bool)Member::get()->byID((int)$allowed->ID)->MashaFeedlyOnboardingCompleted);

        $this->logInWithPermission('ADMIN');
        $adminDenied = $this->post('/__masha-feedly/restartOnboarding', ['SecurityID' => SecurityToken::getSecurityID()]);
        $this->assertSame(403, $adminDenied->getStatusCode(), 'Admins ohne Freigabe dürfen die Einführung nicht starten.');

        $this->logInAs($allowed);
        $restarted = $this->post('/__masha-feedly/restartOnboarding', ['SecurityID' => SecurityToken::getSecurityID()]);
        $this->assertSame(200, $restarted->getStatusCode());
        $this->assertFalse((bool)Member::get()->byID((int)$allowed->ID)->MashaFeedlyOnboardingCompleted);
        $this->assertTrue((bool)Member::get()->byID((int)$allowed->ID)->MashaFeedlyShowOnboarding);
        $this->assertTrue((bool)json_decode($restarted->getBody(), true)['success']);
    }

    /** Filteransichten bleiben im Profil des Mitglieds und sind über die geschützten Endpunkte verwaltbar. */
    public function testSavedViewsArePrivateValidatedAndCanBeDeleted(): void
    {
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $otherAllowed = $this->objFromFixture(Member::class, 'normalize');
        $blocked = $this->objFromFixture(Member::class, 'notAllowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$allowed->ID, (int)$otherAllowed->ID]);
        $config->write();
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $category = MashaFeedlyCategory::defaultCategory();
        $priority = MashaFeedlyPriority::get()->filter('SystemKey', 'critical')->first();
        $this->assertNotNull($priority);

        $this->logInAs($allowed);
        $saved = $this->post('/__masha-feedly', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'ViewAction' => 'save',
            'Title' => 'Meine kritischen offenen Fehler',
            'Mode' => 'open',
            'CategoryID' => (int)$category->ID,
            'PriorityID' => (int)$priority->ID,
        ]);
        $this->assertSame(200, $saved->getStatusCode());
        $view = json_decode($saved->getBody(), true)['view'];
        $this->assertSame('Meine kritischen offenen Fehler', $view['title']);
        $this->assertSame('open', $view['mode']);
        $this->assertSame((int)$category->ID, $view['categoryID']);
        $this->assertSame((int)$priority->ID, $view['priorityID']);
        $storedCount = DB::prepared_query(
            'SELECT COUNT(*) FROM "MashaFeedlySavedView" WHERE "ID" = ? AND "MemberID" = ?',
            [(int)$view['id'], (int)$allowed->ID]
        )->value();
        $this->assertSame('1', (string)$storedCount, 'Eine gespeicherte Ansicht muss dauerhaft einem Mitglied zugeordnet sein.');

        $this->logInAs($otherAllowed);
        $othersViews = json_decode($this->get('/__masha-feedly')->getBody(), true);
        $this->assertSame([], $othersViews['views'], 'Ein anderes freigegebenes Mitglied sieht keine persönlichen Ansichten.');

        $this->logInAs($allowed);
        $ownViews = json_decode($this->get('/__masha-feedly')->getBody(), true);
        $this->assertSame([$view], $ownViews['views'], 'Das eigene Mitglied kann seine gespeicherte Ansicht nach einem erneuten Request wieder laden.');
        $invalid = $this->post('/__masha-feedly', [
            'SecurityID' => SecurityToken::getSecurityID(), 'ViewAction' => 'save', 'Title' => 'Ungültig', 'Mode' => 'unknown',
        ]);
        $this->assertSame(400, $invalid->getStatusCode());
        $delete = $this->post('/__masha-feedly', [
            'SecurityID' => SecurityToken::getSecurityID(), 'ViewAction' => 'delete', 'ViewID' => $view['id'],
        ]);
        $this->assertSame(200, $delete->getStatusCode());
        $this->assertSame([], json_decode($delete->getBody(), true)['views']);

        $this->logInAs($blocked);
        $denied = $this->get('/__masha-feedly');
        $this->assertSame(403, $denied->getStatusCode());
    }

    /** Zeigt passende offene Einträge derselben Seite, ignoriert geschlossene und fremde Seiten. */
    public function testSimilarEntrySearchIsScopedToOpenEntriesOnTheSamePage(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $this->logInAs($member);
        $pageURL = 'https://example.test/suche';
        $open = MashaFeedlyEntry::create([
            'Content' => 'Der Suchbutton verschwindet, wenn ich das Ergebnisfenster öffne.',
            'PageURL' => $pageURL,
            'CategoryID' => (int)MashaFeedlyCategory::defaultCategory()->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $open->write();
        $closed = MashaFeedlyEntry::create([
            'Content' => 'Der Suchbutton verschwindet, wenn ich das Ergebnisfenster öffne.',
            'PageURL' => $pageURL,
            'CategoryID' => (int)MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first()->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $closed->write();
        MashaFeedlyEntry::create([
            'Content' => 'Der Suchbutton verschwindet, wenn ich das Ergebnisfenster öffne.',
            'PageURL' => 'https://example.test/andere-seite',
            'CategoryID' => (int)MashaFeedlyCategory::defaultCategory()->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ])->write();

        $response = $this->post('/__masha-feedly/findSimilarEntries', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => 'Der Suchbutton verschwindet, wenn ich das Ergebnisfenster öffne.',
            'PageURL' => $pageURL . '?tracking=1#results',
        ]);
        $data = json_decode($response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['matches']);
        $this->assertSame((int)$open->ID, $data['matches'][0]['id']);
        $this->assertGreaterThanOrEqual(20, $data['matches'][0]['score']);
    }

    /** Verhindert, dass nicht freigeschaltete Mitglieder den Ähnlichkeitsendpunkt nutzen. */
    public function testSimilarEntrySearchRequiresMashaAccess(): void
    {
        $blocked = $this->objFromFixture(Member::class, 'notAllowed');
        $this->logInAs($blocked);
        $response = $this->post('/__masha-feedly/findSimilarEntries', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'Content' => 'Ein hinreichend langer Beschreibungstext',
            'PageURL' => 'https://example.test/kontakt',
        ]);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse(json_decode($response->getBody(), true)['success']);
    }

    /** Speichert Eintragsverknüpfungen nur seitenlokal und protokolliert die Änderung. */
    public function testEntryRelationsAreSavedScopedAndRecordedInHistory(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $this->logInAs($member);
        $pageURL = 'https://example.test/kontakt';
        $entry = MashaFeedlyEntry::create([
            'Content' => 'Der Absenden-Button ist verdeckt.', 'PageURL' => $pageURL,
            'CategoryID' => (int)MashaFeedlyCategory::defaultCategory()->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $entry->write();
        $samePage = MashaFeedlyEntry::create([
            'Content' => 'Formular kann nicht gesendet werden.', 'PageURL' => $pageURL,
            'CategoryID' => (int)MashaFeedlyCategory::defaultCategory()->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $samePage->write();
        $this->assertSame($pageURL, (string)$entry->PageURL);
        $this->assertSame((string)$entry->PageURL, (string)$samePage->PageURL);
        $otherPage = MashaFeedlyEntry::create([
            'Content' => 'Anderer Fehler.', 'PageURL' => 'https://example.test/andere-seite',
            'CategoryID' => (int)MashaFeedlyCategory::defaultCategory()->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $otherPage->write();

        $response = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$entry->CategoryID,
            'PriorityID' => (int)$entry->PriorityID,
            'RelationType' => 'duplicate_of',
            'RelatedEntryIDs' => [(int)$samePage->ID, (int)$otherPage->ID, (int)$entry->ID],
        ]);
        $data = json_decode($response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $entry->Relations()->toArray(), 'Der Controller muss beide Verknüpfungen persistieren und den eigenen Eintrag ignorieren.');
        $this->assertSame([
            ['id' => (int)$samePage->ID, 'title' => (string)$samePage->Title, 'type' => 'duplicate_of', 'categoryTitle' => (string)$samePage->Category()->Title, 'direction' => 'outgoing'],
            ['id' => (int)$otherPage->ID, 'title' => (string)$otherPage->Title, 'type' => 'duplicate_of', 'categoryTitle' => (string)$otherPage->Category()->Title, 'direction' => 'outgoing'],
        ], $data['relations']);
        $history = MashaFeedlyEntryHistory::get()->filter(['EntryID' => (int)$entry->ID, 'ChangeType' => 'relations'])->first();
        $this->assertNotNull($history);
        $this->assertStringContainsString('Duplikat von', (string)$history->NewValue);
        $this->assertStringContainsString('#' . (int)$samePage->ID, (string)$history->NewValue);
        $this->assertStringContainsString('#' . (int)$otherPage->ID, (string)$history->NewValue);

        $listData = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $listedEntry = array_values(array_filter($listData['entries'], static fn(array $item): bool => $item['id'] === (int)$entry->ID))[0];
        $this->assertSame($data['relations'], $listedEntry['relations'], 'Verknüpfungen müssen nach einem neuen Request erneut erscheinen.');
        $listedTarget = array_values(array_filter($listData['entries'], static fn(array $item): bool => $item['id'] === (int)$samePage->ID))[0];
        $this->assertContains([
            'id' => (int)$entry->ID,
            'title' => (string)$entry->Title,
            'type' => 'has_duplicate',
            'categoryTitle' => (string)$entry->Category()->Title,
            'direction' => 'incoming',
        ], $listedTarget['relations'], 'Auch der verknüpfte Zieleintrag muss den Zusammenhang erhalten.');

        foreach (['blocked_by', 'related'] as $relationType) {
            $typedResponse = $this->post('/__masha-feedly/updateEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'EntryID' => (int)$entry->ID,
                'CategoryID' => (int)$entry->CategoryID,
                'PriorityID' => (int)$entry->PriorityID,
                'RelationType' => $relationType,
                'RelatedEntryIDs' => [(int)$samePage->ID],
            ]);
            $typedData = json_decode($typedResponse->getBody(), true);
            $this->assertSame(200, $typedResponse->getStatusCode());
            $this->assertSame($relationType, $typedData['relations'][0]['type']);
            $this->assertSame($relationType, (string)$entry->Relations()->first()->LinkType);
            $listedData = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
            $listedTarget = array_values(array_filter($listedData['entries'], static fn(array $item): bool => $item['id'] === (int)$samePage->ID))[0];
            $incomingType = $relationType === 'blocked_by' ? 'blocks' : 'related_to';
            $this->assertContains([
                'id' => (int)$entry->ID,
                'title' => (string)$entry->Title,
                'type' => $incomingType,
                'categoryTitle' => (string)$entry->Category()->Title,
                'direction' => 'incoming',
            ], $listedTarget['relations'], 'Die Gegenrichtung muss am verknüpften Eintrag genauso abrufbar sein.');
        }
    }

    /** Prüft Selbstlinks, doppelte Ziele, Typwechsel und Entfernen samt vollständigem Verlauf über HTTP. */
    public function testRelationLifecycleDeduplicatesRejectsSelfLinksAndRecordsEveryChange(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $this->logInAs($member);
        $category = MashaFeedlyCategory::defaultCategory();
        $priority = MashaFeedlyPriority::defaultPriority();
        $entry = MashaFeedlyEntry::create([
            'Content' => 'Primärer Verlaufseintrag', 'PageURL' => 'https://example.test/relation-lifecycle',
            'CategoryID' => (int)$category->ID, 'PriorityID' => (int)$priority->ID,
        ]);
        $entry->write();
        $firstTarget = MashaFeedlyEntry::create([
            'Content' => 'Erstes verbundenes Thema', 'PageURL' => 'https://example.test/relation-lifecycle',
            'CategoryID' => (int)$category->ID, 'PriorityID' => (int)$priority->ID,
        ]);
        $firstTarget->write();
        $secondTarget = MashaFeedlyEntry::create([
            'Content' => 'Zweites verbundenes Thema', 'PageURL' => 'https://example.test/relation-lifecycle',
            'CategoryID' => (int)$category->ID, 'PriorityID' => (int)$priority->ID,
        ]);
        $secondTarget->write();

        $saveRelations = function (array $targetIDs, string $type = 'related') use ($entry, $category, $priority) {
            return $this->post('/__masha-feedly/updateEntry', [
                'SecurityID' => SecurityToken::getSecurityID(),
                'EntryID' => (int)$entry->ID,
                'CategoryID' => (int)$category->ID,
                'PriorityID' => (int)$priority->ID,
                'RelationType' => $type,
                'RelatedEntryIDs' => $targetIDs,
            ]);
        };

        $initial = $saveRelations([
            (int)$firstTarget->ID,
            (int)$firstTarget->ID,
            (int)$secondTarget->ID,
            (int)$entry->ID,
            (int)$entry->ID,
        ]);
        $initialData = json_decode($initial->getBody(), true);
        $this->assertSame(200, $initial->getStatusCode());
        $this->assertTrue($initialData['success']);
        $this->assertEqualsCanonicalizing([(int)$firstTarget->ID, (int)$secondTarget->ID], array_map('intval', $entry->Relations()->column('RelatedEntryID')));
        $this->assertCount(2, $entry->Relations(), 'Mehrfach übermittelte Ziele dürfen nur eine Beziehung erzeugen.');
        $this->assertNotContains((int)$entry->ID, array_map('intval', $entry->Relations()->column('RelatedEntryID')), 'Selbstverknüpfungen dürfen nicht gespeichert werden.');
        $history = static fn(): array => MashaFeedlyEntryHistory::get()
            ->filter(['EntryID' => (int)$entry->ID, 'ChangeType' => 'relations'])
            ->sort('ID ASC')->toArray();
        $events = $history();
        $this->assertCount(1, $events);
        $this->assertSame('', (string)$events[0]->OldValue);
        $this->assertStringContainsString('Thematisch verwandt mit #' . (int)$firstTarget->ID, (string)$events[0]->NewValue);
        $this->assertStringContainsString('Thematisch verwandt mit #' . (int)$secondTarget->ID, (string)$events[0]->NewValue);
        $this->assertSame('Erika Muster', (string)$events[0]->ActorName);
        $this->assertNotEmpty($events[0]->Created);

        $unchanged = $saveRelations([(int)$firstTarget->ID, (int)$firstTarget->ID, (int)$secondTarget->ID]);
        $this->assertSame(200, $unchanged->getStatusCode());
        $this->assertCount(1, $history(), 'Das erneute Speichern unveränderter Beziehungen darf keinen Verlaufseintrag erzeugen.');

        $changed = $saveRelations([(int)$firstTarget->ID, (int)$firstTarget->ID], 'blocked_by');
        $changedData = json_decode($changed->getBody(), true);
        $this->assertSame(200, $changed->getStatusCode());
        $this->assertCount(1, $entry->Relations());
        $this->assertSame('blocked_by', (string)$entry->Relations()->first()->LinkType);
        $events = $history();
        $this->assertCount(2, $events);
        $this->assertStringContainsString('Thematisch verwandt mit #' . (int)$secondTarget->ID, (string)$events[1]->OldValue);
        $this->assertStringContainsString('Blockiert durch #' . (int)$firstTarget->ID, (string)$events[1]->NewValue);
        $this->assertStringNotContainsString('#' . (int)$secondTarget->ID, (string)$events[1]->NewValue);
        $this->assertSame('Erika Muster', (string)$events[1]->ActorName);
        $this->assertNotEmpty($events[1]->Created);
        $this->assertSame($changedData['history'][0]['newValue'], (string)$events[1]->NewValue);

        $removed = $saveRelations([]);
        $removedData = json_decode($removed->getBody(), true);
        $this->assertSame(200, $removed->getStatusCode());
        $this->assertCount(0, $entry->Relations());
        $events = $history();
        $this->assertCount(3, $events);
        $this->assertStringContainsString('Blockiert durch #' . (int)$firstTarget->ID, (string)$events[2]->OldValue);
        $this->assertSame('', (string)$events[2]->NewValue);
        $this->assertSame('Erika Muster', (string)$events[2]->ActorName);
        $this->assertNotEmpty($events[2]->Created);
        $this->assertSame([], $removedData['relations']);

        $listed = json_decode($this->get('/__masha-feedly/listEntries?mode=all')->getBody(), true);
        $listedTarget = array_values(array_filter($listed['entries'], static fn(array $item): bool => (int)$item['id'] === (int)$firstTarget->ID))[0];
        $this->assertSame([], $listedTarget['relations'], 'Entfernte Beziehungen dürfen auch beim Ziel nicht mehr als eingehend erscheinen.');
        $listedSource = array_values(array_filter($listed['entries'], static fn(array $item): bool => (int)$item['id'] === (int)$entry->ID))[0];
        $listedHistory = array_values(array_filter($listedSource['history'], static fn(array $item): bool => $item['type'] === 'relations'));
        $this->assertCount(3, $listedHistory, 'Alle drei tatsächlichen Änderungen müssen über die Liste abrufbar bleiben.');
        $this->assertSame('', $listedHistory[0]['newValue']);
    }

    /** Schließt nur offene Duplikate nach Abschluss des Haupteintrags und protokolliert jede Folgeänderung. */
    public function testCompletingCanonicalEntryClosesItsDuplicateChainButNotOtherRelations(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $backlog = MashaFeedlyCategory::get()->filter('SystemKey', 'backlog')->first();
        $done = MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first();
        $archive = MashaFeedlyCategory::get()->filter('SystemKey', 'archive')->first();
        $this->logInAs($member);

        $canonical = MashaFeedlyEntry::create([
            'Content' => 'Kanonischer Eintrag',
            'CategoryID' => (int)$backlog->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $canonical->write();
        $duplicate = MashaFeedlyEntry::create([
            'Content' => 'Duplikat eins',
            'CategoryID' => (int)$backlog->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $duplicate->write();
        $nestedDuplicate = MashaFeedlyEntry::create([
            'Content' => 'Duplikat zwei',
            'CategoryID' => (int)$backlog->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $nestedDuplicate->write();
        $archivedDuplicate = MashaFeedlyEntry::create([
            'Content' => 'Archiviertes Duplikat',
            'CategoryID' => (int)$archive->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $archivedDuplicate->write();
        $blockedEntry = MashaFeedlyEntry::create([
            'Content' => 'Blockierter anderer Eintrag',
            'CategoryID' => (int)$backlog->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $blockedEntry->write();
        foreach ([[$duplicate, $canonical], [$nestedDuplicate, $duplicate], [$archivedDuplicate, $canonical]] as [$source, $target]) {
            MashaFeedlyEntryRelation::create([
                'EntryID' => (int)$source->ID,
                'RelatedEntryID' => (int)$target->ID,
                'LinkType' => 'duplicate_of',
            ])->write();
        }
        MashaFeedlyEntryRelation::create([
            'EntryID' => (int)$blockedEntry->ID,
            'RelatedEntryID' => (int)$canonical->ID,
            'LinkType' => 'blocked_by',
        ])->write();

        $response = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$canonical->ID,
            'CategoryID' => (int)$done->ID,
            'PriorityID' => (int)$canonical->PriorityID,
        ]);
        $data = json_decode($response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $data['closedDuplicateCount']);
        $this->assertStringContainsString('2 Duplikate wurden ebenfalls abgeschlossen.', $data['message']);
        $this->assertSame((int)$done->ID, (int)MashaFeedlyEntry::get()->byID((int)$duplicate->ID)->CategoryID);
        $this->assertSame((int)$done->ID, (int)MashaFeedlyEntry::get()->byID((int)$nestedDuplicate->ID)->CategoryID);
        $this->assertSame((int)$archive->ID, (int)MashaFeedlyEntry::get()->byID((int)$archivedDuplicate->ID)->CategoryID);
        $this->assertSame((int)$backlog->ID, (int)MashaFeedlyEntry::get()->byID((int)$blockedEntry->ID)->CategoryID);
        $duplicateStatus = MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$nestedDuplicate->ID,
            'ChangeType' => 'status',
            'NewValue' => (string)$done->Title,
        ])->first();
        $this->assertNotNull($duplicateStatus);
        $this->assertSame((string)$backlog->Title, (string)$duplicateStatus->OldValue);
        $this->assertSame((int)$member->ID, (int)$duplicateStatus->ActorMemberID);

        $reopened = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$canonical->ID,
            'CategoryID' => (int)$backlog->ID,
            'PriorityID' => (int)$canonical->PriorityID,
        ]);
        $this->assertSame(0, json_decode($reopened->getBody(), true)['closedDuplicateCount']);
        $this->assertSame((int)$done->ID, (int)MashaFeedlyEntry::get()->byID((int)$duplicate->ID)->CategoryID, 'Ein erneutes Öffnen darf abgeschlossene Duplikate nicht automatisch wieder öffnen.');

        $reviewer = $this->objFromFixture(Member::class, 'notAllowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID, (int)$reviewer->ID]);
        $config->write();
        $feedbackCategory = MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->first();
        $awaitingApproval = MashaFeedlyEntry::create([
            'Content' => 'Eintrag wartet auf Rückmeldung',
            'CategoryID' => (int)$backlog->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $awaitingApproval->write();
        $awaitingApprovalDuplicate = MashaFeedlyEntry::create([
            'Content' => 'Duplikat wartet ebenfalls',
            'CategoryID' => (int)$backlog->ID,
            'PriorityID' => (int)MashaFeedlyPriority::defaultPriority()->ID,
        ]);
        $awaitingApprovalDuplicate->write();
        MashaFeedlyEntryRelation::create([
            'EntryID' => (int)$awaitingApprovalDuplicate->ID,
            'RelatedEntryID' => (int)$awaitingApproval->ID,
            'LinkType' => 'duplicate_of',
        ])->write();

        $this->logInAs($reviewer);
        $feedbackResponse = $this->post('/__masha-feedly/updateEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$awaitingApproval->ID,
            'CategoryID' => (int)$done->ID,
            'PriorityID' => (int)$awaitingApproval->PriorityID,
        ]);
        $feedbackData = json_decode($feedbackResponse->getBody(), true);
        $this->assertSame((int)$feedbackCategory->ID, $feedbackData['categoryID'], 'Der Abschluss durch eine andere Person muss erst zur Rückmeldung gehen.');
        $this->assertSame(0, $feedbackData['closedDuplicateCount'], 'Ein wartender Eintrag darf seine Duplikate noch nicht schließen.');
        $this->assertSame((int)$backlog->ID, (int)MashaFeedlyEntry::get()->byID((int)$awaitingApprovalDuplicate->ID)->CategoryID);
    }

    /** Schreibt die Testfreigabe in die Konfiguration, ohne weitere Mitglieder anzulegen. */
    private function allowMember(Member ...$members): void
    {
        $ids = array_map(static fn(Member $member): int => (int)$member->ID, $members);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode($ids);
        $config->write();
    }
}
