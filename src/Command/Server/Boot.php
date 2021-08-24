<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Boot extends \OvhCli\Command
{
  public const BOOT_HARDDISK = 1;
  public const BOOT_RESCUE = 1122;

  public $shortDescription = 'Changes server boot mode';
  public $usageExamples = [
    '-a'             => 'Retrieve current boot mode for all servers',
    '-a --hd'        => 'Set boot from HARDDISK on all servers',
    '-a --rescue'    => 'Set boot from RESCUE on all servers',
    'my.server --hd' => 'Set boot from HARDDISK for my.server',
  ];

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('server', Operand::MULTIPLE)
        ->setDescription('Server name'),
    ]);

    $this->addOptions([
      Option::create('a', 'all', GetOpt::NO_ARGUMENT)
        ->setDescription('Execute on all servers'),
      Option::create('l', 'list', GetOpt::NO_ARGUMENT)
        ->setDescription('List available boot ids'),
      Option::create('s', 'boot-id', GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Set custom boot id'),
      Option::create(null, 'hd', GetOpt::NO_ARGUMENT)
        ->setDescription('Boot from HD (1)'),
      Option::create(null, 'rescue', GetOpt::NO_ARGUMENT)
        ->setDescription('Boot from rescue (1122)'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $list = $getopt->getOption('list');

    if ($getopt->getOption('all')) {
      $servers = $this->ovh()->getServers();
    } else {
      $servers = $getopt->getOperand('server');
    }

    if ($getopt->getOption('hd')) {
      $bootId = self::BOOT_HARDDISK;
    } elseif ($getopt->getOption('rescue')) {
      $bootId = self::BOOT_RESCUE;
    } else {
      $bootId = $getopt->getOption('boot-id');
    }

    if (empty($servers)) {
      $this->missingArgument($getopt, 'Operand server is required (or specify option --all)');
    }

    $result = [];
    foreach ($servers as $server) {
      $server = $this->resolve($server);
      $details = $this->ovh()->getServerDetails($server);
      $bootIds = $this->ovh()->getServerBootIdsList($server);

      if ($list) {
        $result[$server] = $bootIds;

        continue;
      }

      if (empty($bootId)) {
        $boot = $this->ovh()->getServerBootMode($server);
        $result[$server] = [
          'reverse'  => $details['reverse'],
          'bootType' => $boot['bootType'],
        ];

        continue;
      }

      if (!array_key_exists($bootId, $bootIds)) {
        Cli::error("Invalid boot id '%s' specified", $bootId);
      }

      if ($details['bootId'] != $bootId) {
        Cli::out(
          'Setting server boot id to %s (%s) ...',
          Cli::boldWhite($bootIds[$bootId]['bootType']),
          $bootId
        );
        $this->ovh()->updateServer($server, [
          'bootId' => $bootId,
        ]);
      }
    }
    asort($result);
    Cli::format($result, [
      'grep' => (bool) $getopt->getOption('grep'),
    ]);
  }
}
