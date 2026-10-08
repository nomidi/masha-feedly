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
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\DatetimeField;
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
 * @property string $StepsToReproduce Optionale Schritte zum Nachstellen eines Fehlers.
 * @property string $ExpectedResult Optionales erwartetes Ergebnis.
 * @property string $ActualResult Optionales tatsächliches Ergebnis.
 * @property string $EntryDate Datum und Uhrzeit des Eintrags.
 * @property string $DueDate Fälligkeitstermin des Eintrags.
 * @property string $DueDateReminderSentAt Zeitpunkt der letzten Fälligkeitserinnerung.
 * @property string $EstimatedCostAmount Freigegebene Kostenschätzung als Dezimalbetrag.
 * @property string $EstimatedCostAmountMax Oberer berechneter Kostenschätzungsbetrag.
 * @property string $EstimatedCostDuration Geschätzte Dauer.
 * @property string $EstimatedCostCurrency Währung der Kostenschätzung.
 * @property string $EstimatedCostNote Erläuterung zur Kostenschätzung.
 * @property int $CategoryID ID des Status.
 * @property int $PriorityID ID der Priorität.
 * @property MashaFeedlyCategory $Category Statuskategorie des Eintrags.
 * @property string $PageURL Seitenadresse des gemeldeten Bereichs.
 * @property string $ElementSelector CSS-Auswahlpfad des gemeldeten Bereichs.
 * @property string $ElementText Lesbarer Text aus dem gemeldeten Bereich.
 * @property string $ElementPositionX Horizontale Klickposition relativ zum ausgewählten Element.
 * @property string $ElementPositionY Vertikale Klickposition relativ zum ausgewählten Element.
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

    /** E-Mail-Adressen der Betreiberkonten, die die angezeigte Meldeperson ändern dürfen. */
    private static $reporter_manager_emails = [];

    private static $db = [
        'Content' => 'HTMLText',
        'StepsToReproduce' => 'Text',
        'ExpectedResult' => 'Text',
        'ActualResult' => 'Text',
        'EntryDate' => 'Datetime',
        'DueDate' => 'Date',
        'DueDateReminderSentAt' => 'Datetime',
        'EstimatedCostAmount' => 'Decimal(12,2)',
        'EstimatedCostAmountMax' => 'Decimal(12,2)',
        'EstimatedCostDuration' => 'Varchar(80)',
        'EstimatedCostCurrency' => 'Varchar(3)',
        'EstimatedCostNote' => 'Text',
        'Sort' => 'Int',
        'PageURL' => 'Varchar(2048)',
        'ElementSelector' => 'Varchar(512)',
        'ElementText' => 'Text',
        'ElementPositionX' => 'Varchar(16)',
        'ElementPositionY' => 'Varchar(16)',
        'OperatingSystem' => 'Varchar(255)',
        'Browser' => 'Varchar(255)',
        'UserAgent' => 'Varchar(512)',
        'Resolution' => 'Varchar(50)',
        'BrowserWindow' => 'Varchar(50)',
        'ColorDepth' => 'Int',
    ];

    private static $defaults = [
        'EstimatedCostCurrency' => 'EUR',
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
        'ReportedBy' => Member::class,
    ];

    private static $many_many = [
        'AssignedMembers' => Member::class,
    ];

    private static $summary_fields = [
        'Title' => 'Meldung',
        'Category.Title' => 'Status',
        'EntryDate.Nice' => 'Datum',
        'DueDate.Nice' => 'Fällig am',
        'Comments.Count' => 'Kommentare',
    ];

    private static $default_sort = 'Sort ASC, EntryDate DESC, Created DESC';

    private bool $notifyMembersAfterWrite = false;

    private bool $deferNewEntryNotification = false;

    private bool $notifyMembersAfterUpdate = false;

    private ?string $historyOldCategoryTitle = null;

    private ?string $historyOldPriorityTitle = null;

    private ?string $historyOldDueDate = null;

    private bool $historyDueDateChanged = false;

    private ?string $historyOldReporter = null;

    private ?string $historyNewReporter = null;

    private ?string $historyOldEstimate = null;

    private ?string $historyNewEstimate = null;

    private bool $notifyCostEstimateRequestedAfterWrite = false;

    /** Verschiebt die Erstellungsbenachrichtigung, bis der Controller Zuständigkeiten gespeichert hat. */
    public function deferNewEntryNotification(): void
    {
        $this->deferNewEntryNotification = true;
    }


    /**
     * Erstellt die im CMS bearbeitbaren Felder des Eintrags.
     *
     * @return FieldList CMS-Felder für Inhalt, Datum und Kommentare.
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName([
            'Comments', 'ClassName', 'Title', 'Sort', 'ReportedByID',
            'EstimatedCostAmount', 'EstimatedCostAmountMax', 'EstimatedCostDuration', 'EstimatedCostCurrency', 'EstimatedCostNote',
        ]);
        $fields->replaceField('Content', TextareaField::create('Content', $this->translate('FIELD_DESCRIPTION', 'Beschreibung')));
        $fields->replaceField('StepsToReproduce', TextareaField::create('StepsToReproduce', $this->translate('DIAGNOSTICS_STEPS', 'Schritte zum Nachstellen'))->setRows(5));
        $fields->replaceField('ExpectedResult', TextareaField::create('ExpectedResult', $this->translate('DIAGNOSTICS_EXPECTED', 'Erwartetes Ergebnis'))->setRows(3));
        $fields->replaceField('ActualResult', TextareaField::create('ActualResult', $this->translate('DIAGNOSTICS_ACTUAL', 'Tatsächliches Ergebnis'))->setRows(3));
        $dateField = DatetimeField::create('EntryDate', $this->translate('FIELD_DATETIME', 'Datum und Uhrzeit'));
        if (!$this->isInDB() && !$this->EntryDate) {
            $dateField->setValue(self::currentEntryDateTime());
        }
        $fields->replaceField('EntryDate', $dateField);
        $fields->replaceField('DueDate', \SilverStripe\Forms\DateField::create(
            'DueDate',
            $this->translate('FIELD_DUE_DATE', 'Fällig am')
        ));
        $member = Security::getCurrentUser();
        if (self::canManageReporter($member)) {
            $reporterOptions = ['0' => $this->translate('REPORTER_USE_CREATOR', 'Technischen Ersteller verwenden')];
            foreach (Member::get()->sort('Surname ASC, FirstName ASC') as $reporter) {
                $reporterOptions[(string)$reporter->ID] = (string)$reporter->getName();
            }
            $fields->addFieldToTab('Root.Main', DropdownField::create(
                'ReportedByID',
                $this->translate('FIELD_REPORTED_BY', 'Gemeldet von'),
                $reporterOptions
            )->setValue((int)$this->ReportedByID)
                ->setDescription($this->translate('FIELD_REPORTED_BY_DESCRIPTION', 'Ändert nur die angezeigte Meldeperson. Der technische Ersteller bleibt im Verlauf erhalten.')));
        }
        if (self::canManageEstimate($member) && (string)$this->Category()->SystemKey === 'estimate_pending') {
            $fields->addFieldsToTab('Root.Main', [
                \SilverStripe\Forms\TextField::create('EstimatedCostDuration', $this->translate('ESTIMATE_DURATION', 'Geschätzte Dauer')),
                LiteralField::create('EstimatedCostCalculatedPrice', '<p>' . htmlspecialchars($this->estimatePriceLabel(), ENT_QUOTES, 'UTF-8') . '</p>'),
                TextareaField::create('EstimatedCostNote', $this->translate('ESTIMATE_NOTE', 'Erläuterung'))
                    ->setRows(3),
            ]);
        }
        $fields->fieldByName('PageURL')?->setTitle($this->translate('FIELD_PAGE_URL', 'Seitenadresse'))->setReadonly(true);
        $fields->fieldByName('ElementSelector')?->setTitle($this->translate('FIELD_SELECTOR', 'Ausgewählter Bereich'))->setReadonly(true);
        $fields->fieldByName('ElementText')?->setTitle($this->translate('FIELD_ELEMENT_TEXT', 'Text im ausgewählten Bereich'))->setReadonly(true);
        $fields->fieldByName('ElementPositionX')?->setTitle($this->translate('FIELD_ELEMENT_POSITION_X', 'Klickstelle X (relativ)'))->setReadonly(true);
        $fields->fieldByName('ElementPositionY')?->setTitle($this->translate('FIELD_ELEMENT_POSITION_Y', 'Klickstelle Y (relativ)'))->setReadonly(true);
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
        $canManageEstimate = self::canManageEstimate($member);
        $categories = [];
        foreach (MashaFeedlyCategory::get()->sort('Sort ASC, Title ASC') as $category) {
            $estimateRole = in_array((string)$category->SystemKey, ['estimate_pending', 'estimate_approved'], true);
            if ($estimateRole && !$canManageEstimate && (int)$category->ID !== (int)$this->CategoryID) {
                continue;
            }
            $categories[(string)$category->ID] = $estimateRole && !$canManageEstimate
                ? $this->translate('ESTIMATE_HIDDEN_CATEGORY', 'In Bearbeitung')
                : (string)$category->Title;
        }
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
        $fields['Title'] = $this->translate('FIELD_TITLE', 'Meldung');
        $fields['Category.Title'] = $this->translate('FIELD_STATUS', 'Status');
        $fields['Priority.Title'] = $this->translate('FIELD_PRIORITY', 'Priorität');
        $fields['EntryDate.Nice'] = $this->translate('FIELD_DATE', 'Datum');
        $fields['DueDate.Nice'] = $this->translate('FIELD_DUE_DATE', 'Fällig am');
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

    /** Prüft die explizite Betreiberfreigabe; CMS-ADMIN-Rechte allein reichen absichtlich nicht. */
    public static function canManageReporter($member = null): bool
    {
        $member ??= Security::getCurrentUser();
        if (!$member instanceof Member || !$member->Email || !Permission::checkMember($member, 'ADMIN')) {
            return false;
        }
        $emails = static::config()->get('reporter_manager_emails');
        if (!is_array($emails)) {
            return false;
        }
        $emails = array_map(static fn($email): string => mb_strtolower(trim((string)$email)), $emails);
        return in_array(mb_strtolower(trim((string)$member->Email)), $emails, true);
    }

    /** Nur der konfigurierte Betreiber darf Kostenschätzungen erfassen oder bearbeiten. */
    public static function canManageEstimate($member = null): bool
    {
        $member ??= Security::getCurrentUser();
        return $member instanceof Member
            && MashaFeedlyConfigExtension::canUse($member)
            && self::canManageReporter($member);
    }

    /** Freigegebene Mitglieder dürfen vorhandene Kostenschätzungen ansehen und freigeben. */
    public static function canApproveEstimate($member = null): bool
    {
        $member ??= Security::getCurrentUser();
        return $member instanceof Member
            && MashaFeedlyConfigExtension::canUse($member)
            && (self::canManageEstimate($member) || (bool)$member->MashaFeedlyCanManageEstimates);
    }

    /** Formatiert eine Schätzung für den unveränderlichen Verlauf. */
    private function estimateHistoryLabel(): string
    {
        $duration = trim((string)$this->EstimatedCostDuration);
        if ($duration === '') {
            return 'Keine Kostenschätzung';
        }
        $note = trim((string)$this->EstimatedCostNote);
        return $duration . ' · ' . $this->estimatePriceLabel() . ($note !== '' ? ' · ' . $note : '');
    }

    /** Parst Dauern wie „10 Minuten“, „2 Stunden“ oder „2–4 Stunden“ und berechnet den Preis. */
    public static function calculateEstimate(string $duration, float $hourlyRate): ?array
    {
        $duration = trim($duration);
        $number = '[0-9]+(?:[.,][0-9]+)?';
        $pattern = '/^\s*(' . $number . ')\s*(?:(?:-|–|bis)\s*(' . $number . ')\s*)?(stunden?|std\\.?|h|minuten?|min)\s*$/iu';
        if (!preg_match($pattern, $duration, $matches)) {
            return null;
        }
        $first = (float)str_replace(',', '.', $matches[1]);
        $last = isset($matches[2]) && $matches[2] !== '' ? (float)str_replace(',', '.', $matches[2]) : $first;
        $unit = mb_strtolower($matches[3]);
        $factor = in_array($unit, ['minute', 'minuten', 'min'], true) ? 1 / 60 : 1;
        $minimumHours = $first * $factor;
        $maximumHours = $last * $factor;
        if ($first <= 0 || $last < $first || $maximumHours > 10000 || $hourlyRate < 0) {
            return null;
        }
        return [
            'minimum' => number_format(round($minimumHours * $hourlyRate, 2), 2, '.', ''),
            'maximum' => number_format(round($maximumHours * $hourlyRate, 2), 2, '.', ''),
        ];
    }

    /** Formatiert den berechneten Preis in Euro. */
    public function estimatePriceLabel(): string
    {
        $minimum = number_format((float)$this->EstimatedCostAmount, 2, ',', '.') . ' €';
        $maximum = number_format((float)$this->EstimatedCostAmountMax, 2, ',', '.') . ' €';
        return $minimum === $maximum ? $minimum : $minimum . ' – ' . $maximum;
    }

    /** Liefert die ausgewählte Meldeperson oder fällt auf den tatsächlichen Ersteller zurück. */
    public function reportedByMember(): ?Member
    {
        if ((int)$this->ReportedByID > 0) {
            $reporter = Member::get()->byID((int)$this->ReportedByID);
            if ($reporter instanceof Member) {
                return $reporter;
            }
        }
        $creatorID = $this->creatorMemberID();
        $creator = $creatorID > 0 ? Member::get()->byID($creatorID) : null;
        return $creator instanceof Member ? $creator : null;
    }

    /** Name für die Anzeige, mit historischem Fallback falls das Konto gelöscht wurde. */
    public function reportedByName(): string
    {
        $reporter = $this->reportedByMember();
        if ($reporter instanceof Member) {
            return (string)$reporter->getName();
        }
        $createdEvent = MashaFeedlyEntryHistory::get()
            ->filter(['EntryID' => (int)$this->ID, 'ChangeType' => 'created'])
            ->sort('Created ASC, ID ASC')
            ->first();
        return (string)($createdEvent?->ActorName ?: '');
    }

    private function resolveReporterName(int $memberID): string
    {
        if ($memberID > 0) {
            $reporter = Member::get()->byID($memberID);
            if ($reporter instanceof Member) {
                return (string)$reporter->getName();
            }
        }
        $creatorID = $this->creatorMemberID();
        $creator = $creatorID > 0 ? Member::get()->byID($creatorID) : null;
        if ($creator instanceof Member) {
            return (string)$creator->getName();
        }
        $createdEvent = MashaFeedlyEntryHistory::get()
            ->filter(['EntryID' => (int)$this->ID, 'ChangeType' => 'created'])
            ->sort('Created ASC, ID ASC')
            ->first();
        return (string)($createdEvent?->ActorName ?: '');
    }

    /** Merkt sich, ob der Eintrag neu angelegt wird. */
    protected function onBeforeWrite(): void
    {
        $this->historyOldCategoryTitle = null;
        $this->historyOldPriorityTitle = null;
        $this->historyOldDueDate = null;
        $this->historyDueDateChanged = false;
        $this->historyOldReporter = null;
        $this->historyNewReporter = null;
        $this->historyOldEstimate = null;
        $this->historyNewEstimate = null;
        $this->notifyCostEstimateRequestedAfterWrite = false;
        if ($this->isInDB()) {
            $storedEntry = self::get()->byID((int)$this->ID);
            if ($storedEntry) {
                $oldRole = (string)$storedEntry->Category()->SystemKey;
                $newCategory = MashaFeedlyCategory::get()->byID((int)$this->CategoryID);
                $newRole = (string)($newCategory?->SystemKey ?? '');
                $invalidEstimateTransition = ($newRole === 'estimate_pending'
                        && !self::canManageEstimate()
                        && !($oldRole === 'estimate_pending' && (int)$storedEntry->CategoryID === (int)$this->CategoryID))
                    || ($newRole === 'estimate_approved'
                        && !self::canApproveEstimate()
                        && !($oldRole === 'estimate_approved' && (int)$storedEntry->CategoryID === (int)$this->CategoryID))
                    || ($oldRole === 'estimate_pending'
                        && !in_array($newRole, ['estimate_pending', 'estimate_approved'], true))
                    || ($newRole === 'estimate_approved'
                        && $oldRole !== 'estimate_pending'
                        && !($oldRole === 'estimate_approved' && (int)$storedEntry->CategoryID === (int)$this->CategoryID));
                if ($invalidEstimateTransition) {
                    $this->CategoryID = (int)$storedEntry->CategoryID;
                }
                $this->notifyCostEstimateRequestedAfterWrite = $oldRole !== 'estimate_pending'
                    && $newRole === 'estimate_pending'
                    && self::canManageEstimate();
                if (self::canManageEstimate() && (string)($newCategory?->SystemKey ?? '') === 'estimate_pending') {
                    $calculated = self::calculateEstimate((string)$this->EstimatedCostDuration, MashaFeedlyConfigExtension::hourlyRate());
                    if ($calculated) {
                        $this->EstimatedCostAmount = $calculated['minimum'];
                        $this->EstimatedCostAmountMax = $calculated['maximum'];
                        $this->EstimatedCostCurrency = 'EUR';
                    }
                }
            }
            if ($storedEntry && (
                (string)$storedEntry->EstimatedCostDuration !== (string)$this->EstimatedCostDuration
                ||
                (string)$storedEntry->EstimatedCostAmount !== (string)$this->EstimatedCostAmount
                || (string)$storedEntry->EstimatedCostAmountMax !== (string)$this->EstimatedCostAmountMax
                || (string)$storedEntry->EstimatedCostCurrency !== (string)$this->EstimatedCostCurrency
                || (string)$storedEntry->EstimatedCostNote !== (string)$this->EstimatedCostNote
            )) {
                $targetCategory = MashaFeedlyCategory::get()->byID((int)$this->CategoryID);
                if (!self::canManageEstimate() || (string)($targetCategory?->SystemKey ?? '') !== 'estimate_pending') {
                    $this->EstimatedCostAmount = $storedEntry->EstimatedCostAmount;
                    $this->EstimatedCostAmountMax = $storedEntry->EstimatedCostAmountMax;
                    $this->EstimatedCostDuration = $storedEntry->EstimatedCostDuration;
                    $this->EstimatedCostCurrency = $storedEntry->EstimatedCostCurrency;
                    $this->EstimatedCostNote = $storedEntry->EstimatedCostNote;
                } else {
                    $this->historyOldEstimate = $storedEntry->estimateHistoryLabel();
                    $this->historyNewEstimate = $this->estimateHistoryLabel();
                }
            }
            if ($storedEntry && (int)$storedEntry->ReportedByID !== (int)$this->ReportedByID) {
                if (!self::canManageReporter()) {
                    $this->ReportedByID = (int)$storedEntry->ReportedByID;
                } else {
                    $oldReporter = $storedEntry->reportedByName();
                    $newReporter = $this->resolveReporterName((int)$this->ReportedByID);
                    if ((int)$storedEntry->ReportedByID !== (int)$this->ReportedByID || $oldReporter !== $newReporter) {
                        $this->historyOldReporter = $oldReporter ?: 'Unbekannt';
                        $this->historyNewReporter = $newReporter ?: 'Unbekannt';
                    }
                }
            }
            if ($storedEntry && (int)$storedEntry->CategoryID !== (int)$this->CategoryID) {
                $this->historyOldCategoryTitle = (string)$storedEntry->Category()->Title;
            }
            if ($storedEntry && (int)$storedEntry->PriorityID > 0 && (int)$storedEntry->PriorityID !== (int)$this->PriorityID) {
                $this->historyOldPriorityTitle = (string)$storedEntry->Priority()->Title;
            }
            if ($storedEntry && (string)$storedEntry->DueDate !== (string)$this->DueDate) {
                $this->historyOldDueDate = (string)$storedEntry->DueDate;
                $this->historyDueDateChanged = true;
                $this->DueDateReminderSentAt = null;
            }
        } else {
            if ((int)$this->ReportedByID > 0 && !self::canManageReporter()) {
                $this->ReportedByID = 0;
            }
            if (!self::canManageEstimate()) {
                $requestedCategory = MashaFeedlyCategory::get()->byID((int)$this->CategoryID);
                if (in_array((string)($requestedCategory?->SystemKey ?? ''), ['estimate_pending', 'estimate_approved'], true)) {
                    $this->CategoryID = (int)MashaFeedlyCategory::defaultCategory()->ID;
                }
                $this->EstimatedCostAmount = null;
                $this->EstimatedCostAmountMax = null;
                $this->EstimatedCostDuration = '';
                $this->EstimatedCostCurrency = 'EUR';
                $this->EstimatedCostNote = '';
            }
        }
        $targetCategory = MashaFeedlyCategory::get()->byID((int)$this->CategoryID);
        if (self::canManageEstimate() && (string)($targetCategory?->SystemKey ?? '') === 'estimate_pending') {
            $calculated = self::calculateEstimate((string)$this->EstimatedCostDuration, MashaFeedlyConfigExtension::hourlyRate());
            if ($calculated) {
                $this->EstimatedCostAmount = $calculated['minimum'];
                $this->EstimatedCostAmountMax = $calculated['maximum'];
                $this->EstimatedCostCurrency = 'EUR';
            }
        }
        if (trim((string)$this->EstimatedCostCurrency) === '') {
            $this->EstimatedCostCurrency = 'EUR';
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
            ['Content', 'EntryDate', 'DueDate', 'CategoryID', 'PriorityID'],
            DataObject::CHANGE_VALUE
        );
    }

    /** Verhindert die Zuweisung an Mitglieder ohne ausdrückliche Masha-Feedly-Freigabe. */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        $newCategory = MashaFeedlyCategory::get()->byID((int)$this->CategoryID);
        $newRole = (string)($newCategory?->SystemKey ?? '');
        $oldRole = '';
        if ($this->isInDB()) {
            $storedEntry = self::get()->byID((int)$this->ID);
            $oldRole = (string)($storedEntry?->Category()->SystemKey ?? '');
            if ($oldRole === 'estimate_pending' && !in_array($newRole, ['estimate_pending', 'estimate_approved'], true)) {
                $result->addFieldError('CategoryID', $this->translate(
                    'ESTIMATE_APPROVAL_REQUIRED',
                    'Diese Meldung wartet auf die Freigabe der Kostenschätzung. Wähle den Status „Kostenschätzung freigegeben“, wenn du die Schätzung geprüft hast.'
                ));
            }
            if ($newRole === 'estimate_approved'
                && $oldRole !== 'estimate_pending'
                && !($oldRole === 'estimate_approved' && (int)$storedEntry->CategoryID === (int)$this->CategoryID)) {
                $result->addFieldError('CategoryID', $this->translate(
                    'ESTIMATE_APPROVAL_REQUIRED',
                    'Nur eine freigegebene Person kann eine ausstehende Kostenschätzung freigeben.'
                ));
            }
            if ($oldRole === 'estimate_pending' && $newRole === 'estimate_approved' && !self::canApproveEstimate()) {
                $result->addFieldError('CategoryID', $this->translate(
                    'ESTIMATE_APPROVAL_REQUIRED',
                    'Nur eine freigegebene Person kann eine ausstehende Kostenschätzung freigeben.'
                ));
            }
        }
        if ($this->isInDB() && $newRole === 'estimate_pending' && !self::canManageEstimate() && $oldRole !== 'estimate_pending') {
            $result->addFieldError('CategoryID', $this->translate('ESTIMATE_FORBIDDEN', 'Nur die zuständige Ansprechperson kann eine Kostenschätzung anfordern.'));
        }
        if ($newRole === 'estimate_pending' && MashaFeedlyConfigExtension::hourlyRate() <= 0) {
            $result->addFieldError('EstimatedCostDuration', $this->translate('ESTIMATE_RATE_REQUIRED', 'Der Stundensatz muss zuerst in den Masha:Feedly-Einstellungen hinterlegt werden.'));
        } elseif ($newRole === 'estimate_pending' && self::calculateEstimate((string)$this->EstimatedCostDuration, MashaFeedlyConfigExtension::hourlyRate()) === null) {
            $result->addFieldError('EstimatedCostDuration', $this->translate(
                'ESTIMATE_AMOUNT_REQUIRED',
                'Gib zuerst eine gültige geschätzte Dauer ein, bevor du die Freigabe anforderst.'
            ));
        }
        $assignedMemberIDs = array_map('intval', $this->AssignedMembers()->column('ID'));
        $unauthorizedIDs = array_diff($assignedMemberIDs, MashaFeedlyConfigExtension::memberIDs());
        if ($unauthorizedIDs) {
            $result->addFieldError('AssignedMembers', $this->translate('ALL_ASSIGNEES_ALLOWED', 'Alle zugeordneten Benutzer müssen für Masha:Feedly freigegeben sein.'));
        }
        if ((int)$this->ReportedByID > 0 && !Member::get()->byID((int)$this->ReportedByID)) {
            $result->addFieldError('ReportedByID', $this->translate('REPORTER_MUST_EXIST', 'Die Meldeperson muss ein vorhandenes Benutzerkonto sein.'));
        }
        if (trim((string)$this->EstimatedCostAmount) !== '' && (!is_numeric($this->EstimatedCostAmount) || (float)$this->EstimatedCostAmount < 0)) {
            $result->addFieldError('EstimatedCostAmount', $this->translate('ESTIMATE_INVALID_AMOUNT', 'Bitte gib einen gültigen positiven Betrag ein.'));
        }
        if (!in_array((string)$this->EstimatedCostCurrency, ['EUR', 'CHF', 'GBP', 'USD'], true)) {
            $result->addFieldError('EstimatedCostCurrency', $this->translate('ESTIMATE_INVALID_CURRENCY', 'Bitte wähle eine unterstützte Währung.'));
        }
        return $result;
    }

    /** Liefert den aktuellen Zeitpunkt in der für Einträge konfigurierten Zeitzone. */
    public static function currentEntryDateTime(): string
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
        if ($this->historyDueDateChanged) {
            $newDueDate = (string)$this->DueDate;
            if ($this->historyOldDueDate !== $newDueDate) {
                MashaFeedlyEntryHistory::record(
                    $this,
                    'due_date',
                    $this->historyOldDueDate,
                    $newDueDate,
                    Security::getCurrentUser()
                );
            }
            $this->historyOldDueDate = null;
            $this->historyDueDateChanged = false;
        }
        if ($this->historyOldReporter !== null && $this->historyNewReporter !== null) {
            MashaFeedlyEntryHistory::record(
                $this,
                'reported_by',
                $this->historyOldReporter,
                $this->historyNewReporter,
                Security::getCurrentUser()
            );
            $this->historyOldReporter = null;
            $this->historyNewReporter = null;
        }
        if ($this->historyOldEstimate !== null && $this->historyNewEstimate !== null) {
            MashaFeedlyEntryHistory::record(
                $this,
                'estimate',
                $this->historyOldEstimate,
                $this->historyNewEstimate,
                Security::getCurrentUser()
            );
            $this->historyOldEstimate = null;
            $this->historyNewEstimate = null;
        }
        if ($this->notifyMembersAfterWrite) {
            MashaFeedlyEntryHistory::record($this, 'created', '', $this->getTitle(), Security::getCurrentUser());
            if (!$this->deferNewEntryNotification) {
                MashaFeedlyNotificationService::notifyNewEntry($this);
            }
            $this->deferNewEntryNotification = false;
        } elseif ($this->notifyMembersAfterUpdate) {
            MashaFeedlyNotificationService::notifyUpdatedEntry($this);
        }
        if ($this->notifyCostEstimateRequestedAfterWrite) {
            MashaFeedlyNotificationService::notifyCostEstimateRequested($this);
            $this->notifyCostEstimateRequestedAfterWrite = false;
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
        return $member instanceof Member && (
            MashaFeedlyConfigExtension::canUse($member)
            || self::canManageReporter($member)
        );
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
