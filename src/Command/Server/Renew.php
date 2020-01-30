<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class Renew extends \OvhCli\Command
{
    public $shortDescription = "Manages dedicated server renewal (WIP)";

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);
        
        $this->addOperands([
            Operand::create('server', Operand::OPTIONAL)
                ->setDescription('Server name')
        ]);

        $this->addOptions([
            Option::create('a', 'all', GetOpt::NO_ARGUMENT)
                ->setDescription('Check all servers'),
            Option::create(null, 'on', GetOpt::NO_ARGUMENT)
                ->setDescription('Enable monitoring'),
            Option::create(null, 'off', GetOpt::NO_ARGUMENT)
                ->setDescription('Disable monitoring'),
        ]);
    }

    public function handle(GetOpt $getopt) {
        $all   = (bool) $getopt->getOption('all');
        $on    = (bool) $getopt->getOption('on');
        $off   = (bool) $getopt->getOption('off');

        if ($all) {
            $servers = $this->ovh()->getServers();
            foreach($servers as $server) {
                $details = $this->ovh()->getServerDetails($server);
                $service = $this->ovh()->getServerServiceInfo($server);
                $renew = $service['renew']['automatic'] ? Cli::green('ENABLED') : Cli::boldRed('DISABLED');
                Cli::out('%-30s %-30s %-15s %s', $server, $details['reverse'], $service['expiration'], $renew);
            }
        } else {
            $server = $getopt->getOperand('server');
            if (!$server) {
                return $this->missingArgument($getopt, 'Operand server is required');
            }
            $server = $this->resolve($server);
            $details = $this->ovh()->getServerDetails($server);
            $service = $this->ovh()->getServerServiceInfo($server);
            $data= [ $server => ['reverse'   => $details['reverse']] + $service ];
            Cli::format($data);
        }
    }
}