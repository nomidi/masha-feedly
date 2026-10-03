<?php

namespace KW\MashaFeedly\Tests\Unit\Model;

use KW\MashaFeedly\Admin\MashaFeedlyAdmin;
use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use KW\MashaFeedly\Model\MashaFeedlyPriority;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Tests für benutzerspezifisch ungelesene Masha-Feedly-Einträge und -Änderungen.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyEntryReadTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedly.yml';

    /** Prüft, dass Lesen nur für das aktuelle Mitglied zählt und Änderungen erneut als ungelesen gelten. */
    public function testUnreadCountIsPrivatePerMemberAndReopensAfterAnUpdate(): void
    {
        $firstMember = $this->objFromFixture(Member::class, 'allowed');
        $secondMember = $this->objFromFixture(Member::class, 'notAllowed');
        $firstMember->MashaFeedlyEmailNotifications = false;
        $firstMember->write();
        $secondMember->MashaFeedlyEmailNotifications = false;
        $secondMember->write();

        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode([
            (int)$firstMember->ID,
            (int)$secondMember->ID,
        ]);
        $config->write();

        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $this->assertSame(1, MashaFeedlyEntryRead::unreadCount($firstMember));
        $this->assertSame(1, MashaFeedlyEntryRead::unreadCount($secondMember));

        $this->logInAs($firstMember);
        $this->assertSame('Masha:Feedly (1/0)', MashaFeedlyAdmin::menu_title());
        $entry->getCMSFields();
        $this->assertSame(0, MashaFeedlyEntryRead::unreadCount($firstMember));
        $this->assertSame(1, MashaFeedlyEntryRead::unreadCount($secondMember));
        $this->assertSame('Masha:Feedly', MashaFeedlyAdmin::menu_title());

        $this->logInAs($secondMember);
        $entry->Content = 'Der Eintrag wurde nach dem Lesen geändert.';
        $entry->write();

        $this->assertSame(1, MashaFeedlyEntryRead::unreadCount($firstMember));
        $this->assertSame(0, MashaFeedlyEntryRead::unreadCount($secondMember));
        $this->assertSame('Masha:Feedly', MashaFeedlyAdmin::menu_title());
    }

    /** Prüft, dass ungelesene IDs nur bei Änderungen am relevanten Eintragsstand wechseln. */
    public function testUnreadEntryIDsTrackContentDateCategoryAndPriorityButIgnoreSort(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMembers([$member]);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');

        MashaFeedlyEntryRead::markAsSeen($entry, $member);
        $this->assertSame([], MashaFeedlyEntryRead::unreadEntryIDs($member));

        Security::setCurrentUser(null);
        $entry->Sort = (int)$entry->Sort + 10;
        $entry->write();
        $this->assertSame([], MashaFeedlyEntryRead::unreadEntryIDs($member));

        $entry->Content = 'Geänderte Bug-Beschreibung.';
        $entry->write();
        $this->assertSame([(int)$entry->ID], MashaFeedlyEntryRead::unreadEntryIDs($member));

        MashaFeedlyEntryRead::markAsSeen($entry, $member);
        $entry->EntryDate = '2026-10-02 10:15:00';
        $entry->write();
        $this->assertSame([(int)$entry->ID], MashaFeedlyEntryRead::unreadEntryIDs($member));

        MashaFeedlyEntryRead::markAsSeen($entry, $member);
        MashaFeedlyCategory::ensureDefaultCategories();
        $category = MashaFeedlyCategory::get()->filter('Title', 'To Do')->first();
        $entry->CategoryID = (int)$category->ID;
        $entry->write();
        $this->assertSame([(int)$entry->ID], MashaFeedlyEntryRead::unreadEntryIDs($member));

        MashaFeedlyEntryRead::markAsSeen($entry, $member);
        MashaFeedlyPriority::ensureDefaultPriorities();
        $entry->PriorityID = (int)MashaFeedlyPriority::get()->filter('Title', 'Sofort bearbeiten')->first()->ID;
        $entry->write();
        $this->assertSame([(int)$entry->ID], MashaFeedlyEntryRead::unreadEntryIDs($member));

        MashaFeedlyEntryRead::markAsSeen($entry, $member);
        $entry->AssignedMembers()->setByIDList([(int)$member->ID]);
        $this->assertSame([(int)$entry->ID], MashaFeedlyEntryRead::unreadEntryIDs($member));
    }

    /** Prüft, dass nicht gespeicherte Einträge ignoriert und Lesestände aktualisiert statt dupliziert werden. */
    public function testMarkAsSeenIgnoresUnsavedEntriesAndReusesExistingReadRecord(): void
    {
        $member = $this->objFromFixture(Member::class, 'allowed');
        $this->allowMembers([$member]);
        $unsavedEntry = MashaFeedlyEntry::create(['Content' => 'Noch nicht gespeichert']);

        MashaFeedlyEntryRead::markAsSeen($unsavedEntry, $member);
        $this->assertSame(0, MashaFeedlyEntryRead::get()->filter('MemberID', (int)$member->ID)->count());

        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        MashaFeedlyEntryRead::markAsSeen($entry, $member);
        $entry->Content = 'Ein aktualisierter gelesener Stand.';
        $entry->write();
        MashaFeedlyEntryRead::markAsSeen($entry, $member);

        $this->assertSame(1, MashaFeedlyEntryRead::get()->filter([
            'MemberID' => (int)$member->ID,
            'EntryID' => (int)$entry->ID,
        ])->count());
        $this->assertSame([], MashaFeedlyEntryRead::unreadEntryIDs($member));
    }

    /** Prüft, dass allgemeine und persönlich zugeordnete neue Einträge getrennt gezählt werden. */
    public function testUnreadCountsSeparateGeneralAndPersonalEntries(): void
    {
        $firstMember = $this->objFromFixture(Member::class, 'allowed');
        $secondMember = $this->objFromFixture(Member::class, 'notAllowed');
        $this->allowMembers([$firstMember, $secondMember]);
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');

        $this->assertSame(['general' => 1, 'personal' => 0], MashaFeedlyEntryRead::unreadCounts($firstMember));

        Security::setCurrentUser(null);
        $entry->AssignedMembers()->setByIDList([(int)$firstMember->ID]);
        $this->assertSame(['general' => 0, 'personal' => 1], MashaFeedlyEntryRead::unreadCounts($firstMember));
        $this->assertSame(['general' => 0, 'personal' => 0], MashaFeedlyEntryRead::unreadCounts($secondMember));

        $entry->AssignedMembers()->setByIDList([(int)$firstMember->ID, (int)$secondMember->ID]);
        $this->assertSame(['general' => 0, 'personal' => 1], MashaFeedlyEntryRead::unreadCounts($firstMember));
        $this->assertSame(['general' => 0, 'personal' => 1], MashaFeedlyEntryRead::unreadCounts($secondMember));

        $entry->AssignedMembers()->setByIDList([(int)$secondMember->ID]);
        $this->assertSame(['general' => 0, 'personal' => 0], MashaFeedlyEntryRead::unreadCounts($firstMember));
        $this->assertSame(['general' => 0, 'personal' => 1], MashaFeedlyEntryRead::unreadCounts($secondMember));
        Security::setCurrentUser($secondMember);
        $this->assertSame('Masha:Feedly (0/1)', MashaFeedlyAdmin::menu_title());
        Security::setCurrentUser(null);
    }

    /** Prüft, dass neue Kommentare für Ersteller und Zuständige, aber nicht für andere Personen ungelesen sind. */
    public function testCommentActivityIsUnreadForCreatorAndAssigneesOnly(): void
    {
        $creator = $this->objFromFixture(Member::class, 'allowed');
        $assignee = $this->objFromFixture(Member::class, 'notAllowed');
        $unrelated = Member::create(['FirstName' => 'Unbeteiligt', 'Surname' => 'Person', 'Email' => 'unrelated@example.test']);
        $unrelated->write();
        $this->allowMembers([$creator, $assignee, $unrelated]);

        MashaFeedlyCategory::ensureDefaultCategories();
        $category = MashaFeedlyCategory::defaultCategory();
        Security::setCurrentUser($creator);
        $entry = MashaFeedlyEntry::create([
            'Content' => 'Ein Eintrag mit Kommentaraktivität',
            'CategoryID' => (int)$category->ID,
        ]);
        $entry->write();
        $entry->AssignedMembers()->setByIDList([(int)$assignee->ID]);
        foreach ([$creator, $assignee, $unrelated] as $member) {
            foreach (MashaFeedlyEntry::get() as $visibleEntry) {
                MashaFeedlyEntryRead::markAsSeen($visibleEntry, $member);
            }
        }

        MashaFeedlyEntryHistory::record($entry, 'comment', '', 'Neue Rückfrage', $assignee, 123);

        $this->assertSame([(int)$entry->ID], MashaFeedlyEntryRead::unreadEntryIDs($creator));
        $this->assertSame([(int)$entry->ID], MashaFeedlyEntryRead::unreadEntryIDs($assignee));
        $this->assertSame([], MashaFeedlyEntryRead::unreadEntryIDs($unrelated));
        $this->assertSame(['general' => 0, 'personal' => 1], MashaFeedlyEntryRead::unreadCounts($creator));
        $this->assertSame(['general' => 0, 'personal' => 1], MashaFeedlyEntryRead::unreadCounts($assignee));
        $this->assertSame(['general' => 0, 'personal' => 0], MashaFeedlyEntryRead::unreadCounts($unrelated));
        Security::setCurrentUser(null);
    }

    /** Speichert Testmitglieder in der Freigabeliste und schaltet ihre E-Mail-Versandoptionen aus. */
    private function allowMembers(array $members): void
    {
        $memberIDs = [];
        foreach ($members as $member) {
            $member->MashaFeedlyEmailNotifications = false;
            $member->write();
            $memberIDs[] = (int)$member->ID;
        }
        $config = MashaFeedlyConfigExtension::currentSiteConfig();
        $config->MashaFeedlyAllowedMemberIDs = json_encode($memberIDs);
        $config->write();
    }
}
