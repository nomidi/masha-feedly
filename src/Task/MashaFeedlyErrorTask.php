<?php

namespace KW\MashaFeedly\Task;

use KW\MashaFeedly\Service\MashaFeedlyErrorService;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;

/** Verarbeitet technische Fehler und fällige Zusammenfassungen im Cronmodus. */
class MashaFeedlyErrorTask extends BuildTask
{
    protected static string $commandName = 'MashaFeedlyErrorTask';
    protected string $title = 'Masha:Feedly – Technische Fehler';
    protected static string $description = 'Übernimmt gesammelte technische Fehler und versendet fällige Zusammenfassungen.';

    /** Führt die gemeinsam gesperrte Intervallprüfung aus. */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        try {
            $output->writeln('Übernommene Fehlergruppen: ' . MashaFeedlyErrorService::run('cron'));
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('Fehlerverarbeitung fehlgeschlagen (' . get_class($exception) . '). Details bitte im unabhängigen Fehlerprotokoll prüfen.');
            return Command::FAILURE;
        }
    }
}
