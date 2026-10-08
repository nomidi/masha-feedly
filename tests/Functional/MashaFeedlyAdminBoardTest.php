<?php

namespace KW\MashaFeedly\Tests\Functional;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyAttachment;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyCommentReaction;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Model\MashaFeedlyEntryRelation;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Core\Injector\Injector;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use SilverStripe\Security\Security;

/**
 * Prüft die Kategorienübersicht und die geschützte Sortieraktion im Masha-Feedly-Admin.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyAdminBoardTest extends FunctionalTest
{
    protected static $fixture_file = '../fixtures/MashaFeedly.yml';

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('de_DE');
    }

    /** Prüft, dass alle Kategorien samt Einträgen als sortierbare Board-Spalten erscheinen. */
    public function testBoardShowsEveryCategoryAndItsEntries(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $entryWithDeadline = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entryWithDeadline->DueDate = '2026-10-10';
        $entryWithDeadline->write();
        $this->logInAs($member);

        $response = $this->get('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry');

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        // SilverStripe liefert CMS-React-Props teils als JSON mit Unicode-Escapes.
        $body = str_replace(['\\u003C', '\\u003E', '\\u0022'], ['<', '>', '"'], $body);
        foreach (['Backlog', 'To Do', 'Doing', 'Done', 'Archiv', 'Feedback'] as $categoryTitle) {
            $this->assertStringContainsString($categoryTitle, $body);
        }
        $this->assertStringContainsString('Ein Inhalt', $body);
        $this->assertStringContainsString('masha-feedly-board__priority', $body);
        $this->assertStringContainsString('masha-feedly-board__entry-number">#', $body);
        $this->assertStringContainsString('masha-feedly-board__card-topline', $body);
        $this->assertStringContainsString('masha-feedly-board__card-date', $body);
        $this->assertStringContainsString('masha-feedly-board__due-date', $body);
        $this->assertStringContainsString('datetime="2026-10-10"', $body);
        $this->assertStringContainsString('name="DueDate"', $body, 'Das CMS-Overlay muss dasselbe Fälligkeitsfeld wie das Frontend anbieten.');
        $this->assertStringNotContainsString('name="EntryDate"', $body, 'Der Erstellungszeitpunkt wird im Overlay automatisch gesetzt.');
        $this->assertStringNotContainsString('type="datetime-local"', $body, 'Das Overlay bietet keine manuelle Eingabe des Erstellungszeitpunkts an.');
        $this->assertStringNotContainsString('masha-feedly-board__entry-status', $body);
        $this->assertDoesNotMatchRegularExpression('/masha-feedly-board__priority-label/', $body);
        $this->assertMatchesRegularExpression(
            '/masha-feedly-board__card-topline[\\s\\S]*?masha-feedly-board__entry-number[\\s\\S]*?masha-feedly-board__priority[\\s\\S]*?masha-feedly-board__new-indicator[\\s\\S]*?masha-feedly-board__drag-handle[\\s\\S]*?masha-feedly-board__card-heading[\\s\\S]*?masha-feedly-board__card-date/',
            $body,
            'Jede Karte zeigt ID, Priorität, Neu-Icon und Ziehgriff oben, Titel danach und das Datum darunter.'
        );
        $this->assertStringContainsString('aria-label="Normal"', $body);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $body);
        $this->assertStringContainsString('data-masha-feedly-board', $body);
        $this->assertStringContainsString('data-admin-translations=', $body);
        $this->assertStringContainsString('draggable="true"', $body);
        $this->assertStringContainsString('masha-feedly-admin.js', $body);
        $this->assertStringNotContainsString('data-open-entry-form', $body);
        $this->assertStringNotContainsString('masha-feedly-create=1', $body);
        $this->assertStringNotContainsString('/item/new', $body);
        $this->assertStringContainsString('masha-feedly-board__drag-handle', $body);
        $this->assertStringContainsString('data-masha-feedly-assignee-filter', $body);
        $this->assertLessThan(
            strpos($body, 'masha-feedly-board__columns'),
            strpos($body, 'masha-feedly-board__filters')
        );
        $this->assertStringContainsString('Alle Meldungen', $body);
        $this->assertStringNotContainsString('masha-feedly-board__new-indicator--general', $body);
        $this->assertStringNotContainsString('masha-feedly-board__new-indicator--personal', $body);
        $this->assertStringNotContainsString('>Neu<', $body);
        $this->assertStringContainsString('masha-feedly-board__new-icon', $body);
        $this->assertStringContainsString('viewBox="0 0 177800 177800"', $body);
        $this->assertStringContainsString('fill="#fff" d="m43403 112710', $body);
        $this->assertStringNotContainsString('>Aktivität</span>', $body);
        $this->assertStringContainsString('Neue Aktivität für alle', $body);
        $this->assertStringContainsString('<svg viewBox="0 0 24 24" aria-hidden="true"', $body);
        $this->assertStringContainsString('Nicht zugeordnet', $body);
        $this->assertStringContainsString('Erika Muster', $body, 'Freigegebene Mitglieder müssen auswählbar sein.');
        $this->assertStringNotContainsString('Max Beispiel', $body, 'Nicht freigegebene Mitglieder dürfen nicht auswählbar sein.');
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entry->PageURL = 'https://example.test/kontakt?from=cms#formular';
        $entry->write();
        $response = $this->get('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry');
        $body = $response->getBody();
        $this->assertStringContainsString(
            '<a href="https://example.test/kontakt?from=cms&amp;masha-feedly-entry=' . (int)$entry->ID
            . '#formular" target="_blank" rel="noopener noreferrer">Ein Inhalt</a>',
            $body,
            'Der Titel muss die Originalseite mit Eintragskennung in einem neuen, abgesicherten Tab öffnen.'
        );
    }

    /** Nur das freigegebene CMS-Admin-Konto sieht die Meldepersonen-Ansicht und kann Änderungen speichern. */
    public function testAllowlistedAdminCanManageDisplayedReportersFromBoardTab(): void
    {
        $this->logInWithPermission('ADMIN');
        $manager = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $manager);
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$manager->Email]);
        $this->assertFalse(MashaFeedlyConfigExtension::canUse($manager), 'Das Betreiberkonto erhält die CMS-Ausnahme auch ohne Modulzuordnung.');

        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $reporter = $this->objFromFixture(Member::class, 'allowed');
        $url = '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry';
        $response = $this->get($url);
        $body = str_replace(['\\u003C', '\\u003E', '\\u0022'], ['<', '>', '"'], $response->getBody());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-admin-view-tab="reporters"', $body);
        $this->assertStringContainsString('Menu-KW-MashaFeedly-Admin-MashaFeedlyAdmin', $body);
        $this->assertStringContainsString('data-admin-view-panel="reporters"', $body);
        $this->assertStringContainsString('data-reporter-form', $body);
        $this->assertStringContainsString('name="ReportedByID"', $body);
        $this->assertStringNotContainsString('data-admin-view-tab="mite"', $body);
        $this->assertStringNotContainsString('data-mite-entry-edit="', $body);

        $this->assertStringNotContainsString('data-mite-modal', $body);

        $saved = $this->post($url . '/saveReporter', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'ReportedByID' => (int)$reporter->ID,
        ]);
        $this->assertSame(200, $saved->getStatusCode());
        $this->assertStringContainsString('"success":true', $saved->getBody());
        $this->assertSame((int)$reporter->ID, (int)MashaFeedlyEntry::get()->byID($entry->ID)->ReportedByID);
        $this->assertSame(1, MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => 'reported_by',
        ])->count());
    }

    /** Ein CMS-Admin ohne passende Allowlist-E-Mail sieht den Tab nicht und kann den Schreib-Endpunkt nicht nutzen. */
    public function testCmsAdminWithoutReporterAllowlistCannotSeeTabOrSave(): void
    {
        $this->logInAsAllowedAdmin();
        $manager = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $manager);
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', ['other@example.test']);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $reporter = $this->objFromFixture(Member::class, 'allowed');
        $url = '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry';

        $response = $this->get($url);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('data-admin-view-tab="reporters"', $response->getBody());
        $this->assertStringNotContainsString('data-mite-modal', $response->getBody());
        $miteResponse = $this->get('/admin/masha-feedly/mite');
        $this->assertSame(403, $miteResponse->getStatusCode());
        $saved = $this->post($url . '/saveReporter', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'ReportedByID' => (int)$reporter->ID,
        ]);
        $this->assertSame(403, $saved->getStatusCode());
        $this->assertSame(0, (int)MashaFeedlyEntry::get()->byID($entry->ID)->ReportedByID);
    }

    /** Stellt sicher, dass CMS-Neu-Markierungen mit dem Website-Zähler desselben Admins übereinstimmen. */
    public function testAdminBoardNewBadgesMatchWebsiteUnreadCount(): void
    {
        $this->logInAsAllowedAdmin();
        $member = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $member);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();

        $unreadIDs = MashaFeedlyEntryRead::unreadEntryIDs($member);
        $unreadCounts = MashaFeedlyEntryRead::unreadCounts($member);
        $this->assertSame(
            count($unreadIDs),
            $unreadCounts['general'] + $unreadCounts['personal'],
            'Die getrennten CMS-Zähler müssen alle ungelesenen Einträge enthalten.'
        );
        $websiteResponse = $this->get('/__masha-feedly/listEntries?mode=all');
        $websiteData = json_decode($websiteResponse->getBody(), true);
        $this->assertIsArray($websiteData);
        $this->assertSame(count($unreadIDs), (int)$websiteData['unreadCount']);

        $boardResponse = $this->get('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry');
        $body = str_replace(['\\u003C', '\\u003E', '\\u0022'], ['<', '>', '"'], $boardResponse->getBody());
        if ($unreadIDs) {
            $this->assertMatchesRegularExpression(
                '/class="masha-feedly-board__new-icon" viewBox="0 0 177800 177800"[\s\S]*?fill="#48b02c"[\s\S]*?fill="#fff"/',
                $body,
                'Ungelesene Karten müssen das Icon zeigen; die verständliche Beschriftung bleibt im Screenreader-Titel.'
            );
            $this->assertStringNotContainsString('>Neu</span>', $body);
            $this->assertStringNotContainsString('>New</span>', $body);
        }
        foreach ($unreadIDs as $entryID) {
            $this->assertMatchesRegularExpression(
                '/data-entry-id="' . (int)$entryID . '" data-entry-unread="true"/',
                $body,
                'Jeder ungelesene Eintrag muss im CMS als neu markiert sein.'
            );
        }
        $this->assertSame(
            count($unreadIDs),
            preg_match_all('/<span class="masha-feedly-board__new-indicator"/', $body),
            'Die Zahl der Neu-Markierungen muss dem Website-Zähler entsprechen.'
        );
    }

    /** Auch das CMS-Board leitet einen fremden Abschluss nach Feedback um. */
    public function testBoardMoveToDoneWaitsForReporterConfirmation(): void
    {
        $creator = $this->objFromFixture(Member::class, 'allowed');
        $editor = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($creator);
        $this->logInAs($creator);
        MashaFeedlyCategory::ensureDefaultCategories();
        $entry = MashaFeedlyEntry::create([
            'Content' => 'Abschluss im CMS prüfen',
            'CategoryID' => (int)MashaFeedlyCategory::get()->filter('SystemKey', 'backlog')->first()->ID,
        ]);
        $entry->write();
        $this->allowMember($editor);
        $this->logInAs($editor);
        $done = MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first();
        $feedback = MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->first();
        $response = $this->post('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/moveEntry', [
            'SecurityID' => SecurityToken::getSecurityID(),
            'EntryID' => (int)$entry->ID,
            'CategoryID' => (int)$done->ID,
            'EntryIDs' => [(int)$entry->ID],
        ]);
        $data = json_decode($response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['sentToFeedback']);
        $this->assertSame((int)$feedback->ID, $data['categoryID']);
        $this->assertStringContainsString('bleibt offen', $data['message']);
        $this->assertSame((int)$feedback->ID, (int)MashaFeedlyEntry::get()->byID((int)$entry->ID)->CategoryID);
    }

    /** Prüft, dass die Drag-and-drop-Aktion Kategorie und Reihenfolge serverseitig speichert. */
    public function testMoveEntryChangesCategoryAndSortOrder(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $this->logInAs($member);

        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $category = MashaFeedlyCategory::get()->filter('Title', 'Doing')->first();
        $secondEntry = MashaFeedlyEntry::create([
            'Content' => 'Der zweite Eintrag in der neuen Sortierung.',
            'EntryDate' => '2026-10-01 09:20:00',
            'CategoryID' => (int)$category->ID,
            'Sort' => 20,
        ]);
        $secondEntry->write();

        $response = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/moveEntry',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'EntryID' => (int)$entry->ID,
                'CategoryID' => (int)$category->ID,
                'EntryIDs' => [(int)$secondEntry->ID, (int)$entry->ID],
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"success":true', $response->getBody());
        $this->assertSame((int)$category->ID, (int)MashaFeedlyEntry::get()->byID($entry->ID)->CategoryID);
        $this->assertSame(20, (int)MashaFeedlyEntry::get()->byID($entry->ID)->Sort);
        $this->assertSame(10, (int)MashaFeedlyEntry::get()->byID($secondEntry->ID)->Sort);
        $history = MashaFeedlyEntryHistory::get()
            ->filter(['EntryID' => (int)$entry->ID, 'ChangeType' => 'status'])
            ->first();
        $this->assertNotNull($history);
        $this->assertSame('status', (string)$history->ChangeType);
        $this->assertSame('Backlog', (string)$history->OldValue);
        $this->assertSame('Doing', (string)$history->NewValue);
        $this->assertSame('Erika Muster', (string)$history->ActorName);
        $this->assertNotEmpty((string)$history->Created);
    }

    /** Prüft, dass die Kategorienreihenfolge im Board serverseitig gespeichert wird. */
    public function testMoveCategoryChangesSortOrder(): void
    {
        $this->logInAsAllowedAdmin();
        $categoryIDs = array_map('intval', MashaFeedlyCategory::get()->sort('Sort ASC, Title ASC')->column('ID'));
        $reversedIDs = array_reverse($categoryIDs);

        $response = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/moveCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'CategoryIDs' => $reversedIDs,
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"success":true', $response->getBody());
        $this->assertSame(
            $reversedIDs,
            array_map('intval', MashaFeedlyCategory::get()->sort('Sort ASC, Title ASC')->column('ID'))
        );
    }

    /** Prüft, dass Administratoren eine benutzerdefinierte Kategorie direkt im Board anlegen können. */
    public function testCreateCategoryAddsCustomCategory(): void
    {
        $this->logInAsAllowedAdmin();
        $response = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/createCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'Title' => '  Barrierefreiheit  ',
            ]
        );
        $data = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $category = MashaFeedlyCategory::get()->byID((int)$data['category']['id']);
        $this->assertNotNull($category);
        $this->assertSame('Barrierefreiheit', (string)$category->Title);
        $this->assertSame('', (string)$category->SystemKey);
        $this->assertFalse((bool)$category->IsClosed);
    }

    /** Prüft, dass leere und zu lange Kategorienamen abgelehnt werden. */
    public function testCreateCategoryRejectsInvalidTitle(): void
    {
        $this->logInAsAllowedAdmin();
        foreach (['', str_repeat('x', 121)] as $title) {
            $response = $this->post(
                '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/createCategory',
                [
                    'SecurityID' => SecurityToken::getSecurityID(),
                    'Title' => $title,
                ]
            );
            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('"success":false', $response->getBody());
        }
    }

    /** Prüft, dass nur leere benutzerdefinierte Kategorien gelöscht werden können. */
    public function testDeleteCategoryOnlyDeletesEmptyCustomCategory(): void
    {
        $this->logInAsAllowedAdmin();
        $emptyCategory = MashaFeedlyCategory::create([
            'Title' => 'Leere Testkategorie',
            'SystemKey' => '',
            'Sort' => 900,
        ]);
        $emptyCategory->write();

        $deleteResponse = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/deleteCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'CategoryID' => (int)$emptyCategory->ID,
            ]
        );
        $this->assertSame(200, $deleteResponse->getStatusCode());
        $this->assertStringContainsString('"success":true', $deleteResponse->getBody());
        $this->assertNull(MashaFeedlyCategory::get()->byID((int)$emptyCategory->ID));

        $occupiedCategory = MashaFeedlyCategory::create([
            'Title' => 'Kategorie mit Eintrag',
            'SystemKey' => '',
            'Sort' => 910,
        ]);
        $occupiedCategory->write();
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entry->CategoryID = (int)$occupiedCategory->ID;
        $entry->write();
        $blockedResponse = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/deleteCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'CategoryID' => (int)$occupiedCategory->ID,
            ]
        );
        $this->assertSame(409, $blockedResponse->getStatusCode());
        $this->assertNotNull(MashaFeedlyCategory::get()->byID((int)$occupiedCategory->ID));

        $requiredCategory = MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first();
        $requiredResponse = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/deleteCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'CategoryID' => (int)$requiredCategory->ID,
            ]
        );
        $this->assertSame(409, $requiredResponse->getStatusCode());
        $this->assertNotNull(MashaFeedlyCategory::get()->byID((int)$requiredCategory->ID));

        $optionalSystemCategory = MashaFeedlyCategory::get()->filter('SystemKey', 'todo')->first();
        $optionalResponse = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/deleteCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'CategoryID' => (int)$optionalSystemCategory->ID,
            ]
        );
        $this->assertSame(200, $optionalResponse->getStatusCode());
        $this->assertNull(MashaFeedlyCategory::get()->byID((int)$optionalSystemCategory->ID));
    }

    /** Prüft, dass das Board nur für leere, frei angelegte Kategorien eine Löschaktion zeigt. */
    public function testBoardShowsCategorySortingAndSafeDeleteControls(): void
    {
        $this->logInAsAllowedAdmin();
        $emptyCategory = MashaFeedlyCategory::create([
            'Title' => 'Leere Kategorie',
            'SystemKey' => '',
            'Sort' => 900,
        ]);
        $emptyCategory->write();
        $occupiedCategory = MashaFeedlyCategory::create([
            'Title' => 'Belegte Kategorie',
            'SystemKey' => '',
            'Sort' => 910,
        ]);
        $occupiedCategory->write();
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entry->CategoryID = (int)$occupiedCategory->ID;
        $entry->write();

        $response = $this->get('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry');
        $body = $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-move-category-url=', $body);
        $this->assertStringContainsString('data-delete-category-url=', $body);
        $this->assertStringContainsString('data-create-category-url=', $body);
        $this->assertStringContainsString('masha-feedly-board__category-drag-handle', $body);
        $this->assertStringContainsString('data-open-category-form', $body);
        $this->assertStringContainsString('masha-feedly-board__action-button--category', $body);
        $this->assertStringContainsString('data-create-category-form', $body);
        $this->assertStringContainsString('data-submit-category-form', $body);
        $this->assertStringContainsString('class="masha-feedly-board__category-form" data-create-category-form role="form"', $body);
        $this->assertStringContainsString('data-category-modal hidden="hidden"', $body);
        $this->assertStringContainsString('placeholder=', $body);
        $this->assertStringContainsString('data-category-title-input', $body);
        $this->assertStringContainsString('role="dialog" aria-modal="true" aria-labelledby="masha-feedly-category-title"', $body);
        $this->assertMatchesRegularExpression('/(?:Kategorie hinzufügen|Add category)/', $body);
        $this->assertGreaterThan(1, substr_count($body, ' data-delete-category '));
        $this->assertStringContainsString('Leere Kategorie', $body);
        $this->assertStringContainsString('Belegte Kategorie', $body);
    }

    /** Prüft, dass nicht freigeschaltete Mitglieder weder das Board öffnen noch Einträge verschieben können. */
    public function testMemberWithoutAccessCannotViewOrMoveEntries(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $blockedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowedMember);
        $this->logInAs($blockedMember);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $originalCategoryID = (int)$entry->CategoryID;
        $customCategory = MashaFeedlyCategory::create([
            'Title' => 'Geschützte Testkategorie',
            'SystemKey' => '',
            'Sort' => 900,
        ]);
        $customCategory->write();

        $boardResponse = $this->get('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry');
        $moveResponse = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/moveEntry',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'EntryID' => (int)$entry->ID,
                'CategoryID' => (int)MashaFeedlyCategory::get()->filter('Title', 'Doing')->first()->ID,
                'EntryIDs' => [(int)$entry->ID],
            ]
        );
        $sortResponse = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/moveCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'CategoryIDs' => array_map('intval', MashaFeedlyCategory::get()->column('ID')),
            ]
        );
        $deleteResponse = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/deleteCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'CategoryID' => (int)$customCategory->ID,
            ]
        );
        $createResponse = $this->post(
            '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry/createCategory',
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'Title' => 'Nicht erlaubt',
            ]
        );

        $this->assertNotSame(200, $boardResponse->getStatusCode());
        $this->assertNotSame(200, $moveResponse->getStatusCode());
        $this->assertSame(403, $sortResponse->getStatusCode());
        $this->assertSame(403, $deleteResponse->getStatusCode());
        $this->assertSame(403, $createResponse->getStatusCode());
        $this->assertNotNull(MashaFeedlyCategory::get()->byID((int)$customCategory->ID));
        $this->assertSame($originalCategoryID, (int)MashaFeedlyEntry::get()->byID($entry->ID)->CategoryID);
    }

    /** Prüft, dass alle zugewiesenen freigegebenen Mitglieder auf der Board-Karte erscheinen. */
    public function testBoardShowsEveryAssignedMemberOnTheEntryCard(): void
    {
        $firstMember = $this->objFromFixture(Member::class, 'allowed');
        $secondMember = $this->objFromFixture(Member::class, 'notAllowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([
            (int)$firstMember->ID,
            (int)$secondMember->ID,
        ]);
        $config->write();
        $this->logInAs($firstMember);

        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $entry->AssignedMembers()->setByIDList([(int)$firstMember->ID, (int)$secondMember->ID]);
        $firstMember->MashaFeedlyColor = '#E7A6C8';
        $firstMember->write();
        $secondMember->MashaFeedlyColor = '#91B8E8';
        $secondMember->write();
        $response = $this->get('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry');
        $body = $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('masha-feedly-board__assignees', $body);
        $this->assertStringContainsString('data-has-assignees="true"', $body);
        $this->assertMatchesRegularExpression(
            '/<div class="masha-feedly-board__card-topline"[\s\S]*?<\/div><div class="masha-feedly-board__card-heading"[\s\S]*?<\/time><div class="masha-feedly-board__assignees"/',
            $body,
            'Avatar-Gruppe muss außerhalb der Kopfzeile als direktes Kind der Karte nach den Inhalten gerendert werden.'
        );
        $response = $this->get('/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry');
        $body = $response->getBody();
        $this->assertStringContainsString(
            'masha-feedly-board__assignee" style="--masha-feedly-member-color:#E7A6C8" aria-label="'
            . $firstMember->getName() . '"',
            $body
        );
        $this->assertStringContainsString('masha-feedly-board__assignee-icon" aria-hidden="true">EM</span>', $body);
        $this->assertStringContainsString('masha-feedly-board__assignee-icon" aria-hidden="true">MB</span>', $body);
        $this->assertStringContainsString('style="--masha-feedly-member-color:#E7A6C8"', $body);
        $this->assertStringContainsString('style="--masha-feedly-member-color:#91B8E8"', $body);
        $this->assertSame(2, preg_match_all('/class="masha-feedly-board__assignee"/', $body));
        $this->assertStringNotContainsString('Zugewiesen an', $body);
        $this->assertStringNotContainsString('<span>' . $firstMember->getName() . '</span></span>', $body);
        $this->assertStringContainsString('class="masha-feedly-board__new-indicator"', $body);
        $this->assertStringNotContainsString('masha-feedly-board__new-indicator--personal', $body);
        $this->assertStringNotContainsString('>Neu</span>', $body);
        $this->assertStringContainsString('Neue Aktivität für dich', $body);
    }

    /** Prüft, dass Administratoren Avatarfarben freigegebener Benutzer konfigurieren können. */
    public function testConfigurationShowsEditableColorsForAllowedMembers(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $this->logInAsAllowedAdmin();
        $superAdmin = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $superAdmin);
        \SilverStripe\Core\Config\Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$superAdmin->Email]);

        $response = $this->get('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('MashaFeedlyMemberColor_' . (int)$member->ID, $response->getBody());
        $this->assertStringContainsString('masha-feedly-color-palette__swatch', $response->getBody());
        $this->assertStringContainsString('#E95DAB', $response->getBody());
        $this->assertStringContainsString('aria-label="Pink, #E95DAB"', $response->getBody());
        $this->assertStringContainsString('name="MashaFeedlyAddress"', $response->getBody());
        $this->assertStringContainsString('General', $response->getBody());
        $this->assertStringContainsString('Access &amp; appearance', $response->getBody());
        $this->assertStringContainsString('Reminders &amp; estimates', $response->getBody());
        $this->assertStringContainsString('Reset data', $response->getBody());
        $this->assertStringContainsString('masha-feedly-config-section--general', $response->getBody());
        $this->assertStringContainsString('masha-feedly-config-section--operations ss-toggle ss-toggle-start-closed', $response->getBody());
        $this->assertStringContainsString('masha-feedly-config-section--reset ss-toggle ss-toggle-start-closed', $response->getBody());
        $this->assertStringContainsString('name="MashaFeedlyFontSize"', $response->getBody());
        $this->assertStringContainsString('name="MashaFeedlyTheme"', $response->getBody());
        $this->assertStringContainsString('name="MashaFeedlyDueDateReminderMode"', $response->getBody());
        $this->assertStringContainsString('value="cron"', $response->getBody());
        $this->assertStringContainsString('value="visitor"', $response->getBody());
        $this->assertStringContainsString('data-masha-feedly-animation-previews', $response->getBody());
        $this->assertStringContainsString('data-masha-feedly-effect-catalog', $response->getBody());
        $this->assertStringContainsString('masha-feedly-effects.js', $response->getBody());
        $this->assertStringContainsString('KWMashaFeedlyEffectsManifestURL', $response->getBody());
        $this->assertStringNotContainsString('effects/unicorn.js', $response->getBody());
        $this->assertStringNotContainsString('data-unicorn-url', $response->getBody());
        $this->assertStringContainsString('Preview completion animations', $response->getBody());
        $this->assertStringContainsString('value="small"', $response->getBody());
        $this->assertStringContainsString('value="medium"', $response->getBody());
        $this->assertStringContainsString('value="large"', $response->getBody());
        $this->assertStringContainsString('value="playful"', $response->getBody());
        $this->assertStringContainsString('value="serious"', $response->getBody());
        $this->assertStringContainsString('value="sie"', $response->getBody());
        $this->assertStringNotContainsString('MashaFeedlyIconImage', $response->getBody());
        $this->assertSame(1, preg_match('/<form[^>]+action="([^"]+)"/', $response->getBody(), $matches));

        $saveResponse = $this->post(
            html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'AllowedMemberIDs' => [
                    (int)$member->ID,
                    (int)$this->objFromFixture(Member::class, 'notAllowed')->ID,
                ],
                'MashaFeedlyMemberColor_' . (int)$member->ID => '#B5A0E0',
                'MashaFeedlyAddress' => 'sie',
                'MashaFeedlyFontSize' => 'large',
                'MashaFeedlyTheme' => 'serious',
                'MashaFeedlyDueDateReminderMode' => 'visitor',
                'action_saveConfiguration' => 'Konfiguration speichern',
            ]
        );

        $this->assertSame(200, $saveResponse->getStatusCode());
        $this->assertSame('sie', MashaFeedlyConfigExtension::address());
        $this->assertSame('large', MashaFeedlyConfigExtension::fontSize());
        $this->assertSame('serious', MashaFeedlyConfigExtension::theme());
        $this->assertSame('visitor', MashaFeedlyConfigExtension::dueDateReminderMode());
        $this->assertSame('#B5A0E0', (string)Member::get()->byID($member->ID)->MashaFeedlyColor);
        $automaticallyColoredMember = Member::get()->byID((int)$this->objFromFixture(Member::class, 'notAllowed')->ID);
        $this->assertNotSame('', (string)$automaticallyColoredMember->MashaFeedlyColor);
        $this->assertNotSame('#B5A0E0', (string)$automaticallyColoredMember->MashaFeedlyColor);
    }

    /** CMS-Administratoren können die konfigurierte Mailstrecke aus der Modulkonfiguration testen. */
    public function testConfigurationCanSendTestEmailToCurrentAdmin(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyEmailTestSucceeded = false;
        $siteConfig->write();
        $this->logInAsAllowedAdmin();
        $admin = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $admin);
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$admin->Email]);
        $response = $this->get('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('action_sendTestEmail', $response->getBody());
        $this->assertStringContainsString('masha-feedly-email-test-notice', $response->getBody());
        $this->assertStringContainsString('E-Mail-Test noch nicht erfolgreich durchgeführt', $response->getBody());
        $this->assertSame(1, preg_match('/<form[^>]+action="([^"]+)"/', $response->getBody(), $matches));

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] */
            public array $messages = [];
            public ?\Throwable $failure = null;
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if ($this->failure) {
                    throw $this->failure;
                }
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        try {
            $mailer->failure = new \RuntimeException('Simulierter SMTP-Ausfall vor dem ersten erfolgreichen Versand.');
            $initialFailure = $this->post(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), [
                'SecurityID' => SecurityToken::getSecurityID(),
                'action_sendTestEmail' => 'Test-E-Mail senden',
            ]);
            $this->assertSame(200, $initialFailure->getStatusCode());
            $this->assertFalse(MashaFeedlyConfigExtension::emailTestSucceeded());
            $this->assertCount(0, $mailer->messages);

            $mailer->failure = null;
            $sent = $this->post(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), [
                'SecurityID' => SecurityToken::getSecurityID(),
                'action_sendTestEmail' => 'Test-E-Mail senden',
            ]);

            $this->assertSame(200, $sent->getStatusCode());
            $this->assertCount(1, $mailer->messages);
            $this->assertSame((string)$admin->Email, $mailer->messages[0]->getTo()[0]->getAddress());
            $this->assertSame('Masha:Feedly – Test-E-Mail', $mailer->messages[0]->getSubject());
            $this->assertTrue(MashaFeedlyConfigExtension::emailTestSucceeded());
            $configuredResponse = $this->get('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig');
            $this->assertStringNotContainsString('masha-feedly-email-test-notice', $configuredResponse->getBody());

            $mailer->failure = new \RuntimeException('Verbindung über smtp://mailuser:geheim@example.test:587 fehlgeschlagen.');
            $failed = $this->post(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), [
                'SecurityID' => SecurityToken::getSecurityID(),
                'action_sendTestEmail' => 'Test-E-Mail senden',
            ]);
            $this->assertSame(200, $failed->getStatusCode());
            $this->assertStringContainsString('Verbindung über smtp://[redacted]@example.test:587 fehlgeschlagen.', $failed->getBody());
            $this->assertStringNotContainsString('geheim', $failed->getBody());
            $this->assertCount(1, $mailer->messages);
            $this->assertTrue(MashaFeedlyConfigExtension::emailTestSucceeded());
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Normale CMS-Admins erhalten weder sensible Einstellungen im Formular noch per manipuliertem POST Schreibzugriff. */
    public function testOrdinaryCmsAdminCannotSeeOrChangeSensitiveSettingsAndColors(): void
    {
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $allowed = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($allowed);
        $this->logInAsAllowedAdmin();
        $admin = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $admin);
        \SilverStripe\Core\Config\Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', ['super-admin@example.test']);

        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyDueDateReminderMode = 'cron';
        $siteConfig->MashaFeedlyHourlyRate = 125;
        $siteConfig->write();
        $allowed->MashaFeedlyColor = '#E95DAB';
        $allowed->write();

        $response = $this->get('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig');
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringNotContainsString('name="MashaFeedlyDueDateReminderMode"', $body);
        $this->assertStringNotContainsString('name="MashaFeedlyHourlyRate"', $body);
        $this->assertStringContainsString('General', $body);
        $this->assertStringContainsString('Access &amp; appearance', $body);
        $this->assertStringNotContainsString('Reminders &amp; estimates', $body);
        $this->assertStringNotContainsString('Reset data', $body);
        $this->assertStringNotContainsString('masha-feedly-config-section--operations', $body);
        $this->assertStringNotContainsString('masha-feedly-config-section--reset', $body);
        $this->assertStringNotContainsString('data-masha-feedly-animation-previews', $body);
        $this->assertStringNotContainsString('name="ResetConfirmation"', $body);
        $this->assertStringNotContainsString('action_resetAllMashaFeedlyData', $body);
        $this->assertStringNotContainsString('action_sendTestEmail', $body);
        $this->assertStringNotContainsString('MashaFeedlyMemberColor_' . (int)$allowed->ID, $body);
        $this->assertSame(1, preg_match('/<form[^>]+action="([^"]+)"/', $body, $matches));

        $testEmailAttempt = $this->post(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), [
            'SecurityID' => SecurityToken::getSecurityID(),
            'action_sendTestEmail' => 'Test-E-Mail senden',
        ]);
        $this->assertSame(403, $testEmailAttempt->getStatusCode());

        $saveResponse = $this->post(
            html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'AllowedMemberIDs' => [(int)$allowed->ID],
                'MashaFeedlyDueDateReminderMode' => 'visitor',
                'MashaFeedlyHourlyRate' => '9999',
                'MashaFeedlyMemberColor_' . (int)$allowed->ID => '#00FF00',
                'action_saveConfiguration' => 'Konfiguration speichern',
            ]
        );

        $this->assertSame(200, $saveResponse->getStatusCode());
        $this->assertSame('cron', MashaFeedlyConfigExtension::dueDateReminderMode());
        $this->assertSame(125.0, MashaFeedlyConfigExtension::hourlyRate());
        $this->assertSame('#E95DAB', (string)Member::get()->byID($allowed->ID)->MashaFeedlyColor);

        $resetAttempt = $this->post(
            html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            [
                'SecurityID' => SecurityToken::getSecurityID(),
                'ResetConfirmation' => 'RESET',
                'action_resetAllMashaFeedlyData' => 'Alle Masha:Feedly-Daten löschen',
            ]
        );
        $this->assertNotSame(0, $resetAttempt->getStatusCode());
        $this->assertNotNull(MashaFeedlyEntry::get()->byID((int)$entry->ID), 'Ein normaler CMS-Admin darf den Reset auch per direktem POST nicht ausführen.');
    }

    /** Nur der konfigurierte Superadmin kann den vollständigen Inhaltsreset auslösen. */
    public function testSuperAdminCanResetAllContentAndReseedCategories(): void
    {
        $adminMember = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($adminMember);
        $this->logInAsAllowedAdmin();
        $superAdmin = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $superAdmin);
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$superAdmin->Email]);

        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $otherEntry = MashaFeedlyEntry::create([
            'Title' => 'Zweiter Eintrag',
            'Content' => 'Wird ebenfalls gelöscht',
            'CategoryID' => (int)$entry->CategoryID,
        ]);
        $otherEntry->write();
        $comment = MashaFeedlyComment::create([
            'EntryID' => (int)$entry->ID,
            'AuthorName' => 'Erika',
            'CommentText' => 'Kommentar zum Löschen',
        ]);
        $comment->write();
        MashaFeedlyCommentReaction::create([
            'CommentID' => (int)$comment->ID,
            'MemberID' => (int)$adminMember->ID,
            'Emoji' => '👍',
        ])->write();
        MashaFeedlyAttachment::create(['EntryID' => (int)$entry->ID, 'OriginalName' => 'alt.png'])->write();
        MashaFeedlyEntryRelation::create([
            'EntryID' => (int)$entry->ID,
            'RelatedEntryID' => (int)$otherEntry->ID,
            'LinkType' => 'duplicate_of',
        ])->write();
        MashaFeedlyEntryHistory::record($entry, 'comment', '', 'Kommentar', $adminMember);
        MashaFeedlyEntryRead::markAsSeen($entry, $adminMember);
        MashaFeedlyCategory::create(['Title' => 'Eigene Kategorie', 'Sort' => 90])->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyHourlyRate = 175;
        $config->MashaFeedlyEstimateCategoriesSeeded = true;
        $config->write();

        $url = '/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig';
        $body = $this->get($url)->getBody();
        $this->assertStringContainsString('name="ResetConfirmation"', $body);
        $this->assertStringContainsString('action_resetAllMashaFeedlyData', $body);
        $this->assertSame(1, preg_match('/<form[^>]+action="([^"]+)"/', $body, $matches));
        $actionURL = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $wrongConfirmation = $this->post($actionURL, [
            'SecurityID' => SecurityToken::getSecurityID(),
            'ResetConfirmation' => 'delete',
            'action_resetAllMashaFeedlyData' => 'Alle Masha:Feedly-Daten löschen',
        ]);
        $this->assertSame(2, MashaFeedlyEntry::get()->count());
        $this->assertSame(1, MashaFeedlyComment::get()->count());

        $response = $this->post($actionURL, [
            'SecurityID' => SecurityToken::getSecurityID(),
            'ResetConfirmation' => 'RESET',
            'action_resetAllMashaFeedlyData' => 'Alle Masha:Feedly-Daten löschen',
        ]);

        $this->assertSame(200, $wrongConfirmation->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, MashaFeedlyEntry::get()->count());
        $this->assertSame(0, MashaFeedlyComment::get()->count());
        $this->assertSame(0, MashaFeedlyCommentReaction::get()->count());
        $this->assertSame(0, MashaFeedlyAttachment::get()->count());
        $this->assertSame(0, MashaFeedlyEntryRelation::get()->count());
        $this->assertSame(0, MashaFeedlyEntryHistory::get()->count());
        $this->assertSame(0, MashaFeedlyEntryRead::get()->count());
        foreach (['backlog', 'todo', 'doing', 'done', 'archive', 'feedback', 'estimate_pending', 'estimate_approved'] as $key) {
            $this->assertSame(1, MashaFeedlyCategory::get()->filter('SystemKey', $key)->count(), 'Systemkategorie ' . $key . ' muss genau einmal neu angelegt werden.');
        }
        $this->assertNotContains('Eigene Kategorie', MashaFeedlyCategory::get()->column('Title'));
        $this->assertSame(175.0, MashaFeedlyConfigExtension::hourlyRate());
        $this->assertContains((int)$adminMember->ID, MashaFeedlyConfigExtension::memberIDs());
    }

    /** Nicht zugeordnete CMS-Admins dürfen weder Menü noch direkte Modulrouten öffnen. */
    public function testUnassignedAdminCannotAccessFeedlyAdmin(): void
    {
        $this->logInWithPermission('ADMIN');
        $member = Security::getCurrentUser();
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', ['operator@example.test']);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = '[]';
        $config->write();
        $this->assertFalse(\KW\MashaFeedly\Admin\MashaFeedlyAdmin::create()->canView($member));
        $cms = $this->get('/admin/pages');
        $this->assertSame(200, $cms->getStatusCode());
        $this->assertStringNotContainsString('Menu-KW-MashaFeedly-Admin-MashaFeedlyAdmin', $cms->getBody());
        foreach (['/admin/masha-feedly', '/admin/masha-feedly/KW-MashaFeedly-Model-MashaFeedlyEntry', '/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig'] as $url) {
            $this->assertSame(403, $this->get($url)->getStatusCode(), $url);
        }
    }

    /** Erlaubt bestehenden Admin-Tests ausdrücklich den Modulzugriff. */
    private function logInAsAllowedAdmin(): void
    {
        $this->logInWithPermission('ADMIN');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode(array_unique([
            ...MashaFeedlyConfigExtension::memberIDs(), (int)Security::getCurrentUser()->ID,
        ]));
        $config->write();
    }

    /** Schreibt eine Testfreigabe, ohne produktive Silverstripe-Mitglieder anzulegen. */
    private function allowMember(Member $member): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();
    }
}
