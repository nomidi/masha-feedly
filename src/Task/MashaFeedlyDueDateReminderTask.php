<?php

namespace KW\MashaFeedly\Task;

use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/** Versendet einmal täglich E-Mail-Erinnerungen für fällige offene Einträge. */
class MashaFeedlyDueDateReminderTask extends BuildTask
{
    protected static string $commandName = 'MashaFeedlyDueDateReminderTask';

    public function __construct()
    {
        parent::__construct();
        $this->title = 'Masha:Feedly – Fälligkeitserinnerungen';
        $this->description = 'Versendet einmalige Erinnerungen für heute oder überfällige Einträge.';
    }

    /** Führt den täglichen Erinnerungsversand aus. */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (MashaFeedlyConfigExtension::dueDateReminderMode() !== 'cron') {
            $output->writeln('Fälligkeitserinnerungen werden bei Websitebesuchen geprüft; Cronjob übersprungen.');
            return Command::SUCCESS;
        }

        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
        $sent = self::sendDueRemindersForDate($today);
        $output->writeln(sprintf('Fälligkeitserinnerungen versendet: %d', $sent));
        return Command::SUCCESS;
    }

    /** Unterstützt den klassischen Task-Aufruf aus Silverstripe 5 und PolyExecution in Silverstripe 6. */
    public function run($input, $output = null): int
    {
        if ($output instanceof PolyOutput) {
            return parent::run($input, $output);
        }

        if (MashaFeedlyConfigExtension::dueDateReminderMode() !== 'cron') {
            echo 'Fälligkeitserinnerungen werden bei Websitebesuchen geprüft; Cronjob übersprungen.';
            return Command::SUCCESS;
        }

        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
        $sent = self::sendDueRemindersForDate($today);
        echo sprintf('Fälligkeitserinnerungen versendet: %d', $sent);
        return Command::SUCCESS;
    }

    /** Führt die Prüfung bei der ersten Websiteanfrage des konfigurierten Tages höchstens einmal aus. */
    public static function runForWebsiteVisit(?string $date = null): bool
    {
        $today = $date ?: (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
        if (!self::claimWebsiteVisitRun($today)) {
            return false;
        }

        self::sendDueRemindersForDate($today);
        return true;
    }

    /** Beansprucht den täglichen Besuchslauf atomar, sodass parallele Seitenaufrufe keinen Doppellauf starten. */
    public static function claimWebsiteVisitRun(string $date): bool
    {
        if (MashaFeedlyConfigExtension::dueDateReminderMode() !== 'visitor') {
            return false;
        }

        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $table = DataObject::getSchema()->tableName(SiteConfig::class);
        $connection = DB::get_conn();
        $table = $connection->escapeIdentifier($table);
        $id = $connection->escapeIdentifier('ID');
        $lastRun = $connection->escapeIdentifier('MashaFeedlyDueDateReminderLastRunDate');
        DB::prepared_query(
            "UPDATE {$table} SET {$lastRun} = ? WHERE {$id} = ? AND ({$lastRun} IS NULL OR {$lastRun} < ?)",
            [$date, (int)$siteConfig->ID, $date]
        );
        return DB::affected_rows() === 1;
    }

    /** Versendet Erinnerungen für offene Einträge, die am angegebenen Tag oder früher fällig sind. */
    public static function sendDueRemindersForDate(string $date): int
    {
        $sent = 0;
        foreach (self::entriesDueOnOrBefore($date) as $entry) {
            if ((bool)$entry->Category()->IsClosed) {
                continue;
            }
            $sent += MashaFeedlyNotificationService::notifyDueDateReminder($entry);
        }
        return $sent;
    }

    /** Liefert überfällige oder heute fällige Einträge ohne bereits versendete Erinnerung. */
    public static function entriesDueOnOrBefore(string $date)
    {
        return MashaFeedlyEntry::get()
            ->filter('DueDate:LessThanOrEqual', $date)
            ->filter('DueDateReminderSentAt', null)
            ->sort('DueDate ASC, ID ASC');
    }
}
