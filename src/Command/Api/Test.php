<?php

namespace OvhCli\Command\Api;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class Test extends \OvhCli\Command
{
    public $shortDescription = "Tests if API is working";

    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);
    }

    public function handle(GetOpt $getopt) {
        try {
            $me = $this->ovh()->getUser();
            Cli::success("Hi %s %s, OVH API are working properly ;)",
                $me['firstname'],
                $me['name']
            );
        } catch (\Exception $e) {
            Cli::error($e);
        }
    }

}