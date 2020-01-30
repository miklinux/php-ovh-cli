<?php

use GetOpt\GetOpt;
use GetOpt\Option;
use GetOpt\Command;
use GetOpt\ArgumentException;
use GetOpt\ArgumentException\Missing;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\Config as CacheConfig;
use Phpfastcache\Core\phpFastCache;
use OvhCli\Ovh;
use OvhCli\Cli;

require_once __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL ^ E_NOTICE);
define('CONFIG_FILE', getenv("HOME").'/.ovh-cli.config.json');
define('COMMAND_PATH', __DIR__ . '/src/Command');

// set cache
CacheManager::setDefaultConfig(new CacheConfig([
    "path" => sys_get_temp_dir(),
    "itemDetailedDate" => false
]));

$cacheManager = CacheManager::getInstance('files');
Ovh::setCacheManager($cacheManager);

$getOpt = new GetOpt();
$getOpt->addOptions([
    Option::create('?', 'help', GetOpt::NO_ARGUMENT)
        ->setDescription('Show this help and quit'),
    Option::create('t', 'dry-run', GetOpt::NO_ARGUMENT)
        ->setDescription('Will fake PUT/POST/DELETE requests'),
    Option::create('g', 'grep', GetOpt::NO_ARGUMENT)
        ->setDescription('Greppable output'),
    Option::create('n', 'no-cache', GetOpt::NO_ARGUMENT)
        ->setDescription('Disable cache')
]);

$commands = [];
// glob() doesn't work with PHAR, so use iterators
$directory = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator(COMMAND_PATH)
);
// commands autoloading
foreach($directory as $file) {
    if (!preg_match('/\.php$/', $file)) {
        continue;
    }
    $relativePath = substr($file, strlen(COMMAND_PATH) + 1);
    $classSuffix = str_replace(['.php',DIRECTORY_SEPARATOR], ['','\\'], $relativePath);
    $class = '\\OvhCli\\Command\\' . $classSuffix;
    $command = new $class;
    $name = $command->getName();
    $commands[$name] = $command;
}
// I like commands to be in alphabetical order :)
ksort($commands);
$getOpt->addCommands($commands);

try {
    try {
        $getOpt->process();
    } catch (Missing $exception) {
        if (!$getOpt->getOption('help')) {
            throw $exception;
        }
    }
} catch (ArgumentException $exception) {
    file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
    echo PHP_EOL . $getOpt->getHelpText();
    exit;
}

// show help and quit
$command = $getOpt->getCommand();
if (!$command || $getOpt->getOption('help')) {
    echo $getOpt->getHelpText();
    exit;
}

if ($getOpt->getOption('dry-run')) {
    Ovh::setDryRun(true);
    Cli::warning("running in DRY-RUN mode");
}

if ($getOpt->getOption('no-cache')) {
    Ovh::disableCache();
    Cli::warning("cache is disabled");
}

// call the requested command
call_user_func($command->getHandler(), $getOpt);
