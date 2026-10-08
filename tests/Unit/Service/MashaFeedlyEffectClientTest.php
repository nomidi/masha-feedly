<?php

namespace KW\MashaFeedly\Tests\Unit\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\StreamInterface;
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
        return ['version' => 2, 'maxAge' => 300, 'categories' => [['id' => 'playful', 'name' => 'Verspielt'], ['id' => 'serious', 'name' => 'Sachlich']], 'effects' => [['id' => 'test', 'name' => 'Test', 'categories' => ['playful', 'serious'], 'weight' => 1,
            'files' => ['js' => self::BASE . 'file/1/' . hash('sha256', self::BODY) . '/js']]]];
    }

    /** @param list<Response> $responses Erwartete Netzwerkantworten. @return MashaFeedlyEffectClient Isolierter Client. */
    private function client(array $responses, ?string $avatarIconStoragePath = null): MashaFeedlyEffectClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new MashaFeedlyEffectClient(new Client(['handler' => $stack]), $this->cache, $avatarIconStoragePath);
    }

    /** Katalog und Dateien werden nur einmal geladen; der Browser erhält ausschließlich lokale URLs. */
    public function testPrivateCacheAndLocalURLs(): void
    {
        $client = $this->client([new Response(200, ['Content-Type' => 'application/json'], json_encode($this->catalogue())), new Response(200, ['Content-Type' => 'text/javascript'], self::BODY)]);
        $manifest = $client->manifest();
        $this->assertSame('Verspielt', $manifest['categories'][0]['name']);
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
        $client = $this->client([new Response(200, [], json_encode($this->catalogue())), new Response(200, ['Content-Type' => 'text/javascript'], self::BODY), new Response(200, [], json_encode(['version' => 2, 'maxAge' => 300, 'categories' => [], 'effects' => []]))]);
        $client->file(1, hash('sha256', self::BODY), 'js');
        $this->cache->delete(hash('sha256', self::BASE . "manifest\0" . str_repeat('a', 64)) . '_manifest_v2');
        $this->expectException(RuntimeException::class); $this->expectExceptionCode(404);
        $client->file(1, hash('sha256', self::BODY), 'js');
    }

    /** Abgelaufene Metadaten werden erneuert; gültige Cache-Einträge geben nur ihre Restlaufzeit weiter. */
    public function testExpiredCatalogueIsRefreshed(): void
    {
        $key = hash('sha256', self::BASE . "manifest\0" . str_repeat('a', 64)) . '_manifest_v2';
        $this->cache->set($key, ['expires' => time() - 1, 'data' => ['version' => 2, 'maxAge' => 300, 'categories' => [], 'effects' => []]], 300);
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

    /** Ein Effekt darf nur Kategorien verwenden, die der Anbieter im Katalog ausweist. */
    public function testUnknownEffectCategoryIsRejected(): void
    {
        $data = $this->catalogue();
        $data['effects'][0]['categories'] = ['not-listed'];
        $client = $this->client([new Response(200, [], json_encode($data))]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(502);
        $client->manifest();
    }

    /** Fehler enthalten weder Providerdetails noch Schlüssel, und Weiterleitungen werden nicht verfolgt. */
    public function testFailuresDoNotExposeSecrets(): void
    {
        $client = $this->client([new Response(302, ['Location' => 'https://evil.example.test'], 'Bearer ' . str_repeat('a', 64))]);
        try { $client->manifest(); $this->fail('Weiterleitung wurde akzeptiert.'); }
        catch (RuntimeException $error) {
            $this->assertSame('Der Effekt-Anbieter antwortet mit HTTP 302.', $error->getMessage());
            $this->assertStringNotContainsString(str_repeat('a', 64), $error->getMessage());
        }
        $this->assertCount(1, $this->history);
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', '');
        $this->expectException(RuntimeException::class); $this->expectExceptionCode(503);
        $client->manifest();
    }

    /** Der CMS-Status erklärt eine abgewiesene Schlüsselprüfung ohne Anbieterantwort offenzulegen. */
    public function testRejectedProviderKeyHasActionableSafeMessage(): void
    {
        $client = $this->client([new Response(403, [], 'secret provider response')]);
        try { $client->manifest(); $this->fail('Abgewiesener API-Schlüssel wurde akzeptiert.'); }
        catch (RuntimeException $error) {
            $this->assertSame('Der Effekt-Anbieter lehnt den API-Schlüssel ab. Prüfe MASHA_FEEDLY_EFFECTS_API_KEY.', $error->getMessage());
            $this->assertStringNotContainsString('secret provider response', $error->getMessage());
        }
    }

    /** Manipulierte Inhalte mit einem anderen Hash gelangen nicht in den privaten Ressourcencache. */
    public function testHashMismatchIsRejected(): void
    {
        $client = $this->client([new Response(200, [], json_encode($this->catalogue())), new Response(200, ['Content-Type' => 'text/javascript'], 'different')]);
        $this->expectException(RuntimeException::class); $this->expectExceptionCode(502);
        $client->file(1, hash('sha256', self::BODY), 'js');
    }

    /** Katalog und SVGs werden validiert, gecacht und ausschließlich als lokale Proxy-Adressen ausgegeben. */
    public function testAvatarIconsRequireProviderAndUseVersionedLocalProxy(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1" stroke="#171717"><path d="M0 0h1"/></svg>';
        $version = hash('sha256', $svg);
        $payload = ['version' => 1, 'categories' => [['id' => 'people', 'name' => 'Menschen']], 'icons' => [[
            'id' => 'person', 'name' => 'Person', 'category' => 'people',
            'files' => ['black' => self::BASE . 'icon/person/' . $version . '/black', 'white' => self::BASE . 'icon/person/' . $version . '/white'],
        ]]];
        $client = $this->client([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload)),
            new Response(200, ['Content-Type' => 'image/svg+xml'], $svg),
        ]);
        $catalogue = $client->avatarIcons();
        $this->assertSame('people', $catalogue['categories'][0]['id']);
        $this->assertStringContainsString('/__masha-feedly-effects/icon/person/' . $version . '/black', $catalogue['icons'][0]['files']['black']);
        $this->assertStringNotContainsString('effects.example.test', json_encode($catalogue));
        $file = $client->avatarIcon('person', $version, 'black');
        $this->assertSame($svg, $file['body']);
        $this->assertSame('image/svg+xml', $file['mime']);
        $this->assertCount(2, $this->history);
    }

    /** Liest einen gestreamten Anbieter-Katalog vollständig, auch wenn der HTTP-Stream nur kleine Teile liefert. */
    public function testAvatarIconCatalogueReadsPartialStreamChunks(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0h1"/></svg>';
        $version = hash('sha256', $svg);
        $payload = json_encode(['version' => 1, 'categories' => [['id' => 'people', 'name' => 'Menschen']], 'icons' => [[
            'id' => 'person', 'name' => 'Person', 'category' => 'people',
            'files' => ['black' => self::BASE . 'icon/person/' . $version . '/black', 'white' => self::BASE . 'icon/person/' . $version . '/white'],
        ]]]) . str_repeat(' ', 2048);
        $stream = new class($payload) implements StreamInterface {
            private int $offset = 0;
            public function __construct(private string $content) {}
            public function __toString(): string { return $this->content; }
            public function close(): void { $this->offset = strlen($this->content); }
            public function detach() { $this->close(); return null; }
            public function getSize(): ?int { return strlen($this->content); }
            public function tell(): int { return $this->offset; }
            public function eof(): bool { return $this->offset >= strlen($this->content); }
            public function isSeekable(): bool { return false; }
            public function seek(int $offset, int $whence = SEEK_SET): void { throw new RuntimeException('Stream ist nicht durchsuchbar.'); }
            public function rewind(): void { throw new RuntimeException('Stream ist nicht durchsuchbar.'); }
            public function isWritable(): bool { return false; }
            public function write(string $string): int { throw new RuntimeException('Stream ist schreibgeschützt.'); }
            public function isReadable(): bool { return true; }
            public function read(int $length): string
            {
                $chunk = substr($this->content, $this->offset, min($length, 128));
                $this->offset += strlen($chunk);
                return $chunk;
            }
            public function getContents(): string
            {
                $contents = '';
                while (!$this->eof()) $contents .= $this->read(128);
                return $contents;
            }
            public function getMetadata(?string $key = null) { return $key === null ? [] : null; }
        };
        $client = $this->client([new Response(200, ['Content-Type' => 'application/json'], $stream)]);

        $catalogue = $client->avatarIcons();

        $this->assertSame('person', $catalogue['icons'][0]['id']);
        $this->assertCount(1, $this->history);
    }

    /** Speichert nur ein explizit gewähltes Symbol dauerhaft und liefert es danach ohne Anbieterzugriff aus. */
    public function testSelectedAvatarIconIsPersistedLocally(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0h1"/></svg>';
        $version = hash('sha256', $svg);
        $payload = ['version' => 1, 'categories' => [['id' => 'people', 'name' => 'Menschen']], 'icons' => [[
            'id' => 'person', 'name' => 'Person', 'category' => 'people',
            'files' => ['black' => self::BASE . 'icon/person/' . $version . '/black', 'white' => self::BASE . 'icon/person/' . $version . '/white'],
        ], [
            'id' => 'unused', 'name' => 'Nicht gewählt', 'category' => 'people',
            'files' => ['black' => self::BASE . 'icon/unused/' . $version . '/black', 'white' => self::BASE . 'icon/unused/' . $version . '/white'],
        ]]];
        $directory = sys_get_temp_dir() . '/masha-avatar-icons-' . bin2hex(random_bytes(6));
        try {
            $client = $this->client([
                new Response(200, ['Content-Type' => 'application/json'], json_encode($payload)),
                new Response(200, ['Content-Type' => 'image/svg+xml'], $svg),
                new Response(200, ['Content-Type' => 'image/svg+xml'], $svg),
            ], $directory);
            $client->storeSelectedAvatarIcon('person');
            $this->assertSame($svg, $client->storedAvatarIcon('person', 'black')['body']);
            $this->assertSame($svg, $client->storedAvatarIcon('person', 'white')['body']);
            $this->assertFileDoesNotExist($directory . '/unused-black.svg');
            $this->assertCount(3, $this->history, 'Die erneute lokale Auslieferung darf den Anbieter nicht kontaktieren.');
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) @unlink($file);
            @rmdir($directory);
        }
    }

    /** Ohne ausdrücklich gesetzte HTTPS-Basis und Schlüssel bleibt die Symbolauswahl aus. */
    public function testAvatarIconPickerIsUnavailableWithoutProviderConfiguration(): void
    {
        $previousBase = MashaFeedlyEffectProvider::config()->get('base_url');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', '');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', '');
        Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', '');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionCode(503);
            (new MashaFeedlyEffectClient(null, $this->cache))->avatarIcons();
        } finally {
            Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', $previousBase);
        }
    }
}
