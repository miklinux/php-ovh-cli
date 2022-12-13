<?php

namespace OvhCli\Command\Service;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Engagement extends \OvhCli\Command
{
  public $shortDescription = 'Manages dedicated servers billing engagement';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('server', Operand::MULTIPLE)
        ->setDescription('Server name'),
    ]);

    $this->addOptions([
      Option::create('a', 'all', GetOpt::NO_ARGUMENT)
        ->setDescription('Run against all servers'),
      Option::create(null, 'on', GetOpt::NO_ARGUMENT)
        ->setDescription('Enable service billing engagement'),
      Option::create(null, 'off', GetOpt::NO_ARGUMENT)
        ->setDescription('Disable service billing engagement'),
      Option::create(null, 'enabled-only', GetOpt::NO_ARGUMENT)
        ->setDescription('Shows enabled services only'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $servers = $getopt->getOption('all') ?
      $this->ovh()->getServers() :
      $getopt->getOperand('server');

    $on = (bool) $getopt->getOption('on');
    $off = (bool) $getopt->getOption('off');
    $show_enabled_only = (bool) $getopt->getOption('enabled-only');

    if ($on && $off) {
      return $this->missingArgument($getopt, 'ERROR: --on and --off options should be enabled exclusively');
    }

    if (empty($servers)) {
      echo $getopt->getHelpText();
      exit();
    }
    
    $data = [];
    foreach ($servers as $server) {
      $server = $this->resolve($server);
      $details = $this->ovh()->getServerDetails($server);
      $service = $this->ovh()->getServerServiceInfo($server);
      $service_id = $service['serviceId'];
      try {
        $commitment = $this->ovh()->getServiceBillingEngagement($service_id);
        $strategy = $commitment['endRule']['strategy'];
        $engagement_enabled = ($strategy == 'REACTIVATE_ENGAGEMENT');
      } catch (\Exception $e) {
        // Server doesn't support billing engagement
        continue;
      }
      
      if ($engagement_enabled && $off) {
        Cli::out('Disabling server engagement on %s ...', $details['reverse']);
        $this->ovh()->setServiceBillingEngagement($service_id, false);
      } elseif (!$engagement_enabled && $on) {
        Cli::out('Enabling server engagement on %s ...', $details['reverse']);
        $this->ovh()->setServiceBillingEngagement($service_id, true);
      } elseif (!$on && !$off) {
        if (!$engagement_enabled && $show_enabled_only) {
          continue;
        }
        $data[$server] = [
          'reverse' => $details['reverse'],
          'enabled' => $engagement_enabled,
          'period'  => $commitment['currentPeriod'],
        ];
      }
    }

    if (!empty($data)) {
      Cli::format($data);
    }
  }
}