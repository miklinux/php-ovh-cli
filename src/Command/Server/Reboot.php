<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use OvhCli\Cli;

class Reboot extends \OvhCli\Command
{
  public $shortDescription = 'Request hardware reboot for a server';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('server', \GetOpt\Operand::REQUIRED)
        ->setDescription('Server name'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $server = $this->resolve($getopt->getOperand('server'));
    Cli::out(Cli::boldRed('CAUTION: A hardware reboot will be requested to OVH support for server %s!'), $server);
    $confirm = Cli::confirm('Are you sure to proceed ?', false);
    if ($confirm) {
      Cli::out('Rebooting server %s ...', $server);

      try {
        $this->ovh()->rebootServer($server);
      } catch (\Exception $e) {
        Cli::error($e);
      }
    }
  }
}
