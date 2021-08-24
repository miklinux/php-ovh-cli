<?php

namespace OvhCli\Command\Api;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Apps extends \OvhCli\Command
{
  public $shortDescription = 'Manages API applications';
  public $usageExamples = [
    '<app-id>'    => 'Show information about an API application',
    '-l'          => 'List all API applications',
    '-d <app-id>' => 'Delete API application',
  ];

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('app-id', \GetOpt\Operand::OPTIONAL)
        ->setDescription('Application ID'),
    ]);

    $this->addOptions([
      Option::create('l', 'list', GetOpt::NO_ARGUMENT)
        ->setDescription('Delete application'),
      Option::create('d', 'delete', GetOpt::NO_ARGUMENT)
        ->setDescription('Delete application'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    \OvhCli\Ovh::disableCache(true);
    $appid = $getopt->getOperand('app-id');
    $list = $getopt->getOption('list');
    $delete = $getopt->getOption('delete');
    $grep = $getopt->getOption('grep');

    try {
      if ($appid) {
        $app = $this->ovh()->getApiApp($appid);
        Cli::format($app, ['grep' => $grep]);
        if ($delete) {
          if (Cli::confirm("Are you sure to delete application {$appid} ?", false)) {
            $this->ovh()->deleteApiApp($appid);
            Cli::out('Application deleted!');
          }
        }

        exit();
      }
      if ($list) {
        $apps = [];
        foreach ($this->ovh()->getApiApps() as $appid) {
          $apps[$appid] = $this->ovh()->getApiApp($appid);
          ksort($apps[$appid]);
        }
        Cli::format($apps, ['grep' => $grep]);

        exit();
      }
    } catch (\Exception $e) {
      Cli::error($e);
    }
    echo $getopt->getHelpText();

    exit();
  }
}
