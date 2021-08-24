<?php

namespace OvhCli\Command\Ip;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Failover extends \OvhCli\Command
{
  public $shortDescription = 'Lists failover IPs';
  public $usageExamples = [
    '-l'               => 'List all FO IPs from ALL servers',
    '-l -s <server>'   => 'Returns all FO IPs attached to <server>',
    '-l <ip>'          => 'Returns the server having that FO IP attached',
    '<ip> -s <server>' => 'Moves FO IP <ip> to server <server>',
  ];

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('ip', \GetOpt\Operand::OPTIONAL)
        ->setDescription('Failover IP address'),
    ]);

    $this->addOptions([
      Option::create('l', 'list', GetOpt::NO_ARGUMENT)
        ->setDescription('Retrieve reverse DNS names associated with a server'),
      Option::create('s', 'server', GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Server to move the Failover IP to'),
    ]);
  }

  // Very very very rough around the edges. Needs improvement.
  public function handle(GetOpt $getopt)
  {
    $foip = (string) $this->getOperand('ip');
    if (!empty($foip) && !strstr($foip, '/')) {
      $foip .= '/32';
    }
    $list = $getopt->getOption('list');
    $server = (string) $this->getOption('server');
    if (!empty($server)) {
      $server = $this->resolve($server);
    }
    $data = [];
    if ($list) {
      $ips = $this->ovh()->getFailoverIps();
      foreach ($ips as $ip) {
        if (!empty($foip) && $ip != $foip) {
          continue;
        }
        $info = $this->ovh()->getIpDetails($ip);
        if (!empty($server) && $info['routedTo']['serviceName'] != $server) {
          continue;
        }
        $details = $this->ovh()->getServerDetails($info['routedTo']['serviceName']);
        $item = empty($details['reverse']) ? $info['routedTo']['serviceName'] : $details['reverse'];
        $data[$item][] = $ip;
      }
      ksort($data);
      Cli::format($data, [
        'noArrayIndex' => true,
        'grep'         => (bool) $getopt->getOption('grep'),
      ]);

      exit();
    }
    if (!empty($server) && !empty($foip)) {
      // Destination server
      $details = $this->ovh()->getServerDetails($server);

      // Source server
      $info = $this->ovh()->getIpDetails($foip);
      $srcdetails = $this->ovh()->getServerDetails($info['routedTo']['serviceName']);
      if ($info['routedTo']['serviceName'] == $server) {
        Cli::error('IP %s already belongs to %s (%s)', $foip, $server, $details['reverse']);
      }
      $confirm = Cli::confirm("Do you really want to move Failover IP {$foip} from ${srcdetails['reverse']} to ${details['reverse']} ?", false);
      if ($confirm) {
        $res = $this->ovh()->moveIp($foip, $server);
        Cli::success('Failover IP has been moved successfully');
      } else {
        Cli::error('Aborting');
      }
    } else {
      echo $getopt->getHelpText();

      exit();
    }
  }
}
