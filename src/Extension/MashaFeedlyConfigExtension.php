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
use SilverStripe\ORM\Connect\DatabaseException;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;
use KW\MashaFeedly\Model\MashaFeedlyMiteTrigger;

/**
 * Ergänzt die SiteConfig um die für Masha Feedly freigegebenen Benutzer.
 *
 * @property string $MashaFeedlyAddress Konfigurierte Anrede im Modul (du oder sie).
 * @property string $MashaFeedlyTheme Website-Vorgabe für das persönliche Masha-Feedly-Erscheinungsbild.
 * @property string $MashaFeedlyAllowedMemberIDs JSON-Liste freigegebener Mitglieds-IDs.
 * @property bool $MashaFeedlyClosedCategoriesMigrated Kennzeichnet die einmalige Übernahme abgeschlossener Kategorien.
 * @property bool $MashaFeedlyEmailTestSucceeded Kennzeichnet einen erfolgreichen globalen E-Mail-Test.
 * @property string $MashaFeedlyEmailFooter Inhalt des zentralen E-Mail-Footers.
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
        'MashaFeedlyDueDateReminderMode' => 'Varchar(20)',
        'MashaFeedlyHourlyRate' => 'Decimal(10,2)',
        'MashaFeedlyMiteProjectID' => 'Int',
        'MashaFeedlyMiteEnabled' => 'Boolean',
        'MashaFeedlyEstimateCategoriesSeeded' => 'Boolean',
        'MashaFeedlyDueDateReminderLastRunDate' => 'Date',
        'MashaFeedlyClosedCategoriesMigrated' => 'Boolean',
        'MashaFeedlyEmailTestSucceeded' => 'Boolean',
        'MashaFeedlyEmailFooter' => 'Text',
    ];

    private static $defaults = [
        'MashaFeedlyTheme' => 'playful',
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
            'MashaFeedlyMiteProjectID',
            'MashaFeedlyMiteEnabled',
            'MashaFeedlyMiteTriggers',
            'MashaFeedlyEmailFooter',
        ]);
    }

    /** Ergänzt den E-Mail-Footer bei dev/build, solange noch kein eigener Text gespeichert wurde. */
    public function requireDefaultRecords(): void
    {
        if (!self::hasSiteConfigField('MashaFeedlyEmailFooter')) {
            return;
        }
        $config = self::currentSiteConfig();
        if (trim((string)$config->MashaFeedlyEmailFooter) !== '') {
            return;
        }
        $footer = self::defaultEmailFooter();
        if ($footer === '') {
            return;
        }
        $config->MashaFeedlyEmailFooter = $footer;
        $config->write();
    }

    /** Liest den optionalen Footer aus der Serverumgebung und decodiert Zeilenumbrüche. */
    public static function defaultEmailFooter(): string
    {
        $footer = trim((string)Environment::getEnv('MASHA_FEEDLY_EMAIL_FOOTER'));
        if ($footer === '') {
            return '';
        }

        // Mehrzeilige Werte lassen sich in der .env mit den Zeichenfolgen \n hinterlegen.
        return trim(str_replace(['\\r\\n', '\\n', '\\r'], "\n", $footer));
    }

    /** Liefert den gespeicherten Footer oder den Wert aus der Serverumgebung. */
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
        // requireDefaultRecords kann während dev/build vor dem Schema-Update laufen.
        if (!self::hasSiteConfigField('MashaFeedlyEmailTestSucceeded')) {
            return false;
        }

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

    /** Begrüßt Mitglieder nach erfolgreicher Speicherung ihrer neuen Freigabe. */
    protected function onAfterWrite(): void
    {
        self::clearMemberIDsCache();
        foreach ($this->newlyAllowedMemberIDs as $memberID) {
            $member = Member::get()->byID($memberID);
            if ($member) {
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

        // dev/build initialisiert Controller, bevor neue SiteConfig-Spalten angelegt sind.
        // In diesem kurzen Zeitraum darf keine ORM-Abfrage alle SiteConfig-Felder auswählen.
        if (!self::hasSiteConfigField('MashaFeedlyEmailFooter')) {
            return false;
        }

        return in_array((int)$member->ID, self::memberIDs(), true);
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
        return $member instanceof Member
            && $member->isInDB()
            && in_array((int)$member->ID, self::memberIDs(), true);
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

        $config = self::currentSiteConfig();
        $raw = (string)$config->MashaFeedlyAllowedMemberIDs;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        self::$memberIDsCache = self::normalizeMemberIDs($decoded);
        self::$memberIDsCacheRequestID = $requestID;
        return self::$memberIDsCache;
    }

    /** Verwirft die Request-Zwischenspeicherung nach Änderungen an der Freigabeliste. */
    private static function clearMemberIDsCache(): void
    {
        self::$memberIDsCache = null;
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
