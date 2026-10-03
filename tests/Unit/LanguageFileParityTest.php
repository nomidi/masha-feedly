<?php

namespace KW\MashaFeedly\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use SilverStripe\i18n\i18n;

class LanguageFileParityTest extends TestCase
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
}
