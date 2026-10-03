<?php

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use SilverStripe\Assets\Folder;
use SilverStripe\Security\InheritedPermissions;

/** Stellt die geschützte Ordnerstruktur für Masha:Feedly-Dateien her. */
class MashaFeedlyFolderService
{
    private const PARENT_FOLDER = 'masha-feedly';

    private const CHILD_FOLDERS = [
        'masha-feedly-attachments',
        'masha-feedly-profile-images',
    ];

    /** Erstellt den privaten Oberordner, verschiebt Altordner hinein und synchronisiert Zugriffe. */
    public static function ensureStructure(): array
    {
        $allowedMemberIDs = MashaFeedlyConfigExtension::memberIDs();
        $parent = Folder::find_or_make(self::PARENT_FOLDER);
        self::restrictFolder($parent, $allowedMemberIDs);

        $folders = [];
        foreach (self::CHILD_FOLDERS as $folderName) {
            $folder = Folder::get()->filter([
                'ParentID' => (int)$parent->ID,
                'Name' => $folderName,
            ])->first();
            if (!$folder) {
                $folder = Folder::get()->filter([
                    'ParentID' => 0,
                    'Name' => $folderName,
                ])->first();
            }
            if (!$folder) {
                $folder = Folder::find_or_make(self::PARENT_FOLDER . '/' . $folderName);
            }

            if ((int)$folder->ParentID !== (int)$parent->ID) {
                $folder->ParentID = (int)$parent->ID;
            }
            self::restrictFolder($folder, $allowedMemberIDs);
            $folders[$folderName] = $folder;
        }

        return ['parent' => $parent, 'children' => $folders];
    }

    /** Beschränkt das Anzeigen jedes Ordners auf die aktuell freigegebenen Mitglieder. */
    private static function restrictFolder(Folder $folder, array $memberIDs): void
    {
        $folder->CanViewType = InheritedPermissions::ONLY_THESE_MEMBERS;
        $folder->ViewerMembers()->setByIDList($memberIDs);
        $folder->write();
    }
}
