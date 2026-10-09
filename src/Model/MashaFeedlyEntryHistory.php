<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;

/** Unveränderlicher Verlaufseintrag für Status- und Zuständigkeitsänderungen. */
class MashaFeedlyEntryHistory extends DataObject
{
    private static $table_name = 'MashaFeedlyEntryHistory';

    private static $db = [
        'ChangeType' => 'Varchar(32)',
        'OldValue' => 'Text',
        'NewValue' => 'Text',
        'ActorName' => 'Varchar(120)',
        'RelatedID' => 'Int',
    ];

    private static $has_one = [
        'Entry' => MashaFeedlyEntry::class,
        'ActorMember' => Member::class,
    ];

    /** Speichert eine protokollierte Änderung mit unveränderlichem Anzeigenamen der handelnden Person. */
    public static function record(
        MashaFeedlyEntry $entry,
        string $type,
        string $oldValue,
        string $newValue,
        ?Member $actor,
        int $relatedID = 0
    ): void
    {
        $history = self::create([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => $type,
            'OldValue' => $oldValue,
            'NewValue' => $newValue,
            'ActorMemberID' => (int)($actor?->ID ?? 0),
            'ActorName' => mb_substr($actor?->getName() ?: 'System', 0, 120),
            'RelatedID' => $relatedID,
        ]);
        $history->write();

        // Der Zähler erkennt Aktivitäten innerhalb derselben Sekunde und umgeht
        // beim Metadatenspeichern den OnAfterWrite-Lesestatus des Eintrags.
        $activityAt = (string)$history->Created;
        $entryID = (int)$entry->ID;
        $connection = DB::get_conn();
        $table = $connection->escapeIdentifier(DataObject::getSchema()->tableName(MashaFeedlyEntry::class));
        $idField = $connection->escapeIdentifier('ID');
        $versionField = $connection->escapeIdentifier('ActivityVersion');
        $activityField = $connection->escapeIdentifier('LastActivityAt');
        DB::prepared_query(
            "UPDATE {$table} SET {$versionField} = COALESCE({$versionField}, 0) + 1, "
                . "{$activityField} = CASE WHEN {$activityField} IS NULL OR {$activityField} < ? "
                . "THEN ? ELSE {$activityField} END WHERE {$idField} = ?",
            [$activityAt, $activityAt, $entryID]
        );

        // Nachfolgende Aufrufer können den aktuellen Hash bilden, ohne den Eintrag erneut zu schreiben.
        $updatedEntry = MashaFeedlyEntry::get()->byID($entryID);
        if ($updatedEntry) {
            $entry->ActivityVersion = (int)$updatedEntry->ActivityVersion;
            $entry->LastActivityAt = (string)$updatedEntry->LastActivityAt;
        }
    }

    /** Formatiert den Verlauf einheitlich für die Widget-Antworten. */
    public static function dataForEntry(MashaFeedlyEntry $entry): array
    {
        return array_map(static fn(self $item): array => [
            'type' => (string)$item->ChangeType,
            'oldValue' => (string)$item->OldValue,
            'newValue' => (string)$item->NewValue,
            'actor' => (string)$item->ActorName,
            'created' => (new \DateTimeImmutable((string)$item->Created, new \DateTimeZone('UTC')))
                ->format('Y-m-d\\TH:i:s\\Z'),
        ], $entry->History()->sort('Created DESC, ID DESC')->toArray());
    }

    /** Ergänzt beim Datenbankaufbau Verlaufspunkte für bereits vorhandene Einträge und Kommentare. */
    public function requireDefaultRecords(): void
    {
        foreach (MashaFeedlyEntry::get() as $entry) {
            if (!self::get()->filter(['EntryID' => (int)$entry->ID, 'ChangeType' => 'created'])->exists()) {
                self::create([
                    'EntryID' => (int)$entry->ID,
                    'ChangeType' => 'created',
                    'NewValue' => $entry->getTitle(),
                    'ActorName' => 'Vor Verlaufsbeginn',
                    'Created' => (string)$entry->Created,
                ])->write();
            }
            foreach ($entry->Comments()->filter('IsApproved', true) as $comment) {
                if (self::get()->filter([
                    'EntryID' => (int)$entry->ID,
                    'ChangeType' => 'comment',
                    'RelatedID' => (int)$comment->ID,
                ])->exists()) {
                    continue;
                }
                self::create([
                    'EntryID' => (int)$entry->ID,
                    'ChangeType' => 'comment',
                    'NewValue' => (string)$comment->CommentText,
                    'ActorMemberID' => (int)$comment->AuthorMemberID,
                    'ActorName' => (string)$comment->AuthorName,
                    'RelatedID' => (int)$comment->ID,
                    'Created' => (string)$comment->Created,
                ])->write();
            }
            foreach ($entry->Attachments() as $attachment) {
                if (self::get()->filter([
                    'EntryID' => (int)$entry->ID,
                    'ChangeType' => 'attachment',
                    'RelatedID' => (int)$attachment->ID,
                ])->exists()) {
                    continue;
                }
                self::create([
                    'EntryID' => (int)$entry->ID,
                    'ChangeType' => 'attachment',
                    'NewValue' => (string)$attachment->OriginalName,
                    'ActorName' => 'Vor Verlaufsbeginn',
                    'RelatedID' => (int)$attachment->ID,
                    'Created' => (string)$attachment->Created,
                ])->write();
            }

            $latestHistory = $entry->History()->sort('Created DESC, ID DESC')->first();
            if ($latestHistory && (!$entry->LastActivityAt
                || strtotime((string)$latestHistory->Created) > strtotime((string)$entry->LastActivityAt))) {
                $entry->LastActivityAt = (string)$latestHistory->Created;
                $entry->write();
            }
        }
    }
}
