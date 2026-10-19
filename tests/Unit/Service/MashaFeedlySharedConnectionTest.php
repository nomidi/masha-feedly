<?php

declare(strict_types=1);

namespace KW\MashaFeedly\Tests\Unit\Service;

use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use KW\MashaFeedly\Service\MashaFeedlyEffectProvider;
use PHPUnit\Framework\TestCase;
use SilverStripe\Core\Environment;

/** Prüft die gemeinsame Auswahl vor jeder Katalog- oder Cache-Anfrage. */
class MashaFeedlySharedConnectionTest extends TestCase
{
    public function testSharedConfigurationNeedsNoFeedlySpecificAccess(): void
    {
        $before = [];
        foreach (['MASHA_API_KEY', 'MASHA_PROVIDER_URL', 'MASHA_FEEDLY_EFFECTS_API_KEY', 'MASHA_FEEDLY_EFFECTS_BASE_URL'] as $name) $before[$name] = Environment::getEnv($name);
        try {
            Environment::setEnv('MASHA_API_KEY', str_repeat('a', 64)); Environment::setEnv('MASHA_PROVIDER_URL', 'https://provider.example.test/subdir/');
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', ''); Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', '');
            self::assertSame('https://provider.example.test/subdir/__masha-effects/manifest', MashaFeedlyEffectProvider::manifestURL());
            self::assertTrue(MashaFeedlyEffectClient::hasConfiguredProvider());
            Environment::setEnv('MASHA_PROVIDER_URL', 'http://provider.example.test');
            self::assertFalse(MashaFeedlyEffectClient::hasConfiguredProvider());
        } finally { foreach ($before as $name => $value) Environment::setEnv($name, $value); }
    }
}
