<?php

namespace KW\MashaFeedly\Tests\Unit;

use SilverStripe\Dev\SapphireTest;
use Symfony\Component\Yaml\Yaml;
use SilverStripe\i18n\i18n;

class LanguageFileParityTest extends SapphireTest
{
    public function testDeutscheUndEnglischeSprachdateienHabenDieselbenSchluesselUndPlatzhalter(): void
    {
        $languageDirectory = dirname(__DIR__, 2) . '/lang';
        $german = Yaml::parseFile($languageDirectory . '/de.yml')['de']['KW\\MashaFeedly\\Translations'];
        $english = Yaml::parseFile($languageDirectory . '/en.yml')['en']['KW\\MashaFeedly\\Translations'];

        $germanKeys = array_keys($german);
        $englishKeys = array_keys($english);
        sort($germanKeys);
        sort($englishKeys);
        self::assertSame($germanKeys, $englishKeys, 'Beide Sprachdateien müssen dieselben Übersetzungsschlüssel enthalten.');

        foreach ($german as $key => $germanValue) {
            self::assertIsString($germanValue, "$key muss auf Deutsch ein Textwert sein.");
            self::assertIsString($english[$key], "$key muss auf Englisch ein Textwert sein.");

            preg_match_all('/\\{[a-zA-Z][a-zA-Z0-9_]*\\}/', $germanValue, $germanPlaceholders);
            preg_match_all('/\\{[a-zA-Z][a-zA-Z0-9_]*\\}/', $english[$key], $englishPlaceholders);
            sort($germanPlaceholders[0]);
            sort($englishPlaceholders[0]);
            self::assertSame($germanPlaceholders[0], $englishPlaceholders[0], "$key muss dieselben Platzhalter enthalten.");
        }
    }

    public function testBeideSprachenVerwendenIhreJeweiligeUebersetzung(): void
    {
        $german = i18n::with_locale('de_DE', static fn (): string => i18n::_t(
            'KW\\MashaFeedly\\Translations.OPEN_WIDGET',
            'Missing German translation'
        ));
        $english = i18n::with_locale('en_US', static fn (): string => i18n::_t(
            'KW\\MashaFeedly\\Translations.OPEN_WIDGET',
            'Missing English translation'
        ));

        self::assertSame('Masha:Feedly öffnen', $german);
        self::assertSame('Open Masha:Feedly', $english);
    }

    /** Prüft, dass alle Modultexte im von Oberfläche und Controllern verwendeten Bereich stehen. */
    public function testAlleModultexteSindDemVerwendetenUebersetzungsbereichZugeordnet(): void
    {
        foreach (['de', 'en'] as $language) {
            $catalogue = Yaml::parseFile(dirname(__DIR__, 2) . '/lang/' . $language . '.yml')[$language];
            self::assertSame(['KW\\MashaFeedly\\Translations'], array_keys($catalogue));
            foreach (['TOUR_THANKS_TEXT', 'PROFILE_THEME_DESCRIPTION', 'PROFILE_ICON_CLEAR', 'ESTIMATE_REVIEW_HELP', 'RELATIONS_HELP', 'REPORTER_USE_CREATOR'] as $key) {
                self::assertArrayHasKey($key, $catalogue['KW\\MashaFeedly\\Translations']);
            }
        }
    }

    /** Prüft echte Silverstripe-Auflösung statt unbemerkter Ersatztexte für Einführung und Profil. */
    public function testEinfuehrungUndProfilVerwendenInBeidenSprachenDieKatalogtexte(): void
    {
        foreach (['de' => 'de_DE', 'en' => 'en_US'] as $language => $locale) {
            $catalogue = Yaml::parseFile(dirname(__DIR__, 2) . '/lang/' . $language . '.yml')[$language]['KW\\MashaFeedly\\Translations'];
            foreach (['TOUR_THANKS_TEXT', 'PROFILE_THEME_DESCRIPTION', 'PROFILE_ICON_CLEAR', 'ESTIMATE_REVIEW_HELP'] as $key) {
                $translated = i18n::with_locale($locale, static fn (): string => i18n::_t(
                    'KW\\MashaFeedly\\Translations.' . $key,
                    'FEHLENDE_UEBERSETZUNG'
                ));
                self::assertSame($catalogue[$key], $translated, $language . ': ' . $key);
            }
        }
    }


