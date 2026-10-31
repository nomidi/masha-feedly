<?php

namespace KW\MashaFeedly\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\Connect\DatabaseException;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;
use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use KW\MashaFeedly\Model\MashaFeedlyMiteTrigger;

/**
 * Ergänzt die SiteConfig um die für Masha Feedly freigegebenen Benutzer.
 *
 * @property string $MashaFeedlyAddress Konfigurierte Anrede im Modul (du oder sie).
 * @property bool $MashaFeedlyPollingEnabled Aktiviert automatische Aktivitätsabfragen.
 * @property int $MashaFeedlyPollingMinutes Abstand der Aktivitätsabfragen in Minuten.
 * @property string $MashaFeedlyTheme Website-Vorgabe für das persönliche Masha-Feedly-Erscheinungsbild.
 * @property string $MashaFeedlyAllowedMemberIDs JSON-Liste freigegebener Mitglieds-IDs.
 * @property bool $MashaFeedlyClosedCategoriesMigrated Kennzeichnet die einmalige Übernahme abgeschlossener Kategorien.
 * @property bool $MashaFeedlyEmailTestSucceeded Kennzeichnet einen erfolgreichen globalen E-Mail-Test.
 * @property string $MashaFeedlyWelcomeTextDu Willkommenstext in Du-Anrede.
 * @property string $MashaFeedlyWelcomeTextSie Willkommenstext in Sie-Anrede.
 * @property string $MashaFeedlyEmailFooter Inhalt des zentralen E-Mail-Footers.
 * @property string $MashaFeedlyNotificationNewEntryDu Text für Benachrichtigungen über neue Meldungen in Du-Anrede.
 * @property string $MashaFeedlyNotificationNewEntrySie Text für Benachrichtigungen über neue Meldungen in Sie-Anrede.
 * @property string $MashaFeedlyNotificationUpdatedEntryDu Text für Änderungsbenachrichtigungen in Du-Anrede.
 * @property string $MashaFeedlyNotificationUpdatedEntrySie Text für Änderungsbenachrichtigungen in Sie-Anrede.
 * @property string $MashaFeedlyNotificationNewCommentDu Text für Kommentarbenachrichtigungen in Du-Anrede.
 * @property string $MashaFeedlyNotificationNewCommentSie Text für Kommentarbenachrichtigungen in Sie-Anrede.
 * @property string $MashaFeedlyNotificationDueDateDu Text für Fälligkeitserinnerungen in Du-Anrede.
 * @property string $MashaFeedlyNotificationDueDateSie Text für Fälligkeitserinnerungen in Sie-Anrede.
 * @property string $MashaFeedlyNotificationCostEstimateDu Text für Kostenschätzungsbenachrichtigungen in Du-Anrede.
 * @property string $MashaFeedlyNotificationCostEstimateSie Text für Kostenschätzungsbenachrichtigungen in Sie-Anrede.
 * @property int $MashaFeedlyMiteProjectID Standardprojekt für den persönlichen Mite-Timer.
 * @property bool $MashaFeedlyMiteEnabled Aktiviert die Mite-Timerfunktion für diese Website.
 * @method \SilverStripe\ORM\HasManyList<MashaFeedlyMiteTrigger> MashaFeedlyMiteTriggers() Auslösende Statuskategorien.
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyConfigExtension extends Extension
{
    /** @var int[] */
    private array $newlyAllowedMemberIDs = [];

    /** @var int[]|null Freigabeliste für den aktuellen Request. */
    private static ?array $memberIDsCache = null;

    /** @var int[]|null Ungeprüfte IDs für schnelle Berechtigungsprüfungen im aktuellen Request. */
    private static ?array $configuredMemberIDsCache = null;

    /** Request-Identität, damit Anfragen in Testläufen nicht dieselbe Cachekopie teilen. */
    private static ?int $memberIDsCacheRequestID = null;

    /** Schema-Prüfung für den aktuellen Request. */
    private static ?HTTPRequest $schemaCheckRequest = null;

    /** @var array<string, true>|null Vorhandene SiteConfig-Felder für den aktuellen Request. */
    private static ?array $siteConfigFieldNamesCache = null;

    private static $db = [
        'MashaFeedlyAllowedMemberIDs' => 'Text',
        'MashaFeedlyAddress' => "Varchar(3)",
        'MashaFeedlyFontSize' => 'Varchar(10)',
        'MashaFeedlyTheme' => 'Varchar(80)',
        'MashaFeedlyPollingEnabled' => 'Boolean',
        'MashaFeedlyPollingMinutes' => 'Int',
        'MashaFeedlyDueDateReminderMode' => 'Varchar(20)',
        'MashaFeedlyHourlyRate' => 'Decimal(10,2)',
        'MashaFeedlyMiteProjectID' => 'Int',
        'MashaFeedlyMiteEnabled' => 'Boolean',
        'MashaFeedlyEstimateCategoriesSeeded' => 'Boolean',
        'MashaFeedlyDueDateReminderLastRunDate' => 'Date',
        'MashaFeedlyClosedCategoriesMigrated' => 'Boolean',
        'MashaFeedlyEmailTestSucceeded' => 'Boolean',
        'MashaFeedlyEmailFooter' => 'Text',
        'MashaFeedlyWelcomeTextDu' => 'HTMLText',
        'MashaFeedlyWelcomeTextSie' => 'HTMLText',
        'MashaFeedlyNotificationNewEntryDu' => 'HTMLText',
        'MashaFeedlyNotificationNewEntrySie' => 'HTMLText',
        'MashaFeedlyNotificationUpdatedEntryDu' => 'HTMLText',
        'MashaFeedlyNotificationUpdatedEntrySie' => 'HTMLText',
        'MashaFeedlyNotificationNewCommentDu' => 'HTMLText',
        'MashaFeedlyNotificationNewCommentSie' => 'HTMLText',
        'MashaFeedlyNotificationDueDateDu' => 'HTMLText',
        'MashaFeedlyNotificationDueDateSie' => 'HTMLText',
        'MashaFeedlyNotificationCostEstimateDu' => 'HTMLText',
        'MashaFeedlyNotificationCostEstimateSie' => 'HTMLText',
    ];

    private static $defaults = [
        'MashaFeedlyTheme' => 'playful',
        'MashaFeedlyPollingEnabled' => false,
        'MashaFeedlyPollingMinutes' => 5,
        'MashaFeedlyMiteEnabled' => false,
        'MashaFeedlyHourlyRate' => 0,
        'MashaFeedlyEstimateCategoriesSeeded' => false,
    ];

    private static $has_many = [
        'MashaFeedlyMiteTriggers' => MashaFeedlyMiteTrigger::class . '.SiteConfig',
    ];

    /** Die Mite-Zuordnung wird ausschließlich im geschützten Modulreiter gepflegt. */
    public function updateCMSFields(\SilverStripe\Forms\FieldList $fields): void
    {
        $fields->removeByName([
            'MashaFeedlyPollingEnabled',
            'MashaFeedlyPollingMinutes',
            'MashaFeedlyMiteProjectID',
            'MashaFeedlyMiteEnabled',
            'MashaFeedlyMiteTriggers',
            'MashaFeedlyEmailFooter',
            'MashaFeedlyWelcomeTextDu',
            'MashaFeedlyWelcomeTextSie',
            'MashaFeedlyNotificationNewEntryDu',
            'MashaFeedlyNotificationNewEntrySie',
            'MashaFeedlyNotificationUpdatedEntryDu',
            'MashaFeedlyNotificationUpdatedEntrySie',
            'MashaFeedlyNotificationNewCommentDu',
            'MashaFeedlyNotificationNewCommentSie',
            'MashaFeedlyNotificationDueDateDu',
            'MashaFeedlyNotificationDueDateSie',
            'MashaFeedlyNotificationCostEstimateDu',
            'MashaFeedlyNotificationCostEstimateSie',
        ]);
    }

    /** Ergänzt Footer und leere Benachrichtigungstexte bei dev/build, ohne eigene Inhalte zu überschreiben. */
    public function onRequireDefaultRecords(): void
    {
        // Während des ersten dev/build darf SiteConfig nicht gelesen werden, solange die neuen Spalten fehlen.
        $fieldsToCheck = ['MashaFeedlyEmailFooter'];
        foreach (self::notificationFieldMap() as $addresses) {
            array_push($fieldsToCheck, ...array_values($addresses));
        }
        foreach ($fieldsToCheck as $field) {
            if (!self::hasSiteConfigField($field)) return;
        }

        $config = self::currentSiteConfig();
        $changed = false;
        $footer = trim((string)$config->MashaFeedlyEmailFooter);
        if ($footer === '' && ($defaultFooter = self::defaultEmailFooter()) !== '') {
            $config->MashaFeedlyEmailFooter = $defaultFooter;
            $changed = true;
        }

        $emptyFields = self::emptyNotificationFields($config);
        if ($emptyFields && MashaFeedlyEffectClient::hasConfiguredProvider()) {
            try {
                $providerTexts = Injector::inst()->get(MashaFeedlyEffectClient::class)->emailDefaults(true);
                $defaults = ['welcome' => $providerTexts['welcome']] + $providerTexts['notifications'];
                $changed = self::fillEmptyNotificationTexts($config, $defaults) || $changed;
            } catch (\Throwable $exception) {
                error_log('[Masha:Feedly] Benachrichtigungstexte konnten beim dev/build nicht vom Effekt-Anbieter geladen werden (' . get_class($exception) . ').');
            }
        }

        if ($changed) $config->write();
    }

    /** Liefert die Feldnamen für die Willkommen und die fünf Benachrichtigungstypen in beiden Anreden. @return array<string, array<string, string>> Zuordnung Typ/Anrede zu SiteConfig-Feld. */
    public static function notificationFieldMap(): array
    {
        return [
            'welcome' => ['du' => 'MashaFeedlyWelcomeTextDu', 'sie' => 'MashaFeedlyWelcomeTextSie'],
            'newEntry' => ['du' => 'MashaFeedlyNotificationNewEntryDu', 'sie' => 'MashaFeedlyNotificationNewEntrySie'],
            'updatedEntry' => ['du' => 'MashaFeedlyNotificationUpdatedEntryDu', 'sie' => 'MashaFeedlyNotificationUpdatedEntrySie'],
            'newComment' => ['du' => 'MashaFeedlyNotificationNewCommentDu', 'sie' => 'MashaFeedlyNotificationNewCommentSie'],
            'dueDate' => ['du' => 'MashaFeedlyNotificationDueDateDu', 'sie' => 'MashaFeedlyNotificationDueDateSie'],
            'costEstimate' => ['du' => 'MashaFeedlyNotificationCostEstimateDu', 'sie' => 'MashaFeedlyNotificationCostEstimateSie'],
        ];
    }

    /** Ergänzt ausschließlich leere Textfelder aus einer geprüften Anbieterantwort. @param SiteConfig $config Website-Konfiguration. @param array<string, mixed> $defaults Texte nach Typ und Anrede. @return bool Gibt zurück, ob ein Feld geändert wurde. */
    public static function fillEmptyNotificationTexts(SiteConfig $config, array $defaults): bool
    {
        $changed = false;
        foreach (self::notificationFieldMap() as $type => $addresses) {
            foreach ($addresses as $address => $field) {
                if (trim((string)$config->$field) !== '') continue;
                $text = $defaults[$type][$address] ?? '';
                if (!is_string($text) || trim($text) === '') continue;
                $config->$field = $text;
                $changed = true;
            }
        }
        return $changed;
    }

    /** Liefert alle derzeit leeren Benachrichtigungsfelder für dev/build. @param SiteConfig $config Website-Konfiguration. @return list<string> Leere Feldnamen. */
    private static function emptyNotificationFields(SiteConfig $config): array
    {
        $emptyFields = [];
        foreach (self::notificationFieldMap() as $addresses) {
            foreach ($addresses as $field) {
                if (trim((string)$config->$field) === '') $emptyFields[] = $field;
            }
        }
        return $emptyFields;
    }

    /** Liefert den gespeicherten Text der persönlichen Anrede. @param string $type Benachrichtigungstyp. @param string $address Persönliche Profilanrede. @return string Formatierte Einleitung oder leer für die bisherige Mailvorlage. */
    public static function notificationEmailText(string $type, string $address): string
    {
        $field = self::notificationFieldMap()[$type][$address] ?? null;
        if (!$field) return '';

        try {
            return trim((string)self::currentSiteConfig()->$field);
        } catch (DatabaseException) {
            // Während dev/build können neue Textfelder noch nicht angelegt sein.
            return '';
        }
    }

    /** Lokale ausdrückliche Vorgabe, sonst der geprüfte gemeinsame Footer des konfigurierten Anbieters. */
    public static function defaultEmailFooter(): string
    {
        $footer = trim((string)Environment::getEnv('MASHA_FEEDLY_EMAIL_FOOTER'));
        if ($footer === '') {
            if (MashaFeedlyEffectClient::hasConfiguredProvider()) {
                try {
                    $defaults = Injector::inst()->get(MashaFeedlyEffectClient::class)->emailDefaults();
                    return trim((string)($defaults['footer'] ?? ''));
                } catch (\Throwable) {
                    // Keine erfundenen Impressumsangaben und kein Rückfall auf andere Anbieterzugänge.
                    return '';
                }
            }
            return '';
        }

        // Mehrzeilige Werte lassen sich in der .env mit den Zeichenfolgen \n hinterlegen.
        return trim(str_replace(['\\r\\n', '\\n', '\\r'], "\n", $footer));
    }

    /** Erhält eigene gespeicherte Texte; leere Felder verwenden die lokale oder zentrale Vorgabe ohne Datenbankschreibzugriff. */
    public static function emailFooter(): string
    {
        $value = trim((string)self::currentSiteConfig()->MashaFeedlyEmailFooter);
        if ($value === '') {
            return self::defaultEmailFooter();
        }

        // Hashes erkennen ausschließlich zwei früher automatisch vorbelegte Footer, ohne ihre Kontaktdaten im Code abzulegen.
        if (in_array(hash('sha256', $value), [
            'd1e84d1fde95ac985c5754bb96b97caf2ea362038032fd0256768acda7da0532',
            '7bb52ee474caa560366e5c463ceffc9a633e1ad7ac5f6c991313937e6a0ba73b',
        ], true)) {
            return self::defaultEmailFooter();
        }

        // Entfernt eine früher doppelt gespeicherte Wirtschafts-ID aus frei bearbeiteten Footertexten.
        return trim((string)preg_replace('/\s*·\s*Wirtschafts-ID:\s*[^\s·]+/u', '', $value));
    }

    /** Prüft den zentralen Aktiv-Schalter; neue Installationen sind zunächst deaktiviert. */
    public static function miteEnabled(): bool
    {
        return (bool)self::currentSiteConfig()->MashaFeedlyMiteEnabled;
    }

    /** Prüft, ob der globale E-Mail-Versand bereits erfolgreich getestet wurde. */
    public static function emailTestSucceeded(): bool
    {
        try {
            return (bool)self::currentSiteConfig()->MashaFeedlyEmailTestSucceeded;
        } catch (DatabaseException) {
            // Während eines laufenden dev/build kann die neue Spalte noch fehlen.
            return false;
        }
    }

    /** @return int[] IDs der für den Timerdialog konfigurierten Statuskategorien. */
    public static function miteCategoryIDs(): array
    {
        return array_map('intval', self::currentSiteConfig()->MashaFeedlyMiteTriggers()->column('CategoryID'));
    }

    /** Prüft Aktivierung und Kategorie serverseitig, auch für direkte Timerstart-Anfragen. */
    public static function miteTriggersCategory(int $categoryID): bool
    {
        return self::miteEnabled() && in_array($categoryID, self::miteCategoryIDs(), true);
    }

    /** Ermittelt neue Freigaben, bevor SiteConfig die bisherige Liste überschreibt. */
    protected function onBeforeWrite(): void
    {
        $owner = $this->getOwner();
        $persisted = $owner->isInDB() ? SiteConfig::get()->byID((int)$owner->ID) : null;
        $previous = $persisted ? self::normalizeMemberIDs(json_decode((string)$persisted->MashaFeedlyAllowedMemberIDs, true) ?: []) : [];
        $currentRaw = json_decode((string)$owner->MashaFeedlyAllowedMemberIDs, true);
        $current = self::normalizeMemberIDs(is_array($currentRaw) ? $currentRaw : []);
        $this->newlyAllowedMemberIDs = array_values(array_diff($current, $previous));
    }

    /** Setzt bei jeder neuen Freigabe sinnvolle Mail-Vorgaben und sendet danach die Willkommensmail. */
    protected function onAfterWrite(): void
    {
        self::clearMemberIDsCache();
        foreach ($this->newlyAllowedMemberIDs as $memberID) {
            $member = Member::get()->byID($memberID);
            if ($member) {
                MashaFeedlyMemberExtension::initializeAccessEmailPreferences($member);
                MashaFeedlyNotificationService::notifyAccessGranted($member);
            }
        }
        $this->newlyAllowedMemberIDs = [];
    }

    /**
     * Liefert die aktuelle SiteConfig mit der Typinformation für PhpStorm.
     *
     * @return SiteConfig&MashaFeedlyConfigExtension SiteConfig mit Masha-Feedly-Feldern.
     */
    public static function currentSiteConfig(): SiteConfig
    {
        return SiteConfig::current_site_config();
    }

    /** Aktivitätsabfragen bleiben ohne ausdrückliche Aktivierung ausgeschaltet. */
    public static function pollingEnabled(): bool
    {
        return self::hasSiteConfigField('MashaFeedlyPollingEnabled')
            && (bool)self::currentSiteConfig()->MashaFeedlyPollingEnabled;
    }

    /** Begrenzt das Intervall auf fünf bis 60 Minuten in Fünf-Minuten-Schritten. */
    public static function normalizePollingMinutes(int $minutes): int
    {
        return $minutes >= 5 && $minutes <= 60 && $minutes % 5 === 0 ? $minutes : 5;
    }

    /** Liefert auch vor dem Schemaaufbau eine sichere Intervallvorgabe. */
    public static function pollingMinutes(): int
    {
        return self::hasSiteConfigField('MashaFeedlyPollingMinutes')
            ? self::normalizePollingMinutes((int)self::currentSiteConfig()->MashaFeedlyPollingMinutes)
            : 5;
    }

    /** Liefert die konfigurierte Anrede und fällt bei ungültigen Werten auf Du zurück. */
    public static function address(): string
    {
        return strtolower((string)self::currentSiteConfig()->MashaFeedlyAddress) === 'sie' ? 'sie' : 'du';
    }

    /** Liefert die gültige globale Schriftgrößenstufe für das Widget. */
    public static function fontSize(): string
    {
        $value = strtolower((string)self::currentSiteConfig()->MashaFeedlyFontSize);
        return in_array($value, ['small', 'medium', 'large'], true) ? $value : 'small';
    }

    /** Liefert das gespeicherte Widget-Theme und fällt bei Altbestand auf Verspielt zurück. */
    public static function theme(): string
    {
        $value = strtolower((string)self::currentSiteConfig()->MashaFeedlyTheme);
        return preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $value) ? $value : 'playful';
    }

    /** Liefert den Erinnerungsmodus und nutzt für bestehende Installationen weiterhin Cronjobs. */
    public static function dueDateReminderMode(): string
    {
        return strtolower((string)self::currentSiteConfig()->MashaFeedlyDueDateReminderMode) === 'visitor'
            ? 'visitor'
            : 'cron';
    }

    /** Liefert den konfigurierten Stundensatz in Euro. */
    public static function hourlyRate(): float
    {
        return max(0.0, (float)self::currentSiteConfig()->MashaFeedlyHourlyRate);
    }

    /**
     * Prüft, ob ein Silverstripe-Mitglied Masha Feedly verwenden darf.
     *
     * @param Member|null $member Zu prüfendes Silverstripe-Mitglied.
     * @return bool Gibt zurück, ob Zugriff erlaubt ist.
     */
    public static function canUse(?Member $member): bool
    {
        if (!$member || !$member->isInDB()) {
            return false;
        }

        // Während dev/build kann das neue Feld noch fehlen. Der ohnehin nötige Config-Lesezugriff
        // erkennt das direkt; eine separate Schemaabfrage wäre dafür zusätzliche Datenbankarbeit.
        try {
            $allowedIDs = self::configuredMemberIDs();
        } catch (DatabaseException) {
            return false;
        }

        return in_array((int)$member->ID, $allowedIDs, true);
    }

    /** Prüft das während dev/build benötigte Feld einmal pro HTTP-Request. */
    private static function hasSiteConfigField(string $fieldName): bool
    {
        $request = Controller::curr()?->getRequest();
        if (!$request) {
            return DB::get_schema()->hasField('SiteConfig', $fieldName);
        }

        if (self::$schemaCheckRequest !== $request) {
            self::$schemaCheckRequest = $request;
            self::$siteConfigFieldNamesCache = null;
        }

        if (self::$siteConfigFieldNamesCache === null) {
            $schema = DB::get_schema();
            self::$siteConfigFieldNamesCache = $schema->hasTable('SiteConfig')
                ? array_fill_keys(array_keys($schema->fieldList('SiteConfig')), true)
                : [];
        }

        return isset(self::$siteConfigFieldNamesCache[$fieldName]);
    }

    /** Prüft, ob ein Mitglied ausdrücklich in der Masha-Feedly-Freigabeliste steht. */
    public static function isExplicitlyAllowed(?Member $member): bool
    {
        if (!$member instanceof Member || !$member->isInDB()) {
            return false;
        }

        try {
            return in_array((int)$member->ID, self::configuredMemberIDs(), true);
        } catch (DatabaseException) {
            return false;
        }
    }

    /**
     * Liefert konfigurierte positive IDs ohne zusätzliche Mitgliedertabelle-Abfrage.
     *
     * @return int[] Positive und eindeutige IDs aus der Freigabeliste.
     */
    private static function configuredMemberIDs(): array
    {
        $request = Controller::curr()?->getRequest();
        $requestID = $request ? spl_object_id($request) : null;
        if ($requestID !== null && self::$memberIDsCacheRequestID === $requestID && self::$configuredMemberIDsCache !== null) {
            return self::$configuredMemberIDsCache;
        }

        $raw = (string)self::currentSiteConfig()->MashaFeedlyAllowedMemberIDs;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = preg_split('/\\s*,\\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        self::$configuredMemberIDsCache = array_values(array_unique(array_filter(
            array_map(static fn($id): int => is_scalar($id) ? (int)$id : 0, $decoded),
            static fn(int $id): bool => $id > 0
        )));
        self::$memberIDsCacheRequestID = $requestID;
        return self::$configuredMemberIDsCache;
    }

    /**
     * Liefert freigegebene Benutzer-IDs aus der SiteConfig.
     *
     * @return int[] Freigegebene, existierende Mitglieds-IDs.
     */
    public static function memberIDs(): array
    {
        $request = Controller::curr()?->getRequest();
        $requestID = $request ? spl_object_id($request) : null;
        if ($requestID !== null && self::$memberIDsCacheRequestID === $requestID && self::$memberIDsCache !== null) {
            return self::$memberIDsCache;
        }

        self::$memberIDsCache = self::normalizeMemberIDs(self::configuredMemberIDs());
        self::$memberIDsCacheRequestID = $requestID;
        return self::$memberIDsCache;
    }

    /** Verwirft die Request-Zwischenspeicherung nach Änderungen an der Freigabeliste. */
    private static function clearMemberIDsCache(): void
    {
        self::$memberIDsCache = null;
        self::$configuredMemberIDsCache = null;
        self::$memberIDsCacheRequestID = null;
    }

    /**
     * Prüft übermittelte IDs und entfernt ungültige oder doppelte Werte.
     *
     * @param mixed $memberIDs Ausgewählte IDs aus dem CMS-Listenfeld.
     * @return int[] Bereinigte IDs vorhandener Silverstripe-Mitglieder.
     */
    public static function normalizeMemberIDs($memberIDs): array
    {
        if (!is_array($memberIDs)) {
            $memberIDs = [$memberIDs];
        }

        $candidateIDs = array_values(array_unique(array_filter(
            array_map(static fn($id): int => (int)$id, $memberIDs),
            static fn(int $id): bool => $id > 0
        )));
        if (!$candidateIDs) {
            return [];
        }

        $existingIDs = Member::get()->filter('ID', $candidateIDs)->column('ID');
        $existingIDs = array_map('intval', $existingIDs);
        sort($existingIDs);
        return $existingIDs;
    }
}
