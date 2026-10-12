<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;

/**
 * Speichert pro Mitglied den zuletzt gesehenen Stand eines Masha-Feedly-Eintrags.
 *
 * @property int $EntryID ID des gelesenen Eintrags.
 * @property int $MemberID ID des lesenden Mitglieds.
 * @property string $StateHash Fingerabdruck des zuletzt gesehenen Eintragsstands.
 * @property MashaFeedlyEntry $Entry Gelesener Eintrag.
 * @property Member $Member Lesendes Mitglied.
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyEntryRead extends DataObject
{
    private static $table_name = 'MashaFeedlyEntryRead';

    private static $db = [
        'StateHash' => 'Varchar(64)',
    ];

    private static $has_one = [
        'Entry' => MashaFeedlyEntry::class,
        'Member' => Member::class,
    ];

    private static $indexes = [
        'MemberEntry' => [
            'type' => 'unique',
            'columns' => ['MemberID', 'EntryID'],
        ],
    ];

    /** Markiert den aktuellen Eintragsstand für das Mitglied als gelesen. */
    public static function markAsSeen(MashaFeedlyEntry $entry, Member $member): void
    {
        if (!$entry->isInDB() || !$entry->ID) {
            return;
        }

        $read = self::get()->filter([
            'MemberID' => (int)$member->ID,
            'EntryID' => (int)$entry->ID,
        ])->first();
        if (!$read) {
            $read = self::create([
                'MemberID' => (int)$member->ID,
                'EntryID' => (int)$entry->ID,
            ]);
        }
        $read->StateHash = self::stateHash($entry);
        $read->write();
    }

    /** Zählt ungesehene Einträge und Änderungen individuell für ein Mitglied. */
    public static function unreadCount(Member $member): int
    {
        $counts = self::unreadCounts($member);
        return $counts['general'] + $counts['personal'];
    }

    /** Liefert die Zahl ungelesener Einträge sowie Kommentare seit dem letzten Öffnen je Eintrag. */
    public static function unreadActivityCounts(Member $member, ?array $unreadIDs = null): array
    {
        $unreadIDs ??= self::unreadEntryIDs($member);
        $commentCount = 0;
        if ($unreadIDs) {
            $schema = DataObject::getSchema();
            $connection = DB::get_conn();
            $historyTable = $connection->escapeIdentifier($schema->tableName(MashaFeedlyEntryHistory::class));
            $readTable = $connection->escapeIdentifier($schema->tableName(self::class));
            $entryTable = $connection->escapeIdentifier($schema->tableName(MashaFeedlyEntry::class));
            foreach (array_chunk($unreadIDs, 500) as $entryIDChunk) {
                $placeholders = implode(', ', array_fill(0, count($entryIDChunk), '?'));
                $commentRows = DB::prepared_query(
                    "SELECT history.\"EntryID\", COUNT(*) AS \"Count\" "
                        . "FROM {$historyTable} AS history "
                        . "INNER JOIN {$entryTable} AS entry ON entry.\"ID\" = history.\"EntryID\" "
                        . "LEFT JOIN {$readTable} AS read_state "
                        . "ON read_state.\"EntryID\" = history.\"EntryID\" AND read_state.\"MemberID\" = ? "
                        . "WHERE history.\"EntryID\" IN ({$placeholders}) "
                        . "AND history.\"ChangeType\" IN (?, ?) "
                        // Für bereits geöffnete Einträge gilt der Lesestand, sonst die Erstellungszeit.
                        . "AND history.\"Created\" > COALESCE(read_state.\"LastEdited\", entry.\"Created\") "
                        . "GROUP BY history.\"EntryID\"",
                    [(int)$member->ID, ...$entryIDChunk, 'comment', 'comment_edited']
                );
                foreach ($commentRows as $row) {
                    $commentCount += (int)$row['Count'];
                }
            }
        }

        return ['entries' => count($unreadIDs), 'comments' => $commentCount];
    }

    /** Liefert getrennte Zähler für allgemeine und persönlich zugeordnete ungelesene Einträge. */
    public static function unreadCounts(Member $member): array
    {
        $counts = ['general' => 0, 'personal' => 0];
        $unreadIDs = self::unreadEntryIDs($member);
        if (!$unreadIDs) {
            return $counts;
        }

        foreach (MashaFeedlyEntry::get()->filter('ID', $unreadIDs) as $entry) {
            $assignedMemberIDs = array_map('intval', $entry->AssignedMembers()->column('ID'));
            if (!$assignedMemberIDs) {
                $counts['general']++;
            } elseif (in_array((int)$member->ID, $assignedMemberIDs, true)
                || (int)$member->ID === $entry->creatorMemberID()
            ) {
                $counts['personal']++;
            }
        }
        return $counts;
    }

    /**
     * Liefert die IDs aller Einträge, deren aktueller Stand für ein Mitglied ungelesen ist.
     * @param list<MashaFeedlyEntry>|null $entries Bereits geladene Einträge zur Wiederverwendung.
     * @return list<int> IDs ungelesener Einträge.
     */
    public static function unreadEntryIDs(Member $member, ?array $entries = null): array
    {
        $seenReads = [];
        foreach (self::get()->filter('MemberID', (int)$member->ID) as $read) {
            $seenReads[(int)$read->EntryID] = $read;
        }

        $unreadIDs = [];
        $assignedByEntry = self::assignedMemberIDsByEntry();
        $entries ??= MashaFeedlyEntry::get()->toArray();
        $creatorMemberIDs = self::creatorMemberIDsByEntry($entries);
        foreach ($entries as $entry) {
            $entryID = (int)$entry->ID;
            $assignedMemberIDs = $assignedByEntry[$entryID] ?? [];
            $read = $seenReads[$entryID] ?? null;
            $currentHash = self::stateHash($entry, $assignedMemberIDs);
            if ($read && (string)$read->StateHash === $currentHash) {
                continue;
            }
            $isRecipient = !$assignedMemberIDs
                || in_array((int)$member->ID, $assignedMemberIDs, true)
                || (int)$member->ID === ($creatorMemberIDs[$entryID] ?? 0);
            if ($isRecipient) {
                $unreadIDs[] = (int)$entry->ID;
            }
        }
        return $unreadIDs;
    }

    /** Lädt die ursprünglichen Ersteller aller Einträge gemeinsam aus dem Verlauf. */
    private static function creatorMemberIDsByEntry(array $entries): array
    {
        $entryIDs = array_values(array_unique(array_filter(array_map(
            static fn(MashaFeedlyEntry $entry): int => (int)$entry->ID,
            $entries
        ))));
        if (!$entryIDs) {
            return [];
        }

        $creatorIDsByEntry = [];
        foreach (MashaFeedlyEntryHistory::get()
            ->filter(['EntryID' => $entryIDs, 'ChangeType' => 'created'])
            ->sort('EntryID ASC, Created ASC, ID ASC') as $event) {
            $entryID = (int)$event->EntryID;
            $creatorIDsByEntry[$entryID] ??= (int)$event->ActorMemberID;
        }

        return $creatorIDsByEntry;
    }

    /** Bildet den relevanten Stand aus Eintragsdaten, Zuständigkeit und letzter Aktivität. */
    private static function stateHash(MashaFeedlyEntry $entry, ?array $assignedMemberIDs = null): string
    {
        $assignedMemberIDs ??= self::assignedMemberIDs($entry);
        return hash('sha256', json_encode([
            'Content' => (string)$entry->Content,
            'EntryDate' => (string)$entry->EntryDate,
            'CategoryID' => (int)$entry->CategoryID,
            'PriorityID' => (int)$entry->PriorityID,
            'AssignedMemberIDs' => $assignedMemberIDs,
            // Der Zähler erfasst Aktivitäten auch dann, wenn der Zeitstempel gleich bleibt.
            'ActivityVersion' => (int)$entry->ActivityVersion,
            'LastActivityAt' => (string)($entry->LastActivityAt ?: $entry->Created),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Lädt alle Zuständigkeiten gesammelt für die Ungelesen-Prüfung. */
    private static function assignedMemberIDsByEntry(): array
    {
        $assignedByEntry = [];
        foreach (DB::query('SELECT "MashaFeedlyEntryID", "MemberID" FROM "MashaFeedlyEntry_AssignedMembers" ORDER BY "MashaFeedlyEntryID" ASC, "MemberID" ASC') as $row) {
            $assignedByEntry[(int)$row['MashaFeedlyEntryID']][] = (int)$row['MemberID'];
        }
        return $assignedByEntry;
    }

    /** Liefert die stabil sortierten IDs aller zugeordneten Mitglieder für den Eintragshash. */
    private static function assignedMemberIDs(MashaFeedlyEntry $entry): array
    {
        $memberIDs = array_map('intval', $entry->AssignedMembers()->column('ID'));
        sort($memberIDs);
        return $memberIDs;
    }
}
