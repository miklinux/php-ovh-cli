<?php

namespace OvhCli\Command\Ip;

use OvhCli\Color;
use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use PhpIP\IP;
use PhpIP\IPBlock;
use OvhCli\Cli;

class Reverse extends \OvhCli\Command
{
    public $shortDescription = "Manages dedicated server reverse DNS";
    public $usageExamples = [
        '-s <host> <reverse>'             => 'Assign reverse on first ips of server\'s assigned blocks',
        '-s <host> -l'                    => 'Retrieves IP reverse from the IP blocks assigned to server',
        '-s <host> -d'                    => 'Delete all IP reverse assigned to server',
        '-b <ip-block> -i <ip> <reverse>' => 'Assign reverse on specific IP belonging to an IP block',
        '-b <ip-block> -l'                => 'Retrieves IP reverse from an IP block',
        '-v <vrack> -i <ip> <reverse>'    => 'Assign reverse on IP belonging to vRack',
        '-v <vrack> -l'                   => 'Retrieves IP reverse from an IP block assigned to vRack',
    ];

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);

        $this->addOperands([
            Operand::create('reverse', \GetOpt\Operand::OPTIONAL)
                ->setDescription('Reverse DNS name')
        ]);

        $this->addOptions([
            Option::create('s', 'server', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Automatically set reverse on the first ip of each server\'s assigned blocks'),
            Option::create('b', 'ip-block', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('IP block (eg. 55.22.33.44/32, feed:dead:beef::/64)'),
            Option::create('i', 'ip', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('IP address to set reverse on'),
            Option::create('l', 'list', GetOpt::NO_ARGUMENT)
                ->setDescription('Retrieve reverse DNS names associated with a server'),
            Option::create('v', 'vrack', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Use IP block assigned to vRack'),
            Option::create('d', 'delete', GetOpt::NO_ARGUMENT)
                ->setDescription('Delete reverse'),
        ]);
    }

    protected function updateReverse($block, $ip, $reverse) {
        if (is_string($block)) {
            $block = IPBlock::create($block);
        }
        if (is_string($ip)) {
            $ip = IP::create($ip);
        }
        try {
            if (!$block->contains($ip)) {
                Cli::error("IP block '%s' does not contain IP '%s'", $block, $ip);
            }
            Cli::out("Assigning reverse DNS name '%s' to IP %s (block %s) ...", $reverse, $ip, $block);
            $assign = $this->ovh()->updateIpReverse($block, $ip, $reverse);
        } catch (\Exception $e) {
            Cli::error($e);
        }
    }

    protected function queryReverse($block) {
        Cli::out(Cli::boldWhite('IP block %s'), $block);
        foreach($this->ovh()->getIpBlockIps($block) as $ip) {
            $info = $this->ovh()->getIpReverse($block, $ip);
            Cli::out("%-30s %s", $ip, $info['reverse']);
        }
    }

    protected function deleteReverse($block, $ip) {
        $info = $this->ovh()->getIpReverse($block, $ip);
        $reverse = rtrim($info['reverse'], '.');
        $answer = Cli::confirm("Are you sure to delete IP reverse '${reverse}' for ${ip} (block $block) ?", false);
        if ($answer) {
            return $this->ovh()->deleteIpReverse($block, $ip);
        }
    }

    public function handle(GetOpt $getopt) {
        $server  = $getopt->getOption('server');
        if (!empty($server)) {
            $server = $this->resolve($server);
        }
        $reverse = $getopt->getOperand('reverse');
        $query   = $getopt->getOption('list');
        $delete  = $getopt->getOption('delete');
        $ip = $getopt->getOption('ip');

        if ($server) {
            try {
                $ips = $this->ovh()->getServerIps($server);
            } catch (\Exception $e) {
                Cli::error($e);
            }
            foreach($ips as $subnet) {
                try {
                    $block = IPBlock::create($subnet);
                } catch (\InvalidArgumentException $e) {
                    Cli::error($e);
                }
                if ($query) {
                    $this->queryReverse($subnet);
                    continue;
                }
                if (!$getopt->getOption('ip')) {
                    $ip = $block->getNbAddresses() > 1 ?
                        $block->getNetworkAddress()->plus(1) :
                        $block->getFirstIp();
                } else {
                    if (!$block->contains($ip)) {
                        continue;
                    }
                }
                
                if ($delete) {
                    $this->deleteReverse($block, $ip);
                    continue;
                }   
                if (empty($reverse)) {
                    return $this->missingArgument($getopt, 'Operand reverse is required');
                }
                $this->updateReverse($block, $ip, $reverse);
            }
        } else {
            $subnet = $getopt->getOption('ip-block');
            $vrack  = $getopt->getOption('vrack');
            if (empty($subnet) && !empty($vrack)) {
                $vrack_id = $this->findVrack($vrack);
                $ipblocks = $this->ovh()->getVrackIpBlocks($vrack_id);
                if (!empty($ipblocks)) {
                    // Use the first subnet
                    $subnet = $ipblocks[0];
                }
            }
            if (empty($subnet)) {
                return $this->missingArgument($getopt, 'Option ip-block is required');
            }
            if ($query) {
                $this->queryReverse($subnet);
                exit();
            }
            if (empty($ip)) {
                return $this->missingArgument($getopt, 'Option ip is required');
            }
            if ($delete) {
                $this->deleteReverse($subnet, $ip);
                exit();
            }
            if (empty($reverse)) {
                return $this->missingArgument($getopt, 'Operand reverse is required');
            }
            try {
                $ip = IP::create($ip);
                $block = IPBlock::create($subnet);
            } catch (\InvalidArgumentException $e) {
                Cli::error($e);
            }
            $this->updateReverse($block, $ip, $reverse);
        }
    }
}