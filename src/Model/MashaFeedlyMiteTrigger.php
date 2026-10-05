<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;

/** Ordnet der Website eine Statuskategorie zu, die den freiwilligen Mite-Timerdialog auslöst.
 *
 * @property int $ID Datenbank-ID der Zuordnung.
 * @property string $Created Erstellungszeitpunkt.
 * @property string $LastEdited Zeitpunkt der letzten Änderung.
 * @property int $SiteConfigID ID der Website-Konfiguration.
 * @property SiteConfig $SiteConfig Zugehörige Website-Konfiguration.
 * @property int $CategoryID ID der auslösenden Kategorie.
 * @property MashaFeedlyCategory $Category Auslösende Kategorie.
 */
class MashaFeedlyMiteTrigger extends DataObject
{
    private static $table_name = 'MashaFeedlyMiteTrigger';

    private static $has_one = [
        'SiteConfig' => SiteConfig::class,
        'Category' => MashaFeedlyCategory::class,
    ];

    private static $indexes = [
        'ConfigCategoryUnique' => ['type' => 'unique', 'columns' => ['SiteConfigID', 'CategoryID']],
    ];

    /** Die Zuordnungen werden ausschließlich über die zentrale Mehrfachauswahl gepflegt. */
    public function getCMSFields(): FieldList
    {
        return new FieldList();
    }

    /** @param \SilverStripe\Security\Member|null $member Zu prüfendes CMS-Konto. */
    public function canView($member = null): bool
    {
        return MashaFeedlyEntry::canManageReporter($member);
    }

    /** @param \SilverStripe\Security\Member|null $member Zu prüfendes CMS-Konto. */
    public function canEdit($member = null): bool
    {
        return MashaFeedlyEntry::canManageReporter($member);
    }

    /**
     * @param \SilverStripe\Security\Member|null $member Zu prüfendes CMS-Konto.
     * @param array<string, mixed> $context Optionaler CMS-Kontext.
     */
    public function canCreate($member = null, $context = []): bool
    {
        return MashaFeedlyEntry::canManageReporter($member);
    }

    /** @param \SilverStripe\Security\Member|null $member Zu prüfendes CMS-Konto. */
    public function canDelete($member = null): bool
    {
        return MashaFeedlyEntry::canManageReporter($member);
    }

    /** Verhindert ungültige oder mehrfach gespeicherte Konfigurationszuordnungen. */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (!SiteConfig::get()->byID((int)$this->SiteConfigID)) {
            $result->addFieldError('SiteConfigID', 'Eine vorhandene Website-Konfiguration ist erforderlich.');
        }
        if (!MashaFeedlyCategory::get()->byID((int)$this->CategoryID)) {
            $result->addFieldError('CategoryID', 'Bitte eine vorhandene Kategorie auswählen.');
        }
        $existing = self::get()->filter(['SiteConfigID' => (int)$this->SiteConfigID, 'CategoryID' => (int)$this->CategoryID]);
        if ($this->isInDB()) {
            $existing = $existing->exclude('ID', (int)$this->ID);
        }
        if ($existing->exists()) {
            $result->addFieldError('CategoryID', 'Diese Kategorie ist bereits als Mite-Auslöser gespeichert.');
        }
        return $result;
    }
}
