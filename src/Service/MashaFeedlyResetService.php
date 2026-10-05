<?php

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyAttachment;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyCommentReaction;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use KW\MashaFeedly\Model\MashaFeedlyEntryRelation;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DB;
use Throwable;

/** Löscht Masha:Feedly-Inhalte und setzt die Kategorien auf den Startzustand zurück. */
final class MashaFeedlyResetService
{
    /**
     * Löscht Einträge und alle daran hängenden Daten; Einstellungen, Mitglieder und Profile bleiben erhalten.
     *
     * @return array{entries:int,comments:int,attachments:int,categories:int}
     */
    public static function resetAllData(): array
    {
        $connection = DB::get_conn();
        $connection->transactionStart();
        $fileIDs = [];
        $attachmentFolderID = 0;
        try {
            $counts = [
                'entries' => MashaFeedlyEntry::get()->count(),
                'comments' => MashaFeedlyComment::get()->count(),
                'attachments' => MashaFeedlyAttachment::get()->count(),
                'categories' => MashaFeedlyCategory::get()->count(),
            ];

            $attachmentFolder = MashaFeedlyAttachmentService::protectedFolder();
            $attachmentFolderID = (int)$attachmentFolder->ID;
            $fileIDs = array_values(array_unique(array_map(
                'intval',
                MashaFeedlyAttachment::get()->column('FileID')
            )));

            foreach (MashaFeedlyEntryRelation::get() as $relation) {
                $relation->delete();
            }
            foreach (MashaFeedlyEntryRead::get() as $read) {
                $read->delete();
            }
            foreach (MashaFeedlyCommentReaction::get() as $reaction) {
                $reaction->delete();
            }
            foreach (MashaFeedlyEntryHistory::get() as $history) {
                $history->delete();
            }
            foreach (MashaFeedlyAttachment::get() as $attachment) {
                $attachment->delete();
            }
            foreach (MashaFeedlyComment::get() as $comment) {
                $comment->delete();
            }
            foreach (MashaFeedlyEntry::get() as $entry) {
                $entry->AssignedMembers()->removeAll();
                $entry->delete();
            }
            foreach (MashaFeedlyCategory::get() as $category) {
                $category->delete();
            }

            // Keep the existing SiteConfig, but reseed optional estimate categories on this reset.
            $config = MashaFeedlyConfigExtension::currentSiteConfig();
            $config->MashaFeedlyEstimateCategoriesSeeded = false;
            $config->MashaFeedlyMiteEnabled = false;
            $config->write();
            MashaFeedlyCategory::ensureDefaultCategories();

            $connection->transactionEnd();
        } catch (Throwable $exception) {
            $connection->transactionRollback();
            throw $exception;
        }

        // Delete files only after the database reset commits, so a rollback cannot leave
        // attachment records pointing at files that have already been physically deleted.
        foreach ($fileIDs as $fileID) {
            $file = File::get()->byID($fileID);
            if ($file && (int)$file->ParentID === $attachmentFolderID) {
                $file->delete();
            }
        }

        return $counts;
    }
}
