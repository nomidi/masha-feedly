<?php

namespace KW\MashaFeedly\Service;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;

/** Konfiguriert die öffentliche URL des unabhängigen Effekt-Anbieters. */
class MashaFeedlyEffectProvider
{
    use Configurable;

    private static $base_url = '';
    private static $local_provider_base_url = '';

    /**
     * Liefert die Manifest-URL; ohne Konfiguration wird dieselbe Website verwendet.
     * @return string Öffentliche Manifest-URL.
     * @throws \InvalidArgumentException Wenn die Basis-URL ungültig ist.
     */
    public static function manifestURL(): string
    {
        $base = trim((string)(Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL') ?: self::config()->get('base_url')));
        if ($base === '') return Director::absoluteURL('__masha-effects/manifest');
        $parts = parse_url($base);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('MASHA_FEEDLY_EFFECTS_BASE_URL muss eine HTTP(S)-Basis-URL ohne Zugangsdaten sein.');
        }
        return rtrim($base, '/') . '/__masha-effects/manifest';
    }

    /** Bindet den universellen Loader ein; Effekt-Dateien kommen vom Anbieter. @return void */
    public static function requireLoader(): void
    {
        $url = Director::absoluteURL('__masha-feedly-effects/manifest');
        // CMS-Vorschauen benötigen die persönliche Tonwahl auch ohne eingeblendetes Widget.
        $disableSound = (bool)(Security::getCurrentUser() ? Security::getCurrentUser()->MashaFeedlyDisableSoundEffects : false);
        Requirements::customScript(
            'window.KWMashaFeedlyEffectsManifestURL = ' . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';'
                . 'window.KWMashaFeedlyDisableSoundEffects = ' . json_encode($disableSound) . ';',
            'masha-feedly-effects-provider'
        );
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-effects.js');
    }
}
