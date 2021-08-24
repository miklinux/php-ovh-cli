<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use OvhCli\Cli;

class Info extends \OvhCli\Command
{
  public $shortDescription = 'Retrieves info on a dedicated server';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('server', Operand::MULTIPLE + Operand::REQUIRED)
        ->setDescription('Server name'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $servers = $getopt->getOperand('server');
    $data = [];

    foreach ($servers as $server) {
      $item = [];

      try {
        $server = $this->resolve($server);
        // Server information
        $item += $this->ovh()->getServerDetails($server);
        // Hardware specifications
        $item += $this->ovh()->getServerHardwareSpecs($server);
        // Network interface details
        $item += $this->getNetworkInterfaces($server);
        // IP blocks details
        $item += $this->getIpBlocks($server);
      } catch (\Exception $e) {
        Cli::error($e);
      }
      $data[$server] = $item;
    }

    if (1 == count($data)) {
      $data = array_shift($data);
    }
    Cli::format($data, [
      'grep' => (bool) $getopt->getOption('grep'),
    ]);
  }

  protected function getNetworkInterfaces($server)
  {
    $nics = [];
    $uuids = $this->ovh()->getServerNetworkInterfaces($server);
    if (!empty($uuids)) {
      foreach ($uuids as $uuid) {
        $net = $this->ovh()->getServerNetworkInterfaceDetails($server, $uuid);
        $nics[$net['mode']] = [
          'macAddress' => $net['networkInterfaceController'][0],
          'uuid'       => $uuid,
        ];
      }

      return ['networkInterfaces' => $nics];
    }

    return [];
  }

  protected function getIpBlocks($server)
  {
    $ipBlocks = [];
    $ips = $this->ovh()->getServerIps($server);
    foreach ($ips as $ip) {
      $subnet = \PhpIP\IPBlock::create($ip);
      $firstIp = $subnet->getNbAddresses() > 1 ?
        $subnet->getNetworkAddress()->plus(1) :
        $subnet->getFirstIp();
      if ($subnet instanceof \PhpIP\IPv4Block) {
        $n = new \PhpIP\IPV4Block($subnet->getNetworkAddress(), '24');
        $gateway = $n->getBroadcastAddress()->minus(1);
      } elseif ($subnet instanceof \PhpIP\IPv6Block) {
        $n = new \PhpIP\IPV6Block($subnet->getNetworkAddress(), '56');
        $gateway = preg_replace('/:ffff(?:|$)/', ':ff', $n->getBroadcastAddress());
      }
      $ipBlocks[(string) $subnet] = [
        'firstIp' => $firstIp,
        'gateway' => $gateway,
      ];
    }

    return ['ipBlocks' => $ipBlocks];
  }
}
