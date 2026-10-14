<?php

namespace KW\MashaFeedly\Tests\Unit\Service;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Extension\MashaFeedlyErrorConfigExtension;
use KW\MashaFeedly\Logging\MashaFeedlyErrorHandler;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Service\MashaFeedlyErrorService;
use KW\MashaFeedly\Service\MashaFeedlyNotificationService;
use Monolog\Level;
use Monolog\LogRecord;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;

/** Prüft die gesamte Erfassung mit privaten Testdateien und abgefangenem Mailversand. */
class MashaFeedlyErrorServiceTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedly.yml';

    private string $directory;
    private Member $manager;
    private Member $customer;
    private MailerInterface $originalMailer;
    /** @var MailerInterface&object{messages: array<RawMessage>} Test-Mailer. */
    private MailerInterface $mailer;

    /** Richtet einen Betreiber und eine andere freigegebene Person ein. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = TEMP_PATH . '/feedly-error-test-' . bin2hex(random_bytes(8));
        Config::modify()->set(MashaFeedlyErrorHandler::class, 'spool_directory', $this->directory);
        $this->manager = $this->objFromFixture(Member::class, 'allowed');
        $this->customer = $this->objFromFixture(Member::class, 'normalize');
        $group = Group::create(['Title' => 'Technische Betreuung', 'Code' => 'feedly-error-test']);
        $group->write();
        Permission::grant($group->ID, 'ADMIN');
        $group->Members()->add($this->manager);
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [$this->manager->Email]);
        $this->originalMailer = Injector::inst()->get(MailerInterface::class);
        $this->mailer = new class implements MailerInterface {
            /** @var array<RawMessage> Abgefangene E-Mails. */
            public array $messages = [];
            /** Fängt den Versand ohne Netzwerkzugriff ab. */
            public function send(RawMessage $message, ?Envelope $envelope = null): void { $this->messages[] = $message; }
        };
        Injector::inst()->registerService($this->mailer, MailerInterface::class);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([$this->manager->ID, $this->customer->ID]);
        $config->MashaFeedlyErrorsEnabled = true;
        $config->MashaFeedlyErrorsMode = 'cron';
        $config->MashaFeedlyErrorsInterval = 5;
        $config->MashaFeedlyErrorsLevel = 'error';
        $config->MashaFeedlyEmailTestSucceeded = true;
        $config->write();
        foreach ([$this->manager, $this->customer] as $member) {
            $member->MashaFeedlyEmailNotifications = true;
            $member->MashaFeedlyEmailAssignedOnly = $member === $this->manager;
            $member->MashaFeedlyNotifyNewEntries = true;
            $member->MashaFeedlyNotifyEntryUpdates = true;
            $member->write();
        }
        Security::setCurrentUser(null);
        $this->mailer->messages = [];
    }

    /** Entfernt ausschließlich den eigenen privaten Testordner. */
    protected function tearDown(): void
    {
        Injector::inst()->registerService($this->originalMailer, MailerInterface::class);
        MashaFeedlyErrorHandler::$processing = false;
        foreach (glob($this->directory . '/*') ?: [] as $path) unlink($path);
        if (is_dir($this->directory)) rmdir($this->directory);
        parent::tearDown();
    }

    /** Erfasst einen Fehler zu einem festen UTC-Zeitpunkt. */
    private function capture(string $message = 'Fehler für ID 123'): void
    {
        (new MashaFeedlyErrorHandler())->handle(new LogRecord(new \DateTimeImmutable('2026-10-14 10:00:00', new \DateTimeZone('UTC')), 'application', Level::Error, $message));
    }

    /** Gleiche Fehler werden zusammengefasst, persönlich zugewiesen und für Kunden verborgen. */
    public function testGroupsErrorsAndUsesPersonalNotifications(): void
    {
        $this->capture();
        $this->capture('Fehler für ID 456');
        $this->assertSame(1, MashaFeedlyErrorService::run('cron'));
        $entry = MashaFeedlyEntry::get()->exclude('ErrorFingerprint', [null, ''])->first();
        $this->assertSame(2, (int)$entry->ErrorOccurrences);
        $this->assertSame([(int)$this->manager->ID], array_map('intval', $entry->AssignedMembers()->column('ID')));
        $this->assertTrue($entry->canView($this->manager));
        $this->assertFalse($entry->canView($this->customer));
        $this->assertFalse(MashaFeedlyEntry::visibleTo($this->customer)->filter('ID', $entry->ID)->exists());
        $this->assertCount(1, $this->mailer->messages);
        $this->assertSame($this->manager->Email, $this->mailer->messages[0]->getTo()[0]->getAddress());
        $this->capture();
        $this->assertSame(0, MashaFeedlyErrorService::run('cron'), 'Das Intervall verhindert einen weiteren Import.');
        unlink($this->directory . '/state.json');
        $this->assertSame(1, MashaFeedlyErrorService::run('cron'));
        $this->assertSame(3, (int)MashaFeedlyEntry::get()->byID($entry->ID)->ErrorOccurrences);
        $this->assertCount(1, $this->mailer->messages, 'Wiederholungen erzeugen keine Änderungsmail.');
    }

    /** Erledigte Fehler werden einmal wieder geöffnet; eine gespeicherte Charge wird nicht erneut gezählt. */
    public function testReopensAndRecoversWithoutDuplicateCounting(): void
    {
        $this->capture();
        $path = (glob($this->directory . '/*.pending.json'))[0];
        $batch = (string)file_get_contents($path);
        MashaFeedlyErrorService::run('cron');
        $entry = MashaFeedlyEntry::get()->exclude('ErrorFingerprint', [null, ''])->first();
        file_put_contents($path, $batch);
        MashaFeedlyErrorService::importFile($path, (int)$this->manager->ID);
        $this->assertSame(1, (int)MashaFeedlyEntry::get()->byID($entry->ID)->ErrorOccurrences);
        $closed = MashaFeedlyCategory::create(['Title' => 'Abgeschlossen', 'IsClosed' => true]);
        $closed->write();
        $entry->CategoryID = $closed->ID;
        $entry->write();
        $this->mailer->messages = [];
        $this->capture();
        unlink($this->directory . '/state.json');
        MashaFeedlyErrorService::run('cron');
        $entry = MashaFeedlyEntry::get()->byID($entry->ID);
        $this->assertFalse((bool)$entry->Category()->IsClosed);
        $this->assertCount(1, $this->mailer->messages);
    }

    /** Deaktivierung, Besuchsmodus und gegenseitige Sperre verhindern ungewollte Verarbeitung. */
    public function testRespectsModeDisabledCaptureAndLock(): void
    {
        $this->capture();
        $this->assertSame(0, MashaFeedlyErrorService::run('visitor'));
        $lock = fopen($this->directory . '/processing.lock', 'c');
        flock($lock, LOCK_EX);
        $this->assertSame(0, MashaFeedlyErrorService::run('cron'));
        flock($lock, LOCK_UN);
        fclose($lock);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyErrorsEnabled = false;
        $config->write();
        $this->capture('Ein anderer Fehler');
        $this->assertCount(1, glob($this->directory . '/*.pending.json'));
        $this->assertSame(0, MashaFeedlyErrorService::run('cron'));
    }

    /** Die Zusammenfassung bleibt auf den persönlichen Bereich beschränkt und wird pro Tag einmal versendet. */
    public function testDigestIsSentOnceAndHonoursEmailPreference(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyErrorsDigest = true;
        $config->MashaFeedlyErrorsDigestTime = '00:00';
        $config->write();
        $this->capture();
        $path = (glob($this->directory . '/*.pending.json'))[0];
        $batch = json_decode((string)file_get_contents($path), true);
        $batch['first'] = $batch['last'] = gmdate('Y-m-d H:i:s');
        MashaFeedlyErrorHandler::writeJSON($path, $batch);
        MashaFeedlyErrorService::run('cron');
        $this->assertCount(2, $this->mailer->messages);
        MashaFeedlyErrorService::run('cron');
        $this->assertCount(2, $this->mailer->messages);
        $this->manager->MashaFeedlyEmailNotifications = false;
        $this->manager->write();
        MashaFeedlyNotificationService::notifyErrorDigest(MashaFeedlyEntry::get()->exclude('ErrorFingerprint', [null, ''])->toArray());
        $this->assertCount(2, $this->mailer->messages);
    }

    /** Häufige Geheimnisse werden bereinigt; Kontext und Trace-Argumente werden überhaupt nicht übernommen. */
    public function testRedactsSecrets(): void
    {
        $this->capture('Aufruf https://user:pass@example.test/path?token=abcd password=secret Bearer private');
        $batch = file_get_contents((glob($this->directory . '/*.pending.json'))[0]);
        foreach (['user:pass', 'abcd', 'secret', 'private'] as $secret) $this->assertStringNotContainsString($secret, $batch);
    }

    /** Besuchsmodus nutzt dieselbe Verarbeitung und stellt die handelnde Person anschließend wieder her. */
    public function testVisitorProcessingRestoresCurrentUser(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyErrorsMode = 'visitor';
        $config->write();
        $this->capture();
        Security::setCurrentUser($this->customer);
        MashaFeedlyErrorService::runForWebsiteVisit();
        $this->assertSame((int)$this->customer->ID, (int)Security::getCurrentUser()->ID);
        $this->assertFalse(MashaFeedlyErrorHandler::$processing);
        $this->assertCount(1, $this->mailer->messages, 'Der Import bleibt eine Systemaktion und benachrichtigt die Betreuung.');
        $this->assertEmpty(glob($this->directory . '/*.pending.json'));
    }

    /** Mindestschweregrad und Rückkopplungsschutz verhindern das Erfassen ungeeigneter Ereignisse. */
    public function testSeverityAndFeedbackProtection(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyErrorsLevel = 'critical';
        $config->write();
        $this->capture();
        $this->assertEmpty(glob($this->directory . '/*.pending.json'));
        $handler = new MashaFeedlyErrorHandler();
        $record = new LogRecord(new \DateTimeImmutable(), 'application', Level::Critical, 'Kritischer Fehler');
        MashaFeedlyErrorHandler::$processing = true;
        $handler->handle($record);
        $this->assertEmpty(glob($this->directory . '/*.pending.json'));
        MashaFeedlyErrorHandler::$processing = false;
        $handler->handle($record);
        $this->assertCount(1, glob($this->directory . '/*.pending.json'));
    }

    /** Formularwerte werden sicher begrenzt und ausschließlich allgemein freigegebene Personen zusätzlich zugelassen. */
    public function testConfigurationNormalisesValuesAndBuildsFields(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        MashaFeedlyErrorConfigExtension::save($config, [
            'MashaFeedlyErrorsEnabled' => true, 'MashaFeedlyErrorsInterval' => 99999,
            'MashaFeedlyErrorsMode' => 'ungültig', 'MashaFeedlyErrorsLevel' => 'debug',
            'MashaFeedlyErrorsDigestTime' => '27:99', 'AllowedMemberIDs' => [$this->manager->ID],
            'MashaFeedlyErrorsMemberIDs' => [$this->manager->ID, $this->customer->ID, 999999],
        ]);
        $config->write();
        $this->assertSame(1440, (int)$config->MashaFeedlyErrorsInterval);
        $this->assertSame('cron', $config->MashaFeedlyErrorsMode);
        $this->assertSame('error', $config->MashaFeedlyErrorsLevel);
        $this->assertSame('08:00', $config->MashaFeedlyErrorsDigestTime);
        $this->assertSame([(int)$this->manager->ID], json_decode($config->MashaFeedlyErrorsMemberIDs, true));
        $fields = MashaFeedlyErrorConfigExtension::fields();
        $this->assertSame(1440, (int)$fields->dataFieldByName('MashaFeedlyErrorsInterval')->getValue());
        $this->assertNotNull($fields->dataFieldByName('MashaFeedlyErrorsEnabled'));
    }

    /** Ohne konfiguriertes Administratorkonto darf die Erfassung nicht aktiviert werden. */
    public function testActivationRequiresManager(): void
    {
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [$this->customer->Email]);
        $this->expectException(\InvalidArgumentException::class);
        MashaFeedlyErrorConfigExtension::save(MashaFeedlyConfigExtension::currentSiteConfig(), ['MashaFeedlyErrorsEnabled' => true]);
    }

    /** Ein abgebrochener Import wird aus der Arbeitsdatei übernommen und eine spätere Charge separat gezählt. */
    public function testResumesWorkingBatchBeforePendingBatch(): void
    {
        $this->capture();
        $pending = (glob($this->directory . '/*.pending.json'))[0];
        rename($pending, str_replace('.pending.json', '.working.json', $pending));
        $this->capture();
        $this->assertSame(2, MashaFeedlyErrorService::run('cron'));
        $entry = MashaFeedlyEntry::get()->exclude('ErrorFingerprint', [null, ''])->first();
        $this->assertSame(2, (int)$entry->ErrorOccurrences);
        $this->assertCount(1, $this->mailer->messages);
    }

    /** Entfernen der Zuständigkeit beim Bearbeiten hebt die feste Betreiberzuweisung nicht auf. */
    public function testEditingKeepsManagerAssignment(): void
    {
        $this->capture();
        MashaFeedlyErrorService::run('cron');
        $entry = MashaFeedlyEntry::get()->exclude('ErrorFingerprint', [null, ''])->first();
        $entry->AssignedMembers()->removeAll();
        $entry->write();
        $this->assertSame([(int)$this->manager->ID], array_map('intval', $entry->AssignedMembers()->column('ID')));
    }

    /** Fehler auf Inhaltsseiten übernehmen deren Pfad, ohne Querystrings oder Geheimnisse zu speichern. */
    public function testCapturesContentPageWithoutQueryString(): void
    {
        $page = $this->objFromFixture(\Page::class, 'frontendTestPage');
        $controller = new \PageController($page);
        $request = new HTTPRequest('GET', 'kontakt?token=vertraulich');
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $controller->pushCurrent();
        try { $this->capture(); }
        finally { $controller->popCurrent(); }
        $path = (glob($this->directory . '/*.pending.json'))[0];
        $raw = (string)file_get_contents($path);
        $batch = json_decode($raw, true);
        $this->assertSame('/kontakt', $batch['pageURL']);
        $this->assertStringNotContainsString('vertraulich', $raw);
        MashaFeedlyErrorService::run('cron');
        $entry = MashaFeedlyEntry::get()->filter('ErrorFingerprint', $batch['fingerprint'])->first();
        $this->assertSame('/kontakt', $entry->PageURL);
    }

    /** Ältere Chargen ohne Seitenadresse und unzulässige fremde Adressen erhalten ein lokales Admin-Ziel. */
    public function testImportAlwaysAssignsLocation(): void
    {
        foreach ([null, '', 'https://foreign.example.test/path', 'javascript:alert(1)'] as $index => $location) {
            $this->capture('Eigener Fehler ' . ['Alpha', 'Beta', 'Gamma', 'Delta'][$index]);
            $path = (glob($this->directory . '/*.pending.json'))[0];
            $batch = json_decode((string)file_get_contents($path), true);
            if ($location === null) unset($batch['pageURL']);
            else $batch['pageURL'] = $location;
            MashaFeedlyErrorHandler::writeJSON($path, $batch);
            MashaFeedlyErrorService::importFile($path, (int)$this->manager->ID);
            $entry = MashaFeedlyEntry::get()->filter('ErrorFingerprint', $batch['fingerprint'])->first();
            $this->assertSame('/admin', $entry->PageURL);
        }
    }

    /** Eine später bekannte Inhaltsseite ersetzt das Admin-Ziel, ohne eine Wiederholungsmail auszulösen. */
    public function testKnownPageReplacesAdminFallback(): void
    {
        $this->capture();
        MashaFeedlyErrorService::run('cron');
        $entry = MashaFeedlyEntry::get()->exclude('ErrorFingerprint', [null, ''])->first();
        $this->assertSame('/admin', $entry->PageURL);
        $this->capture();
        $path = (glob($this->directory . '/*.pending.json'))[0];
        $batch = json_decode((string)file_get_contents($path), true);
        $batch['pageURL'] = '/kontakt';
        MashaFeedlyErrorHandler::writeJSON($path, $batch);
        MashaFeedlyErrorService::importFile($path, (int)$this->manager->ID);
        $this->assertSame('/kontakt', MashaFeedlyEntry::get()->byID($entry->ID)->PageURL);
        $this->assertCount(1, $this->mailer->messages);
    }
}
