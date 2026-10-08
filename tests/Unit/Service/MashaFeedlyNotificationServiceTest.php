<?php

namespace KW\MashaFeedly\Tests\Unit\Service;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Control\Email\Mailer;

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
        foreach (['allowed', 'notAllowed', 'normalize'] as $fixtureName) {
            $member = $this->objFromFixture(Member::class, $fixtureName);
            $member->MashaFeedlyEmailNotifications = true;
            $member->MashaFeedlyNotifyNewEntries = true;
            $member->MashaFeedlyNotifyEntryUpdates = true;
            $member->MashaFeedlyNotifyOwnEntryChanges = false;
            $member->MashaFeedlyNotifyComments = true;
            $member->MashaFeedlyNotifyDueDateReminders = true;
            $member->MashaFeedlyNotifyCostEstimates = true;
            $member->write();
        }
    }

    /** Der allgemeine Testversand adressiert das Konto und enthält eine einfache Zustellbestätigung. */
    public function testTestEmailUsesMemberAddressAndIdentifiesTheSite(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->Title = 'Projekt Wolke';
        $config->MashaFeedlyEmailFooter = "Masha:Feedly · Kooperative Web\nKontakt: example@example.test · Kennung: TEST-123";
        $config->write();
        $mailer = new class implements Mailer {
            /** @var Mailer[] */
            public array $messages = [];
            public function send($message) { $this->messages[] = $message; }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);
        try {
            MashaFeedlyNotificationService::sendTestEmail($member);

            $this->assertCount(1, $mailer->messages);
            $this->assertSame('allowed@example.test', array_key_first($mailer->messages[0]->getTo()));
            $this->assertSame('Masha:Feedly – Test-E-Mail', $mailer->messages[0]->getSubject());
            $this->assertStringContainsString('Projekt Wolke', (string)$mailer->messages[0]->findPlainPart()->getBody());
            $this->assertStringContainsString('Impressum:', (string)$mailer->messages[0]->findPlainPart()->getBody());
            $this->assertStringContainsString('E-Mail-Einstellungen ändern:', (string)$mailer->messages[0]->findPlainPart()->getBody());
            $this->assertStringContainsString('automatische E-Mail, weil du solche Benachrichtigungen aktiviert hast', (string)$mailer->messages[0]->findPlainPart()->getBody());
            $this->assertStringContainsString('Kontakt: example@example.test · Kennung: TEST-123', (string)$mailer->messages[0]->findPlainPart()->getBody());
            $this->assertSame(1, substr_count((string)$mailer->messages[0]->findPlainPart()->getBody(), 'Kennung:'));
        } finally {
            $injector->registerService($originalMailer, Mailer::class);
        }
    }

    /** Ein leerer CMS-Footer wird aus der Serverumgebung mit Zeilenumbrüchen vorbelegt. */
    public function testDefaultEmailFooterUsesEnvironmentValue(): void
    {
        $environmentKey = 'MASHA_FEEDLY_EMAIL_FOOTER';
        $originalValue = Environment::getEnv($environmentKey);
        Environment::setEnv($environmentKey, 'Projekt Kooperative Web\\nKontakt: example@example.test');
        try {
            $this->assertSame(
                "Projekt Kooperative Web\nKontakt: example@example.test",
                MashaFeedlyConfigExtension::defaultEmailFooter()
            );
        } finally {
            Environment::setEnv($environmentKey, is_string($originalValue) ? $originalValue : '');
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

        $mailer = new class implements Mailer {
            public array $messages = [];
            public function send($message) { $this->messages[] = $message; }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);
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
            $this->assertSame('allowed@example.test', array_key_first($mailer->messages[0]->getTo()));
            $this->assertSame('Projekt Wolke: Heute fällig – Termin für den Regressionstest.', $mailer->messages[0]->getSubject());
            $this->assertStringContainsString('2026-10-04', (string)$mailer->messages[0]->getBody());
            $this->assertNotEmpty($entry->DueDateReminderSentAt);
            $this->assertSame(0, MashaFeedlyNotificationService::notifyDueDateReminder($entry));
            $this->assertCount(1, $mailer->messages);
        } finally {
            Security::setCurrentUser($previousMember);
            $injector->registerService($originalMailer, Mailer::class);
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

        $mailer = new class implements Mailer {
            /** @var Mailer[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send($message)
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);

        try {
            MashaFeedlyNotificationService::notifyAccessGranted($member);

            $this->assertCount(1, $mailer->messages);
            $message = $mailer->messages[0];
            $this->assertStringContainsString('Projekt Wolke', $message->getSubject());
            $this->assertStringContainsString('So erstellst du eine Meldung', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Dein Profil und deine E-Mails', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('freigeschaltet sind', (string)$message->findPlainPart()->getBody());
        } finally {
            $injector->registerService($originalMailer, Mailer::class);
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
        $mailer = new class implements Mailer {
            public array $messages = [];
            public function send($message) { $this->messages[] = $message; }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);
        $previousMember = Security::getCurrentUser();
        try {
            Security::setCurrentUser($member);
            $entry = MashaFeedlyEntry::create(['Content' => 'Erinnerung aus.', 'EntryDate' => '2026-10-01', 'DueDate' => '2026-10-04']);
            $entry->write();
            $mailer->messages = [];
            $this->assertSame(0, MashaFeedlyNotificationService::notifyDueDateReminder($entry));
            $this->assertCount(0, $mailer->messages);
            $this->assertEmpty($entry->DueDateReminderSentAt);
        } finally {
            Security::setCurrentUser($previousMember);
            $injector->registerService($originalMailer, Mailer::class);
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

        $mailer = new class implements Mailer {
            /** @var Mailer[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send($message)
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);

        try {
            $entry = MashaFeedlyEntry::create([
                'Content' => 'Der Speichern-Button löst einen Fehler aus.',
                'EntryDate' => '2026-10-01',
                'DueDate' => '2026-10-12',
                'ReportedByID' => (int)$allowedMember->ID,
            ]);
            $entry->write();
            $entry->AssignedMembers()->add($allowedMember);
            $mailer->messages = [];
            MashaFeedlyNotificationService::notifyNewEntry($entry);

            $this->assertCount(1, $mailer->messages);
            $message = $mailer->messages[0];
            $this->assertSame('allowed@example.test', array_key_first($message->getTo()));
            $this->assertSame(
                'Neue Meldung auf Projekt Wolke',
                $message->getSubject()
            );
            $this->assertSame(1, substr_count((string)$message->findPlainPart()->getBody(), 'Der Speichern-Button löst einen Fehler aus.'));
            $this->assertStringContainsString('Neue Meldung', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Auf Projekt Wolke wurde eine neue Meldung erstellt.', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Vollständige Beschreibung:', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Gemeldet von:', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString((string)$allowedMember->getName(), (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Kategorie:', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString((string)$entry->Category()->Title, (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Priorität:', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString((string)$entry->Priority()->Title, (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Fällig am:', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString((string)$entry->dbObject('DueDate')->Nice(), (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Verantwortlich:', (string)$message->findPlainPart()->getBody());
            $this->assertStringNotContainsString('Neue Meldung: Der Speichern-Button löst einen Fehler aus.', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('Meldung ansehen:', (string)$message->findPlainPart()->getBody());
            $this->assertStringContainsString('/admin/masha-feedly/', (string)$message->findPlainPart()->getBody());

            $entry->Content = 'Aktualisierte Beschreibung';
            $entry->write();
            $this->assertCount(1, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, Mailer::class);
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

        $mailer = new class implements Mailer {
            /** @var Mailer[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send($message)
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);

        try {
            MashaFeedlyEntry::create([
                'Content' => 'Ein weiterer Bug-Hinweis.',
                'EntryDate' => '2026-10-01',
            ])->write();

            $this->assertCount(0, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, Mailer::class);
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

        $mailer = new class implements Mailer {
            /** @var Mailer[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send($message)
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);

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
            $this->assertStringContainsString('Aktuelle Kategorie: Doing', (string)$mailer->messages[0]->getBody());

            $entry->Content = 'Der Fehler tritt jetzt beim Aktualisieren auf.';
            $entry->write();

            $this->assertCount(2, $mailer->messages);
            $message = $mailer->messages[1];
            $this->assertSame('allowed@example.test', array_key_first($message->getTo()));
            $this->assertSame(
                'Masha Feedly: Masha-Feedly-Meldung geändert: Der Fehler tritt jetzt beim Aktualisieren auf.',
                $message->getSubject()
            );
            $this->assertStringContainsString('Aktuelle Kategorie: Doing', (string)$message->getBody());

            $entry->write();
            $this->assertCount(2, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, Mailer::class);
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

        $mailer = new class implements Mailer {
            /** @var Mailer[] Gespeicherte Testnachrichten. */
            public array $messages = [];

            public function send($message)
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);

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
            $this->assertSame('allowed@example.test', array_key_first($mailer->messages[0]->getTo()));
        } finally {
            $injector->registerService($originalMailer, Mailer::class);
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

        $mailer = new class implements Mailer {
            /** @var Mailer[] */
            public array $messages = [];
            public function send($message)
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);
        try {
            MashaFeedlyNotificationService::notifyNewComment($entry, $comment, $author);

            $this->assertCount(1, $mailer->messages);
            $message = $mailer->messages[0];
            $this->assertSame('normalize@example.test', array_key_first($message->getTo()));
            $this->assertSame('Projekt Wolke: Neuer Kommentar zu Fehler im Formular speichern.', $message->getSubject());
            $this->assertStringContainsString('Erika Muster', (string)$message->getBody());
            $this->assertStringContainsString('Der Button bleibt deaktiviert.', (string)$message->getBody());
            $this->assertStringContainsString('/admin/masha-feedly/', (string)$message->getBody());

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
            $injector->registerService($originalMailer, Mailer::class);
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

        $mailer = new class implements Mailer {
            /** @var Mailer[] */
            public array $messages = [];
            public function send($message)
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);
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
            $this->assertSame('normalize@example.test', array_key_first($mailer->messages[0]->getTo()));
            $this->assertSame('Projekt Wolke: Neuer Kommentar zu Fehler der Erstellerin.', $mailer->messages[0]->getSubject());

            $creator->MashaFeedlyNotifyComments = false;
            $creator->write();
            MashaFeedlyNotificationService::notifyNewComment($entry, $comment, $author);
            $this->assertCount(1, $mailer->messages, 'Abgewählte Kommentar-Mails müssen auch beim Ersteller respektiert werden.');
        } finally {
            Security::setCurrentUser($previousMember);
            $injector->registerService($originalMailer, Mailer::class);
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

        $mailer = new class implements Mailer {
            /** @var Mailer[] */
            public array $messages = [];
            public function send($message)
            {
                $this->messages[] = $message;
            }
        };
        $injector = Injector::inst();
        $originalMailer = $injector->get(Mailer::class);
        $injector->registerService($mailer, Mailer::class);
        try {
            $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
            $config->write();
            $this->assertCount(1, $mailer->messages);
            $message = $mailer->messages[0];
            $this->assertSame('allowed@example.test', array_key_first($message->getTo()));
            $this->assertSame('Willkommen bei Masha:Feedly auf Projekt Wolke', $message->getSubject());
            $this->assertStringContainsString('pinke plus', mb_strtolower((string)$message->findPlainPart()->getBody()));

            $config->write();
            $this->assertCount(1, $mailer->messages);
        } finally {
            $injector->registerService($originalMailer, Mailer::class);
        }
    }
}
