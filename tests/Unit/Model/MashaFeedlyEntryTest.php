<?php

namespace KW\MashaFeedly\Tests\Unit\Model;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Tests für das Masha-Feedly-Eintragsdatenobjekt.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyEntryTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedly.yml';

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('de_DE');
    }

    /** Prüft, dass die Eintragsfelder im Datenobjekt gesetzt werden. */
    public function testEntryHasExpectedFields(): void
    {
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');

        $this->assertSame('Ein Inhalt', $entry->Content);
        $this->assertSame('Ein Inhalt', $entry->Title);
        $this->assertSame('2026-10-01 09:15:00', $entry->EntryDate);
        $this->assertSame('Backlog', $entry->Category()->Title);
    }

    /** Prüft, dass ein neuer Eintrag ohne gewählten Status in Backlog eingeordnet wird. */
    public function testNewEntryGetsBacklogCategoryByDefault(): void
    {
        $entry = MashaFeedlyEntry::create([
            'Content' => 'Ein neuer Bug ohne manuell gewählten Status.',
            'EntryDate' => '2026-10-01',
        ]);
        $entry->write();

        $this->assertNotEmpty($entry->CategoryID);
        $this->assertSame('Backlog', $entry->Category()->Title);
    }

    /** Prüft, dass Datum und Uhrzeit im CMS angeboten und beim Speichern automatisch gesetzt werden. */
    public function testEntryDateDefaultsToCurrentDateAndTime(): void
    {
        DBDatetime::set_mock_now('2026-10-01 10:34:56');
        try {
            $entry = MashaFeedlyEntry::create([
                'Content' => 'Ein Eintrag ohne manuelles Datum.',
            ]);
            $dateField = $entry->getCMSFields()->dataFieldByName('EntryDate');
            $this->assertInstanceOf(DatetimeField::class, $dateField);
            $this->assertSame('2026-10-01T12:34:56', $dateField->Value());
            $entry->write();
            $this->assertSame('2026-10-01 12:34:56', $entry->EntryDate);
        } finally {
            DBDatetime::clear_mock_now();
        }
    }

    /** Prüft, dass ein nur mit Datum übermittelter neuer Eintrag die aktuelle Uhrzeit erhält. */
    public function testNewEntryWithDateOnlyGetsCurrentTime(): void
    {
        DBDatetime::set_mock_now('2026-10-01 10:34:56');
        try {
            $entry = MashaFeedlyEntry::create([
                'Content' => 'Ein Eintrag mit Datum ohne Uhrzeit.',
                'EntryDate' => '2026-10-01',
            ]);
            $entry->write();

            $this->assertSame('2026-10-01 12:34:56', $entry->EntryDate);
        } finally {
            DBDatetime::clear_mock_now();
        }
    }

    /** Fälligkeit lässt sich im CMS setzen, wird protokolliert und setzt die Erinnerung nach Änderungen zurück. */
    public function testDueDateFieldAndHistoryResetReminder(): void
    {
        $entry = MashaFeedlyEntry::create(['Content' => 'Ein Termin mit Verlauf.', 'EntryDate' => '2026-10-01', 'DueDate' => '2026-10-10']);
        $dueDateField = $entry->getCMSFields()->dataFieldByName('DueDate');
        $this->assertInstanceOf(DateField::class, $dueDateField);
        $entry->write();

        $entry->DueDateReminderSentAt = '2026-10-09 07:00:00';
        $entry->write();
        $entry->DueDate = '2026-10-12';
        $entry->write();

        $history = MashaFeedlyEntryHistory::get()->filter(['EntryID' => (int)$entry->ID, 'ChangeType' => 'due_date'])->first();
        $this->assertNotNull($history);
        $this->assertSame('2026-10-10', (string)$history->OldValue);
        $this->assertSame('2026-10-12', (string)$history->NewValue);
        $this->assertEmpty($entry->DueDateReminderSentAt);
    }

    /** Prüft, dass die Sichtbarkeit anhand der Freigabe des aktuellen Benutzers ermittelt wird. */
    public function testCanViewUsesTheConfiguredMember(): void
    {
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $otherMember = $this->objFromFixture(Member::class, 'notAllowed');
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([$allowedMember->ID]);
        $siteConfig->write();

        $this->assertTrue($entry->canView($allowedMember));
        $this->assertFalse($entry->canView($otherMember));
        $this->assertSame('Sichtbar', $entry->getVisibilityLabelFor($allowedMember));
        $this->assertSame('Nicht sichtbar', $entry->getVisibilityLabelFor($otherMember));
    }

    /** Prüft, dass nur ausdrücklich freigegebene Mitglieder zugeordnet werden können. */
    public function testAssignmentOptionsAndValidationRequireMashaFeedlyAccess(): void
    {
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        $allowedMember = $this->objFromFixture(Member::class, 'allowed');
        $additionalAllowedMember = $this->objFromFixture(Member::class, 'notAllowed');
        $blockedMember = $this->objFromFixture(Member::class, 'normalize');
        $siteConfig = MashaFeedlyConfigExtension::currentSiteConfig();
        $siteConfig->MashaFeedlyAllowedMemberIDs = json_encode([
            (int)$allowedMember->ID,
            (int)$additionalAllowedMember->ID,
        ]);
        $siteConfig->write();

        $field = $entry->getCMSFields()->dataFieldByName('AssignedMembers');
        $this->assertInstanceOf(ListboxField::class, $field);
        $this->assertArrayHasKey((string)$allowedMember->ID, $field->getSource());
        $this->assertArrayHasKey((string)$additionalAllowedMember->ID, $field->getSource());
        $this->assertArrayNotHasKey((string)$blockedMember->ID, $field->getSource());

        $field->setValue([(string)$allowedMember->ID, (string)$additionalAllowedMember->ID]);
        $field->saveInto($entry);
        $this->assertTrue($entry->validate()->isValid());
        $entry->write();
        $assignedMemberIDs = array_map(
            'intval',
            MashaFeedlyEntry::get()->byID($entry->ID)->AssignedMembers()->column('ID')
        );
        sort($assignedMemberIDs);
        $expectedMemberIDs = [(int)$allowedMember->ID, (int)$additionalAllowedMember->ID];
        sort($expectedMemberIDs);
        $this->assertSame($expectedMemberIDs, $assignedMemberIDs);

        $field->setValue([(string)$allowedMember->ID, (string)$blockedMember->ID]);
        $field->saveInto($entry);
        $validation = $entry->validate();
        $this->assertFalse($validation->isValid());
        $this->assertSame('AssignedMembers', $validation->getMessages()[0]['fieldName']);
    }

    /** Eine konfigurierte E-Mail allein reicht ohne SilverStripe-ADMIN-Recht nicht für die Melder-Auswahl. */
    public function testAllowlistedNonAdminCannotSeeOrChangeReportedBy(): void
    {
        Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', ['allowed@example.test']);
        $allowlistedNonAdmin = $this->objFromFixture(Member::class, 'allowed');
        $reporter = $this->objFromFixture(Member::class, 'notAllowed');

        $this->assertFalse(MashaFeedlyEntry::canManageReporter($allowlistedNonAdmin));
        Security::setCurrentUser($allowlistedNonAdmin);
        $entry = MashaFeedlyEntry::create(['Content' => 'Manuell übertragener älterer Eintrag']);
        $entry->write();
        $creatorID = $entry->creatorMemberID();
        $field = $entry->getCMSFields()->dataFieldByName('ReportedByID');
        $this->assertNull($field);
        $entry->ReportedByID = (int)$reporter->ID;
        $entry->write();

        $this->assertSame($creatorID, $entry->creatorMemberID());
        $this->assertSame(0, (int)$entry->ReportedByID);
        $this->assertSame(0, MashaFeedlyEntryHistory::get()->filter([
            'EntryID' => (int)$entry->ID,
            'ChangeType' => 'reported_by',
        ])->count());
    }
}
