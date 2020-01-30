<?php

namespace OvhCli\Command\Cache;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Ovh;
use OvhCli\Cli;

class Clear extends \OvhCli\Command
{
    public $shortDescription = "Clears the cache";

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);
    }

    public function handle(GetOpt $getopt) {
        Ovh::getCacheManager()->clear();
        Cli::success('Cache has been cleared!');
    }
}