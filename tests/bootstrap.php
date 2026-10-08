<?php

declare(strict_types=1);

// Silverstripe 4 liest den Flush-Schalter aus argv, nicht aus SS_PHPUNIT_FLUSH.
// PHPUnit hat seine Optionen bereits verarbeitet, bevor dieser Bootstrap läuft.
if (getenv('SS_PHPUNIT_FLUSH') === '1') {
    $_SERVER['argv'][] = 'flush=1';
}

// Nur ein eigenständiger Modul-Checkout benötigt die minimalen Seitenklassen.
// In einem Hostprojekt dürfen keine Fixtures angelegt werden: Dessen Klassen
// können in beliebigen Unterordnern liegen und sind vor dem Framework-Boot
// noch nicht zwingend über class_exists() auffindbar.
if (realpath(BASE_PATH) === realpath(__DIR__ . '/..')) {
    $appSource = BASE_PATH . '/app/src';
    if (!is_file($appSource . '/Page.php') || !is_file($appSource . '/PageController.php')) {
        if (!is_dir($appSource)) {
            mkdir($appSource, 0775, true);
        }
    }
    if (!is_file($appSource . '/Page.php')) {
        copy(__DIR__ . '/fixtures/Page.php.fixture', $appSource . '/Page.php');
    }
    if (!is_file($appSource . '/PageController.php')) {
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
