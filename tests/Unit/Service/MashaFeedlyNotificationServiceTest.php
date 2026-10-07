<?php

namespace KW\MashaFeedly\Tests\Unit\Service;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;

/**
 * Tests für den E-Mail-Versand bei neuen Masha-Feedly-Einträgen.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyNotificationServiceTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedly.yml';

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('de_DE');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyEmailTestSucceeded = true;
        $config->write();
    }

    /** Der allgemeine Testversand adressiert das Konto und enthält eine einfache Zustellbestätigung. */
    public function testTestEmailUsesMemberAddressAndIdentifiesTheSite(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Projekt Wolke';
        $config->write();
        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] */
            public array $messages = [];
            public function send(RawMessage $message, ?Envelope $envelope = null): void { $this->messages[] = $message; }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        try {
            MashaFeedlyNotificationService::sendTestEmail($member);

            $this->assertCount(1, $mailer->messages);
            $this->assertSame('allowed@example.test', $mailer->messages[0]->getTo()[0]->getAddress());
            $this->assertSame('Masha:Feedly – Test-E-Mail', $mailer->messages[0]->getSubject());
            $this->assertStringContainsString('Projekt Wolke', (string)$mailer->messages[0]->getTextBody());
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Erinnerungen gehen nur an optierte, freigegebene Zuständige und werden nicht doppelt versandt. */
    public function testDueDateReminderHonoursPreferenceAndIsSentOnlyOnce(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $member->MashaFeedlyEmailNotifications = true;
        $member->MashaFeedlyNotifyDueDateReminders = true;
        $member->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Projekt Wolke';
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();

        $mailer = new class implements MailerInterface {
            public array $messages = [];
            public function send(RawMessage $message, ?Envelope $envelope = null): void { $this->messages[] = $message; }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        $previousMember = Security::getCurrentUser();
        try {
            Security::setCurrentUser($member);
            $entry = MashaFeedlyEntry::create([
                'Content' => 'Termin für den Regressionstest.',
                'EntryDate' => '2026-10-01',
                'DueDate' => '2026-10-04',
            ]);
            $entry->write();
            $mailer->messages = [];

            $this->assertSame(1, MashaFeedlyNotificationService::notifyDueDateReminder($entry));
            $this->assertSame('allowed@example.test', $mailer->messages[0]->getTo()[0]->getAddress());
            $this->assertSame('Projekt Wolke: Heute fällig – Termin für den Regressionstest.', $mailer->messages[0]->getSubject());
            $this->assertStringContainsString('2026-10-04', (string)$mailer->messages[0]->getTextBody());
            $this->assertNotEmpty($entry->DueDateReminderSentAt);
            $this->assertSame(0, MashaFeedlyNotificationService::notifyDueDateReminder($entry));
            $this->assertCount(1, $mailer->messages);
        } finally {
            Security::setCurrentUser($previousMember);
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft, dass die Freischaltungs-E-Mail die wichtigsten ersten Schritte erklärt. */
    public function testAccessGrantedEmailExplainsReportingAndProfileNotifications(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $member->MashaFeedlyEmailNotifications = true;
        $member->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Projekt Wolke';
        $config->write();

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);

        try {
            MashaFeedlyNotificationService::notifyAccessGranted($member);

            $this->assertCount(1, $mailer->messages);
            $message = $mailer->messages[0];
            $this->assertStringContainsString('Projekt Wolke', $message->getSubject());
            $this->assertStringContainsString('Eine Meldung erstellen', (string)$message->getTextBody());
            $this->assertStringContainsString('Status und Zuständigkeit', (string)$message->getTextBody());
            $this->assertStringContainsString('Benachrichtigungen und Profil', (string)$message->getTextBody());
            $this->assertStringContainsString('freigegeben sind', (string)$message->getTextBody());
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Ohne aktivierte Erinnerungen wird nicht gesendet und der Eintrag bleibt erneut prüfbar. */
    public function testDueDateReminderCanBeDisabledPerMember(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $member->MashaFeedlyEmailNotifications = true;
        $member->MashaFeedlyNotifyDueDateReminders = false;
        $member->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();
        $mailer = new class implements MailerInterface {
            public array $messages = [];
            public function send(RawMessage $message, ?Envelope $envelope = null): void { $this->messages[] = $message; }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        $previousMember = Security::getCurrentUser();
        try {
            Security::setCurrentUser($member);
            $entry = MashaFeedlyEntry::create(['Content' => 'Erinnerung aus.', 'EntryDate' => '2026-10-01', 'DueDate' => '2026-10-04']);
            $entry->write();
            $this->assertSame(0, MashaFeedlyNotificationService::notifyDueDateReminder($entry));
            $this->assertCount(0, $mailer->messages);
            $this->assertEmpty($entry->DueDateReminderSentAt);
        } finally {
            Security::setCurrentUser($previousMember);
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft Versand an abonnierende freigegebene Mitglieder und Ausschluss aller anderen. */
    public function testNewEntryEmailsOnlyOptedInMembersWithModuleAccess(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $notAllowedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $disabledMember = $this->objFromFixture(Member::class, 'normalize');
        $allowedMember->MashaFeedlyEmailNotifications = true;
        $allowedMember->MashaFeedlyNotifyNewEntries = true;
        $disabledMember->MashaFeedlyNotifyNewEntries = false;
        $disabledMember->MashaFeedlyNotifyEntryUpdates = false;
        $allowedMember->MashaFeedlyNotifyEntryUpdates = false;
        $disabledMember->write();
        $allowedMember->write();

        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Projekt Wolke';
        $config->MashaFeedlyAllowedMemberIDs = json_encode([
            (int)$allowedMember->ID,
            (int)$disabledMember->ID,
        ]);
        $config->write();

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);

        try {
            $entry = MashaFeedlyEntry::create([
                'Content' => 'Der Speichern-Button löst einen Fehler aus.',
                'EntryDate' => '2026-10-01',
            ]);
            $entry->write();

            $this->assertCount(1, $mailer->messages);
            $message = $mailer->messages[0];
            $this->assertSame('allowed@example.test', $message->getTo()[0]->getAddress());
            $this->assertSame(
                'Projekt Wolke: Neuer Masha-Feedly-Bug: Der Speichern-Button löst einen Fehler aus.',
                $message->getSubject()
            );
            $this->assertStringContainsString('Der Speichern-Button löst einen Fehler aus.', (string)$message->getTextBody());
            $this->assertStringContainsString('/admin/masha-feedly/', (string)$message->getTextBody());
            $this->assertStringContainsString('Projekt Wolke', (string)$message->getTextBody());

            $entry->Content = 'Aktualisierte Beschreibung';
            $entry->write();
            $this->assertCount(1, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft, dass ohne allgemeine E-Mail-Zustimmung keine Benachrichtigung versendet wird. */
    public function testNewEntryEmailRequiresGeneralEmailConsent(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $member->MashaFeedlyEmailNotifications = false;
        $member->MashaFeedlyNotifyNewEntries = true;
        $member->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);

        try {
            MashaFeedlyEntry::create([
                'Content' => 'Ein weiterer Bug-Hinweis.',
                'EntryDate' => '2026-10-01',
            ])->write();

            $this->assertCount(0, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft, dass inhaltliche Änderungen nur an Mitglieder mit passender Einstellung gemailt werden. */
    public function testUpdatedEntryEmailRequiresUpdatePreference(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $disabledMember = $this->objFromFixture(Member::class, 'normalize');
        $allowedMember->MashaFeedlyEmailNotifications = true;
        $allowedMember->MashaFeedlyNotifyEntryUpdates = true;
        $allowedMember->write();
        $disabledMember->MashaFeedlyNotifyEntryUpdates = false;
        $disabledMember->MashaFeedlyNotifyNewEntries = false;
        $disabledMember->write();

        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Masha Feedly';
        $config->MashaFeedlyAllowedMemberIDs = json_encode([
            (int)$allowedMember->ID,
            (int)$disabledMember->ID,
        ]);
        $config->write();

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);

        try {
            $entry = MashaFeedlyEntry::create([
                'Content' => 'Der Fehler tritt beim Speichern auf.',
                'EntryDate' => '2026-10-01',
            ]);
            $entry->write();
            $mailer->messages = [];

            $entry->CategoryID = MashaFeedlyCategory::get()->filter('Title', 'Doing')->first()->ID;
            $entry->write();

            $this->assertCount(1, $mailer->messages);
            $this->assertStringContainsString('Aktuelle Kategorie: Doing', (string)$mailer->messages[0]->getTextBody());

            $entry->Content = 'Der Fehler tritt jetzt beim Aktualisieren auf.';
            $entry->write();

            $this->assertCount(2, $mailer->messages);
            $message = $mailer->messages[1];
            $this->assertSame('allowed@example.test', $message->getTo()[0]->getAddress());
            $this->assertSame(
                'Masha Feedly: Masha-Feedly-Eintrag geändert: Der Fehler tritt jetzt beim Aktualisieren auf.',
                $message->getSubject()
            );
            $this->assertStringContainsString('Aktuelle Kategorie: Doing', (string)$message->getTextBody());

            $entry->write();
            $this->assertCount(2, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft, dass Mitglieder mit aktivierten Präferenzen E-Mails auch für eigene Einträge erhalten. */
    public function testOwnEntryAndUpdateEmailsAreConfigurable(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $member->MashaFeedlyEmailNotifications = true;
        $member->MashaFeedlyNotifyNewEntries = true;
        $member->MashaFeedlyNotifyEntryUpdates = true;
        $member->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();
        $this->logInAs($member);

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);

        try {
            $entry = MashaFeedlyEntry::create([
                'Content' => 'Eigener Änderungstest.',
                'EntryDate' => '2026-10-01',
            ]);
            $entry->write();
            $this->assertCount(0, $mailer->messages);

            $entry->Content = 'Eigene Änderung ohne Benachrichtigung.';
            $entry->write();
            $this->assertCount(0, $mailer->messages);

            $member->MashaFeedlyNotifyOwnEntryChanges = true;
            $member->write();
            $newEntry = MashaFeedlyEntry::create([
                'Content' => 'Eigener neuer Eintrag mit Benachrichtigung.',
                'EntryDate' => '2026-10-01',
            ]);
            $newEntry->write();
            $this->assertCount(1, $mailer->messages);

            $newEntry->Content = 'Eigener geänderter Eintrag mit Benachrichtigung.';
            $newEntry->write();

            $this->assertCount(2, $mailer->messages);
            $this->assertSame('allowed@example.test', $mailer->messages[0]->getTo()[0]->getAddress());
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft Kommentar-Mails nur an zugewiesene, freigegebene Empfänger mit aktiver Präferenz. */
    public function testNewCommentEmailsAssignedMembersWithCommentPreference(): void
    {
        $author = $this->objFromFixture(Member::class, 'allowed');
        $assignee = $this->objFromFixture(Member::class, 'normalize');
        $optedOut = $this->objFromFixture(Member::class, 'notAllowed');
        $author->MashaFeedlyNotifyNewEntries = false;
        $assignee->MashaFeedlyNotifyNewEntries = false;
        $assignee->MashaFeedlyEmailNotifications = true;
        $assignee->MashaFeedlyNotifyComments = true;
        $author->write();
        $assignee->write();
        $optedOut->MashaFeedlyNotifyComments = false;
        $optedOut->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Projekt Wolke';
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$author->ID, (int)$assignee->ID]);
        $config->write();

        $entry = MashaFeedlyEntry::create(['Content' => 'Fehler im Formular speichern.', 'EntryDate' => '2026-10-01']);
        $entry->write();
        $entry->AssignedMembers()->setByIDList([(int)$author->ID, (int)$assignee->ID, (int)$optedOut->ID]);
        $comment = MashaFeedlyComment::create([
            'AuthorName' => 'Erika Muster', 'CommentText' => 'Der Button bleibt deaktiviert.',
            'EntryID' => (int)$entry->ID, 'AuthorMemberID' => (int)$author->ID,
        ]);
        $comment->write();

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] */
            public array $messages = [];
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        try {
            MashaFeedlyNotificationService::notifyNewComment($entry, $comment, $author);

            $this->assertCount(1, $mailer->messages);
            $message = $mailer->messages[0];
            $this->assertSame('normalize@example.test', $message->getTo()[0]->getAddress());
            $this->assertSame('Projekt Wolke: Neuer Kommentar zu Fehler im Formular speichern.', $message->getSubject());
            $this->assertStringContainsString('Erika Muster', (string)$message->getTextBody());
            $this->assertStringContainsString('Der Button bleibt deaktiviert.', (string)$message->getTextBody());
            $this->assertStringContainsString('/admin/masha-feedly/', (string)$message->getTextBody());

            $assignee->MashaFeedlyEmailNotifications = false;
            $assignee->write();
            MashaFeedlyNotificationService::notifyNewComment($entry, $comment, $author);
            $this->assertCount(1, $mailer->messages);

            $assignee->MashaFeedlyEmailNotifications = true;
            $assignee->MashaFeedlyNotifyComments = false;
            $assignee->write();
            MashaFeedlyNotificationService::notifyNewComment($entry, $comment, $author);
            $this->assertCount(1, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Der nicht zuständige Ersteller erhält Kommentare ebenfalls, sofern er Kommentar-Mails aktiviert hat. */
    public function testEntryCreatorReceivesCommentEmailWithoutBeingAssigned(): void
    {
        $author = $this->objFromFixture(Member::class, 'allowed');
        $creator = $this->objFromFixture(Member::class, 'normalize');
        foreach ([$author, $creator] as $member) {
            $member->MashaFeedlyNotifyNewEntries = false;
            $member->write();
        }
        $creator->MashaFeedlyEmailNotifications = true;
        $creator->MashaFeedlyNotifyComments = true;
        $creator->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Projekt Wolke';
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$author->ID, (int)$creator->ID]);
        $config->write();

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] */
            public array $messages = [];
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        $previousMember = Security::getCurrentUser();
        try {
            Security::setCurrentUser($creator);
            $entry = MashaFeedlyEntry::create(['Content' => 'Fehler der Erstellerin.', 'EntryDate' => '2026-10-01']);
            $entry->write();
            $entry->AssignedMembers()->setByIDList([]);
            $this->assertSame((int)$creator->ID, $entry->creatorMemberID());

            Security::setCurrentUser($author);
            $comment = MashaFeedlyComment::create([
                'AuthorName' => 'Erika Muster', 'CommentText' => 'Ich prüfe das.',
                'EntryID' => (int)$entry->ID, 'AuthorMemberID' => (int)$author->ID,
            ]);
            $comment->write();
            MashaFeedlyNotificationService::notifyNewComment($entry, $comment, $author);

            $this->assertCount(1, $mailer->messages);
            $this->assertSame('normalize@example.test', $mailer->messages[0]->getTo()[0]->getAddress());
            $this->assertSame('Projekt Wolke: Neuer Kommentar zu Fehler der Erstellerin.', $mailer->messages[0]->getSubject());

            $creator->MashaFeedlyNotifyComments = false;
            $creator->write();
            MashaFeedlyNotificationService::notifyNewComment($entry, $comment, $author);
            $this->assertCount(1, $mailer->messages, 'Abgewählte Kommentar-Mails müssen auch beim Ersteller respektiert werden.');
        } finally {
            Security::setCurrentUser($previousMember);
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft die Willkommensmail genau beim erstmaligen Hinzufügen zur Freigabeliste. */
    public function testNewlyAllowedMemberReceivesWelcomeEmailOnlyOnce(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $member->MashaFeedlyEmailNotifications = true;
        $member->write();
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Projekt Wolke';

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] */
            public array $messages = [];
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(MailerInterface::class);
        $injector->registerService($mailer, MailerInterface::class);
        try {
            $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
            $config->write();
            $this->assertCount(1, $mailer->messages);
            $message = $mailer->messages[0];
            $this->assertSame('allowed@example.test', $message->getTo()[0]->getAddress());
            $this->assertSame('Willkommen bei Masha:Feedly auf Projekt Wolke', $message->getSubject());
            $this->assertStringContainsString('klicke auf das plus', mb_strtolower((string)$message->getTextBody()));

            $config->write();
            $this->assertCount(1, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }
}
