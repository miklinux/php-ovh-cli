<?php

namespace OvhCli\Command\Api;

use GetOpt\GetOpt;
use OvhCli\Cli;

class Setup extends \OvhCli\Command
{
  public const DEFAULT_CACHE_TTL = 86400;

  public $shortDescription = 'Configure OVH credentials';

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);
  }

  public function handle(GetOpt $getopt)
  {
    if (file_exists(CONFIG_FILE) && !is_writable(CONFIG_FILE)) {
      Cli::out("Please check config file '%s' permissions since it's not writable.", CONFIG_FILE);
    }
    $this->generateToken();

    $this->config->applicationKey = Cli::prompt(
      'Application key',
      $this->config->applicationKey
    );

    $this->config->applicationSecret = Cli::prompt(
      'Application secret',
      $this->config->applicationSecret
    );

    $this->config->consumerKey = Cli::prompt(
      'Consumer key',
      $this->config->consumerKey
    );

    $this->config->endpoint = Cli::prompt(
      'Endpoint',
      $this->config->endpoint,
      'ovh-eu'
    );

    $this->config->javaws = Cli::prompt(
      'JavaWS binary',
      $this->config->javaws,
      trim(@shell_exec('which javaws'))
    );

    $this->config->editor = Cli::prompt(
      'Default text editor',
      $this->config->editor,
      trim(@shell_exec('which vim'))
    );

    $this->config->cache_ttl = Cli::prompt(
      'Cache TTL in seconds',
      $this->config->cache_ttl,
      self::DEFAULT_CACHE_TTL
    );

    $this->config->save();
    Cli::out("\nConfiguration file written: %s", CONFIG_FILE);
  }

  protected function getTokenUrl()
  {
    $query = [
      // Token application description
      'applicationName'        => 'api_'.getenv('USER'),
      'applicationDescription' => 'php-ovh-cli',
      // Token permissions
      'GET'    => '/*',
      'POST'   => '/*',
      'PUT'    => '/*',
      'DELETE' => '/*',
      // Token duration in seconds
      'duration' => (24 * 60 * 60) * 30,
    ];

    return 'https://api.ovh.com/createToken/index.cgi?'.http_build_query($query);
  }

  protected function generateToken()
  {
    $tokenUrl = $this->getTokenUrl();
    $configExists = file_exists(CONFIG_FILE);
    $xdgOpen = trim(@shell_exec('which xdg-open'));
    if (empty($xdgOpen)) {
      Cli::out("Please follow this link to create your OVH API token:\n%s\n", $tokenUrl);

      return;
    }
    $answer = Cli::confirm('Would you like me to open the browser and create a new OVH API token?', !$configExists);
    if ($answer) {
      Cli::out("Opening the web browser... If you don't see anything, please check your already opened instances...");
      shell_exec(sprintf("%s '%s'", $xdgOpen, $tokenUrl));
    } elseif (!$configExists) {
      Cli::out("Please follow this link to create your OVH API token:\n%s", $tokenUrl);
    }
    echo PHP_EOL;
  }
}
