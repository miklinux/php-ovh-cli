#!/usr/bin/php
<?php

$pharFile = '/tmp/ovh.phar';

if (posix_getuid() == 0) {
  $finalDir = '/usr/local/bin';
} else {
  $finalDir = getenv('HOME') . '/bin';
  if (!file_exists($finalDir)) {
    @mkdir($finalDir);
  }
  if (!is_dir($finalDir)) {
    die("Unable to create directory $finalDir!\n");
  }
  $finalFile = $finalDir;
}
$finalFile = $finalDir . '/ovh-cli';

// clean up
if (file_exists($pharFile)) 
{
    unlink($pharFile);
}

if (file_exists($pharFile . '.gz')) 
{
    unlink($pharFile . '.gz');
}

// create phar
$phar = new Phar($pharFile);

// start buffering. Mandatory to modify stub to add shebang
$phar->startBuffering();

// Create the default stub from main.php entrypoint
$defaultStub = $phar->createDefaultStub('cli.php');

// Add the rest of the apps files
$phar->buildFromDirectory(__DIR__, '/^((?!\.git).)*$/');

// Customize the stub to add the shebang
$stub = "#!/usr/bin/php \n" . $defaultStub;

// Add the stub
$phar->setStub($stub);

$phar->stopBuffering();

// plus - compressing it into gzip  
$phar->compressFiles(Phar::GZ);

# Make the file executable
chmod($pharFile, 0755);
rename($pharFile, $finalFile);

echo "$finalFile has been successfully created!" . PHP_EOL;
