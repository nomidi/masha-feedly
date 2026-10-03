<?php

declare(strict_types=1);

$bootstrapCandidates = [
    __DIR__ . '/../vendor/silverstripe/framework/tests/bootstrap.php',
    __DIR__ . '/../../vendor/silverstripe/framework/tests/bootstrap.php',
];

foreach ($bootstrapCandidates as $bootstrap) {
    if (is_file($bootstrap)) {
        require_once $bootstrap;
        return;
    }
}

throw new RuntimeException('SilverStripe PHPUnit bootstrap was not found in the module or project vendor directory.');
