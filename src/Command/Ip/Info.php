<?php

namespace OvhCli\Command\Ip;

use GetOpt\GetOpt;
use GetOpt\Operand;
use OvhCli\Cli;

class Info extends \OvhCli\Command
{
  public $shortDescription = 'Retrieve information on IP Address';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);
    $this->addOperands([
      Operand::create('ip', \GetOpt\Operand::REQUIRED)
        ->setDescription('IP Address'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $host = $this->getOperand('ip');
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      $ip = $host;
    } else {
      $ip = gethostbyname($host);
      if ($ip == $host) {
        Cli::error("Unable to resolve host: {$host}");
      }
    }

    try {
      $data = $this->ovh()->getIpDetails($ip);
      Cli::format($data, [
        'grep' => (bool) $getopt->getOption('grep'),
      ]);
    } catch (\Exception $e) {
      Cli::error($e);
    }
  }
}
