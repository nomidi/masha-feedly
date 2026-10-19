<?php

declare(strict_types=1);

// Eigener Prozess ohne Framework-Boot und ohne Connect-Autoloader, auch wenn Connect im Host installiert ist.
$loader = require $argv[1];
$loader->unregister();
spl_autoload_register(static function (string $class) use ($loader): void {
    if (str_starts_with($class, 'KW\\MashaConnect\\')) return;
    $prefix = 'KW\\MashaFeedly\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
        return;
    }
    $loader->loadClass($class);
});

use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use KW\MashaFeedly\Service\MashaFeedlyEffectProvider;
use SilverStripe\Config\Collections\MemoryConfigCollection;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\ConfigLoader;
use SilverStripe\Core\Environment;

ConfigLoader::inst()->pushManifest(new MemoryConfigCollection());
$shared = $argv[2] === 'shared-missing';
Environment::setEnv('MASHA_API_KEY', $shared ? str_repeat('a', 64) : '');
Environment::setEnv('MASHA_PROVIDER_URL', 'https://provider.example.test');
Environment::setEnv('MASHA_FEEDLY_EFFECTS_API_KEY', $shared ? str_repeat('b', 64) : '');
Environment::setEnv('MASHA_FEEDLY_EFFECTS_BASE_URL', 'https://legacy.example.test');
Config::modify()->set(MashaFeedlyEffectProvider::class, 'base_url', '');
Config::modify()->set(MashaFeedlyEffectProvider::class, 'local_provider_base_url', '');

$client = (new ReflectionClass(MashaFeedlyEffectClient::class))->newInstanceWithoutConstructor();
$errorCode = null;
try { $client->requireConfiguredProvider(); }
catch (RuntimeException $error) { $errorCode = $error->getCode(); }

echo json_encode([
    'connectAvailable' => class_exists('KW\\MashaConnect\\Connection'),
    'configuredProvider' => MashaFeedlyEffectClient::hasConfiguredProvider(),
    'contentAllowed' => MashaFeedlyEffectClient::providerContentAllowed(),
    'errorCode' => $errorCode,
], JSON_THROW_ON_ERROR);
