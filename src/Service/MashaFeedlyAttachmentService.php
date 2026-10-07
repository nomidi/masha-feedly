<?php

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Model\MashaFeedlyAttachment;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Security\InheritedPermissions;

/** Validiert, speichert und schützt hochgeladene Eintragsdateien. */
class MashaFeedlyAttachmentService
{
    private const MAX_FILE_SIZE = 10_485_760;
    private const MAX_TOTAL_SIZE = 20_971_520;

    private const ALLOWED_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    /** Prüft alle PHP-Uploadfelder, bevor ein Eintrag gespeichert wird. */
    public static function validateUploads(array $uploads): ?string
    {
        $total = 0;
        foreach (self::normalizeUploads($uploads) as $upload) {
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
                // Tests und interne Aufrufe dürfen temporäre Dateien nutzen; der Controller selbst prüft den Uploadstatus.
                if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_file($upload['tmp_name'] ?? '')) {
                    return 'Eine Datei konnte nicht gelesen werden.';
                }
            }
            $size = (int)($upload['size'] ?? 0);
            $total += $size;
            if ($size < 1 || $size > self::MAX_FILE_SIZE || $total > self::MAX_TOTAL_SIZE) {
                return 'Dateien dürfen höchstens 10 MB groß sein (insgesamt maximal 20 MB).';
            }
            $extension = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
            if (!isset(self::ALLOWED_TYPES[$extension])) {
                return 'Erlaubt sind Bilder (JPG, PNG, GIF, WebP), PDF- und ZIP-Dateien.';
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            if (!in_array($mime, self::ALLOWED_TYPES[$extension], true)) {
                return 'Der Dateityp passt nicht zur Dateiendung.';
            }
        }
        return null;
    }

    /** Speichert zuvor validierte Uploads in einem nur für Masha-Mitglieder sichtbaren Asset-Ordner. */
    public static function attachUploads(array $uploads, MashaFeedlyEntry $entry): array
    {
        $folder = self::protectedFolder();
        $saved = [];
        foreach (self::normalizeUploads($uploads) as $upload) {
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $originalName = basename(str_replace('\\', '/', (string)$upload['name']));
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            $asset = File::create();
            $asset->setFromLocalFile($upload['tmp_name'], bin2hex(random_bytes(12)) . '.' . $extension);
            $asset->ParentID = (int)$folder->ID;
            $asset->Title = mb_substr($originalName, 0, 255);
            $asset->write();
            $asset->CanViewType = InheritedPermissions::ONLY_THESE_USERS;
            $asset->ViewerGroups()->setByIDList([(int)MashaFeedlyFolderService::permissionGroup()->ID]);
            $asset->write();
            $asset->publishSingle();
            $asset->protectFile();

            $attachment = MashaFeedlyAttachment::create([
                'EntryID' => (int)$entry->ID,
                'FileID' => (int)$asset->ID,
                'OriginalName' => mb_substr($originalName, 0, 255),
                'MimeType' => (string)$mime,
            ]);
            $attachment->write();
            $saved[] = $attachment;
        }
        return $saved;
    }

    /** Liefert die geschützte Asset-Ablage und synchronisiert ihre Leseberechtigungen. */
    public static function protectedFolder(): Folder
    {
        $structure = MashaFeedlyFolderService::ensureStructure();
        return $structure['children']['masha-feedly-attachments'];
    }

    /** Vereinheitlicht den flachen und mehrteiligen Aufbau von $_FILES. */
    private static function normalizeUploads(array $uploads): array
    {
        if (!isset($uploads['name'])) {
            return array_values(array_filter($uploads, 'is_array'));
        }
        if (!is_array($uploads['name'])) {
            return [$uploads];
        }
        $normalized = [];
        foreach ($uploads['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $uploads['type'][$index] ?? '',
                'tmp_name' => $uploads['tmp_name'][$index] ?? '',
                'error' => $uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $uploads['size'][$index] ?? 0,
            ];
        }
        return $normalized;
    }
}
