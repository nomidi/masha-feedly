<?php

namespace KW\MashaFeedly\Tests\Unit\Model;

use KW\MashaFeedly\Model\MashaFeedlyPriority;
use SilverStripe\Dev\SapphireTest;

class MashaFeedlyPriorityTest extends SapphireTest
{
    public function testPriorityIconsAreStaticAndCmsCanSelectIconAndColor(): void
    {
        $priority = MashaFeedlyPriority::create([
            'Title' => 'Info',
            'Description' => 'Ein Hinweis',
            'Sort' => 50,
            'Color' => '#4285c7',
            'IconType' => 'info',
        ]);

        $this->assertStringContainsString('viewBox="0 0 512 512"', $priority->getIconSVG());
        $this->assertStringNotContainsString('#4285c7', $priority->getIconSVG());
        $fields = $priority->getCMSFields();
        $iconField = $fields->dataFieldByName('IconType');
        $this->assertNotNull($iconField);
        $this->assertSame(['warning' => 'Warnsymbol', 'info' => 'Info-Symbol'], $iconField->getSource());
        $this->assertNotNull($fields->dataFieldByName('Color'));

        $priority->IconType = 'warning';
        $this->assertStringContainsString('viewBox="0 0 24 24"', $priority->getIconSVG());
    }

    public function testDefaultSetupKeepsAdminCustomizationsAfterInitialCreation(): void
    {
        MashaFeedlyPriority::ensureDefaultPriorities();
        $priority = MashaFeedlyPriority::get()->filter('Title', 'Normal')->first();
        $priority->Color = '#123456';
        $priority->Description = 'Eigene Beschreibung';
        $priority->write();

        MashaFeedlyPriority::ensureDefaultPriorities();

        $reloaded = MashaFeedlyPriority::get()->byID($priority->ID);
        $this->assertSame('#123456', (string)$reloaded->Color);
        $this->assertSame('Eigene Beschreibung', (string)$reloaded->Description);
    }

    /** Alle fünf Prioritäten sind erforderliche, nicht löschbare Systemwerte und fehlende werden ergänzt. */
    public function testAllRequiredPrioritiesAreRestoredAndRenamesArePreserved(): void
    {
        MashaFeedlyPriority::ensureDefaultPriorities();
        $requiredKeys = ['critical', 'high', 'normal', 'low', 'info'];
        foreach ($requiredKeys as $key) {
            $priority = MashaFeedlyPriority::get()->filter('SystemKey', $key)->first();
            $this->assertNotNull($priority, 'Fehlende Systempriorität: ' . $key);
            $this->assertFalse($priority->canDelete(), 'Systempriorität darf nicht löschbar sein: ' . $key);
        }

        $critical = MashaFeedlyPriority::get()->filter('SystemKey', 'critical')->first();
        $criticalID = (int)$critical->ID;
        $critical->Title = 'Blockierend';
        $critical->write();
        MashaFeedlyPriority::get()->filter('SystemKey', 'info')->first()->delete();
        MashaFeedlyPriority::ensureDefaultPriorities();

        $this->assertSame(1, MashaFeedlyPriority::get()->filter('SystemKey', 'info')->count());
        $this->assertSame(1, MashaFeedlyPriority::get()->filter('SystemKey', 'critical')->count());
        $this->assertSame($criticalID, (int)MashaFeedlyPriority::get()->filter('SystemKey', 'critical')->first()->ID);
        $this->assertSame('Blockierend', (string)MashaFeedlyPriority::get()->filter('SystemKey', 'critical')->first()->Title);
    }
}
