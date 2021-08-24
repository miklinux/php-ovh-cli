<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use OvhCli\Cli;

class IpmiReset extends \OvhCli\Command
{
  public $shortDescription = "Reset server's IPMI interface";

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
    $answer = Cli::confirm("Are you sure to reset IPMI interface for server {$server}?", false);
    if (!$answer) {
      $this->error('Operation aborted by user');
    }

    try {
      $result = $this->ovh()->resetServerIpmiInterface($server);
      Cli::success('Done!');
    } catch (\Exception $e) {
      $this->error($e);
    }
  }
}
