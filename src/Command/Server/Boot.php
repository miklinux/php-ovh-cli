<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class Boot extends \OvhCli\Command
{
    const BOOT_HARDDISK = 1;
    const BOOT_RESCUE   = 1122; 

    public $shortDescription = "Changes server boot mode";

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);

        $this->addOperands([
            Operand::create('server', \GetOpt\Operand::MULTIPLE)
                ->setDescription('Server name'),
        ]);

        $this->addOptions([
            Option::create('c', 'check', GetOpt::NO_ARGUMENT)
                ->setDescription('Check current boot id'),
            Option::create('a', 'all', GetOpt::NO_ARGUMENT)
                ->setDescription('Execute on all servers'),
            Option::create('l', 'list', GetOpt::NO_ARGUMENT)
                ->setDescription('List available boot ids'),
            Option::create('s', 'boot-id', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Set custom boot id'),
            Option::create(null, 'hd', GetOpt::NO_ARGUMENT)
                ->setDescription('Boot from HD (1)'),
            Option::create(null, 'rescue', GetOpt::NO_ARGUMENT)
                ->setDescription('Boot from rescue (1122)'),            
        ]);
    }

    public function getBootIds($server) {
        // print "Retrieving available boot ids for $server ...\n";
        $result = [];
        $bootids = $this->ovh()->getServerBootIds($server);
        foreach($bootids as $id) {
            $entry = $this->ovh()->getServerBootIdDetails($server, $id);
            $result[ $entry['bootId'] ] = $entry;
        }
        return $result;
    }

    public function handle(GetOpt $getopt) {
        $list = $getopt->getOption('list');

        if ($getopt->getOption('all')) {
            $servers = $this->ovh()->getServers();
        } else {
            $servers = $getopt->getOperand('server');
        }

        if ($getopt->getOption('hd')) {
            $bootid = self::BOOT_HARDDISK;
        } elseif ($getopt->getOption('rescue')) {
            $bootid = self::BOOT_RESCUE;
        } else {
            $bootid = $getopt->getOption('boot-id');
        }

        if (empty($servers)) {
            $this->missingArgument($getopt, 'Operand server is required (or specify option --all)');
        }
        $i=0;
        $n=count($servers);
        foreach($servers as $server) {
            $i++;
            $server = $this->resolve($server);
            $bootids = $this->getBootIds($server);
            if ($list) {
                $result = [];
                foreach ($bootids as $id => $entry) {
                    arsort($entry);
                    $result[$server][$id] = [
                        'type' => $entry['bootType'],
                        'description' => $entry['description'],
                    ];
                }
                Cli::format($result);
                continue;
            }

            $details = $this->ovh()->getServerDetails($server);
            $currentBootId = $details['bootId'];
            $currentBootType = $bootids[$currentBootId]['bootType'];
            switch($currentBootType) {
                case 'harddisk': $currentBootType = Cli::green($currentBootType); break;
                default: $currentBootType = Cli::boldRed($currentBootType); break;
            }
            Cli::out('%-30s %-30s %s', $server, $details['reverse'], $currentBootType);

            if ($bootid == 0) {
                continue;
            } elseif (!array_key_exists($bootid, $bootids)) {
                Cli::error("invalid bootId [%s] specified", $bootid);
            }

            if ($details['bootId'] != $bootid) {
                Cli::out("Setting BootId to %s ...", $bootid);
                $this->ovh()->updateServer($server, [
                    'bootId' => $bootid
                ]);
            }
        }
    }
}
