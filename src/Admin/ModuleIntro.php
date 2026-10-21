<?php

namespace KW\MashaFeedly\Admin;

use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\i18n\i18n;

/** Einheitlicher lokaler Moduleinstieg; optionale Hilfe prüft ihre eigenen Sammlungsrechte. */
final class ModuleIntro
{
    /** Rendert die kurze Einführung mit einem ausschließlich autorisierten Hilfezugang. */
    public static function render(): string
    {
        $help = self::help();
        // Der Namespace-Rückfall hält insbesondere Promptly mit Silverstripe 4/5/6 kompatibel.
        $dataClass = class_exists('SilverStripe\\Model\\ArrayData') ? 'SilverStripe\\Model\\ArrayData' : 'SilverStripe\\View\\ArrayData';
        return (string)$dataClass::create([
            'HeadingID' => 'masha-feedly-start-title',
            'ModuleName' => 'Masha:Feedly',
            'Title' => i18n::_t(self::class . '.TITLE', 'Aufgaben gemeinsam erledigen.'),
            'Description' => i18n::_t(self::class . '.DESCRIPTION', 'Sammle Meldungen direkt auf deiner Website. Besprich Änderungen, verteile Aufgaben und behalte den Bearbeitungsstand im Blick.'),
            'IconURL' => self::iconURL(),
            'HelpHTML' => $help,
            'HelpUnavailable' => i18n::_t(self::class . '.HELP_UNAVAILABLE', 'Die passende Hilfe ist derzeit nicht verfügbar.'),
        ])->renderWith('KW/MashaFeedly/Admin/ModuleIntro');
    }
    /** Gleicher autorisierter Hilfezugang für Kopfzeile und Einstieg. */
    public static function help(): string
    {
        $help = '';
        if (class_exists('KW\\MashaHelply\\MashaHelplyRenderer')) {
            $help = \KW\MashaHelply\MashaHelplyRenderer::create()->render('feedly', 'inline', i18n::_t(self::class . '.HELP', 'Hilfe zu Masha:Feedly öffnen'));
        }
        return $help;
    }

    /** Das lokale Modulmotiv benötigt keine Abhängigkeit auf das Dashboard. */
    public static function iconURL(): string
    {
        return ModuleResourceLoader::resourceURL('kooperativeweb/masha-feedly:client/dist/icons/masha-feedly.svg');
    }

}
