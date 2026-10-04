<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/** Speichert eine Reaktion pro Person, Kommentar und Emoji. */
class MashaFeedlyCommentReaction extends DataObject
{
    private static $table_name = 'MashaFeedlyCommentReaction';

    private static $db = [
        'Emoji' => 'Varchar(16)',
    ];

    private static $has_one = [
        'Comment' => MashaFeedlyComment::class,
        'Member' => Member::class,
    ];

    private static $indexes = [
        'UniqueMemberComment' => [
            'type' => 'unique',
            'columns' => ['CommentID', 'MemberID'],
        ],
    ];

    /** @return list<string> */
    public static function supportedEmojis(): array
    {
        return ['👍', '❤️', '😂', '😢', '😮', '🙏'];
    }

    /** Liefert alle unterstützten Reaktionen samt Anzahl und eigener Auswahl. */
    public static function summaryForComment(MashaFeedlyComment $comment, Member $member): array
    {
        $summary = [];
        foreach (self::supportedEmojis() as $emoji) {
            $summary[$emoji] = ['emoji' => $emoji, 'count' => 0, 'selected' => false];
        }
        foreach (self::get()->filter('CommentID', (int)$comment->ID) as $reaction) {
            $emoji = (string)$reaction->Emoji;
            if (!isset($summary[$emoji])) {
                continue;
            }
            $summary[$emoji]['count']++;
            if ((int)$reaction->MemberID === (int)$member->ID) {
                $summary[$emoji]['selected'] = true;
            }
        }
        return array_values($summary);
    }
}
