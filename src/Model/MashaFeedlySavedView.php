<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/** Persönliche Filteransicht eines freigegebenen Masha-Feedly-Mitglieds. */
class MashaFeedlySavedView extends DataObject
{
    private static $table_name = 'MashaFeedlySavedView';

    private static $db = [
        'Title' => 'Varchar(60)',
        'Mode' => 'Varchar(20)',
        'CategoryID' => 'Int',
        'PriorityID' => 'Int',
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $summary_fields = [
        'Title' => 'Name',
        'Member.Title' => 'Mitglied',
    ];
}
