<?php

namespace KW\MashaFeedly\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;

/** Holt autorisierte Anbieter-Ressourcen und cached sie außerhalb des öffentlichen Webverzeichnisses. */
class MashaFeedlyEffectClient
{
    /** @param ClientInterface|null $http Austauschbarer HTTP-Transport. @param CacheInterface|null $cache Privater Servercache. */
    public function __construct(private ?ClientInterface $http = null, private ?CacheInterface $cache = null)
    {
        $this->http ??= new Client();
        $this->cache ??= Injector::inst()->get(CacheInterface::class . '.MashaFeedlyEffects');
    }

    /**
     * Liefert validierte Metadaten mit ausschließlich lokalen Proxy-URLs.
     * @return array{version: int, maxAge: int, effects: list<array<string, mixed>>} Browser-Katalog ohne Schlüssel.
     * @throws RuntimeException Bei fehlender Konfiguration oder ungültiger Anbieterantwort.
     */
    public function manifest(): array
    {
        $data = $this->catalogue();
        foreach ($data['effects'] as &$effect) {
            foreach ($effect['files'] as $type => &$url) {
                $parts = $this->fileParameters($url);
                $url = Director::absoluteURL('__masha-feedly-effects/file/' . implode('/', $parts));
            }
            unset($url);
        }
        unset($effect);
        return $data;
    }

    /**
     * Liefert nur Dateien, die im aktuell gültigen Katalog freigegeben sind.
     * @param int $id Anbieter-Datensatz.
     * @param string $version SHA256-Dateiversion.
     * @param string $type Ressourcentyp.
     * @return array{body: string, mime: string} Validierte Ressource aus dem privaten Cache oder vom Anbieter.
     * @throws RuntimeException Bei unbekannter Zuordnung oder Anbieterfehler.
     */
    public function file(int $id, string $version, string $type): array
    {
        if ($id <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $version) || !in_array($type, ['js', 'css', 'image'], true)) throw new RuntimeException('Unbekannte Ressource.', 404);
        $endpoint = 'file/' . $id . '/' . $version . '/' . $type;
        $expected = $this->providerURL($endpoint);
        $available = false;
        foreach ($this->catalogue()['effects'] as $effect) {
            if (($effect['files'][$type] ?? '') === $expected) $available = true;
        }
        if (!$available) throw new RuntimeException('Unbekannte Ressource.', 404);
        $key = $this->cachePrefix() . '_file_' . hash('sha256', $endpoint);
        $cached = $this->cache->get($key);
        if (is_array($cached)) return $cached;
        $file = $this->request($endpoint);
        if (!hash_equals($version, hash('sha256', $file['body']))) throw new RuntimeException('Ungültige Dateiversion.', 502);
        $mime = explode(';', $file['mime'])[0];
        $allowed = ['js' => ['text/javascript', 'application/javascript'], 'css' => ['text/css'], 'image' => ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp', 'image/gif']];
        if (!in_array($mime, $allowed[$type], true)) throw new RuntimeException('Ungültiger Dateityp.', 502);
        $file['mime'] = $mime;
        $this->cache->set($key, $file, 86400);
        return $file;
    }

