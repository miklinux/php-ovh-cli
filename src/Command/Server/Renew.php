<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Renew extends \OvhCli\Command
{
  public $shortDescription = 'Manages dedicated server renewal';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('server', Operand::OPTIONAL)
        ->setDescription('Server name'),
    ]);

    $this->addOptions([
      Option::create('a', 'all', GetOpt::NO_ARGUMENT)
        ->setDescription('Check all servers'),
      Option::create(null, 'on', GetOpt::NO_ARGUMENT)
        ->setDescription('Enable automatic renewal'),
      Option::create(null, 'off', GetOpt::NO_ARGUMENT)
        ->setDescription('Disable automatic renewal'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $all = (bool) $getopt->getOption('all');
    $renew = null;
    if (true === (bool) $getopt->getOption('on')) {
      $renew = true;
    } elseif (true === (bool) $getopt->getOption('off')) {
      $renew = false;
    }

    if ($all) {
      $servers = $this->ovh()->getServers();
      foreach ($servers as $server) {
        $details = $this->ovh()->getServerDetails($server);
        $service = $this->ovh()->getServerServiceInfo($server);
        $renew = $service['renew']['automatic'] ? Cli::green('ENABLED') : Cli::boldRed('DISABLED');
        Cli::out('%-30s %-30s %-15s %s', $server, $details['reverse'], $service['expiration'], $renew);
      }
    } else {
      $server = $getopt->getOperand('server');
      if (!$server) {
        return $this->missingArgument($getopt, 'Operand server is required');
      }
      $server = $this->resolve($server);
      $details = $this->ovh()->getServerDetails($server);
      $service = $this->ovh()->getServerServiceInfo($server);
      if (is_bool($renew)) {
        Cli::out('%s automatic renewal for server %s ...', ($renew ? 'Enabling' : 'Disabling'), $server);
        $result = $this->ovh()->updateServerServiceInfo($server, [
          'renew' => [
            'automatic'          => $renew,
            'period'             => 1,
            'deleteAtExpiration' => false,
            'forced'             => false,
          ],
        ]);

        exit();
      }
      $data = ['reverse' => $details['reverse']] + $service;
      ksort($data);
      Cli::format($data, [
        'grep' => (bool) $getopt->getOption('grep'),
      ]);
    }
  }
}
