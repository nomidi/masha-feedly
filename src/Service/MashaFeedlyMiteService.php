<?php

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyMiteTrigger;
use SilverStripe\Core\Injector\Injector;
use RuntimeException;

/** Startet Mite-Timer nach Betreiberbestätigung, ohne Zeiteintragsdaten in Feedly abzulegen. */
class MashaFeedlyMiteService
{
    /** @return MashaFeedlyMiteClient Austauschbarer, serverseitiger API-Client. */
    private function client(): MashaFeedlyMiteClient
    {
        return Injector::inst()->get(MashaFeedlyMiteClient::class);
    }

    /**
     * Lädt Projekte, Leistungen und Timerzustand erst beim Öffnen des Dialogs.
     * @return array<string, mixed> Projekt- und Leistungsauswahl sowie laufender Timer.
     * @throws RuntimeException Bei fehlendem Zugriff oder nicht erreichbarer API.
     */
    public function options(): array
    {
        $this->requireManager();
        $this->requireEnabled();
        $client = $this->client();
        $projects = $client->projects();
        $services = $client->services();
        $timer = $client->tracker();
        $activeID = (int)($timer['id'] ?? 0);
        $activeNote = $activeID > 0 ? (string)($client->timeEntry($activeID)['note'] ?? '') : '';
        return [
            'projects' => $projects,
            'services' => $services,
            'projectID' => (int)MashaFeedlyConfigExtension::currentSiteConfig()->MashaFeedlyMiteProjectID,
            'activeTimerID' => $activeID,
            'activeTimerNote' => $activeNote,
        ];
    }

    /**
     * Speichert Aktivierung, Standardprojekt und auslösende Kategorien.
     * @param bool $enabled Aktiv-Schalter der Integration.
     * @param int $projectID Projekt-ID, oder 0 zum Entfernen des Standards.
     * @param int[] $categoryIDs Ausgewählte Statuskategorien.
     * @throws RuntimeException Bei fehlendem Zugriff oder ungültiger Projektauswahl.
     */
    public function saveConfiguration(bool $enabled, int $projectID, array $categoryIDs): void
    {
        $this->requireManager();
        $categoryIDs = array_values(array_unique(array_map('intval', $categoryIDs)));
        if ($enabled && ($projectID <= 0 || !$categoryIDs)) {
            throw new RuntimeException('Für aktives Mite bitte ein Projekt und mindestens eine Startkategorie auswählen.', 400);
        }
        if ($categoryIDs && MashaFeedlyCategory::get()->filter('ID', $categoryIDs)->count() !== count($categoryIDs)) {
            throw new RuntimeException('Bitte vorhandene Startkategorien auswählen.', 400);
        }
        // Ausschalten muss auch bei fehlendem Schlüssel oder nicht erreichbarem Mite möglich bleiben.
        if ($enabled && !isset($this->client()->projects()[$projectID])) {
            throw new RuntimeException('Bitte ein verfügbares Mite-Projekt auswählen.', 400);
        }
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyMiteEnabled = $enabled;
        $config->MashaFeedlyMiteProjectID = $projectID;
        $config->write();
        foreach ($config->MashaFeedlyMiteTriggers() as $trigger) {
            if (!in_array((int)$trigger->CategoryID, $categoryIDs, true)) {
                $trigger->delete();
            }
        }
        $existingIDs = array_map('intval', $config->MashaFeedlyMiteTriggers()->column('CategoryID'));
        foreach (array_diff($categoryIDs, $existingIDs) as $categoryID) {
            MashaFeedlyMiteTrigger::create(['SiteConfigID' => (int)$config->ID, 'CategoryID' => $categoryID])->write();
        }
    }

