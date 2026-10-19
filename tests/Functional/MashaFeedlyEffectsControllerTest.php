<?php

namespace KW\MashaFeedly\Tests\Functional;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/** Prüft die Feedly-Zugriffsmatrix vor jedem Katalog- und Datei-Cachezugriff. */
class MashaFeedlyEffectsControllerTest extends FunctionalTest
{
    protected static $fixture_file = '../fixtures/MashaFeedly.yml';
    private MashaFeedlyEffectClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $member = $this->objFromFixture(Member::class, 'allowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([$member->ID]); $config->write();
        $this->client = $this->createMock(MashaFeedlyEffectClient::class);
        Injector::inst()->registerService($this->client, MashaFeedlyEffectClient::class);
    }

    /** Die lokale Nutzung bleibt erlaubt, während gesperrte Anbieterinhalte den Client nicht erreichen. */
    public function testDeniedProviderContentDoesNotReachCacheOrDisableLocalFeedly(): void
    {
        $previousKey = \SilverStripe\Core\Environment::getEnv('MASHA_API_KEY');
        \SilverStripe\Core\Environment::setEnv('MASHA_API_KEY', str_repeat('a', 64));
        $injector = Injector::inst();
        $original = $injector->get(\KW\MashaConnect\ServiceAccess::class);
        $access = new class extends \KW\MashaConnect\ServiceAccess {
            public function isAllowed(string $service): bool { return false; }
        };
        $injector->registerService($access, \KW\MashaConnect\ServiceAccess::class);
        $this->client->expects($this->never())->method('manifest');
        $this->client->expects($this->never())->method('file');
        $this->client->expects($this->never())->method('avatarIcons');
        $this->client->expects($this->never())->method('storedAvatarIcon');
        try {
            $member = $this->objFromFixture(Member::class, 'allowed');
            $this->logInAs($member);
            $this->assertTrue(MashaFeedlyConfigExtension::canUse($member));
            foreach (['/__masha-feedly-effects/manifest', '/__masha-feedly-effects/icons', '/__masha-feedly-effects/avatar/person/black'] as $url) {
                $this->assertSame(403, $this->get($url)->getStatusCode());
            }
        } finally {
            \SilverStripe\Core\Environment::setEnv('MASHA_API_KEY', $previousKey);
            $injector->registerService($original, \KW\MashaConnect\ServiceAccess::class);
        }
    }

    /** Gäste und nicht freigegebene Mitglieder erreichen weder Client noch warmen Servercache. */
    public function testDeniedUsersCannotReachCache(): void
    {
        $this->client->expects($this->never())->method('manifest');
        $this->client->expects($this->never())->method('file');
        $this->client->expects($this->never())->method('avatarIcons');
        $this->client->expects($this->never())->method('avatarIcon');
        $this->client->expects($this->never())->method('storedAvatarIcon');
        foreach ([null, $this->objFromFixture(Member::class, 'notAllowed')] as $member) {
            if ($member) $this->logInAs($member); else $this->logOut();
            $this->assertSame(403, $this->get('/__masha-feedly-effects/manifest')->getStatusCode());
            $this->assertSame(403, $this->get('/__masha-feedly-effects/file/1/' . str_repeat('a', 64) . '/js')->getStatusCode());
            $this->assertSame(403, $this->get('/__masha-feedly-effects/icons')->getStatusCode());
            $this->assertSame(403, $this->get('/__masha-feedly-effects/icon/person/' . str_repeat('a', 64) . '/black')->getStatusCode());
            $this->assertSame(403, $this->get('/__masha-feedly-effects/avatar/person/black')->getStatusCode());
        }
    }

    /** Autorisierte Antworten sind sitzungsgebunden und dürfen nicht in öffentliche Browser-/CDN-Caches gelangen. */
    public function testAllowedUserReceivesPrivateResponses(): void
    {
        $this->logInAs($this->objFromFixture(Member::class, 'allowed'));
        $this->client->expects($this->once())->method('manifest')->willReturn(['version' => 1, 'maxAge' => 300, 'effects' => []]);
        $this->client->expects($this->once())->method('file')->willReturn(['body' => 'window.effect = true;', 'mime' => 'text/javascript']);
        $this->client->expects($this->once())->method('avatarIcons')->willReturn(['version' => 1, 'categories' => [], 'icons' => []]);
        $this->client->expects($this->once())->method('avatarIcon')->willReturn(['body' => '<svg/>', 'mime' => 'image/svg+xml']);
        $this->client->expects($this->once())->method('storedAvatarIcon')->with('person', 'black')->willReturn(['body' => '<svg/>', 'mime' => 'image/svg+xml']);
        foreach (['/__masha-feedly-effects/manifest', '/__masha-feedly-effects/file/1/' . str_repeat('a', 64) . '/js', '/__masha-feedly-effects/icons', '/__masha-feedly-effects/icon/person/' . str_repeat('a', 64) . '/black', '/__masha-feedly-effects/avatar/person/black'] as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('private, no-store', $response->getHeader('Cache-Control'));
            $this->assertSame('same-origin', $response->getHeader('Cross-Origin-Resource-Policy'));
            $this->assertNull($response->getHeader('Access-Control-Allow-Origin'));
        }
        $this->logOut();
        $this->assertSame(403, $this->get('/__masha-feedly-effects/file/1/' . str_repeat('a', 64) . '/js')->getStatusCode());
    }

    /** CMS-ADMIN ohne ausdrückliche Feedly-Freigabe erhält ebenfalls keine Effekt-Dateien. */
    public function testAdminWithoutFeedlyAccessIsDenied(): void
    {
        $this->logInWithPermission('ADMIN');
        $this->client->expects($this->never())->method('manifest');
        $this->assertSame(403, $this->get('/__masha-feedly-effects/manifest')->getStatusCode());
    }

    /** Mutierende Anfragen und Anbieterfehler werden ohne interne Details beantwortet. */
    public function testMethodsAndFailures(): void
    {
        $this->logInAs($this->objFromFixture(Member::class, 'allowed'));
        $this->assertSame(405, $this->post('/__masha-feedly-effects/manifest', [])->getStatusCode());
        $this->client->expects($this->once())->method('manifest')->willThrowException(new \RuntimeException('internal private key'));
        $response = $this->get('/__masha-feedly-effects/manifest');
        $this->assertSame(502, $response->getStatusCode());
        $this->assertStringNotContainsString('private key', $response->getBody());
    }
}
