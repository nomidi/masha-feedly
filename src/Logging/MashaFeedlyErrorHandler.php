<?php

namespace KW\MashaFeedly\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\CMS\Controllers\ContentController;

/** Erfasst technische Fehler ohne Datenbankzugriff; Kontextwerte und Trace-Argumente bleiben bewusst außen vor. */
class MashaFeedlyErrorHandler extends AbstractProcessingHandler
{
    use Configurable;

    private static string $spool_directory = '';

    /** Unterbindet Rückkopplungen während Import und Mailversand. */
    public static bool $processing = false;

    /** Erfasst mindestens Fehler und lässt unabhängige LogHandler weiterarbeiten. */
    public function __construct()
    {
        parent::__construct(Level::Error, true);
    }

    /** Liefert den geschützten, projektspezifischen Speicher außerhalb des Webroots. @return string Absoluter Ordnerpfad. */
    public static function directory(): string
    {
        $configured = (string)static::config()->get('spool_directory');
        if ($configured !== '') return $configured;
        // Die gesamte PHPUnit-Suite bleibt von der echten Fehlerablage getrennt, auch vor dem ersten Test-Setup.
        if (defined('MASHA_FEEDLY_TEST_ERROR_SPOOL')) return (string)constant('MASHA_FEEDLY_TEST_ERROR_SPOOL');
        return TEMP_PATH . '/masha-feedly-errors-' . substr(hash('sha256', BASE_PATH), 0, 16);
    }

    /** Legt einen nur für den Serverbenutzer zugänglichen Ordner an. @return void */
    public static function prepareDirectory(): void
    {
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Fehlerablage konnte nicht angelegt werden.');
        }
        $real = realpath($directory);
        $public = realpath(BASE_PATH . '/public');
        if (!$real || ($public && ($real === $public || str_starts_with($real, $public . DIRECTORY_SEPARATOR)))) {
            throw new \RuntimeException('Die Fehlerablage muss außerhalb des Webroots liegen.');
        }
        chmod($directory, 0700);
    }

    /** Entfernt häufige Geheimnisse sowie Querystrings aus Meldungen. @param string $text Technischer Text. @return string Bereinigter Text. */
    public static function redact(string $text): string
    {
        $text = preg_replace('~(https?://)[^\s/@]+:[^\s/@]+@~i', '$1[entfernt]@', $text) ?? '';
        $text = preg_replace('~(https?://[^\s?\#]+)[?\#][^\s]*~i', '$1', $text) ?? '';
        $text = preg_replace('~\b(password|passwd|pwd|secret|token|api[_-]?key|authorization|cookie)\b\s*[:=]\s*[^\s,;]+~i', '$1=[entfernt]', $text) ?? '';
        $text = preg_replace('~\bBearer\s+\S+~i', 'Bearer [entfernt]', $text) ?? '';
        return mb_substr($text, 0, 8000);
    }

    /** Bildet eine stabile Fehlerkennung ohne variable Zahlen, UUIDs und Projektpfade. @param string $message Bereinigte Meldung. @param string $origin Herkunft. @return string SHA-256-Kennung. */
    public static function fingerprint(string $message, string $origin): string
    {
        $message = preg_replace('/\b[0-9a-f]{8}-[0-9a-f-]{27,}\b/i', '{id}', $message) ?? $message;
        $message = preg_replace('/\b\d+\b/', '{n}', $message) ?? $message;
        return hash('sha256', $origin . "\n" . $message);
    }

    /** Ermittelt ohne ORM-Zugriff die Inhaltsseite; technische Hintergrundaufrufe erhalten das CMS als Ort. @return string Lokaler Pfad ohne Querystring und Fragment. */
    public static function currentPageURL(): string
    {
        $controller = Controller::curr();
        $fallback = Controller::join_links(Director::baseURL(), 'admin');
        if (!$controller instanceof ContentController) return $fallback;
        $url = $controller->getRequest()?->getURL();
        if (!is_string($url)) return $fallback;
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || preg_match('/[\x00-\x20\\\\]/', $path)) return $fallback;
        $location = Controller::join_links(Director::baseURL(), ltrim($path, '/'));
        return strlen($location) <= 2048 ? $location : $fallback;
    }

    /** Speichert eine zusammengefasste Charge atomar; Fehler der Erfassung gehen ausschließlich ins PHP-Protokoll. */
    protected function write(LogRecord $record): void
    {
        if (self::$processing) return;
        $directory = self::directory();
        $settings = @file_get_contents($directory . '/settings.json');
        $settings = $settings === false ? [] : json_decode($settings, true);
        if (empty($settings['enabled']) || $record->level->value < (int)($settings['level'] ?? 400)) return;
        $lock = null;
        try {
            self::prepareDirectory();
            $exception = $record->context['exception'] ?? null;
            $origin = $exception instanceof \Throwable
                ? get_class($exception) . ' ' . str_replace(BASE_PATH, '', $exception->getFile()) . ':' . $exception->getLine()
                : (isset($record->context['file']) && is_string($record->context['file'])
                    ? str_replace(BASE_PATH, '', $record->context['file']) . ':' . (int)($record->context['line'] ?? 0)
                    : (string)$record->channel);
            $origin = self::redact($origin);
            $message = self::redact($record->message);
            $fingerprint = self::fingerprint($message, $origin);
            $lock = fopen($directory . '/queue.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('Fehlerablage konnte nicht gesperrt werden.');
            $path = $directory . '/' . $fingerprint . '.pending.json';
            $batch = is_file($path) ? json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR) : [
                'id' => bin2hex(random_bytes(16)), 'fingerprint' => $fingerprint,
                'message' => $message, 'origin' => $origin, 'level' => $record->level->name,
                'first' => $record->datetime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'count' => 0,
            ];
            $batch['count']++;
            $pageURL = self::currentPageURL();
            if (empty($batch['pageURL']) || $batch['pageURL'] === Controller::join_links(Director::baseURL(), 'admin')) {
                $batch['pageURL'] = $pageURL;
            }
            $batch['last'] = $record->datetime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            self::writeJSON($path, $batch);
        } catch (\Throwable $exception) {
            error_log('[Masha:Feedly] Technischer Fehler konnte nicht erfasst werden (' . get_class($exception) . ').');
        } finally {
            if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
        }
    }

    /** Ersetzt eine JSON-Datei atomar mit privaten Dateirechten. @param string $path Zielpfad. @param array<string, mixed> $data Daten. @return void */
    public static function writeJSON(string $path, array $data): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        try {
            if (file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX) === false) {
                throw new \RuntimeException('Fehlerablage konnte nicht geschrieben werden.');
            }
            chmod($temporary, 0600);
            if (!rename($temporary, $path)) throw new \RuntimeException('Fehlerablage konnte nicht übernommen werden.');
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }
}
