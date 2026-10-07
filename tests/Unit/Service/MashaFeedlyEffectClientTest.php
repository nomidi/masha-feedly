<?php

namespace KW\MashaFeedly\Tests\Unit\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use KW\MashaFeedly\Service\MashaFeedlyEffectProvider;
use RuntimeException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/** Prüft den privaten Servercache, URL-Grenzen und den geheimen Anbieterzugang ohne Netzwerkzugriff. */
class MashaFeedlyEffectClientTest extends SapphireTest
{
    private array $previous = [];
    private array $history = [];
    private Psr16Cache $cache;
    private const BASE = 'https://effects.example.test/tenant/__masha-effects/';
    private const BODY = 'window.effect = true;';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['MASHA_FEEDLY_EFFECTS_API_KEY', 'MASHA_FEEDLY_EFFECTS_BASE_URL', 'MASHA_FEEDLY_EFFECTS_CA_FILE'] as $name) $this->previous[$name] = Environment::getEnv($name);
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', str_repeat('a', 64));
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', '');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_CA_FILE', '');
        Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', 'https://effects.example.test/tenant');
        $this->cache = new Psr16Cache(new ArrayAdapter());
    }

    protected function tearDown(): void
    {
        foreach ($this->previous as $name => $value) Environment::setEnv($name, $value);
        parent::tearDown();
    }

    /** @return array<string, mixed> Gültige Anbieterantwort mit versionierter Ressource. */
    private function catalogue(): array
    {
        return ['version' => 1, 'maxAge' => 300, 'effects' => [['id' => 'test', 'name' => 'Test', 'theme' => 'both', 'weight' => 1,
            'files' => ['js' => self::BASE . 'file/1/' . hash('sha256', self::BODY) . '/js']]]];
    }

    /** @param list<Response> $responses Erwartete Netzwerkantworten. @return MashaFeedlyEffectClient Isolierter Client. */
    private function client(array $responses): MashaFeedlyEffectClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new MashaFeedlyEffectClient(new Client(['handler' => $stack]), $this->cache);
    }

    /** Katalog und Dateien werden nur einmal geladen; der Browser erhält ausschließlich lokale URLs. */
    public function testPrivateCacheAndLocalURLs(): void
    {
        $client = $this->client([new Response(200, ['Content-Type' => 'application/json'], json_encode($this->catalogue())), new Response(200, ['Content-Type' => 'text/javascript'], self::BODY)]);
        $manifest = $client->manifest();
        $this->assertStringContainsString('/__masha-feedly-effects/file/1/', $manifest['effects'][0]['files']['js']);
        $this->assertStringNotContainsString('effects.example.test', json_encode($manifest));
        $this->assertStringNotContainsString(str_repeat('a', 64), json_encode($manifest));
        $again = $client->manifest();
        $this->assertSame($manifest['effects'], $again['effects']);
        $this->assertLessThanOrEqual(300, $again['maxAge']);
        $file = $client->file(1, hash('sha256', self::BODY), 'js');
        $this->assertSame(self::BODY, $file['body']);
        $this->assertSame($file, $client->file(1, hash('sha256', self::BODY), 'js'));
        $this->assertCount(2, $this->history);
        $this->assertSame('Bearer ' . str_repeat('a', 64), $this->history[0]['request']->getHeaderLine('Authorization'));
        $this->assertTrue($this->history[0]['options']['verify']);
        $this->assertFalse($this->history[0]['options']['allow_redirects']);
    }

    /** Ein neuer Zugangsschlüssel darf den Cache des vorherigen Projektschlüssels nicht übernehmen. */
    public function testCacheIsSeparatedByKey(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], json_encode($this->catalogue()));
        $client = $this->client([$response, new Response(200, ['Content-Type' => 'application/json'], json_encode($this->catalogue()))]);
        $client->manifest();
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', str_repeat('b', 64));
        $client->manifest();
        $this->assertCount(2, $this->history);
        $this->assertSame('Bearer ' . str_repeat('b', 64), $this->history[1]['request']->getHeaderLine('Authorization'));
    }

    /** Auch eine bereits gecachte Datei darf nicht mehr geladen werden, wenn sie im neuen Katalog fehlt. */
    public function testUnavailableFileIsRejectedBeforeCache(): void
    {
        $client = $this->client([new Response(200, [], json_encode($this->catalogue())), new Response(200, ['Content-Type' => 'text/javascript'], self::BODY), new Response(200, [], json_encode(['version' => 1, 'maxAge' => 300, 'effects' => []]))]);
        $client->file(1, hash('sha256', self::BODY), 'js');
        $this->cache->delete(hash('sha256', self::BASE . "manifest\0" . str_repeat('a', 64)) . '_manifest');
        $this->expectException(RuntimeException::class); $this->expectExceptionCode(404);
        $client->file(1, hash('sha256', self::BODY), 'js');
    }

    /** Abgelaufene Metadaten werden erneuert; gültige Cache-Einträge geben nur ihre Restlaufzeit weiter. */
    public function testExpiredCatalogueIsRefreshed(): void
    {
        $key = hash('sha256', self::BASE . "manifest\0" . str_repeat('a', 64)) . '_manifest';
        $this->cache->set($key, ['expires' => time() - 1, 'data' => ['version' => 1, 'maxAge' => 300, 'effects' => []]], 300);
        $client = $this->client([new Response(200, [], json_encode($this->catalogue()))]);
        $this->assertCount(1, $client->manifest()['effects']);
        $this->assertCount(1, $this->history);
        $this->cache->set($key, ['expires' => time() + 20, 'data' => $this->catalogue()], 300);
        $remaining = $client->manifest()['maxAge'];
        $this->assertLessThanOrEqual(20, $remaining);
        $this->assertGreaterThan(0, $remaining);
        $this->assertCount(1, $this->history);
    }

    /** Dateiadressen dürfen keine fremden Server, Zugangsdaten, Query-Parameter oder Pfadtricks enthalten. */
    public function testUntrustedManifestURLsAreRejected(): void
    {
        foreach (['https://evil.example.test/file.js', self::BASE . 'file/1/' . hash('sha256', self::BODY) . '/js?token=x', self::BASE . 'file/../secret'] as $url) {
            $data = $this->catalogue(); $data['effects'][0]['files']['js'] = $url;
            $client = $this->client([new Response(200, [], json_encode($data))]);
            try { $client->manifest(); $this->fail('Ungültige Datei wurde akzeptiert.'); }
            catch (RuntimeException $error) { $this->assertSame(502, $error->getCode()); }
        }
    }

    /** Fehler enthalten weder Providerdetails noch Schlüssel, und Weiterleitungen werden nicht verfolgt. */
    public function testFailuresDoNotExposeSecrets(): void
    {
        $client = $this->client([new Response(302, ['Location' => 'https://evil.example.test'], 'Bearer ' . str_repeat('a', 64))]);
        try { $client->manifest(); $this->fail('Weiterleitung wurde akzeptiert.'); }
        catch (RuntimeException $error) {
            $this->assertSame('Effekt-Anbieter nicht verfügbar.', $error->getMessage());
            $this->assertStringNotContainsString(str_repeat('a', 64), $error->getMessage());
        }
        $this->assertCount(1, $this->history);
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', '');
        $this->expectException(RuntimeException::class); $this->expectExceptionCode(503);
        $client->manifest();
    }

    /** Manipulierte Inhalte mit einem anderen Hash gelangen nicht in den privaten Ressourcencache. */
    public function testHashMismatchIsRejected(): void
    {
        $client = $this->client([new Response(200, [], json_encode($this->catalogue())), new Response(200, ['Content-Type' => 'text/javascript'], 'different')]);
        $this->expectException(RuntimeException::class); $this->expectExceptionCode(502);
        $client->file(1, hash('sha256', self::BODY), 'js');
    }
}
