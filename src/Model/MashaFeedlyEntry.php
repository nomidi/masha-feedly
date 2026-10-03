<?php

namespace KW\MashaFeedly\Model;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;
use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\File;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\i18n\i18n;

/**
 * Datenobjekt für einen datierten Masha-Feedly-Eintrag.
 *
 * @property int $ID Datenbank-ID des Eintrags.
 * @property-read string $Title Automatisch aus dem Anfang der Bug-Beschreibung gebildeter Titel.
 * @property string $Content Inhalt des Eintrags.
 * @property string $EntryDate Datum und Uhrzeit des Eintrags.
 * @property int $CategoryID ID des Status.
 * @property int $PriorityID ID der Priorität.
 * @property MashaFeedlyCategory $Category Statuskategorie des Eintrags.
 * @property string $PageURL Seitenadresse des gemeldeten Bereichs.
 * @property string $ElementSelector CSS-Auswahlpfad des gemeldeten Bereichs.
 * @property string $ElementText Lesbarer Text aus dem gemeldeten Bereich.
 * @property string $OperatingSystem Betriebssystem einschließlich erkannter Version.
 * @property string $Browser Browser einschließlich erkannter Version.
 * @property string $UserAgent Vom Browser übermittelte Kennung.
 * @property string $Resolution Bildschirmauflösung zum Zeitpunkt der Meldung.
 * @property string $BrowserWindow Größe des Browserfensters zum Zeitpunkt der Meldung.
 * @property int $ColorDepth Farbtiefe des Bildschirms in Bit.
 * @method \SilverStripe\ORM\ManyManyList<Member> AssignedMembers Zugeordnete freigegebene Mitglieder.
 * @property int $Sort Sortierung innerhalb der Kategorie.
 * @property string $VisibilityLabel Sichtbarkeitsstatus für den aktuellen Benutzer.
 * @property bool $CanCurrentMemberView Gibt an, ob der aktuelle Benutzer den Eintrag sehen darf.
 * @property string $Created Erstellungszeitpunkt.
 * @property string $LastEdited Zeitpunkt der letzten Änderung.
 * @method \SilverStripe\ORM\DataList<MashaFeedlyComment> Comments() Zugehörige Kommentare.
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyEntry extends DataObject
{
    private static $table_name = 'MashaFeedlyEntry';

    private static $entry_timezone = 'Europe/Berlin';

    private static $db = [
        'Content' => 'HTMLText',
        'EntryDate' => 'Datetime',
        'Sort' => 'Int',
        'PageURL' => 'Varchar(2048)',
        'ElementSelector' => 'Varchar(512)',
        'ElementText' => 'Text',
        'OperatingSystem' => 'Varchar(255)',
        'Browser' => 'Varchar(255)',
        'UserAgent' => 'Varchar(512)',
        'Resolution' => 'Varchar(50)',
        'BrowserWindow' => 'Varchar(50)',
        'ColorDepth' => 'Int',
    ];

    private static $has_many = [
        'Comments' => MashaFeedlyComment::class,
        'History' => MashaFeedlyEntryHistory::class,
        'Attachments' => MashaFeedlyAttachment::class,
        'Relations' => MashaFeedlyEntryRelation::class . '.Entry',
    ];

    private static $has_one = [
        'Category' => MashaFeedlyCategory::class,
        'Priority' => MashaFeedlyPriority::class,
    ];

    private static $many_many = [
        'AssignedMembers' => Member::class,
    ];

    private static $summary_fields = [
        'Title' => 'Bug-Hinweis',
        'Category.Title' => 'Status',
        'EntryDate.Nice' => 'Datum',
        'Comments.Count' => 'Kommentare',
    ];

    private static $default_sort = 'Sort ASC, EntryDate DESC, Created DESC';

    private bool $notifyMembersAfterWrite = false;

    private bool $notifyMembersAfterUpdate = false;

    private ?string $historyOldCategoryTitle = null;

    private ?string $historyOldPriorityTitle = null;


    /**
     * Erstellt die im CMS bearbeitbaren Felder des Eintrags.
     *
     * @return FieldList CMS-Felder für Inhalt, Datum und Kommentare.
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Comments', 'ClassName', 'Title', 'Sort']);
        $fields->replaceField('Content', TextareaField::create('Content', $this->translate('FIELD_DESCRIPTION', 'Bug-Beschreibung')));
        $dateField = DatetimeField::create('EntryDate', $this->translate('FIELD_DATETIME', 'Datum und Uhrzeit'));
        if (!$this->isInDB() && !$this->EntryDate) {
            $dateField->setValue(self::currentEntryDateTime());
        }
        $fields->replaceField('EntryDate', $dateField);
        $fields->fieldByName('PageURL')?->setTitle($this->translate('FIELD_PAGE_URL', 'Seitenadresse'))->setReadonly(true);
        $fields->fieldByName('ElementSelector')?->setTitle($this->translate('FIELD_SELECTOR', 'Ausgewählter Bereich'))->setReadonly(true);
        $fields->fieldByName('ElementText')?->setTitle($this->translate('FIELD_ELEMENT_TEXT', 'Text im ausgewählten Bereich'))->setReadonly(true);
        $fields->fieldByName('OperatingSystem')?->setTitle($this->translate('FIELD_OPERATING_SYSTEM', 'Betriebssystem'))->setReadonly(true);
        $fields->fieldByName('Browser')?->setTitle($this->translate('FIELD_BROWSER', 'Browser'))->setReadonly(true);
        $fields->fieldByName('UserAgent')?->setTitle($this->translate('FIELD_USER_AGENT', 'Browserkennung'))->setReadonly(true);
        $fields->fieldByName('Resolution')?->setTitle($this->translate('FIELD_RESOLUTION', 'Bildschirmauflösung'))->setReadonly(true);
        $fields->fieldByName('BrowserWindow')?->setTitle($this->translate('FIELD_BROWSER_WINDOW', 'Browserfenster'))->setReadonly(true);
        $fields->fieldByName('ColorDepth')?->setTitle($this->translate('FIELD_COLOR_DEPTH', 'Farbtiefe (Bit)'))->setReadonly(true);
        if ($this->Attachments()->exists()) {
            $attachmentHTML = '<ul class="masha-feedly-admin-attachments">';
            foreach ($this->Attachments() as $attachment) {
                $file = $attachment->File();
                if ($file instanceof File && $file->exists()) {
                    $attachmentHTML .= '<li><a href="' . htmlspecialchars((string)$file->getURL(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                        . htmlspecialchars((string)$attachment->OriginalName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></li>';
                }
            }
            $attachmentHTML .= '</ul>';
            $fields->addFieldToTab('Root.Main', LiteralField::create('MashaFeedlyAttachments', $attachmentHTML));
        }
        MashaFeedlyCategory::ensureDefaultCategories();
        $categories = MashaFeedlyCategory::get()->sort('Sort ASC, Title ASC')->map('ID', 'Title')->toArray();
        $fields->replaceField(
            'CategoryID',
            DropdownField::create('CategoryID', $this->translate('FIELD_STATUS', 'Status'), $categories)
                ->setValue($this->CategoryID ?: MashaFeedlyCategory::defaultCategory()->ID)
        );
        MashaFeedlyPriority::ensureDefaultPriorities();
        $priorities = MashaFeedlyPriority::get()->sort('Sort ASC, Title ASC')->map('ID', 'Title')->toArray();
        $fields->replaceField('PriorityID', DropdownField::create(
            'PriorityID',
            $this->translate('FIELD_PRIORITY', 'Priorität'),
            $priorities
        )->setValue($this->PriorityID ?: MashaFeedlyPriority::defaultPriority()->ID));
        $authorizedMembers = MashaFeedlyConfigExtension::memberIDs();
        $memberOptions = [];
        if ($authorizedMembers) {
            foreach (Member::get()->filter('ID', $authorizedMembers)->sort('Surname ASC, FirstName ASC') as $authorizedMember) {
                $memberOptions[(string)$authorizedMember->ID] = (string)$authorizedMember->getName();
            }
        }
        $fields->replaceField(
            'AssignedMembers',
            ListboxField::create('AssignedMembers', $this->translate('FIELD_ASSIGNEES', 'Zugeordnet an'), $memberOptions)
                ->setValue($this->AssignedMembers()->column('ID'))
                ->setDescription($this->translate('FIELD_ASSIGNEES_DESCRIPTION', 'Mehrere freigegebene Benutzer können ausgewählt werden.'))
        );
        $fields->addFieldToTab('Root.Comments', GridField::create(
            'Comments',
            $this->translate('FIELD_COMMENTS', 'Kommentare'),
            $this->Comments(),
            GridFieldConfig_RelationEditor::create()
        ));
        $member = Security::getCurrentUser();
        if ($this->isInDB() && $member instanceof Member && MashaFeedlyConfigExtension::canUse($member)) {
            MashaFeedlyEntryRead::markAsSeen($this, $member);
            $unreadCount = MashaFeedlyEntryRead::unreadCount($member);
            $feedbackCount = \KW\MashaFeedly\Admin\MashaFeedlyAdmin::feedbackCount();
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'MashaFeedlyUnreadMarker',
                    '<span hidden data-masha-feedly-unread-count="'
                        . $unreadCount
                        . '" data-masha-feedly-feedback-count="'
                        . $feedbackCount . '"></span>'
                )
            );
        }
        return $fields;
    }

    /** Liefert lokalisierte Spaltenüberschriften für die Eintragsübersicht. */
    public function summaryFields()
    {
        $fields = parent::summaryFields();
        $fields['Title'] = $this->translate('FIELD_TITLE', 'Bug-Hinweis');
        $fields['Category.Title'] = $this->translate('FIELD_STATUS', 'Status');
        $fields['Priority.Title'] = $this->translate('FIELD_PRIORITY', 'Priorität');
        $fields['EntryDate.Nice'] = $this->translate('FIELD_DATE', 'Datum');
        $fields['Comments.Count'] = $this->translate('FIELD_COMMENTS', 'Kommentare');
        return $fields;
    }

    /** Ergänzt bestehende Einträge um den Standardstatus, falls noch keiner gesetzt ist. */
    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();
        $backlog = MashaFeedlyCategory::defaultCategory();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $defaultPriority = MashaFeedlyPriority::defaultPriority();
        foreach (self::get()->filter('CategoryID', 0) as $entry) {
            $entry->CategoryID = $backlog->ID;
            $entry->write();
        }
        foreach (self::get()->filter('PriorityID', 0) as $entry) {
            $entry->PriorityID = $defaultPriority->ID;
            $entry->write();
        }
    }

    /** Liefert einen kurzen automatisch gebildeten Titel aus dem Beschreibungstext. */
    public function getTitle(): string
    {
        $plainText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(
            strip_tags((string)$this->Content),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        )) ?? '');
        if (mb_strlen($plainText) <= 72) {
            return $plainText;
        }

        return rtrim(mb_substr($plainText, 0, 69)) . '…';
    }

    /** Liefert die ursprüngliche Autorin aus dem unveränderlichen Erstellungseintrag im Verlauf. */
    public function creatorMemberID(): int
    {
        $createdEvent = MashaFeedlyEntryHistory::get()
            ->filter(['EntryID' => (int)$this->ID, 'ChangeType' => 'created'])
            ->sort('Created ASC, ID ASC')
            ->first();

        return (int)($createdEvent?->ActorMemberID ?? 0);
    }

    /** Merkt sich, ob der Eintrag neu angelegt wird. */
    protected function onBeforeWrite(): void
    {
        $this->historyOldCategoryTitle = null;
        $this->historyOldPriorityTitle = null;
        if ($this->isInDB()) {
            $storedEntry = self::get()->byID((int)$this->ID);
            if ($storedEntry && (int)$storedEntry->CategoryID !== (int)$this->CategoryID) {
                $this->historyOldCategoryTitle = (string)$storedEntry->Category()->Title;
            }
            if ($storedEntry && (int)$storedEntry->PriorityID > 0 && (int)$storedEntry->PriorityID !== (int)$this->PriorityID) {
                $this->historyOldPriorityTitle = (string)$storedEntry->Priority()->Title;
            }
        }
        if (!$this->EntryDate) {
            $this->EntryDate = self::currentEntryDateTime();
        } elseif (!$this->isInDB() && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$this->EntryDate)) {
            $this->EntryDate .= ' ' . substr(self::currentEntryDateTime(), 11);
        }
        if (!$this->CategoryID) {
            $this->CategoryID = MashaFeedlyCategory::defaultCategory()->ID;
        }
        if (!$this->PriorityID) {
            $this->PriorityID = MashaFeedlyPriority::defaultPriority()->ID;
        }
        if (!$this->Sort && !$this->isInDB()) {
            $this->Sort = (int)self::get()->filter('CategoryID', $this->CategoryID)->max('Sort') + 10;
        }
        $this->notifyMembersAfterWrite = !$this->isInDB();
        parent::onBeforeWrite();
        $this->notifyMembersAfterUpdate = $this->isInDB() && (bool)$this->getChangedFields(
            ['Content', 'EntryDate', 'CategoryID', 'PriorityID'],
            DataObject::CHANGE_VALUE
        );
    }

    /** Verhindert die Zuweisung an Mitglieder ohne ausdrückliche Masha-Feedly-Freigabe. */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        $assignedMemberIDs = array_map('intval', $this->AssignedMembers()->column('ID'));
        $unauthorizedIDs = array_diff($assignedMemberIDs, MashaFeedlyConfigExtension::memberIDs());
        if ($unauthorizedIDs) {
            $result->addFieldError('AssignedMembers', $this->translate('ALL_ASSIGNEES_ALLOWED', 'Alle zugeordneten Benutzer müssen für Masha:Feedly freigegeben sein.'));
        }
        return $result;
    }

    /** Liefert den aktuellen Zeitpunkt in der für Einträge konfigurierten Zeitzone. */
    private static function currentEntryDateTime(): string
    {
        $timezone = (string)(static::config()->get('entry_timezone') ?: 'Europe/Berlin');
        $timestamp = DBDatetime::now()->getTimestamp();
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone($timezone))
            ->format('Y-m-d H:i:s');
    }

    /** Versendet nach dem erstmaligen Speichern die konfigurierten Benachrichtigungen. */
    protected function onAfterWrite(): void
    {
        parent::onAfterWrite();
        if ($this->historyOldCategoryTitle !== null) {
            $newCategoryTitle = (string)$this->Category()->Title;
            if ($this->historyOldCategoryTitle !== $newCategoryTitle) {
                MashaFeedlyEntryHistory::record(
                    $this,
                    'status',
                    $this->historyOldCategoryTitle,
                    $newCategoryTitle,
                    Security::getCurrentUser()
                );
            }
            $this->historyOldCategoryTitle = null;
        }
        if ($this->historyOldPriorityTitle !== null) {
            $newPriorityTitle = (string)$this->Priority()->Title;
            if ($this->historyOldPriorityTitle !== $newPriorityTitle) {
                MashaFeedlyEntryHistory::record(
                    $this,
                    'priority',
                    $this->historyOldPriorityTitle,
                    $newPriorityTitle,
                    Security::getCurrentUser()
                );
            }
            $this->historyOldPriorityTitle = null;
        }
        if ($this->notifyMembersAfterWrite) {
            MashaFeedlyEntryHistory::record($this, 'created', '', $this->getTitle(), Security::getCurrentUser());
            MashaFeedlyNotificationService::notifyNewEntry($this);
        } elseif ($this->notifyMembersAfterUpdate) {
            MashaFeedlyNotificationService::notifyUpdatedEntry($this);
        }
        $member = Security::getCurrentUser();
        if ($member instanceof Member && MashaFeedlyConfigExtension::canUse($member)) {
            MashaFeedlyEntryRead::markAsSeen($this, $member);
        }
    }

    /**
     * Prüft, ob das angegebene Mitglied diesen Eintrag sehen darf.
     *
     * Masha-Feedly-Administratoren und die im Konfigurationstab freigegebenen
     * Silverstripe-Mitglieder dürfen Einträge sehen.
     *
     * @param Member|null $member Zu prüfendes Mitglied; ohne Wert wird der aktuelle Benutzer verwendet.
     * @return bool Gibt zurück, ob der Eintrag für dieses Mitglied sichtbar ist.
     */
    public function canView($member = null): bool
    {
        $member ??= Security::getCurrentUser();
        return $member instanceof Member && MashaFeedlyConfigExtension::canUse($member);
    }

    /**
     * Liefert den Sichtbarkeitstext für den aktuellen Frontend-Benutzer.
     *
     * @return string „Sichtbar“ oder „Nicht sichtbar“ für den aktuellen Benutzer.
     */
    public function getVisibilityLabel(): string
    {
        return $this->getVisibilityLabelFor(Security::getCurrentUser());
    }

    /**
     * Liefert den Sichtbarkeitstext für ein bestimmtes Mitglied.
     *
     * @param Member|null $member Zu prüfendes Mitglied.
     * @return string „Sichtbar“ oder „Nicht sichtbar“ für dieses Mitglied.
     */
    public function getVisibilityLabelFor(?Member $member): string
    {
        return $this->canView($member)
            ? $this->translate('VISIBILITY_VISIBLE', 'Sichtbar')
            : $this->translate('VISIBILITY_HIDDEN', 'Nicht sichtbar');
    }

    /**
     * Liefert, ob der aktuelle Frontend-Benutzer den Eintrag sehen darf.
     *
     * @return bool Gibt zurück, ob Beschreibung und Bug-Hinweis ausgegeben werden dürfen.
     */
    public function getCanCurrentMemberView(): bool
    {
        return $this->canView();
    }

    /** Liefert einen Modelltext aus dem Silverstripe-Sprachkatalog. */
    private function translate(string $key, string $fallback): string
    {
        return i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $fallback);
    }
}
