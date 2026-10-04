<?php

namespace KW\MashaFeedly\Control;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyCommentReaction;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\i18n\i18n;

/** Eigener JSON-Endpunkt zum Kommentieren von Masha-Feedly-Einträgen. */
class MashaFeedlyCommentController extends Controller
{
    /** Verarbeitet Kommentar-POSTs über die Standardaktion des eigenen Endpunkts. */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate('SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate('SESSION_EXPIRED_UPDATE_SIE', 'Deine Sitzung ist abgelaufen.')], 400);
        }

        $entry = MashaFeedlyEntry::get()->byID((int)$request->postVar('EntryID'));
        if (!$entry) {
            return $this->respond(['success' => false, 'message' => $this->translate('ENTRY_NOT_FOUND', 'Der Eintrag wurde nicht gefunden.')], 404);
        }
        $action = strtolower(trim((string)$request->postVar('CommentAction')));
        if (in_array($action, ['edit', 'delete'], true)) {
            return $this->manageComment($request, $member, $action);
        }
        if ($action === 'react') {
            return $this->toggleReaction($request, $entry, $member);
        }
        $text = trim((string)$request->postVar('CommentText'));
        if ($text === '' || mb_strlen($text) > 5000) {
            return $this->respond(['success' => false, 'message' => $this->translate('COMMENT_INVALID', 'Bitte gib einen Kommentar mit höchstens 5000 Zeichen ein.')], 400);
        }

        $comment = MashaFeedlyComment::create([
            'AuthorName' => mb_substr((string)$member->getName(), 0, 120),
            'CommentText' => $text,
            'IsApproved' => true,
            'EntryID' => (int)$entry->ID,
            'AuthorMemberID' => (int)$member->ID,
        ]);
        $comment->write();
        MashaFeedlyNotificationService::notifyNewComment($entry, $comment, $member);
        MashaFeedlyEntryHistory::record($entry, 'comment', '', (string)$comment->CommentText, $member, (int)$comment->ID);
        MashaFeedlyEntryRead::markAsSeen($entry, $member);

        return $this->respond([
            'success' => true,
            'comment' => [
                'id' => (int)$comment->ID,
                'author' => (string)$comment->AuthorName,
                'text' => (string)$comment->CommentText,
                'created' => (string)$comment->Created,
                'edited' => false,
                'canManage' => true,
                'reactions' => MashaFeedlyCommentReaction::summaryForComment($comment, $member),
            ],
            'history' => MashaFeedlyEntryHistory::dataForEntry($entry),
        ]);
    }

    /** Schaltet eine erlaubte Reaktion auf einem freigegebenen Kommentar um. */
    private function toggleReaction(HTTPRequest $request, MashaFeedlyEntry $entry, Member $member): HTTPResponse
    {
        $comment = MashaFeedlyComment::get()->byID((int)$request->postVar('CommentID'));
        if (!$comment || (int)$comment->EntryID !== (int)$entry->ID || !(bool)$comment->IsApproved) {
            return $this->respond(['success' => false, 'message' => $this->translate('COMMENT_NOT_FOUND', 'Der Kommentar wurde nicht gefunden.')], 404);
        }
        $emoji = trim((string)$request->postVar('ReactionEmoji'));
        if (!in_array($emoji, MashaFeedlyCommentReaction::supportedEmojis(), true)) {
            return $this->respond(['success' => false, 'message' => $this->translate('COMMENT_REACTION_INVALID', 'Diese Reaktion ist nicht verfügbar.')], 400);
        }

        $existing = MashaFeedlyCommentReaction::get()->filter([
            'CommentID' => (int)$comment->ID,
            'MemberID' => (int)$member->ID,
        ]);
        $current = $existing->first();
        if ($current && (string)$current->Emoji === $emoji) {
            $current->delete();
        } else {
            foreach ($existing as $oldReaction) {
                $oldReaction->delete();
            }
            MashaFeedlyCommentReaction::create([
                'CommentID' => (int)$comment->ID,
                'MemberID' => (int)$member->ID,
                'Emoji' => $emoji,
            ])->write();
        }

        return $this->respond([
            'success' => true,
            'commentID' => (int)$comment->ID,
            'reactions' => MashaFeedlyCommentReaction::summaryForComment($comment, $member),
        ]);
    }

    private function manageComment(HTTPRequest $request, Member $member, string $action): HTTPResponse
    {
        $comment = MashaFeedlyComment::get()->byID((int)$request->postVar('CommentID'));
        if (!$comment) {
            return $this->respond(['success' => false, 'message' => $this->translate('COMMENT_NOT_FOUND', 'Der Kommentar wurde nicht gefunden.')], 404);
        }
        $isAdmin = Permission::checkMember($member, 'ADMIN');
        if (!$isAdmin && (int)$comment->AuthorMemberID !== (int)$member->ID) {
            return $this->respond(['success' => false, 'message' => $this->translate('COMMENT_FORBIDDEN', 'Du darfst diesen Kommentar nicht ändern.')], 403);
        }
        if ($action === 'delete') {
            $commentID = (int)$comment->ID;
            $entry = MashaFeedlyEntry::get()->byID((int)$comment->EntryID);
            if ($entry) {
                MashaFeedlyEntryHistory::record($entry, 'comment_deleted', (string)$comment->CommentText, '', $member, $commentID);
                MashaFeedlyEntryRead::markAsSeen($entry, $member);
            }
            foreach ($comment->Reactions() as $reaction) {
                $reaction->delete();
            }
            $comment->delete();
            return $this->respond([
                'success' => true,
                'commentID' => $commentID,
                'history' => $entry ? MashaFeedlyEntryHistory::dataForEntry($entry) : [],
            ]);
        }
        $text = trim((string)$request->postVar('CommentText'));
        if ($text === '' || mb_strlen($text) > 5000) {
            return $this->respond(['success' => false, 'message' => $this->translate('COMMENT_INVALID', 'Bitte gib einen Kommentar mit höchstens 5000 Zeichen ein.')], 400);
        }
        $oldText = (string)$comment->CommentText;
        $comment->CommentText = $text;
        $comment->WasEdited = true;
        $comment->write();
        $entry = MashaFeedlyEntry::get()->byID((int)$comment->EntryID);
        if ($entry) {
            MashaFeedlyEntryHistory::record($entry, 'comment_edited', $oldText, $text, $member, (int)$comment->ID);
            MashaFeedlyEntryRead::markAsSeen($entry, $member);
        }
        return $this->respond(['success' => true, 'comment' => [
            'id' => (int)$comment->ID,
            'author' => (string)$comment->AuthorName,
            'text' => (string)$comment->CommentText,
            'created' => (string)$comment->Created,
            'edited' => true,
            'canManage' => true,
            'reactions' => MashaFeedlyCommentReaction::summaryForComment($comment, $member),
        ], 'history' => $entry ? MashaFeedlyEntryHistory::dataForEntry($entry) : []]);
    }

    private function translate(string $key, string $fallback): string
    {
        return i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $fallback);
    }

    private function respond(array $data, int $status = 200): HTTPResponse
    {
        return $this->getResponse()->setStatusCode($status)
            ->addHeader('Content-Type', 'application/json; charset=utf-8')
            ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
