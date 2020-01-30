<?php

namespace OvhCli\Command\Ip;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use PhpIP\IP;
use PhpIP\IPBlock;
use OvhCli\Cli;

class Resolve extends \OvhCli\Command
{
    public $shortDescription = "Resolve IP/Host to OVH hostname";

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);
        $this->addOperands([
            Operand::create('host', \GetOpt\Operand::REQUIRED)
                ->setDescription('Host')
        ]);
    }

    public function handle(GetOpt $getopt) {
        try {
            Cli::out($this->resolve($getopt->getOperand('host')));
        } catch (\Exception $e) {
            Cli::error($e);
        }
    }
}