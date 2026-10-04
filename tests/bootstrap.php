<?php

declare(strict_types=1);

// SilverStripe CMS expects applications to define Page and PageController. In a
// standalone module checkout, expose minimal app fixtures for ClassManifest too.
if (!class_exists('Page')) {
    $appSource = getcwd() . '/app/src';
    if (!is_file($appSource . '/Page.php')) {
        if (!is_dir($appSource)) {
            mkdir($appSource, 0775, true);
        }
        copy(__DIR__ . '/fixtures/Page.php.fixture', $appSource . '/Page.php');
    }
    if (!is_file($appSource . '/PageController.php')) {
        if (!is_dir($appSource)) {
            mkdir($appSource, 0775, true);
        }
        copy(__DIR__ . '/fixtures/PageController.php.fixture', $appSource . '/PageController.php');
    }
}

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
