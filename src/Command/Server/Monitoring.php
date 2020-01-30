<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class Monitoring extends \OvhCli\Command
{
    public $shortDescription = "Manages dedicated server OVH monitoring";
    public $usageExamples = [
        '-a'             => 'Check monitoring status on ALL servers',
        '-a --on'        => 'Enable monitoring on ALL servers',
        '-a --off'       => 'Disable monitoring on ALL servers',
        '<server> --on'  => 'Enable monitoring on single server',
        '<server> --off' => 'Disable monitoring on single server',
    ];

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);
        
        $this->addOperands([
            Operand::create('server', Operand::MULTIPLE)
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
        $check = null;

        if ($on && $off) {
            die("ERROR: please choose one between --on and --off\n");
        } elseif ($on) {
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

        if ($n == 0) {
            echo $getopt->getHelpText();
            exit();
        }

        $data = [];
        $i = 0;
        foreach($servers as $server) {
            $server = $this->resolve($server);
            $i++;
            
            $details = $this->ovh()->getServerDetails($server);
            $status = (bool) $details['monitoring'];
            if (!$on && !$off) {
                $data[$server] = [
                    'reverse' => $details['reverse'],
                    'enabled' => $status,
                ];
            }
            if ($check !== null) {
                if ($status !== $check) {
                    $checkAction = $check ? 'Enabling' : 'Disabling';
                    Cli::out("%s monitoring on %s (%s) ...", $checkAction, $server, $details['reverse']);
                    $this->ovh()->updateServer($server, [
                        'monitoring' => $check
                    ]);
                }
            }
            asort($data);
            Cli::format($data, [
                'grep' => (bool) $getopt->getOption('grep'),
            ]);  
        }
    }
}