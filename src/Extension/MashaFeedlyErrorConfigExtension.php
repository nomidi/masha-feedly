<?php

namespace KW\MashaFeedly\Extension;

use KW\MashaFeedly\Logging\MashaFeedlyErrorHandler;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\i18n\i18n;

/**
 * Konfiguriert die optionale Erfassung technischer Fehler ausschließlich im Betreiberbereich.
 * @property bool $MashaFeedlyErrorsEnabled Aktivierte Erfassung.
 * @property string $MashaFeedlyErrorsMode Verarbeitung per cron oder visitor.
 * @property int $MashaFeedlyErrorsInterval Intervall in Minuten.
 * @property string $MashaFeedlyErrorsLevel Mindestschweregrad.
 * @property string $MashaFeedlyErrorsMemberIDs JSON-Liste zusätzlich berechtigter Personen.
 * @property bool $MashaFeedlyErrorsDigest Aktivierte tägliche Zusammenfassung.
 * @property string $MashaFeedlyErrorsDigestTime Versandzeit in Europe/Berlin.
 */
class MashaFeedlyErrorConfigExtension extends Extension
{
    private static $db = [
        'MashaFeedlyErrorsEnabled' => 'Boolean',
        'MashaFeedlyErrorsMode' => 'Varchar(10)',
        'MashaFeedlyErrorsInterval' => 'Int',
        'MashaFeedlyErrorsLevel' => 'Varchar(10)',
        'MashaFeedlyErrorsMemberIDs' => 'Text',
        'MashaFeedlyErrorsDigest' => 'Boolean',
        'MashaFeedlyErrorsDigestTime' => 'Varchar(5)',
    ];

    private static $defaults = [
        'MashaFeedlyErrorsEnabled' => false,
        'MashaFeedlyErrorsMode' => 'cron',
        'MashaFeedlyErrorsInterval' => 5,
        'MashaFeedlyErrorsLevel' => 'error',
        'MashaFeedlyErrorsDigestTime' => '08:00',
    ];

