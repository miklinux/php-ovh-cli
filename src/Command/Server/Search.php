<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class Search extends \OvhCli\Command
{
    public $shortDescription = "Search dedicated servers";
    public $usageExamples = [
        '"^ns123"' => 'Search for entries starting with "ns123"',
    ];
    
    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);

        $this->addOperands([
            Operand::create('filter', Operand::REQUIRED)
                ->setDescription('Regexp filter for reverse DNS')
        ]);

        $this->addOptions([
            Option::create('l', 'limit', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Limit results to this number')
        ]);
    }

    public function handle(GetOpt $getopt) {
        $filter  = addcslashes($getopt->getOperand('filter'), '/');
        $limit   = (int) $getopt->getOption('limit');
        $servers = $this->ovh()->getServers();
        $n = count($servers);

        $i=0;
        $results = [];
        Cli::out(Cli::boldWhite('Performing search on %d servers ...'), $n);
        foreach($servers as $server) {
            $details = $this->ovh()->getServerDetails($server);
            // try to match on OVH hostname
            if (!@preg_match("/$filter/", $server)) {
                // try to match on reverse hostname
                if (!@preg_match("/$filter/", $details['reverse'])) {
                    continue;
                }
            }
            $i++;
            $results[$server] = $details['reverse'];
            if ($i == $limit) {
                break;
            }
        }
        $f = count($results);
        if ($f == 0) {
            Cli::error('Sorry, your query did not match any result!');
        } else {
            // sort results for better readability
            asort($results);
            Cli::format($results, [ 
                'maxSize' => 30,
                'grep'    => (bool) $getopt->getOption('grep'),
            ]);
        }
        
    }
}