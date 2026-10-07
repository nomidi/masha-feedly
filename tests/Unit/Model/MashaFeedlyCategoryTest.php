<?php

namespace KW\MashaFeedly\Tests\Unit\Model;

use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Service\MashaFeedlyFolderService;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\DropdownField;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\ORM\DB;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Assets\Folder;
use SilverStripe\Security\InheritedPermissions;

/**
 * Tests für die GTD-Statuskategorien von Masha Feedly.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyCategoryTest extends SapphireTest
{
    // Kategorieprüfungen brauchen echtes Schema und laufen strikt auf der isolierten PHPUnit-Datenbank.
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('de_DE');
        if (!SiteConfig::get()->exists()) {
            SiteConfig::create()->write();
        }
    }

    /** Prüft, dass beim erstmaligen Aufbau alle Standardkategorien erstellt werden. */
    public function testDefaultCategoriesAreCreated(): void
    {
        MashaFeedlyCategory::ensureDefaultCategories();

        $expectedTitles = [
            'Backlog',
            'To Do',
            'Doing',
            'Done',
            'Archiv',
            'Feedback',
            'Kostenschätzung wartet auf Freigabe',
            'Kostenschätzung freigegeben',
        ];
        $actualTitles = array_values(array_unique(MashaFeedlyCategory::get()->column('Title')));
        sort($expectedTitles);
        sort($actualTitles);
        $this->assertSame($expectedTitles, $actualTitles);
        $this->assertTrue((bool)MashaFeedlyCategory::get()->filter('Title', 'Done')->first()->IsClosed);
        $this->assertTrue((bool)MashaFeedlyCategory::get()->filter('Title', 'Archiv')->first()->IsClosed);
        $this->assertFalse((bool)MashaFeedlyCategory::get()->filter('Title', 'Backlog')->first()->IsClosed);
    }

    /** Regressionstest für den Category-RequireDefaultRecords-Hook während dev/build. */
    public function testRequireDefaultRecordsCreatesRestrictedAssetFolders(): void
    {
        MashaFeedlyCategory::singleton()->requireDefaultRecords();

        $folder = Folder::get()->filter('Name', 'masha-feedly')->first();
        $this->assertNotNull($folder);
        $this->assertSame(InheritedPermissions::ONLY_THESE_USERS, $folder->CanViewType);
        $this->assertSame(
            [(int)MashaFeedlyFolderService::permissionGroup()->ID],
            array_map('intval', $folder->ViewerGroups()->column('ID'))
        );
    }

    /** Prüft, dass eigene Kategorien zusätzlich zu den Standardkategorien angelegt werden können. */
    public function testCustomCategoryCanBeAdded(): void
    {
        $category = MashaFeedlyCategory::create([
            'Title' => 'Rückfrage',
            'Sort' => 70,
        ]);
        $category->write();

        $this->assertSame('Rückfrage', MashaFeedlyCategory::get()->byID($category->ID)->Title);
        $this->assertFalse((bool)$category->IsClosed);
        $category->IsClosed = true;
        $category->write();
        $this->assertTrue((bool)MashaFeedlyCategory::get()->byID($category->ID)->IsClosed);
    }

    /** Prüft, dass die Abschlusskennzeichnung im CMS einer Kategorie bearbeitbar ist. */
    public function testClosedCategoryCanBeConfiguredInCMS(): void
    {
        MashaFeedlyCategory::ensureDefaultCategories();
        $fields = MashaFeedlyCategory::get()->filter('Title', 'Done')->first()->getCMSFields();

        $this->assertNotNull($fields->dataFieldByName('IsClosed'));
        $this->assertSame('Als abgeschlossen behandeln', $fields->dataFieldByName('IsClosed')->Title());
        $roleField = $fields->dataFieldByName('SystemKey');
        $this->assertInstanceOf(DropdownField::class, $roleField);
        $this->assertSame('Erledigt (erforderlich)', $roleField->getSource()['done']);
        $this->assertSame('Wartet auf Freigabe (erforderlich)', $roleField->getSource()['feedback']);
        $this->assertSame('Startstatus (erforderlich)', $roleField->getSource()['backlog']);
    }

    /** Systemkategorien bleiben anhand ihres Schlüssels erhalten, auch bei Umbenennung oder fehlendem Datensatz. */
    public function testRequiredCategoriesAreRestoredAndKeepTheirIdentityWhenRenamed(): void
    {
        MashaFeedlyCategory::ensureDefaultCategories();
        $done = MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first();
        $feedback = MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->first();
        $this->assertNotNull($done);
        $this->assertNotNull($feedback);
        $this->assertFalse($done->canDelete());
        $this->assertFalse($feedback->canDelete());

        $doneID = (int)$done->ID;
        $done->Title = 'Fertig';
        $done->write();
        $feedback->delete();
        MashaFeedlyCategory::ensureDefaultCategories();

        $this->assertSame(1, MashaFeedlyCategory::get()->filter('SystemKey', 'done')->count());
        $this->assertSame(1, MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->count());
        $this->assertSame($doneID, (int)MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first()->ID);
        $this->assertSame('Fertig', (string)MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first()->Title);
        $this->assertTrue((bool)MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first()->IsClosed);
        $this->assertFalse((bool)MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->first()->IsClosed);
    }

    /** Nur Start, Erledigt und Feedback sind Pflichtrollen; Rollen lassen sich mit dem Dropdown übertragen. */
    public function testRequiredRolesCannotDisappearButCanMoveToRenamedCategories(): void
    {
        MashaFeedlyCategory::ensureDefaultCategories();
        $done = MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first();
        $done->SystemKey = '';
        $this->assertFalse($done->validate()->isValid(), 'Die einzige Erledigt-Rolle darf nicht entfernt werden.');

        $custom = MashaFeedlyCategory::create([
            'Title' => 'Abgeschlossen',
            'SystemKey' => 'done',
            'Sort' => 45,
        ]);
        $custom->write();
        $this->assertSame('', (string)MashaFeedlyCategory::get()->byID($done->ID)->SystemKey);
        $this->assertSame(1, MashaFeedlyCategory::get()->filter('SystemKey', 'done')->count());
        $this->assertTrue((bool)$custom->IsClosed);
        $this->assertSame((int)$custom->ID, (int)MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first()->ID);
    }

    /** Nicht erforderliche Standardstufen können als normale Kategorie abgewählt und gelöscht werden. */
    public function testOptionalWorkflowCategoriesCanBeRemoved(): void
    {
        MashaFeedlyCategory::ensureDefaultCategories();
        $todo = MashaFeedlyCategory::get()->filter('SystemKey', 'todo')->first();
        $todo->SystemKey = '';
        $todo->write();
        $this->assertSame('', (string)$todo->SystemKey, 'Eine optionale Stufe lässt sich zur normalen Kategorie machen.');
        $todo->delete();

        MashaFeedlyCategory::ensureDefaultCategories();

        $this->assertSame(0, MashaFeedlyCategory::get()->filter('SystemKey', 'todo')->count());
        $this->assertSame(0, MashaFeedlyCategory::get()->filter('Title', 'To Do')->count());
        foreach (['backlog', 'done', 'feedback'] as $requiredRole) {
            $this->assertSame(1, MashaFeedlyCategory::get()->filter('SystemKey', $requiredRole)->count());
        }
    }

    /** Kostenschätzungskategorien werden initial angelegt, bleiben optional und erscheinen nach Löschung nicht erneut. */
    public function testEstimateCategoriesAreSeededOnceAndCanBeDeleted(): void
    {
        $this->logInWithPermission('ADMIN');
        MashaFeedlyCategory::ensureDefaultCategories();

        $pending = MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_pending')->first();
        $approved = MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_approved')->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($approved);
        $pending->delete();
        $approved->delete();
        MashaFeedlyCategory::ensureDefaultCategories();

        $this->assertSame(0, MashaFeedlyCategory::get()->filter('SystemKey', ['estimate_pending', 'estimate_approved'])->count());
    }

    /** Wiederholte Build-Läufe dürfen keine parallelen Kostenschätzungskategorien hinterlassen. */
    public function testDuplicateEstimateCategoriesAreMergedWithoutLosingEntries(): void
    {
        $this->logInWithPermission('ADMIN');
        MashaFeedlyCategory::ensureDefaultCategories();

        $duplicate = MashaFeedlyCategory::create([
            'Title' => 'Kostenschätzung wartet auf Freigabe (Kopie)',
            'Sort' => 90,
        ]);
        $duplicate->write();
        // Simuliert Altbestand aus Builds, bevor die Einmalanlage korrekt abgesichert war.
        $categoryTable = DB::get_conn()->escapeIdentifier('MashaFeedlyCategory');
        $systemKeyField = DB::get_conn()->escapeIdentifier('SystemKey');
        $idField = DB::get_conn()->escapeIdentifier('ID');
        DB::prepared_query(
            "UPDATE {$categoryTable} SET {$systemKeyField} = ? WHERE {$idField} = ?",
            ['estimate_pending', (int)$duplicate->ID]
        );

        $entry = MashaFeedlyEntry::create([
            'Content' => 'Eintrag einer doppelten Kostenschätzungskategorie',
            'CategoryID' => (int)MashaFeedlyCategory::get()->filter('SystemKey', 'backlog')->first()->ID,
        ]);
        $entry->write();
        $entryTable = DB::get_conn()->escapeIdentifier('MashaFeedlyEntry');
        $categoryIDField = DB::get_conn()->escapeIdentifier('CategoryID');
        $entryIDField = DB::get_conn()->escapeIdentifier('ID');
        DB::prepared_query(
            "UPDATE {$entryTable} SET {$categoryIDField} = ? WHERE {$entryIDField} = ?",
            [(int)$duplicate->ID, (int)$entry->ID]
        );

        MashaFeedlyCategory::ensureDefaultCategories();

        $this->assertSame(1, MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_pending')->count());
        $this->assertNull(MashaFeedlyCategory::get()->byID((int)$duplicate->ID));
        $this->assertSame(
            (int)MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_pending')->first()->ID,
            (int)MashaFeedlyEntry::get()->byID((int)$entry->ID)->CategoryID
        );

        MashaFeedlyCategory::ensureDefaultCategories();
        $this->assertSame(1, MashaFeedlyCategory::get()->filter('SystemKey', 'estimate_pending')->count());
    }
}
