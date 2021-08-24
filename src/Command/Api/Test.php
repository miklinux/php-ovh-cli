<?php

namespace OvhCli\Command\Api;

use GetOpt\GetOpt;
use OvhCli\Cli;

/**
 * @internal
 * @coversNothing
 */
class Test extends \OvhCli\Command
{
  public $shortDescription = 'Tests if API is working';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);
  }

  public function handle(GetOpt $getopt)
  {
    try {
      $me = $this->ovh()->getUser();
      Cli::success(
        'Hi %s %s, OVH API are working properly ;-)',
        $me['firstname'],
        $me['name']
      );
      ksort($me);
      print PHP_EOL;
      Cli::format($me);
      print PHP_EOL;
    } catch (\Exception $e) {
      Cli::error($e);
    }
  }
}
