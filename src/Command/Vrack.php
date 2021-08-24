<?php

namespace OvhCli\Command;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Vrack extends \OvhCli\Command
{
  public $shortDescription = 'Manage vRacks';
  public $usageExamples = [
    '-l'                     => 'Lists all available vRacks',
    '-s <server>'            => 'Retrieve server\'s assigned vRack',
    '-s <server> <vrack>'    => 'Assigns server to vRack',
    '-d -s <server> <vrack>' => 'Removes server from vRack',
    '<vrack>'                => 'Shows all servers assigned to vRack',
  ];

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('vrack', \GetOpt\Operand::OPTIONAL)
        ->setDescription('vRACK id or alias'),
    ]);

    $this->addOptions([
      Option::create('s', 'server', GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Server name'),
      Option::create('d', 'delete', GetOpt::NO_ARGUMENT)
        ->setDescription('Remove server from vRack'),
      Option::create('l', 'list', GetOpt::NO_ARGUMENT)
        ->setDescription('List available vRacks'),
    ]);
  }

  public function assignServerToVrack($server, $vrack_id)
  {
    Cli::out('Assigning %s to vRack %s ...', Cli::boldWhite($server), Cli::boldWhite($vrack_id));
    $vrack_nic_uuid = $this->ovh()->getServerVrackInterface($server);
    if ($this->ovh()->isInVrack($vrack_id, $vrack_nic_uuid)) {
      throw new \Exception("Server {$server} seems to be already assigned to vRack {$vrack_id}");
    }
    $this->ovh()->assignServerNetworkInterfaceToVrack($vrack_id, $vrack_nic_uuid);
    Cli::success('Operation completed successfully!');
  }

  public function removeServerFromVrack($server, $vrack_id)
  {
    $vrack_nic_uuid = $this->ovh()->getServerVrackInterface($server);
    if (!$this->ovh()->isInVrack($vrack_id, $vrack_nic_uuid)) {
      throw new \Exception("Server {$server} doesn't seem to be assigned to vRack {$vrack_id}");
    }
    if (!Cli::confirm("Are you sure to remove server {$server} from vRack {$vrack_id}?", false)) {
      exit();
    }
    $this->ovh()->removeServerNetworkInterfaceFromVrack($vrack_id, $vrack_nic_uuid);
    Cli::success('Operation completed successfully!');
  }

  public function getVrackServers($vrack_id)
  {
    $info = $this->ovh()->getVrackNetworkInterfacesDetails($vrack_id);
    $data = [];
    foreach ($info as $item) {
      $server = $item['dedicatedServer'];
      $serverInfo = $this->ovh()->getServerDetails($server);
      $data[$server] = $serverInfo['reverse'];
    }
    asort($data);

    return $data;
  }

  public function handle(GetOpt $getopt)
  {
    $vrack = $getopt->getOperand('vrack');
    $server = $getopt->getOption('server');
    $list = $getopt->getOption('list');
    $delete = $getopt->getOption('delete');
    $grep = (bool) $getopt->getOption('grep');

    try {
      if ($list) {
        $vracks = $this->ovh()->getVracksList();
        Cli::format($vracks, ['grep' => $grep]);

        exit();
      }
      if (!empty($vrack)) {
        $vrack_id = $this->ovh()->findVrack($vrack);
      }

      if ($server) {
        $server = $this->resolve($server);
        if (empty($vrack)) {
          $vrack_id = $this->ovh()->findServerVrack($server);
          if (false === $vrack_id) {
            Cli::error("Server {$server} is not assigned to any vRack");
          }
          Cli::success("Server {$server} is currently assigned to vRack {$vrack_id}");

          exit();
        }
        if ($delete) {
          $this->removeServerFromvRack($server, $vrack_id);
        } else {
          $this->assignServerToVrack($server, $vrack_id);
        }

        exit();
      }
      if (empty($vrack)) {
        return $this->missingArgument($getopt, 'Operand vrack is required');
      }
      $data = ['vrackServers' => $this->getVrackServers($vrack_id)];
      if ($ipblocks = $this->ovh()->getVrackIpBlocks($vrack_id)) {
        $data['ipBlocks'] = [];
        foreach ($ipblocks as $ipblock) {
          $info = $this->ovh()->getVrackIpBlock($vrack_id, $ipblock);
          $data['ipBlocks'][] = $info;
        }
      }
      Cli::format($data, ['grep' => $grep]);
    } catch (\Exception $e) {
      Cli::error($e);
    }
  }
}
