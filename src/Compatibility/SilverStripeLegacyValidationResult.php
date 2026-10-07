<?php

declare(strict_types=1);

// Silverstripe 4 stellt ValidationResult unter SilverStripe\ORM bereit.
if (!class_exists(\SilverStripe\Core\Validation\ValidationResult::class)
    && class_exists(\SilverStripe\ORM\ValidationResult::class)
) {
    class_alias(
        \SilverStripe\ORM\ValidationResult::class,
        \SilverStripe\Core\Validation\ValidationResult::class
    );
}
