<?php

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use SilverStripe\Assets\Folder;
use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\Group;

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
        $permissionGroup = self::permissionGroup();
        $parent = Folder::find_or_make(self::PARENT_FOLDER);
        self::restrictFolder($parent, $permissionGroup);

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
            self::restrictFolder($folder, $permissionGroup);
            $folders[$folderName] = $folder;
        }

        return ['parent' => $parent, 'children' => $folders];
    }

    /** Beschränkt das Anzeigen jedes Ordners auf die aktuell freigegebenen Mitglieder. */
    /** Stellt eine Silverstripe-4-Gruppe für die Dateiberechtigungen synchron zur SiteConfig bereit. */
    public static function permissionGroup(): Group
    {
        $group = Group::get()->filter('Code', 'MashaFeedlyUsers')->first();
        if (!$group) {
            $group = Group::create([
                'Title' => 'Masha Feedly Benutzer',
                'Code' => 'MashaFeedlyUsers',
            ]);
            $group->write();
        }
        $group->Members()->setByIDList(MashaFeedlyConfigExtension::memberIDs());
        return $group;
    }

    /** Beschränkt Ordner auf die dedizierte Masha-Feedly-Gruppe. */
    private static function restrictFolder(Folder $folder, Group $permissionGroup): void
    {
        $folder->CanViewType = InheritedPermissions::ONLY_THESE_USERS;
        $folder->ViewerGroups()->setByIDList([(int)$permissionGroup->ID]);
        $folder->write();
    }
}
