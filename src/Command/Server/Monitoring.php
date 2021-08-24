<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Monitoring extends \OvhCli\Command
{
  public $shortDescription = 'Manages dedicated server OVH monitoring';
  public $usageExamples = [
    '-a'                 => 'Check monitoring status on ALL servers',
    '-a --show-disabled' => 'Check monitoring status on ALL servers but report only the ones disabled',
    '-a --on'            => 'Enable monitoring on ALL servers',
    '-a --off'           => 'Disable monitoring on ALL servers',
    '<server> --on'      => 'Enable monitoring on single server',
    '<server> --off'     => 'Disable monitoring on single server',
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
        ->setDescription('Check all servers'),
      Option::create(null, 'on', GetOpt::NO_ARGUMENT)
        ->setDescription('Enable monitoring'),
      Option::create(null, 'off', GetOpt::NO_ARGUMENT)
        ->setDescription('Disable monitoring'),
      Option::create('d', 'show-disabled', GetOpt::NO_ARGUMENT)
        ->setDescription('Show servers with monitoring disabled only'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $all = (bool) $getopt->getOption('all');
    $on = (bool) $getopt->getOption('on');
    $off = (bool) $getopt->getOption('off');
    $showdisabled = (bool) $getopt->getOption('show-disabled');
    $check = null;

    if ($on && $off) {
      exit("ERROR: please choose one between --on and --off\n");
    }
    if ($on) {
      $check = true;
    } elseif ($off) {
      $check = false;
    }

    if ($all) {
      $servers = $this->ovh()->getServers();
    } else {
      $servers = $this->getOperand('server')->getValue();
    }
    $servers = array_unique($servers);
    $n = count($servers);

    if (0 == $n) {
      echo $getopt->getHelpText();

      exit();
    }

    $data = [];
    $i = 0;
    foreach ($servers as $server) {
      $server = $this->resolve($server);
      ++$i;

      $details = $this->ovh()->getServerDetails($server);
      $status = (bool) $details['monitoring'];
      if (!$on && !$off) {
        if (true == $showdisabled && true == $status) {
          continue;
        }
        $data[$server] = [
          'reverse' => $details['reverse'],
          'enabled' => $status,
        ];
      }
      if (null !== $check) {
        if ($status !== $check) {
          $checkAction = $check ? 'Enabling' : 'Disabling';
          Cli::out('%s monitoring on %s (%s) ...', $checkAction, $server, $details['reverse']);
          $this->ovh()->updateServer($server, [
            'monitoring' => $check,
          ]);
        }
      }
    }
    asort($data);
    Cli::format($data, [
      'grep' => (bool) $getopt->getOption('grep'),
    ]);
  }
}
