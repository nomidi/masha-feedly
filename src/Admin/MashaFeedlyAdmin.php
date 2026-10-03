<?php

namespace KW\MashaFeedly\Admin;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Extension\MashaFeedlyMemberExtension;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyPriority;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use SilverStripe\Assets\Image;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ListboxField;
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
    ];

    /** Ergänzt die Menübezeichnung um die ungelesene Anzahl des aktuellen Mitglieds. */
    public static function menu_title($class = null, $localise = true)
    {
        $title = parent::menu_title($class, $localise);
        if ($class !== null) {
            return $title;
        }

        $member = Security::getCurrentUser();
        if (!$member instanceof Member || !MashaFeedlyConfigExtension::canUse($member)) {
            return $title;
        }

        $unreadCounts = MashaFeedlyEntryRead::unreadCounts($member);
        $totalUnread = $unreadCounts['general'] + $unreadCounts['personal'];
        return $totalUnread > 0
            ? $title . ' (' . $unreadCounts['general'] . '/' . $unreadCounts['personal'] . ')'
            : $title;
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
        return $member instanceof Member && MashaFeedlyConfigExtension::canUse($member);
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
                Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-admin.js');
            }
            return $form;
        }

        $members = [];
        $authorizedMemberIDs = MashaFeedlyConfigExtension::memberIDs();
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
                ->setDescription(self::translate('CONFIG_ALLOWED_MEMBERS_DESCRIPTION', 'Wähle alle Benutzer aus, die Einträge und Kommentare verwalten dürfen. Administratoren behalten immer Zugriff.'))
        );
        foreach ($authorizedMemberIDs ? Member::get()->filter('ID', $authorizedMemberIDs)->sort('Surname ASC, FirstName ASC') : [] as $authorizedMember) {
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
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-colors.js');
        $actions = FieldList::create(
            FormAction::create('saveConfiguration', self::translate('CONFIG_SAVE', 'Konfiguration speichern'))
                ->addExtraClass('btn-primary')
        );
        $form = Form::create($this, 'EditForm', $fields, $actions)
            ->setHTMLID('Form_EditForm')
            ->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->addExtraClass('cms-edit-form cms-panel-padded center flexbox-area-grow');
        $form->setFormAction(Controller::join_links($this->getLinkForModelTab($this->modelTab), 'EditForm'));
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        return $form;
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
        $unreadCounts = $member instanceof Member
            ? MashaFeedlyEntryRead::unreadCounts($member)
            : ['general' => 0, 'personal' => 0];
        return $this->jsonResponse([
            'success' => true,
            'unreadGeneralCount' => $unreadCounts['general'],
            'unreadPersonalCount' => $unreadCounts['personal'],
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
            'MENU_GENERAL_UNREAD' => '{count} neue Einträge für alle',
            'MENU_PERSONAL_UNREAD_DU' => '{count} neue Einträge für dich',
            'MENU_PERSONAL_UNREAD_SIE' => '{count} neue Einträge für Sie',
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
        $html .= '<button type="button" class="btn btn-primary masha-feedly-board__action-button masha-feedly-board__action-button--entry" data-open-entry-form aria-haspopup="dialog">'
            . self::translate('BOARD_CREATE_ENTRY', 'Eintrag hinzufügen') . '</button>';
        if ($canManageCategories) {
            $html .= '<button type="button" class="btn btn-default masha-feedly-board__action-button masha-feedly-board__action-button--category" data-open-category-form aria-haspopup="dialog">'
                . self::translate('BOARD_CATEGORY_ADD', 'Kategorie hinzufügen') . '</button>';
        }
        $html .= '</div></header>';
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
        $html .= '<div class="masha-feedly-board__modal" data-entry-modal hidden="hidden">'
            . '<section class="masha-feedly-board__dialog masha-feedly-board__entry-dialog" role="dialog" aria-modal="true" aria-labelledby="masha-feedly-create-title">'
            . '<header class="masha-feedly-board__dialog-header"><div><span class="masha-feedly-board__eyebrow">'
            . self::translate('BOARD_CREATE_ENTRY_EYEBROW', 'NEUER EINTRAG') . '</span><h2 id="masha-feedly-create-title">'
            . self::translate('BOARD_CREATE_ENTRY_TITLE', 'Eintrag hinzufügen') . '</h2></div>'
            . '<button type="button" class="masha-feedly-board__dialog-close" data-close-entry-modal aria-label="'
            . $this->escapeBoardValue(self::translate('BOARD_CATEGORY_ADD_CANCEL', 'Abbrechen')) . '">×</button></header>'
            . '<form class="masha-feedly-board__entry-form" data-admin-create-entry-form data-create-url="'
            . $this->escapeBoardValue(Controller::join_links(Director::baseURL(), '__masha-feedly', 'createEntry'))
            . '" data-security-id="' . $this->escapeBoardValue((string)SecurityToken::getSecurityID()) . '">'
            . '<label for="MashaFeedlyAdminEntryContent">' . self::translate('BOARD_ENTRY_DESCRIPTION', 'Beschreibung')
            . '<textarea id="MashaFeedlyAdminEntryContent" name="Content" rows="5" maxlength="10000" required placeholder="'
            . $this->escapeBoardValue(self::translate('BOARD_ENTRY_PLACEHOLDER', 'Beschreibe den Fehler oder Hinweis …')) . '"></textarea></label>'
            . '<label class="masha-feedly-board__upload"><span>' . self::translate('BOARD_ENTRY_ATTACHMENTS', 'Dateien anhängen')
            . '</span><input type="file" name="Attachments[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,application/zip,.jpg,.jpeg,.png,.gif,.webp,.pdf,.zip">'
            . '<small>' . self::translate('BOARD_ENTRY_UPLOAD_LIMIT', 'Bilder, PDFs oder ZIP-Dateien · max. 10 MB je Datei') . '</small></label>'
            . '<div class="masha-feedly-board__entry-fields"><label>' . self::translate('BOARD_ENTRY_STATUS', 'Status')
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
        $html .= '</select></label><label>' . self::translate('BOARD_ENTRY_DATE', 'Datum und Uhrzeit')
            . '<input type="datetime-local" name="EntryDate"></label></div>';
        $allowedMemberIDs = MashaFeedlyConfigExtension::memberIDs();
        if ($allowedMemberIDs) {
            $html .= '<fieldset class="masha-feedly-board__entry-assignees"><legend>'
                . self::translate('BOARD_ENTRY_ASSIGNEES', 'Verantwortlich') . '</legend><div>';
            foreach (Member::get()->filter('ID', $allowedMemberIDs)->sort('Surname ASC, FirstName ASC') as $assignee) {
                $html .= '<label><input type="checkbox" name="AssignedMemberIDs[]" value="' . (int)$assignee->ID . '">'
                    . '<span>' . $this->escapeBoardValue((string)$assignee->getName()) . '</span></label>';
            }
            $html .= '</div></fieldset>';
        }
        $html .= '<p class="masha-feedly-board__dialog-status" data-entry-form-status role="status" aria-live="polite"></p>'
            . '<footer class="masha-feedly-board__dialog-actions"><button type="button" class="btn btn-default" data-close-entry-modal>'
            . self::translate('BOARD_CATEGORY_ADD_CANCEL', 'Abbrechen') . '</button><button type="submit" class="btn btn-primary">'
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
                $description = trim(preg_replace('/\s+/u', ' ', html_entity_decode(
                    strip_tags((string)$entry->Content),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )) ?? '');
                $assignedEntryMemberIDs = array_map('intval', $entry->AssignedMembers()->column('ID'));
                $isUnread = in_array((int)$entry->ID, $unreadEntryIDs, true);
                $isPersonallyAssigned = $member instanceof Member
                    && (in_array((int)$member->ID, $assignedEntryMemberIDs, true)
                        || (int)$member->ID === $entry->creatorMemberID());
                $html .= '<article class="masha-feedly-board__card" data-entry-id="'
                    . (int)$entry->ID . '" data-entry-unread="'
                    . ($isUnread ? 'true' : 'false')
                    . '" data-assigned-member-ids="' . $this->escapeBoardValue(implode(',', $assignedEntryMemberIDs))
                    . '"><span class="masha-feedly-board__drag-handle" draggable="true"'
                    . ' title="' . $this->escapeBoardValue(self::translate('BOARD_DRAG_TITLE', 'Zum Sortieren ziehen')) . '" aria-label="' . $this->escapeBoardValue(self::translate('BOARD_DRAG_ARIA', 'Eintrag sortieren')) . '">⠿</span>'
                    . '<div class="masha-feedly-board__card-heading">';
                if ($isUnread) {
                    $unreadLabel = $isPersonallyAssigned
                        ? self::translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'BOARD_NEW_FOR_SIE' : 'BOARD_NEW_FOR_DU', 'Neue Aktivität für dich')
                        : self::translate('BOARD_NEW_FOR_ALL', 'Neue Aktivität für alle');
                    $unreadStyle = $isPersonallyAssigned ? 'personal' : 'general';
                    $html .= '<span class="masha-feedly-board__new-indicator masha-feedly-board__new-indicator--'
                        . $unreadStyle . '" aria-label="' . $unreadLabel . '" title="' . $unreadLabel . '">'
                        . '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
                        . '<path d="M12 2.5 14.4 9.6 21.5 12l-7.1 2.4L12 21.5l-2.4-7.1L2.5 12l7.1-2.4z"/>'
                        . '</svg><span>' . self::translate('BOARD_NEW', 'Aktivität') . '</span></span>';
                }
                $frontendEntryURL = $this->frontendEntryURL($entry);
                if ($frontendEntryURL !== '') {
                    $html .= '<a href="' . $this->escapeBoardValue($frontendEntryURL)
                        . '" target="_blank" rel="noopener noreferrer">';
                } else {
                    $html .= '<span class="masha-feedly-board__entry-title">';
                }
                $html .= $this->escapeBoardValue($entry->getTitle() ?: self::translate('BOARD_ENTRY_NO_DESCRIPTION', 'Eintrag ohne Beschreibung'));
                $html .= $frontendEntryURL !== '' ? '</a></div>' : '</span></div>';
                if ($description !== '') {
                    $html .= '<p>' . $this->escapeBoardValue(mb_strimwidth($description, 0, 180, '…')) . '</p>';
                }
                $priority = $entry->Priority();
                if ($priority->exists()) {
                    $html .= '<span class="masha-feedly-board__priority" style="--masha-feedly-priority-color:'
                        . $this->escapeBoardValue((string)$priority->Color) . '" aria-label="'
                        . $this->escapeBoardValue((string)$priority->Title) . '" title="'
                        . $this->escapeBoardValue((string)$priority->Title) . '">'
                        . $priority->getIconSVG() . '</span>';
                }
                $html .= '<time>' . $this->escapeBoardValue((string)$entry->dbObject('EntryDate')->Nice()) . '</time>';
                $assignedMembers = $entry->AssignedMembers()->sort('Surname ASC, FirstName ASC');
                if ($assignedMembers->exists()) {
                    $html .= '<div class="masha-feedly-board__assignees" aria-label="' . $this->escapeBoardValue(self::translate('BOARD_ASSIGNED_MEMBERS', 'Zugeordnete Mitglieder')) . '">';
                    foreach ($assignedMembers as $assignedMember) {
                        $name = (string)$assignedMember->getName();
                        $imageURL = $this->memberProfileImageURL($assignedMember);
                        $color = $assignedMember->getMashaFeedlyDisplayColor();
                        $html .= '<span class="masha-feedly-board__assignee" style="--masha-feedly-member-color:'
                            . $this->escapeBoardValue($color) . '" aria-label="'
                            . $this->escapeBoardValue($name) . '">';
                        if ($imageURL !== '') {
                            $html .= '<img class="masha-feedly-board__assignee-image" src="'
                                . $this->escapeBoardValue($imageURL) . '" alt="" loading="lazy">';
                        } else {
                            $html .= '<span class="masha-feedly-board__assignee-icon" aria-hidden="true">'
                                . $this->escapeBoardValue($assignedMember->getMashaFeedlyInitials()) . '</span>';
                        }
                        $html .= '</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</article>';
            }
            $html .= '</div></section>';
        }

        $html .= '</div><p class="masha-feedly-board__status" aria-live="polite"></p></section>';
        return $html;
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
        $siteConfig->write();

        $usedColors = [];
        foreach ($memberIDs as $authorizedMemberID) {
            $authorizedMember = Member::get()->byID($authorizedMemberID);
            if (!$authorizedMember) {
                continue;
            }

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

        $folder = MashaFeedlyMemberExtension::protectedIconFolder();
        foreach (Image::get()->filter('ParentID', (int)$folder->ID) as $profileImage) {
            $profileImage->CanViewType = \SilverStripe\Security\InheritedPermissions::ONLY_THESE_MEMBERS;
            $profileImage->ViewerMembers()->setByIDList($memberIDs);
            $profileImage->write();
            $profileImage->publishSingle();
            $profileImage->protectFile();
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
