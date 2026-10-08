<?php

namespace KW\MashaFeedly\Service;

use KW\MashaFeedly\Admin\MashaFeedlyAdmin;
use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Extension\MashaFeedlyMemberExtension;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Admin\CMSProfileController;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\i18n\i18n;
use Throwable;

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
    /** Ergänzt Maildaten um den zentralen Footer und den direkten Profil-Link. */
    private static function emailData(array $data, ?Member $member = null): array
    {
        $profileURL = Director::absoluteURL(CMSProfileController::singleton()->Link() . '#Root_MashaFeedly');
        $isFormal = $member
            ? MashaFeedlyMemberExtension::addressFor($member) === 'sie'
            : MashaFeedlyConfigExtension::address() === 'sie';
        return array_merge($data, [
            // Silverstripe 4 benötigt benannte Datenobjekte statt einer Liste roher Textwerte.
            'EmailFooterLines' => ArrayList::create(array_map(
                static fn(string $line): ArrayData => ArrayData::create(['Text' => $line]),
                preg_split('/\R/u', MashaFeedlyConfigExtension::emailFooter()) ?: []
            )),
            'EmailProfileURL' => $profileURL,
            'EmailImprintURL' => 'https://www.kooperative-web.de/impressum',
            'EmailFooterOptOutText' => i18n::_t(
                $isFormal ? 'KW\\MashaFeedly\\Translations.EMAIL_FOOTER_OPT_OUT_SIE' : 'KW\\MashaFeedly\\Translations.EMAIL_FOOTER_OPT_OUT_DU',
                $isFormal
                    ? 'Möchten Sie keine Statusmeldungen per E-Mail mehr erhalten? Passen Sie Ihre Auswahl in Ihrem Profil an.'
                    : 'Möchtest du keine Statusmeldungen per E-Mail mehr erhalten? Passe deine Auswahl in deinem Profil an.'
            ),
        ]);
    }

    /** Versendet eine Benachrichtigung, ohne einen fachlichen Schreibvorgang durch Mailfehler abzubrechen. */
    private static function sendSafely(Email $email): bool
    {
        try {
            $email->send();
            return true;
        } catch (Throwable $exception) {
            error_log('[Masha:Feedly] E-Mail-Versand fehlgeschlagen (' . get_class($exception) . '): ' . $exception->getMessage());
            return false;
        }
    }

    /** Sendet eine allgemeine Testnachricht an ein Mitglied und lässt Versandfehler für die CMS-Rückmeldung hochlaufen. */
    public static function sendTestEmail(Member $member): void
    {
        if (!Email::is_valid_address((string)$member->Email)) {
            throw new \RuntimeException('Für dein Benutzerkonto ist keine gültige E-Mail-Adresse hinterlegt.');
        }
        $siteTitle = trim((string)SiteConfig::current_site_config()->Title) ?: 'Masha:Feedly';
        Email::create()
            ->setTo((string)$member->Email)
            ->setSubject('Masha:Feedly – Test-E-Mail')
            ->setHTMLTemplate('KW/MashaFeedly/Email/TestEmail')
            ->setPlainTemplate('KW/MashaFeedly/Email/TestEmailPlain')
            ->setData(self::emailData(['SiteTitle' => $siteTitle], $member))
            ->send();
    }

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

            if (self::sendSafely(Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_DUE_DATE_SUBJECT',
                    '{siteTitle}: Heute fällig – {title}',
                    ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/DueDateReminderEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/DueDateReminderEmailPlain')
                ->setData(self::emailData([
                    'SiteTitle' => $siteTitle,
                    'BugTitle' => $entry->getTitle(),
                    'DueDate' => (string)$entry->DueDate,
                    'EntryURL' => $entryURL,
                ], $member))
            )) {
                $sent++;
            }
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
        self::sendSafely(Email::create()
            ->setTo((string)$member->Email)
            ->setSubject(i18n::_t(
                'KW\\MashaFeedly\\Translations.EMAIL_ACCESS_SUBJECT',
                'Willkommen bei Masha:Feedly auf {siteTitle}',
                ['siteTitle' => $siteTitle]
            ))
            ->setHTMLTemplate('KW/MashaFeedly/Email/AccessGrantedEmail')
            ->setPlainTemplate('KW/MashaFeedly/Email/AccessGrantedEmailPlain')
            ->setData(self::emailData(['SiteTitle' => $siteTitle, 'WidgetURL' => $widgetURL], $member))
        );
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
        $descriptionHTML = (string)$entry->Content;
        $descriptionText = preg_replace('/<\\s*br\\s*\\/?\\s*>|<\\/\\s*(?:p|div|li|h[1-6])\\s*>/i', "\n", $descriptionHTML) ?? $descriptionHTML;
        $bugDescription = trim(html_entity_decode(strip_tags($descriptionText), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $siteTitle = trim((string)SiteConfig::current_site_config()->Title);
        $assignedMembers = $entry->AssignedMembers()->sort('Surname ASC, FirstName ASC');
        $assignedNames = [];
        foreach ($assignedMembers as $assignedMember) {
            $assignedNames[] = (string)$assignedMember->getName();
        }
        $dueDate = trim((string)$entry->DueDate);
        $reporterName = trim($entry->reportedByName());
        $currentMemberID = (int)((Security::getCurrentUser() ? Security::getCurrentUser()->ID : null) ?? 0);
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

            self::sendSafely(Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_NEW_SUBJECT',
                    'Neue Meldung auf {siteTitle}',
                    ['siteTitle' => $siteTitle]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/NewEntryEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/NewEntryEmailPlain')
                ->setData(self::emailData([
                    'SiteTitle' => $siteTitle,
                    'BugDescription' => $bugDescription,
                    'ReporterName' => $reporterName,
                    'CategoryTitle' => trim((string)$entry->Category()->Title),
                    'PriorityTitle' => trim((string)$entry->Priority()->Title),
                    'DueDate' => $dueDate !== '' ? (string)$entry->dbObject('DueDate')->Nice() : '',
                    'AssignedNames' => implode(', ', $assignedNames),
                    'EntryURL' => $entryURL,
                ], $member))
            );
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
        $currentMember = Security::getCurrentUser();
        $currentMemberID = $currentMember ? (int)$currentMember->ID : 0;
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

            self::sendSafely(Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_UPDATED_SUBJECT',
                    '{siteTitle}: Masha-Feedly-Meldung geändert: {title}',
                    ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/UpdatedEntryEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/UpdatedEntryEmailPlain')
                ->setData(self::emailData([
                    'SiteTitle' => $siteTitle,
                    'BugTitle' => $entry->getTitle(),
                    'EntryURL' => $entryURL,
                    'CategoryTitle' => (string)$entry->Category()->Title,
                ], $member))
            );
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

            self::sendSafely(Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_COMMENT_SUBJECT',
                    '{siteTitle}: Neuer Kommentar zu {title}',
                    ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/NewCommentEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/NewCommentEmailPlain')
                ->setData(self::emailData([
                    'SiteTitle' => $siteTitle,
                    'BugTitle' => $entry->getTitle(),
                    'CommentAuthor' => (string)$comment->AuthorName,
                    'CommentText' => (string)$comment->CommentText,
                    'EntryURL' => $entryURL,
                ], $member))
            );
        }
    }

    /** Benachrichtigt zugewiesene, freigegebene Mitglieder einmal beim Wechsel zur Kostenschätzung. */
    public static function notifyCostEstimateRequested(MashaFeedlyEntry $entry): int
    {
        $allowedIDs = array_map('intval', MashaFeedlyConfigExtension::memberIDs());
        $assignedIDs = array_map('intval', $entry->AssignedMembers()->column('ID'));
        $recipientIDs = array_values(array_intersect($assignedIDs, $allowedIDs));
        if (!$recipientIDs) {
            return 0;
        }

        $siteTitle = trim((string)SiteConfig::current_site_config()->Title) ?: 'Masha:Feedly';
        $entryURL = Director::absoluteURL(
            Director::baseURL() . '?masha-feedly-entry=' . (int)$entry->ID
        );
        $sent = 0;
        foreach (Member::get()->filter('ID', $recipientIDs) as $member) {
            if (
                !(bool)$member->MashaFeedlyEmailNotifications
                || !(bool)$member->MashaFeedlyNotifyCostEstimates
                || !Email::is_valid_address((string)$member->Email)
            ) {
                continue;
            }

            if (self::sendSafely(Email::create()
                ->setTo((string)$member->Email)
                ->setSubject(i18n::_t(
                    'KW\\MashaFeedly\\Translations.EMAIL_ESTIMATE_REQUEST_SUBJECT',
                    '{siteTitle}: Kostenschätzung zur Freigabe – {title}',
                    ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]
                ))
                ->setHTMLTemplate('KW/MashaFeedly/Email/CostEstimateRequestedEmail')
                ->setPlainTemplate('KW/MashaFeedly/Email/CostEstimateRequestedEmailPlain')
                ->setData(self::emailData([
                    'SiteTitle' => $siteTitle,
                    'EntryTitle' => $entry->getTitle(),
                    'EntryURL' => $entryURL,
                ], $member))
            )) {
                $sent++;
            }
        }
        return $sent;
    }
}