    /**
     * Erstellt einen Mite-Zeiteintrag und startet dessen Timer. Mite ist alleiniger Speicherort.
     * @param MashaFeedlyEntry $entry Fehler in einer konfigurierten Startkategorie.
     * @param int $projectID Im Dialog ausgewähltes Mite-Projekt.
     * @param int $serviceID Im Dialog ausgewählte Mite-Leistung.
     * @param int $confirmedTimerID Laufender Timer bei Anzeige des Dialogs; 0 bedeutet kein Timer.
     * @return int Mite-Zeiteintrags-ID; wird nicht in Feedly gespeichert.
     * @throws RuntimeException Bei fehlender Freigabe, geändertem Timer oder API-Fehlern.
     */
    public function start(MashaFeedlyEntry $entry, int $projectID, int $serviceID, int $confirmedTimerID): int
    {
        $this->requireManager();
        $this->requireEnabled();
        if (!MashaFeedlyConfigExtension::miteTriggersCategory((int)$entry->CategoryID)) {
            throw new RuntimeException('Die aktuelle Kategorie ist nicht als Mite-Startkategorie konfiguriert.', 409);
        }
        $client = $this->client();
        if (!isset($client->projects()[$projectID])) {
            throw new RuntimeException('Bitte ein verfügbares Mite-Projekt auswählen.', 400);
        }
        if (!isset($client->services()[$serviceID])) {
            throw new RuntimeException('Bitte eine verfügbare Mite-Leistung auswählen.', 400);
        }
        $activeID = (int)($client->tracker()['id'] ?? 0);
        if ($activeID !== $confirmedTimerID) {
            throw new RuntimeException('Der laufende Mite-Timer hat sich geändert. Bitte den Dialog erneut öffnen und bestätigen.', 409);
        }
        $persisted = MashaFeedlyEntry::get()->byID((int)$entry->ID);
        if (!$persisted || !MashaFeedlyConfigExtension::miteTriggersCategory((int)$persisted->CategoryID)) {
            throw new RuntimeException('Mite ist deaktiviert oder die aktuelle Kategorie ist keine Mite-Startkategorie.', 409);
        }
        $note = 'Masha:Feedly #' . (int)$persisted->ID . ': ' . self::plainTextDescription((string)$persisted->Content);
        if ($persisted->PageURL) {
            $note .= "\n" . (string)$persisted->PageURL;
        }
        $timeEntryID = $client->createTimeEntry(
            $projectID,
            $note,
            substr(MashaFeedlyEntry::currentEntryDateTime(), 0, 10),
            $serviceID
        );
        $client->startTracker($timeEntryID);
        return $timeEntryID;
    }

    /** Stoppt den zuvor im Dialog bestätigten, laufenden Mite-Timer. */
    public function stop(int $confirmedTimerID): void
    {
        $this->requireManager();
        $this->requireEnabled();
        $client = $this->client();
        $activeID = (int)($client->tracker()['id'] ?? 0);
        if ($activeID <= 0 || $activeID !== $confirmedTimerID) {
            throw new RuntimeException('Der laufende Mite-Timer hat sich geändert. Bitte den Dialog neu laden.', 409);
        }
        $client->stopTracker($activeID);
    }

    /** Startet einen allgemeinen Mite-Zeiteintrag ohne Bezug zu einem Feedly-Eintrag. */
    public function startGeneral(int $projectID, int $serviceID, int $confirmedTimerID): int
    {
        $this->requireManager();
        $this->requireEnabled();
        $client = $this->client();
        if (!isset($client->projects()[$projectID])) {
            throw new RuntimeException('Bitte ein verfügbares Mite-Projekt auswählen.', 400);
        }
        if (!isset($client->services()[$serviceID])) {
            throw new RuntimeException('Bitte eine verfügbare Mite-Leistung auswählen.', 400);
        }
        $activeID = (int)($client->tracker()['id'] ?? 0);
        if ($activeID !== $confirmedTimerID) {
            throw new RuntimeException('Der laufende Mite-Timer hat sich geändert. Bitte den Dialog erneut öffnen und bestätigen.', 409);
        }
        $timeEntryID = $client->createTimeEntry(
            $projectID,
            'Masha:Feedly – allgemeine Zeiterfassung',
            substr(MashaFeedlyEntry::currentEntryDateTime(), 0, 10),
            $serviceID
        );
        $client->startTracker($timeEntryID);
        return $timeEntryID;
    }

    /** Wandelt den vollständigen gespeicherten Beschreibungsinhalt lesbar in Klartext um. */
    private static function plainTextDescription(string $content): string
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/<(br\\s*\\/?|\\/(?:p|div|li|h[1-6]))\\b[^>]*>/i', "\n", $content) ?? $content;
        $content = trim(strip_tags($content));
        return preg_replace('/\\n{3,}/', "\n\n", $content) ?? $content;
    }

    /** @throws RuntimeException Wenn das aktuelle Konto nicht als CMS-Betreiber freigegeben ist. */
    private function requireManager(): void
    {
        if (!MashaFeedlyEntry::canManageReporter()) {
            throw new RuntimeException('Keine Berechtigung für Mite.', 403);
        }
    }

    /** @throws RuntimeException Wenn die Integration zentral ausgeschaltet ist. */
    private function requireEnabled(): void
    {
        if (!MashaFeedlyConfigExtension::miteEnabled()) {
            throw new RuntimeException('Mite ist für diese Website deaktiviert.', 409);
        }
    }
}
