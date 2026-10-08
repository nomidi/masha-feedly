<?php

namespace KW\MashaFeedly\Model;

use KW\MashaFeedly\Service\MashaFeedlyFolderService;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Bearbeitbare GTD-Kategorie für Masha-Feedly-Einträge.
 *
 * @property int $ID Datenbank-ID der Kategorie.
 * @property string $Title Name der Kategorie.
 * @property int $Sort Sortierreihenfolge im Statusfeld.
 * @property bool $IsClosed Kennzeichnet eine abgeschlossene Kategorie ohne Seitenmarkierung.
 * @method \SilverStripe\ORM\DataList<MashaFeedlyEntry> Entries Zugeordnete Einträge.
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyCategory extends DataObject
{
    private static $table_name = 'MashaFeedlyCategory';

    private const REQUIRED_SYSTEM_KEYS = ['backlog', 'done', 'feedback'];

    private static $db = [
        'Title' => 'Varchar(120)',
        'SystemKey' => 'Varchar(32)',
        'Sort' => 'Int',
        'IsClosed' => 'Boolean',
    ];

    private static $has_many = [
        'Entries' => MashaFeedlyEntry::class . '.Category',
    ];

    private static $summary_fields = [
        'Title' => 'Kategorie',
        'Entries.Count' => 'Meldungen',
    ];

    private static $default_sort = 'Sort ASC, Title ASC';

    private static $defaults = [
        'IsClosed' => false,
    ];

    /** Entfernt verwaiste Startregeln beim Löschen einer Kategorie. */
    protected function onBeforeDelete(): void
    {
        parent::onBeforeDelete();
        foreach (MashaFeedlyMiteTrigger::get()->filter('CategoryID', (int)$this->ID) as $trigger) {
            $trigger->delete();
        }
    }

    /** Liefert die Startkategorie und legt alle Standardkategorien bei Bedarf an. */
    public static function defaultCategory(): self
    {
        self::ensureDefaultCategories();
        return self::get()->filter('SystemKey', 'backlog')->first() ?? self::get()->filter('Title', 'Backlog')->first() ?? self::get()->first();
    }

    /** Stellt die erforderlichen Rollen wieder her und ergänzt Standardstufen bei einer Neuinstallation. */
    public static function ensureDefaultCategories(): void
    {
        $categories = [
            ['backlog', 'Backlog', 10, false, ['Backlog'], true],
            ['todo', 'To Do', 20, false, ['To Do', 'Zu erledigen'], false],
            ['doing', 'Doing', 30, false, ['Doing', 'In Bearbeitung'], false],
            ['done', 'Done', 40, true, ['Done', 'Fertig', 'Erledigt', 'Behoben'], true],
            ['archive', 'Archiv', 50, true, ['Archiv', 'Archive'], false],
            ['feedback', 'Feedback', 60, false, ['Feedback', 'Rückmeldung', 'Rueckmeldung'], true],
        ];
        $isFreshInstall = !self::get()->exists();
        if ($isFreshInstall) {
            foreach ($categories as [$key, $title, $sort, $isClosed]) {
                self::create([
                    'Title' => $title,
                    'SystemKey' => $key,
                    'Sort' => $sort,
                    'IsClosed' => $isClosed,
                ])->write();
            }
        }

        foreach ($categories as [$key, $title, $sort, $isClosed, $aliases, $required]) {
            $category = self::get()->filter('SystemKey', $key)->first()
                ?? self::get()->filter(['Title' => $aliases, 'SystemKey' => ''])->first();
            if (!$category && $required) {
                $category = self::create(['Title' => $title]);
            }
            if (!$category) {
                continue;
            }
            $changed = !$category->exists()
                || (string)$category->SystemKey !== $key
                || (!(int)$category->Sort && (int)$sort > 0)
                || (bool)$category->IsClosed !== $isClosed;
            $category->SystemKey = $key;
            if (!(int)$category->Sort) {
                $category->Sort = $sort;
            }
            $category->IsClosed = $isClosed;
            if ($changed) {
                $category->write();
            }
        }

        foreach (array_column($categories, 0) as $key) {
            self::collapseDuplicateRoleCategories($key);
        }

        self::ensureEstimateCategoriesOnce();
        self::collapseDuplicateRoleCategories('estimate_pending');
        self::collapseDuplicateRoleCategories('estimate_approved');
    }

    /** Führt versehentlich doppelt angelegte Systemkategorien zusammen und erhält ihre Einträge. */
    private static function collapseDuplicateRoleCategories(string $key): void
    {
        $duplicates = self::get()->filter('SystemKey', $key)->sort('ID ASC')->toArray();
        if (count($duplicates) < 2) {
            return;
        }

        // Die älteste Kategorie behält ihren Namen und ihre Identität.
        $canonical = array_shift($duplicates);

        foreach ($duplicates as $duplicate) {
            $entryTable = DB::get_conn()->escapeIdentifier(DataObject::getSchema()->tableName(MashaFeedlyEntry::class));
            $categoryField = DB::get_conn()->escapeIdentifier('CategoryID');
            DB::prepared_query(
                "UPDATE {$entryTable} SET {$categoryField} = ? WHERE {$categoryField} = ?",
                [(int)$canonical->ID, (int)$duplicate->ID]
            );
            $duplicate->delete();
        }
    }

    /** Ergänzt optionale Freigabekategorien einmalig, ohne später gelöschte Kategorien neu anzulegen. */
    private static function ensureEstimateCategoriesOnce(): void
    {
        // Während dev/build werden requireDefaultRecords teils ausgeführt,
        // bevor die SiteConfig-Erweiterungsspalten in der Datenbank existieren.
        if (!DB::get_schema()->hasField('SiteConfig', 'MashaFeedlyEstimateCategoriesSeeded')) {
            return;
        }
        try {
            $config = SiteConfig::get()->first();
        } catch (DatabaseException $exception) {
            // Ein laufender dev/build kann die neue SiteConfig-Spalte erst nach
            // requireDefaultRecords anlegen. In diesem Durchlauf später erneut versuchen.
            return;
        }
        if (!$config || (bool)$config->MashaFeedlyEstimateCategoriesSeeded) {
            return;
        }

        foreach ([
            ['estimate_pending', 'Kostenschätzung wartet auf Freigabe', 70],
            ['estimate_approved', 'Kostenschätzung freigegeben', 80],
        ] as [$key, $title, $sort]) {
            $category = self::get()->filter('SystemKey', $key)->first()
                ?? self::get()->filter(['Title' => $title, 'SystemKey' => ''])->first();
            if ($category) {
                if ((string)$category->SystemKey === '') {
                    $category->SystemKey = $key;
                    $category->Sort = $sort;
                    $category->write();
                }
                continue;
            }
            self::create([
                'Title' => $title,
                'SystemKey' => $key,
                'Sort' => $sort,
                'IsClosed' => false,
            ])->write();
        }

        $config->MashaFeedlyEstimateCategoriesSeeded = true;
        $config->write();
    }

    /** Legt die Standardkategorien beim Datenbankaufbau an. */
    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();
        self::ensureDefaultCategories();
        MashaFeedlyFolderService::ensureStructure();
    }

    /** Erstellt die bearbeitbaren Felder einer Kategorie. */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Entries', 'ClassName', 'SystemKey']);
        $fields->replaceField('Title', TextField::create('Title', i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_NAME', 'Kategoriename')));
        $fields->insertAfter('Title', DropdownField::create(
            'SystemKey',
            i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE', 'Funktion dieser Kategorie'),
            self::systemRoleOptions()
        )->setDescription(i18n::_t(
            'KW\\MashaFeedly\\Translations.CATEGORY_ROLE_DESCRIPTION',
            'Die Funktion bleibt auch dann erhalten, wenn Sie den Kategorienamen ändern.'
        )));
        $fields->replaceField(
            'IsClosed',
            CheckboxField::create('IsClosed', i18n::_t(
                'KW\\MashaFeedly\\Translations.CATEGORY_CLOSED',
                'Als abgeschlossen behandeln'
            ))->setDescription(i18n::_t(
                'KW\\MashaFeedly\\Translations.CATEGORY_CLOSED_DESCRIPTION',
                'Meldungen in dieser Kategorie werden nicht als Fehler-Markierung auf der Webseite angezeigt.'
            ))
        );
        return $fields;
    }

    /** Rollen, auf die das Workflow-Verhalten der Kategorie reagiert. */
    public static function systemRoleOptions(): array
    {
        return [
            '' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_CUSTOM', 'Normale Kategorie'),
            'backlog' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_BACKLOG', 'Startstatus (erforderlich)'),
            'todo' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_TODO', 'Zu erledigen'),
            'doing' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_DOING', 'In Bearbeitung'),
            'done' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_DONE', 'Erledigt (erforderlich)'),
            'feedback' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_FEEDBACK', 'Wartet auf Freigabe (erforderlich)'),
            'archive' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_ARCHIVE', 'Archiv'),
            'estimate_pending' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_ESTIMATE_PENDING', 'Kostenschätzung wartet auf Freigabe (optional)'),
            'estimate_approved' => i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_ROLE_ESTIMATE_APPROVED', 'Kostenschätzung freigegeben (optional)'),
        ];
    }

    /** Verschiebt eine Rolle auf die gewählte Kategorie und hält ihre Abschlusslogik synchron. */
    protected function onBeforeWrite(): void
    {
        $role = (string)$this->SystemKey;
        if ($role !== '') {
            $previousOwner = self::get()->filter('SystemKey', $role)->exclude('ID', (int)$this->ID)->first();
            if ($previousOwner) {
                $previousOwner->SystemKey = '';
                if (in_array($role, ['done', 'archive'], true)) {
                    $previousOwner->IsClosed = false;
                }
                $previousOwner->write(false, false, false, false, true);
            }
        }
        if (in_array($role, ['done', 'archive'], true)) {
            $this->IsClosed = true;
        } elseif (in_array($role, ['backlog', 'todo', 'doing', 'feedback', 'estimate_pending', 'estimate_approved'], true)) {
            $this->IsClosed = false;
        }
        parent::onBeforeWrite();
    }

    /** Hält die drei für Erstellung, Abschluss und Freigabe nötigen Rollen besetzt. */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        $role = (string)$this->SystemKey;
        if (!array_key_exists($role, self::systemRoleOptions())) {
            $result->addFieldError('SystemKey', 'Bitte wählen Sie eine gültige Kategoriefunktion.');
        }
        if ($this->isInDB()) {
            $stored = self::get()->byID((int)$this->ID);
            $oldRole = (string)($stored?->SystemKey ?? '');
            if (in_array($oldRole, ['backlog', 'done', 'feedback'], true) && $oldRole !== $role
                && !self::get()->filter('SystemKey', $oldRole)->exclude('ID', (int)$this->ID)->exists()
            ) {
                $result->addFieldError('SystemKey', 'Diese erforderliche Funktion muss einer Kategorie zugeordnet bleiben.');
            }
        }
        return $result;
    }

    /** Liefert lokalisierte Spaltenüberschriften für die Kategorienübersicht. */
    public function summaryFields()
    {
        $fields = parent::summaryFields();
        $fields['Title'] = i18n::_t('KW\\MashaFeedly\\Translations.FIELD_CATEGORY', 'Kategorie');
        $fields['Entries.Count'] = i18n::_t('KW\\MashaFeedly\\Translations.FIELD_ENTRIES', 'Meldungen');
        $fields['IsClosed'] = i18n::_t('KW\\MashaFeedly\\Translations.CATEGORY_CLOSED', 'Abgeschlossen');
        return $fields;
    }

    /** Schützt Pflichtrollen und Kategorien, denen noch Einträge zugeordnet sind. */
    public function canDelete($member = null): bool
    {
        return !in_array((string)$this->SystemKey, self::REQUIRED_SYSTEM_KEYS, true)
            && !$this->Entries()->exists()
            && parent::canDelete($member);
    }
}
