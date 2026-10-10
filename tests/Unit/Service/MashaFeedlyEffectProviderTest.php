<?php

namespace KW\MashaFeedly\Tests\Unit\Service;

use KW\MashaFeedly\Service\MashaFeedlyEffectProvider;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use SilverStripe\View\Requirements_Backend;

/** Prüft die Anbieter-Konfiguration ohne Änderungen an der Projektdatenbank. */
class MashaFeedlyEffectProviderTest extends SapphireTest
{
    /** Ohne Anbieter erhält der Browser keine Abrufadresse und behält den lokalen Effekt-Loader. */
    public function testLoaderDoesNotAdvertiseManifestWithoutProvider(): void
    {
        $previousBase = Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL');
        $previousKey = Environment::getEnv('MASHA_FEEDLY_EFFECTS_API_KEY');
        $previousRequirements = Requirements::backend();
        Requirements::set_backend(new Requirements_Backend());
        try {
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', '');
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', '');
            Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', '');
            Config::modify()->set(MashaFeedlyEffectProvider::class, 'local_provider_base_url', '');
            MashaFeedlyEffectProvider::requireLoader();
            $script = Requirements::get_custom_scripts()['masha-feedly-effects-provider'];
            $this->assertStringContainsString('window.KWMashaFeedlyEffectsManifestURL = "";', $script);
            $this->assertStringContainsString('/client/dist/js/masha-feedly-effects.js', implode('\n', array_keys(Requirements::backend()->getJavascript())));
        } finally {
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', $previousBase);
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', $previousKey);
            Requirements::set_backend($previousRequirements);
        }
    }

    /** CMS-Vorschauen erhalten die gespeicherte Tonwahl unabhängig vom Widget-Markup. */
    public function testLoaderIncludesPersonalSoundPreference(): void
    {
        $previousMember = Security::getCurrentUser();
        $previousRequirements = Requirements::backend();
        Requirements::set_backend(new Requirements_Backend());
        try {
            foreach ([true, false] as $disabled) {
                Security::setCurrentUser(Member::create(['MashaFeedlyDisableSoundEffects' => $disabled]));
                MashaFeedlyEffectProvider::requireLoader();
                $script = Requirements::get_custom_scripts()['masha-feedly-effects-provider'];
                $this->assertStringContainsString('window.KWMashaFeedlyDisableSoundEffects = ' . ($disabled ? 'true' : 'false') . ';', $script);
            }
            Security::setCurrentUser(null);
            MashaFeedlyEffectProvider::requireLoader();
            $this->assertStringContainsString('window.KWMashaFeedlyDisableSoundEffects = false;', Requirements::get_custom_scripts()['masha-feedly-effects-provider']);
        } finally {
            Security::setCurrentUser($previousMember);
            Requirements::set_backend($previousRequirements);
        }
    }

    /** Die Umgebungsvariable hat Vorrang; Unterverzeichnisse bleiben erhalten. */
    public function testEnvironmentOverridesConfiguredBase(): void
    {
        $previous = Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL');
        try {
            Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', 'https://configured.example.test');
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', 'https://provider.example.test/subdirectory/');
            $this->assertSame('https://provider.example.test/subdirectory/__masha-effects/manifest', MashaFeedlyEffectProvider::manifestURL());
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', '');
            $this->assertSame('https://configured.example.test/__masha-effects/manifest', MashaFeedlyEffectProvider::manifestURL());
            Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', '');
            $this->assertSame(Director::absoluteURL('__masha-effects/manifest'), MashaFeedlyEffectProvider::manifestURL());
        } finally { Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', $previous); }
    }

    /** Zugangsdaten, Skriptprotokolle und Query-Parameter sind keine Anbieter-Basis-URLs. */
    public function testUnsafeURLsAreRejected(): void
    {
        $previous = Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL');
        try {
            foreach (['javascript:alert(1)', 'https://user:password@provider.example.test', 'https://provider.example.test?key=private', 'https://provider.example.test#fragment'] as $url) {
                Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', $url);
                try {
                    MashaFeedlyEffectProvider::manifestURL();
                    $this->fail('Ungültige URL wurde akzeptiert.');
                } catch (\InvalidArgumentException $error) {
                    $this->assertStringContainsString('Basis-URL', $error->getMessage());
                }
            }
        } finally { Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', $previous); }
    }
}
