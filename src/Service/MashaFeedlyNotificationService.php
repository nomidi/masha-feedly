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
    /**
     * Liefert Ersteller und aktuelle Zuständige für den persönlichen E-Mail-Bereich.
     * @param MashaFeedlyEntry $entry Betroffene Meldung.
     * @return int[] Mitglieds-IDs, ohne fehlenden Ersteller und doppelte Zuweisungen.
     */
    private static function personalRecipientIDs(MashaFeedlyEntry $entry): array
    {
        return array_values(array_unique(array_filter([
            ...array_map('intval', $entry->AssignedMembers()->column('ID')),
            $entry->creatorMemberID(),
        ], static fn(int $id): bool => $id > 0)));
    }

    /** Stellt die gemeinsamen Meldungsdaten für gestaltete Benachrichtigungen bereit. */
    private static function entryEmailDetails(MashaFeedlyEntry $entry): array
    {
        $assignedNames = [];
        foreach ($entry->AssignedMembers()->sort('Surname ASC, FirstName ASC') as $member) {
            $assignedNames[] = (string)$member->getName();
        }
        $description = (string)$entry->Content;
        $description = preg_replace('/<\\s*br\\s*\\/?\\s*>|<\\/\\s*(?:p|div|li|h[1-6])\\s*>/i', "\n", $description) ?? $description;
        $dueDate = trim((string)$entry->DueDate);
        return [
            'BugTitle' => $entry->getTitle(),
            'EntryTitle' => $entry->getTitle(),
            'ReporterName' => trim($entry->reportedByName()),
            'CategoryTitle' => trim((string)$entry->Category()->Title),
            'PriorityTitle' => trim((string)$entry->Priority()->Title),
            'DueDate' => $dueDate !== '' ? self::formatDueDate($dueDate) : '',
            'AssignedNames' => implode(', ', $assignedNames),
            'BugDescription' => trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ];
    }

    /** Formatiert ein reines Kalenderdatum ohne Zeitzonenverschiebung. */
    private static function formatDueDate(string $date): string
    {
        $calendarDate = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        return $calendarDate ? $calendarDate->format('d.m.Y') : $date;
    }

    /** Ergänzt Maildaten um den zentralen Footer und den direkten Profil-Link. */
    private static function emailData(array $data, ?Member $member = null, ?string $notificationType = null, array $placeholders = []): array
    {
        $profileURL = Director::absoluteURL(CMSProfileController::singleton()->Link() . '#Root_MashaFeedly');
        $isFormal = $member
            ? MashaFeedlyMemberExtension::addressFor($member) === 'sie'
            : MashaFeedlyConfigExtension::address() === 'sie';
        $intro = $notificationType !== null ? self::notificationIntro($notificationType, $member, $placeholders) : null;
        return array_merge($data, [
            // Silverstripe 5 benötigt benannte Datenobjekte statt einer Liste roher Textwerte.
            'EmailFooterLines' => ArrayList::create(array_map(
                static fn(string $line): ArrayData => ArrayData::create(['Text' => $line]),
                preg_split('/\R/u', MashaFeedlyConfigExtension::emailFooter()) ?: []
            )),
            'EmailProfileURL' => $profileURL,
            'EmailImprintURL' => 'https://www.kooperative-web.de/impressum',
            'EmailOptOutText' => i18n::_t(
                $isFormal ? 'KW\\MashaFeedly\\Translations.EMAIL_FOOTER_OPT_OUT_SIE' : 'KW\\MashaFeedly\\Translations.EMAIL_FOOTER_OPT_OUT_DU',
                $isFormal
                    ? 'Möchten Sie keine Statusmeldungen per E-Mail mehr erhalten? Passen Sie Ihre Auswahl in Ihrem Profil an.'
                    : 'Möchtest du keine Statusmeldungen per E-Mail mehr erhalten? Passe deine Auswahl in deinem Profil an.'
            ),
            'NotificationIntroHTML' => $intro['html'] ?? ($data['NotificationIntroHTML'] ?? ''),
            'NotificationIntroPlain' => $intro['plain'] ?? ($data['NotificationIntroPlain'] ?? ''),
        ]);
    }

    /** Liefert die formatierte Einleitung in der persönlichen Anrede des Empfängers. @param string $type Benachrichtigungstyp. @param Member|null $member Empfänger. @param array<string, string> $placeholders Dynamische Mailwerte. @return array{html: string, plain: string}|null Gespeicherte Einleitung oder bisheriger Vorlagentext. */
    private static function notificationIntro(string $type, ?Member $member, array $placeholders): ?array
    {
        if (!$member) return null;
        $address = $type === 'welcome' ? MashaFeedlyConfigExtension::address() : MashaFeedlyMemberExtension::addressFor($member);
        $html = MashaFeedlyConfigExtension::notificationEmailText($type, $address);
        if ($html === '') return null;
        $escaped = [];
        foreach ($placeholders as $name => $value) {
            $escaped['{' . $name . '}'] = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $html = strtr($html, $escaped);
        $plain = preg_replace('/<\\s*br\\s*\\/?\\s*>|<\\/\\s*(?:p|div|li|h[1-6])\\s*>/i', "\n", $html) ?? $html;
        $plain = trim(html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return ['html' => $html, 'plain' => $plain];
    }

    /** Versendet eine Benachrichtigung, ohne einen fachlichen Schreibvorgang durch Mailfehler abzubrechen. */
    private static function sendSafely(Email $email): bool
    {
        try {
            $email->send();
            return true;
        } catch (\Throwable $exception) {
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

    /** Sendet eine ungespeicherte E-Mail-Vorlage zur Prüfung an das Betreiberkonto. */
    public static function sendEmailTextPreview(Member $member, string $fieldName, string $text): void
    {
        if (!Email::is_valid_address((string)$member->Email)) {
            throw new \RuntimeException('Für das Betreiberkonto ist keine gültige E-Mail-Adresse hinterlegt.');
        }
        $type = null;
        $address = null;
        foreach (MashaFeedlyConfigExtension::notificationFieldMap() as $candidateType => $addresses) {
            foreach ($addresses as $candidateAddress => $candidateField) {
                if ($candidateField === $fieldName) {
                    $type = $candidateType;
                    $address = $candidateAddress;
                    break 2;
                }
            }
        }
        if ($type === null || $address === null) {
            throw new \InvalidArgumentException('Unbekannte Benachrichtigungsvorlage.');
        }
        $siteTitle = trim((string)SiteConfig::current_site_config()->Title) ?: 'Masha:Feedly';
        $placeholders = [
            '{siteTitle}' => htmlspecialchars($siteTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{websiteName}' => htmlspecialchars($siteTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{recipientName}' => htmlspecialchars((string)$member->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{name}' => htmlspecialchars((string)$member->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{title}' => htmlspecialchars('Beispielmeldung', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{date}' => htmlspecialchars(date('d.m.Y'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{author}' => htmlspecialchars((string)$member->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ];
        $html = strtr($text, $placeholders);
        $plain = preg_replace('/<\\s*br\\s*\\/?\\s*>|<\\/\\s*(?:p|div|li|h[1-6])\\s*>/i', "\\n", $html) ?? $html;
        $plain = trim(html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $labels = [
            'welcome' => 'Willkommen',
            'newEntry' => 'Neue Meldung',
            'updatedEntry' => 'Meldung geändert',
            'newComment' => 'Neuer Kommentar',
            'dueDate' => 'Heute fällig',
            'costEstimate' => 'Kostenschätzung angefragt',
        ];
        $previewTemplates = [
            'welcome' => ['AccessGrantedEmail', 'AccessGrantedEmailPlain'],
            'newEntry' => ['NewEntryEmail', 'NewEntryEmailPlain'],
            'updatedEntry' => ['NotificationTextPreviewUpdatedEntry', 'NotificationTextPreviewUpdatedEntryPlain'],
            'newComment' => ['NewCommentEmail', 'NewCommentEmailPlain'],
            'dueDate' => ['DueDateReminderEmail', 'DueDateReminderEmailPlain'],
            'costEstimate' => ['CostEstimateRequestedEmail', 'CostEstimateRequestedEmailPlain'],
        ];
        $exampleTitle = 'Beispielmeldung: Kontaktformular funktioniert nicht';
        $exampleURL = Director::absoluteURL(Director::baseURL() . '?masha-feedly-entry=123');
        $previewData = [
            'SiteTitle' => $siteTitle,
            'NotificationIntroHTML' => $html,
            'NotificationIntroPlain' => $plain,
            'WidgetURL' => Director::absoluteURL(Director::baseURL()),
            'EntryURL' => $exampleURL,
            'BugTitle' => $exampleTitle,
            'EntryTitle' => $exampleTitle,
            'CategoryTitle' => 'In Bearbeitung',
            'PriorityTitle' => 'Normal',
            'DueDate' => $type === 'dueDate' ? date('d.m.Y') : date('d.m.Y', strtotime('+2 days')),
            'ReporterName' => 'Alex Beispiel',
            'AssignedNames' => 'Sam Beispiel',
            'BugDescription' => 'Das Kontaktformular zeigt nach dem Absenden eine Fehlermeldung. Bitte prüfen, damit Besucherinnen und Besucher Anfragen senden können.',
            'EstimateDuration' => '2–3 Stunden',
            'EstimatePrice' => '190,00 € – 285,00 €',
            'EstimateNote' => 'Ursache finden, Formular korrigieren und prüfen.',
            'CommentAuthor' => 'Sam Beispiel',
            'CommentDate' => date('d.m.Y, H:i'),
            'CommentText' => 'Ich prüfe das Formular und gebe hier Bescheid, sobald ich mehr weiß.',
        ];
        Email::create()
            ->setTo((string)$member->Email)
            ->setSubject('Masha:Feedly – Test: ' . $labels[$type] . ' (' . ($address === 'sie' ? 'Sie' : 'Du') . ')')
            ->setHTMLTemplate('KW/MashaFeedly/Email/' . $previewTemplates[$type][0])
            ->setPlainTemplate('KW/MashaFeedly/Email/' . $previewTemplates[$type][1])
            ->setData(self::emailData($previewData, $member))
            ->send();
    }

    /** Sendet einmalig Fälligkeitserinnerungen gemäß persönlichem Bereich und aktivierter Präferenz. */
    public static function notifyDueDateReminder(MashaFeedlyEntry $entry): int
    {
        if (!$entry->DueDate || $entry->DueDateReminderSentAt) {
            return 0;
        }
        $personalIDs = self::personalRecipientIDs($entry);
        $recipientIDs = MashaFeedlyConfigExtension::memberIDs();
        if (!$recipientIDs) {
            return 0;
        }

        $entryURL = Director::absoluteURL(MashaFeedlyAdmin::singleton()->getCMSEditLinkForManagedDataObject($entry));
        $siteTitle = trim((string)SiteConfig::current_site_config()->Title) ?: 'Masha:Feedly';
        $sent = 0;
        foreach (Member::get()->filter('ID', $recipientIDs) as $member) {
            if (
                !(bool)$member->MashaFeedlyEmailNotifications
                || ((bool)$member->MashaFeedlyEmailAssignedOnly && !in_array((int)$member->ID, $personalIDs, true))
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
                    'DueDate' => (string)$entry->dbObject('DueDate')->Nice(),
                    'EntryURL' => $entryURL,
                    ...self::entryEmailDetails($entry),
                ], $member, 'dueDate', ['title' => $entry->getTitle(), 'date' => (string)$entry->dbObject('DueDate')->Nice()]))
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
        if (!Email::is_valid_address((string)$member->Email)) {
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
            ->setData(self::emailData(['SiteTitle' => $siteTitle, 'WidgetURL' => $widgetURL], $member, 'welcome', ['siteTitle' => $siteTitle, 'websiteName' => $siteTitle]))
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
        $personalIDs = self::personalRecipientIDs($entry);
        $assignedNames = [];
        foreach ($assignedMembers as $assignedMember) {
            $assignedNames[] = (string)$assignedMember->getName();
        }
        $dueDate = trim((string)$entry->DueDate);
        $reporterName = trim($entry->reportedByName());
        $currentMemberID = (int)(Security::getCurrentUser()?->ID ?? 0);
        if ($siteTitle === '') {
            $siteTitle = 'Masha:Feedly';
        }

        foreach (Member::get()->filter('ID', $memberIDs) as $member) {
            if (
                !(bool)$member->MashaFeedlyEmailNotifications
                || ((bool)$member->MashaFeedlyEmailAssignedOnly && !in_array((int)$member->ID, $personalIDs, true))
                || !(bool)$member->MashaFeedlyNotifyNewEntries
                || (int)$member->ID === $currentMemberID
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
                    'DueDate' => $dueDate !== '' ? self::formatDueDate($dueDate) : '',
                    'AssignedNames' => implode(', ', $assignedNames),
                    'EntryURL' => $entryURL,
                ], $member, 'newEntry', ['siteTitle' => $siteTitle]))
            );
        }
    }

    /**
     * Sendet Änderungen oder zusätzliche Zuweisungshinweise ohne doppelte E-Mail.
     * @param MashaFeedlyEntry $entry Betroffene Meldung.
     * @param int[]|null $newAssignmentIDs Bei neuen Zuweisungen nur Empfänger ohne Änderungsabo benachrichtigen.
     * @return void
     */
    public static function notifyUpdatedEntry(MashaFeedlyEntry $entry, ?array $newAssignmentIDs = null): void
    {
        if ($newAssignmentIDs === []) {
            return;
        }

        $personalIDs = self::personalRecipientIDs($entry);
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
                || ((bool)$member->MashaFeedlyEmailAssignedOnly && !in_array((int)$member->ID, $personalIDs, true))
                || ($newAssignmentIDs === null
                    ? !(bool)$member->MashaFeedlyNotifyEntryUpdates
                    : (!in_array((int)$member->ID, $newAssignmentIDs, true)
                        || !(bool)$member->MashaFeedlyNotifyNewEntries
                        || (bool)$member->MashaFeedlyNotifyEntryUpdates))
                || (int)$member->ID === $currentMemberID
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
                ], $member, 'updatedEntry', ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]))
            );
        }
    }

    /** Sendet Kommentare an freigegebene Mitglieder gemäß Bereich und Kommentarpräferenz; nie an den Autor. */
    public static function notifyNewComment(MashaFeedlyEntry $entry, MashaFeedlyComment $comment, Member $author): void
    {
        $personalIDs = self::personalRecipientIDs($entry);
        $recipientIDs = array_values(array_diff(MashaFeedlyConfigExtension::memberIDs(), [(int)$author->ID]));
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
                || ((bool)$member->MashaFeedlyEmailAssignedOnly && !in_array((int)$member->ID, $personalIDs, true))
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
                    'CommentDate' => (string)$comment->dbObject('Created')->Nice(),
                    'EntryURL' => $entryURL,
                    ...self::entryEmailDetails($entry),
                ], $member, 'newComment', ['siteTitle' => $siteTitle, 'title' => $entry->getTitle(), 'author' => (string)$comment->AuthorName]))
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
                || (int)$member->ID === (int)(Security::getCurrentUser()?->ID ?? 0)
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
                    'EstimateDuration' => (string)$entry->EstimatedCostDuration,
                    'EstimatePrice' => trim((string)$entry->EstimatedCostDuration) !== '' ? $entry->estimatePriceLabel() : '',
                    'EstimateNote' => (string)$entry->EstimatedCostNote,
                    ...self::entryEmailDetails($entry),
                ], $member, 'costEstimate', ['siteTitle' => $siteTitle, 'title' => $entry->getTitle()]))
            )) {
                $sent++;
            }
        }
        return $sent;
    }
}
