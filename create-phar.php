#!/usr/bin/php
<?php
$pharFile = '/tmp/ovh-cli.phar';

if (0 == posix_getuid()) {
  $finalDir = '/usr/local/bin';
} else {
  $finalDir = getenv('HOME').'/bin';
  if (!file_exists($finalDir)) {
    @mkdir($finalDir);
  }
  if (!is_dir($finalDir)) {
    exit("Unable to create directory {$finalDir}!\n");
  }
  $finalFile = $finalDir;
}
$finalFile = $finalDir.'/ovh-cli';

foreach ([$pharFile, $pharFile.'.gz'] as $file) {
  if (file_exists($file)) {
    unlink($file);
  }
}

$phar = new Phar($pharFile);
$phar->startBuffering();
$defaultStub = $phar->createDefaultStub('cli.php');
$phar->buildFromDirectory(__DIR__, '/^((?!\.git).)*$/');
$stub = "#!/usr/bin/php \n".$defaultStub;
$phar->setStub($stub);
$phar->stopBuffering();
$phar->compressFiles(Phar::GZ);
chmod($pharFile, 0755);
rename($pharFile, $finalFile);
echo "{$finalFile} has been successfully created!".PHP_EOL;
