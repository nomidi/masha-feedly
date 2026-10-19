<?php

declare(strict_types=1);

namespace KW\MashaFeedly\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Prüft Feedly ohne ladbares Connect; der separate Prozess lädt weder Anwendung noch Datenbank. */
class MashaFeedlyStandaloneTest extends TestCase
{
    public static function modes(): iterable
    {
        yield 'lokal ohne Anbieter' => ['standalone', true, 503];
        yield 'gemeinsamer Zugang ohne Connect fällt nicht auf gültige Altwerte zurück' => ['shared-missing', false, 403];
    }

    #[DataProvider('modes')]
    public function testOptionalConnectIsNotRequiredAtRuntime(string $mode, bool $allowed, int $errorCode): void
    {
        $module = dirname(__DIR__, 3);
        $autoload = is_file($module . '/vendor/autoload.php')
            ? $module . '/vendor/autoload.php' : dirname($module) . '/vendor/autoload.php';
        $process = proc_open([PHP_BINARY, $module . '/tests/fixtures/standalone-provider.php', $autoload, $mode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($result['connectAvailable']);
        self::assertFalse($result['configuredProvider']);
        self::assertSame($allowed, $result['contentAllowed']);
        self::assertSame($errorCode, $result['errorCode']);
    }

    public function testComposerDoesNotRequireConnect(): void
    {
        $package = json_decode(file_get_contents(dirname(__DIR__, 3) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('kooperativeweb/masha-connect', $package['require']);
        self::assertArrayHasKey('kooperativeweb/masha-connect', $package['suggest']);
    }
}
