<?php

namespace KW\MashaFeedly\Tests\Unit\Extension;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Tests für Freigabeprüfung und Bereinigung der Benutzerliste.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyConfigExtensionTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedlyConfig.yml';

    /** Polling startet nur nach Aktivierung; fehlerhafte Intervalle fallen auf fünf Minuten zurück. */
    public function testPollingDefaultsAndIntervals(): void
    {
        $fresh = SiteConfig::create();
        $this->assertFalse((bool)$fresh->MashaFeedlyPollingEnabled);
        $this->assertSame(5, (int)$fresh->MashaFeedlyPollingMinutes);
        foreach ([0 => 5, 1 => 5, 5 => 5, 7 => 5, 10 => 10, 60 => 60, 65 => 5] as $input => $expected) {
            $this->assertSame($expected, MashaFeedlyConfigExtension::normalizePollingMinutes($input));
        }
    }

    /** Stellt sicher, dass SiteConfig-Felder physisch angelegt sind und regulär gelesen werden können. */
    public function testThemeFieldExistsInSiteConfigSchema(): void
    {
        $fields = DB::get_schema()->fieldList('SiteConfig');

        $this->assertArrayHasKey('MashaFeedlyTheme', $fields);
        $this->assertArrayHasKey('MashaFeedlyFontSize', $fields);
        $this->assertArrayHasKey('MashaFeedlyDueDateReminderMode', $fields);
        $this->assertArrayHasKey('MashaFeedlyDueDateReminderLastRunDate', $fields);
        $this->assertArrayHasKey('MashaFeedlyEmailTestSucceeded', $fields);
        foreach (array_merge(...array_values(MashaFeedlyConfigExtension::notificationFieldMap())) as $notificationField) {
            $this->assertArrayHasKey($notificationField, $fields);
        }
        $this->assertFalse(MashaFeedlyConfigExtension::emailTestSucceeded());

        // Eine echte ORM-Abfrage deckt fehlende Spalten auf, die eine reine Config-Prüfung übersieht.
        $siteConfig = SiteConfig::create();
        $siteConfig->MashaFeedlyTheme = 'serious';
        $siteConfig->write();
        $reloaded = SiteConfig::get()->byID($siteConfig->ID);
        $this->assertNotNull($reloaded);
        $this->assertSame('serious', $reloaded->MashaFeedlyTheme);
    }

    /** Übernimmt Anbietervorlagen nur in leere Felder und erhält lokale Anpassungen. */
    public function testEffectNotificationDefaultsFillOnlyEmptyFields(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyNotificationNewEntryDu = '<p>Eigener Text</p>';
        $config->MashaFeedlyNotificationNewEntrySie = '';
        $config->write();
        $defaults = [
            'newEntry' => ['du' => '<p>Provider Du</p>', 'sie' => '<p>Provider Sie</p>'],
            'updatedEntry' => ['du' => '<p>Update Du</p>', 'sie' => '<p>Update Sie</p>'],
        ];

        $this->assertTrue(MashaFeedlyConfigExtension::fillEmptyNotificationTexts($config, $defaults));
        $this->assertSame('<p>Eigener Text</p>', $config->MashaFeedlyNotificationNewEntryDu);
        $this->assertSame('<p>Provider Sie</p>', $config->MashaFeedlyNotificationNewEntrySie);
        $this->assertSame('<p>Update Du</p>', $config->MashaFeedlyNotificationUpdatedEntryDu);
        $this->assertSame('<p>Update Sie</p>', $config->MashaFeedlyNotificationUpdatedEntrySie);
    }

    /** Der dev/build-Hook übernimmt Anbietertexte nach dem Schemaaufbau in leere Felder. */
    public function testBuildHookImportsProviderTextsIntoEmptyFields(): void
    {
        $previousBase = Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL');
        $previousKey = Environment::getEnv('MASHA_FEEDLY_EFFECTS_API_KEY');
        $providerConfig = \KW\MashaFeedly\Service\MashaFeedlyEffectProvider::config();
        $previousLocalBase = $providerConfig->get('local_provider_base_url');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', '');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', str_repeat('a', 64));
        $providerConfig->set('local_provider_base_url', rtrim(\SilverStripe\Control\Director::absoluteURL('/'), '/'));
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        foreach (array_merge(...array_values(MashaFeedlyConfigExtension::notificationFieldMap())) as $field) {
            $config->$field = '';
        }
        $config->write();

        $texts = [];
        foreach (MashaFeedlyConfigExtension::notificationFieldMap() as $type => $addresses) {
            foreach ($addresses as $address => $_field) {
                $texts[$type][$address] = '<p>' . $type . ' ' . $address . '</p>';
            }
        }
        $injector = Injector::inst();
        $originalClient = $injector->get(MashaFeedlyEffectClient::class);
        $client = new class($texts) extends MashaFeedlyEffectClient {
            public function __construct(private array $texts) {}
            public function emailDefaults(bool $refresh = false): array
            {
                return ['version' => 1, 'footer' => '', 'welcome' => $this->texts['welcome'], 'notifications' => $this->texts];
            }
        };
        $injector->registerService($client, MashaFeedlyEffectClient::class);
        try {
            $config->requireDefaultRecords();
            $saved = SiteConfig::get()->byID($config->ID);
            foreach (MashaFeedlyConfigExtension::notificationFieldMap() as $type => $addresses) {
                foreach ($addresses as $address => $field) {
                    $this->assertSame('<p>' . $type . ' ' . $address . '</p>', (string)$saved->$field);
                }
            }
        } finally {
            $injector->registerService($originalClient, MashaFeedlyEffectClient::class);
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', $previousBase);
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', $previousKey);
            $providerConfig->set('local_provider_base_url', $previousLocalBase);
        }
    }

    /** Leere Footer nutzen die Schnittstelle; eigene Angaben und lokale Vorgaben bleiben vorrangig. */
    public function testEmptyFooterUsesProviderWithoutOverwritingSavedText(): void
    {
        $previousFooter = Environment::getEnv('MASHA_FEEDLY_EMAIL_FOOTER');
        $previousBase = Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL');
        $previousKey = Environment::getEnv('MASHA_FEEDLY_EFFECTS_API_KEY');
        $provider = \KW\MashaFeedly\Service\MashaFeedlyEffectProvider::config();
        $previousLocalBase = $provider->get('local_provider_base_url');
        Environment::setEnv('MASHA_FEEDLY_EMAIL_FOOTER', '');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', '');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', str_repeat('a', 64));
        $provider->set('local_provider_base_url', rtrim(\SilverStripe\Control\Director::absoluteURL('/'), '/'));
        $injector = Injector::inst();
        $originalClient = $injector->get(MashaFeedlyEffectClient::class);
        $client = new class extends MashaFeedlyEffectClient {
            public int $calls = 0;
            public bool $fail = false;
            public function __construct() {}
            public function emailDefaults(bool $refresh = false): array
            {
                $this->calls++;
                if ($this->fail) throw new \RuntimeException('provider_unavailable');
                return ['footer' => "Example Provider\nExample Street 1"];
            }
        };
        $injector->registerService($client, MashaFeedlyEffectClient::class);
        try {
            $config = MashaFeedlyConfigExtension::currentSiteConfig();
            $config->MashaFeedlyEmailFooter = ''; $config->write();
            $this->assertSame("Example Provider\nExample Street 1", MashaFeedlyConfigExtension::emailFooter());
            $this->assertSame('', (string)SiteConfig::get()->byID($config->ID)->MashaFeedlyEmailFooter, 'Lesen allein speichert keine CMS-Einstellung.');
            $config->MashaFeedlyEmailFooter = 'Eigene Footerangaben'; $config->write();
            $calls = $client->calls;
            $this->assertSame('Eigene Footerangaben', MashaFeedlyConfigExtension::emailFooter());
            $this->assertSame($calls, $client->calls);
            Environment::setEnv('MASHA_FEEDLY_EMAIL_FOOTER', 'Lokale Vorgabe\\nZweite Zeile');
            $this->assertSame("Lokale Vorgabe\nZweite Zeile", MashaFeedlyConfigExtension::defaultEmailFooter());
            $this->assertSame($calls, $client->calls);
            Environment::setEnv('MASHA_FEEDLY_EMAIL_FOOTER', '');
            $client->fail = true;
            $this->assertSame('', MashaFeedlyConfigExtension::defaultEmailFooter());
            $this->assertSame('Eigene Footerangaben', MashaFeedlyConfigExtension::emailFooter());
        } finally {
            $injector->registerService($originalClient, MashaFeedlyEffectClient::class);
            Environment::setEnv('MASHA_FEEDLY_EMAIL_FOOTER', $previousFooter);
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', $previousBase);
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', $previousKey);
            $provider->set('local_provider_base_url', $previousLocalBase);
        }
    }

    /** Benachrichtigungstexte werden passend zur persönlichen Du-/Sie-Auswahl gelesen. */
    public function testNotificationTextUsesRequestedAddressVariant(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyNotificationNewCommentDu = '<p>Du-Version</p>';
        $config->MashaFeedlyNotificationNewCommentSie = '<p>Sie-Version</p>';
        $config->write();

        $this->assertSame('<p>Du-Version</p>', MashaFeedlyConfigExtension::notificationEmailText('newComment', 'du'));
        $this->assertSame('<p>Sie-Version</p>', MashaFeedlyConfigExtension::notificationEmailText('newComment', 'sie'));
        $this->assertSame('', MashaFeedlyConfigExtension::notificationEmailText('unknown', 'du'));
    }

    /** Prüft, dass Auswahl Zugriff erteilt und Entfernen ihn wieder entzieht. */
    public function testSelectedMembersCanUseMashaFeedly(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $otherMember = $this->objFromFixture(Member::class, 'notAllowed');

        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([$allowedMember->ID]);
        $siteConfig->write();

        $this->assertTrue(MashaFeedlyConfigExtension::canUse($allowedMember));
        $this->assertFalse(MashaFeedlyConfigExtension::canUse($otherMember));
        $this->assertFalse(MashaFeedlyConfigExtension::canUse(null));
        $this->assertSame([(int)$allowedMember->ID], MashaFeedlyConfigExtension::memberIDs());

        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([]);
        $siteConfig->write();
        $this->assertFalse(MashaFeedlyConfigExtension::canUse($allowedMember));
    }

    /** Die Widget-Freigabe vergleicht konfigurierte IDs direkt; erst die vollständige Liste prüft Mitglieder in der Datenbank. */
    public function testQuickAccessCheckUsesConfiguredIDsAndFullListStillRemovesUnknownMembers(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([
            (int)$allowedMember->ID,
            999999,
            (int)$allowedMember->ID,
            'ungültig',
        ]);
        $siteConfig->write();

        $this->assertTrue(MashaFeedlyConfigExtension::canUse($allowedMember));
        $this->assertTrue(MashaFeedlyConfigExtension::isExplicitlyAllowed($allowedMember));
        $this->assertSame([(int)$allowedMember->ID], MashaFeedlyConfigExtension::memberIDs());
    }

    /** Jede neue Freigabe setzt E-Mail-Vorgaben zurück und sendet unabhängig davon die Willkommensmail. */
    public function testAddingAndReaddingMemberResetsNotificationPreferencesAndWelcomesThem(): void
    {
        $member = $this->objFromFixture(Member::class, 'normalize');
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = '[]';
        $config->write();
        $member->MashaFeedlyEmailNotifications = false;
        $member->MashaFeedlyEmailAssignedOnly = false;
        $member->MashaFeedlyNotifyNewEntries = false;
        $member->MashaFeedlyNotifyEntryUpdates = false;
        $member->MashaFeedlyNotifyComments = false;
        $member->MashaFeedlyNotifyDueDateReminders = false;
        $member->MashaFeedlyNotifyCostEstimates = false;
        $member->write();

        $mailer = new class implements MailerInterface {
            /** @var RawMessage[] Gespeicherte Willkommensnachrichten. */
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
            foreach ([1, 2] as $attempt) {
                $config->MashaFeedlyAllowedMemberIDs = json_encode([$member->ID]);
                $config->write();

                $saved = Member::get()->byID((int)$member->ID);
                foreach ([
                    'MashaFeedlyEmailNotifications',
                    'MashaFeedlyEmailAssignedOnly',
                    'MashaFeedlyNotifyNewEntries',
                    'MashaFeedlyNotifyEntryUpdates',
                    'MashaFeedlyNotifyComments',
                    'MashaFeedlyNotifyDueDateReminders',
                    'MashaFeedlyNotifyCostEstimates',
                ] as $field) {
                    $this->assertTrue((bool)$saved->$field, $field . ' wird beim Hinzufügen eingeschaltet.');
                }
                $this->assertFalse((bool)$saved->MashaFeedlyNotifyOwnEntryChanges);
                $this->assertCount($attempt, $mailer->messages);

                if ($attempt === 1) {
                    $config->MashaFeedlyAllowedMemberIDs = '[]';
                    $config->write();
                    foreach ([
                        'MashaFeedlyEmailNotifications',
                        'MashaFeedlyEmailAssignedOnly',
                        'MashaFeedlyNotifyNewEntries',
                        'MashaFeedlyNotifyEntryUpdates',
                        'MashaFeedlyNotifyComments',
                        'MashaFeedlyNotifyDueDateReminders',
                        'MashaFeedlyNotifyCostEstimates',
                    ] as $field) {
                        $member->$field = false;
                    }
                    $member->write();
                }
            }
        } finally {
            $injector->registerService($originalMailer, MailerInterface::class);
        }
    }

    /** Prüft, dass doppelte, ungültige und unbekannte IDs entfernt werden. */
    public function testSubmittedMemberIDsAreNormalized(): void
    {
        $member = $this->objFromFixture(Member::class, 'normalize');

        $this->assertSame(
            [(int)$member->ID],
            MashaFeedlyConfigExtension::normalizeMemberIDs([
                $member->ID,
                (string)$member->ID,
                -1,
                99999999,
                'not-a-user',
            ])
        );
    }

    /** Prüft Anredeauswahl und sicheren Du-Fallback bei leeren oder ungültigen Werten. */
    public function testConfiguredAddressSupportsDuAndSie(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAddress = 'du';
        $siteConfig->write();
        $this->assertSame('du', MashaFeedlyConfigExtension::address());

        $siteConfig->MashaFeedlyAddress = 'sie';
        $siteConfig->write();
        $this->assertSame('sie', MashaFeedlyConfigExtension::address());

        $siteConfig->MashaFeedlyAddress = 'xxx';
        $siteConfig->write();
        $this->assertSame('du', MashaFeedlyConfigExtension::address());
    }

    /** Schriftgröße akzeptiert nur drei Stufen und fällt bei Altbestand sicher auf Klein zurück. */
    public function testConfiguredFontSizeSupportsThreePresets(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        foreach (['small', 'medium', 'large'] as $size) {
            $siteConfig->MashaFeedlyFontSize = $size;
            $siteConfig->write();
            $this->assertSame($size, MashaFeedlyConfigExtension::fontSize());
        }

        $siteConfig->MashaFeedlyFontSize = 'unexpected';
        $siteConfig->write();
        $this->assertSame('small', MashaFeedlyConfigExtension::fontSize());
    }

    /** Anbieter-Kategorien bleiben erhalten; leere oder syntaktisch ungültige Kennungen verwenden Verspielt. */
    public function testConfiguredThemeSupportsProviderCategoriesWithPlayfulFallback(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyTheme = '';
        $siteConfig->write();
        $this->assertSame('playful', MashaFeedlyConfigExtension::theme(), 'Eine nicht konfigurierte Website-Vorgabe startet immer verspielt.');
        foreach (['playful', 'serious', 'seasons', 'provider-category'] as $theme) {
            $siteConfig->MashaFeedlyTheme = $theme;
            $siteConfig->write();
            $this->assertSame($theme, MashaFeedlyConfigExtension::theme());
        }

        $siteConfig->MashaFeedlyTheme = 'invalid/category';
        $siteConfig->write();
        $this->assertSame('playful', MashaFeedlyConfigExtension::theme());
    }

    /** Auswahl der server- oder besuchergetriggerten Fälligkeitserinnerungen mit Cron-Fallback testen. */
    public function testDueDateReminderModeSupportsCronAndWebsiteVisits(): void
    {
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        foreach (['cron', 'visitor'] as $mode) {
            $siteConfig->MashaFeedlyDueDateReminderMode = $mode;
            $siteConfig->write();
            $this->assertSame($mode, MashaFeedlyConfigExtension::dueDateReminderMode());
        }

        $siteConfig->MashaFeedlyDueDateReminderMode = 'unexpected';
        $siteConfig->write();
        $this->assertSame('cron', MashaFeedlyConfigExtension::dueDateReminderMode());
    }
}
