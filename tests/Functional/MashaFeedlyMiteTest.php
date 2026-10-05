<?php

namespace KW\MashaFeedly\Tests\Functional;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyMiteTrigger;
use KW\MashaFeedly\Service\MashaFeedlyMiteClient;
use KW\MashaFeedly\Service\MashaFeedlyMiteService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/** Prüft den geschützten Mite-Ablauf mit simulierten API-Antworten und ohne Feedly-Zeitdatensätze. */
class MashaFeedlyMiteTest extends FunctionalTest
{
    protected static $fixture_file = '../fixtures/MashaFeedly.yml';

    private MockHandler $httpResponses;
    /** @var array<int, array<string, mixed>> Simulierte ausgehende API-Anfragen. */
    private array $requests = [];
    /** @var array<string, mixed> Ursprüngliche Umgebungswerte. */
    private array $previousEnvironment = [];
    private MashaFeedlyMiteClient $originalClient;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['MASHA_FEEDLY_MITE_API_KEY' => 'test-key-only', 'MASHA_FEEDLY_MITE_ACCOUNT' => 'unit-test'] as $name => $value) {
            $this->previousEnvironment[$name] = Environment::getEnv($name);
            Environment::setEnv($name, $value);
        }
        $this->httpResponses = new MockHandler();
        $stack = HandlerStack::create($this->httpResponses);
        $stack->push(Middleware::history($this->requests));
        $injector = Injector::inst();
        $this->originalClient = $injector->get(MashaFeedlyMiteClient::class);
        $injector->registerService(new MashaFeedlyMiteClient(new Client(['handler' => $stack])), MashaFeedlyMiteClient::class);
        MashaFeedlyCategory::ensureDefaultCategories();
    }

    protected function tearDown(): void
    {
        Injector::inst()->registerService($this->originalClient, MashaFeedlyMiteClient::class);
        foreach ($this->previousEnvironment as $name => $value) Environment::setEnv($name, $value);
        parent::tearDown();
    }

    /** Andere CMS-Admins können Optionen weder lesen noch Timer starten oder stoppen. */
    public function testOnlyConfiguredManagerCanAccessMiteEndpoints(): void
    {
        $this->logInWithPermission('ADMIN');
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', ['someone-else@example.test']);
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)Security::getCurrentUser()->ID]);
        $config->MashaFeedlyMiteEnabled = true;
        $config->write();
        $page = $this->objFromFixture(\Page::class, 'frontendTestPage');
        $page->publishRecursive();
        $widget = $this->get('/masha-feedly-widget-test')->getBody();
        $this->assertStringNotContainsString('data-masha-feedly-open-mite', $widget);
        $this->assertSame(403, $this->get('/__masha-feedly/miteOptions')->getStatusCode());
        foreach (['startMiteTimer', 'stopMiteTimer'] as $action) {
            $response = $this->post('/__masha-feedly/' . $action, ['SecurityID' => SecurityToken::getSecurityID()]);
            $this->assertSame(403, $response->getStatusCode());
        }
        $this->assertCount(0, $this->requests);
    }

    /** Der Manager sieht Optionen und Timerstatus; das Feedly-Widget bietet allgemeines Stoppen. */
    public function testManagerOptionsAndWidgetMarkup(): void
    {
        $this->logInManager();
        $this->projectResponse();
        $this->serviceResponse();
        $this->respond(['tracker' => ['tracking_time_entry' => ['id' => 777]]]);
        $this->respond(['time_entry' => ['id' => 777, 'note' => 'Aktuelle Arbeit']]);
        $data = json_decode($this->get('/__masha-feedly/miteOptions')->getBody(), true);
        $this->assertSame([123 => 'Kunde / Testprojekt'], $data['projects']);
        $this->assertSame([789 => 'Entwicklung'], $data['services']);
        $this->assertSame(777, $data['activeTimerID']);
        $this->assertSame('Aktuelle Arbeit', $data['activeTimerNote']);
        $this->assertStringNotContainsString('test-key-only', json_encode($data));
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)Security::getCurrentUser()->ID]);
        $config->write();
        $page = $this->objFromFixture(\Page::class, 'frontendTestPage');
        $page->publishRecursive();
        $widget = $this->get('/masha-feedly-widget-test')->getBody();
        $this->assertStringContainsString('data-masha-feedly-open-mite', $widget);
        $this->assertStringContainsString('data-widget-mite-stop', $widget);
    }

    /** Beim Start wird Leistung und vollständige Fehlerbeschreibung an Mite gesendet, nicht in Feedly abgelegt. */
    public function testStartSendsServiceAndDescriptionWithoutFeedlyTracking(): void
    {
        $this->logInManager();
        $entry = $this->entry();
        $entry->CategoryID = (int)MashaFeedlyCategory::get()->filter('SystemKey', 'doing')->first()->ID;
        $entry->Content = '<p>Erster Teil</p><p>Zweiter &amp; voller Teil</p>';
        $entry->PageURL = 'https://example.test/fehler';
        $entry->write();
        $this->projectResponse();
        $this->serviceResponse();
        $this->respond(['tracker' => []]);
        $this->respond(['time_entry' => ['id' => 456]], 201);
        $this->respond(['tracker' => ['tracking_time_entry' => ['id' => 456]]]);
        $response = $this->post('/__masha-feedly/startMiteTimer', [
            'SecurityID' => SecurityToken::getSecurityID(), 'EntryID' => (int)$entry->ID,
            'ProjectID' => 123, 'ServiceID' => 789, 'ConfirmedTimerID' => 0,
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(456, json_decode($response->getBody(), true)['timeEntryID']);
        $payload = json_decode((string)$this->requests[3]['request']->getBody(), true)['time_entry'];
        $this->assertSame(789, $payload['service_id']);
        $this->assertSame('Masha:Feedly #' . $entry->ID . ": Erster Teil\nZweiter & voller Teil\nhttps://example.test/fehler", $payload['note']);
        $this->assertFalse(class_exists('KW\\MashaFeedly\\Model\\MashaFeedlyMiteTimeTracking'));
    }

    /** Stoppen prüft erneut die Timer-ID und sendet DELETE an Mite. */
    public function testStopRequiresConfirmedRunningTimer(): void
    {
        $this->logInManager();
        $this->respond(['tracker' => ['tracking_time_entry' => ['id' => 777]]]);
        $this->httpResponses->append(new Response(204));
        $response = $this->post('/__masha-feedly/stopMiteTimer', [
            'SecurityID' => SecurityToken::getSecurityID(), 'ConfirmedTimerID' => 777,
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('DELETE', $this->requests[1]['request']->getMethod());
        $this->assertSame('/tracker/777.json', (string)$this->requests[1]['request']->getUri()->getPath());
    }

    /** Der allgemeine Widget-Button kann einen Timer ohne Feedly-Eintragsbezug starten. */
    public function testGeneralStartCreatesOnlyAMiteEntry(): void
    {
        $this->logInManager();
        $this->projectResponse();
        $this->serviceResponse();
        $this->respond(['tracker' => []]);
        $this->respond(['time_entry' => ['id' => 654]], 201);
        $this->respond(['tracker' => ['tracking_time_entry' => ['id' => 654]]]);
        $response = $this->post('/__masha-feedly/startMiteTimer', [
            'SecurityID' => SecurityToken::getSecurityID(), 'ProjectID' => 123,
            'ServiceID' => 789, 'ConfirmedTimerID' => 0,
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$this->requests[3]['request']->getBody(), true)['time_entry'];
        $this->assertSame('Masha:Feedly – allgemeine Zeiterfassung', $payload['note']);
        $this->assertSame(789, $payload['service_id']);
        $this->assertArrayNotHasKey('EntryID', $payload);
        $this->assertFalse(class_exists('KW\\MashaFeedly\\Model\\MashaFeedlyMiteTimeTracking'));
    }

    /** Ein abweichender Timerzustand verhindert Start oder Stop ohne erneute Bestätigung. */
    public function testStaleTimerConfirmationIsRejected(): void
    {
        $this->logInManager();
        $this->respond(['tracker' => ['tracking_time_entry' => ['id' => 888]]]);
        $response = $this->post('/__masha-feedly/stopMiteTimer', [
            'SecurityID' => SecurityToken::getSecurityID(), 'ConfirmedTimerID' => 777,
        ]);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertCount(1, $this->requests);
    }

    /** Schaltet die Integration mit Triggerkategorien ein und aus, ohne eine Mite-Leistung festzulegen. */
    public function testConfigurationStoresProjectAndTriggerCategoriesOnly(): void
    {
        $this->logInManager(false);
        $doingID = (int)MashaFeedlyCategory::get()->filter('SystemKey', 'doing')->first()->ID;
        $this->projectResponse();
        Injector::inst()->get(MashaFeedlyMiteService::class)->saveConfiguration(true, 123, [$doingID]);
        $this->assertTrue(MashaFeedlyConfigExtension::miteEnabled());
        $this->assertSame([$doingID], MashaFeedlyConfigExtension::miteCategoryIDs());
        $this->assertSame(123, (int)MashaFeedlyConfigExtension::currentSiteConfig()->MashaFeedlyMiteProjectID);
    }

    private function logInManager(bool $activate = true): void
    {
        $this->logInWithPermission('ADMIN');
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [(string)Security::getCurrentUser()->Email]);
        if (!$activate) return;
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyMiteEnabled = true;
        $config->MashaFeedlyMiteProjectID = 123;
        $config->write();
        MashaFeedlyMiteTrigger::create([
            'SiteConfigID' => (int)$config->ID,
            'CategoryID' => (int)MashaFeedlyCategory::get()->filter('SystemKey', 'doing')->first()->ID,
        ])->write();
    }

    private function entry(): MashaFeedlyEntry
    {
        return MashaFeedlyEntry::get()->byID($this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry')->ID);
    }

    private function projectResponse(): void
    {
        $this->respond([['project' => ['id' => 123, 'name' => 'Testprojekt', 'customer_name' => 'Kunde', 'archived' => false]]]);
    }

    private function serviceResponse(): void
    {
        $this->respond([['service' => ['id' => 789, 'name' => 'Entwicklung', 'archived' => false]]]);
    }

    private function respond(array $data, int $status = 200): void
    {
        $this->httpResponses->append(new Response($status, ['Content-Type' => 'application/json'], json_encode($data)));
    }
}
