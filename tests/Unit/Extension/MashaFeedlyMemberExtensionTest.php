<?php

namespace KW\MashaFeedly\Tests\Unit\Extension;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Extension\MashaFeedlyMemberExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Service\MashaFeedlyAttachmentService;
use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use KW\MashaFeedly\Service\MashaFeedlyFolderService;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Config\Config;
use KW\MashaFeedly\Service\MashaFeedlyEffectProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Security\Member;
use SilverStripe\Security\InheritedPermissions;

/**
 * Tests für Masha-Feedly-Profilfelder, Benachrichtigungen und geschützte Profilbilder.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyMemberExtensionTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedly.yml';

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('de_DE');
    }

    /** Prüft, dass freigegebene Mitglieder die Einstellungen im Profilformular sehen. */
    public function testAllowedMemberSeesEmailPreferencesInProfile(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyEmailTestSucceeded = true;
        $config->write();
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $this->logInAs($member);
        $fields = $member->getCMSFields();
        $mainTab = $fields->findTab('Root.Main');
        $mashaFeedlyTab = $fields->findTab('Root.MashaFeedly');

        $this->assertNotNull($mainTab);
        $this->assertNotNull($mashaFeedlyTab);
        $onboardingGroup = $mashaFeedlyTab->Fields()->fieldByName('MashaFeedlyOnboardingSettings');
        $this->assertInstanceOf(\SilverStripe\Forms\CompositeField::class, $onboardingGroup);
        $this->assertSame('Einführung', $onboardingGroup->Title());
        $onboardingField = $onboardingGroup->getChildren()->dataFieldByName('MashaFeedlyShowOnboarding');
        $this->assertInstanceOf(CheckboxField::class, $onboardingField);
        $this->assertSame('Einführung erneut anzeigen', $onboardingField->Title());
        $completedField = $onboardingGroup->getChildren()->dataFieldByName('MashaFeedlyOnboardingCompleted');
        $this->assertInstanceOf(CheckboxField::class, $completedField);
        $this->assertSame('Einführung abgeschlossen', $completedField->Title());
        $appearanceGroup = $mashaFeedlyTab->Fields()->fieldByName('MashaFeedlyProfileAppearance');
        $this->assertInstanceOf(\SilverStripe\Forms\CompositeField::class, $appearanceGroup);
        $this->assertSame('Darstellung', $appearanceGroup->Title());
        $themeField = $appearanceGroup->getChildren()->dataFieldByName('MashaFeedlyTheme');
        $this->assertInstanceOf(DropdownField::class, $themeField);
        $this->assertSame('Website-Vorgabe', $themeField->getEmptyString());
        // Die Namen werden im Anbieter-CMS gepflegt; das Profil muss beide auswählbaren Kategorien anbieten.
        $themeOptions = $themeField->getSource();
        $this->assertArrayHasKey('playful', $themeOptions);
        $this->assertArrayHasKey('serious', $themeOptions);
        $this->assertNotSame('', trim((string)$themeOptions['playful']));
        $this->assertNotSame('', trim((string)$themeOptions['serious']));
        $this->assertNull($mainTab->Fields()->dataFieldByName('MashaFeedlyOnboardingCompleted'));
        $profileGroup = $mashaFeedlyTab->Fields()->fieldByName('MashaFeedlyProfileSettings');
        $this->assertInstanceOf(\SilverStripe\Forms\CompositeField::class, $profileGroup);
        $imageField = $profileGroup->getChildren()->dataFieldByName('MashaFeedlyIconImage');
        $this->assertInstanceOf(UploadField::class, $imageField);
        $this->assertSame('Dein Masha:Feedly-Avatar', $imageField->Title());
        $this->assertSame('masha-feedly/masha-feedly-profile-images', $imageField->getFolderName());
        $this->assertFalse($imageField->getAttachEnabled());
        $this->assertContains('png', $imageField->getAllowedExtensions());
        $this->assertContains('jpg', $imageField->getAllowedExtensions());
        $colorGroup = $appearanceGroup->getChildren()->fieldByName('MashaFeedlyAvatarColor');
        $this->assertInstanceOf(\SilverStripe\Forms\CompositeField::class, $colorGroup);
        $this->assertInstanceOf(HiddenField::class, $colorGroup->getChildren()->dataFieldByName('MashaFeedlyColor'));
        $palette = $colorGroup->getChildren()->fieldByName('MashaFeedlyColorPalette');
        $this->assertInstanceOf(\SilverStripe\Forms\LiteralField::class, $palette);
        foreach (MashaFeedlyMemberExtension::colorOptions() as $color => $label) {
            $this->assertStringContainsString($color, MashaFeedlyMemberExtension::renderColorPalette());
            $this->assertStringContainsString($label, MashaFeedlyMemberExtension::renderColorPalette());
        }
        $this->assertStringContainsString('masha-feedly-color-palette__swatch', MashaFeedlyMemberExtension::renderColorPalette());
        $this->assertStringContainsString('Automatisch vergeben', MashaFeedlyMemberExtension::renderColorPalette());
        $this->assertStringContainsString('data-color=""', MashaFeedlyMemberExtension::renderColorPalette());
        $this->assertStringContainsString('bestimme, worüber dich Masha:Feedly per E-Mail informiert', $mashaFeedlyTab->Fields()->fieldByName('MashaFeedlyPreferencesIntro')->getContent());
        $emailGroup = $mashaFeedlyTab->Fields()->fieldByName('MashaFeedlyEmailSettings');
        $this->assertInstanceOf(\SilverStripe\Forms\CompositeField::class, $emailGroup);
        $this->assertSame('E-Mail-Benachrichtigungen', $emailGroup->Title());
        $topLevelNames = $mashaFeedlyTab->Fields()->column();
        $this->assertLessThan(array_search('MashaFeedlyProfileAppearance', $topLevelNames, true), array_search('MashaFeedlyProfileSettings', $topLevelNames, true));
        $this->assertLessThan(array_search('MashaFeedlyEmailSettings', $topLevelNames, true), array_search('MashaFeedlyProfileAppearance', $topLevelNames, true));
        $this->assertLessThan(array_search('MashaFeedlyOnboardingSettings', $topLevelNames, true), array_search('MashaFeedlyEmailSettings', $topLevelNames, true));
        $this->assertNull($mainTab->Fields()->dataFieldByName('MashaFeedlyIconImage'));
        $this->assertNull($mainTab->Fields()->dataFieldByName('MashaFeedlyColor'));

        foreach ([
            'MashaFeedlyEmailNotifications',
            'MashaFeedlyNotifyNewEntries',
            'MashaFeedlyNotifyEntryUpdates',
            'MashaFeedlyNotifyOwnEntryChanges',
            'MashaFeedlyNotifyComments',
            'MashaFeedlyNotifyDueDateReminders',
        ] as $fieldName) {
            $this->assertInstanceOf(CheckboxField::class, $fields->dataFieldByName($fieldName));
            $this->assertNull($mainTab->Fields()->dataFieldByName($fieldName));
        }

        $this->assertSame(
            'MashaFeedlyEmailNotifications',
            $mashaFeedlyTab->Fields()->dataFieldByName('MashaFeedlyNotifyNewEntries')->DisplayLogicDispatchers()
        );
        $this->assertSame(
            'MashaFeedlyEmailNotifications',
            $mashaFeedlyTab->Fields()->dataFieldByName('MashaFeedlyNotifyEntryUpdates')->DisplayLogicDispatchers()
        );
        $this->assertSame(
            'MashaFeedlyEmailNotifications',
            $mashaFeedlyTab->Fields()->dataFieldByName('MashaFeedlyNotifyOwnEntryChanges')->DisplayLogicDispatchers()
        );
        $this->assertSame(
            'MashaFeedlyEmailNotifications',
            $mashaFeedlyTab->Fields()->dataFieldByName('MashaFeedlyNotifyComments')->DisplayLogicDispatchers()
        );
        $this->assertStringContainsString('Erhalte eine E-Mail', $mashaFeedlyTab->Fields()->dataFieldByName('MashaFeedlyNotifyNewEntries')->getDescription());
        $this->assertStringContainsString('selbst erstellst oder änderst', $mashaFeedlyTab->Fields()->dataFieldByName('MashaFeedlyNotifyOwnEntryChanges')->getDescription());
        $this->assertStringContainsString('oder den du erstellt hast', $mashaFeedlyTab->Fields()->dataFieldByName('MashaFeedlyNotifyComments')->getDescription());
        $this->assertStringContainsString('Status, Beschreibung, Zuständigkeit', $mashaFeedlyTab->Fields()->dataFieldByName('MashaFeedlyNotifyEntryUpdates')->getDescription());
    }

    /** Sperrt Profilfelder und Änderungen, bis ein Testversand erfolgreich war. */
    public function testEmailPreferencesStayLockedUntilSuccessfulTest(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyEmailTestSucceeded = false;
        $config->write();
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMember($member);
        $config->MashaFeedlyEmailTestSucceeded = true;
        $config->write();
        $member->MashaFeedlyEmailNotifications = true;
        $member->MashaFeedlyNotifyNewEntries = true;
        $member->write();
        $config->MashaFeedlyEmailTestSucceeded = false;
        $config->write();
        $this->logInAs($member);

        $fields = $member->getCMSFields();
        $emailGroup = $fields->findTab('Root.MashaFeedly')->Fields()->fieldByName('MashaFeedlyEmailSettings');
        $mainPreference = $emailGroup->getChildren()->dataFieldByName('MashaFeedlyEmailNotifications');
        $newEntries = $emailGroup->getChildren()->dataFieldByName('MashaFeedlyNotifyNewEntries');
        $this->assertTrue($mainPreference->isDisabled());
        $this->assertTrue($newEntries->isDisabled());
        $this->assertStringContainsString('Test-E-Mail erfolgreich versenden', $mainPreference->getDescription());

        $member->MashaFeedlyEmailNotifications = false;
        $member->MashaFeedlyNotifyNewEntries = false;
        $member->write();
        $reloaded = Member::get()->byID((int)$member->ID);
        $this->assertTrue((bool)$reloaded->MashaFeedlyEmailNotifications);
        $this->assertTrue((bool)$reloaded->MashaFeedlyNotifyNewEntries);

        $config->MashaFeedlyEmailTestSucceeded = true;
        $config->write();
        $fields = $reloaded->getCMSFields();
        $enabledGroup = $fields->findTab('Root.MashaFeedly')->Fields()->fieldByName('MashaFeedlyEmailSettings');
        $this->assertFalse($enabledGroup->getChildren()->dataFieldByName('MashaFeedlyEmailNotifications')->isDisabled());
    }

    /** Prüft, dass Mitglieder ohne Freigabe keine Masha-Feedly-Einstellungen im Profil sehen. */
    public function testMemberWithoutAccessDoesNotSeeEmailPreferencesInProfile(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $otherMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowedMember);
        $this->logInAs($otherMember);
        $fields = $otherMember->getCMSFields();

        $this->assertNull($fields->dataFieldByName('MashaFeedlyEmailNotifications'));
        $this->assertNull($fields->dataFieldByName('MashaFeedlyNotifyNewEntries'));
        $this->assertNull($fields->dataFieldByName('MashaFeedlyNotifyEntryUpdates'));
        $this->assertNull($fields->dataFieldByName('MashaFeedlyNotifyOwnEntryChanges'));
        $this->assertNull($fields->dataFieldByName('MashaFeedlyNotifyComments'));
        $this->assertNull($fields->dataFieldByName('MashaFeedlyShowOnboarding'));
        $this->assertNull($fields->dataFieldByName('MashaFeedlyOnboardingCompleted'));
        $this->assertNull($fields->dataFieldByName('MashaFeedlyIconImage'));
        $this->assertNull($fields->dataFieldByName('MashaFeedlyColor'));
    }

    /** Prüft, dass E-Mails zu eigenen Einträgen standardmäßig ausgeschaltet sind. */
    public function testOwnEntryEmailPreferenceIsDisabledByDefault(): void
    {
        $member = Member::create();

        $this->assertTrue((bool)$member->MashaFeedlyEmailNotifications);
        $this->assertTrue((bool)$member->MashaFeedlyNotifyNewEntries);
        $this->assertTrue((bool)$member->MashaFeedlyNotifyEntryUpdates);
        $this->assertFalse((bool)$member->MashaFeedlyNotifyOwnEntryChanges);
        $this->assertTrue((bool)$member->MashaFeedlyNotifyComments);
    }

    /** Das persönliche Theme überschreibt die Website-Vorgabe; ungültige Werte fallen sicher zurück. */
    public function testPersonalThemeUsesWebsiteDefaultAndAllowsIndependentChoices(): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyTheme = 'playful';
        $config->write();
        $firstMember = $this->objFromFixture(Member::class, 'allowed');
        $secondMember = $this->objFromFixture(Member::class, 'notAllowed');

        $this->assertSame('playful', MashaFeedlyMemberExtension::themeFor($firstMember));
        $this->assertSame('playful', MashaFeedlyMemberExtension::themeFor(null));

        $firstMember->MashaFeedlyTheme = 'serious';
        $firstMember->write();
        $secondMember->MashaFeedlyTheme = 'playful';
        $secondMember->write();

        $this->assertSame('serious', MashaFeedlyMemberExtension::themeFor(Member::get()->byID($firstMember->ID)));
        $this->assertSame('playful', MashaFeedlyMemberExtension::themeFor(Member::get()->byID($secondMember->ID)));

        $firstMember->MashaFeedlyTheme = 'not a valid id!';
        $firstMember->write();
        $this->assertSame('playful', MashaFeedlyMemberExtension::themeFor(Member::get()->byID($firstMember->ID)));
    }

    /** Die Icon-Auswahl erfordert explizite Anbieter-URL und Schlüssel; Avatar-Farben wählen eine Kontrastvariante. */
    public function testAvatarIconPickerRequiresConfiguredProviderAndPicksContrastColor(): void
    {
        $previousURL = Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL');
        $previousKey = Environment::getEnv('MASHA_FEEDLY_EFFECTS_API_KEY');
        $previousBase = MashaFeedlyEffectProvider::config()->get('base_url');
        try {
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', '');
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', '');
            Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', '');
            $member = $this->objFromFixture(Member::class, 'allowed');
            $this->allowMember($member);
            $this->logInAs($member);
            $fields = $member->getCMSFields();
            $profile = $fields->findTab('Root.MashaFeedly')->Fields()->fieldByName('MashaFeedlyProfileSettings');
            $this->assertNull($profile->getChildren()->fieldByName('MashaFeedlyAvatarIconSelection'));
            $this->assertSame('black', MashaFeedlyMemberExtension::iconColorForAvatarColor('#F4D06F'));
            $this->assertSame('white', MashaFeedlyMemberExtension::iconColorForAvatarColor('#C05CC8'));
            $this->assertSame('white', MashaFeedlyMemberExtension::iconColorForAvatarColor('#35A98F'));
            $this->assertSame('white', MashaFeedlyMemberExtension::iconColorForAvatarColor('#69b85a'));
        } finally {
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', $previousURL);
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', $previousKey);
            Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', $previousBase);
        }
    }

    /** Das Icon-Popup zeigt eine Kategorie als Tab und lädt Bildadressen für inaktive Gruppen verzögert. */
    public function testAvatarIconPickerUsesTabsAndDefersInactiveCategoryImages(): void
    {
        $client = new class extends MashaFeedlyEffectClient {
            public function avatarIcons(): array
            {
                return [
                    'categories' => [
                        ['id' => 'people', 'name' => 'Menschen'],
                        ['id' => 'space', 'name' => 'Weltraum'],
                    ],
                    'icons' => [
                        ['id' => 'person', 'name' => 'Person', 'category' => 'people', 'files' => ['black' => '/person-black.svg', 'white' => '/person-white.svg']],
                        ['id' => 'planet', 'name' => 'Planet', 'category' => 'space', 'files' => ['black' => '/planet-black.svg', 'white' => '/planet-white.svg']],
                    ],
                ];
            }
        };
        $extension = new class extends MashaFeedlyMemberExtension {
            public static function renderPickerForTest(string $selectedID, MashaFeedlyEffectClient $client): string
            {
                return parent::renderAvatarIconPicker($selectedID, '#F4D06F', $client);
            }
        };

        $html = $extension::renderPickerForTest('', $client);
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('role="tab"', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('src="/person-black.svg"', $html);
        $this->assertStringNotContainsString('<img src="/planet-black.svg"', $html);
        $this->assertStringContainsString('data-icon-src="/planet-black.svg"', $html);
    }

    /** Bei erreichbarer Konfiguration, aber ausgefallenem Anbieter wird ein Hinweis statt einer leeren Stelle gezeigt. */
    public function testAvatarIconPickerExplainsTemporaryProviderFailure(): void
    {
        $previousBase = Environment::getEnv('MASHA_FEEDLY_EFFECTS_BASE_URL');
        $previousKey = Environment::getEnv('MASHA_FEEDLY_EFFECTS_API_KEY');
        $previousConfigBase = MashaFeedlyEffectProvider::config()->get('base_url');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', 'https://effects.example.test');
        Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', str_repeat('a', 64));
        Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', '');

        $client = new class extends MashaFeedlyEffectClient {
            public function avatarIcons(): array
            {
                throw new \RuntimeException('Interne Verbindungsdetails');
            }
        };
        $extension = new class extends MashaFeedlyMemberExtension {
            public static function renderPickerForTest(MashaFeedlyEffectClient $client): string
            {
                return parent::renderAvatarIconPicker('', null, $client);
            }
        };

        try {
            $html = $extension::renderPickerForTest($client);
            $this->assertStringContainsString('masha-feedly-avatar-icons__unavailable', $html);
            $this->assertStringContainsString('vorübergehend nicht verfügbar', $html);
            $this->assertStringNotContainsString('Interne Verbindungsdetails', $html);
        } finally {
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', $previousBase);
            Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', $previousKey);
            Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', $previousConfigBase);
        }
    }

    /** Erneutes Anzeigen setzt den gespeicherten Abschlussstatus zurück. */
    public function testRequestingOnboardingAgainResetsCompletedState(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $member->MashaFeedlyOnboardingCompleted = true;
        $member->MashaFeedlyShowOnboarding = true;
        $member->write();

        $this->assertFalse((bool)Member::get()->byID((int)$member->ID)->MashaFeedlyOnboardingCompleted);
    }

    /** Prüft, dass ohne Profilbild die Initialen aus Vor- und Nachname angezeigt werden. */
    public function testAvatarFallbackUsesMemberInitials(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');

        $this->assertSame('EM', $member->getMashaFeedlyInitials());

        $member->FirstName = 'Élodie';
        $member->Surname = '';
        $this->assertSame('É', $member->getMashaFeedlyInitials());
    }

    /** Prüft, dass Profilbilder geschützt gespeichert und nur freigegebenen Mitgliedern gezeigt werden. */
    public function testUploadedAvatarIsProtectedAndRestrictedToAllowedMembers(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $blockedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowedMember);
        TestAssetStore::activate('masha-feedly-avatar-security-test');
        $temporaryImage = tempnam(sys_get_temp_dir(), 'masha-feedly-avatar-');
        file_put_contents(
            $temporaryImage,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII=')
        );

        try {
            // Simuliere die bisherige Struktur, in der beide Ordner noch im Asset-Wurzelverzeichnis lagen.
            $legacyAttachmentFolder = Folder::find_or_make('masha-feedly-attachments');
            $legacyProfileFolder = Folder::find_or_make('masha-feedly-profile-images');
            $legacyAttachment = File::create([
                'Name' => 'bestehender-anhaeng.png',
                'ParentID' => (int)$legacyAttachmentFolder->ID,
            ]);
            $legacyAttachment->setFromLocalFile($temporaryImage, 'bestehender-anhaeng.png');
            $legacyAttachment->ParentID = (int)$legacyAttachmentFolder->ID;
            $legacyAttachment->CanViewType = InheritedPermissions::ONLY_THESE_MEMBERS;
            $legacyAttachment->ViewerMembers()->setByIDList([(int)$allowedMember->ID]);
            $legacyAttachment->write();
            $legacyAvatar = Image::create([
                'Name' => 'bestehendes-profilbild.png',
                'ParentID' => (int)$legacyProfileFolder->ID,
            ]);
            $legacyAvatar->setFromLocalFile($temporaryImage, 'bestehendes-profilbild.png');
            $legacyAvatar->ParentID = (int)$legacyProfileFolder->ID;
            $legacyAvatar->CanViewType = InheritedPermissions::ONLY_THESE_MEMBERS;
            $legacyAvatar->ViewerMembers()->setByIDList([(int)$allowedMember->ID]);
            $legacyAvatar->write();
            $allowedMember->MashaFeedlyIconImageID = (int)$legacyAvatar->ID;
            $allowedMember->write();
            $this->logInAs($allowedMember);
            $structure = MashaFeedlyFolderService::ensureStructure();
            $parent = $structure['parent'];
            $this->assertSame('masha-feedly', (string)$parent->Name);
            $this->assertSame('masha-feedly', (string)$parent->Title);
            $this->assertSame(InheritedPermissions::ONLY_THESE_MEMBERS, $parent->CanViewType);
            $this->assertSame([(int)$allowedMember->ID], array_map('intval', $parent->ViewerMembers()->column('ID')));
            foreach ($structure['children'] as $child) {
                $this->assertSame((int)$parent->ID, (int)$child->ParentID);
                $this->assertSame(InheritedPermissions::ONLY_THESE_MEMBERS, $child->CanViewType);
                $this->assertSame([(int)$allowedMember->ID], array_map('intval', $child->ViewerMembers()->column('ID')));
            }
            $movedAttachment = File::get()->byID((int)$legacyAttachment->ID);
            $movedAvatar = Image::get()->byID((int)$legacyAvatar->ID);
            $this->assertSame((int)$structure['children']['masha-feedly-attachments']->ID, (int)$movedAttachment->ParentID);
            $this->assertSame((int)$structure['children']['masha-feedly-profile-images']->ID, (int)$movedAvatar->ParentID);
            $this->assertTrue($movedAvatar->canView($allowedMember), 'MOVED avatar parent=' . $movedAvatar->Parent()->Name . ' canUse=' . (int)MashaFeedlyConfigExtension::canUse($allowedMember) . ' type=' . $movedAvatar->CanViewType . ' viewers=' . implode(',', $movedAvatar->ViewerMembers()->column('ID')));
            $this->assertFalse($movedAvatar->canView($blockedMember));
            $this->logOut();
            $this->assertFalse($movedAvatar->canView());
            $folder = MashaFeedlyMemberExtension::protectedIconFolder();
            $image = Image::create([
                'Name' => 'profilbild.png',
                'ParentID' => (int)$folder->ID,
            ]);
            $image->setFromLocalFile($temporaryImage, 'profilbild.png');
            $image->ParentID = (int)$folder->ID;
            $image->write();
            $allowedMember->MashaFeedlyIconImageID = (int)$image->ID;
            $allowedMember->write();

            $savedImage = Image::get()->byID($image->ID);
            $protectedPath = TestAssetStore::getLocalPath($savedImage, true);
            $assetStore = Injector::inst()->get(AssetStore::class);
            $fileID = $assetStore->getFileID($savedImage->getFilename(), $savedImage->getHash());
            $publicPath = TestAssetStore::base_path() . '/' . $fileID;

            $this->assertSame(InheritedPermissions::ONLY_THESE_MEMBERS, $savedImage->CanViewType);
            $this->assertSame([(int)$allowedMember->ID], array_map('intval', $savedImage->ViewerMembers()->column('ID')));
            $this->assertGreaterThan(0, $savedImage->ViewerMembers()->filter('ID', (int)$allowedMember->ID)->count());
            $this->logInAs($allowedMember);
            $this->assertTrue(MashaFeedlyConfigExtension::canUse($allowedMember));
            $this->assertSame('masha-feedly-profile-images', (string)$savedImage->Parent()->Name);
            $this->assertSame('masha-feedly', (string)$savedImage->Parent()->Parent()->Name);
            $this->assertTrue($savedImage->canView());
            $this->logOut();
            $this->assertFalse($savedImage->canView($blockedMember));
            $this->assertFalse($savedImage->canView());
            $this->assertFileExists($protectedPath);
            $this->assertFileDoesNotExist($publicPath);
        } finally {
            TestAssetStore::reset();
            unlink($temporaryImage);
        }
    }

    /** Prüft Upload-Whitelist, Speicherung und Zugriffsschutz der Eintragsanhänge. */
    public function testEntryAttachmentsAreValidatedAndOnlyVisibleToAllowedMembers(): void
    {
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $blockedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMember($allowedMember);
        TestAssetStore::activate('masha-feedly-attachment-test');
        $temporaryImage = tempnam(sys_get_temp_dir(), 'masha-feedly-attachment-');
        file_put_contents($temporaryImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/8ioAAAAASUVORK5CYII='));
        $upload = ['name' => 'layout.png', 'type' => 'image/png', 'tmp_name' => $temporaryImage, 'error' => UPLOAD_ERR_OK, 'size' => filesize($temporaryImage)];

        try {
            $this->assertNull(MashaFeedlyAttachmentService::validateUploads($upload));
            $blockedUpload = $upload;
            $blockedUpload['name'] = 'payload.exe';
            $this->assertStringContainsString('Erlaubt sind Bilder', MashaFeedlyAttachmentService::validateUploads($blockedUpload));
            $spoofedUpload = $upload;
            $spoofedUpload['name'] = 'dokument.pdf';
            $this->assertStringContainsString('Dateityp passt nicht', MashaFeedlyAttachmentService::validateUploads($spoofedUpload));
            $oversizedUpload = $upload;
            $oversizedUpload['size'] = 10_485_761;
            $this->assertStringContainsString('höchstens 10 MB', MashaFeedlyAttachmentService::validateUploads($oversizedUpload));

            MashaFeedlyCategory::ensureDefaultCategories();
            $entry = MashaFeedlyEntry::create(['Content' => 'Testeintrag', 'CategoryID' => MashaFeedlyCategory::defaultCategory()->ID]);
            $entry->write();
            $attachments = MashaFeedlyAttachmentService::attachUploads($upload, $entry);
            $this->assertCount(1, $attachments);
            $this->assertSame('layout.png', (string)$attachments[0]->OriginalName);
            $file = File::get()->byID((int)$attachments[0]->FileID);
            $this->assertNotNull($file);
            $this->assertSame('masha-feedly-attachments', (string)$file->Parent()->Name);
            $this->assertSame('masha-feedly', (string)$file->Parent()->Parent()->Name);
            $this->assertTrue($file->canView($allowedMember));
            $this->assertFalse($file->canView($blockedMember));
            $this->logOut();
            $this->assertFalse($file->canView());
        } finally {
            TestAssetStore::reset();
            unlink($temporaryImage);
        }
    }

    /** Prüft Farbpalette, automatische Vergabe und den sicheren Farb-Fallback des Avatar-Rahmens. */
    public function testMemberAvatarColorUsesPaletteAndAutomaticAssignment(): void
    {
        $colors = array_keys(MashaFeedlyMemberExtension::colorOptions());
        $member = $this->objFromFixture(Member::class, 'allowed');

        $this->assertCount(18, $colors);
        $this->assertSame($colors[0], MashaFeedlyMemberExtension::nextAvailableColor());
        $this->assertSame($colors[1], MashaFeedlyMemberExtension::nextAvailableColor([$colors[0]]));
        $this->assertSame($colors[0], MashaFeedlyMemberExtension::normalizeColor(strtolower($colors[0])));
        $this->assertNull(MashaFeedlyMemberExtension::normalizeColor('#123456'));

        $member->MashaFeedlyColor = '#123456';
        $this->assertSame($colors[0], $member->getMashaFeedlyDisplayColor());
        $member->MashaFeedlyColor = '';
        $member->write();
        $stored = Member::get()->byID($member->ID);
        $this->assertContains($stored->MashaFeedlyColor, $colors);
        $automaticColor = $stored->MashaFeedlyColor;
        $stored->write();
        $this->assertSame($automaticColor, Member::get()->byID($member->ID)->MashaFeedlyColor);
        $stored->MashaFeedlyColor = '#C05CC8';
        $stored->MashaFeedlyAvatarIcon = 'person';
        $stored->write();
        $this->assertSame('#C05CC8', Member::get()->byID($member->ID)->MashaFeedlyColor);
        $preview = $stored->renderAvatarPreview();
        $this->assertStringContainsString('background-color:#C05CC8', $preview);
        $this->assertStringContainsString('/avatar/person/white', $preview);
    }

    /** Schreibt die Testfreigabe in die Konfiguration, ohne produktive Mitglieder anzulegen. */
    private function allowMember(Member $member): void
    {
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([(int)$member->ID]);
        $config->write();
    }
}
