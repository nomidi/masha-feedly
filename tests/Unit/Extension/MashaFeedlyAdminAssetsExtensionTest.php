<?php

namespace KW\MashaFeedly\Tests\Unit\Extension;

use KW\MashaFeedly\Extension\MashaFeedlyAdminAssetsExtension;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Requirements;

/**
 * Prüft, dass die Menü-Badge-Assets auf allen CMS-Seiten geladen werden.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyAdminAssetsExtensionTest extends SapphireTest
{
    /** Prüft die globale Registrierung und die eingebundenen Badge-Assets. */
    public function testAssetsAreRegisteredForEveryCMSPage(): void
    {
        $extensions = LeftAndMain::config()->get('extensions');
        $this->assertContains(MashaFeedlyAdminAssetsExtension::class, $extensions);

        Requirements::clear();
        (new MashaFeedlyAdminAssetsExtension())->onAfterInit();

        $cssFiles = array_keys(Requirements::backend()->getCSS());
        $javascriptFiles = array_keys(Requirements::backend()->getJavascript());
        $this->assertTrue($this->containsAsset($cssFiles, 'masha-feedly-admin.css'));
        $this->assertTrue($this->containsAsset($javascriptFiles, 'masha-feedly-admin.js'));
        Requirements::clear();
    }

    /** Prüft, ob ein registrierter Asset-Pfad den erwarteten Dateinamen enthält. */
    private function containsAsset(array $assets, string $filename): bool
    {
        foreach ($assets as $asset) {
            if (str_ends_with((string)$asset, $filename)) {
                return true;
            }
        }
        return false;
    }
}
