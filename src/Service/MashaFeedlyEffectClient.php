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
use SilverStripe\i18n\i18n;

/** Holt autorisierte Anbieter-Ressourcen und cached sie außerhalb des öffentlichen Webverzeichnisses. */
class MashaFeedlyEffectClient
{
    /** Liefert die Anbieter-Kategorien für Silverstripe-Auswahlfelder und bewahrt die alten Auswahlwerte im Fehlerfall.
     * @param string|null $preserve Gespeicherte Kategorie, die beim Entfernen noch sichtbar bleiben soll.
     * @return array<string, string> Kennung zu Anzeigename.
     */
    public static function themeOptions(?string $preserve = null): array
    {
        $options = [
            'playful' => i18n::_t('KW\\MashaFeedly\\Translations.CONFIG_THEME_PLAYFUL', 'Verspielt'),
            'serious' => i18n::_t('KW\\MashaFeedly\\Translations.CONFIG_THEME_SERIOUS', 'Sachlich'),
        ];
        try {
            $client = new self();
            $options = [];
            foreach ($client->catalogue()['categories'] as $category) $options[$category['id']] = $category['name'];
            if (!$options) $options = [
                'playful' => i18n::_t('KW\\MashaFeedly\\Translations.CONFIG_THEME_PLAYFUL', 'Verspielt'),
                'serious' => i18n::_t('KW\\MashaFeedly\\Translations.CONFIG_THEME_SERIOUS', 'Sachlich'),
            ];
        } catch (\Throwable) {
            // Profile und Konfiguration bleiben bei einem fehlenden Anbieter weiter bearbeitbar.
            $options = [
                'playful' => i18n::_t('KW\\MashaFeedly\\Translations.CONFIG_THEME_PLAYFUL', 'Verspielt'),
                'serious' => i18n::_t('KW\\MashaFeedly\\Translations.CONFIG_THEME_SERIOUS', 'Sachlich'),
            ];
        }
        if ($preserve && preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $preserve) && !isset($options[$preserve])) {
            $options[$preserve] = $preserve . ' (gespeicherte Auswahl)';
        }
        return $options;
    }

    /** @param ClientInterface|null $http Austauschbarer HTTP-Transport. @param CacheInterface|null $cache Privater Servercache. */
    public function __construct(private ?ClientInterface $http = null, private ?CacheInterface $cache = null, private ?string $avatarIconStoragePath = null)
    {
        $this->http ??= new Client();
        $this->cache ??= Injector::inst()->get(CacheInterface::class . '.MashaFeedlyEffects');
    }

    /**
     * Liefert validierte Metadaten mit ausschließlich lokalen Proxy-URLs.
     * @return array{version: int, maxAge: int, categories: list<array{id: string, name: string}>, effects: list<array<string, mixed>>} Browser-Katalog ohne Schlüssel.
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

    /** Liefert den Icon-Katalog nur bei explizit gesetztem externen Anbieter und gültiger Anbieterantwort. @return array{version: int, categories: list<array{id: string, name: string}>, icons: list<array{id: string, name: string, category: string, files: array{black: string, white: string}}>} */
    public function avatarIcons(): array
    {
        $this->requireConfiguredProvider();
        $key = $this->cachePrefix() . '_avatar_icons_v1';
        $cached = $this->cache->get($key);
        if (is_array($cached) && ($cached['expires'] ?? 0) > time()) return $cached['data'];

        $response = $this->request('icons');
        try { $data = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new RuntimeException('Der Profil-Icon-Katalog enthält kein gültiges JSON.', 502); }
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['categories'] ?? null)
            || !is_array($data['icons'] ?? null) || count($data['categories']) > 50 || count($data['icons']) > 500) {
            throw new RuntimeException('Die Version oder Struktur des Profil-Icon-Katalogs ist ungültig.', 502);
        }
        $categories = [];
        foreach ($data['categories'] as $category) {
            if (!is_array($category) || !is_string($category['id'] ?? null) || !preg_match('/^[a-z][a-z0-9_-]{0,49}$/D', $category['id'])
                || !is_string($category['name'] ?? null) || trim($category['name']) === '' || isset($categories[$category['id']])) {
                throw new RuntimeException('Ungültige Profil-Icon-Kategorie.', 502);
            }
            $categories[$category['id']] = ['id' => $category['id'], 'name' => $category['name']];
        }
        $icons = [];
        foreach ($data['icons'] as $icon) {
            if (!is_array($icon) || !is_string($icon['id'] ?? null) || !preg_match('/^[a-z0-9-]{1,50}$/D', $icon['id'])
                || !is_string($icon['name'] ?? null) || trim($icon['name']) === '' || !is_string($icon['category'] ?? null)
                || !isset($categories[$icon['category']]) || !is_array($icon['files'] ?? null) || count($icon['files']) !== 2) {
                throw new RuntimeException('Ein Eintrag im Profil-Icon-Katalog ist ungültig.', 502);
            }
            $files = [];
            foreach (['black', 'white'] as $color) {
                $url = $icon['files'][$color] ?? null;
                $prefix = $this->providerURL('icon/');
                if (!is_string($url) || !str_starts_with($url, $prefix)
                    || !preg_match('/^([a-z0-9-]{1,50})\/([a-f0-9]{64})\/' . $color . '$/D', substr($url, strlen($prefix)), $parts)
                    || $parts[1] !== $icon['id']) throw new RuntimeException('Ungültige Profil-Icon-Datei.', 502);
                $files[$color] = Director::absoluteURL('__masha-feedly-effects/icon/' . $icon['id'] . '/' . $parts[2] . '/' . $color);
            }
            $icons[$icon['id']] = ['id' => $icon['id'], 'name' => $icon['name'], 'category' => $icon['category'], 'files' => $files];
        }
        $result = ['version' => 1, 'categories' => array_values($categories), 'icons' => array_values($icons)];
        $this->cache->set($key, ['expires' => time() + 300, 'data' => $result], 300);
        return $result;
    }

    /** Liefert nur die zum aktuellen Icon-Katalog gehörende Farbvariante. @return array{body: string, mime: string} Versionierte SVG-Datei. */
    public function avatarIcon(string $id, string $version, string $color): array
    {
        $this->requireConfiguredProvider();
        if (!preg_match('/^[a-z0-9-]{1,50}$/D', $id) || !preg_match('/^[a-f0-9]{64}$/D', $version)
            || !in_array($color, ['black', 'white'], true)) throw new RuntimeException('Unbekannte Ressource.', 404);
        $endpoint = 'icon/' . $id . '/' . $version . '/' . $color;
        $expected = Director::absoluteURL('__masha-feedly-effects/' . $endpoint);
        $found = false;
        foreach ($this->avatarIcons()['icons'] as $icon) if (($icon['id'] ?? '') === $id && ($icon['files'][$color] ?? '') === $expected) $found = true;
        if (!$found) throw new RuntimeException('Unbekannte Ressource.', 404);
        $cacheKey = $this->cachePrefix() . '_avatar_icon_' . hash('sha256', $endpoint);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) return $cached;
        $file = $this->request($endpoint);
        if (!hash_equals($version, hash('sha256', $file['body'])) || explode(';', $file['mime'])[0] !== 'image/svg+xml'
            || !str_contains($file['body'], '<svg') || preg_match('/<script|<!DOCTYPE|<!ENTITY|onload\s*=|javascript:/i', $file['body'])) {
            throw new RuntimeException('Ungültige Profil-Icon-Datei.', 502);
        }
        $file['mime'] = 'image/svg+xml';
        $this->cache->set($cacheKey, $file, 86400);
        return $file;
    }

    /**
     * Lädt ausschließlich ein explizit ausgewähltes Symbol samt Kontrastvarianten in den privaten Projektordner.
     * @param string $id Kennung des ausgewählten Symbols.
     * @return void
     * @throws RuntimeException Bei ungültigem Symbol, Anbieterfehler oder fehlendem Schreibzugriff.
     */
    public function storeSelectedAvatarIcon(string $id): void
    {
        if (!preg_match('/^[a-z0-9-]{1,50}$/D', $id)) throw new RuntimeException('Unbekanntes Profil-Icon.', 404);
        $icon = null;
        foreach ($this->avatarIcons()['icons'] as $candidate) if ($candidate['id'] === $id) { $icon = $candidate; break; }
        if (!$icon) throw new RuntimeException('Unbekanntes Profil-Icon.', 404);
        foreach (['black', 'white'] as $color) {
            $parts = $this->iconParameters($icon['files'][$color]);
            $file = $this->avatarIcon($id, $parts[1], $color);
            $path = $this->storedAvatarIconPath($id, $color);
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Das Profil-Icon konnte nicht lokal gespeichert werden.', 500);
            @chmod($directory, 0700);
            $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
            if (file_put_contents($temporary, $file['body'], LOCK_EX) === false || !rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Das Profil-Icon konnte nicht lokal gespeichert werden.', 500);
            }
            @chmod($path, 0600);
        }
    }

    /**
     * Gibt eine zuvor geprüfte, dauerhaft lokal gespeicherte Icon-Variante zurück.
     * @param string $id Gespeicherte Symbolkennung.
     * @param string $color Kontrastvariante.
     * @return array{body: string, mime: string} Lokal gespeicherte SVG-Datei.
     * @throws RuntimeException Wenn Datei oder Parameter ungültig sind.
     */
    public function storedAvatarIcon(string $id, string $color): array
    {
        if (!preg_match('/^[a-z0-9-]{1,50}$/D', $id) || !in_array($color, ['black', 'white'], true)) throw new RuntimeException('Unbekannte Ressource.', 404);
        $path = $this->storedAvatarIconPath($id, $color);
        $body = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($body) || !str_contains($body, '<svg') || preg_match('/<script|<!DOCTYPE|<!ENTITY|onload\\s*=|javascript:/i', $body)) throw new RuntimeException('Unbekannte Ressource.', 404);
        return ['body' => $body, 'mime' => 'image/svg+xml'];
    }

    /**
     * Entfernt die lokal gespeicherten Varianten, wenn kein Profil das Symbol mehr ausgewählt hat.
     * @param string $id Nicht mehr verwendete Symbolkennung.
     * @return void
     */
    public function removeStoredAvatarIcon(string $id): void
    {
        if (!preg_match('/^[a-z0-9-]{1,50}$/D', $id)) return;
        foreach (['black', 'white'] as $color) {
            $path = $this->storedAvatarIconPath($id, $color);
            if (is_file($path)) @unlink($path);
        }
    }

    /** @param string $id Geprüfte Symbolkennung. @param string $color Geprüfte Kontrastvariante. @return string Privater Dateipfad. */
    private function storedAvatarIconPath(string $id, string $color): string
    {
        return rtrim($this->avatarIconStoragePath ?? Director::baseFolder() . '/private/masha-feedly-avatar-icons', '/') . '/' . $id . '-' . $color . '.svg';
    }

    /** @return list<string> IDs, Hash und Farbvariante aus einer bereits geprüften Proxy-URL. */
    private function iconParameters(string $url): array
    {
        if (preg_match('~/icon/([a-z0-9-]{1,50})/([a-f0-9]{64})/(black|white)$~D', $url, $matches)) return [$matches[1], $matches[2], $matches[3]];
        throw new RuntimeException('Ungültige Profil-Icon-Datei.', 502);
    }

    /** Stellt sicher, dass die Auswahl nicht über die lokale Fallback-Konfiguration angeboten wird. */
    public function requireConfiguredProvider(): void
    {
        if (!self::hasConfiguredProvider()) throw new RuntimeException('Die Profil-Icon-Auswahl ist nicht verfügbar.', 503);
    }

    /** Prüft die explizite Anbieter-Konfiguration, ohne eine Netzwerkverbindung aufzubauen. */
    public static function hasConfiguredProvider(): bool
    {
        $base = trim((string)(Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL') ?: MashaFeedlyEffectProvider::config()->get('base_url')));
        $key = trim((string)Environment::getEnv('MASHA_FEEDLY_EFFECTS_API_KEY'));
        $parts = $base !== '' ? parse_url($base) : false;
        return (bool)($parts && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment']) && preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $key));
    }

    /** @return array<string, mixed> Gültiger Anbieter-Katalog; die Restlaufzeit begrenzt den Browsercache. */
    private function catalogue(): array
    {
        $key = $this->cachePrefix() . '_manifest_v2';
        $cached = $this->cache->get($key);
        if (is_array($cached) && ($cached['expires'] ?? 0) > time()) {
            $cached['data']['maxAge'] = max(1, $cached['expires'] - time());
            return $cached['data'];
        }
        $response = $this->request('manifest');
        try { $data = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $error) { throw new RuntimeException('Ungültiger Effekt-Katalog.', 502); }
        if (!is_array($data) || ($data['version'] ?? null) !== 2 || !is_array($data['categories'] ?? null) || count($data['categories']) > 100
            || !is_array($data['effects'] ?? null) || count($data['effects']) > 1000) throw new RuntimeException('Ungültiger Effekt-Katalog.', 502);
        $categories = [];
        foreach ($data['categories'] as $category) {
            if (!is_array($category) || !is_string($category['id'] ?? null) || !preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $category['id'])
                || !is_string($category['name'] ?? null) || trim($category['name']) === '' || isset($categories[$category['id']])) {
                throw new RuntimeException('Ungültige Effekt-Kategorie.', 502);
            }
            $categories[$category['id']] = ['id' => $category['id'], 'name' => $category['name']];
        }
        $effects = [];
        foreach ($data['effects'] as $effect) {
            if (!is_array($effect) || !is_string($effect['id'] ?? null) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/D', $effect['id']) || !is_string($effect['name'] ?? null)
                || !is_array($effect['categories'] ?? null) || !$effect['categories'] || !is_int($effect['weight'] ?? null) || $effect['weight'] < 1 || $effect['weight'] > 100
                || !is_array($effect['files'] ?? null) || !isset($effect['files']['js'])) throw new RuntimeException('Ungültiger Effekt-Katalog.', 502);
            foreach ($effect['categories'] as $categoryID) {
                if (!is_string($categoryID) || !isset($categories[$categoryID])) throw new RuntimeException('Ungültige Effekt-Kategorie.', 502);
            }
            foreach ($effect['files'] as $type => $url) {
                if (!in_array($type, ['js', 'css', 'image'], true) || !is_string($url) || $this->fileParameters($url)[2] !== $type) throw new RuntimeException('Ungültige Effekt-Datei.', 502);
            }
            if (array_key_exists('hasSound', $effect) && !is_bool($effect['hasSound'])) throw new RuntimeException('Ungültige Sound-Kennzeichnung.', 502);
            $effects[] = array_intersect_key($effect, array_flip(['id', 'name', 'categories', 'weight', 'files', 'hasSound']));
        }
        $ttl = min(300, max(1, (int)($data['maxAge'] ?? 60)));
        $result = ['version' => 2, 'maxAge' => $ttl, 'categories' => array_values($categories), 'effects' => $effects];
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
        if (!preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $key)) throw new RuntimeException('Effekt-Anbieter nicht vollständig konfiguriert. Prüfe MASHA_FEEDLY_EFFECTS_API_KEY.', 503);
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
                elseif ($endpoint === 'icons') $response = $controller->icons($request);
                elseif (str_starts_with($endpoint, 'icon/')) {
                    [$id, $version, $color] = explode('/', substr($endpoint, strlen('icon/')));
                    $request->setRouteParams(['ID' => $id, 'Version' => $version, 'Color' => $color]);
                    $response = $controller->icon($request);
                }
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
                $status = $response->getStatusCode();
                $mime = $response->getHeaderLine('Content-Type');
                $stream = $response->getBody();
                $body = '';
                $maximumSize = 8 * 1024 * 1024;
                while (!$stream->eof() && strlen($body) <= $maximumSize) {
                    $chunk = $stream->read(min(65536, $maximumSize + 1 - strlen($body)));
                    if ($chunk === '') break;
                    $body .= $chunk;
                }
                $stream->close();
            }
            if ($status === 401 || $status === 403) throw new RuntimeException('Der Effekt-Anbieter lehnt den API-Schlüssel ab. Prüfe MASHA_FEEDLY_EFFECTS_API_KEY.', 502);
            if ($status === 404) throw new RuntimeException('Der Effekt-Anbieter wurde erreicht, aber der API-Endpunkt fehlt. Prüfe MASHA_FEEDLY_EFFECTS_BASE_URL.', 502);
            if ($status !== 200) throw new RuntimeException('Der Effekt-Anbieter antwortet mit HTTP ' . $status . '.', 502);
            if (strlen($body) > 8 * 1024 * 1024) throw new RuntimeException('Die Antwort des Effekt-Anbieters ist zu groß.', 502);
            return ['body' => $body, 'mime' => $mime];
        } catch (\Throwable $error) {
            // Guzzle-Meldungen können den Authorization-Header enthalten; niemals an Browser oder Protokolle weiterreichen.
            if ($error instanceof RuntimeException && $error->getMessage() !== 'Effekt-Anbieter nicht verfügbar.') throw $error;
            throw new RuntimeException('Der Effekt-Anbieter ist nicht erreichbar. Prüfe URL, HTTPS und TLS-Zertifikat.', 502);
        }
    }
}
