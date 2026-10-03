<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\ORM\DataObject;
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
    public static function unreadActivityCounts(Member $member): array
    {
        $unreadIDs = self::unreadEntryIDs($member);
        $commentCount = 0;
        foreach ($unreadIDs as $entryID) {
            $entry = MashaFeedlyEntry::get()->byID($entryID);
            if (!$entry) {
                continue;
            }
            $read = self::get()->filter([
                'MemberID' => (int)$member->ID,
                'EntryID' => $entryID,
            ])->first();
            // LastEdited wird beim Markieren als gelesen aktualisiert. Für erstmals ungelesene
            // Einträge zählt nur Aktivität nach ihrer Anlage, nicht die Erstellung selbst.
            $cutoff = $read ? (string)$read->LastEdited : (string)$entry->Created;
            $commentCount += MashaFeedlyEntryHistory::get()->filter([
                'EntryID' => $entryID,
                'ChangeType' => ['comment', 'comment_edited'],
                'Created:GreaterThan' => $cutoff,
            ])->count();
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

    /** Liefert die IDs aller Einträge, deren aktueller Stand für ein Mitglied ungelesen ist. */
    public static function unreadEntryIDs(Member $member): array
    {
        $seenHashes = [];
        foreach (self::get()->filter('MemberID', (int)$member->ID) as $read) {
            $seenHashes[(int)$read->EntryID] = (string)$read->StateHash;
        }

        $unreadIDs = [];
        foreach (MashaFeedlyEntry::get() as $entry) {
            $currentHash = self::stateHash($entry);
            if (($seenHashes[(int)$entry->ID] ?? null) === $currentHash) {
                continue;
            }
            $assignedMemberIDs = self::assignedMemberIDs($entry);
            $isRecipient = !$assignedMemberIDs
                || in_array((int)$member->ID, $assignedMemberIDs, true)
                || (int)$member->ID === $entry->creatorMemberID();
            if ($isRecipient) {
                $unreadIDs[] = (int)$entry->ID;
            }
        }
        return $unreadIDs;
    }

    /** Bildet den relevanten Stand aus Inhalt, Datum, Status, Zuständigkeit und Kommentarverlauf. */
    private static function stateHash(MashaFeedlyEntry $entry): string
    {
        return hash('sha256', json_encode([
            'Content' => (string)$entry->Content,
            'EntryDate' => (string)$entry->EntryDate,
            'CategoryID' => (int)$entry->CategoryID,
            'PriorityID' => (int)$entry->PriorityID,
            'AssignedMemberIDs' => self::assignedMemberIDs($entry),
            'CommentHistoryID' => (int)MashaFeedlyEntryHistory::get()->filter([
                'EntryID' => (int)$entry->ID,
                'ChangeType' => ['comment', 'comment_edited', 'comment_deleted'],
            ])->max('ID'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Liefert die stabil sortierten IDs aller zugeordneten Mitglieder für den Eintragshash. */
    private static function assignedMemberIDs(MashaFeedlyEntry $entry): array
    {
        $memberIDs = array_map('intval', $entry->AssignedMembers()->column('ID'));
        sort($memberIDs);
        return $memberIDs;
    }
}
