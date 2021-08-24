<?php

namespace OvhCli\Command\Service;

use GetOpt\GetOpt;
use GetOpt\Operand;
use OvhCli\Cli;

class Info extends \OvhCli\Command
{
  public $shortDescription = 'Lists all available services';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('service-id', \GetOpt\Operand::OPTIONAL)
        ->setDescription('Service ID'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $sid = $getopt->getOperand('service-id');
    if (!$sid) {
      $services = $this->ovh()->getServices();
      foreach ($services as $service) {
        $data = $this->ovh()->getService($service);
        Cli::out('%-10s %-40s %-10s', $service, $data['resource']['name'], strtoupper($data['state']));
      }

      return;
    }

    try {
      $service = $this->ovh()->getService($sid);
    } catch (\Exception $e) {
      Cli::error($e);
    }
    Cli::format($service, [
      'maxSize' => 40,
      'grep'    => (bool) $getopt->getOption('grep'),
    ]);
  }
}
