<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class Console extends \OvhCli\Command
{
    public $shortDescription = "Open a KVM session";

    const DEFAULT_ATTEMPTS = 10;
    const DEFAULT_DELAY    = 5;
    const DEFAULT_TTL      = 15;

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);

        $this->addOperands([
            Operand::create('server', \GetOpt\Operand::REQUIRED)
                ->setDescription('Server name')
        ]);

        $this->addOptions([
            Option::create('a', 'attempts', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Max numbers of attempts (default: '. self::DEFAULT_ATTEMPTS .')')
                ->setDefaultValue(self::DEFAULT_ATTEMPTS),
            Option::create('d', 'delay', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Delay between attempts (default: '. self::DEFAULT_DELAY .')')
                ->setDefaultValue(self::DEFAULT_DELAY),
            Option::create('x', 'ttl', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Session TTL in minutes (default: '. self::DEFAULT_TTL .')')
                ->setDefaultValue(self::DEFAULT_TTL),    
        ]);

    }

    public function handle(GetOpt $getopt) {
        $server   = $this->resolve($getopt->getOperand('server'));
        $attempts = (int) $getopt->getOption('attempts');
        $delay    = (int) $getopt->getOption('delay');
        $ttl      = (int) $getopt->getOption('ttl');

        if (!is_executable($this->config->javaws)) {
            Cli::error("Not an valid executable '%s' - Please run api::setup!", $this->config->javaws);
        }

        // Disable cache and dry run by default
        \OvhCli\Ovh::disableCache(true);
        \OvhCli\Ovh::setDryRun(false);

        try {
            Cli::out('Requesting IPMI access for %s (TTL %d) ...', $server, $ttl);
            $access = $this->ovh()->requestServerIpmiAccess($server, [
                'ttl'  => $ttl,
                'type' => 'kvmipJnlp',
            ]);
            Cli::out("IPMI access request sent... Waiting for session (This may take a while)...");
        } catch (\Exception $e) {
            Cli::out($e);
        }
        
        for($i=1; $i<=$attempts; $i++) {
            if ($i > 1) {
                sleep($delay);
            }     
            Cli::out('[%d/%d] Trying to retrieve JNLP file ...', $i, $attempts);
            try {
              $data = $this->ovh()->getServerIpmiAccessData($server, [
                'type' => 'kvmipJnlp', 
              ]);
              break;
            } catch (\Exception $e) {
              continue;
            }
          }
          
          if (empty($data)) {
            Cli::error('unable to fetch JNLP file!');
          }
          
          $jnlp = Cli::tempFile($data['value']);
          Cli::out('JNLP file saved to %s ...', $jnlp);
          Cli::out('Spawning console client ...');
          $command = sprintf('%s %s', $this->config->javaws, $jnlp);
          system($command);
          unlink($jnlp);
    }

}