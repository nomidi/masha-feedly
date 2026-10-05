<?php

namespace KW\MashaFeedly\Admin;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Extension\MashaFeedlyMemberExtension;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyPriority;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Service\MashaFeedlyResetService;
use SilverStripe\Assets\Image;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;
use SilverStripe\i18n\i18n;

/**
 * CMS-Verwaltung für Masha-Feedly-Einträge, Kommentare und Einstellungen.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyAdmin extends ModelAdmin
{
    private static $url_segment = 'masha-feedly';
    private static $menu_title = 'Masha:Feedly';
    private static $menu_icon = 'kooperativeweb/masha-feedly:client/dist/icons/masha-feedly.svg';
    private static $managed_models = [
        MashaFeedlyEntry::class => ['title' => 'Einträge'],
        MashaFeedlyCategory::class => ['title' => 'Kategorien'],
        MashaFeedlyPriority::class => ['title' => 'Prioritäten'],
        MashaFeedlyComment::class => ['title' => 'Kommentare'],
        SiteConfig::class => ['title' => 'Konfiguration'],
    ];

    /** canView() verwendet die modulspezifische Benutzerfreigabe. */
    private static $required_permission_codes = false;

    private static $allowed_actions = [
        'saveConfiguration',
        'moveEntry',
        'moveCategory',
        'createCategory',
        'deleteCategory',
        'saveReporter',
        'resetAllMashaFeedlyData',
    ];

    /** Liefert die Anzahl offener Einträge in der Feedback-Kategorie. */
    public static function feedbackCount(): int
    {
        $category = MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->first();
        return $category && !$category->IsClosed ? $category->Entries()->count() : 0;
    }

    /** Leitet Nicht-Administratoren bei direkten Aufrufen der Konfiguration um. */
    /** @return void */
    protected function init(): void
    {
        $requestedModel = $this->getRequest()->param('ModelClass');
        $member = Security::getCurrentUser();
        if (
            $requestedModel
            && str_replace('-', '\\', $requestedModel) === SiteConfig::class
            && (!$member || !Permission::checkMember($member, 'ADMIN'))
        ) {
            $this->redirect($this->Link());
        }
        parent::init();
        MashaFeedlyCategory::ensureDefaultCategories();
    }

    /**
     * Prüft den Zugriff für Administratoren und ausdrücklich freigegebene Benutzer.
     *
     * @param Member|null $member Zu prüfendes Silverstripe-Mitglied.
     * @return bool Gibt zurück, ob das Mitglied den Bereich öffnen darf.
     */
    public function canView($member = null)
    {
        $member ??= Security::getCurrentUser();
        return $member instanceof Member && (
            Permission::checkMember($member, 'ADMIN')
            || MashaFeedlyConfigExtension::canUse($member)
        );
    }

    /**
     * Zeigt im Konfigurationstab direkt die auswählbaren Benutzer an.
     *
     * @param int|string|null $id Optionale Datensatz-ID des ModelAdmin.
     * @param FieldList|null $fields Optionale Formularfelder.
     * @return Form Das Formular für den ausgewählten ModelAdmin-Tab.
     */
    public function getEditForm($id = null, $fields = null)
    {
        if ($this->modelClass !== SiteConfig::class) {
            $form = parent::getEditForm($id, $fields);
            if (
                $this->modelClass === MashaFeedlyEntry::class
                && !str_contains((string)$this->getRequest()->getURL(), '/item/')
            ) {
                $gridFieldName = $this->sanitiseClassName($this->modelTab);
                $gridField = $form->Fields()->dataFieldByName($gridFieldName);
                if ($gridField) {
                    $gridField->addExtraClass('masha-feedly-admin-gridfield--hidden');
                    $form->Fields()->insertBefore(
                        $gridFieldName,
                        LiteralField::create('MashaFeedlyEntryBoard', $this->renderEntryBoard())
                    );
                }
                Requirements::css('kooperativeweb/masha-feedly:client/dist/css/masha-feedly-admin.css');
                Requirements::css('kooperativeweb/masha-feedly:client/dist/css/masha-feedly.css');
                Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-admin.js');
            }
            return $form;
        }

        $members = [];
        $authorizedMemberIDs = MashaFeedlyConfigExtension::memberIDs();
        $canManageSensitiveSettings = MashaFeedlyEntry::canManageReporter(Security::getCurrentUser());
        foreach (Member::get()->sort('Surname ASC, FirstName ASC') as $member) {
            $members[(string)$member->ID] = (string)$member->getName();
        }
        $fields = FieldList::create(
            DropdownField::create('MashaFeedlyAddress', self::translate('CONFIG_ADDRESS', 'Anrede im Modul'), [
                'du' => self::translate('CONFIG_ADDRESS_DU', 'Du'),
                'sie' => self::translate('CONFIG_ADDRESS_SIE', 'Sie'),
            ])
                ->setValue(MashaFeedlyConfigExtension::address())
                ->setDescription(self::translate('CONFIG_ADDRESS_DESCRIPTION', 'Legt fest, wie Masha:Feedly Benutzerinnen und Benutzer anspricht.')),
            DropdownField::create('MashaFeedlyFontSize', self::translate('CONFIG_FONT_SIZE', 'Schriftgröße im Widget'), [
                'small' => self::translate('CONFIG_FONT_SIZE_SMALL', 'Klein'),
                'medium' => self::translate('CONFIG_FONT_SIZE_MEDIUM', 'Mittel'),
                'large' => self::translate('CONFIG_FONT_SIZE_LARGE', 'Groß'),
            ])
                ->setValue(MashaFeedlyConfigExtension::fontSize())
                ->setDescription(self::translate('CONFIG_FONT_SIZE_DESCRIPTION', 'Passt die Schriftgröße für alle Masha:Feedly-Ansichten an.')),
            DropdownField::create('MashaFeedlyTheme', self::translate('CONFIG_THEME', 'Erscheinungsbild'), [
                'playful' => self::translate('CONFIG_THEME_PLAYFUL', 'Verspielt'),
                'serious' => self::translate('CONFIG_THEME_SERIOUS', 'Seriös'),
            ])
                ->setValue(MashaFeedlyConfigExtension::theme())
                ->setDescription(self::translate('CONFIG_THEME_DESCRIPTION', 'Legt Farben, Erfolgsmeldungen und Abschlussanimationen im Widget fest.')),
            ListboxField::create('AllowedMemberIDs', self::translate('CONFIG_ALLOWED_MEMBERS', 'Benutzer mit Zugriff'), $members)
                ->setValue(MashaFeedlyConfigExtension::memberIDs())
                ->setDescription(self::translate('CONFIG_ALLOWED_MEMBERS_DESCRIPTION', 'Wähle alle Benutzer aus, die das Widget und die Einträge verwenden dürfen. Auch Administratoren benötigen eine Freigabe.'))
        );
        if ($canManageSensitiveSettings) {
            $fields->insertBefore('AllowedMemberIDs', DropdownField::create('MashaFeedlyDueDateReminderMode', self::translate('CONFIG_DUE_DATE_REMINDER_MODE', 'Fälligkeitserinnerungen ausführen'), [
                'cron' => self::translate('CONFIG_DUE_DATE_REMINDER_CRON', 'Serverseitig per Cronjob'),
                'visitor' => self::translate('CONFIG_DUE_DATE_REMINDER_VISITOR', 'Bei Websitebesuchen'),
            ])
                ->setValue(MashaFeedlyConfigExtension::dueDateReminderMode())
                ->setDescription(self::translate('CONFIG_DUE_DATE_REMINDER_MODE_DESCRIPTION', 'Cronjob: tägliche Prüfung unabhängig von Websitebesuchen. Bei Websitebesuchen: erster Seitenaufruf pro Tag startet die Prüfung; ohne Besuch werden keine Erinnerungen versendet.')));
            $fields->insertBefore('AllowedMemberIDs', NumericField::create('MashaFeedlyHourlyRate', self::translate('CONFIG_HOURLY_RATE', 'Stundensatz für Kostenschätzungen (EUR)'))
                ->setAttribute('min', '0')->setAttribute('step', '0.01')
                ->setValue(MashaFeedlyConfigExtension::hourlyRate())
                ->setDescription(self::translate('CONFIG_HOURLY_RATE_DESCRIPTION', 'Aus der eingetragenen Dauer wird automatisch der Preis berechnet.')));
            $fields->insertBefore('AllowedMemberIDs', LiteralField::create('MashaFeedlyAnimationPreviews', $this->renderCompletionAnimationPreviews()));
            $fields->insertBefore('AllowedMemberIDs', LiteralField::create(
                'MashaFeedlyResetHeading',
                '<section class="masha-feedly-reset"><h3>'
                    . self::translate('RESET_TITLE', 'Masha:Feedly-Daten zurücksetzen')
                    . '</h3><p>'
                    . self::translate('RESET_DESCRIPTION', 'Löscht alle Einträge samt Kommentaren, Anhängen, Reaktionen, Verknüpfungen und Verlauf. Kategorien werden anschließend neu angelegt. Benutzer, Zugriffsrechte, Profile und Einstellungen bleiben erhalten.')
                    . '</p><p><strong>'
                    . self::translate('RESET_CONFIRMATION_INSTRUCTION', 'Diese Aktion kann nicht rückgängig gemacht werden. Tippe zur Bestätigung genau: RESET')
                    . '</strong></p></section>'
            ));
            $fields->insertBefore('AllowedMemberIDs', TextField::create(
                'ResetConfirmation',
                self::translate('RESET_CONFIRMATION_LABEL', 'Bestätigung')
            )->setAttribute('autocomplete', 'off')->setAttribute('spellcheck', 'false'));
        }
        foreach ($canManageSensitiveSettings && $authorizedMemberIDs ? Member::get()->filter('ID', $authorizedMemberIDs)->sort('Surname ASC, FirstName ASC') : [] as $authorizedMember) {
            $colorFieldName = 'MashaFeedlyMemberColor_' . (int)$authorizedMember->ID;
            $fields->push(CompositeField::create(
                LiteralField::create(
                    'MashaFeedlyMemberColorPalette_' . (int)$authorizedMember->ID,
                    MashaFeedlyMemberExtension::renderColorPalette(
                        $colorFieldName,
                        MashaFeedlyMemberExtension::normalizeColor((string)$authorizedMember->MashaFeedlyColor)
                    )
                ),
                HiddenField::create(
                    $colorFieldName,
                    null,
                    MashaFeedlyMemberExtension::normalizeColor((string)$authorizedMember->MashaFeedlyColor) ?? ''
                )
            )->setName('MashaFeedlyMemberColorGroup_' . (int)$authorizedMember->ID)
                ->setTitle(self::translate('CONFIG_AVATAR_COLOR', 'Avatarfarbe: {name}', ['name' => $authorizedMember->getName()])));
        }
        Requirements::css('kooperativeweb/masha-feedly:client/dist/css/masha-feedly-admin.css');
        Requirements::css('kooperativeweb/masha-feedly:client/dist/css/masha-feedly.css');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-colors.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/unicorn.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/rocket.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/hearts.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/arcade.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/retro.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/dino.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/check.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/glow.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/rings.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/confirmation.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/effects/runner.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-entries.js');
        $actions = FieldList::create(
            FormAction::create('saveConfiguration', self::translate('CONFIG_SAVE', 'Konfiguration speichern'))
                ->addExtraClass('btn-primary')
        );
        if ($canManageSensitiveSettings) {
            $actions->push(FormAction::create(
                'resetAllMashaFeedlyData',
                self::translate('RESET_BUTTON', 'Alle Masha:Feedly-Daten löschen')
            )->addExtraClass('btn-danger'));
        }
        $form = Form::create($this, 'EditForm', $fields, $actions)
            ->setHTMLID('Form_EditForm')
            ->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->addExtraClass('cms-edit-form cms-panel-padded center flexbox-area-grow');
        $form->setFormAction(Controller::join_links($this->getLinkForModelTab($this->modelTab), 'EditForm'));
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        return $form;
    }

    /** Rendert die sofort abspielbaren Vorschauen für verspielte Abschlussanimationen. */
    private function renderCompletionAnimationPreviews(): string
    {
        $unicornURL = (string)ModuleResourceLoader::resourceURL(
            'kooperativeweb/masha-feedly:client/dist/icons/masha-feedly-unicorn.svg'
        );
        $playfulMessage = $this->escapeBoardValue(self::translate('CONFIG_ANIMATION_PREVIEW_STARTED', 'Vorschau gestartet.'));
        $reducedMotionMessage = $this->escapeBoardValue(self::translate('CONFIG_ANIMATION_REDUCED_MOTION', 'Animationen sind für reduzierte Bewegung ausgeschaltet.'));
        $previewLabel = self::translate('CONFIG_ANIMATION_PREVIEW', 'Vorschau ansehen');
        return '<section class="masha-feedly-animation-previews" data-masha-feedly-animation-previews data-unicorn-url="'
            . $this->escapeBoardValue($unicornURL) . '">'
            . '<h3>' . self::translate('CONFIG_ANIMATIONS_TITLE', 'Abschlussanimationen ansehen') . '</h3>'
            . '<p>' . self::translate('CONFIG_ANIMATIONS_DESCRIPTION', 'Klicke auf eine Vorschau. Beim Abschließen wird je nach Theme zufällig eine passende Animation abgespielt.') . '</p>'
            . '<div class="masha-feedly-animation-previews__grid">'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="playful"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">✨🦄</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_UNICORN', 'Konfetti & Chaos-Einhorn') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="playful" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="playful"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">🚀</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_ROCKET', 'Raketenstart') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="rocket" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="playful"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">💖</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_HEARTS', 'Herzregen') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="hearts" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="playful"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">👾</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_ARCADE', '8-Bit-Level geschafft') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="arcade" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="playful"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">🪟</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_RETRO', 'Retro: Windows 80er/90er') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="retro" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="playful"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">🦖</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_DINO', 'Pixel-Dino frisst den Speichern-Button') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="dino" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="serious"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">✓</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_CHECK', 'Gezeichnetes Häkchen') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="check" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="serious"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">◌</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_GLOW', 'Sanfter Lichtimpuls') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="glow" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="serious"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">◎</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_RINGS', 'Ruhige Ringwellen') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="rings" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '<article class="masha-feedly-animation-preview" data-masha-feedly-animation-preview-card data-masha-feedly-theme="serious"><span class="masha-feedly-animation-preview__icon" aria-hidden="true">▱</span>'
            . '<div><strong>' . self::translate('CONFIG_ANIMATION_CONFIRMATION', 'Leise Statuskarte') . '</strong>'
            . '<button type="button" data-masha-feedly-animation-preview="confirmation" data-preview-message="' . $playfulMessage
            . '" data-reduced-motion-message="' . $reducedMotionMessage . '">' . $previewLabel . '</button></div></article>'
            . '</div><p class="masha-feedly-animation-previews__status" data-masha-feedly-animation-preview-status role="status" aria-live="polite"></p>'
            . '</section>';
    }

    /**
     * Verschiebt und sortiert einen Eintrag nach einer Drag-and-drop-Aktion.
     *
     * @param HTTPRequest $request POST-Daten mit Eintrag, Zielkategorie und Kartenreihenfolge.
     * @return HTTPResponse JSON-Antwort für das Board.
     */
    public function moveEntry(HTTPRequest $request): HTTPResponse
    {
        if (!$this->canView()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }
        if (!$request->isPOST()) {
            return $this->jsonResponse(['success' => false, 'message' => 'POST erforderlich.'], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Ungültiges Sicherheitstoken.'], 400);
        }

        $entry = MashaFeedlyEntry::get()->byID((int)$request->postVar('EntryID'));
        $category = MashaFeedlyCategory::get()->byID((int)$request->postVar('CategoryID'));
        if (!$entry || !$category) {
            return $this->jsonResponse(['success' => false, 'message' => 'Eintrag oder Kategorie nicht gefunden.'], 404);
        }
        if ((string)$entry->Category()->SystemKey === 'estimate_pending'
            && !in_array((string)$category->SystemKey, ['estimate_pending', 'estimate_approved'], true)
        ) {
            return $this->jsonResponse([
                'success' => false,
                'message' => i18n::_t(
                    'KW\\MashaFeedly\\Translations.ESTIMATE_APPROVAL_REQUIRED',
                    'Dieser Eintrag wartet auf die Freigabe der Kostenschätzung. Er kann nur in „Kostenschätzung freigegeben“ verschoben werden.'
                ),
            ], 409);
        }

        $entry->CategoryID = (int)$category->ID;
        $entry->write();
        $entryIDs = array_values(array_unique(array_filter(array_map(
            'intval',
            (array)$request->postVar('EntryIDs')
        ), static fn(int $id): bool => $id > 0)));
        if (!in_array((int)$entry->ID, $entryIDs, true)) {
            $entryIDs[] = (int)$entry->ID;
        }

        foreach ($entryIDs as $position => $entryID) {
            $orderedEntry = MashaFeedlyEntry::get()->byID($entryID);
            if (!$orderedEntry || (int)$orderedEntry->CategoryID !== (int)$category->ID) {
                continue;
            }
            $orderedEntry->Sort = ($position + 1) * 10;
            $orderedEntry->write();
        }

        $member = Security::getCurrentUser();
        $unreadCount = $member instanceof Member ? MashaFeedlyEntryRead::unreadCount($member) : 0;
        return $this->jsonResponse([
            'success' => true,
            'unreadCount' => $unreadCount,
            'feedbackCount' => self::feedbackCount(),
        ]);
    }

    /** Speichert die Reihenfolge der Kategorien im Admin-Board. */
    public function moveCategory(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!$this->canView() || !$member || !Permission::checkMember($member, 'ADMIN')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }
        if (!$request->isPOST()) {
            return $this->jsonResponse(['success' => false, 'message' => 'POST erforderlich.'], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Ungültiges Sicherheitstoken.'], 400);
        }

        $categoryIDs = array_values(array_unique(array_filter(array_map(
            'intval',
            (array)$request->postVar('CategoryIDs')
        ), static fn(int $id): bool => $id > 0)));
        if (!$categoryIDs) {
            return $this->jsonResponse(['success' => false, 'message' => 'Ungültige Kategorienreihenfolge.'], 400);
        }
        $categories = MashaFeedlyCategory::get()->byIDs($categoryIDs);
        if ($categories->count() !== count($categoryIDs)
            || $categories->count() !== MashaFeedlyCategory::get()->count()
        ) {
            return $this->jsonResponse(['success' => false, 'message' => 'Ungültige Kategorienreihenfolge.'], 400);
        }

        foreach ($categoryIDs as $position => $categoryID) {
            $category = MashaFeedlyCategory::get()->byID($categoryID);
            $category->Sort = ($position + 1) * 10;
            $category->write();
        }

        return $this->jsonResponse(['success' => true]);
    }

    /** Löscht ausschließlich leere, frei angelegte Kategorien aus dem Admin-Board. */
    public function deleteCategory(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!$this->canView() || !$member || !Permission::checkMember($member, 'ADMIN')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }
        if (!$request->isPOST()) {
            return $this->jsonResponse(['success' => false, 'message' => 'POST erforderlich.'], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Ungültiges Sicherheitstoken.'], 400);
        }

        $category = MashaFeedlyCategory::get()->byID((int)$request->postVar('CategoryID'));
        if (!$category) {
            return $this->jsonResponse(['success' => false, 'message' => 'Kategorie nicht gefunden.'], 404);
        }
        if ($category->Entries()->exists()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Nur leere Kategorien können gelöscht werden.',
            ], 409);
        }
        if (!$category->canDelete()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Diese erforderliche Kategorie kann nicht gelöscht werden.',
            ], 409);
        }

        $category->delete();
        return $this->jsonResponse(['success' => true]);
    }

    /** Legt eine neue benutzerdefinierte Kategorie direkt aus dem Board an. */
    public function createCategory(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!$this->canView() || !$member || !Permission::checkMember($member, 'ADMIN')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }
        if (!$request->isPOST()) {
            return $this->jsonResponse(['success' => false, 'message' => 'POST erforderlich.'], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Ungültiges Sicherheitstoken.'], 400);
        }

        $title = trim((string)$request->postVar('Title'));
        if ($title === '' || mb_strlen($title) > 120) {
            // Keep validation failures as JSON: SilverStripe replaces error-status
            // responses from this ModelAdmin action with its CMS error page.
            return $this->jsonResponse(['success' => false, 'message' => 'Bitte einen Kategorienamen mit höchstens 120 Zeichen eingeben.']);
        }

        $sortValues = array_map('intval', MashaFeedlyCategory::get()->column('Sort'));
        $category = MashaFeedlyCategory::create([
            'Title' => $title,
            'SystemKey' => '',
            'Sort' => ($sortValues ? max($sortValues) : 0) + 10,
            'IsClosed' => false,
        ]);
        $category->write();

        return $this->jsonResponse([
            'success' => true,
            'category' => [
                'id' => (int)$category->ID,
                'title' => (string)$category->Title,
                'sort' => (int)$category->Sort,
            ],
        ]);
    }

    /** Rendert alle Kategorien samt sortierbaren Eintragskarten für die Übersicht. */
    private function renderEntryBoard(): string
    {
        MashaFeedlyPriority::ensureDefaultPriorities();
        $member = Security::getCurrentUser();
        $canManageCategories = $member instanceof Member && Permission::checkMember($member, 'ADMIN');
        $adminTranslations = [];
        foreach ([
            'BOARD_SAVING' => 'Änderung wird gespeichert …',
            'BOARD_SAVE_ERROR' => 'Speichern fehlgeschlagen.',
            'BOARD_SAVE_SUCCESS' => 'Eintrag wurde gespeichert.',
            'BOARD_SAVE_FAILURE' => 'Eintrag konnte nicht gespeichert werden.',
            'BOARD_CATEGORY_SAVING' => 'Kategorien werden sortiert …',
            'BOARD_CATEGORY_SAVE_ERROR' => 'Sortieren fehlgeschlagen.',
            'BOARD_CATEGORY_SAVE_SUCCESS' => 'Kategorienreihenfolge gespeichert.',
            'BOARD_CATEGORY_SAVE_FAILURE' => 'Kategorienreihenfolge konnte nicht gespeichert werden.',
            'BOARD_CATEGORY_NAME_REQUIRED' => 'Bitte gib einen Kategorienamen ein.',
            'BOARD_CATEGORY_ADDING' => 'Kategorie wird angelegt …',
            'BOARD_CATEGORY_ADD_ERROR' => 'Kategorie konnte nicht angelegt werden.',
            'BOARD_CATEGORY_ADD_SUCCESS' => 'Kategorie wurde angelegt.',
            'BOARD_CATEGORY_DELETE' => 'Leere Kategorie löschen',
            'BOARD_CATEGORY_DELETE_CONFIRM' => 'Möchtest du diese leere Kategorie wirklich löschen?',
            'BOARD_CATEGORY_DELETING' => 'Kategorie wird gelöscht …',
            'BOARD_CATEGORY_DELETE_ERROR' => 'Kategorie konnte nicht gelöscht werden.',
            'BOARD_CATEGORY_DELETE_SUCCESS' => 'Leere Kategorie gelöscht.',
            'BOARD_CATEGORY_DELETE_FAILURE' => 'Kategorie konnte nicht gelöscht werden.',
            'BOARD_CATEGORY_DRAG_ARIA' => 'Kategorie sortieren',
            'BOARD_CATEGORY_DRAG_TITLE' => 'Kategorie zum Sortieren ziehen',
            'BOARD_ENTRY_SAVING' => 'Eintrag wird gespeichert …',
            'BOARD_ENTRY_SAVE_ERROR' => 'Eintrag konnte nicht gespeichert werden.',
            'BOARD_ENTRY_SAVE_SUCCESS' => 'Eintrag wurde gespeichert.',
            'BOARD_ENTRY_UPLOAD_HINT' => 'Bilder, PDFs oder ZIP-Dateien auswählen',
        ] as $key => $default) {
            $adminTranslations[$key] = self::translate($key, $default);
        }
        $html = '<section class="masha-feedly-board" data-masha-feedly-board data-admin-translations="'
            . $this->escapeBoardValue((string)json_encode($adminTranslations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP))
            . '" data-move-url="'
            . $this->escapeBoardValue(Controller::join_links(
                $this->getLinkForModelClass(MashaFeedlyEntry::class),
                'moveEntry'
            )) . '" data-security-id="'
            . $this->escapeBoardValue((string)SecurityToken::getSecurityID()) . '" data-move-category-url="'
            . $this->escapeBoardValue(Controller::join_links(
                $this->getLinkForModelClass(MashaFeedlyEntry::class),
                'moveCategory'
            )) . '" data-delete-category-url="'
            . $this->escapeBoardValue(Controller::join_links(
                $this->getLinkForModelClass(MashaFeedlyEntry::class),
                'deleteCategory'
            )) . '" data-create-category-url="'
            . $this->escapeBoardValue(Controller::join_links(
                $this->getLinkForModelClass(MashaFeedlyEntry::class),
                'createCategory'
            )) . '" data-create-entry-url="'
            . $this->escapeBoardValue(Controller::join_links(Director::baseURL(), '__masha-feedly', 'createEntry')) . '">';
        $html .= '<header class="masha-feedly-board__header"><div><h2>' . self::translate('BOARD_HEADER', 'Einträge nach Kategorie') . '</h2>'
            . '<p>' . self::translate('BOARD_HELP', 'Ziehe Einträge in andere Kategorien. Admins können Kategorien am Griff sortieren und eigene leere Kategorien löschen.') . '</p></div>'
            . '<div class="masha-feedly-board__header-actions">';
        //$html .= '<button type="button" class="btn btn-primary masha-feedly-board__action-button masha-feedly-board__action-button--entry" data-open-entry-form aria-haspopup="dialog">'
        //    . self::translate('BOARD_CREATE_ENTRY', 'Eintrag hinzufügen') . '</button>';
        if ($canManageCategories) {
            $html .= '<button type="button" class="btn btn-default masha-feedly-board__action-button masha-feedly-board__action-button--category" data-open-category-form aria-haspopup="dialog">'
                . self::translate('BOARD_CATEGORY_ADD', 'Kategorie hinzufügen') . '</button>';
        }
        $html .= '</div></header>';
        $canManageReporter = MashaFeedlyEntry::canManageReporter($member);
        if ($canManageReporter) {
            $html .= '<nav class="masha-feedly-board__views" role="tablist" aria-label="'
                . $this->escapeBoardValue(self::translate('REPORTER_TABS_LABEL', 'Masha:Feedly-Ansichten')) . '">'
                . '<button type="button" class="masha-feedly-board__view-tab is-active" role="tab" aria-selected="true" aria-controls="masha-feedly-entry-board-panel" data-admin-view-tab="entries">'
                . self::translate('ADMIN_ENTRIES', 'Einträge') . '</button>'
                . '<button type="button" class="masha-feedly-board__view-tab" role="tab" aria-selected="false" aria-controls="masha-feedly-reporter-panel" data-admin-view-tab="reporters">'
                . self::translate('REPORTER_TAB', 'Meldepersonen') . '</button></nav>';
        }
        $html .= '<div id="masha-feedly-entry-board-panel" data-admin-view-panel="entries" role="tabpanel">';
        if ($canManageCategories) {
            $html .= '<div class="masha-feedly-board__modal" data-category-modal hidden="hidden">'
                . '<section class="masha-feedly-board__dialog" role="dialog" aria-modal="true" aria-labelledby="masha-feedly-category-title">'
                . '<header class="masha-feedly-board__dialog-header"><div><span class="masha-feedly-board__eyebrow">'
                . self::translate('BOARD_CATEGORY_FORM_TITLE', 'Neue Kategorie') . '</span><h2 id="masha-feedly-category-title">'
                . self::translate('BOARD_CATEGORY_NAME', 'Name der Kategorie') . '</h2></div>'
                . '<button type="button" class="masha-feedly-board__dialog-close" data-close-category-modal aria-label="'
                . $this->escapeBoardValue(self::translate('BOARD_CATEGORY_ADD_CANCEL', 'Abbrechen')) . '">×</button></header>'
                . '<div class="masha-feedly-board__category-form" data-create-category-form role="form">'
                . '<label for="MashaFeedlyCategoryTitle">' . self::translate('BOARD_CATEGORY_NAME', 'Name der Kategorie') . '</label>'
                . '<input id="MashaFeedlyCategoryTitle" name="Title" maxlength="120" required data-category-title-input placeholder="'
                . $this->escapeBoardValue(self::translate('BOARD_CATEGORY_PLACEHOLDER', 'z. B. Barrierefreiheit')) . '">'
                . '<footer class="masha-feedly-board__dialog-actions"><button type="button" class="btn btn-default" data-cancel-category-form>'
                . self::translate('BOARD_CATEGORY_ADD_CANCEL', 'Abbrechen') . '</button><button type="button" class="btn btn-primary" data-submit-category-form>'
                . self::translate('BOARD_CATEGORY_ADD_SHORT', 'Kategorie hinzufügen') . '</button></footer></div></section></div>';
        }

        $categories = MashaFeedlyCategory::get()->sort('Sort ASC, Title ASC');
        $priorities = MashaFeedlyPriority::get()->sort('Sort ASC, Title ASC');
        $defaultCategoryID = (int)MashaFeedlyCategory::defaultCategory()->ID;
        $defaultPriorityID = (int)MashaFeedlyPriority::defaultPriority()->ID;
        $html .= '<div class="masha-feedly-board__modal kw-masha-feedly__modal kw-masha-feedly__create-modal" data-entry-modal hidden="hidden">'
            . '<section class="masha-feedly-board__dialog masha-feedly-board__entry-dialog kw-masha-feedly__dialog" role="dialog" aria-modal="true" aria-labelledby="masha-feedly-create-title">'
            . '<header class="masha-feedly-board__dialog-header kw-masha-feedly__dialog-header"><div><span class="masha-feedly-board__eyebrow kw-masha-feedly__eyebrow">'
            . self::translate('BOARD_CREATE_ENTRY_EYEBROW', 'NEUER EINTRAG') . '</span><h2 id="masha-feedly-create-title">'
            . self::translate('BOARD_CREATE_ENTRY_TITLE', 'Eintrag hinzufügen') . '</h2></div>'
            . '<button type="button" class="masha-feedly-board__dialog-close kw-masha-feedly__close" data-close-entry-modal aria-label="'
            . $this->escapeBoardValue(self::translate('BOARD_CATEGORY_ADD_CANCEL', 'Abbrechen')) . '">×</button></header>'
            . '<form class="masha-feedly-board__entry-form kw-masha-feedly__create-form" data-admin-create-entry-form data-create-url="'
            . $this->escapeBoardValue(Controller::join_links(Director::baseURL(), '__masha-feedly', 'createEntry'))
            . '" data-security-id="' . $this->escapeBoardValue((string)SecurityToken::getSecurityID()) . '">'
            . '<label for="MashaFeedlyAdminEntryContent">' . self::translate('BOARD_ENTRY_DESCRIPTION', 'Beschreibung')
            . '<textarea id="MashaFeedlyAdminEntryContent" name="Content" rows="5" maxlength="10000" required placeholder="'
            . $this->escapeBoardValue(self::translate('BOARD_ENTRY_PLACEHOLDER', 'Beschreibe den Fehler oder Hinweis …')) . '"></textarea></label>'
            . '<label class="masha-feedly-board__upload kw-masha-feedly__attachment-field">'
            . '<svg class="kw-masha-feedly__upload-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M22 13a1 1 0 0 0-1 1v4.213A2.79 2.79 0 0 1 18.213 21H5.787A2.79 2.79 0 0 1 3 18.213V14a1 1 0 0 0-2 0v4.213A4.792 4.792 0 0 0 5.787 23H18.213A4.792 4.792 0 0 0 23 18.213V14a1 1 0 0 0-1-1zM6.707 8.707 11 4.414V17a1 1 0 0 0 2 0V4.414l4.293 4.293a1 1 0 0 0 1.414-1.414l-6-6a1 1 0 0 0-1.414 0l-6 6a1 1 0 0 0 1.414 1.414z"/></svg>'
            . '<span class="kw-masha-feedly__attachment-title">' . self::translate('BOARD_ENTRY_ATTACHMENTS', 'Dateien anhängen') . '</span>'
            . '<span class="kw-masha-feedly__attachment-hint">' . self::translate('BOARD_ENTRY_UPLOAD_HINT', 'Bilder, PDFs oder ZIP-Dateien auswählen') . '</span>'
            . '<input type="file" name="Attachments[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,application/zip,.jpg,.jpeg,.png,.gif,.webp,.pdf,.zip">'
            . '<small>' . self::translate('BOARD_ENTRY_UPLOAD_LIMIT', 'Maximal 10 MB pro Datei, 20 MB insgesamt') . '</small></label>'
            . '<div class="masha-feedly-board__entry-fields kw-masha-feedly__form-grid"><label>' . self::translate('BOARD_ENTRY_STATUS', 'Status')
            . '<select name="CategoryID">';
        foreach ($categories as $category) {
            $html .= '<option value="' . (int)$category->ID . '"'
                . ((int)$category->ID === $defaultCategoryID ? ' selected' : '') . '>'
                . $this->escapeBoardValue((string)$category->Title) . '</option>';
        }
        $html .= '</select></label><label>' . self::translate('BOARD_ENTRY_PRIORITY', 'Priorität') . '<select name="PriorityID">';
        foreach ($priorities as $priority) {
            $html .= '<option value="' . (int)$priority->ID . '"'
                . ((int)$priority->ID === $defaultPriorityID ? ' selected' : '') . '>'
                . $this->escapeBoardValue((string)$priority->Title) . '</option>';
        }
        $html .= '</select></label><label>'
            . self::translate('FIELD_DUE_DATE', 'Fällig am') . '<input type="date" name="DueDate"></label></div>';
        $allowedMemberIDs = MashaFeedlyConfigExtension::memberIDs();
        if ($allowedMemberIDs) {
            $html .= '<fieldset class="masha-feedly-board__entry-assignees kw-masha-feedly__assignees"><legend>'
                . self::translate('BOARD_ENTRY_ASSIGNEES', 'Verantwortlich') . '</legend><div>';
            foreach (Member::get()->filter('ID', $allowedMemberIDs)->sort('Surname ASC, FirstName ASC') as $assignee) {
                $name = (string)$assignee->getName();
                $imageURL = $this->memberProfileImageURL($assignee);
                $html .= '<label class="kw-masha-feedly__assignee-choice" title="' . $this->escapeBoardValue($name) . '">'
                    . '<input type="checkbox" name="AssignedMemberIDs[]" value="' . (int)$assignee->ID . '">'
                    . '<span class="kw-masha-feedly__assignee-avatar" style="background-color: '
                    . $this->escapeBoardValue((string)$assignee->getMashaFeedlyDisplayColor()) . '" aria-label="' . $this->escapeBoardValue($name) . '">'
                    . ($imageURL !== '' ? '<img src="' . $this->escapeBoardValue($imageURL) . '" alt="" loading="lazy">' : $this->escapeBoardValue((string)$assignee->getMashaFeedlyInitials()))
                    . '</span><span class="kw-masha-feedly__assignee-name">' . $this->escapeBoardValue($name) . '</span></label>';
            }
            $html .= '</div></fieldset>';
        }
        $html .= '<p class="masha-feedly-board__dialog-status kw-masha-feedly__form-status" data-entry-form-status role="status" aria-live="polite"></p>'
            . '<footer class="masha-feedly-board__dialog-actions kw-masha-feedly__dialog-actions"><button type="button" class="btn btn-default kw-masha-feedly__secondary" data-close-entry-modal>'
            . self::translate('BOARD_CATEGORY_ADD_CANCEL', 'Abbrechen') . '</button><button type="submit" class="btn btn-primary kw-masha-feedly__submit">'
            . self::translate('BOARD_CREATE_ENTRY', 'Eintrag hinzufügen') . '</button></footer></form></section></div>';
        $unreadEntryIDs = $member instanceof Member
            ? MashaFeedlyEntryRead::unreadEntryIDs($member)
            : [];
        $assignedMemberID = (string)$this->getRequest()->getVar('assignedMemberID');
        $allowedMemberIDs = MashaFeedlyConfigExtension::memberIDs();
        if ($assignedMemberID !== '' && $assignedMemberID !== '0'
            && !in_array((int)$assignedMemberID, $allowedMemberIDs, true)
        ) {
            $assignedMemberID = '';
        }

        $html .= '<div class="masha-feedly-board__filters"><label for="MashaFeedlyAssignedFilter">' . self::translate('BOARD_FILTER_LABEL', 'Einträge anzeigen') . '</label>'
            . '<select id="MashaFeedlyAssignedFilter" data-masha-feedly-assignee-filter>'
            . '<option value=""' . ($assignedMemberID === '' ? ' selected' : '') . '>' . self::translate('BOARD_FILTER_ALL', 'Alle Einträge') . '</option>'
            . '<option value="0"' . ($assignedMemberID === '0' ? ' selected' : '') . '>' . self::translate('BOARD_FILTER_UNASSIGNED', 'Nicht zugeordnet') . '</option>';
        foreach ($allowedMemberIDs ? Member::get()->filter('ID', $allowedMemberIDs)->sort('Surname ASC, FirstName ASC') : [] as $authorizedMember) {
            $memberID = (string)$authorizedMember->ID;
            $html .= '<option value="' . (int)$memberID . '"'
                . ($assignedMemberID === $memberID ? ' selected' : '') . '>'
                . $this->escapeBoardValue((string)$authorizedMember->getName()) . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="masha-feedly-board__columns">';

        foreach (MashaFeedlyCategory::get()->sort('Sort ASC, Title ASC') as $category) {
            $entries = MashaFeedlyEntry::get()
                ->filter('CategoryID', (int)$category->ID)
                ->sort('Sort ASC, EntryDate DESC, ID DESC');
            $html .= '<section class="masha-feedly-board__column" data-category-id="' . (int)$category->ID . '">';
            $html .= '<header class="masha-feedly-board__column-header">';
            if ($canManageCategories) {
                $html .= '<button type="button" class="masha-feedly-board__category-drag-handle" draggable="true"'
                    . ' title="' . $this->escapeBoardValue(self::translate('BOARD_CATEGORY_DRAG_TITLE', 'Kategorie zum Sortieren ziehen'))
                    . '" aria-label="' . $this->escapeBoardValue(self::translate('BOARD_CATEGORY_DRAG_ARIA', 'Kategorie sortieren')) . '">⠿</button>';
            }
            $html .= '<h3>' . $this->escapeBoardValue((string)$category->Title)
                . '</h3><span data-category-entry-count>' . $entries->count() . '</span>';
            if ($canManageCategories && !$entries->exists() && $category->canDelete()) {
                $deleteLabel = self::translate('BOARD_CATEGORY_DELETE', 'Leere Kategorie löschen');
                $html .= '<button type="button" class="masha-feedly-board__category-delete" data-delete-category'
                    . ' aria-label="' . $this->escapeBoardValue($deleteLabel) . '" title="'
                    . $this->escapeBoardValue($deleteLabel) . '">×</button>';
            }
            $html .= '</header>';
            $html .= '<div class="masha-feedly-board__list" data-category-id="' . (int)$category->ID . '">';
            foreach ($entries as $entry) {
                $assignedEntryMemberIDs = array_map('intval', $entry->AssignedMembers()->column('ID'));
                $isUnread = in_array((int)$entry->ID, $unreadEntryIDs, true);
                $isPersonallyAssigned = $member instanceof Member
                    && (in_array((int)$member->ID, $assignedEntryMemberIDs, true)
                        || (int)$member->ID === $entry->creatorMemberID());
                $html .= '<article class="masha-feedly-board__card" data-entry-id="'
                    . (int)$entry->ID . '" data-entry-unread="'
                    . ($isUnread ? 'true' : 'false')
                    . '" data-assigned-member-ids="' . $this->escapeBoardValue(implode(',', $assignedEntryMemberIDs))
                    . '" data-has-assignees="' . ($assignedEntryMemberIDs ? 'true' : 'false')
                    . '"><div class="masha-feedly-board__card-topline"><span class="masha-feedly-board__entry-number">#'
                    . (int)$entry->ID . '</span>';
                $priority = $entry->Priority();
                if ($priority->exists()) {
                    $html .= '<span class="masha-feedly-board__priority" style="--masha-feedly-priority-color:'
                        . $this->escapeBoardValue((string)$priority->Color) . '" aria-label="'
                        . $this->escapeBoardValue((string)$priority->Title) . '" title="'
                        . $this->escapeBoardValue((string)$priority->Title) . '">'
                        . $priority->getIconSVG() . '</span>';
                }
                if ($isUnread) {
                    $unreadLabel = $isPersonallyAssigned
                        ? self::translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'BOARD_NEW_FOR_SIE' : 'BOARD_NEW_FOR_DU', 'Neue Aktivität für dich')
                        : self::translate('BOARD_NEW_FOR_ALL', 'Neue Aktivität für alle');
                    $html .= '<span class="masha-feedly-board__new-indicator" aria-label="' . $unreadLabel . '" title="' . $unreadLabel . '">'
                        . '<svg class="masha-feedly-board__new-icon" viewBox="0 0 177800 177800" aria-hidden="true" focusable="false">'
                        . '<path fill="#48b02c" d="m91116 1296 7525 13368c395 700 1026 1138 1819 1263 793 124 1529-96 2119-640l11286-10389c695-639 1611-839 2508-546 896 291 1520 992 1706 1916l3026 15039c159 787 623 1400 1339 1763 715 365 1482 381 2212 47l13945-6391c859-394 1791-302 2554 255 763 554 1140 1413 1030 2348l-1769 15238c-93 799 159 1524 728 2092 568 567 1293 820 2091 726l15237-1769c937-108 1796 269 2350 1032s647 1695 255 2553l-6392 13944c-335 730-318 1499 46 2214 365 715 977 1180 1763 1338l15040 3026c925 186 1624 809 1916 1707 291 897 91 1813-547 2506l-10388 11287c-544 592-766 1326-642 2120 126 793 565 1424 1265 1817l13368 7525c821 463 1294 1273 1294 2217 0 943-473 1752-1294 2214l-13368 7525c-700 395-1140 1024-1265 1819-124 793 98 1529 642 2119l10388 11286c638 694 838 1611 545 2508-291 896-991 1520-1914 1706l-15040 3026c-788 159-1399 621-1764 1338-365 716-380 1483-45 2213l6391 13945c393 857 300 1791-255 2554-554 761-1413 1138-2349 1030l-15237-1769c-798-93-1524 159-2092 727s-820 1294-727 2092l1769 15237c108 936-269 1795-1032 2349-761 554-1695 647-2552 255l-13945-6391c-730-335-1499-318-2213 45-717 365-1181 978-1338 1766l-3026 15038c-186 923-811 1623-1706 1914-897 293-1814 93-2508-545l-11286-10388c-590-544-1326-766-2119-642-795 126-1424 565-1819 1266l-7525 13367c-462 821-1271 1292-2214 1294-944 0-1754-473-2217-1294l-7525-13367c-393-700-1024-1140-1817-1264-794-126-1528 96-2120 641l-11285 10387c-694 638-1610 838-2506 547-898-292-1523-991-1709-1916l-3026-15040c-158-786-623-1398-1338-1763-715-364-1484-381-2214-46l-13944 6391c-858 393-1790 300-2553-254s-1140-1413-1032-2350l1769-15237c94-798-160-1523-726-2091-568-569-1293-821-2092-728l-15238 1769c-937 110-1794-269-2350-1030-554-764-647-1698-253-2556l6391-13943c334-730 316-1497-47-2213-365-717-976-1179-1764-1338l-15038-3026c-925-186-1625-810-1916-1706-293-898-92-1816 548-2509l10387-11285c544-590 764-1326 640-2119-125-795-565-1424-1265-1819l-13366-7525c-821-462-1294-1271-1296-2214 0-944 473-1754 1296-2217l13366-7525c700-393 1139-1024 1265-1818 124-793-96-1527-640-2119l-10389-11285c-639-694-839-1610-548-2508 292-898 993-1521 1918-1707l15039-3026c787-159 1398-623 1763-1338 363-716 381-1484 47-2214l-6391-13944c-394-858-302-1792 253-2554 554-763 1413-1140 2350-1031l15238 1769c797 94 1522-160 2090-728s822-1293 728-2090l-1769-15238c-109-937 268-1796 1031-2350 762-555 1696-647 2554-253l13944 6391c730 334 1498 316 2212-47 717-365 1181-976 1340-1763l3026-15039c186-925 809-1626 1706-1918 898-291 1814-90 2507 548l11287 10389c592 544 1326 764 2119 640 793-126 1425-563 1818-1263l7525-13368c463-823 1272-1296 2217-1296 943 2 1752 475 2214 1296z"/>'
                        . '<path fill="#fff" d="m43403 112710-5400-30623 6018-1062 16142 18239-3606-20449 5747-1014 5399 30625-6211 1095-15864-17796 3521 19972zm32326-5700-5400-30623 22702-4004 915 5184-16522 2913 1197 6788 15373-2711 910 5155-15373 2711 1469 8334 17105-3015 910 5163zm34364-6059-12709-29335 6325-1116 8330 20220 1888-22020 7352-1298 9139 20444 927-22219 6224-1098-2035 31936-6561 1157-10133-21820-2045 23967z"/>'
                        . '</svg></span>';
                }
                $assignedMembers = $entry->AssignedMembers()->sort('Surname ASC, FirstName ASC');
                $assigneesHTML = '';
                if ($assignedMembers->exists()) {
                    $assigneesHTML .= '<div class="masha-feedly-board__assignees" aria-label="' . $this->escapeBoardValue(self::translate('BOARD_ASSIGNED_MEMBERS', 'Zugeordnete Mitglieder')) . '">';
                    foreach ($assignedMembers as $assignedMember) {
                        $name = (string)$assignedMember->getName();
                        $imageURL = $this->memberProfileImageURL($assignedMember);
                        $color = $assignedMember->getMashaFeedlyDisplayColor();
                        $assigneesHTML .= '<span class="masha-feedly-board__assignee" style="--masha-feedly-member-color:'
                            . $this->escapeBoardValue($color) . '" aria-label="'
                            . $this->escapeBoardValue($name) . '">';
                        if ($imageURL !== '') {
                            $assigneesHTML .= '<img class="masha-feedly-board__assignee-image" src="'
                                . $this->escapeBoardValue($imageURL) . '" alt="" loading="lazy">';
                        } else {
                            $assigneesHTML .= '<span class="masha-feedly-board__assignee-icon" aria-hidden="true">'
                                . $this->escapeBoardValue($assignedMember->getMashaFeedlyInitials()) . '</span>';
                        }
                        $assigneesHTML .= '</span>';
                    }
                    $assigneesHTML .= '</div>';
                }
                $html .= '<span class="masha-feedly-board__drag-handle" draggable="true"'
                    . ' title="' . $this->escapeBoardValue(self::translate('BOARD_DRAG_TITLE', 'Zum Sortieren ziehen')) . '" aria-label="' . $this->escapeBoardValue(self::translate('BOARD_DRAG_ARIA', 'Eintrag sortieren')) . '">⠿</span>'
                    . '</div><div class="masha-feedly-board__card-heading">';
                $frontendEntryURL = $this->frontendEntryURL($entry);
                if ($frontendEntryURL !== '') {
                    $html .= '<a href="' . $this->escapeBoardValue($frontendEntryURL)
                        . '" target="_blank" rel="noopener noreferrer">';
                } else {
                    $html .= '<span class="masha-feedly-board__entry-title">';
                }
                $html .= $this->escapeBoardValue($entry->getTitle() ?: self::translate('BOARD_ENTRY_NO_DESCRIPTION', 'Eintrag ohne Beschreibung'));
                $html .= $frontendEntryURL !== '' ? '</a></div>' : '</span></div>';
                $html .= '<time class="masha-feedly-board__card-date" datetime="'
                    . $this->escapeBoardValue((string)$entry->EntryDate) . '">'
                    . $this->escapeBoardValue((string)$entry->dbObject('EntryDate')->Nice()) . '</time>';
                if ($entry->DueDate) {
                    $html .= '<time class="masha-feedly-board__due-date" datetime="'
                        . $this->escapeBoardValue((string)$entry->DueDate) . '">'
                        . $this->escapeBoardValue(self::translate('BOARD_DUE_DATE', 'Fällig am {date}', [
                            'date' => (string)$entry->dbObject('DueDate')->Nice(),
                        ])) . '</time>';
                }
                $html .= $assigneesHTML;
                $html .= '</article>';
            }
            $html .= '</div></section>';
        }

        $html .= '</div></div>';
        if ($canManageReporter) {
            $html .= $this->renderReporterManager();
        }
        $html .= '<p class="masha-feedly-board__status" aria-live="polite"></p></section>';
        return $html;
    }

    /** Rendert die geschützte Übersicht zum Ändern der angezeigten Meldeperson. */
    private function renderReporterManager(): string
    {
        if (!MashaFeedlyEntry::canManageReporter()) {
            return '';
        }
        $reporters = Member::get()->sort('Surname ASC, FirstName ASC');
        $entries = MashaFeedlyEntry::get()->sort('EntryDate DESC, ID DESC');
        $entryIDs = array_map('intval', $entries->column('ID'));
        $creatorNames = [];
        $creatorMemberIDs = [];
        $creatorIDsByEntry = [];
        $creatorNamesByID = [];
        if ($entryIDs) {
            foreach (MashaFeedlyEntryHistory::get()->filter('EntryID', $entryIDs)
                ->filter('ChangeType', 'created')->sort('Created ASC, ID ASC') as $creationEvent) {
                $entryID = (int)$creationEvent->EntryID;
                if (isset($creatorNames[$entryID])) {
                    continue;
                }
                $creatorNames[$entryID] = (string)$creationEvent->ActorName;
                if ((int)$creationEvent->ActorMemberID > 0) {
                    $creatorMemberIDs[(int)$creationEvent->ActorMemberID] = true;
                    $creatorIDsByEntry[$entryID] = (int)$creationEvent->ActorMemberID;
                }
            }
            if ($creatorMemberIDs) {
                foreach (Member::get()->byIDs(array_keys($creatorMemberIDs)) as $creator) {
                    $creatorNamesByID[(int)$creator->ID] = (string)$creator->getName();
                }
            }
            foreach ($creatorIDsByEntry as $entryID => $creatorID) {
                if (isset($creatorNamesByID[$creatorID])) {
                    $creatorNames[$entryID] = $creatorNamesByID[$creatorID];
                }
            }
        }
        $html = '<section id="masha-feedly-reporter-panel" class="masha-feedly-reporter-manager" data-admin-view-panel="reporters" role="tabpanel" hidden>'
            . '<header class="masha-feedly-reporter-manager__header"><div><span class="masha-feedly-board__eyebrow">'
            . self::translate('REPORTER_TAB_EYEBROW', 'VERWALTUNG') . '</span><h2>'
            . self::translate('REPORTER_TAB_TITLE', 'Angezeigte Meldeperson ändern') . '</h2><p>'
            . self::translate('REPORTER_TAB_DESCRIPTION', 'Wähle, wessen Name bei einem Eintrag angezeigt wird. Der technische Ersteller bleibt im Verlauf erhalten.')
            . '</p></div><label class="masha-feedly-reporter-manager__search">'
            . self::translate('REPORTER_SEARCH', 'Einträge durchsuchen')
            . '<input type="search" data-reporter-search placeholder="'
            . $this->escapeBoardValue(self::translate('REPORTER_SEARCH_PLACEHOLDER', 'Titel oder ID eingeben …')) . '"></label></header>'
            . '<div class="masha-feedly-reporter-manager__table-wrap"><table class="masha-feedly-reporter-manager__table"><thead><tr><th>'
            . self::translate('REPORTER_ENTRY', 'Eintrag') . '</th><th>' . self::translate('REPORTER_CREATOR', 'Technisch erstellt von')
            . '</th><th>' . self::translate('REPORTER_DISPLAYED', 'Angezeigte Meldeperson') . '</th></tr></thead><tbody>';
        foreach ($entries as $entry) {
            $title = $entry->getTitle() ?: self::translate('BOARD_ENTRY_NO_DESCRIPTION', 'Eintrag ohne Beschreibung');
            $creatorName = $creatorNames[(int)$entry->ID] ?? '';
            $html .= '<tr data-reporter-row data-search="' . $this->escapeBoardValue(mb_strtolower('#' . $entry->ID . ' ' . $title))
                . '"><td><strong>#' . (int)$entry->ID . '</strong><span>' . $this->escapeBoardValue($title) . '</span></td><td>'
                . $this->escapeBoardValue($creatorName ?: self::translate('REPORTER_UNKNOWN_CREATOR', 'Unbekannt')) . '</td><td>'
                . '<form data-reporter-form data-save-url="' . $this->escapeBoardValue(Controller::join_links(
                    $this->getLinkForModelClass(MashaFeedlyEntry::class), 'saveReporter'
                )) . '" data-security-id="' . $this->escapeBoardValue((string)SecurityToken::getSecurityID()) . '" data-saving-message="'
                . $this->escapeBoardValue(self::translate('REPORTER_SAVING', 'Meldeperson wird gespeichert …')) . '" data-error-message="'
                . $this->escapeBoardValue(self::translate('REPORTER_SAVE_ERROR', 'Meldeperson konnte nicht gespeichert werden.')) . '"><input type="hidden" name="EntryID" value="'
                . (int)$entry->ID . '"><select name="ReportedByID" aria-label="'
                . $this->escapeBoardValue(self::translate('REPORTER_SELECT_ARIA', 'Angezeigte Meldeperson für Eintrag {id}', ['id' => (string)$entry->ID]))
                . '"><option value="0"' . ((int)$entry->ReportedByID === 0 ? ' selected' : '') . '>'
                . self::translate('REPORTER_USE_CREATOR', 'Technischen Ersteller verwenden') . '</option>';
            foreach ($reporters as $reporter) {
                $html .= '<option value="' . (int)$reporter->ID . '"'
                    . ((int)$entry->ReportedByID === (int)$reporter->ID ? ' selected' : '') . '>'
                    . $this->escapeBoardValue((string)$reporter->getName()) . '</option>';
            }
            $html .= '</select><button type="submit" class="masha-feedly-board__view-tab masha-feedly-reporter-manager__save">'
                . self::translate('REPORTER_SAVE', 'Speichern') . '</button><span class="masha-feedly-reporter-manager__status" data-reporter-status role="status" aria-live="polite"></span></form></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    /** Speichert eine Meldeperson nur für das explizit freigegebene CMS-Admin-Konto. */
    public function saveReporter(HTTPRequest $request): HTTPResponse
    {
        if (!MashaFeedlyEntry::canManageReporter()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }
        if (!$request->isPOST()) {
            return $this->jsonResponse(['success' => false, 'message' => 'POST erforderlich.'], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Ungültiges Sicherheitstoken.'], 400);
        }
        $entry = MashaFeedlyEntry::get()->byID((int)$request->postVar('EntryID'));
        $reporterID = (int)$request->postVar('ReportedByID');
        $reporter = $reporterID > 0 ? Member::get()->byID($reporterID) : null;
        if (!$entry || ($reporterID > 0 && !$reporter)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Eintrag oder Meldeperson nicht gefunden.'], 404);
        }
        $entry->ReportedByID = $reporterID;
        $entry->write();
        return $this->jsonResponse([
            'success' => true,
            'message' => self::translate('REPORTER_SAVED', 'Meldeperson gespeichert.'),
            'reportedByName' => $entry->reportedByName(),
        ]);
    }

    /** Setzt alle Masha:Feedly-Inhalte nur für das konfigurierte Superadmin-Konto zurück. */
    public function resetAllMashaFeedlyData(array $data, Form $form)
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyEntry::canManageReporter($member)) {
            return Security::permissionFailure($this);
        }
        if ((string)($data['ResetConfirmation'] ?? '') !== 'RESET') {
            $form->sessionMessage(self::translate('RESET_CONFIRMATION_ERROR', 'Bitte tippe genau „RESET“, um den Reset zu bestätigen.'), 'bad');
            return $this->getResponseNegotiator()->respond($this->getRequest(), [
                'CurrentForm' => fn(): string => $this->getEditForm()->forTemplate(),
            ]);
        }

        $counts = MashaFeedlyResetService::resetAllData();
        $form->sessionMessage(self::translate('RESET_SUCCESS', 'Zurückgesetzt: {entries} Einträge, {comments} Kommentare, {attachments} Anhänge gelöscht; Kategorien neu angelegt.', [
            'entries' => (string)$counts['entries'],
            'comments' => (string)$counts['comments'],
            'attachments' => (string)$counts['attachments'],
        ]), 'good');
        return $this->getResponseNegotiator()->respond($this->getRequest(), [
            'CurrentForm' => fn(): string => $this->getEditForm()->forTemplate(),
        ]);
    }

    /**
     * Escapiert Text für die sichere Ausgabe in HTML-Attributen und Elementen.
     *
     * @param string $value Nicht vertrauenswürdiger Text.
     * @return string Sicher escapiertes HTML.
     */
    private function escapeBoardValue(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Erstellt einen Direktlink zur Originalseite, der den konkreten Eintrag öffnet. */
    private function frontendEntryURL(MashaFeedlyEntry $entry): string
    {
        $pageURL = trim((string)$entry->PageURL);
        $parts = parse_url($pageURL);
        if (!is_array($parts)
            || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return '';
        }

        $fragment = '';
        $fragmentPosition = strpos($pageURL, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($pageURL, $fragmentPosition);
            $pageURL = substr($pageURL, 0, $fragmentPosition);
        }
        $separator = str_contains($pageURL, '?') ? '&' : '?';
        return $pageURL . $separator . 'masha-feedly-entry=' . (int)$entry->ID . $fragment;
    }

    /** Liefert das Profilbild eines Mitglieds, falls ein passendes Bildfeld vorhanden ist. */
    private function memberProfileImageURL(Member $member): string
    {
        foreach (['MashaFeedlyIconImage', 'ProfileImage', 'Photo', 'Portrait'] as $relationName) {
            if (!$member->hasMethod($relationName)) {
                continue;
            }
            $image = $member->{$relationName}();
            if ($image instanceof Image && $image->exists()) {
                return (string)$image->getURL();
            }
        }
        return '';
    }

    /**
     * Liefert eine JSON-Antwort für die Board-Aktion.
     *
     * @param array<string, mixed> $data Antwortdaten.
     * @param int $status HTTP-Statuscode.
     * @return HTTPResponse JSON-Antwort.
     */
    private function jsonResponse(array $data, int $status = 200): HTTPResponse
    {
        $response = HTTPResponse::create(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status);
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        return $response;
    }

    /**
     * Speichert Zugriffsfreigaben, Avatarfarben und die geschützten Bildrechte.
     *
     * Fehlt der Listenwert, wurde die Auswahl absichtlich geleert.
     *
     * @param array<string, mixed> $data Übermittelte Formularwerte.
     * @param Form $form Aktuelles CMS-Formular.
     * @return mixed Silverstripe-Antwort nach dem Speichern.
     */
    public function saveConfiguration(array $data, Form $form)
    {
        $member = Security::getCurrentUser();
        if (!$member || !Permission::checkMember($member, 'ADMIN')) {
            return Security::permissionFailure($this);
        }

        $memberIDs = MashaFeedlyConfigExtension::normalizeMemberIDs($data['AllowedMemberIDs'] ?? []);
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode($memberIDs);
        $siteConfig->MashaFeedlyAddress = strtolower((string)($data['MashaFeedlyAddress'] ?? 'du')) === 'sie' ? 'sie' : 'du';
        $fontSize = strtolower((string)($data['MashaFeedlyFontSize'] ?? 'small'));
        $siteConfig->MashaFeedlyFontSize = in_array($fontSize, ['small', 'medium', 'large'], true) ? $fontSize : 'small';
        $theme = strtolower((string)($data['MashaFeedlyTheme'] ?? 'playful'));
        $siteConfig->MashaFeedlyTheme = in_array($theme, ['playful', 'serious'], true) ? $theme : 'playful';
        $canManageSensitiveSettings = MashaFeedlyEntry::canManageReporter($member);
        if ($canManageSensitiveSettings) {
            $reminderMode = strtolower((string)($data['MashaFeedlyDueDateReminderMode'] ?? MashaFeedlyConfigExtension::dueDateReminderMode()));
            $siteConfig->MashaFeedlyDueDateReminderMode = in_array($reminderMode, ['cron', 'visitor'], true) ? $reminderMode : 'cron';
            $hourlyRate = str_replace(',', '.', trim((string)($data['MashaFeedlyHourlyRate'] ?? MashaFeedlyConfigExtension::hourlyRate())));
            $siteConfig->MashaFeedlyHourlyRate = is_numeric($hourlyRate) && (float)$hourlyRate >= 0
                ? number_format(min((float)$hourlyRate, 99999999.99), 2, '.', '')
                : 0;
        }
        $siteConfig->write();

        $usedColors = [];
        foreach ($memberIDs as $authorizedMemberID) {
            $authorizedMember = Member::get()->byID($authorizedMemberID);
            if (!$authorizedMember) {
                continue;
            }

            if ($canManageSensitiveSettings) {
                $colorField = 'MashaFeedlyMemberColor_' . $authorizedMemberID;
                $colorValue = array_key_exists($colorField, $data)
                    ? (string)$data[$colorField]
                    : (string)$authorizedMember->MashaFeedlyColor;
                $color = MashaFeedlyMemberExtension::normalizeColor($colorValue)
                    ?? MashaFeedlyMemberExtension::nextAvailableColor($usedColors);
                $authorizedMember->MashaFeedlyColor = $color;

                $authorizedMember->write();
                $authorizedMember->protectMashaFeedlyIconImage();
                $usedColors[] = $color;
            }
        }

        if ($canManageSensitiveSettings) {
            $folder = MashaFeedlyMemberExtension::protectedIconFolder();
            foreach (Image::get()->filter('ParentID', (int)$folder->ID) as $profileImage) {
                $profileImage->CanViewType = \SilverStripe\Security\InheritedPermissions::ONLY_THESE_MEMBERS;
                $profileImage->ViewerMembers()->setByIDList($memberIDs);
                $profileImage->write();
                $profileImage->publishSingle();
                $profileImage->protectFile();
            }
        }

        $form->sessionMessage(self::translate('CONFIG_SAVED', 'Die Masha-Feedly-Konfiguration wurde gespeichert.'), 'good');
        return $this->getResponseNegotiator()->respond($this->getRequest(), [
            'CurrentForm' => fn(): string => $this->getEditForm()->forTemplate(),
        ]);
    }

    /**
     * Blendet die Konfiguration für freigegebene Nicht-Administratoren aus.
     *
     * @return array<string, array<string, string>> Sichtbare ModelAdmin-Tabs.
     */
    public function getManagedModels(): array
    {
        $models = parent::getManagedModels();
        $titles = [
            MashaFeedlyEntry::class => ['ADMIN_ENTRIES', 'Einträge'],
            MashaFeedlyCategory::class => ['ADMIN_CATEGORIES', 'Kategorien'],
            MashaFeedlyComment::class => ['ADMIN_COMMENTS', 'Kommentare'],
            SiteConfig::class => ['ADMIN_CONFIGURATION', 'Konfiguration'],
        ];
        foreach ($titles as $class => [$key, $fallback]) {
            if (isset($models[$class])) {
                $models[$class]['title'] = self::translate($key, $fallback);
            }
        }
        $member = Security::getCurrentUser();
        if (!$member || !Permission::checkMember($member, 'ADMIN')) {
            unset($models[SiteConfig::class]);
        }
        return $models;
    }

    /** Liefert übersetzte Modultexte und setzt optionale Platzhalter ein. */
    private static function translate(string $key, string $fallback, array $values = []): string
    {
        return i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $fallback, $values);
    }
}