    /** Hält einfache Begriffe und vollständige Handlungsanweisungen in beiden Sprachen fest. */
    public function testOberflaechentexteSindEinheitlichUndNennenDenNaechstenSchritt(): void
    {
        $directory = dirname(__DIR__, 2) . '/lang/';
        $german = Yaml::parseFile($directory . 'de.yml')['de']['KW\\MashaFeedly\\Translations'];
        $english = Yaml::parseFile($directory . 'en.yml')['en']['KW\\MashaFeedly\\Translations'];
        self::assertSame('Meldung speichern', $german['ENTRY_SAVE']);
        self::assertSame('Save report', $english['ENTRY_SAVE']);
        self::assertSame('Profileinstellungen öffnen', $german['TOUR_THEME_SETTINGS_LINK']);
        self::assertSame('Danke-Animation', $german['PROFILE_THEME']);
        self::assertSame('Profilfarbe', $german['PROFILE_COLOR']);
        self::assertStringContainsString('„Änderungen speichern“', $german['ESTIMATE_REVIEW_HELP']);
        self::assertStringContainsString('“Save changes”', $english['ESTIMATE_REVIEW_HELP']);
        self::assertStringContainsString('rechten Seitenrand', $german['EMAIL_ACCESS_GUIDE']);
        self::assertStringNotContainsString('Titel', $german['EMAIL_ACCESS_REPORT_DETAILS']);
        self::assertStringNotContainsString('title', $english['EMAIL_ACCESS_REPORT_DETAILS']);
        self::assertStringNotContainsString('Animation', $german['TOUR_THANKS_TEXT']);
        foreach (['TOUR_BLOCKED_PLUS', 'TOUR_BLOCKED_FORM', 'TOUR_BLOCKED_ENTRIES', 'SEND_POST_DU', 'SEND_POST_SIE'] as $key) {
            self::assertDoesNotMatchRegularExpression('/Freigang|Kaffee|heute der Star|POST/', $german[$key]);
            self::assertDoesNotMatchRegularExpression('/coffee|the star|POST/', $english[$key]);
        }
    }

    /** Prüft, dass das Onboarding formelle Texte für Du/Sie-Einstellungen bereitstellt. */
    public function testOnboardingEnthaeltFormelleVariantenFuerDieSieEinstellung(): void
    {
        $directory = dirname(__DIR__, 2) . '/lang/';
        foreach (['de', 'en'] as $language) {
            $catalogue = Yaml::parseFile($directory . $language . '.yml')[$language]['KW\\MashaFeedly\\Translations'];
            foreach ([
                'TOUR_MOBILE_NOTICE', 'TOUR_WELCOME_EYEBROW', 'TOUR_WELCOME_TEXT', 'TOUR_WELCOME_WORKFLOW', 'TOUR_THEME_SETTINGS',
                'TOUR_STEP_ICON', 'TOUR_STEP_PLUS', 'TOUR_STEP_TARGET', 'TOUR_STEP_FORM',
                'TOUR_STEP_VIEW_ENTRIES', 'TOUR_STEP_OPEN_ENTRY', 'TOUR_STEP_COMMENT_ENTRY',
                'TOUR_STEP_MANAGE_ENTRY', 'TOUR_BLOCKED_ICON', 'TOUR_BLOCKED_PLUS',
                'TOUR_BLOCKED_TARGET', 'TOUR_BLOCKED_FORM', 'TOUR_BLOCKED_ENTRIES',
                'TOUR_BLOCKED_ENTRY', 'TOUR_BLOCKED_COMMENT', 'TOUR_BLOCKED_MANAGE',
                'TOUR_THANKS_TEXT', 'TOUR_THANKS_FORM_INTRO', 'TOUR_THANKS_PREVIEW_TITLE',
                'TOUR_THANKS_PREVIEW_HELP', 'TOUR_THANKS_COLOR_LABEL', 'TOUR_THANKS_ICON_HELP',
                'TOUR_THANKS_EFFECTS_SUMMARY', 'TOUR_THANKS_EFFECTS_HELP',
                'TOUR_THANKS_EMAIL_SUMMARY', 'TOUR_THANKS_EMAIL_HELP',
                'TOUR_THANKS_EMAIL_MASTER_HELP', 'TOUR_SELECTION_TARGET',
                'TOUR_RESTART_ERROR', 'TOUR_THANKS_EXAMPLE_FALLBACK',
                'TOUR_PREFERENCES_SAVING', 'TOUR_PREFERENCES_SAVED', 'TOUR_PREFERENCES_ERROR',
                'PROFILE_ADDRESS', 'PROFILE_ADDRESS_DESCRIPTION',
            ] as $key) {
                self::assertArrayHasKey($key . '_SIE', $catalogue, $language . ': ' . $key);
                self::assertSame($catalogue[$key . '_SIE'], i18n::with_locale(
                    $language === 'de' ? 'de_DE' : 'en_US',
                    static fn (): string => i18n::_t('KW\\MashaFeedly\\Translations.' . $key . '_SIE', 'MISSING')
                ));
            }
        }
    }

}