    /** @return array<string, mixed> Gültiger Anbieter-Katalog; die Restlaufzeit begrenzt den Browsercache. */
    private function catalogue(): array
    {
        $key = $this->cachePrefix() . '_manifest';
        $cached = $this->cache->get($key);
        if (is_array($cached) && ($cached['expires'] ?? 0) > time()) {
            $cached['data']['maxAge'] = max(1, $cached['expires'] - time());
            return $cached['data'];
        }
        $response = $this->request('manifest');
        try { $data = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $error) { throw new RuntimeException('Ungültiger Effekt-Katalog.', 502); }
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['effects'] ?? null) || count($data['effects']) > 1000) throw new RuntimeException('Ungültiger Effekt-Katalog.', 502);
        $effects = [];
        foreach ($data['effects'] as $effect) {
            if (!is_array($effect) || !is_string($effect['id'] ?? null) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/D', $effect['id']) || !is_string($effect['name'] ?? null)
                || !in_array($effect['theme'] ?? '', ['playful', 'serious', 'both'], true) || !is_int($effect['weight'] ?? null) || $effect['weight'] < 1 || $effect['weight'] > 100
                || !is_array($effect['files'] ?? null) || !isset($effect['files']['js'])) throw new RuntimeException('Ungültiger Effekt-Katalog.', 502);
            foreach ($effect['files'] as $type => $url) {
                if (!in_array($type, ['js', 'css', 'image'], true) || !is_string($url) || $this->fileParameters($url)[2] !== $type) throw new RuntimeException('Ungültige Effekt-Datei.', 502);
            }
            $effects[] = array_intersect_key($effect, array_flip(['id', 'name', 'theme', 'weight', 'files']));
        }
        $ttl = min(300, max(1, (int)($data['maxAge'] ?? 60)));
        $result = ['version' => 1, 'maxAge' => $ttl, 'effects' => $effects];
        $this->cache->set($key, ['expires' => time() + $ttl, 'data' => $result], $ttl);
        return $result;
    }

    /** @param string $url Anbieter-Datei-URL. @return array{int, string, string} Ausschließlich ID, Hash und Typ. */
    private function fileParameters(string $url): array
    {
        $prefix = $this->providerURL('file/');
        if (!str_starts_with($url, $prefix) || !preg_match('/^([1-9][0-9]*)\/([a-f0-9]{64})\/(js|css|image)$/D', substr($url, strlen($prefix)), $match)) throw new RuntimeException('Ungültige Effekt-Datei.', 502);
        return [(int)$match[1], $match[2], $match[3]];
    }

    /** @param string $endpoint Fest definierter Anbieter-Pfad. @return string URL ohne Benutzerparameter. */
    private function providerURL(string $endpoint): string
    {
        return substr(MashaFeedlyEffectProvider::manifestURL(), 0, -strlen('manifest')) . $endpoint;
    }

    /** @return string API-Schlüssel ausschließlich aus der serverseitigen Umgebung. */
    private function apiKey(): string
    {
        $key = trim((string)Environment::getEnv('MASHA_FEEDLY_EFFECTS_API_KEY'));
        if (!preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $key)) throw new RuntimeException('Effekt-Anbieter nicht konfiguriert.', 503);
        return $key;
    }

    /** @return string Cache-Namensraum getrennt nach Anbieter und Zugangsschlüssel. */
    private function cachePrefix(): string { return hash('sha256', MashaFeedlyEffectProvider::manifestURL() . "\0" . $this->apiKey()); }

    /**
     * Ruft nur konfigurierte Anbieter-URLs ab; Weiterleitungen dürfen keinen Schlüssel an fremde Hosts weiterreichen.
     * @param string $endpoint Validierter Katalog- oder Datei-Endpunkt.
     * @return array{body: string, mime: string} Begrenzte Antwort ohne Authentifizierungsdaten.
     * @throws RuntimeException Bei Transportfehlern; technische Details mit Schlüsseln werden nicht weitergegeben.
     */
    private function request(string $endpoint): array
    {
        $url = $this->providerURL($endpoint);
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey(), 'Accept' => '*/*'];
        try {
            // Bei gemeinsamer Installation entfällt HTTPS gegen den eigenen Server; die Schlüsselprüfung bleibt gleich.
            $localBase = trim((string)MashaFeedlyEffectProvider::config()->get('local_provider_base_url'));
            $local = rtrim($localBase, '/') . '/__masha-effects/';
            if ($localBase !== '' && str_starts_with($url, $local) && class_exists(\KW\MashaEffects\Control\EffectsController::class)) {
                $request = new HTTPRequest('GET', $url);
                $request->addHeader('Authorization', $headers['Authorization']);
                $controller = \KW\MashaEffects\Control\EffectsController::create();
                if ($endpoint === 'manifest') $response = $controller->manifest($request);
                else {
                    [$id, $version, $type] = explode('/', substr($endpoint, strlen('file/')));
                    $request->setRouteParams(['ID' => $id, 'Version' => $version, 'Type' => $type]);
                    $response = $controller->file($request);
                }
                $status = $response->getStatusCode(); $body = $response->getBody(); $mime = (string)$response->getHeader('Content-Type');
            } else {
                if (parse_url($url, PHP_URL_SCHEME) !== 'https') throw new RuntimeException('HTTPS erforderlich.', 503);
                $ca = trim((string)Environment::getEnv('MASHA_FEEDLY_EFFECTS_CA_FILE'));
                if ($ca !== '' && !is_file($ca)) throw new RuntimeException('Ungültige TLS-Konfiguration.', 503);
                $response = $this->http->request('GET', $url, ['headers' => $headers, 'allow_redirects' => false, 'http_errors' => false,
                    'timeout' => 5, 'connect_timeout' => 2, 'verify' => $ca ?: true, 'stream' => true]);
                $status = $response->getStatusCode(); $body = $response->getBody()->read(8 * 1024 * 1024 + 1); $mime = $response->getHeaderLine('Content-Type');
                $response->getBody()->close();
            }
            if ($status !== 200 || strlen($body) > 8 * 1024 * 1024) throw new RuntimeException('Effekt-Anbieter nicht verfügbar.', 502);
            return ['body' => $body, 'mime' => $mime];
        } catch (\Throwable $error) {
            // Guzzle-Meldungen können den Authorization-Header enthalten; niemals an Browser oder Protokolle weiterreichen.
            throw new RuntimeException('Effekt-Anbieter nicht verfügbar.', 502);
        }
    }
}
