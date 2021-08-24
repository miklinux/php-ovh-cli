<?php

namespace OvhCli\Command\Cache;

use GetOpt\GetOpt;
use OvhCli\Cli;

class Warm extends \OvhCli\Command
{
  public $shortDescription = 'Warms the cache';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);
  }

  public function formatPeriod($endtime, $starttime)
  {
    $duration = $endtime - $starttime;
    $hours = (int) ($duration / 60 / 60);
    $minutes = (int) ($duration / 60) - $hours * 60;
    $seconds = (int) $duration - $hours * 60 * 60 - $minutes * 60;

    return (0 == $hours ? '00' : $hours).':'.
      (0 == $minutes ? '00' : ($minutes < 10 ? '0'.$minutes : $minutes)).':'.
      (0 == $seconds ? '00' : ($seconds < 10 ? '0'.$seconds : $seconds));
  }

  public function handle(GetOpt $getopt)
  {
    Cli::out('Warming cache (this may take a while) ... ');
    $start = microtime(true);
    $servers = $this->ovh()->getServers();
    $n = count($servers);
    foreach ($servers as $server) {
      ++$i;
      $warn = false;
      printf('%-10s %-30s', "[{$i}/{$n}]", $server);
      $this->ovh()->getServerDetails($server);
      foreach ($this->ovh()->getServerBootIds($server) as $bootid) {
        $this->ovh()->getServerBootIdDetails($server, $bootid);
      }
      foreach ($this->ovh()->getServerNetworkInterfaces($server) as $uuid) {
        $this->ovh()->getServerNetworkInterfaceDetails($server, $uuid);
      }
      foreach ($this->ovh()->getServerIps($server) as $ip) {
        try {
          $this->ovh()->getIpDetails($ip);
        } catch (\Exception $e) {
          $warn = true;
          Cli::warning($e);

          continue;
        }
      }
      if (!$warn) {
        echo Cli::green("done\n");
      }
    }
    echo PHP_EOL;
    printf('%-41s', 'Caching vRack configuration');
    foreach ($this->ovh()->getVracks() as $vrack_id) {
      $this->ovh()->getVrackDetails($vrack_id);
    }
    echo Cli::green("done\n");

    printf('%-41s', 'Caching services');
    foreach ($this->ovh()->getServices() as $service) {
      $this->ovh()->getService($service);
    }
    echo Cli::green("done\n");

    printf('%-41s', 'Caching API applications');
    foreach ($this->ovh()->getApiApps() as $app) {
      $this->ovh()->getApiApp($app);
    }
    echo Cli::green("done\n");
    echo PHP_EOL;
    $end = microtime(true);
    Cli::out('Cache is now WARM! (%s)', $this->formatPeriod($end, $start));
  }
}
