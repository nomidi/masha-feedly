<?php

declare(strict_types=1);

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Admin\MashaFeedlyAdmin;
use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use SilverStripe\Security\Member;

/** Optionaler, ausschließlich lesender Feedly-Beitrag zum persönlichen masha.tools-Wochenrückblick. */
class WeeklySummaryProvider
{
    public function sections(Member $member, array $period): array
    {
        // Freigabe vor jedem Datenzugriff; ADMIN ersetzt die ausdrückliche Feedly-Mitgliederliste nicht.
        if (!MashaFeedlyConfigExtension::canUse($member) || !$member->MashaFeedlyEmailNotifications) return [];
        $start = $period['start'] . ' 00:00:00';
        $until = (new \DateTimeImmutable($period['end']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        $counts = ['new' => 0, 'open' => 0, 'overdue' => 0];
        $items = []; $visibleIDs = []; $errors = [];
        foreach (MashaFeedlyEntry::get()->sort('LastEdited DESC') as $entry) {
            if (!$entry->canView($member)) continue;
            $personal = (int)$entry->creatorMemberID() === (int)$member->ID || $entry->AssignedMembers()->filter('ID', $member->ID)->exists();
            if ($member->MashaFeedlyEmailAssignedOnly && !$personal) continue;
            if ($entry->ErrorFingerprint) {
                if ($entry->Created >= $start && $entry->Created < $until) $errors[] = $entry;
                continue;
            }
            $visibleIDs[] = (int)$entry->ID;
            $new = $entry->Created >= $start && $entry->Created < $until;
            if ($new) $counts['new']++;
            $open = !$entry->Category()->IsClosed;
            if ($open) {
                $counts['open']++;
                if ($entry->DueDate && $entry->DueDate <= $period['end']) $counts['overdue']++;
            }
            if (count($items) < 3 && ($new || ($open && $entry->DueDate && $entry->DueDate <= $period['end']))) {
                $items[] = ['label' => $entry->getTitle(), 'value' => $entry->Category()->Title];
            }
        }
        $closedTitles = MashaFeedlyCategory::get()->filter('IsClosed', true)->column('Title');
        $completed = $visibleIDs && $closedTitles ? MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => $visibleIDs, 'ChangeType' => 'status', 'NewValue' => $closedTitles,
            'Created:GreaterThanOrEqual' => $start, 'Created:LessThan' => $until,
        ])->exclude('OldValue', $closedTitles)->columnUnique('EntryID') : [];
        $sections = [];
        if ($counts['open'] || $counts['new'] || $completed) {
            $sections[] = ['source' => 'Feedly', 'title' => self::text('Tasks', 'Deine Aufgaben im Blick.'), 'color' => 'pink',
                'intro' => self::text('TaskIntro', 'Hier siehst du neue Aufgaben und Fortschritte aus deinem persönlichen Meldungsbereich.'),
                'period' => $period['start'] . ' – ' . $period['end'],
                'metrics' => [
                    ['label' => self::text('Done', 'In der Woche abgeschlossen'), 'value' => count($completed), 'achievement' => true],
                    ['label' => self::text('New', 'Neu in der Woche'), 'value' => $counts['new']],
                    ['label' => self::text('Open', 'Aktuell offen'), 'value' => $counts['open']],
                    ['label' => self::text('Due', 'Termin bis zum Wochenende'), 'value' => $counts['overdue']],
                ], 'items' => $items, 'url' => MashaFeedlyAdmin::singleton()->getLinkForModelTab(MashaFeedlyEntry::class),
                'action' => self::text('OpenTasks', 'Aufgaben und Meldungen ansehen'),
                'nextStep' => $counts['overdue'] ? self::text('DueStep', 'Ein guter Anfang: Schau dir eine Aufgabe mit vergangenem Termin an und lege fest, wie es damit weitergeht.')
                    : self::text('TaskStep', 'Welche kleine Verbesserung bringt deine Website als Nächstes weiter? Wähle eine offene Aufgabe oder halte eine neue Idee fest.')];
        }
        if ($errors) {
            $sections[] = ['source' => 'Feedly', 'title' => self::text('Errors', 'Technik, die gut begleitet bleibt.'), 'color' => 'yellow',
                'intro' => self::text('ErrorIntro', 'Diese technischen Hinweise wurden in der Woche neu erfasst. Die Anzahl bezeichnet Fehlergruppen, keine einzelnen Aufrufe.'),
                'metrics' => [['label' => self::text('ErrorGroups', 'Neue Fehlergruppen'), 'value' => count($errors)]],
                'items' => array_map(static fn ($entry): array => ['label' => $entry->getTitle(), 'value' => ''], array_slice($errors, 0, 3)),
                'nextStep' => self::text('ErrorStep', 'Öffne einen Hinweis und schau, ob er wiederkehrt. Wenn du Unterstützung möchtest, können wir die Ursache gemeinsam eingrenzen.'),
                'url' => MashaFeedlyAdmin::singleton()->getLinkForModelTab(MashaFeedlyEntry::class), 'action' => self::text('CheckErrors', 'Technische Hinweise ansehen')];
        }
        return $sections;
    }

    private static function text(string $key, string $fallback): string { return _t(self::class . '.' . $key, $fallback); }
}
