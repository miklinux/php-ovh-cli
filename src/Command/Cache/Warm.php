<?php

namespace OvhCli\Command\Cache;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Ovh;
use OvhCli\Cli;

class Warm extends \OvhCli\Command
{
    public $shortDescription = "Warms the cache";

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);
    }

    function formatPeriod($endtime, $starttime) {
        $duration = $endtime - $starttime;
        $hours = (int) ($duration / 60 / 60);
        $minutes = (int) ($duration / 60) - $hours * 60;
        $seconds = (int) $duration - $hours * 60 * 60 - $minutes * 60;
        return ($hours == 0 ? "00":$hours) . ":" . 
               ($minutes == 0 ? "00":($minutes < 10? "0".$minutes:$minutes)). ":" . 
               ($seconds == 0 ? "00":($seconds < 10? "0".$seconds:$seconds));
    }

    public function handle(GetOpt $getopt) {
        Cli::out("Warming cache (this may take a while) ... ");
        $start = microtime(true);
        $servers = $this->ovh()->getServers();
        $n = count($servers);
        foreach($servers as $server) {
            $i++;
            printf('%-10s %-30s', "[$i/$n]", $server);
            $this->ovh()->getServerDetails($server);
            foreach($this->ovh()->getServerBootIds($server) as $bootid) {
                $this->ovh()->getServerBootIdDetails($server, $bootid);
            }
            foreach ($this->ovh()->getServerNetworkInterfaces($server) as $uuid) { 
                $this->ovh()->getServerNetworkInterfaceDetails($server, $uuid);
            }
            foreach($this->ovh()->getServerIps($server) as $ip) {
                try {
                    $this->ovh()->getIpDetails($ip);
                } catch (\Exception $e) {
                    continue;
                }
            }
            print Cli::green("cached\n");
        }
        print "Retrieving vRack information ... ";
        foreach($this->ovh()->getVracks() as $vrack_id) {
            $this->ovh()->getVrackDetails($vrack_id);
        }
        print Cli::green("cached\n");
        print "Retrieving services information ... ";
        foreach($this->ovh()->getServices() as $service) {
            $this->ovh()->getService($service);
        }
        print Cli::green("cached\n");
        print "Retrieving API applications ... ";
        foreach($this->ovh()->getApiApps() as $app) {
            $this->ovh()->getApiApp($app);
        }
        print Cli::green("cached\n");
        $end = microtime(true);
        Cli::out('Cache is now warm! (%s)', $this->formatPeriod($end, $start));
    }
}