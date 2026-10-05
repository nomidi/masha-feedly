<?php

namespace KW\MashaFeedly\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Core\Environment;
use RuntimeException;

/** Kommuniziert serverseitig mit dem persönlichen Mite-Konto, ohne Zugangsdaten auszugeben. */
class MashaFeedlyMiteClient
{
    /** @param ClientInterface|null $http Austauschbarer HTTP-Client für isolierte API-Tests. */
    public function __construct(private ?ClientInterface $http = null)
    {
        $this->http ??= new Client();
    }

    /** Prüft, ob API-Schlüssel und ein gültiger Mite-Kontoname vorhanden sind. */
    public function isConfigured(): bool
    {
        return trim((string)Environment::getEnv('MASHA_FEEDLY_MITE_API_KEY')) !== ''
            && (bool)preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $this->account());
    }

    /** Liefert den Kontonamen; die Anwendung erwartet einen Subdomainnamen ohne URL. */
    public function account(): string
    {
        return trim((string)(Environment::getEnv('MASHA_FEEDLY_MITE_ACCOUNT') ?: 'kooperative-web'));
    }

    /** Erkennt einen Wechsel des API-Benutzers, ohne den API-Schlüssel in der Datenbank abzulegen. */
    public function connectionFingerprint(): string
    {
        return hash('sha256', $this->account() . "\0" . (string)Environment::getEnv('MASHA_FEEDLY_MITE_API_KEY'));
    }

    /** @return array<int, string> Aktive Projekte mit optionalem Kundennamen. */
    public function projects(): array
    {
        $projects = [];
        foreach ($this->request('GET', 'projects.json') as $row) {
            $project = $row['project'] ?? [];
            if ((int)($project['id'] ?? 0) <= 0 || ($project['archived'] ?? false)) {
                continue;
            }
            $customer = trim((string)($project['customer_name'] ?? ''));
            $projects[(int)$project['id']] = ($customer !== '' ? $customer . ' / ' : '') . (string)$project['name'];
        }
        return $projects;
    }

    /** @return array<int, string> Aktive Mite-Leistungen nach ID. */
    public function services(): array
    {
        $services = [];
        foreach ($this->request('GET', 'services.json') as $row) {
            $service = $row['service'] ?? [];
            if ((int)($service['id'] ?? 0) <= 0 || ($service['archived'] ?? false)) {
                continue;
            }
            $services[(int)$service['id']] = (string)$service['name'];
        }
        return $services;
    }

    /** @return array<string, mixed> Laufender Timer oder leeres Array. */
    public function tracker(): array
    {
        return $this->request('GET', 'tracker.json')['tracker']['tracking_time_entry'] ?? [];
    }

    /** @return array<string, mixed> Zeiteintrag einschließlich Beschreibung und Projekt. */
    public function timeEntry(int $id): array
    {
        return $this->request('GET', 'time_entries/' . $id . '.json')['time_entry'] ?? [];
    }

    /**
     * Legt einen Zeiteintrag für den API-Benutzer an; die Uhr wird separat gestartet.
     * @param int $projectID Ausgewähltes Mite-Projekt.
     * @param string $note Vollständige Fehlerbeschreibung samt Seitenlink.
     * @param string $date Datum des Zeiteintrags in der Projektzeitzone.
     * @param int $serviceID Ausgewählte Mite-Leistung.
     * @return int ID des angelegten Zeiteintrags.
     * @throws RuntimeException Bei fehlender oder ungültiger API-Antwort.
     */
    public function createTimeEntry(int $projectID, string $note, string $date, int $serviceID): int
    {
        $data = $this->request('POST', 'time_entries.json', ['time_entry' => [
            'project_id' => $projectID,
            'service_id' => $serviceID,
            'note' => $note,
            'date_at' => $date,
            'minutes' => 0,
        ]]);
        $id = (int)($data['time_entry']['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Mite hat keine Zeiteintrags-ID zurückgegeben.');
        }
        return $id;
    }

    /** @return array<string, mixed> Gestarteter Timer und gegebenenfalls zuvor gestoppter Zeiteintrag. */
    public function startTracker(int $id): array
    {
        $tracker = $this->request('PATCH', 'tracker/' . $id . '.json')['tracker'] ?? [];
        if ((int)($tracker['tracking_time_entry']['id'] ?? 0) !== $id) {
            throw new RuntimeException('Mite hat den Timerstart nicht bestätigt.');
        }
        return $tracker;
    }

    /** Stoppt den aktuell laufenden Zeiteintrag in Mite. */
    public function stopTracker(int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Es läuft kein Mite-Timer.');
        }
        $this->request('DELETE', 'tracker/' . $id . '.json');
    }

    /**
     * Führt einen begrenzten HTTPS-Aufruf aus. Rohe Exceptions könnten Schlüssel oder Antwortdaten enthalten.
     * @param string $method HTTP-Methode.
     * @param string $path Relativer API-Pfad.
     * @param array<string, mixed> $payload JSON-Nutzdaten.
     * @return array<string|int, mixed> Dekodierte Mite-Antwort.
     * @throws RuntimeException Mit einer für das CMS geeigneten Fehlermeldung.
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Bitte MASHA_FEEDLY_MITE_API_KEY und MASHA_FEEDLY_MITE_ACCOUNT in der .env konfigurieren.');
        }
        $options = [
            'headers' => [
                'X-MiteApiKey' => (string)Environment::getEnv('MASHA_FEEDLY_MITE_API_KEY'),
                'User-Agent' => 'MashaFeedly (https://github.com/kooperativeweb/masha-feedly)',
            ],
            'timeout' => 10,
            'connect_timeout' => 5,
            'allow_redirects' => false,
            'http_errors' => false,
        ];
        if ($payload !== []) {
            $options['json'] = $payload;
        }
        try {
            $response = $this->http->request($method, 'https://' . $this->account() . '.mite.de/' . $path, $options);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Mite ist gerade nicht erreichbar. Bitte später erneut versuchen.');
        }
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(match ($status) {
                401, 403 => 'Mite hat den Zugriff abgelehnt. Bitte API-Schlüssel und Freigabe prüfen.',
                404 => 'Mite-Konto, Projekt oder Zeiteintrag wurde nicht gefunden.',
                default => 'Mite konnte die Anfrage nicht ausführen (HTTP ' . $status . ').',
            });
        }
        $body = (string)$response->getBody();
        if (trim($body) === '') {
            return [];
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Mite hat eine ungültige Antwort geliefert.');
        }
        return $data;
    }
}
