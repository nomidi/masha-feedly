<?php

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Admin\MashaFeedlyAdmin;
use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\i18n\i18n;

/**
 * Versendet E-Mail-Benachrichtigungen zu Masha-Feedly-Einträgen.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyNotificationService
{
    /** Sendet einmalig eine Erinnerung an freigegebene Verantwortliche und die erstellende Person. */
    public static function notifyDueDateReminder(MashaFeedlyEntry $entry): int
    {
        if (!$entry->DueDate || $entry->DueDateReminderSentAt) {
            return 0;
        }
        $allowedIDs = MashaFeedlyConfigExtension::memberIDs();
        $assignedIDs = array_map('intval', $entry->AssignedMembers()->column('ID'));
        $creatorID = $entry->creatorMemberID();
        $recipientIDs = array_values(array_unique(array_intersect(
            array_filter(array_merge($assignedIDs, [$creatorID]), static fn(int $id): bool => $id > 0),
            array_map('intval', $allowedIDs)
        )));
        if (!$recipientIDs) {
            return 0;
        }

        $entryURL = Director::absoluteURL(MashaFeedlyAdmin::singleton()->getCMSEditLinkForManagedDataObject($entry));
        $siteTitle = trim((string)SiteConfig::current_site_config()->Title) ?: 'Masha:Feedly';
        $sent = 0;
        foreach (Member::get()->filter('ID', $recipientIDs) as $member) {
            if (
                !(bool)$member->MashaFeedlyEmailNotifications
                || !(bool)$member->MashaFeedlyNotifyDueDateReminders
                || !Email::is_valid_address((string)$member->Email)
            ) {
                continue;
            }

            Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_DUE_DATE_SUBJECT',
                    '{siteTitle}: Heute fällig – {title}',
                    ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/DueDateReminderEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/DueDateReminderEmailPlain')
                ->setData([
                    'SiteTitle' => $siteTitle,
                    'BugTitle' => $entry->getTitle(),
                    'DueDate' => (string)$entry->DueDate,
                    'EntryURL' => $entryURL,
                ])
                ->send();
            $sent++;
        }

        if ($sent > 0) {
            $entry->DueDateReminderSentAt = \SilverStripe\ORM\FieldType\DBDatetime::now();
            $entry->write();
        }
        return $sent;
    }

    /** Begrüßt ein neu freigeschaltetes Mitglied per E-Mail. */
    public static function notifyAccessGranted(Member $member): void
    {
        if (!(bool)$member->MashaFeedlyEmailNotifications || !Email::is_valid_address((string)$member->Email)) {
            return;
        }
        $siteTitle = trim((string)SiteConfig::current_site_config()->Title) ?: 'Masha:Feedly';
        $widgetURL = Director::absoluteURL(Director::baseURL());
        Email::create()
            ->setTo((string)$member->Email)
            ->setSubject(i18n::_t(
                'KW\\MashaFeedly\\Translations.EMAIL_ACCESS_SUBJECT',
                'Willkommen bei Masha:Feedly auf {siteTitle}',
                ['siteTitle' => $siteTitle]
            ))
            ->setHTMLTemplate('KW/MashaFeedly/Email/AccessGrantedEmail')
            ->setPlainTemplate('KW/MashaFeedly/Email/AccessGrantedEmailPlain')
            ->setData(['SiteTitle' => $siteTitle, 'WidgetURL' => $widgetURL])
            ->send();
    }

    /** Sendet den neuen Bug-Eintrag an ausdrücklich freigegebene Abonnenten. */
    public static function notifyNewEntry(MashaFeedlyEntry $entry): void
    {
        $memberIDs = MashaFeedlyConfigExtension::memberIDs();
        if (!$memberIDs) {
            return;
        }

        $entryURL = Director::absoluteURL(
            MashaFeedlyAdmin::singleton()->getCMSEditLinkForManagedDataObject($entry)
        );
        $bugDescription = trim(html_entity_decode(strip_tags((string)$entry->Content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $siteTitle = trim((string)SiteConfig::current_site_config()->Title);
        $currentMemberID = (int)(Security::getCurrentUser()?->ID ?? 0);
        if ($siteTitle === '') {
            $siteTitle = 'Masha:Feedly';
        }

        foreach (Member::get()->filter('ID', $memberIDs) as $member) {
            if (
                !(bool)$member->MashaFeedlyEmailNotifications
                || !(bool)$member->MashaFeedlyNotifyNewEntries
                || ((int)$member->ID === $currentMemberID && !(bool)$member->MashaFeedlyNotifyOwnEntryChanges)
                || !Email::is_valid_address((string)$member->Email)
            ) {
                continue;
            }

            Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_NEW_SUBJECT',
                    '{siteTitle}: Neuer Masha-Feedly-Bug: {title}',
                    ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/NewEntryEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/NewEntryEmailPlain')
                ->setData([
                    'SiteTitle' => $siteTitle,
                    'BugTitle' => $entry->getTitle(),
                    'BugDescription' => $bugDescription,
                    'EntryURL' => $entryURL,
                ])
                ->send();
        }
    }

    /** Sendet eine Änderungsbenachrichtigung an Mitglieder mit passender Einstellung. */
    public static function notifyUpdatedEntry(MashaFeedlyEntry $entry): void
    {
        $memberIDs = MashaFeedlyConfigExtension::memberIDs();
        if (!$memberIDs) {
            return;
        }

        $entryURL = Director::absoluteURL(
            MashaFeedlyAdmin::singleton()->getCMSEditLinkForManagedDataObject($entry)
        );
        $siteTitle = trim((string)SiteConfig::current_site_config()->Title);
        $currentMemberID = (int)(Security::getCurrentUser()?->ID ?? 0);
        if ($siteTitle === '') {
            $siteTitle = 'Masha:Feedly';
        }

        foreach (Member::get()->filter('ID', $memberIDs) as $member) {
            if (
                !(bool)$member->MashaFeedlyEmailNotifications
                || !(bool)$member->MashaFeedlyNotifyEntryUpdates
                || ((int)$member->ID === $currentMemberID && !(bool)$member->MashaFeedlyNotifyOwnEntryChanges)
                || !Email::is_valid_address((string)$member->Email)
            ) {
                continue;
            }

            Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_UPDATED_SUBJECT',
                    '{siteTitle}: Masha-Feedly-Eintrag geändert: {title}',
                    ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/UpdatedEntryEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/UpdatedEntryEmailPlain')
                ->setData([
                    'SiteTitle' => $siteTitle,
                    'BugTitle' => $entry->getTitle(),
                    'EntryURL' => $entryURL,
                    'CategoryTitle' => (string)$entry->Category()->Title,
                ])
                ->send();
        }
    }

    /** Sendet neue Kommentare an freigegebene, zugewiesene Mitglieder mit aktivierter Kommentarpräferenz. */
    public static function notifyNewComment(MashaFeedlyEntry $entry, MashaFeedlyComment $comment, Member $author): void
    {
        $allowedIDs = MashaFeedlyConfigExtension::memberIDs();
        $assignedIDs = array_values(array_intersect(
            array_map('intval', $entry->AssignedMembers()->column('ID')),
            array_map('intval', $allowedIDs)
        ));
        $creatorID = $entry->creatorMemberID();
        $recipientIDs = array_values(array_diff(
            array_intersect(array_values(array_unique(array_merge($assignedIDs, [$creatorID]))), $allowedIDs),
            [(int)$author->ID]
        ));
        if (!$recipientIDs) {
            return;
        }

        $siteTitle = trim((string)SiteConfig::current_site_config()->Title) ?: 'Masha:Feedly';
        $entryURL = Director::absoluteURL(
            MashaFeedlyAdmin::singleton()->getCMSEditLinkForManagedDataObject($entry)
        );
        foreach (Member::get()->filter('ID', $recipientIDs) as $member) {
            if (
                !(bool)$member->MashaFeedlyEmailNotifications
                || !(bool)$member->MashaFeedlyNotifyComments
                || !Email::is_valid_address((string)$member->Email)
            ) {
                continue;
            }

            Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_COMMENT_SUBJECT',
                    '{siteTitle}: Neuer Kommentar zu {title}',
                    ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/NewCommentEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/NewCommentEmailPlain')
                ->setData([
                    'SiteTitle' => $siteTitle,
                    'BugTitle' => $entry->getTitle(),
                    'CommentAuthor' => (string)$comment->AuthorName,
                    'CommentText' => (string)$comment->CommentText,
                    'EntryURL' => $entryURL,
                ])
                ->send();
        }
    }
}
