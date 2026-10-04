<?php

namespace KW\MashaFeedly\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;

/**
 * Ergänzt die SiteConfig um die für Masha Feedly freigegebenen Benutzer.
 *
 * @property string $MashaFeedlyAddress Konfigurierte Anrede im Modul (du oder sie).
 * @property string $MashaFeedlyAllowedMemberIDs JSON-Liste freigegebener Mitglieds-IDs.
 * @property bool $MashaFeedlyClosedCategoriesMigrated Kennzeichnet die einmalige Übernahme abgeschlossener Kategorien.
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyConfigExtension extends Extension
{
    /** @var int[] */
    private array $newlyAllowedMemberIDs = [];

    private static $db = [
        'MashaFeedlyAllowedMemberIDs' => 'Text',
        'MashaFeedlyAddress' => "Varchar(3)",
        'MashaFeedlyFontSize' => 'Varchar(10)',
        'MashaFeedlyTheme' => 'Varchar(20)',
        'MashaFeedlyDueDateReminderMode' => 'Varchar(20)',
        'MashaFeedlyDueDateReminderLastRunDate' => 'Date',
        'MashaFeedlyClosedCategoriesMigrated' => 'Boolean',
    ];

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
        return in_array($value, ['playful', 'serious'], true) ? $value : 'playful';
    }

    /** Liefert den Erinnerungsmodus und nutzt für bestehende Installationen weiterhin Cronjobs. */
    public static function dueDateReminderMode(): string
    {
        return strtolower((string)self::currentSiteConfig()->MashaFeedlyDueDateReminderMode) === 'visitor'
            ? 'visitor'
            : 'cron';
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

        return Permission::checkMember($member, 'ADMIN')
            || in_array((int)$member->ID, self::memberIDs(), true);
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
        $config = self::currentSiteConfig();
        $raw = (string)$config->MashaFeedlyAllowedMemberIDs;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return self::normalizeMemberIDs($decoded);
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
