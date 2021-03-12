<?php

namespace OvhCli\Command\Ip;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use PhpIP\IP;
use PhpIP\IPBlock;
use OvhCli\Cli;

class Failover extends \OvhCli\Command
{
  public $shortDescription = "Lists failover IPs";

  public function __construct() {
    parent::__construct($this->getName(), [$this, 'handle']);
  }

  public function handle(GetOpt $getopt) {
    $data = [];
    $ips = $this->ovh()->getFailoverIps();
    foreach($ips as $ip) {
      $info = $this->ovh()->getIpDetails($ip);
      $server = $this->ovh()->getServerDetails($info['routedTo']['serviceName']);
      $item = empty($server['reverse']) ? $info['routedTo']['serviceName'] : $server['reverse'];
      $data[$item][] = $ip;
    }
    ksort($data);

    foreach($data as $host => $ips) {
      print Cli::boldWhite($host) . "\n";
      foreach($ips as $ip) {
        print "  " . $ip . "\n";
      }
    }
  }
}
