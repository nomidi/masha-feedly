<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\DataObject;
use SilverStripe\i18n\i18n;

/** Bearbeitbare Prioritätsstufe für Masha-Feedly-Einträge. */
class MashaFeedlyPriority extends DataObject
{
    private static $table_name = 'MashaFeedlyPriority';

    private static $db = [
        'Title' => 'Varchar(80)',
        'SystemKey' => 'Varchar(32)',
        'Description' => 'Varchar(255)',
        'Sort' => 'Int',
        'Color' => 'Varchar(20)',
        'IconType' => 'Varchar(16)',
    ];

    private static $has_many = [
        'Entries' => MashaFeedlyEntry::class . '.Priority',
    ];

    private static $summary_fields = [
        'Title' => 'Priorität',
        'Description' => 'Bedeutung',
        'Sort' => 'Reihenfolge',
    ];

    private static $default_sort = 'Sort ASC, Title ASC';

    /** Erstellt bzw. migriert die abgestuften Warnfarben sowie eine separate Info-Stufe. */
    public static function ensureDefaultPriorities(): void
    {
        foreach ([
            ['critical', 'Kritisch', 'Sofort bearbeiten', 'Ausfall oder blockierende Störung ohne brauchbare Umgehung.', 10, '#ed4040', 'warning'],
            ['high', 'Hoch', 'Zeitnah bearbeiten', 'Wichtige Funktion ist deutlich beeinträchtigt.', 20, '#ef873d', 'warning'],
            ['normal', 'Normal', 'Normal', 'Relevanter Fehler mit möglicher Umgehung.', 30, '#d7a916', 'warning'],
            ['low', 'Niedrig', 'Bei Gelegenheit', 'Kleine Unstimmigkeit oder kosmetischer Fehler.', 40, '#45a06a', 'warning'],
            ['info', 'Info', 'Info', 'Hinweis oder Information ohne Fehlermeldung.', 50, '#4285c7', 'info'],
        ] as [$key, $legacyTitle, $title, $description, $sort, $color, $iconType]) {
            $priority = self::get()->filter('SystemKey', $key)->first()
                ?? self::get()->filter('Title', [$legacyTitle, $title])->first();
            $isNew = !$priority;
            $priority ??= self::create();
            $isLegacy = !$isNew && (string)$priority->Title === $legacyTitle && $legacyTitle !== $title;
            $changed = $isNew
                || (string)$priority->SystemKey !== $key
                || $isLegacy;

            if ($isLegacy || $isNew) {
                $priority->Title = $title;
            }
            $priority->SystemKey = $key;
            // Initialize defaults once while retaining values an administrator has customized.
            foreach ([
                'Description' => $description,
                'Sort' => $sort,
                'Color' => $color,
                'IconType' => $iconType,
            ] as $field => $default) {
                if ($isNew || (string)$priority->$field === '' || ($field === 'Sort' && !(int)$priority->$field)) {
                    $priority->$field = $default;
                    $changed = true;
                }
            }
            if ($changed) {
                $priority->write();
            }
        }
    }

    /** Liefert die Standardpriorität für neue Einträge. */
    public static function defaultPriority(): self
    {
        self::ensureDefaultPriorities();
        return self::get()->filter('SystemKey', 'normal')->first() ?? self::get()->filter('Title', 'Normal')->first() ?? self::get()->sort('Sort ASC')->first();
    }

    /** Legt die verständlichen CMS-Felder der Priorität an. */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Entries', 'ClassName', 'SystemKey']);
        $fields->replaceField('Title', TextField::create('Title', i18n::_t('KW\\MashaFeedly\\Translations.PRIORITY_TITLE', 'Priorität')));
        $fields->replaceField('Description', TextareaField::create('Description', i18n::_t('KW\\MashaFeedly\\Translations.PRIORITY_DESCRIPTION', 'Wann diese Priorität verwenden')));
        $fields->replaceField('Color', TextField::create('Color', i18n::_t('KW\\MashaFeedly\\Translations.PRIORITY_COLOR', 'Kennfarbe')));
        $fields->replaceField('IconType', DropdownField::create('IconType', 'Symbol', [
            'warning' => 'Warnsymbol',
            'info' => 'Info-Symbol',
        ]));
        return $fields;
    }

    /** Liefert ausschließlich die zwei erlaubten, statischen SVG-Symbole. */
    public function getIconSVG(): string
    {
        if ($this->IconType === 'info') {
            return '<svg viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M256 0C114.509 0 0 114.496 0 256s114.496 256 256 256 256-114.496 256-256S397.504 0 256 0zm0 476.279c-121.462 0-220.279-98.816-220.279-220.279S134.538 35.721 256 35.721 476.279 134.537 476.279 256 377.462 476.279 256 476.279z"/><path d="M256.006 213.397c-15.164 0-25.947 6.404-25.947 15.839v128.386c0 8.088 10.783 16.174 25.947 16.174 14.49 0 26.283-8.086 26.283-16.174V229.234c0-9.434-11.793-15.837-26.283-15.837z"/><path d="M256.006 134.208c-15.501 0-27.631 11.12-27.631 23.925s12.131 24.263 27.631 24.263c15.164 0 27.296-11.457 27.296-24.263s-12.132-23.925-27.296-23.925z"/></svg>';
        }

        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="kw-masha-feedly__priority-shape" d="m14.45 4a2.86 2.86 0 0 0-4.9 0l-7.88 12.87a2.87 2.87 0 0 0 2.45 4.36h15.76a2.87 2.87 0 0 0 2.45-4.36z"/><path class="kw-masha-feedly__priority-mark" d="M12 14.75a.76.76 0 0 1-.75-.75v-4.5a.75.75 0 0 1 1.5 0V14a.76.76 0 0 1-.75.75z"/><circle class="kw-masha-feedly__priority-mark" cx="12" cy="16.5" r="1"/></svg>';
    }

    /** Beschränkt Farben auf Hexwerte, da die Werte in Icon-Styles ausgegeben werden. */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$this->Color)) {
            $result->addFieldError('Color', 'Bitte eine Hex-Farbe wie #ed4040 eintragen.');
        }
        if (!in_array((string)$this->IconType, ['warning', 'info'], true)) {
            $result->addFieldError('IconType', 'Bitte ein gültiges Symbol auswählen.');
        }
        return $result;
    }

    /** Schützt die fünf Systemprioritäten vor versehentlichem Löschen im CMS. */
    public function canDelete($member = null): bool
    {
        return (string)$this->SystemKey === '' && parent::canDelete($member);
    }
}
