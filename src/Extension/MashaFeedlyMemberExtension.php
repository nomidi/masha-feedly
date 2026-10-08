<?php

namespace KW\MashaFeedly\Extension;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Security\Security;
use SilverStripe\Security\Member;
use SilverStripe\Security\InheritedPermissions;
use KW\MashaFeedly\Service\MashaFeedlyFolderService;
use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Forms\MashaFeedlyInteractiveLiteralField;
use SilverStripe\i18n\i18n;
use SilverStripe\View\Requirements;

/**
 * Ergänzt Mitglieder um Profilbild, Avatarfarbe und E-Mail-Einstellungen für Masha Feedly.
 *
 * @property bool $MashaFeedlyEmailNotifications Grundsätzliche Zustimmung zu Masha-Feedly-E-Mails.
 * @property bool $MashaFeedlyNotifyNewEntries Benachrichtigung bei neuen Einträgen.
 * @property bool $MashaFeedlyNotifyEntryUpdates Benachrichtigung bei Änderungen an Einträgen.
 * @property bool $MashaFeedlyNotifyOwnEntryChanges Benachrichtigung bei eigenen Einträgen und Änderungen.
 * @property bool $MashaFeedlyNotifyComments Benachrichtigung bei neuen Kommentaren.
 * @property bool $MashaFeedlyNotifyDueDateReminders Benachrichtigung bei Fälligkeitsterminen.
 * @property bool $MashaFeedlyNotifyCostEstimates Benachrichtigung bei angefragten Kostenschätzungen.
 * @property int $MashaFeedlyIconImageID ID des geschützten Profilbildes für Masha Feedly.
 * @property Image $MashaFeedlyIconImage Geschütztes Masha-Feedly-Profilbild.
 * @property string $MashaFeedlyAvatarIcon Kennung eines ausgewählten Anbieter-Icons.
 * @property string $MashaFeedlyColor Individuelle Avatarfarbe im Masha-Feedly-Board.
 * @property bool $MashaFeedlyDisableSoundEffects Animationen ohne Ton abspielen.
 * @property string $MashaFeedlyTheme Persönliches Masha-Feedly-Theme oder leere Website-Vorgabe.
 * @property string $MashaFeedlyAddress Persönliche Anrede (du/sie) oder leere Website-Vorgabe.
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyMemberExtension extends Extension
{
    private static $db = [
        'MashaFeedlyEmailNotifications' => 'Boolean',
        'MashaFeedlyNotifyNewEntries' => 'Boolean',
        'MashaFeedlyNotifyEntryUpdates' => 'Boolean',
        'MashaFeedlyNotifyOwnEntryChanges' => 'Boolean',
        'MashaFeedlyNotifyComments' => 'Boolean',
        'MashaFeedlyNotifyDueDateReminders' => 'Boolean',
        'MashaFeedlyNotifyCostEstimates' => 'Boolean',
        'MashaFeedlyCanManageEstimates' => 'Boolean',
        'MashaFeedlyColor' => 'Varchar(7)',
        'MashaFeedlyAvatarIcon' => 'Varchar(50)',
        'MashaFeedlyTheme' => 'Varchar(80)',
        'MashaFeedlyDisableSoundEffects' => 'Boolean',
        'MashaFeedlyAddress' => 'Varchar(3)',
        'MashaFeedlyOnboardingCompleted' => 'Boolean',
        'MashaFeedlyShowOnboarding' => 'Boolean',
    ];

    private static $has_one = [
        'MashaFeedlyIconImage' => Image::class,
    ];

    private static $defaults = [
        'MashaFeedlyEmailNotifications' => true,
        'MashaFeedlyNotifyNewEntries' => true,
        'MashaFeedlyNotifyEntryUpdates' => true,
        'MashaFeedlyNotifyOwnEntryChanges' => false,
        'MashaFeedlyNotifyComments' => true,
        'MashaFeedlyNotifyDueDateReminders' => true,
        'MashaFeedlyNotifyCostEstimates' => true,
    ];

    /** Liefert die kräftigen, gut unterscheidbaren Farben für Mitglieder-Avatare. */
    public static function colorOptions(): array
    {
        return [
            '#F6B7A9' => self::translate('COLOR_CORAL', 'Koralle'),
            '#F4D06F' => self::translate('COLOR_SUN_YELLOW', 'Sonnengelb'),
            '#B8D98A' => self::translate('COLOR_PISTACHIO', 'Pistazie'),
            '#8FD3C7' => self::translate('COLOR_MINT', 'Mint'),
            '#91B8E8' => self::translate('COLOR_SKY_BLUE', 'Himmelblau'),
            '#B5A0E0' => self::translate('COLOR_LILAC', 'Flieder'),
            '#E7A6C8' => self::translate('COLOR_ROSE', 'Rosé'),
            '#E9BE91' => self::translate('COLOR_APRICOT', 'Apricot'),
            '#F07872' => self::translate('COLOR_FLAME_RED', 'Flammenrot'),
            '#F39A52' => self::translate('COLOR_TANGERINE', 'Mandarine'),
            '#E95DAB' => self::translate('COLOR_PINK', 'Pink'),
            '#C05CC8' => self::translate('COLOR_BERRY', 'Beere'),
            '#9070DF' => self::translate('COLOR_VIOLET', 'Violett'),
            '#6383D8' => self::translate('COLOR_COBALT_BLUE', 'Kobaltblau'),
            '#42B8D1' => self::translate('COLOR_TURQUOISE', 'Türkis'),
            '#35A98F' => self::translate('COLOR_EMERALD', 'Smaragd'),
            '#69B85A' => self::translate('COLOR_FOREST_GREEN', 'Waldgrün'),
            '#C0C942' => self::translate('COLOR_LIME', 'Limette'),
        ];
    }

    /** Rendert die vollständige Farbpalette als beschriftete Farbfelder. */
    public static function renderColorPalette(string $fieldName = 'MashaFeedlyColor', ?string $currentColor = null): string
    {
        $items = '';
        $safeFieldName = htmlspecialchars($fieldName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $currentColor = self::normalizeColor($currentColor) ?? '';
        $selected = $currentColor === '' ? ' aria-pressed="true" class="masha-feedly-color-palette__item is-selected"' : ' aria-pressed="false" class="masha-feedly-color-palette__item"';
        $automatic = htmlspecialchars(self::translate('COLOR_AUTOMATIC', 'Automatisch'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $automaticDescription = htmlspecialchars(self::translate('COLOR_AUTOMATIC_DESCRIPTION', 'Automatisch vergeben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $items .= '<button type="button"' . $selected . ' data-masha-feedly-color-option data-color="" data-field-name="' . $safeFieldName . '" aria-label="' . $automatic . '">'
            . '<span class="masha-feedly-color-palette__swatch masha-feedly-color-palette__swatch--automatic" aria-hidden="true">A</span>'
            . '<span class="masha-feedly-color-palette__label">' . $automatic . '</span>'
            . '<span class="masha-feedly-color-palette__hex">' . $automaticDescription . '</span>'
            . '</button>';
        foreach (self::colorOptions() as $color => $label) {
            $safeColor = htmlspecialchars($color, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $selected = $currentColor === $color ? ' aria-pressed="true" class="masha-feedly-color-palette__item is-selected"' : ' aria-pressed="false" class="masha-feedly-color-palette__item"';
            $items .= '<button type="button"' . $selected . ' data-masha-feedly-color-option data-color="' . $safeColor . '" data-field-name="' . $safeFieldName . '" aria-label="' . $safeLabel . ', ' . $safeColor . '">'
                . '<span class="masha-feedly-color-palette__swatch" style="--masha-feedly-swatch-color:' . $safeColor . '" aria-hidden="true"></span>'
                . '<span class="masha-feedly-color-palette__label">' . $safeLabel . '</span>'
                . '<span class="masha-feedly-color-palette__hex">' . $safeColor . '</span>'
                . '</button>';
        }

        return '<div class="masha-feedly-color-palette" role="group" aria-label="' . htmlspecialchars(self::translate('COLOR_PALETTE_ARIA', 'Verfügbare Profilfarben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '<span class="masha-feedly-color-palette__heading">' . htmlspecialchars(self::translate('COLOR_PALETTE_TITLE', 'Verfügbare Farben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
            . '<div class="masha-feedly-color-palette__grid">' . $items . '</div>'
            . '</div>';
    }

    /** Prüft, ob eine übermittelte Farbe zur festgelegten Palette gehört. */
    public static function normalizeColor(?string $color): ?string
    {
        $color = strtoupper(trim((string)$color));
        return array_key_exists($color, self::colorOptions()) ? $color : null;
    }

    /** Liefert das persönliche Theme oder bei leerer Auswahl die konfigurierte Website-Vorgabe. */
    public static function themeFor(?Member $member): string
    {
        $theme = strtolower(trim((string)($member?->MashaFeedlyTheme ?? '')));
        return preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $theme)
            ? $theme
            : MashaFeedlyConfigExtension::theme();
    }

    /** Liefert die persönliche Anrede oder verwendet die Website-Vorgabe. */
    public static function addressFor(?Member $member): string
    {
        $address = strtolower(trim((string)($member?->MashaFeedlyAddress ?? '')));
        return in_array($address, ['du', 'sie'], true) ? $address : MashaFeedlyConfigExtension::address();
    }

    /** Wählt aus der Palette die bisher am seltensten vergebene Farbe. */
    public static function nextAvailableColor(array $usedColors = []): string
    {
        $counts = array_fill_keys(array_keys(self::colorOptions()), 0);
        foreach ($usedColors as $usedColor) {
            $normalized = self::normalizeColor((string)$usedColor);
            if ($normalized !== null) {
                $counts[$normalized]++;
            }
        }
        asort($counts);
        return (string)array_key_first($counts);
    }

    /** Liefert die Initialen des Mitglieds als Avatar-Fallback. */
    public function getMashaFeedlyInitials(): string
    {
        $firstName = trim((string)$this->owner->FirstName);
        $surname = trim((string)$this->owner->Surname);
        $initials = mb_strtoupper(mb_substr($firstName, 0, 1));
        if ($surname !== '') {
            $initials .= mb_strtoupper(mb_substr($surname, 0, 1));
        }
        return $initials !== '' ? $initials : '?';
    }

    /** Aktiviert die Einführung erneut, wenn das Profil die Wiederholung anfordert. */
    protected function onBeforeWrite(): void
    {
        // Eine automatische Farbe wird einmal gespeichert und bleibt bei späteren Profiländerungen stabil.
        $colors = array_keys(self::colorOptions());
        $this->owner->MashaFeedlyColor = self::normalizeColor((string)$this->owner->MashaFeedlyColor)
            ?? $colors[random_int(0, count($colors) - 1)];
        if (!MashaFeedlyConfigExtension::emailTestSucceeded()) {
            $notificationFields = [
                'MashaFeedlyEmailNotifications',
                'MashaFeedlyNotifyNewEntries',
                'MashaFeedlyNotifyEntryUpdates',
                'MashaFeedlyNotifyOwnEntryChanges',
                'MashaFeedlyNotifyComments',
                'MashaFeedlyNotifyDueDateReminders',
                'MashaFeedlyNotifyCostEstimates',
            ];
            $persisted = $this->owner->isInDB() ? Member::get()->byID((int)$this->owner->ID) : null;
            foreach ($notificationFields as $field) {
                $this->owner->$field = $persisted ? (bool)$persisted->$field : false;
            }
        }
        if (!MashaFeedlyEntry::canManageReporter(Security::getCurrentUser())) {
            $persisted = $this->owner->isInDB() ? Member::get()->byID((int)$this->owner->ID) : null;
            $this->owner->MashaFeedlyCanManageEstimates = $persisted
                ? (bool)$persisted->MashaFeedlyCanManageEstimates
                : false;
        }
        if ((bool)$this->owner->MashaFeedlyShowOnboarding) {
            $this->owner->MashaFeedlyOnboardingCompleted = false;
        }
    }

    /** Sichert das Masha-Feedly-Profilbild ab und begrenzt es auf freigegebene Mitglieder. */
    protected function onAfterWrite(): void
    {
        $this->protectMashaFeedlyIconImage();
        $iconID = trim((string)$this->owner->MashaFeedlyAvatarIcon);
        if ($iconID !== '' && $this->owner->isChanged('MashaFeedlyAvatarIcon')
            && MashaFeedlyConfigExtension::isExplicitlyAllowed($this->owner)) {
            try {
                (new MashaFeedlyEffectClient())->storeSelectedAvatarIcon($iconID);
            } catch (\Throwable) {
                // Das Profil bleibt speicherbar; der geschützte Avatar-Endpunkt versucht den Import bei Bedarf erneut.
            }
        }
    }

    /** Synchronisiert die Dateirechte des Profilbilds mit der aktuellen Masha-Feedly-Freigabe. */
    public function protectMashaFeedlyIconImage(): void
    {
        $image = $this->owner->MashaFeedlyIconImage();
        if (!$image instanceof Image || !$image->exists()) {
            return;
        }

        $image->CanViewType = InheritedPermissions::ONLY_THESE_MEMBERS;
        $image->ViewerMembers()->setByIDList(MashaFeedlyConfigExtension::memberIDs());
        $image->write();
        $image->publishSingle();
        $image->protectFile();
    }

    /** Erstellt den Upload-Ordner und beschränkt seinen Zugriff auf freigegebene Mitglieder. */
    public static function protectedIconFolder(): Folder
    {
        $structure = MashaFeedlyFolderService::ensureStructure();
        return $structure['children']['masha-feedly-profile-images'];
    }

    /** Liefert die gültige Avatarfarbe oder die erste Palettenfarbe als Darstellungsschutz. */
    public function getMashaFeedlyDisplayColor(): string
    {
        return self::normalizeColor((string)$this->owner->MashaFeedlyColor)
            ?? (string)array_key_first(self::colorOptions());
    }

    /** Liefert das ausgewählte Anbieter-Icon oder das geschützte Upload-Bild für Avatar-Ausgaben. @return string Geschützte Bild-URL oder leer. */
    public function getMashaFeedlyAvatarURL(): string
    {
        $iconID = (string)$this->owner->MashaFeedlyAvatarIcon;
        if ($iconID !== '') {
            $color = self::iconColorForAvatarColor($this->getMashaFeedlyDisplayColor());
            return \SilverStripe\Control\Director::absoluteURL('__masha-feedly-effects/avatar/' . rawurlencode($iconID) . '/' . $color);
        }
        $image = $this->owner->MashaFeedlyIconImage();
        return $image instanceof Image && $image->exists() ? (string)$image->getURL() : '';
    }

    /** Zeigt den tatsächlich verwendeten Avatar auch ohne erreichbaren Icon-Katalog im Profil an. @return string Geschützte Avatarvorschau. */
    public function renderAvatarPreview(): string
    {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = $this->getMashaFeedlyAvatarURL();
        $image = $this->owner->MashaFeedlyIconImage();
        $uploadURL = $image instanceof Image && $image->exists() ? (string)$image->getURL() : '';
        return '<div class="masha-feedly-profile-avatar-preview" data-masha-feedly-avatar-preview'
            . ' data-avatar-base="' . $escape(\SilverStripe\Control\Director::absoluteURL('__masha-feedly-effects/avatar/')) . '"'
            . ' data-icon-id="' . $escape((string)$this->owner->MashaFeedlyAvatarIcon) . '"'
            . ' data-upload-url="' . $escape($uploadURL) . '" data-initials="' . $escape($this->getMashaFeedlyInitials()) . '"'
            . ' style="background-color:' . $escape($this->getMashaFeedlyDisplayColor()) . '"'
            . ' aria-label="' . $escape(self::translate('PROFILE_IMAGE', 'Dein Masha:Feedly-Avatar')) . '">'
            . ($url !== '' ? '<img src="' . $escape($url) . '" alt="">' : $escape($this->getMashaFeedlyInitials())) . '</div>';
    }

    /** Wählt für die gewählte Avatarfarbe eine kontrastreiche Icon-Farbe. @param string $color Hex-Farbwert. @return string Schwarz oder Weiß. */
    public static function iconColorForAvatarColor(string $color): string
    {
        $color = self::normalizeColor($color) ?? (string)array_key_first(self::colorOptions());
        // Diese Grüntöne verwenden bewusst die helle Variante, passend zur gewünschten Avatar-Gestaltung.
        if (in_array($color, ['#35A98F', '#69B85A'], true)) {
            return 'white';
        }
        $channels = array_map(static fn(string $channel): float => hexdec($channel) / 255, str_split(substr($color, 1), 2));
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        return $luminance > 0.52 ? 'black' : 'white';
    }

    /** Fügt die Benachrichtigungsoptionen zum normalen Mitgliederformular hinzu. */
    protected function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName([
            'MashaFeedlyEmailNotifications',
            'MashaFeedlyNotifyNewEntries',
            'MashaFeedlyNotifyEntryUpdates',
            'MashaFeedlyNotifyOwnEntryChanges',
            'MashaFeedlyNotifyComments',
            'MashaFeedlyNotifyDueDateReminders',
            'MashaFeedlyNotifyCostEstimates',
            'MashaFeedlyCanManageEstimates',
            'MashaFeedlyOnboardingCompleted',
            'MashaFeedlyShowOnboarding',
            'MashaFeedlyIconImage',
            'MashaFeedlyAvatarIcon',
            'MashaFeedlyColor',
            'MashaFeedlyTheme',
            'MashaFeedlyDisableSoundEffects',
            'MashaFeedlyAddress',
            'MashaFeedlyProfileAppearance',
            'MashaFeedlyOnboardingSettings',
            'MashaFeedlyEstimateSettings',
        ]);

        $currentUser = Security::getCurrentUser();
        $emailTestSucceeded = MashaFeedlyConfigExtension::emailTestSucceeded();
        $isEstimateManager = MashaFeedlyEntry::canManageReporter($currentUser);
        $isAllowedMember = MashaFeedlyConfigExtension::isExplicitlyAllowed($currentUser);

        $estimateSettings = $isEstimateManager
            ? CompositeField::create(CheckboxField::create(
                'MashaFeedlyCanManageEstimates',
                self::translate('PROFILE_CAN_MANAGE_ESTIMATES', 'Darf Kostenschätzungen freigeben')
            )->setDescription(self::translate('PROFILE_CAN_MANAGE_ESTIMATES_DESCRIPTION', 'Die Person sieht Dauer, Erläuterung und geschätzten Preis und darf die Schätzung freigeben. Bearbeiten kann sie nur der konfigurierte Masha:Feedly-Superadmin.')))
                ->setName('MashaFeedlyEstimateSettings')
                ->setTitle(self::translate('PROFILE_ESTIMATE_SETTINGS', 'Kostenschätzungen'))
                ->addExtraClass('masha-feedly-profile-settings masha-feedly-estimate-settings')
            : null;

        if (!$isAllowedMember) {
            if ($estimateSettings) {
                $fields->addFieldToTab('Root.MashaFeedly', $estimateSettings);
            }
            return;
        }

        Requirements::css('kooperativeweb/masha-feedly:client/dist/css/masha-feedly.css');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-colors.js');
        self::protectedIconFolder();
        $avatarIconPicker = self::renderAvatarIconPicker(
            (string)$this->owner->MashaFeedlyAvatarIcon,
            self::normalizeColor((string)$this->owner->MashaFeedlyColor)
        );
        $logoURL = htmlspecialchars(
            (string)ModuleResourceLoader::resourceURL('kooperativeweb/masha-feedly:client/dist/icons/masha-feedly.svg'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $newEntries = CheckboxField::create(
            'MashaFeedlyNotifyNewEntries',
            self::translate('PROFILE_NOTIFY_NEW_ENTRIES', 'Bei neuen Meldungen benachrichtigen')
        )->setDescription(self::translate('PROFILE_NOTIFY_NEW_ENTRIES_DESCRIPTION', 'Erhalte eine E-Mail, wenn eine neue Meldung erstellt wird. Für deine eigenen Meldungen gilt zusätzlich die separate Option für eigene Meldungen und Änderungen.'))->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $ownEntryUpdates = CheckboxField::create(
            'MashaFeedlyNotifyOwnEntryChanges',
            self::translate('PROFILE_NOTIFY_OWN_CHANGES', 'Auch bei eigenen Meldungen und Änderungen benachrichtigen')
        )->setDescription(self::translate('PROFILE_NOTIFY_OWN_CHANGES_DESCRIPTION', 'Diese Option ist standardmäßig ausgeschaltet. Schalte sie ein, wenn du E-Mails auch für Meldungen erhalten möchtest, die du selbst erstellst oder änderst.'))->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $comments = CheckboxField::create(
            'MashaFeedlyNotifyComments',
            self::translate('PROFILE_NOTIFY_COMMENTS', 'Bei neuen Kommentaren benachrichtigen')
        )->setDescription(self::translate('PROFILE_NOTIFY_COMMENTS_DESCRIPTION', 'Erhalte eine E-Mail, wenn jemand bei einer Meldung kommentiert, für die du zuständig bist oder die du erstellt hast.'))->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $dueDateReminders = CheckboxField::create(
            'MashaFeedlyNotifyDueDateReminders',
            self::translate('PROFILE_NOTIFY_DUE_DATE_REMINDERS', 'An Fälligkeitstermine erinnern')
        )->setDescription(self::translate('PROFILE_NOTIFY_DUE_DATE_REMINDERS_DESCRIPTION', 'Erhalte am Fälligkeitstag eine einmalige Erinnerung für Meldungen, denen du zugewiesen bist oder die du erstellt hast.'))->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $costEstimates = CheckboxField::create(
            'MashaFeedlyNotifyCostEstimates',
            self::translate('PROFILE_NOTIFY_COST_ESTIMATES', 'Bei angefragten Kostenschätzungen benachrichtigen')
        )->setDescription(self::translate('PROFILE_NOTIFY_COST_ESTIMATES_DESCRIPTION', 'Erhalte eine E-Mail, wenn eine Kostenschätzung zur Freigabe bereitsteht.'))->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $entryUpdates = CheckboxField::create(
            'MashaFeedlyNotifyEntryUpdates',
            self::translate('PROFILE_NOTIFY_ENTRY_UPDATES', 'Bei Änderungen an Meldungen benachrichtigen')
        )->setDescription(self::translate('PROFILE_NOTIFY_ENTRY_UPDATES_DESCRIPTION', 'Erhalte eine E-Mail, wenn sich Status, Beschreibung, Zuständigkeit oder andere Meldungsdetails ändern. Für eigene Änderungen gilt zusätzlich die separate Option für eigene Meldungen.'))->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();

        if (!$emailTestSucceeded) {
            foreach ([$newEntries, $entryUpdates, $ownEntryUpdates, $comments, $dueDateReminders, $costEstimates] as $field) {
                $field->setDisabled(true);
            }
        }

        $avatarFields = [
            LiteralField::create('MashaFeedlyAvatarPreview', $this->renderAvatarPreview()),
            UploadField::create('MashaFeedlyIconImage', self::translate('PROFILE_IMAGE', 'Dein Masha:Feedly-Avatar'))
                ->setFolderName('masha-feedly/masha-feedly-profile-images')
                ->setAllowedFileCategories('image/supported')
                ->setAllowedMaxFileNumber(1)
                ->setAttachEnabled(false)
                ->setDescription(self::translate('PROFILE_IMAGE_DESCRIPTION', 'Das Bild ist geschützt und nur für freigegebene Masha-Feedly-Benutzer sichtbar. Ohne Bild werden deine Initialen angezeigt.')),
        ];
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-avatar-icons.js');
        if ($avatarIconPicker !== '') {
            $avatarFields[] = CompositeField::create(
                MashaFeedlyInteractiveLiteralField::create('MashaFeedlyAvatarIconChoices', $avatarIconPicker),
                HiddenField::create('MashaFeedlyAvatarIcon', null, (string)$this->owner->MashaFeedlyAvatarIcon)
                    ->setAttribute('data-masha-feedly-avatar-icon-value', 'true')
            )->setName('MashaFeedlyAvatarIconSelection')->setTitle(self::translate('PROFILE_ICON_SELECTION', 'Oder ein eigenes Masha-Icon wählen'));
            Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-avatar-icons.js');
        }
        $avatarColor = CompositeField::create(
            MashaFeedlyInteractiveLiteralField::create('MashaFeedlyColorPalette', self::renderColorPalette(
                'MashaFeedlyColor', self::normalizeColor((string)$this->owner->MashaFeedlyColor)
            )),
            HiddenField::create('MashaFeedlyColor', null, self::normalizeColor((string)$this->owner->MashaFeedlyColor) ?? '')
        )->setName('MashaFeedlyAvatarColor')->setTitle(self::translate('PROFILE_COLOR', 'Profilfarbe'));

        $appearanceSettings = CompositeField::create(
            $avatarColor,
            DropdownField::create('MashaFeedlyAddress', self::translate('PROFILE_ADDRESS', 'Wie möchtest du angesprochen werden?'), [
                'du' => self::translate('CONFIG_ADDRESS_DU', 'Du'),
                'sie' => self::translate('CONFIG_ADDRESS_SIE', 'Sie'),
            ])
                ->setValue((string)$this->owner->MashaFeedlyAddress)
                ->setEmptyString(self::translate('PROFILE_ADDRESS_DEFAULT', 'Einstellung der Website übernehmen'))
                ->setDescription(self::translate('PROFILE_ADDRESS_DESCRIPTION', 'Wähle Du oder Sie. Ohne eigene Auswahl gilt die Einstellung der Website.')),
            DropdownField::create('MashaFeedlyTheme', self::translate('PROFILE_THEME', 'Danke-Animation'), MashaFeedlyEffectClient::themeOptions((string)$this->owner->MashaFeedlyTheme))
                ->setValue((string)$this->owner->MashaFeedlyTheme)
                ->setEmptyString(self::translate('PROFILE_THEME_DEFAULT', 'Einstellung der Website übernehmen'))
                ->setDescription(self::translate('PROFILE_THEME_DESCRIPTION', 'Wähle dein persönliches Erscheinungsbild. Bei Website-Vorgabe gilt das Theme aus Masha:Feedly → Konfiguration; neue Installationen verwenden Verspielt.')),
            CheckboxField::create('MashaFeedlyDisableSoundEffects', self::translate('PROFILE_DISABLE_SOUND', 'Animationen ohne Ton abspielen'))
                ->setValue((bool)$this->owner->MashaFeedlyDisableSoundEffects)
                ->setDescription(self::translate('PROFILE_DISABLE_SOUND_HELP', 'Alle Animationen bleiben verfügbar. Musik und andere Töne werden nicht abgespielt.'))
        )->setName('MashaFeedlyProfileAppearance')->setTitle(self::translate('PROFILE_APPEARANCE', 'Darstellung'))->addExtraClass('masha-feedly-profile-settings masha-feedly-profile-appearance');

        $profileIntro = self::translate(
            'PROFILE_INTRO',
            'Wähle dein Profilbild oder Symbol und deine Profilfarbe. Stelle deine Danke-Animation, deine Anrede und deine E-Mail-Benachrichtigungen ein. Hier kannst du auch die Einführung erneut starten.'
        );
        if ($isEstimateManager) {
            $profileIntro .= ' ' . self::translate(
                'PROFILE_INTRO_ESTIMATE_MANAGER',
                'Außerdem kannst du festlegen, wer Kostenschätzungen freigeben darf.'
            );
        }

        $fields->addFieldsToTab('Root.MashaFeedly', [
            LiteralField::create(
                'MashaFeedlyPreferencesIntro',
                '<div class="masha-feedly-profile-intro"><img src="' . $logoURL . '" alt="" width="44" height="44">'
                . '<div><h2>' . self::translate('PROFILE_TITLE', 'Masha:Feedly') . '</h2><p>' . $profileIntro . '</p></div></div>'
            ),
            CompositeField::create(...$avatarFields)->setName('MashaFeedlyProfileSettings')->setTitle(self::translate('PROFILE_AVATAR', 'Profil und Avatar'))->addExtraClass('masha-feedly-profile-settings'),
            $appearanceSettings,
            CompositeField::create(
                CheckboxField::create(
                    'MashaFeedlyEmailNotifications',
                    self::translate('PROFILE_EMAIL_NOTIFICATIONS', 'E-Mail-Benachrichtigungen von Masha:Feedly erhalten')
                )->setDescription($emailTestSucceeded
                    ? self::translate('PROFILE_EMAIL_DESCRIPTION', 'Wenn diese Hauptoption eingeschaltet ist, erhältst du die unten ausgewählten E-Mails. Du kannst einzelne Arten oder alle Benachrichtigungen jederzeit ausschalten.')
                    : self::translate('PROFILE_EMAIL_TEST_REQUIRED', 'E-Mail-Einstellungen sind gesperrt. Ein Masha:Feedly-Administrator muss zuerst unter Masha:Feedly → Konfiguration eine Test-E-Mail erfolgreich versenden.'))
                    ->setDisabled(!$emailTestSucceeded),
                $newEntries,
                $entryUpdates,
                $ownEntryUpdates,
                $comments,
                $dueDateReminders,
                $costEstimates
            )->setName('MashaFeedlyEmailSettings')->setTitle(self::translate('PROFILE_EMAIL_SETTINGS', 'E-Mail-Benachrichtigungen'))->addExtraClass('masha-feedly-email-settings'),
            CompositeField::create(
                CheckboxField::create(
                    'MashaFeedlyShowOnboarding',
                    self::translate('PROFILE_SHOW_ONBOARDING', 'Einführung erneut anzeigen')
                )->setDescription(self::translate('PROFILE_SHOW_ONBOARDING_DESCRIPTION', 'Aktiviere diese Option, wenn du die Schritt-für-Schritt-Einführung beim nächsten Besuch noch einmal sehen möchtest.')),
                CheckboxField::create(
                    'MashaFeedlyOnboardingCompleted',
                    self::translate('PROFILE_ONBOARDING_COMPLETED', 'Einführung abgeschlossen')
                )->setDescription(self::translate('PROFILE_ONBOARDING_COMPLETED_DESCRIPTION', 'Zeigt an, ob die Einführung bereits abgeschlossen wurde. Zum erneuten Anzeigen aktiviere „Einführung erneut anzeigen“.'))
            )->setName('MashaFeedlyOnboardingSettings')->setTitle(self::translate('PROFILE_ONBOARDING_SETTINGS', 'Einführung'))->addExtraClass('masha-feedly-profile-settings masha-feedly-onboarding-settings'),
        ]);

        if ($estimateSettings) {
            $fields->addFieldToTab('Root.MashaFeedly', $estimateSettings);
        }
    }

    /**
     * Rendert die vom Anbieter gelieferten Icon-Auswahlen für den Einführungsdialog.
     *
     * @param string $selectedID Gespeicherte Icon-Kennung.
     * @param string|null $color Gewählte Avatarfarbe.
     * @param MashaFeedlyEffectClient|null $client Optionaler Anbieter-Client für Tests.
     * @return string Auswahlelemente oder leer, wenn kein Katalog verfügbar ist.
     */
    public static function renderAvatarIconPickerForWidget(string $selectedID, ?string $color, ?MashaFeedlyEffectClient $client = null): string
    {
        return self::renderAvatarIconPicker($selectedID, $color, $client);
    }

    /** Rendert nur einen gültigen, vom explizit konfigurierten Anbieter gelieferten Icon-Katalog. @return string Auswahlfeld oder leer. */
    protected static function renderAvatarIconPicker(string $selectedID, ?string $color, ?MashaFeedlyEffectClient $client = null): string
    {
        $client ??= new MashaFeedlyEffectClient();
        try { $catalogue = $client->avatarIcons(); }
        catch (\Throwable) {
            if (!$client::hasConfiguredProvider()) return '';
            return '<p class="masha-feedly-avatar-icons__unavailable" role="status">'
                . htmlspecialchars(self::translate('PROFILE_ICON_PROVIDER_UNAVAILABLE', 'Die Icon-Auswahl ist vorübergehend nicht verfügbar. Der Anbieter antwortet gerade nicht. Bitte versuche es später erneut.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</p>';
        }
        if (!$catalogue['icons']) return '';
        $iconColor = self::iconColorForAvatarColor($color ?? '');
        $html = '<div class="masha-feedly-avatar-icons" data-masha-feedly-avatar-icons>'
            . '<button type="button" class="masha-feedly-avatar-icons__open" data-masha-feedly-avatar-icon-open aria-haspopup="dialog">'
            . htmlspecialchars(self::translate('PROFILE_ICON_OPEN', 'Symbol auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button>'
            . '<div class="masha-feedly-avatar-icons__dialog" data-masha-feedly-avatar-icon-dialog hidden><div class="masha-feedly-avatar-icons__backdrop" data-masha-feedly-avatar-icon-close></div>'
            . '<section class="masha-feedly-avatar-icons__panel" role="dialog" aria-modal="true" aria-label="'
            . htmlspecialchars(self::translate('PROFILE_ICON_CHOOSER', 'Profil-Symbol auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '"><header class="masha-feedly-avatar-icons__header"><div><span>MASHA:FEEDLY</span><h2>'
            . htmlspecialchars(self::translate('PROFILE_ICON_CHOOSER', 'Profil-Symbol auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</h2></div><button type="button" data-masha-feedly-avatar-icon-close aria-label="'
            . htmlspecialchars(self::translate('CLOSE', 'Schließen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">×</button></header><div class="masha-feedly-avatar-icons__content"><p>'
            . htmlspecialchars(self::translate('PROFILE_ICON_CHOICES_DESCRIPTION', 'Wähle ein Symbol. Seine Farbe passt sich automatisch an deine Profilfarbe an.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p><button type="button" class="masha-feedly-avatar-icons__clear" data-masha-feedly-avatar-icon-clear aria-pressed="' . ($selectedID === '' ? 'true' : 'false') . '">'
            . htmlspecialchars(self::translate('PROFILE_ICON_CLEAR', 'Kein Symbol verwenden'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button><nav class="masha-feedly-avatar-icons__tabs" role="tablist" aria-label="'
            . htmlspecialchars(self::translate('PROFILE_ICON_CATEGORIES', 'Icon-Kategorien'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        $selectedCategory = '';
        foreach ($catalogue['icons'] as $icon) {
            if ($selectedID !== '' && $icon['id'] === $selectedID) {
                $selectedCategory = $icon['category'];
                break;
            }
        }
        if ($selectedCategory === '' && isset($catalogue['categories'][0]['id'])) {
            $selectedCategory = $catalogue['categories'][0]['id'];
        }
        foreach ($catalogue['categories'] as $index => $category) {
            $categoryID = htmlspecialchars($category['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $categoryName = htmlspecialchars($category['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $active = $category['id'] === $selectedCategory;
            $html .= '<button type="button" role="tab" id="masha-feedly-icon-tab-' . $categoryID . '" aria-controls="masha-feedly-icons-' . $categoryID . '" aria-selected="' . ($active ? 'true' : 'false') . '" tabindex="' . ($active ? '0' : '-1') . '" data-masha-feedly-avatar-icon-tab="' . $categoryID . '">' . $categoryName . '</button>';
        }
        $html .= '</nav>';
        foreach ($catalogue['categories'] as $index => $category) {
            $categoryID = htmlspecialchars($category['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $categoryName = htmlspecialchars($category['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $active = $category['id'] === $selectedCategory;
            $html .= '<section id="masha-feedly-icons-' . $categoryID . '" class="masha-feedly-avatar-icons__category" role="tabpanel" aria-labelledby="masha-feedly-icon-tab-' . $categoryID . '"' . ($active ? '' : ' hidden') . '><h3>' . $categoryName . '</h3><div class="masha-feedly-avatar-icons__grid">';
            foreach ($catalogue['icons'] as $icon) {
                if ($icon['category'] !== $category['id']) continue;
                $id = htmlspecialchars($icon['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $name = htmlspecialchars($icon['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $pressed = $selectedID === $icon['id'];
                $imageURL = htmlspecialchars($icon['files'][$iconColor], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= '<button type="button" class="masha-feedly-avatar-icons__choice' . ($pressed ? ' is-selected' : '') . '" data-masha-feedly-avatar-icon-choice data-icon-id="' . $id . '" data-icon-black="' . htmlspecialchars($icon['files']['black'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-icon-white="' . htmlspecialchars($icon['files']['white'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" aria-pressed="' . ($pressed ? 'true' : 'false') . '" aria-label="' . $name . '"><img' . ($active ? ' src="' . $imageURL . '"' : '') . ' data-icon-src="' . $imageURL . '" alt="" loading="lazy"><span>' . $name . '</span></button>';
            }
            $html .= '</div></section>';
        }
        return $html . '</div></section></div></div>';
    }
    /** Liefert eine lokalisierte Modulbeschriftung mit deutschem Ersatztext. */
    private static function translate(string $key, string $fallback): string
    {
        return i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $fallback);
    }
}
