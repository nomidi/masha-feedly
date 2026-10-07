<?php

namespace KW\MashaFeedly\Tests\Functional;

use KW\MashaFeedly\Admin\MashaFeedlyAdmin;
use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Prüft die globale Widget-Ausgabe im Frontend abhängig vom Benutzerzugriff.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyWidgetTest extends FunctionalTest
{
    protected static $fixture_file = '../fixtures/MashaFeedly.yml';

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('de_DE');
    }

    /** Prüft Icon-Upload und Avatarfarben auf der echten CMS-Profilseite eines freigegebenen Benutzers. */
    public function testAllowedMemberCanChooseProfileIconAndColorOnCmsProfilePage(): void
    {
        $this->logInWithPermission('CMS_ACCESS');
        $member = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $member);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();

        $response = $this->get('/admin/myprofile');

        $this->assertSame(200, $response->getStatusCode());
        $body = html_entity_decode($response->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Root_MashaFeedly', $body, 'Das Masha:Feedly-Profil erscheint als eigener CMS-Tab.');
        $this->assertStringContainsString('MashaFeedlyIconImage', $body, 'Freigegebene Mitglieder können ihr Profilbild/Icon hochladen.');
        $this->assertStringContainsString('MashaFeedlyColor', $body, 'Freigegebene Mitglieder können eine Avatarfarbe wählen.');
        $this->assertStringContainsString('name="MashaFeedlyTheme"', $body, 'Freigegebene Mitglieder können ihr persönliches Theme einstellen.');
        $this->assertStringContainsString('Website-Vorgabe', $body);
        $this->assertStringContainsString('masha-feedly-color-palette__grid', $body, 'Die Farbauswahl wird auf der Profilseite gerendert.');
    }

    /** Prüft, dass nur freigegebene Benutzer das globale Widget erhalten. */
    public function testWidgetIsRenderedOnlyForAllowedMembers(): void
    {
        $germanHelpTitle = i18n::with_locale('de_DE', static fn (): string =>
            i18n::_t('KW\\MashaFeedly\\Translations.HELP_TITLE', 'Übersetzungsdatei fehlt')
        );
        $this->assertSame('Masha:Feedly verwenden', $germanHelpTitle);
        $page = $this->objFromFixture(\Page::class, 'frontendTestPage');
        $page->publishRecursive();
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $otherMember = $this->objFromFixture(Member::class, 'notAllowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$allowedMember->ID]);
        $config->write();

        $this->logInAs($allowedMember);
        $allowedResponse = $this->get('/masha-feedly-widget-test');
        $this->assertSame(200, $allowedResponse->getStatusCode());
        $widgetMarkup = $this->widgetMarkup($allowedResponse->getBody());
        $this->assertStringContainsString('data-address="du"', $widgetMarkup);
        $this->assertStringContainsString('data-theme="playful"', $widgetMarkup);
        $this->assertStringContainsString('data-font-size="small"', $widgetMarkup);
        $this->assertMatchesRegularExpression(
            '/<select name="CategoryID" data-masha-feedly-create-category>(?:(?!<\/select>).)*<option[^>]*>Backlog<\/option>(?:(?!<\/select>).)*<\/select>/s',
            $widgetMarkup,
            'Das Formular zum Erstellen eines Feedly-Eintrags zeigt Statusoptionen.'
        );
        $this->assertMatchesRegularExpression(
            '/<select name="PriorityID">(?:(?!<\/select>).)*<option[^>]*>Normal<\/option>(?:(?!<\/select>).)*<option[^>]*>Info<\/option>(?:(?!<\/select>).)*<\/select>/s',
            $widgetMarkup,
            'Das Formular zum Erstellen eines Feedly-Eintrags zeigt Prioritätsoptionen.'
        );
        $this->assertStringContainsString('window.KWMashaFeedlyWidgetMarkup', $allowedResponse->getBody());
        $this->assertStringContainsString('"HISTORY_COMMENT":"Kommentar: {text}"', $allowedResponse->getBody());
        $this->assertStringContainsString('"HISTORY_META":"{actor} · {when}"', $allowedResponse->getBody());
        $this->assertStringContainsString('"HISTORY_CREATED":"Eintrag erstellt: {title}"', $allowedResponse->getBody());
        $this->assertStringContainsString('"ENTRY_REPORTED_BY":"Gemeldet von {author}"', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-entry-created-avatar', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-sort-details', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-sort-option="due"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('Sortierung', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('"SIMILAR_OPEN_ENTRY":"Eintrag ansehen"', $allowedResponse->getBody());
        $this->assertStringContainsString('"RELATION_DUPLICATE_OF":"Duplikat von"', $allowedResponse->getBody());
        $this->assertStringContainsString('"RELATION_BLOCKED_BY":"Blockiert durch"', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-open-help', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-help-modal', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-restart-onboarding', $allowedResponse->getBody());
        $this->assertStringContainsString('Einführung erneut starten', $allowedResponse->getBody());
        $this->assertStringContainsString('data-onboarding-restart-url="/__masha-feedly/restartOnboarding"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('Masha:Feedly verwenden', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-total-count', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-open-page-list', $allowedResponse->getBody());
        $this->assertStringContainsString('class="kw-masha-feedly__page-icon" aria-hidden="true" viewBox="0 0 512 512"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('kw-masha-feedly__page-icon', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-open-feedback', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-open-news', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-unread-count', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('value="unread"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('Neu seit deinem letzten Besuch', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('kw-masha-feedly__news-copy', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('Neuigkeiten', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('seit deinem letzten Besuch', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-edit-priority', $this->widgetMarkup($allowedResponse->getBody()));
        $config->MashaFeedlyTheme = 'serious';
        $config->write();
        $seriousResponse = $this->get('/masha-feedly-widget-test');
        $this->assertStringContainsString('data-theme="serious"', $this->widgetMarkup($seriousResponse->getBody()));
        $this->assertStringNotContainsString('data-similar-url', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringNotContainsString('kw-masha-feedly__similar', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-relation-type', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('Thematisch verwandt', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('kw-masha-feedly__relations-details', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-related-entries', $this->widgetMarkup($allowedResponse->getBody()));
        $widgetMarkup = $this->widgetMarkup($allowedResponse->getBody());
        $this->assertStringContainsString('kw-masha-feedly__entries-dialog" role="dialog" aria-modal="true"', $widgetMarkup);
        $this->assertStringContainsString('kw-masha-feedly__edit-dialog" role="dialog" aria-modal="true"', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-list-heading tabindex="-1"', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-edit-heading tabindex="-1"', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-edit-priority role="img" aria-label="Priorität"', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-comment-status role="status" aria-live="polite" aria-atomic="true"', $widgetMarkup);
        $this->assertStringContainsString('role="dialog" aria-modal="true" aria-labelledby="kw-masha-feedly-onboarding-title"', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-relation-search', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('viewBox="0 0 24 24"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('value="page-open"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-feedback-count', $allowedResponse->getBody());
        $this->assertStringContainsString('Offene Fehler auf der gesamten Website ansehen', $allowedResponse->getBody());
        $this->assertStringContainsString('Offene Fehler auf dieser Seite ansehen', $allowedResponse->getBody());
        $this->assertStringContainsString('Der Globus zeigt offene Fehler auf der gesamten Website', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-open-closed', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-open-closed data-has-closed="false" hidden aria-label=', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertLessThan(strpos($this->widgetMarkup($allowedResponse->getBody()), 'data-masha-feedly-open-closed'), strpos($this->widgetMarkup($allowedResponse->getBody()), 'data-masha-feedly-open-feedback'));
        $this->assertStringContainsString('aria-label="Abgeschlossene Einträge ansehen"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('<span class="kw-masha-feedly__sr-only">abgeschlossene Einträge</span>', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertMatchesRegularExpression('/data-masha-feedly-open-news[^>]*aria-label="Neu seit deinem letzten Besuch"/', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('viewBox="0 0 177800 177800"', $this->widgetMarkup($allowedResponse->getBody()));
        $widgetMarkup = $this->widgetMarkup($allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-open-feedback data-tooltip=', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-open-closed data-has-closed="false" hidden aria-label="Abgeschlossene Einträge ansehen" data-tooltip=', $widgetMarkup);
        $this->assertDoesNotMatchRegularExpression('/data-masha-feedly-open-(?:news|feedback|closed)[^>]*\stitle=/', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-closed-count', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-list-mode', $allowedResponse->getBody());
        $this->assertStringContainsString('data-saved-views-url="/__masha-feedly"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-save-view-url="/__masha-feedly"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-delete-view-url="/__masha-feedly"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-priority-filter', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-saved-view', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-saved-view-name', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-rainbow', $allowedResponse->getBody());
        $this->assertMatchesRegularExpression(
            '/<button class="[^"]*\bkw-masha-feedly__rainbow\b[^"]*" type="button"/',
            $this->widgetMarkup($allowedResponse->getBody())
        );
        $this->assertStringContainsString('data-masha-feedly-rainbow-copy hidden', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-rainbow-title', $allowedResponse->getBody());
        $this->assertStringContainsString('kw-masha-feedly__rainbow-icon', $allowedResponse->getBody());
        $this->assertStringContainsString('Auf dieser Seite keine Fehler', $allowedResponse->getBody());
        $this->assertStringNotContainsString('kw-masha-feedly__heading', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-start-selection', $allowedResponse->getBody());
        $this->assertStringContainsString('name="Attachments[]"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertSame(2, substr_count($this->widgetMarkup($allowedResponse->getBody()), 'name="Attachments[]"'));
        $widgetMarkup = $this->widgetMarkup($allowedResponse->getBody());
        $this->assertStringContainsString('class="kw-masha-feedly__saved-view-select"', $widgetMarkup);
        $this->assertStringContainsString('<details class="kw-masha-feedly__saved-views-details">', $widgetMarkup);
        $this->assertStringContainsString('<summary>Filterkombination speichern und verwalten</summary>', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-saved-view-name', $widgetMarkup);
        $this->assertSame(2, substr_count($widgetMarkup, 'class="kw-masha-feedly__upload-icon"'));
        $this->assertSame(2, substr_count($widgetMarkup, 'type="file" name="Attachments[]"'));
        $this->assertStringContainsString('Dateien anhängen', $widgetMarkup);
        $this->assertStringContainsString('Weitere Dateien anhängen', $widgetMarkup);
        $this->assertStringContainsString('data-masha-feedly-edit-attachments', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-share-active-entry', $this->widgetMarkup($allowedResponse->getBody()));
        $widgetMarkup = $this->widgetMarkup($allowedResponse->getBody());
        foreach ([
            'IM GESPRÄCH',
            'Kommentare',
            'Dein Kommentar',
            'Schreib etwas dazu …',
            'Links mit https:// oder http:// werden automatisch anklickbar.',
            'Kommentar senden',
        ] as $commentLabel) {
            $this->assertStringContainsString($commentLabel, $widgetMarkup);
        }
        $this->assertStringContainsString('data-onboarding-enabled="1"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-onboarding-welcome', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('Das Erscheinungsbild kannst du später in deinen Masha:Feedly-Profileinstellungen ändern.', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertMatchesRegularExpression('/Theme einstellen[^<]*<\/a>/', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertMatchesRegularExpression('/data-masha-feedly-onboarding-welcome[\s\S]*?data-masha-feedly-profile-link href="[^"]*myprofile[^"]*#Root_MashaFeedly/i', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-onboarding-thanks', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertMatchesRegularExpression('/data-masha-feedly-profile-link href="[^"]*myprofile[^\"]*#Root_MashaFeedly/i', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('E-Mail-Benachrichtigungen zu Kommentaren einrichten', $allowedResponse->getBody());
        $this->assertStringContainsString('Profileinstellungen öffnen', $allowedResponse->getBody());
        $this->assertStringContainsString('kw-masha-feedly__onboarding-logo', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-tour-start', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-tour-cancel', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-onboarding-escape-hint', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('Tipp: Esc beendet die Einführung jederzeit.', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('masha-feedly-onboarding.js', $allowedResponse->getBody());
        $this->assertStringContainsString('Schritt 1 von 8: Klicke auf das runde Masha:Feedly-Symbol ganz unten rechts', $allowedResponse->getBody());
        $this->assertStringContainsString('Schritt 2 von 8: Das Fenster ist offen. Klicke jetzt auf das pinke Plus', $allowedResponse->getBody());
        $this->assertStringContainsString('Schritt 7 von 8: Hier findest du Kommentare', $allowedResponse->getBody());
        $this->assertStringContainsString('Schritt 8 von 8: Ändere Status oder Priorität und markiere zuständige Personen.', $allowedResponse->getBody());
        $allowedMember->MashaFeedlyOnboardingCompleted = true;
        $allowedMember->MashaFeedlyShowOnboarding = true;
        $allowedMember->write();
        $restartedTourResponse = $this->get('/masha-feedly-widget-test');
        $this->assertStringContainsString('data-onboarding-enabled="1"', $this->widgetMarkup($restartedTourResponse->getBody()));
        $allowedMember->MashaFeedlyShowOnboarding = false;
        $allowedMember->MashaFeedlyOnboardingCompleted = false;
        $allowedMember->write();
        $this->assertStringContainsString('<span aria-hidden="true">+</span>', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringNotContainsString('Bug oder Hinweis melden', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-entry-form', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-edit-environment', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-edit-environment-details', $allowedResponse->getBody());
        $widgetMarkup = $this->widgetMarkup($allowedResponse->getBody());
        $this->assertStringNotContainsString('data-masha-feedly-edit-assignees', $widgetMarkup);
        $this->assertLessThan(strpos($widgetMarkup, 'kw-masha-feedly__edit-extra-details'), strpos($widgetMarkup, 'data-masha-feedly-close-edit'));
        $this->assertLessThan(strpos($widgetMarkup, 'kw-masha-feedly__relations-details'), strpos($widgetMarkup, 'data-masha-feedly-edit-environment-details'));
        $this->assertLessThan(strpos($widgetMarkup, 'kw-masha-feedly__history'), strpos($widgetMarkup, 'kw-masha-feedly__relations-details'));
        $this->assertStringContainsString('data-comment-url="/__masha-feedly-comment"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-comment-form', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('data-masha-feedly-history', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('<details class="kw-masha-feedly__entry-environment-details kw-masha-feedly__history"', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('<summary id="kw-masha-feedly-history-title">', $this->widgetMarkup($allowedResponse->getBody()));
        $this->assertStringContainsString('Browser- und Seitendetails', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-open-list', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-page-count', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-list-mode', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-category-filter', $allowedResponse->getBody());
        $this->assertStringContainsString('data-masha-feedly-save-toast', $allowedResponse->getBody());
        $this->assertStringContainsString('masha-feedly-entries.js', $allowedResponse->getBody());

        $config->MashaFeedlyAddress = 'sie';
        $config->write();
        $this->assertSame('sie', MashaFeedlyConfigExtension::address());
        $formalResponse = $this->get('/masha-feedly-widget-test');
        $this->assertSame(200, $formalResponse->getStatusCode());
        $formalMarkup = $this->widgetMarkup($formalResponse->getBody());
        $this->assertStringContainsString('data-address="sie"', $formalMarkup);
        $this->assertStringContainsString('Was ist Ihnen aufgefallen?', $formalMarkup);
        $this->assertStringContainsString('Bitte wählen Sie den betroffenen Bereich auf der Seite aus.', $formalMarkup);
        $this->assertStringContainsString('beschreiben Sie den Fehler oder Hinweis', $formalMarkup);
        $this->assertStringNotContainsString('Was ist dir aufgefallen?', $formalMarkup);

        $this->logOut();
        $this->logInAs($otherMember);
        $restrictedResponse = $this->get('/masha-feedly-widget-test');
        $this->assertSame(200, $restrictedResponse->getStatusCode());
        $this->assertStringNotContainsString('window.KWMashaFeedlyWidgetMarkup', $restrictedResponse->getBody());
        $this->assertStringNotContainsString('data-masha-feedly-start-selection', $restrictedResponse->getBody());
    }

    /** Jedes freigegebene Mitglied erhält sein persönliches Theme; ohne Auswahl gilt verspielt. */
    public function testWidgetUsesIndependentPersonalThemesAndPlayfulDefault(): void
    {
        $page = $this->objFromFixture(\Page::class, 'frontendTestPage');
        $page->publishRecursive();
        $firstMember = $this->objFromFixture(Member::class, 'allowed');
        $secondMember = $this->objFromFixture(Member::class, 'notAllowed');
        $firstMember->MashaFeedlyTheme = 'serious';
        $firstMember->write();
        $secondMember->MashaFeedlyTheme = '';
        $secondMember->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$firstMember->ID, (int)$secondMember->ID]);
        $config->MashaFeedlyTheme = 'playful';
        $config->write();

        $this->logInAs($firstMember);
        $firstResponse = $this->get('/masha-feedly-widget-test');
        $this->assertSame(200, $firstResponse->getStatusCode());
        $this->assertStringContainsString('data-theme="serious"', $this->widgetMarkup($firstResponse->getBody()));

        $this->logInAs($secondMember);
        $secondResponse = $this->get('/masha-feedly-widget-test');
        $this->assertSame(200, $secondResponse->getStatusCode());
        $this->assertStringContainsString('data-theme="playful"', $this->widgetMarkup($secondResponse->getBody()));
    }

    /** Auch anonyme Websitebesuche starten im Besuchsmodus höchstens eine Prüfung pro Kalendertag. */
    public function testAnonymousWebsiteVisitStartsConfiguredReminderCheck(): void
    {
        $page = $this->objFromFixture(\Page::class, 'frontendTestPage');
        $page->publishRecursive();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyDueDateReminderMode = 'visitor';
        $config->MashaFeedlyDueDateReminderLastRunDate = null;
        $config->write();

        $response = $this->get('/masha-feedly-widget-test');

        $this->assertSame(200, $response->getStatusCode());
        $reloadedConfig = \SilverStripe\SiteConfig\SiteConfig::get()->byID((int)$config->ID);
        $this->assertSame(
            (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d'),
            (string)$reloadedConfig->MashaFeedlyDueDateReminderLastRunDate
        );
    }

    /** Das Frontend rendert Kostenschätzungsfelder nur für den Betreiberzugang; die Kategorie wird im Formular dynamisch geprüft. */
    public function testEstimateFormMarkupIsLimitedToConfiguredSuperAdmin(): void
    {
        $page = $this->objFromFixture(\Page::class, 'frontendTestPage');
        $page->publishRecursive();
        $this->logInWithPermission('ADMIN');
        $superAdmin = \SilverStripe\Security\Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $superAdmin);
        $superAdmin->Email = 'super-admin@example.test';
        $superAdmin->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$superAdmin->ID]);
        $config->write();

        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)$superAdmin->Email]);
        $superAdminResponse = $this->get('/masha-feedly-widget-test');
        $this->assertSame(200, $superAdminResponse->getStatusCode());
        $superAdminMarkup = $this->widgetMarkup($superAdminResponse->getBody());
        $this->assertStringContainsString('data-can-manage-estimate="1"', $superAdminMarkup);
        $this->assertStringContainsString('data-masha-feedly-create-estimate hidden', $superAdminMarkup);
        $this->assertStringContainsString('data-system-key="estimate_pending"', $superAdminMarkup);

        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', ['someone-else@example.test']);
        $ordinaryAdminResponse = $this->get('/masha-feedly-widget-test');
        $this->assertSame(200, $ordinaryAdminResponse->getStatusCode());
        $ordinaryAdminMarkup = $this->widgetMarkup($ordinaryAdminResponse->getBody());
        $this->assertStringContainsString('data-can-manage-estimate="0"', $ordinaryAdminMarkup);
        $this->assertStringNotContainsString('data-masha-feedly-create-estimate', $ordinaryAdminMarkup);
        $this->assertStringNotContainsString('name="EstimatedCostDuration"', $ordinaryAdminMarkup);
        $this->assertStringNotContainsString('name="EstimatedCostNote"', $ordinaryAdminMarkup);
    }

    /** Admins benötigen eine ausdrückliche Freigabe, um das Widget zu sehen. */
    public function testAdministratorsOnlySeeWidgetWhenExplicitlyAllowed(): void
    {
        $page = $this->objFromFixture(\Page::class, 'frontendTestPage');
        $page->publishRecursive();
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$allowedMember->ID]);
        $config->write();

        $this->logInWithPermission('ADMIN');
        $response = $this->get('/masha-feedly-widget-test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $this->widgetMarkup($response->getBody()));
        $this->assertStringNotContainsString('data-kw-masha-feedly', $response->getBody());
        $this->assertSame(403, $this->get('/__masha-feedly/listEntries')->getStatusCode());

        $admin = Security::getCurrentUser();
        $this->assertInstanceOf(Member::class, $admin);
        $this->assertTrue(MashaFeedlyAdmin::singleton()->canView($admin), 'Admins behalten die CMS-Konfiguration zur Vergabe von Freigaben.');
        $admin->MashaFeedlyOnboardingCompleted = true;
        $admin->write();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$allowedMember->ID, (int)$admin->ID]);
        $config->write();

        $allowedResponse = $this->get('/masha-feedly-widget-test');
        $this->assertSame(200, $allowedResponse->getStatusCode());
        $markup = $this->widgetMarkup($allowedResponse->getBody());
        $this->assertNotSame('', $markup);
        $this->assertStringNotContainsString('data-onboarding-enabled="1"', $markup);
    }

    /** Dekodiert den serverseitig gerenderten Widget-Markup-String für Inhaltstests. */
    private function widgetMarkup(string $body): string
    {
        if (preg_match('/window\.KWMashaFeedlyWidgetMarkup = (".*");/s', $body, $matches) !== 1) {
            return '';
        }
        return (string)json_decode($matches[1], true);
    }
}
