<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;

/** Geschützter Dateianhang eines Masha:Feedly-Eintrags. */
class MashaFeedlyAttachment extends DataObject
{
    private static $table_name = 'MashaFeedlyAttachment';

    private static $db = [
        'OriginalName' => 'Varchar(255)',
        'MimeType' => 'Varchar(100)',
    ];

    private static $has_one = [
        'Entry' => MashaFeedlyEntry::class,
        'File' => File::class,
    ];
}
