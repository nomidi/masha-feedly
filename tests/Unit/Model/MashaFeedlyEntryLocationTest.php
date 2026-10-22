<?php

namespace KW\MashaFeedly\Tests\Unit\Model;

use KW\MashaFeedly\Admin\MashaFeedlyAdmin;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

/** Prüft den verpflichtenden Ort und anklickbare CMS-Ziele einschließlich älterer technischer Meldungen. */
class MashaFeedlyEntryLocationTest extends SapphireTest
{
    protected static $fixture_file = '../../fixtures/MashaFeedly.yml';

    /** Ein ausdrücklich leerer Ort verhindert das Speichern im Modell. */
    public function testCannotSaveWithoutLocation(): void
    {
        Config::modify()->set(DataObject::class, 'validation_enabled', true);
        $entry = MashaFeedlyEntry::create(['Content' => 'Ohne Ort', 'PageURL' => '']);
        $this->assertFalse($entry->validate()->isValid());
        $this->expectException(ValidationException::class);
        $entry->write();
    }

    /** Hintergrundmeldungen ohne ausdrücklich angegebenen Ort erhalten eine gültige CMS-Vorgabe. */
    public function testDefaultLocationIsAdmin(): void
    {
        $entry = MashaFeedlyEntry::create(['Content' => 'Hintergrundmeldung']);
        $this->assertSame('/admin', $entry->PageURL);
        $this->assertTrue($entry->validate()->isValid());
        $entry->deferNewEntryNotification();
        $entry->write();
        $this->assertSame('/admin', MashaFeedlyEntry::get()->byID($entry->ID)->PageURL);
    }

    /** Unsichere oder nicht anklickbare Adressen gelten nicht als Ort. */
    public function testRejectsUnsafeLocations(): void
    {
        foreach (['', ' ', 'javascript:alert(1)', '//example.test/path', '/\\example.test/path', 'https://user:secret@example.test/path', 'keine-adresse'] as $location) {
            $entry = MashaFeedlyEntry::create(['Content' => 'Ungültiger Ort', 'PageURL' => $location]);
            $this->assertFalse($entry->validate()->isValid(), $location);
        }
        foreach (['/', '/admin', '/kontakt', 'https://example.test/kontakt', 'http://feedly.example.test:8443/kontakt'] as $location) {
            $this->assertTrue(MashaFeedlyEntry::isValidPageURL($location), $location);
        }
    }

    /** Relative Seiten und ältere technische Meldungen bekommen im CMS einen echten Link mit Eintragskennung. */
    public function testBoardLinksRelativeAndLegacyLocations(): void
    {
        $method = new \ReflectionMethod(MashaFeedlyAdmin::class, 'frontendEntryURL');
        foreach (['/kontakt' => '/kontakt', '' => '/admin'] as $location => $expectedPath) {
            $entry = MashaFeedlyEntry::create(['ID' => 321, 'PageURL' => $location, 'ErrorFingerprint' => hash('sha256', 'Test')]);
            $url = $method->invoke(MashaFeedlyAdmin::singleton(), $entry);
            $this->assertSame($expectedPath, parse_url($url, PHP_URL_PATH));
            parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame('321', $query['masha-feedly-entry']);
        }
    }

    /** Die Altdatenkorrektur ergänzt den Ort ohne Änderungen am Verlauf. */
    public function testRepairsExistingMissingLocations(): void
    {
        $entry = $this->objFromFixture(MashaFeedlyEntry::class, 'visibleEntry');
        DB::prepared_query('UPDATE "MashaFeedlyEntry" SET "PageURL" = ? WHERE "ID" = ?', ['', (int)$entry->ID]);
        $historyCount = $entry->History()->count();
        $entry->requireDefaultRecords();
        $stored = MashaFeedlyEntry::get()->byID($entry->ID);
        $this->assertSame('/admin', $stored->PageURL);
        $this->assertSame($historyCount, $stored->History()->count());
    }
}
