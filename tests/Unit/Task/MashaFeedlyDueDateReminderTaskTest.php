<?php

namespace KW\MashaFeedly\Tests\Unit\Task;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Task\MashaFeedlyDueDateReminderTask;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

/** Prüft die Auswahl überfälliger, heutiger, zukünftiger und bereits erinnerter Fälligkeiten. */
class MashaFeedlyDueDateReminderTaskTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedly.yml';

    public function testOnlyDueOrOverdueEntriesWithoutSentReminderAreSelected(): void
    {
        $overdue = MashaFeedlyEntry::create(['Content' => 'Überfällig', 'EntryDate' => '2026-10-01', 'DueDate' => '2026-10-03']);
        $overdue->write();
        $today = MashaFeedlyEntry::create(['Content' => 'Heute fällig', 'EntryDate' => '2026-10-01', 'DueDate' => '2026-10-04']);
        $today->write();
        $future = MashaFeedlyEntry::create(['Content' => 'Noch Zeit', 'EntryDate' => '2026-10-01', 'DueDate' => '2026-10-05']);
        $future->write();
        $alreadyReminded = MashaFeedlyEntry::create([
            'Content' => 'Schon erinnert', 'EntryDate' => '2026-10-01', 'DueDate' => '2026-10-02',
            'DueDateReminderSentAt' => '2026-10-02 07:00:00',
        ]);
        $alreadyReminded->write();

        $ids = array_map('intval', MashaFeedlyDueDateReminderTask::entriesDueOnOrBefore('2026-10-04')->column('ID'));
        $this->assertContains((int)$overdue->ID, $ids);
        $this->assertContains((int)$today->ID, $ids);
        $this->assertNotContains((int)$future->ID, $ids);
        $this->assertNotContains((int)$alreadyReminded->ID, $ids);
    }

    /** Websitebesuche lösen pro Tag höchstens einen Prüfungslauf aus; Cron-Modus bleibt davon unberührt. */
    public function testWebsiteVisitClaimsReminderRunOnlyOncePerDay(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyDueDateReminderMode = 'visitor';
        $siteConfig->MashaFeedlyDueDateReminderLastRunDate = null;
        $siteConfig->write();

        $this->assertTrue(MashaFeedlyDueDateReminderTask::claimWebsiteVisitRun('2026-10-04'));
        $this->assertFalse(MashaFeedlyDueDateReminderTask::claimWebsiteVisitRun('2026-10-04'));
        $reloadedConfig = SiteConfig::get()->byID((int)$siteConfig->ID);
        $this->assertSame('2026-10-04', (string)$reloadedConfig->MashaFeedlyDueDateReminderLastRunDate);
        $this->assertTrue(MashaFeedlyDueDateReminderTask::claimWebsiteVisitRun('2026-10-05'));

        $siteConfig->MashaFeedlyDueDateReminderMode = 'cron';
        $siteConfig->write();
        $this->assertFalse(MashaFeedlyDueDateReminderTask::claimWebsiteVisitRun('2026-10-06'));
    }
}
