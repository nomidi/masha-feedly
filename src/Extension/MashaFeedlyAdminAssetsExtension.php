<?php

namespace KW\MashaFeedly\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Lädt die Menü-Badge auf allen Seiten des Silverstripe-CMS.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyAdminAssetsExtension extends Extension
{
    /** Registriert die Badge-Assets nach der Initialisierung jeder CMS-Seite. */
    public function onAfterInit(): void
    {
        Requirements::css('kooperativeweb/masha-feedly:client/dist/css/masha-feedly-admin.css');
        Requirements::javascript('kooperativeweb/masha-feedly:client/dist/js/masha-feedly-admin.js');
    }
}
