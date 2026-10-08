<?php

namespace KW\MashaFeedly\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\i18n\i18n;
use SilverStripe\Admin\CMSProfileController;
use SilverStripe\CMS\Controllers\ContentController;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyPriority;
use KW\MashaFeedly\Task\MashaFeedlyDueDateReminderTask;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Extension\MashaFeedlyMemberExtension;

/**
 * Bindet das Masha-Feedly-Widget für berechtigte Benutzer global ein.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyWidgetExtension extends Extension
{
    /** Bindet Widget, Stylesheet und JavaScript in Frontend und CMS ein. */
    public function onAfterInit(): void
    {
        if ($this->owner instanceof ContentController) {
            MashaFeedlyDueDateReminderTask::runForWebsiteVisit();
        }

        if (!MashaFeedlyConfigExtension::canUse(Security::getCurrentUser())) {
            return;
        }

        Requirements::css('kooperativeweb/masha-feedly:client/dist/css/masha-feedly.css');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-emoji.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-create-entry.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-onboarding.js');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-colors.js');
        \KW\MashaFeedly\Service\MashaFeedlyEffectProvider::requireLoader();
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-entries.js');
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $canManageEstimate = \KW\MashaFeedly\Model\MashaFeedlyEntry::canManageEstimate(Security::getCurrentUser());
        $canApproveEstimate = \KW\MashaFeedly\Model\MashaFeedlyEntry::canApproveEstimate(Security::getCurrentUser());
        $categories = [];
        foreach (MashaFeedlyCategory::get()->sort('Sort ASC, Title ASC') as $category) {
            $systemKey = (string)$category->SystemKey;
            $estimateRole = in_array($systemKey, ['estimate_pending', 'estimate_approved'], true);
            $categories[] = [
                'ID' => (int)$category->ID,
                'Title' => $estimateRole && !$canApproveEstimate
                    ? i18n::_t('KW\\MashaFeedly\\Translations.ESTIMATE_HIDDEN_CATEGORY', 'In Bearbeitung')
                    : (string)$category->Title,
                'SystemKey' => $estimateRole && !$canApproveEstimate ? 'restricted_estimate' : $systemKey,
                'CanSelectForNewEntry' => $systemKey === 'estimate_pending'
                    ? $canManageEstimate
                    : $systemKey !== 'estimate_approved',
            ];
        }
        $members = [];
        $priorities = [];
        foreach (MashaFeedlyPriority::get()->sort('Sort ASC, Title ASC') as $priority) {
            $priorities[] = [
                'ID' => (int)$priority->ID,
                'Title' => (string)$priority->Title,
                'IsDefault' => (int)$priority->ID === (int)MashaFeedlyPriority::defaultPriority()->ID,
            ];
        }
        $allowedMemberIDs = MashaFeedlyConfigExtension::memberIDs();
        if ($allowedMemberIDs) {
            foreach (Member::get()->filter('ID', $allowedMemberIDs)->sort('Surname ASC, FirstName ASC') as $member) {
                $members[] = [
                    'ID' => (int)$member->ID,
                    'Name' => (string)$member->getName(),
                    'Initials' => (string)$member->getMashaFeedlyInitials(),
                    'Color' => (string)$member->getMashaFeedlyDisplayColor(),
                    'ImageURL' => (string)$member->getMashaFeedlyAvatarURL(),
                ];
            }
        }
        $currentMember = Security::getCurrentUser();
        $canPersonalize = MashaFeedlyConfigExtension::isExplicitlyAllowed($currentMember);
        $onboardingEnabled = $canPersonalize
            && (!((bool)($currentMember?->MashaFeedlyOnboardingCompleted ?? false))
                || (bool)($currentMember?->MashaFeedlyShowOnboarding ?? false));
        // Ein Neustart aus der Hilfe erfolgt ohne Seitenreload; seine Abschlussfelder müssen bereits vorhanden sein.
        $avatarIconPickerHTML = $canPersonalize
            ? MashaFeedlyMemberExtension::renderAvatarIconPickerForWidget(
                (string)$currentMember?->MashaFeedlyAvatarIcon,
                MashaFeedlyMemberExtension::normalizeColor((string)$currentMember?->MashaFeedlyColor)
            )
            : '';
        $avatarIconPickerAvailable = str_contains($avatarIconPickerHTML, 'data-masha-feedly-avatar-icons');
        if ($avatarIconPickerAvailable) {
            Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-avatar-icons.js');
        }
        $profileThemeOptions = [];
        if ($canPersonalize) {
            foreach (\KW\MashaFeedly\Service\MashaFeedlyEffectClient::themeOptions((string)$currentMember?->MashaFeedlyTheme) as $themeID => $themeTitle) {
                $profileThemeOptions[] = [
                    'ID' => $themeID,
                    'Title' => $themeTitle,
                    'Selected' => (string)$currentMember?->MashaFeedlyTheme === (string)$themeID,
                ];
            }
        }
        $markup = $this->owner->renderWith('KW/MashaFeedly/Includes/MashaFeedlyWidget', [
            'CreateEntryURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'createEntry'),
            'ListEntriesURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'listEntries'),
            'MarkEntryReadURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'markEntryRead'),
            'UpdateEntryURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'updateEntry'),
            'FindSimilarEntriesURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'findSimilarEntries'),
            'CommentEntryURL' => Controller::join_links(Director::baseURL(), '__masha-feedly-comment'),
            'CompleteOnboardingURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'completeOnboarding'),
            'RestartOnboardingURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'restartOnboarding'),
            'SavedViewsURL' => Controller::join_links(Director::baseURL(), '__masha-feedly'),
            'SaveViewURL' => Controller::join_links(Director::baseURL(), '__masha-feedly'),
            'DeleteViewURL' => Controller::join_links(Director::baseURL(), '__masha-feedly'),
            'MiteOptionsURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'miteOptions'),
            'MiteStartURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'startMiteTimer'),
            'MiteStopURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'stopMiteTimer'),
            'ProfileURL' => CMSProfileController::singleton()->Link() . '#Root_MashaFeedly',
            'ProfilePreferencesURL' => Controller::join_links(Director::baseURL(), '__masha-feedly', 'saveProfilePreferences'),
            'ProfileThemeOptions' => $profileThemeOptions,
            'ProfileTheme' => (string)($currentMember?->MashaFeedlyTheme ?? ''),
            'ProfileColor' => (string)($currentMember?->MashaFeedlyColor ?? ''),
            'ProfileAvatarIcon' => (string)($currentMember?->MashaFeedlyAvatarIcon ?? ''),
            'AvatarColorPaletteHTML' => $canPersonalize
                ? MashaFeedlyMemberExtension::renderColorPalette('MashaFeedlyColor', (string)$currentMember?->MashaFeedlyColor)
                : '',
            'AvatarIconPickerHTML' => $avatarIconPickerHTML,
            'AvatarIconPickerAvailable' => $avatarIconPickerAvailable,
            'TokenValue' => SecurityToken::inst()->getValue(),
            // Admins may use the widget for configuration, but the guided tour is
            // only for members explicitly added to the Masha:Feedly access list.
            'OnboardingEnabled' => $onboardingEnabled,
            'Categories' => $categories,
            'Priorities' => $priorities,
            'Members' => $members,
            'Address' => MashaFeedlyConfigExtension::address(),
            'FontSize' => MashaFeedlyConfigExtension::fontSize(),
            'Theme' => MashaFeedlyMemberExtension::themeFor(Security::getCurrentUser()),
            'CanManageEstimate' => $canManageEstimate,
            'CanApproveEstimate' => $canApproveEstimate,
            'CanManageReporter' => MashaFeedlyEntry::canManageReporter(Security::getCurrentUser()),
            'MiteEnabled' => MashaFeedlyConfigExtension::miteEnabled(),
            'CanManageMite' => MashaFeedlyEntry::canManageReporter(Security::getCurrentUser())
                && MashaFeedlyConfigExtension::miteEnabled(),
            'EstimateHourlyRate' => $canManageEstimate ? MashaFeedlyConfigExtension::hourlyRate() : 0,
        ])->forTemplate();
        $translationDefaults = [
            'MITE_LOADING' => 'Mite-Projekte und laufender Timer werden geladen …',
            'MITE_LOAD_ERROR' => 'Mite konnte nicht geladen werden.',
            'MITE_CHOOSE_PROJECT' => 'Projekt auswählen',
            'MITE_CHOOSE_SERVICE' => 'Leistung auswählen',
            'MITE_SWITCH_TIMER' => 'Aktuell läuft Timer #{id}: {note}.',
            'MITE_NO_TIMER' => 'Derzeit läuft kein Mite-Timer.',
            'MITE_STARTING' => 'Mite-Timer wird gestartet …',
            'MITE_STARTED' => 'Mite-Timer läuft.',
            'MITE_START_ERROR' => 'Mite-Timer konnte nicht gestartet werden.',
            'MITE_STOPPING' => 'Mite-Timer wird gestoppt …',
            'MITE_STOPPED' => 'Mite-Timer wurde gestoppt.',
            'MITE_STOP_ERROR' => 'Mite-Timer konnte nicht gestoppt werden.',
            'MITE_GENERAL_CONTEXT' => 'Starte eine allgemeine Zeiterfassung ohne Feedly-Eintrag oder stoppe den laufenden Timer.',
            'MITE_ENTRY_CONTEXT' => 'Der Eintrag und Seitenlink werden als Beschreibung an Mite übergeben.',
            'EMOJI_PICKER_OPEN' => 'Emoji auswählen',
            'EMOJI_PICKER_TITLE' => 'Emoji auswählen',
            'EMOJI_PICKER_SEARCH' => 'Emoji oder Begriff suchen …',
            'EMOJI_CATEGORY_SMILEYS' => 'Smileys',
            'EMOJI_CATEGORY_PEOPLE' => 'Menschen',
            'EMOJI_CATEGORY_NATURE' => 'Natur',
            'EMOJI_CATEGORY_FOOD' => 'Essen',
            'EMOJI_CATEGORY_ACTIVITY' => 'Aktivität',
            'EMOJI_CATEGORY_PLACES' => 'Orte',
            'EMOJI_CATEGORY_OBJECTS' => 'Objekte',
            'EMOJI_CATEGORY_SYMBOLS' => 'Symbole',
            'LIST_LOADING' => 'Einträge werden geladen …',
            'LIST_LOAD_ERROR' => 'Einträge konnten nicht geladen werden.',
            'FILTERS_TITLE' => 'Filter',
            'SORTING_TITLE' => 'Sortierung',
            'SORT_DUE' => 'Fälligkeit',
            'SORT_CREATED' => 'Erstellt am',
            'SORT_PRIORITY' => 'Priorität',
            'SORT_ASSIGNEE' => 'Zuständigkeit',
            'SORT_ACTIVITY' => 'Letzte Aktivität',
            'SORT_ASCENDING' => 'aufsteigend',
            'SORT_DESCENDING' => 'absteigend',
            'FILTERS_NONE' => 'Keine aktiv',
            'FILTERS_ACTIVE' => '{count} aktiv',
            'FILTER_REMOVE' => 'Filter entfernen: {label}',
            'FILTERS_CLEAR' => 'Alle Filter zurücksetzen',
            'FILTER_MODE' => 'Ansicht',
            'FILTER_PAGE_OPEN' => 'Offene Fehler hier',
            'FILTER_FEEDBACK' => 'Wartet auf Feedback',
            'FILTER_UNREAD' => 'Neuigkeiten',
            'NEWS_BUTTON_DU' => 'Neu seit deinem letzten Besuch',
            'NEWS_BUTTON_SIE' => 'Neu seit Ihrem letzten Besuch',
            'NEWS_TITLE' => 'Neuigkeiten',
            'NEWS_SUMMARY' => 'Einträge: {entries} · Kommentare: {comments}',
            'NEWS_HINT_DU' => 'seit deinem letzten Besuch',
            'NEWS_HINT_SIE' => 'seit Ihrem letzten Besuch',
            'NEWS_EMPTY' => 'Alles ist auf dem neuesten Stand.',
            'LIST_COUNT_UNREAD' => 'mit neuen Aktivitäten',
            'UNREAD_ACTIVITY' => 'Neue Aktivität',
            'COMMENT_SAVED' => 'Kommentar gesendet.',
            'COMMENT_SAVE_ERROR' => 'Kommentar konnte nicht gesendet werden.',
            'COMMENT_UPDATE_ERROR' => 'Kommentar konnte nicht gespeichert werden.',
            'COMMENT_DELETE_ERROR' => 'Kommentar konnte nicht gelöscht werden.',
            'COMMENT_REACTIONS' => 'Reaktionen auf diesen Kommentar',
            'COMMENT_REACTION_LIKE' => 'Gefällt mir',
            'COMMENT_REACTION_LOVE' => 'Herz',
            'COMMENT_REACTION_LAUGH' => 'Lachen',
            'COMMENT_REACTION_CRY' => 'Weinen',
            'COMMENT_REACTION_SURPRISED' => 'Überrascht',
            'COMMENT_REACTION_THANKS' => 'Danke',
            'COMMENT_REACTION_ADD' => '{reaction}: Reaktion hinzufügen. Bisher {count}.',
            'COMMENT_REACTION_REMOVE' => '{reaction}: eigene Reaktion entfernen. Insgesamt {count}.',
            'COMMENT_REACTION_SAVED' => 'Reaktion gespeichert.',
            'COMMENT_REACTION_ERROR' => 'Reaktion konnte nicht gespeichert werden.',
            'COMMENT_REACTION_INVALID' => 'Diese Reaktion ist nicht verfügbar.',
            'COMMENT_REACTION_PICKER_OPEN' => 'Mit diesem Kommentar reagieren',
            'COMMENT_REACTION_PICKER_CLOSE' => 'Reaktionsauswahl schließen',
            'COMMENT_REACTION_PICKER_TITLE' => 'Reaktion auswählen',
            'ENTRY_RELATIONS_ARIA' => 'Verknüpfte Einträge',
            'ENTRY_WITHOUT_TITLE' => 'Eintrag ohne Titel',
            'ENTRY_PRIORITY_ARIA' => 'Priorität: {priority}',
            'PRIORITY_FALLBACK' => 'Keine Priorität',
            'RELATION_RELATED' => 'Thematisch verwandt',
            'RELATION_RELATED_TO' => 'Thematisch verwandt mit',
            'RELATION_BLOCKED_BY' => 'Blockiert durch',
            'RELATION_BLOCKS' => 'Blockiert',
            'RELATION_DUPLICATE_OF' => 'Duplikat von',
            'RELATION_HAS_DUPLICATE' => 'Hat Duplikat',
            'LIST_COUNT_MINE_DU' => 'für dich',
            'LIST_COUNT_MINE_SIE' => 'für Sie',
            'LIST_COUNT_ALL' => 'insgesamt',
            'LIST_COUNT_PAGE' => 'auf dieser Seite',
            'LIST_COUNT_PAGE_OPEN' => 'offen auf dieser Seite',
            'LIST_COUNT_FEEDBACK' => 'warten auf Feedback',
            'FEEDBACK_BUTTON' => 'Wartet auf Feedback',
            'OPEN_ALL_LABEL' => 'Gesamte Website',
            'OPEN_PAGE_LABEL' => 'Aktuelle Seite',
            'OPEN_FEEDBACK_ENTRIES' => 'Einträge anzeigen, bei denen Feedback aussteht',
            'OPEN_CLOSED_ENTRIES' => 'Abgeschlossene Einträge ansehen',
            'LIST_COUNT_OPEN' => 'offen',
            'LIST_COUNT_CLOSED' => 'abgeschlossen',
            'EMPTY_MINE_DU' => 'Du hast noch keine Einträge.',
            'EMPTY_MINE_SIE' => 'Sie haben noch keine Einträge.',
            'EMPTY_ALL' => 'Es gibt noch keine Einträge.',
            'EMPTY_PAGE' => 'Auf dieser Seite gibt es noch keine Einträge.',
            'EMPTY_OPEN' => 'Es gibt keine offenen Einträge.',
            'EMPTY_CLOSED' => 'Es gibt keine abgeschlossenen Einträge.',
            'CATEGORY_ALL' => 'Alle Kategorien',
            'FILTER_PRIORITY' => 'Priorität',
            'FILTER_ALL_PRIORITIES' => 'Alle Prioritäten',
            'SAVED_VIEW_SELECT' => 'Gespeicherte Ansicht',
            'SAVED_VIEW_NONE' => 'Ansicht auswählen …',
            'SAVED_VIEW_NAME' => 'Name für aktuelle Filter',
            'SAVED_VIEW_EXAMPLE' => 'z. B. Meine offenen kritischen Fehler',
            'SAVED_VIEW_SAVE' => 'Ansicht speichern',
            'SAVED_VIEW_DELETE' => 'Ausgewählte Ansicht löschen',
            'SAVED_VIEW_SAVED' => 'Ansicht gespeichert.',
            'SAVED_VIEW_DELETED' => 'Ansicht gelöscht.',
            'SAVED_VIEW_ERROR' => 'Ansicht konnte nicht gespeichert werden.',
            'SAVED_VIEW_DELETE_ERROR' => 'Ansicht konnte nicht gelöscht werden.',
            'SAVED_VIEW_LIMIT' => 'Du kannst höchstens 20 Ansichten speichern.',
            'FILTER_OPEN' => 'Offene Einträge',
            'FILTER_CLOSED' => 'Abgeschlossene Einträge',
            'ENTRY_SINGULAR' => 'Eintrag',
            'ENTRY_PLURAL' => 'Einträge',
            'ENTRY_WITHOUT_CATEGORY' => 'Ohne Kategorie',
            'ENTRY_WITHOUT_TITLE' => 'Eintrag ohne Titel',
            'ENTRY_PRIORITY_ARIA' => 'Priorität: {priority}',
            'PRIORITY_FALLBACK' => 'Keine Priorität',
            'ENTRY_NO_DESCRIPTION' => 'Keine Beschreibung vorhanden.',
            'ENTRY_OPEN_ARIA' => 'Eintrag öffnen: {title}',
            'ENTRY_MARKER_ARIA' => 'Eintrag {number}: {title}',
            'ENTRY_NUMBER' => 'Eintrag #{id}',
            'ENTRY_REPORTED_BY' => 'Gemeldet von {author}',
            'ENTRY_CREATED_UNKNOWN' => 'Unbekannt',
            'ENTRY_CONTEXT_STATUS' => 'Status: {status}',
            'ENTRY_CONTEXT_AREA' => 'Bereich: {text}',
            'ENTRY_PAGE_LINK' => 'Zur Seite wechseln',
            'ENTRY_SHARE_ARIA' => 'Link zu {title} teilen',
            'ENTRY_SHARE_ARIA_DEFAULT' => 'Link zu diesem Eintrag teilen',
            'ENTRY_SHARE_DONE' => 'Direktlink wurde geteilt oder kopiert',
            'ENTRY_SHARE_ERROR' => 'Link konnte nicht geteilt werden',
            'ENTRY_SELECTED_CONTEXT' => 'Ausgewählter Bereich: „{text}“',
            'ENTRY_CONTEXT_EMPTY' => 'Bereich auf der Seite ausgewählt.',
            'RAINBOW_EMPTY_BOARD_TITLE' => 'Bugfrei – oder noch nichts eingetragen!',
            'RAINBOW_EMPTY_BOARD_MESSAGE' => 'Hier wurde noch nichts erfasst. Wir feiern vorsichtshalber trotzdem.',
            'RAINBOW_BOARD_TITLE' => 'Alles im grünen Bereich!',
            'RAINBOW_BOARD_MESSAGE' => 'Alle Einträge sind erledigt oder archiviert. Stark!',
            'RAINBOW_PAGE_TITLE' => 'Auf dieser Seite keine Fehler',
            'RAINBOW_PAGE_MESSAGE' => 'Alle Meldungen auf dieser Seite sind erledigt oder archiviert. Stark!',
            'RAINBOW_EMPTY_PAGE_MESSAGE' => 'Hier wurde noch nichts eingetragen – vielleicht ist die Seite schon perfekt.',
            'SUCCESS_EMPTY_BOARD_TITLE' => 'Noch keine Einträge',
            'SUCCESS_EMPTY_BOARD_MESSAGE' => 'Für Masha:Feedly liegen noch keine Einträge vor.',
            'SUCCESS_BOARD_TITLE' => 'Keine offenen Einträge',
            'SUCCESS_BOARD_MESSAGE' => 'Alle Einträge sind abgeschlossen oder archiviert.',
            'SUCCESS_PAGE_TITLE' => 'Keine offenen Einträge auf dieser Seite',
            'SUCCESS_PAGE_MESSAGE' => 'Auf dieser Seite gibt es derzeit keine offenen Einträge.',
            'SUCCESS_EMPTY_PAGE_MESSAGE' => 'Für diese Seite wurden noch keine Einträge erfasst.',
            'ESTIMATE_TITLE' => 'Kostenschätzung',
            'ESTIMATE_PENDING' => 'Kostenschätzung wartet auf Freigabe',
            'ESTIMATE_APPROVED' => 'Kostenschätzung freigegeben',
            'ESTIMATE_NOT_ENTERED' => 'Noch keine Kostenschätzung eingetragen',
            'ESTIMATE_REVIEW_HELP' => 'Bitte prüfe die geschätzte Dauer, die Erläuterung und den Gesamtpreis. Wähle anschließend im Status „Kostenschätzung freigegeben“.',
            'ESTIMATE_DETAILS_TOGGLE' => 'Details und Erläuterung',
            'ESTIMATE_TOTAL_PRICE' => 'Geschätzter Gesamtpreis',
            'ESTIMATE_TRANSITION_LOCKED' => 'Der Eintrag kann erst nach Freigabe der Kostenschätzung weiter verschoben werden.',
            'ESTIMATE_MANAGER_ONLY' => 'Die Kostenschätzung wird von der zuständigen Ansprechperson ergänzt.',
            'ESTIMATE_PRICE_HINT' => 'Dauer eingeben, Preis erscheint hier',
            'ESTIMATE_RATE_REQUIRED' => 'Bitte zuerst den Stundensatz in den Masha:Feedly-Einstellungen festlegen.',
            'BOARD_NEW_FOR_DU' => 'Neue Aktivität für dich',
            'BOARD_NEW_FOR_SIE' => 'Neue Aktivität für Sie',
            'BOARD_NEW_FOR_ALL' => 'Neue Aktivität für alle',
            'BOARD_NEW' => 'Neu',
            'BOARD_SAVING' => 'Änderung wird gespeichert …',
            'BOARD_SAVE_ERROR' => 'Speichern fehlgeschlagen.',
            'BOARD_SAVE_SUCCESS' => 'Eintrag wurde gespeichert.',
            'BOARD_SAVE_FAILURE' => 'Eintrag konnte nicht gespeichert werden.',
            'EDIT_SAVING' => 'Änderungen werden gespeichert …',
            'EDIT_SAVE_ERROR' => 'Änderungen konnten nicht gespeichert werden.',
            'CREATE_SAVING' => 'Eintrag wird gespeichert …',
            'CREATE_SAVE_ERROR' => 'Der Eintrag konnte nicht gespeichert werden.',
            'CREATE_ENTRY_FALLBACK' => 'Neuer Eintrag',
            'CLOSE_WIDGET' => 'Masha:Feedly schließen',
            'OPEN_WIDGET' => 'Masha:Feedly öffnen',
            'ASSIGNEES_ARIA' => 'Verantwortliche',
            'MEMBER_FALLBACK' => 'Mitglied',
            'UNKNOWN_BROWSER' => 'Unbekannter Browser',
            'ENV_LOGGED_AT' => 'Erfasst am',
            'ENV_PAGE' => 'Seite',
            'ENV_OPERATING_SYSTEM' => 'Betriebssystem',
            'ENV_BROWSER' => 'Browser',
            'ENV_SELECTED_AREA' => 'Ausgewählter Bereich',
            'ENV_ELEMENT_TEXT' => 'Text im Bereich',
            'ENV_RESOLUTION' => 'Bildschirmauflösung',
            'ENV_BROWSER_WINDOW' => 'Browserfenster',
            'ENV_COLOR_DEPTH' => 'Farbtiefe',
            'ENV_USER_AGENT' => 'Browserkennung',
            'TOUR_WELCOME_EYEBROW' => 'DEIN ERSTER SCHRITT',
            'TOUR_WELCOME_TITLE' => 'Willkommen bei Masha:Feedly',
            'TOUR_WELCOME_TEXT' => 'Wenn dir ein Fehler auffällt, kannst du ihn direkt auf der Website melden. Die kurze Einführung zeigt dir, wie es geht.',
            'TOUR_START' => 'Einführung starten',
            'TOUR_SKIP' => 'Später',
            'TOUR_STEP_PLUS' => 'Schritt 2 von 8: Das Fenster ist offen. Klicke jetzt auf das pinke Plus. Damit startest du eine neue Fehlermeldung.',
            'TOUR_STEP_ICON' => 'Schritt 1 von 8: Klicke auf das runde Masha:Feedly-Symbol ganz unten rechts. Damit öffnest du das Feedly-Fenster.',
            'TOUR_STEP_TARGET' => 'Schritt 3 von 8: Bewege den Mauszeiger über den betroffenen Inhalt und klicke genau auf die Stelle, an der der Fehler auftritt. Mit „Einführung abbrechen“ kannst du jederzeit aufhören.',
            'TOUR_STEP_FORM' => 'Schritt 4 von 8: Beschreibe im Textfeld, was nicht stimmt. Prüfe bei Bedarf Kategorie, Datum und zuständige Personen. Klicke dann auf „Eintrag speichern“.',
            'TOUR_STEP_VIEW_ENTRIES' => 'Schritt 5 von 8: Der Eintrag ist gespeichert. Klicke auf die kleine Zahl beim Standort-Symbol im Feedly-Fenster. So öffnest du die Meldungen dieser Seite.',
            'TOUR_STEP_OPEN_ENTRY' => 'Schritt 6 von 8: In der Liste siehst du die Einträge der Seite. Klicke auf die gerade erstellte Meldung, um ihre Details zu öffnen.',
            'TOUR_STEP_COMMENT_ENTRY' => 'Schritt 7 von 8: Hier findest du Kommentare, Browserdetails und den Verlauf. Schreibe einen kurzen Kommentar und sende ihn ab. Die anderen Aktionen bleiben bis zum nächsten Schritt gesperrt.',
            'TOUR_STEP_MANAGE_ENTRY' => 'Schritt 8 von 8: Ändere Status oder Priorität und markiere zuständige Personen. Unter „Zusammenhänge“ kannst du Einträge verknüpfen. Speichere deine Änderungen, dann bist du fertig.',
            'TOUR_BLOCKED_ICON' => 'Schon auf Entdeckungstour? Masha wartet unten rechts auf den Klick aufs Symbol. Die Website läuft dir nicht weg.',
            'TOUR_BLOCKED_PLUS' => 'Das Plus ist heute der Star. Bitte erst darauf klicken, bevor du die Seite auf eigene Faust erkundest.',
            'TOUR_BLOCKED_TARGET' => 'Diese Schaltfläche ist gerade nicht dran. Wähle den betroffenen Bereich oder brich die Einführung ab.',
            'TOUR_BLOCKED_FORM' => 'Die Website muss kurz warten: erst den Fehler beschreiben und speichern. Der Kaffee läuft nicht weg.',
            'TOUR_BLOCKED_ENTRIES' => 'Noch kein Freigang: erst den gerade gespeicherten Eintrag über die Seitenzahl ansehen.',
            'TOUR_BLOCKED_ENTRY' => 'Der Eintrag versteckt sich in der Liste. Klick ihn an, dann darfst du weiterstöbern.',
            'TOUR_BLOCKED_COMMENT' => 'Erst ein kurzer Kommentar, dann darfst du Status und Zuständigkeit ändern. Der Rest wartet kurz.',
            'TOUR_BLOCKED_MANAGE' => 'Fast fertig! Status oder Zuständigkeit speichern – oder die Einführung abbrechen. Der Rest wartet kurz.',
            'TOUR_CANCEL' => 'Einführung abbrechen',
            'TOUR_THANKS_TITLE' => 'Danke fürs Mitmachen!',
            'TOUR_THANKS_TEXT' => 'Du hast deine erste Meldung erstellt und gelernt, wie du Einträge ansiehst und bearbeitest. Jetzt kannst du Masha noch persönlich gestalten.',
            'TOUR_PROFILE_LINK' => 'Weitere Profileinstellungen',
            'TOUR_ICON_CHOICES' => 'Eigenes Icon wählen',
            'TOUR_SAVE_PREFERENCES' => 'Auswahl speichern',
            'TOUR_PREFERENCES_SAVING' => 'Deine Auswahl wird gespeichert …',
            'TOUR_PREFERENCES_SAVED' => 'Deine Auswahl wurde gespeichert.',
            'TOUR_PREFERENCES_ERROR' => 'Deine Auswahl konnte nicht gespeichert werden. Bitte versuche es erneut.',
            'TOUR_DONE' => 'Fertig',
            'RETRO_SUCCESS_TITLE' => 'Erfolgreich erledigt!',
            'RETRO_SUCCESS_MESSAGE' => 'Der Eintrag wurde abgeschlossen.',
            'RETRO_SUCCESS_BUTTON' => 'OK',
            'TOUR_SELECTION_TARGET' => 'Klicke auf den betroffenen Bereich der Website. Mit „Abbrechen“ kannst du die Auswahl beenden.',
            'HISTORY_EYEBROW' => 'ÄNDERUNGEN',
            'HISTORY_TITLE' => 'Verlauf',
            'HISTORY_CREATED' => 'Eintrag erstellt: {title}',
            'HISTORY_REPORTED_BY' => 'Meldeperson: {oldValue} → {newValue}',
            'HISTORY_COMMENT' => 'Kommentar: {text}',
            'HISTORY_ATTACHMENT' => 'Datei hochgeladen: {text}',
            'HISTORY_COMMENT_EDITED' => 'Kommentar bearbeitet: {oldValue} → {newValue}',
            'HISTORY_COMMENT_DELETED' => 'Kommentar gelöscht: {text}',
            'HISTORY_COMMENT_REACTION' => 'Reaktion auf Kommentar geändert: {oldValue} → {newValue}',
            'HISTORY_NO_REACTION' => 'Keine Reaktion',
            'HISTORY_STATUS_CHANGE' => 'Status: {oldValue} → {newValue}',
            'HISTORY_DUE_DATE_CHANGE' => 'Fälligkeit: {oldValue} → {newValue}',
            'NO_DUE_DATE' => 'Kein Termin',
            'ENTRY_DUE_DATE' => 'Fällig am {date}',
            'HISTORY_ASSIGNEES_CHANGE' => 'Zuständigkeit: {oldValue} → {newValue}',
            'HISTORY_NOBODY' => 'Niemand',
            'HISTORY_META' => '{actor} · {when}',
            'ENTRY_MARKER_TITLE' => '{category} · {title}',
            'ENTRY_RECORDED_CONTEXT' => 'Eintrag',
            'ENTRY_CONTEXT_STATUS' => 'Status: {status}',
            'ENTRY_CONTEXT_AREA' => 'Bereich: {text}',
            'ENTRY_SELECTED_CONTEXT' => 'Ausgewählter Bereich: „{text}“',
            'ENTRY_OPEN_ARIA' => 'Eintrag öffnen: {title}',
            'ENTRY_MARKER_ARIA' => 'Eintrag {number}: {title}',
            'ENTRY_PAGE_LINK' => 'Zur Seite wechseln',
            'ENTRY_SHARE_ARIA' => 'Link zu {title} teilen',
            'ENTRY_SHARE_ARIA_DEFAULT' => 'Link zu diesem Eintrag teilen',
            'ENTRY_SHARE_DONE' => 'Direktlink wurde geteilt oder kopiert',
            'ENTRY_SHARE_ERROR' => 'Link konnte nicht geteilt werden',
            'ENTRY_SINGULAR' => 'Eintrag',
            'ENTRY_PLURAL' => 'Einträge',
            'ENTRY_WITHOUT_CATEGORY' => 'Ohne Kategorie',
            'ENTRY_WITHOUT_TITLE' => 'Eintrag ohne Titel',
            'ENTRY_PRIORITY_ARIA' => 'Priorität: {priority}',
            'PRIORITY_FALLBACK' => 'Keine Priorität',
            'ENTRY_NO_DESCRIPTION' => 'Keine Beschreibung vorhanden.',
            'CATEGORY_ALL' => 'Alle Kategorien',
            'SIMILAR_OPEN_ENTRY' => 'Eintrag ansehen',
            'RAINBOW_EMPTY_BOARD_TITLE' => 'Bugfrei – oder noch nichts eingetragen!',
            'RAINBOW_EMPTY_BOARD_MESSAGE' => 'Hier wurde noch nichts erfasst. Wir feiern vorsichtshalber trotzdem.',
            'RAINBOW_BOARD_TITLE' => 'Alles im grünen Bereich!',
            'RAINBOW_BOARD_MESSAGE' => 'Alle Einträge sind erledigt oder archiviert. Stark!',
            'RAINBOW_PAGE_TITLE' => 'Auf dieser Seite keine Fehler',
            'RAINBOW_PAGE_MESSAGE' => 'Alle Meldungen auf dieser Seite sind erledigt oder archiviert. Stark!',
            'RAINBOW_EMPTY_PAGE_MESSAGE' => 'Hier wurde noch nichts eingetragen – vielleicht ist die Seite schon perfekt.',
            'SUCCESS_EMPTY_BOARD_TITLE' => 'Noch keine Einträge',
            'SUCCESS_EMPTY_BOARD_MESSAGE' => 'Für Masha:Feedly liegen noch keine Einträge vor.',
            'SUCCESS_BOARD_TITLE' => 'Keine offenen Einträge',
            'SUCCESS_BOARD_MESSAGE' => 'Alle Einträge sind abgeschlossen oder archiviert.',
            'SUCCESS_PAGE_TITLE' => 'Keine offenen Einträge auf dieser Seite',
            'SUCCESS_PAGE_MESSAGE' => 'Auf dieser Seite gibt es derzeit keine offenen Einträge.',
            'SUCCESS_EMPTY_PAGE_MESSAGE' => 'Für diese Seite wurden noch keine Einträge erfasst.',
        ];
        $translationDefaults['EFFECT_PROVIDER_UNAVAILABLE'] = 'Effekt-Anbieter nicht erreichbar.';
        $translationDefaults['EFFECT_PREVIEW_UNAVAILABLE'] = 'Dieser Effekt ist momentan nicht verfügbar.';
        $translationDefaults['CONFIG_ANIMATION_PREVIEW'] = 'Vorschau ansehen';
        $translationDefaults['CONFIG_ANIMATION_PREVIEW_STARTED'] = 'Vorschau gestartet.';
        $translations = [];
        foreach ($translationDefaults as $key => $default) {
            $translations[$key] = i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $default);
        }
        $translations['FORMAL_ADDRESS'] = MashaFeedlyConfigExtension::address();
        Requirements::customScript(
            'window.KWMashaFeedlyWidgetStylesheet = ' . json_encode((string)ModuleResourceLoader::resourceURL('kooperativeweb/masha-feedly:client/dist/css/masha-feedly.css')) . ';'
                . 'window.KWMashaFeedlyTranslations = ' . json_encode($translations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';'
                . 'window.KWMashaFeedlyWidgetMarkup = ' . json_encode($markup, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';',
            'kw-masha-feedly-widget-markup'
        );
    }
}
