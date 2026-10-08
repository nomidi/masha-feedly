<?php

namespace KW\MashaFeedly\Tests\Unit\Extension;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Tests für Freigabeprüfung und Bereinigung der Benutzerliste.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyConfigExtensionTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedlyConfig.yml';

    /** Regressionstest: Silverstripe ruft Extension-Hooks beim Schreiben über den Owner auf. */
    public function testSiteConfigCanBeWrittenWithExtensionHooks(): void
    {
        $siteConfig = SiteConfig::create();
        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([]);

        $siteConfig->write();

        $this->assertGreaterThan(0, (int)$siteConfig->ID);
    }

    /** Stellt sicher, dass SiteConfig-Felder physisch angelegt sind und regulär gelesen werden können. */
    public function testThemeFieldExistsInSiteConfigSchema(): void
    {
        $fields = DB::get_schema()->fieldList('SiteConfig');

        $this->assertArrayHasKey('MashaFeedlyTheme', $fields);
        $this->assertArrayHasKey('MashaFeedlyFontSize', $fields);
        $this->assertArrayHasKey('MashaFeedlyDueDateReminderMode', $fields);
        $this->assertArrayHasKey('MashaFeedlyDueDateReminderLastRunDate', $fields);
        $this->assertArrayHasKey('MashaFeedlyEmailTestSucceeded', $fields);
        $this->assertFalse(MashaFeedlyConfigExtension::emailTestSucceeded());

        // Eine echte ORM-Abfrage deckt fehlende Spalten auf, die eine reine Config-Prüfung übersieht.
        $siteConfig = SiteConfig::create();
        $siteConfig->MashaFeedlyTheme = 'serious';
        $siteConfig->write();
        $reloaded = SiteConfig::get()->byID($siteConfig->ID);
        $this->assertNotNull($reloaded);
        $this->assertSame('serious', $reloaded->MashaFeedlyTheme);
    }

    /** Prüft, dass Auswahl Zugriff erteilt und Entfernen ihn wieder entzieht. */
    public function testSelectedMembersCanUseMashaFeedly(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $otherMember = $this->objFromFixture(Member::class, 'notAllowed');

        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([$allowedMember->ID]);
        $siteConfig->write();

        $this->assertTrue(MashaFeedlyConfigExtension::canUse($allowedMember));
        $this->assertFalse(MashaFeedlyConfigExtension::canUse($otherMember));
        $this->assertFalse(MashaFeedlyConfigExtension::canUse(null));
        $this->assertSame([(int)$allowedMember->ID], MashaFeedlyConfigExtension::memberIDs());

        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([]);
        $siteConfig->write();
        $this->assertFalse(MashaFeedlyConfigExtension::canUse($allowedMember));
    }

    /** Prüft, dass doppelte, ungültige und unbekannte IDs entfernt werden. */
    public function testSubmittedMemberIDsAreNormalized(): void
    {
        $member = $this->objFromFixture(Member::class, 'normalize');

        $this->assertSame(
            [(int)$member->ID],
            MashaFeedlyConfigExtension::normalizeMemberIDs([
                $member->ID,
                (string)$member->ID,
                -1,
                99999999,
                'not-a-user',
            ])
        );
    }

    /** Prüft Anredeauswahl und sicheren Du-Fallback bei leeren oder ungültigen Werten. */
    public function testConfiguredAddressSupportsDuAndSie(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAddress = 'du';
        $siteConfig->write();
        $this->assertSame('du', MashaFeedlyConfigExtension::address());

        $siteConfig->MashaFeedlyAddress = 'sie';
        $siteConfig->write();
        $this->assertSame('sie', MashaFeedlyConfigExtension::address());

        $siteConfig->MashaFeedlyAddress = 'xxx';
        $siteConfig->write();
        $this->assertSame('du', MashaFeedlyConfigExtension::address());
    }

    /** Schriftgröße akzeptiert nur drei Stufen und fällt bei Altbestand sicher auf Klein zurück. */
    public function testConfiguredFontSizeSupportsThreePresets(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        foreach (['small', 'medium', 'large'] as $size) {
            $siteConfig->MashaFeedlyFontSize = $size;
            $siteConfig->write();
            $this->assertSame($size, MashaFeedlyConfigExtension::fontSize());
        }

        $siteConfig->MashaFeedlyFontSize = 'unexpected';
        $siteConfig->write();
        $this->assertSame('small', MashaFeedlyConfigExtension::fontSize());
    }

    /** Anbieter-Kategorien bleiben erhalten; leere oder syntaktisch ungültige Kennungen verwenden Verspielt. */
    public function testConfiguredThemeSupportsProviderCategoriesWithPlayfulFallback(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyTheme = '';
        $siteConfig->write();
        $this->assertSame('playful', MashaFeedlyConfigExtension::theme(), 'Eine nicht konfigurierte Website-Vorgabe startet immer verspielt.');
        foreach (['playful', 'serious', 'seasons', 'provider-category'] as $theme) {
            $siteConfig->MashaFeedlyTheme = $theme;
            $siteConfig->write();
            $this->assertSame($theme, MashaFeedlyConfigExtension::theme());
        }

        $siteConfig->MashaFeedlyTheme = 'invalid/category';
        $siteConfig->write();
        $this->assertSame('playful', MashaFeedlyConfigExtension::theme());
    }

    /** Auswahl der server- oder besuchergetriggerten Fälligkeitserinnerungen mit Cron-Fallback testen. */
    public function testDueDateReminderModeSupportsCronAndWebsiteVisits(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        foreach (['cron', 'visitor'] as $mode) {
            $siteConfig->MashaFeedlyDueDateReminderMode = $mode;
            $siteConfig->write();
            $this->assertSame($mode, MashaFeedlyConfigExtension::dueDateReminderMode());
        }

        $siteConfig->MashaFeedlyDueDateReminderMode = 'unexpected';
        $siteConfig->write();
        $this->assertSame('cron', MashaFeedlyConfigExtension::dueDateReminderMode());
    }
}
