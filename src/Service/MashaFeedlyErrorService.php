<?php

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Extension\MashaFeedlyErrorConfigExtension;
use KW\MashaFeedly\Logging\MashaFeedlyErrorHandler;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

/** Übernimmt Fehlerchargen mit Wiederanlaufsicherung und gemeinsamer Sperre für Cronjobs und Websitebesuche. */
class MashaFeedlyErrorService
{
    /** Prüft das Intervall, importiert höchstens 100 Fehlergruppen und versendet eine fällige Zusammenfassung. @param string $mode cron oder visitor. @return int Anzahl übernommener Gruppen. */
    public static function run(string $mode): int
    {
        $config = SiteConfig::current_site_config();
        if (!$config->MashaFeedlyErrorsEnabled || $config->MashaFeedlyErrorsMode !== $mode) return 0;
        MashaFeedlyErrorHandler::prepareDirectory();
        $directory = MashaFeedlyErrorHandler::directory();
        $lock = fopen($directory . '/processing.lock', 'c');
        if (!$lock) throw new \RuntimeException('Fehlerverarbeitung konnte nicht gesperrt werden.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return 0; }
        $previousUser = Security::getCurrentUser();
        $previousProcessing = MashaFeedlyErrorHandler::$processing;
        try {
            MashaFeedlyErrorHandler::$processing = true;
            Security::setCurrentUser(null);
            $statePath = $directory . '/state.json';
            $state = is_file($statePath) ? json_decode((string)file_get_contents($statePath), true, 32, JSON_THROW_ON_ERROR) : [];
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));
            $interval = max(1, min(1440, (int)$config->MashaFeedlyErrorsInterval)) * 60;
            $processed = 0;
            $manager = MashaFeedlyErrorConfigExtension::manager();
            if (!$manager) throw new \RuntimeException('Das konfigurierte Betreiberkonto fehlt oder hat keine Administratorrechte.');
            if ($now->getTimestamp() - (int)($state['lastRun'] ?? 0) >= $interval) {
                // Abgebrochene Chargen werden vor neuen Chargen übernommen. Pro Fehler existiert höchstens eine Arbeitsdatei.
                foreach (array_slice(glob($directory . '/*.working.json') ?: [], 0, 100) as $path) {
                    self::importFile($path, (int)$manager->ID);
                    $processed++;
                }
                foreach (array_slice(glob($directory . '/*.pending.json') ?: [], 0, 100 - $processed) as $path) {
                    $queueLock = fopen($directory . '/queue.lock', 'c');
                    if (!$queueLock) throw new \RuntimeException('Fehlerablage konnte nicht gesperrt werden.');
                    try {
                        flock($queueLock, LOCK_EX);
                        $working = str_replace('.pending.json', '.working.json', $path);
                        if (!rename($path, $working)) throw new \RuntimeException('Fehlercharge konnte nicht übernommen werden.');
                    } finally { flock($queueLock, LOCK_UN); fclose($queueLock); }
                    self::importFile($working, (int)$manager->ID);
                    $processed++;
                }
                $state['lastRun'] = $now->getTimestamp();
                MashaFeedlyErrorHandler::writeJSON($statePath, $state);
            }
            $today = $now->format('Y-m-d');
            if ($config->MashaFeedlyErrorsDigest && $now->format('H:i') >= ($config->MashaFeedlyErrorsDigestTime ?: '08:00')
                && ($state['digestDate'] ?? '') !== $today) {
                $since = (string)($state['digestUntil'] ?? $now->modify('-1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
                $until = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $entries = MashaFeedlyEntry::get()->exclude('ErrorFingerprint', [null, ''])
                    ->filter('ErrorLastSeen:GreaterThan', $since)->filter('ErrorLastSeen:LessThanOrEqual', $until)->toArray();
                // Versand vorab beanspruchen: Ein Prozessabbruch darf keine erneute tägliche Mailflut auslösen.
                $state['digestDate'] = $today;
                $state['digestUntil'] = $until;
                MashaFeedlyErrorHandler::writeJSON($statePath, $state);
                MashaFeedlyNotificationService::notifyErrorDigest($entries);
            }
            return $processed;
        } finally {
            Security::setCurrentUser($previousUser);
            MashaFeedlyErrorHandler::$processing = $previousProcessing;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Übernimmt eine private Charge und entfernt sie erst nach erfolgreicher Speicherung. @param string $path Arbeitsdatei. @param int $managerID Betreiberkonto. @return void */
    public static function importFile(string $path, int $managerID): void
    {
        $batch = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($batch) || !preg_match('/^[a-f0-9]{64}$/D', (string)($batch['fingerprint'] ?? ''))
            || !preg_match('/^[a-f0-9]{32}$/D', (string)($batch['id'] ?? '')) || (int)($batch['count'] ?? 0) < 1) {
            throw new \RuntimeException('Ungültige Fehlercharge.');
        }
        $entry = MashaFeedlyEntry::get()->filter('ErrorFingerprint', $batch['fingerprint'])->first();
        if ($entry && $entry->ErrorLastBatch === $batch['id']) { unlink($path); return; }
        $new = !$entry;
        $entry ??= MashaFeedlyEntry::create([
            'ErrorFingerprint' => $batch['fingerprint'],
            'ErrorFirstSeen' => $batch['first'],
            'Content' => nl2br(htmlspecialchars('[' . $batch['level'] . '] ' . $batch['message'] . "\n" . $batch['origin'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        ]);
        $reopened = !$new && (bool)$entry->Category()->IsClosed;
        if ($reopened) $entry->CategoryID = MashaFeedlyCategory::defaultCategory()->ID;
        $entry->ErrorOccurrences += (int)$batch['count'];
        $entry->ErrorLastSeen = max((string)$entry->ErrorLastSeen, (string)$batch['last']);
        $entry->ErrorLastBatch = $batch['id'];
        $entry->deferErrorNotification = true;
        $connection = DB::get_conn();
        $connection->transactionStart();
        try {
            $entry->write();
            $entry->AssignedMembers()->setByIDList([$managerID]);
            $connection->transactionEnd();
        } catch (\Throwable $exception) {
            $connection->transactionRollback();
            throw $exception;
        }
        // Die Charge ist bereits dauerhaft übernommen; Mailfehler führen nicht zu doppelten Zählern oder Mails.
        unlink($path);
        if ($new) MashaFeedlyNotificationService::notifyNewEntry($entry);
        elseif ($reopened) MashaFeedlyNotificationService::notifyUpdatedEntry($entry);
    }

    /** Führt den Besuchsmodus ohne Auswirkungen auf die aufgerufene Seite aus. @return void */
    public static function runForWebsiteVisit(): void
    {
        try { self::run('visitor'); }
        catch (\Throwable $exception) { error_log('[Masha:Feedly] Fehlerimport fehlgeschlagen (' . get_class($exception) . ').'); }
    }
}
