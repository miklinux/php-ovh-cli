<?php

namespace OvhCli\Command;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class Vrack extends \OvhCli\Command
{
    public $shortDescription = "Manage vRacks";
    public $usageExamples = [
        '-l'                  => 'Lists all available vRacks',
        '-s <server> <vrack>' => 'Assigns server to vRack',
        '<vrack>'             => 'Shows all servers assigned to vRack',
    ];
                
    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);
        
        $this->addOperands([
            Operand::create('vrack', \GetOpt\Operand::OPTIONAL)
                ->setDescription('vRACK id or alias')
        ]);

        $this->addOptions([
            Option::create('s', 'server', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Server name'),
            Option::create('l', 'list', GetOpt::NO_ARGUMENT)
                ->setDescription('List available vRacks')
        ]);
    }

    public function listVracks() {
        $vracks = $this->getVracks();
        Cli::out("Available vRacks:");
        foreach ($vracks as $name => $alias) {
            printf("  %-25s %s\n", Cli::boldWhite($alias), $name);
        }
    }

    public function isInVrack($vrack_id, $vrack_nic_uuid) {
        Cli::out("Checking if NIC UUID %s is assigned to vRack %s ...", Cli::boldWhite($vrack_nic_uuid), Cli::boldWhite($vrack_id));
        $vrack_nic_uuids = $this->ovh()->getVrackNetworkInterfaces($vrack_id);
        return in_array($vrack_nic_uuid, $vrack_nic_uuids);
    }

    public function getServerVrackInterface($server) {
        Cli::out("Retrieving %s network configuration ...", Cli::boldWhite($server));
        $uuids = $this->ovh()->getServerNetworkInterfaces($server);
        foreach ($uuids as $uuid) {
            $net = $this->ovh()->getServerNetworkInterfaceDetails($server, $uuid);
            if ($net['mode'] != 'vrack') {
                continue;
            }
            $vrack_nic_uuid = $uuid;
            break;
        }
        if (!$vrack_nic_uuid) {
            Cli::error("unable to determine vRack dedicated interface for %s!", $server);
        }
        return $vrack_nic_uuid;
    }

    public function assignServerToVrack($server, $vrack_id) {
        $vrack_nic_uuid = $this->getServerVrackInterface($server);
        Cli::out("Detected vRack NIC UUID %s", Cli::boldWhite($vrack_nic_uuid));
        if ($this->isInVrack($vrack_id, $vrack_nic_uuid)) {
            Cli::error("this server's NIC seems to be already assigned to the selected vRack!");
            exit();
        }

        Cli::out("Assigning %s to vRack %s ...", Cli::boldWhite($server), Cli::boldWhite($vrack_id));
        $assign = $this->ovh()->assignServerNetworkInterfaceToVrack($vrack_id, $vrack_nic_uuid);
    }

    public function handle(GetOpt $getopt) {
        $vrack  = $getopt->getOperand('vrack');
        $server = $getopt->getOption('server');
        $query  = $getopt->getOption('query');
        $list   = $getopt->getOption('list');

        if ($list) {
            $this->listVracks();
            exit();
        }
        if (empty($vrack)) {
            return $this->missingArgument($getopt, 'missing vRack');
        }
        $vrack_id = $this->findVrack($vrack);

        if ($server) {
            try {
                $server = $this->resolve($server);
            } catch (\Exception $e) {
                Cli::error($e);
            }
            $this->assignServerToVrack($server, $vrack_id);
            Cli::success("Operation completed successfully!");
            exit();
        }

        $info = $this->ovh()->getVrackNetworkInterfacesDetails($vrack_id);
        $data = [];
        foreach($info as $item) {
            $server = $item['dedicatedServer'];
            $serverInfo = $this->ovh()->getServerDetails($server);
            $data[$server] = $serverInfo['reverse'];
        }
        asort($data);
        Cli::format($data);

        if ($ipblocks = $this->ovh()->getVrackIpBlocks($vrack_id)) {
            print PHP_EOL;
            print Cli::boldWhite("This vRack has the following IP blocks assigned:\n");
            $ips = [];
            foreach($ipblocks as $ipblock) {
                $info = $this->ovh()->getVrackIpBlock($vrack_id, $ipblock);
                Cli::out(' %s (zone: %s, gateway: %s)', $ipblock, strtoupper($info['zone']), $info['gateway']);
            }
            Cli::format($ips);
        }
        exit();
        
    }
}