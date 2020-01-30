<?php

namespace OvhCli;

use OvhCli\Ovh;
use OvhCli\Config;
use OvhCli\Cli;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\CacheConfig;
use Phpfastcache\Core\phpFastCache;
use GetOpt\GetOpt;

class Command extends \GetOpt\Command 
{
    protected $config;
    protected $name;
    protected $usageExamples = [];

    public function __construct($name, $handler, $options = null) {
        parent::__construct($name, $handler, $options);
        $this->config = Config::getInstance(CONFIG_FILE);
        if (
            empty($this->longDescription) && 
            !empty($this->shortDescription)
           ) {
            $this->longDescription = $this->shortDescription;
        }
        $this->usageExamples();
    }

    protected function usageExamples() {
        if (empty($this->usageExamples)) {
            return;
        }
        $this->longDescription .= "\n\nExamples:\n";
        foreach($this->usageExamples as $command => $description) {
            $this->longDescription .= sprintf("  %-50s %s\n", $this->getName() .' '. $command, $description);
        }
        $this->longDescription = trim($this->longDescription);
    }

    public function getName() {
        if (null === $this->name) {
            $class = substr(get_class($this), strlen('\\OvhCli\\Command'));
            $class = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $class));
            $this->name = str_replace('\\', ':', $class);
        }
        return $this->name;
    }

    public function missingArgument(GetOpt $getopt, $message) {
        Cli::out($message . PHP_EOL);
        print $getopt->getHelpText();
        exit();
    }

    public function resolve($address) {
        if (empty($address)) {
            Cli::error('missing server address');
        }
        // Resolve the ones which do not conform to ovh standard
        if (preg_match('/^ns[0-9]+\./', $address)) {
            return $address;
        }
        // Cli::out("%s is not an OVH hostname format. Attempting to resolve from reverse ...", $address);
        $ip = gethostbyname($address);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Cli::error('Unable to resolve host %s', $address);
        }
        try {
            $info = $this->ovh()->getIpDetails($ip);
        } catch (Exception $e) {
            Cli::error($e);
        }
        if ($info['type'] !== 'dedicated') {
            Cli::error("'%s' was expected to be a dedicated server but '%s' was found", $address, $info['type']);
        }
        $realAddress = $info['routedTo']['serviceName'];
        return $realAddress;
    }

    public function getVracks() {
        $result = [];
        $vracks = $this->ovh()->getVracks();
        foreach ($vracks as $vrack) {
            $details = $this->ovh()->getVrackDetails($vrack);
            $result[$vrack] = $details['name'];
        }
        return $result;
    }

    public function findVrack($vrack) {
        $vracks = $this->getVracks();
        if (array_key_exists($vrack, $vracks)) {
            return $vrack;
        } else {
            $vracks = array_flip($vracks);
            if (!array_key_exists($vrack, $vracks)) {
                Cli::error("unabel to find vRack '%s'", $vrack);
            }
            return $vracks[$vrack];
        }
    }

    public function ovh() {
        try {
            return Ovh::getInstance($this->config);
        } catch (\Exception $e) {
            Cli::error(
                "ERROR: Unable to instantiate OVH API: '%s'". PHP_EOL . 
                "Parameters might be wrong, please try to reconfigure.",
                $e->getMessage()            
            );
        }
    }
}
