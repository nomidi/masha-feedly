<?php

namespace KW\MashaFeedly\Extension;

use SilverStripe\Assets\File;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Member;

/**
 * Begrenzt den Abruf von Masha-Feedly-Profilbildern auf freigegebene Mitglieder.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyProfileImageExtension extends Extension
{
    /** Prüft den Modulzugriff für Profilbilder im geschützten Masha-Feedly-Ordner. */
    public function canView(?Member $member = null): ?bool
    {
        if (!($this->owner instanceof File)) {
            return null;
        }

        $folder = $this->owner->Parent();
        if (!$folder || !in_array((string)$folder->Name, ['masha-feedly-profile-images', 'masha-feedly-attachments'], true)) {
            return null;
        }

        return MashaFeedlyConfigExtension::canUse($member);
    }
}
