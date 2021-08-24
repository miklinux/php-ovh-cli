<?php

namespace OvhCli;

use GetOpt\GetOpt;

class Command extends \GetOpt\Command
{
  protected $config;
  protected $name;
  protected $usageExamples = [];

  public function __construct($name, $handler, $options = null)
  {
    parent::__construct($name, $handler, $options);
    $this->config = Config::getInstance(CONFIG_FILE);
    if (empty($this->longDescription) && !empty($this->shortDescription)) {
      $this->longDescription = $this->shortDescription;
    }
    $this->usageExamples();
  }

  public function getName()
  {
    if (null === $this->name) {
      $class = substr(get_class($this), strlen('\\OvhCli\\Command'));
      $class = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $class));
      $this->name = str_replace('\\', ':', $class);
    }

    return $this->name;
  }

  public function missingArgument(GetOpt $getopt, $message)
  {
    Cli::out($message.PHP_EOL);
    echo $getopt->getHelpText();

    exit();
  }

  public function resolve($address)
  {
    try {
      return $this->ovh()->resolveAddress($address);
    } catch (\Exception $e) {
      Cli::error($e);
    }
  }

  public function ovh()
  {
    try {
      return Ovh::getInstance($this->config);
    } catch (\Exception $e) {
      Cli::error(
        "ERROR: Unable to instantiate OVH API: '%s'".PHP_EOL.
        'Parameters might be wrong, please try to reconfigure.',
        $e->getMessage()
      );
    }
  }

  protected function usageExamples()
  {
    if (empty($this->usageExamples)) {
      return;
    }
    $this->longDescription .= "\n\nExamples:\n";
    foreach ($this->usageExamples as $command => $description) {
      $this->longDescription .= sprintf("  %-50s %s\n", $this->getName().' '.$command, $description);
    }
    $this->longDescription = trim($this->longDescription);
  }
}
