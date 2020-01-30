<?php

namespace OvhCli\Command\Api;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class Apps extends \OvhCli\Command
{
    public $shortDescription = "Manages API applications";
    public $usageExamples = [
        '<app-id>'    => 'Show information about an API application',
        '-l'          => 'List all API applications',
        '-d <app-id>' => 'Delete API application',
    ];

    public function __construct() {
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

    public function handle(GetOpt $getopt) {
        $appid = $getopt->getOperand('app-id');
        $list   = $getopt->getOption('list');
        $delete = $getopt->getOption('delete');
        try {
            if ($appid) {
                $app = $this->ovh()->getApiApp($appid);
                Cli::format($app);
                if ($delete) {
                    if (Cli::confirm("Are you sure to delete application ${appid} ?", false)) {
                        $this->ovh()->deleteApiApp($appid);
                        Cli::out('Application deleted!');
                    }
                }
                exit();
            } elseif($list) {
                $apps = [];
                foreach($this->ovh()->getApiApps() as $appid) {
                    $apps[$appid] = $this->ovh()->getApiApp($appid);
                }
                Cli::format($apps);
                exit();
            }
        } catch (\Exception $e) {
            Cli::error($e);
        }
        print $getopt->getHelpText();
        exit();
    }
}