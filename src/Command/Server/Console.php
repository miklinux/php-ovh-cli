<?php

namespace OvhCli\Command\Server;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Console extends \OvhCli\Command
{
  public const DEFAULT_ATTEMPTS = 10;
  public const DEFAULT_DELAY = 5;
  public const DEFAULT_TTL = 15;
  public const DEFAULT_MODE = 'jnlp';
  public $shortDescription = 'Open a KVM session';

  private $modes = [
    'jnlp'  => 'kvmipJnlp',
    'html5' => 'kvmipHtml5URL',
  ];

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('server', \GetOpt\Operand::REQUIRED)
        ->setDescription('Server name'),
    ]);

    $this->addOptions([
      Option::create('a', 'attempts', GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Max numbers of attempts (default: '.self::DEFAULT_ATTEMPTS.')')
        ->setDefaultValue(self::DEFAULT_ATTEMPTS),
      Option::create('d', 'delay', GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Delay between attempts (default: '.self::DEFAULT_DELAY.')')
        ->setDefaultValue(self::DEFAULT_DELAY),
      Option::create('x', 'ttl', GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Session TTL in minutes (default: '.self::DEFAULT_TTL.')')
        ->setDefaultValue(self::DEFAULT_TTL),
      Option::create('m', 'mode', GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Console mode (default: '.self::DEFAULT_MODE.')')
        ->setDefaultValue(self::DEFAULT_MODE),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    $server = $this->resolve($getopt->getOperand('server'));
    $attempts = (int) $getopt->getOption('attempts');
    $delay = (int) $getopt->getOption('delay');
    $ttl = (int) $getopt->getOption('ttl');
    $mode = $getopt->getOption('mode');

    $availableModes = $this->getAvailableModes();
    if (!in_array($mode, $availableModes)) {
      Cli::error("Invalid console mode '%s'. Valid ones are: %s", $mode, implode(', ', $availableModes));
    }

    if ('jnlp' == $mode && !is_executable($this->config->javaws)) {
      Cli::error("Not an valid executable '%s' - Please run api::setup!", $this->config->javaws);
    }

    // Disable cache and dry run by default
    \OvhCli\Ovh::disableCache(true);
    \OvhCli\Ovh::setDryRun(false);

    try {
      Cli::out('Requesting %s IPMI access for %s (TTL %d) ...', strtoupper($mode), $server, $ttl);
      $access = $this->ovh()->requestServerIpmiAccess($server, [
        'ttl'  => $ttl,
        'type' => $this->modes[$mode],
      ]);
      Cli::out('IPMI access request sent... Waiting for session (This may take a while)...');
    } catch (\Exception $e) {
      Cli::error($e);
    }

    for ($i = 1; $i <= $attempts; ++$i) {
      if ($i > 1) {
        sleep($delay);
      }
      Cli::out('[%d/%d] Attempting to retrieve access data ...', $i, $attempts);

      try {
        $data = $this->ovh()->getServerIpmiAccessData($server, [
          'type' => $this->modes[$mode],
        ]);

        break;
      } catch (\Exception $e) {
        continue;
      }
    }

    if (empty($data)) {
      Cli::error('Server returned no data. Please try again!');
    }

    switch ($mode) {
      case 'jnlp':
        $jnlp = Cli::tempFile($data['value']);
        Cli::out('JNLP file saved to %s ...', $jnlp);
        Cli::out('Spawning console client ...');
        $command = sprintf('%s %s', $this->config->javaws, $jnlp);
        system($command);
        unlink($jnlp);

      break;

      case 'html5':
        Cli::out('Opening browser to: %s', $data['value']);
        $xdgOpen = trim(@shell_exec('which xdg-open'));
        if (!empty($xdgOpen)) {
          shell_exec(sprintf("%s '%s'", $xdgOpen, $data['value']));
        }

        break;
    }
  }

  private function getAvailableModes()
  {
    return array_keys($this->modes);
  }
}
