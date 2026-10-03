<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\ORM\DataObject;

/** Gerichtete Verknüpfung zwischen zwei Masha-Feedly-Einträgen. */
class MashaFeedlyEntryRelation extends DataObject
{
    private static $table_name = 'MashaFeedlyEntryRelation';

    private static $db = [
        'LinkType' => 'Varchar(24)',
    ];

    private static $has_one = [
        'Entry' => MashaFeedlyEntry::class,
        'RelatedEntry' => MashaFeedlyEntry::class,
    ];

    private static $indexes = [
        'EntryRelatedUnique' => [
            'type' => 'unique',
            'columns' => ['EntryID', 'RelatedEntryID'],
        ],
    ];
}
