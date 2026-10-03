<?php

namespace KW\MashaFeedly\Tests\Functional;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;

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
        $this->assertStringContainsString('aria-label="Normal"', $body);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $body);
        $this->assertStringContainsString('data-masha-feedly-board', $body);
        $this->assertStringContainsString('data-admin-translations=', $body);
        $this->assertStringContainsString('draggable="true"', $body);
        $this->assertStringContainsString('masha-feedly-admin.js', $body);
        $this->assertStringContainsString('data-open-entry-form', $body);
        $this->assertStringContainsString('data-entry-modal hidden="hidden"', $body);
        $this->assertStringContainsString('data-admin-create-entry-form', $body);
        $this->assertStringContainsString('data-create-url="/__masha-feedly/createEntry"', $body);
        $this->assertStringContainsString('name="Content"', $body);
        $this->assertStringContainsString('name="CategoryID"', $body);
        $this->assertStringContainsString('name="PriorityID"', $body);
        $this->assertMatchesRegularExpression(
            '/<button type="button" class="btn btn-primary masha-feedly-board__action-button masha-feedly-board__action-button--entry" data-open-entry-form[^>]*>Eintrag hinzufügen<\\/button>/',
            $body,
            'Das Eingabeformular für neue Einträge muss als Dialog im CMS geöffnet werden.'
        );
        $this->assertStringNotContainsString('masha-feedly-create=1', $body);
        $this->assertStringNotContainsString('/item/new', $body);
        $this->assertStringContainsString('masha-feedly-board__drag-handle', $body);
        $this->assertStringContainsString('data-masha-feedly-assignee-filter', $body);
        $this->assertLessThan(
            strpos($body, 'masha-feedly-board__columns'),
            strpos($body, 'masha-feedly-board__filters')
        );
        $this->assertStringContainsString('Alle Einträge', $body);
        $this->assertStringContainsString('masha-feedly-board__new-indicator--general', $body);
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
        $this->logInWithPermission('ADMIN');
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
        $this->logInWithPermission('ADMIN');
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
        $this->logInWithPermission('ADMIN');
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
        $this->logInWithPermission('ADMIN');
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
        $this->logInWithPermission('ADMIN');
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
        $this->assertStringContainsString('masha-feedly-board__new-indicator--personal', $body);
        $this->assertStringContainsString('Neue Aktivität für dich', $body);
    }

    /** Prüft, dass Administratoren Avatarfarben freigegebener Benutzer konfigurieren können. */
    public function testConfigurationShowsEditableColorsForAllowedMembers(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $this->logInWithPermission('ADMIN');

        $response = $this->get('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('MashaFeedlyMemberColor_' . (int)$member->ID, $response->getBody());
        $this->assertStringContainsString('masha-feedly-color-palette__swatch', $response->getBody());
        $this->assertStringContainsString('#E95DAB', $response->getBody());
        $this->assertStringContainsString('aria-label="Pink, #E95DAB"', $response->getBody());
        $this->assertStringContainsString('name="MashaFeedlyAddress"', $response->getBody());
        $this->assertStringContainsString('name="MashaFeedlyFontSize"', $response->getBody());
        $this->assertStringContainsString('name="MashaFeedlyTheme"', $response->getBody());
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
                'action_saveConfiguration' => 'Konfiguration speichern',
            ]
        );

        $this->assertSame(200, $saveResponse->getStatusCode());
        $this->assertSame('sie', MashaFeedlyConfigExtension::address());
        $this->assertSame('large', MashaFeedlyConfigExtension::fontSize());
        $this->assertSame('serious', MashaFeedlyConfigExtension::theme());
        $this->assertSame('#B5A0E0', (string)Member::get()->byID($member->ID)->MashaFeedlyColor);
        $automaticallyColoredMember = Member::get()->byID((int)$this->objFromFixture(Member::class, 'notAllowed')->ID);
        $this->assertNotSame('', (string)$automaticallyColoredMember->MashaFeedlyColor);
        $this->assertNotSame('#B5A0E0', (string)$automaticallyColoredMember->MashaFeedlyColor);
    }

    /** Schreibt eine Testfreigabe, ohne produktive Silverstripe-Mitglieder anzulegen. */
    private function allowMember(Member $member): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();
    }
}
