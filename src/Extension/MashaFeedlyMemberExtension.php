<?php

namespace KW\MashaFeedly\Extension;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Security\Security;
use SilverStripe\Security\InheritedPermissions;
use KW\MashaFeedly\Service\MashaFeedlyFolderService;
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
 * @property int $MashaFeedlyIconImageID ID des geschützten Profilbildes für Masha Feedly.
 * @property Image $MashaFeedlyIconImage Geschütztes Masha-Feedly-Profilbild.
 * @property string $MashaFeedlyColor Individuelle Avatarfarbe im Masha-Feedly-Board.
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
        'MashaFeedlyColor' => 'Varchar(7)',
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

        return '<div class="masha-feedly-color-palette" role="group" aria-label="' . htmlspecialchars(self::translate('COLOR_PALETTE_ARIA', 'Verfügbare Avatarfarben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
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
        if ((bool)$this->owner->MashaFeedlyShowOnboarding) {
            $this->owner->MashaFeedlyOnboardingCompleted = false;
        }
    }

    /** Sichert das Masha-Feedly-Profilbild ab und begrenzt es auf freigegebene Mitglieder. */
    protected function onAfterWrite(): void
    {
        $this->protectMashaFeedlyIconImage();
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
            'MashaFeedlyOnboardingCompleted',
            'MashaFeedlyShowOnboarding',
            'MashaFeedlyIconImage',
            'MashaFeedlyColor',
        ]);

        if (!MashaFeedlyConfigExtension::isExplicitlyAllowed(Security::getCurrentUser())) {
            return;
        }

        Requirements::css('kooperativeweb/masha-feedly:client/dist/css/masha-feedly.css');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-colors.js');
        self::protectedIconFolder();
        $logoURL = htmlspecialchars(
            (string)ModuleResourceLoader::resourceURL('kooperativeweb/masha-feedly:client/dist/icons/masha-feedly.svg'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $newEntries = CheckboxField::create(
            'MashaFeedlyNotifyNewEntries',
            self::translate('PROFILE_NOTIFY_NEW_ENTRIES', 'Bei neuen Einträgen benachrichtigen')
        )->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $ownEntryUpdates = CheckboxField::create(
            'MashaFeedlyNotifyOwnEntryChanges',
            self::translate('PROFILE_NOTIFY_OWN_CHANGES', 'Auch bei eigenen Einträgen und Änderungen benachrichtigen')
        )->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $comments = CheckboxField::create(
            'MashaFeedlyNotifyComments',
            self::translate('PROFILE_NOTIFY_COMMENTS', 'Bei neuen Kommentaren benachrichtigen')
        )->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $dueDateReminders = CheckboxField::create(
            'MashaFeedlyNotifyDueDateReminders',
            self::translate('PROFILE_NOTIFY_DUE_DATE_REMINDERS', 'An Fälligkeitstermine erinnern')
        )->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();
        $entryUpdates = CheckboxField::create(
            'MashaFeedlyNotifyEntryUpdates',
            self::translate('PROFILE_NOTIFY_ENTRY_UPDATES', 'Bei Änderungen an Einträgen benachrichtigen')
        )->displayIf('MashaFeedlyEmailNotifications')->isChecked()->end();

        $fields->addFieldsToTab('Root.MashaFeedly', [
            LiteralField::create(
                'MashaFeedlyPreferencesIntro',
                '<div class="masha-feedly-profile-intro"><img src="' . $logoURL . '" alt="" width="44" height="44">'
                . '<div><h2>' . self::translate('PROFILE_TITLE', 'Masha:Feedly') . '</h2><p>' . self::translate('PROFILE_INTRO', 'Verwalte dein Profil und bestimme, worüber dich Masha:Feedly per E-Mail informiert.') . '</p></div></div>'
            ),
            CompositeField::create(
                UploadField::create('MashaFeedlyIconImage', self::translate('PROFILE_IMAGE', 'Dein Masha-Feedly-Profilbild'))
                    ->setFolderName('masha-feedly/masha-feedly-profile-images')
                    ->setAllowedFileCategories('image/supported')
                    ->setAllowedMaxFileNumber(1)
                    ->setAttachEnabled(false)
                    ->setDescription(self::translate('PROFILE_IMAGE_DESCRIPTION', 'Das Bild ist geschützt und nur für freigegebene Masha-Feedly-Benutzer sichtbar. Ohne Bild werden deine Initialen angezeigt.')),
                CompositeField::create(
                    LiteralField::create('MashaFeedlyColorPalette', self::renderColorPalette(
                        'MashaFeedlyColor',
                        self::normalizeColor((string)$this->owner->MashaFeedlyColor)
                    )),
                    HiddenField::create(
                        'MashaFeedlyColor',
                        null,
                        self::normalizeColor((string)$this->owner->MashaFeedlyColor) ?? ''
                    )
                )->setName('MashaFeedlyAvatarColor')->setTitle(self::translate('PROFILE_COLOR', 'Avatarfarbe'))
            )->setName('MashaFeedlyProfileSettings')->setTitle(self::translate('PROFILE_AVATAR', 'Profil und Avatar'))->addExtraClass('masha-feedly-profile-settings'),
            CompositeField::create(
                CheckboxField::create(
                    'MashaFeedlyEmailNotifications',
                    self::translate('PROFILE_EMAIL_NOTIFICATIONS', 'E-Mail-Benachrichtigungen von Masha:Feedly erhalten')
                )->setDescription(self::translate('PROFILE_EMAIL_DESCRIPTION', 'Du kannst die Benachrichtigungen jederzeit ausschalten.')),
                $newEntries,
                $entryUpdates,
                $ownEntryUpdates,
                $comments,
                $dueDateReminders
            )->setName('MashaFeedlyEmailSettings')->setTitle(self::translate('PROFILE_EMAIL_SETTINGS', 'E-Mail-Benachrichtigungen'))->addExtraClass('masha-feedly-email-settings'),
            CheckboxField::create(
                'MashaFeedlyShowOnboarding',
                self::translate('PROFILE_SHOW_ONBOARDING', 'Einführung erneut anzeigen')
            )->setDescription(self::translate('PROFILE_SHOW_ONBOARDING_DESCRIPTION', 'Aktiviere diese Option, wenn du die Schritt-für-Schritt-Einführung beim nächsten Besuch noch einmal sehen möchtest.')),
            CheckboxField::create(
                'MashaFeedlyOnboardingCompleted',
                self::translate('PROFILE_ONBOARDING_COMPLETED', 'Einführung abgeschlossen')
            )->setDescription(self::translate('PROFILE_ONBOARDING_COMPLETED_DESCRIPTION', 'Zeigt an, ob die Einführung bereits abgeschlossen wurde. Zum erneuten Anzeigen aktiviere „Einführung erneut anzeigen“.')),
        ]);
    }
    /** Liefert eine lokalisierte Modulbeschriftung mit deutschem Ersatztext. */
    private static function translate(string $key, string $fallback): string
    {
        return i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $fallback);
    }
}
