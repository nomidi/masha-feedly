<?php

namespace KW\MashaFeedly\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;
use SilverStripe\ORM\ArrayList;
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
            && (!((bool)(($currentMember ? $currentMember->MashaFeedlyOnboardingCompleted : null) ?? false))
                || (bool)(($currentMember ? $currentMember->MashaFeedlyShowOnboarding : null) ?? false));
        // Ein Neustart aus der Hilfe erfolgt ohne Seitenreload; seine Abschlussfelder müssen bereits vorhanden sein.
        $avatarIconPickerHTML = $canPersonalize
            ? MashaFeedlyMemberExtension::renderAvatarIconPickerForWidget(
                (string)($currentMember ? $currentMember->MashaFeedlyAvatarIcon : null),
                MashaFeedlyMemberExtension::normalizeColor((string)($currentMember ? $currentMember->MashaFeedlyColor : null))
            )
            : '';
        $avatarIconPickerAvailable = str_contains($avatarIconPickerHTML, 'data-masha-feedly-avatar-icons');
        if ($canPersonalize) {
            Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-avatar-icons.js');
        }
        $profileThemeOptions = [];
        if ($canPersonalize) {
            foreach (\KW\MashaFeedly\Service\MashaFeedlyEffectClient::themeOptions((string)($currentMember ? $currentMember->MashaFeedlyTheme : null)) as $themeID => $themeTitle) {
                $profileThemeOptions[] = [
                    'ID' => $themeID,
                    'Title' => $themeTitle,
                    'Selected' => (string)($currentMember ? $currentMember->MashaFeedlyTheme : null) === (string)$themeID,
                ];
            }
        }
        $emailTestSucceeded = $canPersonalize && MashaFeedlyConfigExtension::emailTestSucceeded();
        $profileEmailOptions = [];
        if ($emailTestSucceeded) {
            foreach ([
                'MashaFeedlyNotifyNewEntries' => ['PROFILE_NOTIFY_NEW_ENTRIES', 'Bei neuen Meldungen benachrichtigen', 'PROFILE_NOTIFY_NEW_ENTRIES_DESCRIPTION', 'Erhalte eine E-Mail, wenn eine neue Meldung erstellt wird.'],
                'MashaFeedlyNotifyEntryUpdates' => ['PROFILE_NOTIFY_ENTRY_UPDATES', 'Bei Änderungen benachrichtigen', 'PROFILE_NOTIFY_ENTRY_UPDATES_DESCRIPTION', 'Erhalte eine E-Mail, wenn sich eine Meldung ändert.'],
                'MashaFeedlyNotifyOwnEntryChanges' => ['PROFILE_NOTIFY_OWN_CHANGES', 'Auch bei eigenen Meldungen benachrichtigen', 'PROFILE_NOTIFY_OWN_CHANGES_DESCRIPTION', 'Erhalte auch E-Mails für Meldungen, die du selbst erstellst oder änderst.'],
                'MashaFeedlyNotifyComments' => ['PROFILE_NOTIFY_COMMENTS', 'Bei Kommentaren benachrichtigen', 'PROFILE_NOTIFY_COMMENTS_DESCRIPTION', 'Erhalte eine E-Mail, wenn jemand bei einer deiner Meldungen kommentiert.'],
                'MashaFeedlyNotifyDueDateReminders' => ['PROFILE_NOTIFY_DUE_DATE_REMINDERS', 'An Fälligkeitstermine erinnern', 'PROFILE_NOTIFY_DUE_DATE_REMINDERS_DESCRIPTION', 'Erhalte am Fälligkeitstag eine Erinnerung für deine Meldungen.'],
                'MashaFeedlyNotifyCostEstimates' => ['PROFILE_NOTIFY_COST_ESTIMATES', 'Bei Kostenschätzungen benachrichtigen', 'PROFILE_NOTIFY_COST_ESTIMATES_DESCRIPTION', 'Erhalte eine E-Mail, wenn eine Kostenschätzung zur Freigabe bereitsteht.'],
            ] as $name => [$labelKey, $labelFallback, $descriptionKey, $descriptionFallback]) {
                $profileEmailOptions[] = [
                    'Name' => $name,
                    'Title' => i18n::_t('KW\\MashaFeedly\\Translations.' . $labelKey, $labelFallback),
                    'Description' => i18n::_t('KW\\MashaFeedly\\Translations.' . $descriptionKey, $descriptionFallback),
                    'Checked' => (bool)($currentMember ? $currentMember->{$name} : null),
                ];
            }
        }
        $currentMember = Security::getCurrentUser();
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
            'ProfileDisableSoundEffects' => (bool)($currentMember ? $currentMember->MashaFeedlyDisableSoundEffects : null),
            'ProfileTheme' => (string)(($currentMember ? $currentMember->MashaFeedlyTheme : null) ?? ''),
            'ProfileAddress' => (string)(($currentMember ? $currentMember->MashaFeedlyAddress : null) ?? ''),
            'ProfileColor' => (string)(($currentMember ? $currentMember->MashaFeedlyColor : null) ?? ''),
            'ProfileAvatarIcon' => (string)(($currentMember ? $currentMember->MashaFeedlyAvatarIcon : null) ?? ''),
            'ProfileAvatarPreviewHTML' => $canPersonalize ? $currentMember->renderAvatarPreview() : '',
            'ProfileEmailNotifications' => (bool)(($currentMember ? $currentMember->MashaFeedlyEmailNotifications : null) ?? false),
            'ProfileEmailTestSucceeded' => $emailTestSucceeded,
            'ProfileEmailOptions' => $profileEmailOptions,
            'AvatarColorPaletteHTML' => $canPersonalize
                ? MashaFeedlyMemberExtension::renderColorPalette('MashaFeedlyColor', (string)($currentMember ? $currentMember->MashaFeedlyColor : null))
                : '',
            'AvatarIconPickerHTML' => $avatarIconPickerHTML,
            'AvatarIconPickerAvailable' => $avatarIconPickerAvailable,
            'TokenValue' => SecurityToken::inst()->getValue(),
            // Admins may use the widget for configuration, but the guided tour is
            // only for members explicitly added to the Masha:Feedly access list.
            'OnboardingEnabled' => $onboardingEnabled,
            // Silverstripe 4 benötigt Listenobjekte für die Schleifen im Template.
            'Categories' => ArrayList::create($categories),
            'Priorities' => ArrayList::create($priorities),
            'Members' => ArrayList::create($members),
            'Address' => MashaFeedlyMemberExtension::addressFor($currentMember),
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
            'MITE_GENERAL_CONTEXT' => 'Starte eine allgemeine Zeiterfassung ohne Feedly-Meldung oder stoppe den laufenden Timer.',
            'MITE_ENTRY_CONTEXT' => 'Beschreibung und Seitenlink der Meldung werden an Mite übergeben.',
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
            'LIST_LOADING' => 'Meldungen werden geladen …',
            'LIST_LOAD_ERROR' => 'Meldungen konnten nicht geladen werden.',
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
            'FILTER_PAGE_OPEN' => 'Offene Meldungen hier',
            'FILTER_FEEDBACK' => 'Wartet auf Feedback',
            'FILTER_UNREAD' => 'Neuigkeiten',
            'NEWS_BUTTON_DU' => 'Neu seit deinem letzten Besuch',
            'NEWS_BUTTON_SIE' => 'Neu seit Ihrem letzten Besuch',
            'NEWS_TITLE' => 'Neuigkeiten',
            'NEWS_SUMMARY' => 'Meldungen: {entries} · Kommentare: {comments}',
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
            'ENTRY_RELATIONS_ARIA' => 'Verknüpfte Meldungen',
            'ENTRY_WITHOUT_TITLE' => 'Meldung ohne Titel',
            'ENTRY_PRIORITY_ARIA' => 'Priorität: {priority}',
            'PRIORITY_FALLBACK' => 'Keine Priorität',
            'RELATION_RELATED' => 'Thematisch verwandt',
            'RELATION_RELATED_TO' => 'Thematisch verwandt mit',
            'RELATION_BLOCKED_BY' => 'Blockiert durch',
            'RELATION_BLOCKS' => 'Blockiert',
            'RELATION_DUPLICATE_OF' => 'Bereits in einer anderen Meldung beschrieben',
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
            'OPEN_FEEDBACK_ENTRIES' => 'Meldungen anzeigen, bei denen Feedback aussteht',
            'OPEN_CLOSED_ENTRIES' => 'Abgeschlossene Meldungen ansehen',
            'LIST_COUNT_OPEN' => 'offen',
            'LIST_COUNT_CLOSED' => 'abgeschlossen',
            'EMPTY_MINE_DU' => 'Du hast noch keine Meldungen.',
            'EMPTY_MINE_SIE' => 'Sie haben noch keine Meldungen.',
            'EMPTY_ALL' => 'Es gibt noch keine Meldungen.',
            'EMPTY_PAGE' => 'Auf dieser Seite gibt es noch keine Meldungen.',
            'EMPTY_OPEN' => 'Es gibt keine offenen Meldungen.',
            'EMPTY_CLOSED' => 'Es gibt keine abgeschlossenen Meldungen.',
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
            'FILTER_OPEN' => 'Offene Meldungen',
            'FILTER_CLOSED' => 'Abgeschlossene Meldungen',
            'ENTRY_SINGULAR' => 'Meldung',
            'ENTRY_PLURAL' => 'Meldungen',
            'ENTRY_WITHOUT_CATEGORY' => 'Ohne Kategorie',
            'ENTRY_WITHOUT_TITLE' => 'Meldung ohne Titel',
            'ENTRY_PRIORITY_ARIA' => 'Priorität: {priority}',
            'PRIORITY_FALLBACK' => 'Keine Priorität',
            'ENTRY_NO_DESCRIPTION' => 'Keine Beschreibung vorhanden.',
            'ENTRY_OPEN_ARIA' => 'Meldung öffnen: {title}',
            'ENTRY_MARKER_ARIA' => 'Meldung {number}: {title}',
            'ENTRY_NUMBER' => 'Meldung #{id}',
            'ENTRY_REPORTED_BY' => 'Gemeldet von {author}',
            'ENTRY_CREATED_UNKNOWN' => 'Unbekannt',
            'ENTRY_CONTEXT_STATUS' => 'Status: {status}',
            'ENTRY_CONTEXT_AREA' => 'Bereich: {text}',
            'ENTRY_PAGE_LINK' => 'Zur Seite wechseln',
            'ENTRY_SHARE_ARIA' => 'Link zu {title} teilen',
            'ENTRY_SHARE_ARIA_DEFAULT' => 'Link zu dieser Meldung teilen',
            'ENTRY_SHARE_DONE' => 'Direktlink wurde geteilt oder kopiert',
            'ENTRY_SHARE_ERROR' => 'Link konnte nicht geteilt werden',
            'ENTRY_SELECTED_CONTEXT' => 'Ausgewählter Bereich: „{text}“',
            'ENTRY_CONTEXT_EMPTY' => 'Bereich auf der Seite ausgewählt.',
            'RAINBOW_EMPTY_BOARD_TITLE' => 'Noch keine offenen Meldungen',
            'RAINBOW_EMPTY_BOARD_MESSAGE' => 'Hier wurde noch nichts erfasst. Wir feiern vorsichtshalber trotzdem.',
            'RAINBOW_BOARD_TITLE' => 'Alles im grünen Bereich!',
            'RAINBOW_BOARD_MESSAGE' => 'Alle Meldungen sind erledigt oder archiviert. Stark!',
            'RAINBOW_PAGE_TITLE' => 'Auf dieser Seite keine Fehler',
            'RAINBOW_PAGE_MESSAGE' => 'Alle Meldungen auf dieser Seite sind erledigt oder archiviert. Stark!',
            'RAINBOW_EMPTY_PAGE_MESSAGE' => 'Hier wurde noch nichts eingetragen – vielleicht ist die Seite schon perfekt.',
            'SUCCESS_EMPTY_BOARD_TITLE' => 'Noch keine Meldungen',
            'SUCCESS_EMPTY_BOARD_MESSAGE' => 'Für Masha:Feedly liegen noch keine Meldungen vor.',
            'SUCCESS_BOARD_TITLE' => 'Keine offenen Meldungen',
            'SUCCESS_BOARD_MESSAGE' => 'Alle Meldungen sind abgeschlossen oder archiviert.',
            'SUCCESS_PAGE_TITLE' => 'Keine offenen Meldungen auf dieser Seite',
            'SUCCESS_PAGE_MESSAGE' => 'Auf dieser Seite gibt es derzeit keine offenen Meldungen.',
            'SUCCESS_EMPTY_PAGE_MESSAGE' => 'Für diese Seite wurden noch keine Meldungen erfasst.',
            'ESTIMATE_TITLE' => 'Kostenschätzung',
            'ESTIMATE_PENDING' => 'Kostenschätzung wartet auf Freigabe',
            'ESTIMATE_APPROVED' => 'Kostenschätzung freigegeben',
            'ESTIMATE_NOT_ENTERED' => 'Noch keine Kostenschätzung eingetragen',
            'ESTIMATE_REVIEW_HELP' => 'Prüfe die geschätzte Dauer, die Erläuterung und den Gesamtpreis. Wähle danach den Status „Kostenschätzung freigegeben“ und klicke auf „Änderungen speichern“.',
            'ESTIMATE_DETAILS_TOGGLE' => 'Details und Erläuterung',
            'ESTIMATE_TOTAL_PRICE' => 'Geschätzter Gesamtpreis',
            'ESTIMATE_TRANSITION_LOCKED' => 'Die Meldung kann erst nach Freigabe der Kostenschätzung weiter verschoben werden.',
            'ESTIMATE_MANAGER_ONLY' => 'Die Kostenschätzung wird von der zuständigen Ansprechperson ergänzt.',
            'ESTIMATE_PRICE_HINT' => 'Dauer eingeben, Preis erscheint hier',
            'ESTIMATE_RATE_REQUIRED' => 'Bitte zuerst den Stundensatz in den Masha:Feedly-Einstellungen festlegen.',
            'BOARD_NEW_FOR_DU' => 'Neue Aktivität für dich',
            'BOARD_NEW_FOR_SIE' => 'Neue Aktivität für Sie',
            'BOARD_NEW_FOR_ALL' => 'Neue Aktivität für alle',
            'BOARD_NEW' => 'Neu',
            'BOARD_SAVING' => 'Änderung wird gespeichert …',
            'BOARD_SAVE_ERROR' => 'Speichern fehlgeschlagen. Bitte prüfe deine Verbindung und versuche es erneut.',
            'BOARD_SAVE_SUCCESS' => 'Meldung wurde gespeichert.',
            'BOARD_SAVE_FAILURE' => 'Meldung konnte nicht gespeichert werden. Bitte prüfe deine Verbindung und versuche es erneut.',
            'EDIT_FEEDBACK_NOTICE_TITLE' => 'Zur Prüfung weitergegeben',
            'EDIT_SAVING' => 'Änderungen werden gespeichert …',
            'EDIT_SAVE_ERROR' => 'Änderungen konnten nicht gespeichert werden. Bitte prüfe deine Verbindung und versuche es erneut.',
            'CREATE_SAVING' => 'Meldung wird gespeichert …',
            'CREATE_SAVE_ERROR' => 'Die Meldung konnte nicht gespeichert werden.',
            'CREATE_ENTRY_FALLBACK' => 'Neue Meldung',
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
            'TOUR_WELCOME_WORKFLOW' => 'Meldung, Rückfragen und Fortschritt bleiben an einem Ort. Ihr könnt Verantwortliche festlegen, direkt zur Meldung kommentieren und sehen, was noch offen oder bereits erledigt ist. Die kurze Einführung zeigt dir die ersten Schritte.',
            'TOUR_MOBILE_NOTICE' => 'Die Einführung benötigt einen größeren Bildschirm, zum Beispiel einen Laptop oder Computer. Mit OK können Sie die Einführung später starten. Einführung abbrechen beendet sie dauerhaft.',
            'TOUR_START' => 'Einführung starten',
            'TOUR_SKIP' => 'Später',
            'TOUR_STEP_PLUS' => 'Schritt 2 von 8: Im Masha:Feedly-Menü findest du ein großes pinkes Plus. Klicke darauf. Damit startest du eine neue Meldung zu einem Fehler oder Änderungswunsch.',
            'TOUR_STEP_ICON' => 'Schritt 1 von 8: Klicke auf das Masha:Feedly-Logo am rechten Seitenrand. So öffnest du das Masha:Feedly-Bedienfeld mit den Meldungszählern und Aktionen.',
            'TOUR_STEP_TARGET' => 'Schritt 3 von 8: Klicke auf den betroffenen Bereich der Website. Mit „Einführung beenden“ kannst du die Auswahl jederzeit abbrechen.',
            'TOUR_STEP_FORM' => 'Schritt 4 von 8: Klicke in das große Feld „Beschreibung“. Schreib dort kurz hinein, was falsch ist oder was du dir wünschst. Klicke danach auf „Meldung speichern“.',
            'TOUR_STEP_VIEW_ENTRIES' => 'Schritt 5 von 8: Super, du hast eine Meldung erstellt! Klicke jetzt auf das Blatt-Symbol mit der Zahl. Damit siehst du offene Meldungen nur auf dieser Seite. Der Globus darüber zeigt Meldungen von der ganzen Website – den klickst du jetzt nicht an.',
            'TOUR_STEP_OPEN_ENTRY' => 'Schritt 6 von 8: Deine neue Meldung ist bunt umrandet. Klicke genau auf diese Meldung, um sie zu öffnen.',
            'TOUR_STEP_COMMENT_ENTRY' => 'Schritt 7 von 8: Schreibe in der geöffneten Meldung einen kurzen Kommentar und sende ihn ab. Die Bearbeitungsaktionen werden im nächsten Schritt freigegeben.',
            'TOUR_STEP_MANAGE_ENTRY' => 'Schritt 8 von 8: Oben kannst du Status und Priorität ändern. Darunter kannst du verantwortliche Personen auswählen. Klicke danach unbedingt auf „Änderungen speichern“, sonst werden deine Änderungen nicht übernommen.',
            'TOUR_BLOCKED_ICON' => 'Klicke zuerst auf das Masha:Feedly-Logo am rechten Seitenrand.',
            'TOUR_BLOCKED_PLUS' => 'Klicke zuerst auf das pinke Plus, um eine Meldung zu erstellen.',
            'TOUR_BLOCKED_TARGET' => 'Klicke auf die Stelle auf der Website, um die es in deiner Meldung geht.',
            'TOUR_BLOCKED_FORM' => 'Beschreibe, was geändert werden soll. Klicke danach auf „Meldung speichern“.',
            'TOUR_BLOCKED_ENTRIES' => 'Klicke auf das Seitensymbol mit der Zahl, um deine gespeicherte Meldung zu finden.',
            'TOUR_BLOCKED_ENTRY' => 'Klicke in der Liste auf deine neue Meldung, um sie zu öffnen.',
            'TOUR_BLOCKED_COMMENT' => 'Schreibe einen kurzen Kommentar und klicke auf „Kommentar senden“. Danach kannst du die Meldung bearbeiten.',
            'TOUR_BLOCKED_MANAGE' => 'Klicke auf „Änderungen speichern“, um diesen Schritt abzuschließen. Mit „Einführung beenden“ kannst du jederzeit aufhören.',
            'TOUR_CANCEL' => 'Einführung beenden',
            'TOUR_THANKS_TITLE' => 'Danke fürs Mitmachen!',
            'TOUR_THANKS_TEXT' => 'Deine erste Meldung ist gespeichert. Hier kannst du dein Profil und deine E-Mail-Benachrichtigungen einstellen. Speichere deine Auswahl anschließend mit „Auswahl speichern“.',
            'TOUR_THANKS_FORM_INTRO' => 'Wähle, wie dein Profil aussieht und welche Nachrichten du erhalten möchtest.',
            'PROFILE_ADDRESS' => 'Wie möchtest du angesprochen werden?',
            'PROFILE_ADDRESS_DEFAULT' => 'Einstellung der Website übernehmen',
            'PROFILE_ADDRESS_DESCRIPTION' => 'Wähle Du oder Sie. Ohne eigene Auswahl gilt die Einstellung der Website.',
            'TOUR_THANKS_EFFECTS_LABEL' => 'Danke-Animation auswählen',
            'TOUR_THANKS_EFFECTS_SUMMARY' => 'Wähle eine Kategorie und sieh dir Beispiele an.',
            'TOUR_THANKS_EFFECTS_SUMMARY' => 'Wähle eine Kategorie und sieh dir Beispiele an.',
            'TOUR_THANKS_EFFECTS_HELP' => 'Wenn du eine Meldung als erledigt markierst und speicherst, erscheint eine kurze Animation. Aus der gewählten Kategorie wird zufällig eine passende Animation ausgesucht. Saisonale Animationen erscheinen nur zur passenden Jahreszeit. Ist keine verfügbar, erscheint ein ruhiges Häkchen.',
            'TOUR_THANKS_EXAMPLES_LABEL' => 'Beispiele ansehen',
            'TOUR_THANKS_EXAMPLE_BUTTON' => 'Beispiel ansehen',
            'TOUR_THANKS_EXAMPLE_STARTED' => 'Die Vorschau wurde gestartet.',
            'TOUR_THANKS_EXAMPLE_FALLBACK' => 'Für diese Kategorie ist gerade kein Effekt aktiv. Hier siehst du das ruhige Häkchen.',
            'TOUR_THANKS_EXAMPLE_UNAVAILABLE' => 'Die Vorschau ist gerade nicht verfügbar.',
            'TOUR_THANKS_COLOR_LABEL' => 'Farbe deines Profilsymbols',
            'TOUR_THANKS_ICON_TITLE' => 'Profilsymbol',
            'TOUR_THANKS_ICON_HELP' => 'Wähle ein Symbol für dein Profil. Deine Farbe und dein Symbol erscheinen später neben deinen Meldungen und Kommentaren.',
            'TOUR_THANKS_PREVIEW_TITLE' => 'Deine Profilvorschau',
            'TOUR_THANKS_PREVIEW_HELP' => 'Hier siehst du dein Symbol und deine Farbe so, wie sie neben deinen Meldungen und Kommentaren erscheinen.',
            'TOUR_THANKS_EMAIL_HELP' => 'Ein Häkchen bedeutet: Du bekommst diese E-Mail. Du kannst jede Auswahl jederzeit ändern.',
            'TOUR_THANKS_EMAIL_SUMMARY' => 'Wähle, welche Nachrichten du per E-Mail bekommst.',
            'TOUR_THANKS_EMAIL_MASTER_HELP' => 'Schalte diese Option aus, wenn du gar keine E-Mails von Masha:Feedly erhalten möchtest.',
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
            'TOUR_SELECTION_TARGET' => 'Klicke auf den betroffenen Bereich der Website. Mit „Einführung beenden“ kannst du die Auswahl abbrechen.',
            'HISTORY_EYEBROW' => 'ÄNDERUNGEN',
            'HISTORY_TITLE' => 'Verlauf',
            'HISTORY_CREATED' => 'Meldung erstellt: {title}',
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
            'ENTRY_RECORDED_CONTEXT' => 'Meldung',
            'ENTRY_CONTEXT_STATUS' => 'Status: {status}',
            'ENTRY_CONTEXT_AREA' => 'Bereich: {text}',
            'ENTRY_SELECTED_CONTEXT' => 'Ausgewählter Bereich: „{text}“',
            'ENTRY_OPEN_ARIA' => 'Meldung öffnen: {title}',
            'ENTRY_MARKER_ARIA' => 'Meldung {number}: {title}',
            'ENTRY_PAGE_LINK' => 'Zur Seite wechseln',
            'ENTRY_SHARE_ARIA' => 'Link zu {title} teilen',
            'ENTRY_SHARE_ARIA_DEFAULT' => 'Link zu dieser Meldung teilen',
            'ENTRY_SHARE_DONE' => 'Direktlink wurde geteilt oder kopiert',
            'ENTRY_SHARE_ERROR' => 'Link konnte nicht geteilt werden',
            'ENTRY_SINGULAR' => 'Meldung',
            'ENTRY_PLURAL' => 'Meldungen',
            'ENTRY_WITHOUT_CATEGORY' => 'Ohne Kategorie',
            'ENTRY_WITHOUT_TITLE' => 'Meldung ohne Titel',
            'ENTRY_PRIORITY_ARIA' => 'Priorität: {priority}',
            'PRIORITY_FALLBACK' => 'Keine Priorität',
            'ENTRY_NO_DESCRIPTION' => 'Keine Beschreibung vorhanden.',
            'CATEGORY_ALL' => 'Alle Kategorien',
            'SIMILAR_OPEN_ENTRY' => 'Meldung ansehen',
            'RAINBOW_EMPTY_BOARD_TITLE' => 'Noch keine offenen Meldungen',
            'RAINBOW_EMPTY_BOARD_MESSAGE' => 'Hier wurde noch nichts erfasst. Wir feiern vorsichtshalber trotzdem.',
            'RAINBOW_BOARD_TITLE' => 'Alles im grünen Bereich!',
            'RAINBOW_BOARD_MESSAGE' => 'Alle Meldungen sind erledigt oder archiviert. Stark!',
            'RAINBOW_PAGE_TITLE' => 'Auf dieser Seite keine Fehler',
            'RAINBOW_PAGE_MESSAGE' => 'Alle Meldungen auf dieser Seite sind erledigt oder archiviert. Stark!',
            'RAINBOW_EMPTY_PAGE_MESSAGE' => 'Hier wurde noch nichts eingetragen – vielleicht ist die Seite schon perfekt.',
            'SUCCESS_EMPTY_BOARD_TITLE' => 'Noch keine Meldungen',
            'SUCCESS_EMPTY_BOARD_MESSAGE' => 'Für Masha:Feedly liegen noch keine Meldungen vor.',
            'SUCCESS_BOARD_TITLE' => 'Keine offenen Meldungen',
            'SUCCESS_BOARD_MESSAGE' => 'Alle Meldungen sind abgeschlossen oder archiviert.',
            'SUCCESS_PAGE_TITLE' => 'Keine offenen Meldungen auf dieser Seite',
            'SUCCESS_PAGE_MESSAGE' => 'Auf dieser Seite gibt es derzeit keine offenen Meldungen.',
            'SUCCESS_EMPTY_PAGE_MESSAGE' => 'Für diese Seite wurden noch keine Meldungen erfasst.',
        ];
        $translationDefaults['EFFECT_PROVIDER_UNAVAILABLE'] = 'Effekt-Anbieter nicht erreichbar.';
        $translationDefaults['EFFECT_PREVIEW_UNAVAILABLE'] = 'Dieser Effekt ist momentan nicht verfügbar.';
        $translationDefaults['CONFIG_ANIMATION_PREVIEW'] = 'Vorschau ansehen';
        $translationDefaults['CONFIG_ANIMATION_PREVIEW_STARTED'] = 'Vorschau gestartet.';
        $translations = [];
        foreach ($translationDefaults as $key => $default) {
            $translations[$key] = i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $default);
        }
        // Liefert für jedes Tour-Wording eine formelle Variante; sprachlich neutrale Texte bleiben gleich.
        foreach (array_keys($translationDefaults) as $key) {
            if (str_starts_with($key, 'TOUR_') || $key === 'PROFILE_ADDRESS' || $key === 'PROFILE_ADDRESS_DESCRIPTION') {
                $formalKey = $key . '_SIE';
                $translations[$formalKey] = i18n::_t(
                    'KW\\MashaFeedly\\Translations.' . $formalKey,
                    $translations[$key]
                );
            }
        }
        $translations['FORMAL_ADDRESS'] = MashaFeedlyMemberExtension::addressFor($currentMember);
        Requirements::customScript(
            'window.KWMashaFeedlyWidgetStylesheet = ' . json_encode((string)ModuleResourceLoader::resourceURL('kooperativeweb/masha-feedly:client/dist/css/masha-feedly.css')) . ';'
                . 'window.KWMashaFeedlyTranslations = ' . json_encode($translations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';'
                . 'window.KWMashaFeedlyWidgetMarkup = ' . json_encode($markup, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';',
            'kw-masha-feedly-widget-markup'
        );
    }
}