    /** Entfernt die sensiblen Felder aus der allgemeinen Websitekonfiguration. */
    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(array_keys(self::$db));
    }

    /** Synchronisiert die Aktivierung auf Platte, damit der LogHandler keine Datenbank benötigt. */
    protected function onAfterWrite(): void
    {
        try {
            MashaFeedlyErrorHandler::prepareDirectory();
            MashaFeedlyErrorHandler::writeJSON(MashaFeedlyErrorHandler::directory() . '/settings.json', [
                'enabled' => (bool)$this->owner->MashaFeedlyErrorsEnabled,
                'level' => ['error' => 400, 'critical' => 500, 'alert' => 550, 'emergency' => 600][(string)$this->owner->MashaFeedlyErrorsLevel] ?? 400,
            ]);
        } catch (\Throwable $exception) {
            // Andere Website-Einstellungen müssen auch bei einer nicht beschreibbaren Fehlerablage speicherbar bleiben.
            error_log('[Masha:Feedly] Fehlererfassung konnte nicht konfiguriert werden (' . get_class($exception) . ').');
        }
    }

    /** Liefert das konfigurierte Betreiberkonto; ohne eindeutiges Administratorkonto erfolgt kein Import. @return Member|null Betreiber. */
    public static function manager(): ?Member
    {
        $emails = (array)MashaFeedlyEntry::config()->get('reporter_manager_emails');
        foreach ($emails as $email) {
            $member = Member::get()->filter('Email', trim((string)$email))->first();
            if ($member && MashaFeedlyEntry::canManageReporter($member)) return $member;
        }
        return null;
    }

    /** Prüft zusätzliche Freigabe oder Betreiberrolle. @param Member|null $member Zu prüfende Person. @return bool Technischer Zugriff. */
    public static function canAccess(?Member $member): bool
    {
        if (!$member) return false;
        if (MashaFeedlyEntry::canManageReporter($member)) return true;
        $ids = json_decode((string)SiteConfig::current_site_config()->MashaFeedlyErrorsMemberIDs, true) ?: [];
        return MashaFeedlyConfigExtension::canUse($member) && in_array((int)$member->ID, array_map('intval', $ids), true);
    }

    /** Erstellt die Betreiberfelder mit gespeicherten Werten. @return FieldList Konfigurationsfelder. */
    public static function fields(): FieldList
    {
        $config = SiteConfig::current_site_config();
        $t = static fn(string $key, string $fallback): string => i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $fallback);
        $members = [];
        foreach (MashaFeedlyConfigExtension::memberIDs() ? Member::get()->filter('ID', MashaFeedlyConfigExtension::memberIDs()) : [] as $member) {
            $members[(int)$member->ID] = $member->getName();
        }
        $fields = FieldList::create(
            CheckboxField::create('MashaFeedlyErrorsEnabled', $t('ERRORS_ENABLED', 'Technische Fehler automatisch erfassen')),
            DropdownField::create('MashaFeedlyErrorsMode', $t('ERRORS_MODE', 'Fehler verarbeiten'), [
                'cron' => $t('CONFIG_DUE_DATE_REMINDER_CRON', 'Serverseitig per Cronjob'),
                'visitor' => $t('CONFIG_DUE_DATE_REMINDER_VISITOR', 'Bei Websitebesuchen'),
            ])->setDescription($t('ERRORS_MODE_HELP', 'Ohne Websitebesuche verschiebt sich die Verarbeitung im Besuchsmodus. Cronjobs laufen unabhängig davon.')),
            NumericField::create('MashaFeedlyErrorsInterval', $t('ERRORS_INTERVAL', 'Verarbeitungsintervall (Minuten)'))->setAttribute('min', '1')->setAttribute('max', '1440'),
            DropdownField::create('MashaFeedlyErrorsLevel', $t('ERRORS_LEVEL', 'Mindestschweregrad'), ['error' => 'Error', 'critical' => 'Critical', 'alert' => 'Alert', 'emergency' => 'Emergency']),
            ListboxField::create('MashaFeedlyErrorsMemberIDs', $t('ERRORS_MEMBERS', 'Zusätzliche Personen mit Zugriff auf technische Fehler'), $members)
                ->setDescription($t('ERRORS_ACCESS_HELP', 'Technische Fehler werden immer dem konfigurierten Betreiberkonto zugewiesen. Nur dieses Konto und hier ausgewählte Personen sehen die Meldungen. Bestehende persönliche E-Mail-Einstellungen gelten; Wiederholungen erzeugen keine Änderungsmail.')),
            CheckboxField::create('MashaFeedlyErrorsDigest', $t('ERRORS_DIGEST', 'Tägliche Fehlerzusammenfassung senden')),
            TextField::create('MashaFeedlyErrorsDigestTime', $t('ERRORS_DIGEST_TIME', 'Versandzeit (HH:MM, Europe/Berlin)')),
        );
        foreach (array_keys(self::$db) as $name) {
            $value = $config->getField($name);
            if ($name === 'MashaFeedlyErrorsInterval' && (int)$value < 1) $value = 5;
            if (($value === null || $value === '' || $value === 0) && array_key_exists($name, self::$defaults)) {
                $value = self::$defaults[$name];
            }
            $fields->dataFieldByName($name)->setValue($value);
        }
        $fields->dataFieldByName('MashaFeedlyErrorsMemberIDs')->setValue(json_decode((string)$config->MashaFeedlyErrorsMemberIDs, true) ?: []);
        return $fields;
    }

    /** Übernimmt ausschließlich validierte Betreiberwerte. @param SiteConfig $config Ziel. @param array<string, mixed> $data Formulardaten. @return void */
    public static function save(SiteConfig $config, array $data): void
    {
        $config->MashaFeedlyErrorsEnabled = !empty($data['MashaFeedlyErrorsEnabled']);
        if ($config->MashaFeedlyErrorsEnabled && !self::manager()) {
            throw new \InvalidArgumentException(i18n::_t('KW\\MashaFeedly\\Translations.ERRORS_MANAGER_REQUIRED', 'Das konfigurierte Betreiberkonto muss vorhanden sein und Administratorrechte besitzen.'));
        }
        $config->MashaFeedlyErrorsMode = ($data['MashaFeedlyErrorsMode'] ?? '') === 'visitor' ? 'visitor' : 'cron';
        $config->MashaFeedlyErrorsInterval = max(1, min(1440, (int)($data['MashaFeedlyErrorsInterval'] ?? 5)));
        $level = (string)($data['MashaFeedlyErrorsLevel'] ?? 'error');
        $config->MashaFeedlyErrorsLevel = in_array($level, ['error', 'critical', 'alert', 'emergency'], true) ? $level : 'error';
        $config->MashaFeedlyErrorsMemberIDs = json_encode(array_values(array_intersect(
            array_map('intval', (array)($data['MashaFeedlyErrorsMemberIDs'] ?? [])),
            MashaFeedlyConfigExtension::normalizeMemberIDs($data['AllowedMemberIDs'] ?? [])
        )));
        $config->MashaFeedlyErrorsDigest = !empty($data['MashaFeedlyErrorsDigest']);
        $time = (string)($data['MashaFeedlyErrorsDigestTime'] ?? '08:00');
        $config->MashaFeedlyErrorsDigestTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time) ? $time : '08:00';
    }
}
