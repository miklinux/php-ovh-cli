<?php

namespace OvhCli;

use GuzzleHttp\Client;
use Ovh\Api;
use Phpfastcache\Drivers\Files\Driver as CacheDriver;
use PhpIP\IP;
use PhpIP\IPBlock;

class Ovh
{
  private static $instance;
  private static $disableCache = false;
  private static $cache;
  private static $timeout = 30;
  private static $connectTimeout = 10;
  private static $dryRun = false;

  private $api;
  private $config;

  // RegExps collection to exclude some URLs from caching
  private $cacheBlacklist = [
    // Account information
    '~^/me$~',
    // List of nics inside a vRack
    '~^/vrack/.*/dedicatedServerInterface$~',
    // IPMI requests
    '~/dedicated/server/.*/features/ipmi/?~',
  ];

  private function __construct(Config $config)
  {
    $this->config = $config;

    if (!$config->isValid()) {
      throw new \Exception('invalid configuration file');
    }

    $client = new Client([
      'timeout'         => self::$timeout,
      'connect_timeout' => self::$connectTimeout,
    ]);

    $this->api = new Api(
      $config->applicationKey,
      $config->applicationSecret,
      $config->endpoint,
      $config->consumerKey,
      $client
    );
  }

  // Proxy
  public function __call($method, $args)
  {
    try {
      return $this->cachingProxy($method, $args);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
      $this->handleClientException($e);
    }
  }

  public static function setDryRun(bool $flag = true)
  {
    self::$dryRun = $flag;
  }

  public static function disableCache(bool $flag = true)
  {
    self::$disableCache = $flag;
  }

  public static function setCacheManager(CacheDriver $driver)
  {
    self::$cache = $driver;
  }

  public static function getCacheManager()
  {
    return self::$cache;
  }

  public function getConfig()
  {
    return $this->config;
  }

  public static function setTimeout($seconds)
  {
    self::$timeout = (int) $seconds;

    return self;
  }

  public static function setConnectTimeout($seconds)
  {
    self::$connectTimeout = (int) $seconds;

    return self;
  }

  public static function getInstance(Config $config)
  {
    if (null == self::$instance) {
      self::$instance = new self($config);
    }

    return self::$instance;
  }

  public function cachingProxy($method, $args)
  {
    $url = implode('/', array_map('urlencode', explode('/', $args[0])));
    $shouldCache = $this->shouldCache($url);
    $key = sha1($url);
    $item = self::$cache->getItem($key);

    switch ($method) {
      case 'get':
        // Cache MISS
        if (!$shouldCache) {
          if ($item->isHit()) {
            // Invalidate eventual cache
            self::$cache->deleteItem($key);
          }

          return $this->api($method, $args);
        }
        if (!$result = $item->get()) {
          // Cache WARMING
          $result = $this->api($method, $args);
          // Don't cache empty results
          if (!empty($result)) {
            $item->set($result)
              ->expiresAfter($this->config->cache_ttl);
            self::$cache->save($item);
          }
        } // else CACHE HIT!

        return $result;

      break;

      case 'post':
      case 'put':
      case 'delete':
      // Don't purge cache in dry run
      if (self::$dryRun) {
        echo
          Cli::boldBlue('[DRY-RUN] ').
          Cli::lightBlue(strtoupper($method)." ${args[0]}\n");
        if (!empty($args[1])) {
          echo Cli::lightBlue(json_encode($args[1], JSON_PRETTY_PRINT)).PHP_EOL;
        }

        return null;
      }
      // POST, PUT, DELETE calls on same url, invalidate cache
      if ($shouldCache && $item->isHit()) {
        // Cache PURGE!
        self::$cache->deleteItem($key);
      }
      // XXX: break intentionally omitted here!
      // no break
      default:
        $this->api($method, $args);

      break;
    }
  }

  public function isOvhHostname($hostname)
  {
    return preg_match(
      '/^ns[0-9]+\.ip-[0-9]+-[0-9]+-[0-9]+\.(net|eu)$/',
      $hostname
    );
  }

  public function getDnsReverse($server)
  {
    try {
      $details = $this->getServerDetails($server);
      return rtrim($details['reverse'],'.');
    } catch (\Exception $e) {
      return null;
    }
  }

  public function getBlockFirstIp($block)
  {
    if (is_string($block)) {
      $subnet = IPBlock::create($block);
    }

    return $subnet->getNbAddresses() > 1 ?
      $subnet->getNetworkAddress()->plus(1) :
      $subnet->getFirstIp();
  }

  public function getUser()
  {
    return $this->get('/me');
  }

  public function getApiApps()
  {
    return $this->get('/me/api/application');
  }

  public function getApiApp($app)
  {
    return $this->get("/me/api/application/{$app}");
  }

  public function deleteApiApp($app)
  {
    return $this->delete("/me/api/application/{$app}");
  }

  public function getServers()
  {
    return $this->get('/dedicated/server/');
  }

  public function getServerDetails($server)
  {
    return $this->get("/dedicated/server/{$server}");
  }

  public function getServerHardwareSpecs($server)
  {
    return $this->get("/dedicated/server/{$server}/specifications/hardware");
  }

  public function updateServer($server, array $params)
  {
    return $this->put("/dedicated/server/{$server}", $params);
  }

  public function getServerNetworkInterfaces($server)
  {
    return $this->get("/dedicated/server/{$server}/virtualNetworkInterface");
  }

  public function getServerNetworkInterfaceDetails($server, $nic_uuid)
  {
    return $this->get("/dedicated/server/{$server}/virtualNetworkInterface/{$nic_uuid}");
  }

  public function getServerServiceInfo($server)
  {
    return $this->get("/dedicated/server/{$server}/serviceInfos");
  }

  public function updateServerServiceInfo($server, array $params)
  {
    return $this->put("/dedicated/server/{$server}/serviceInfos", $params);
  }

  public function getServerIps($server)
  {
    return $this->get("/dedicated/server/{$server}/ips");
  }

  public function getIpDetails($ip)
  {
    $ip = urlencode($ip);

    return $this->get("/ip/{$ip}");
  }

  public function resolveAddress($address)
  {
    if (empty($address)) {
      throw new \Exception('Empty address provided');
    }
    if ($this->isOvhHostname($address)) {
      return $address;
    }
    $ip = gethostbyname($address);
    if ($ip === $address) {
      throw new \Exception("Unable to resolve {$address}");
    }
    $info = $this->getIpDetails($ip);
    if ('dedicated' !== $info['type']) {
      throw new \Exception("Not a dedicated server '{$address}'");
    }

    return $info['routedTo']['serviceName'];
  }

  public function assignServerNetworkInterfaceToVrack($vrack_id, $nic_uuid)
  {
    return $this->post("/vrack/{$vrack_id}/dedicatedServerInterface", [
      'dedicatedServerInterface' => $nic_uuid,
    ]);
  }

  public function removeServerNetworkInterfaceFromVrack($vrack_id, $nic_uuid)
  {
    return $this->delete("/vrack/{$vrack_id}/dedicatedServerInterface/{$nic_uuid}");
  }

  public function getServerBootIds($server)
  {
    return $this->get("/dedicated/server/{$server}/boot/");
  }

  public function getServerBootIdsList($server)
  {
    $result = [];
    $bootids = $this->getServerBootIds($server);
    foreach ($bootids as $id) {
      $entry = $this->getServerBootIdDetails($server, $id);
      $result[$entry['bootId']] = $entry;
    }

    return $result;
  }

  public function getServerBootMode($server)
  {
    $details = $this->getServerDetails($server);
    $bootIds = $this->getServerBootIdsList($server);
    $currentBootId = $details['bootId'];
    if (!array_key_exists($currentBootId, $bootIds)) {
      throw new \Exception("Cannot find boot id {$currentBootId} for server {$server}");
    }

    return $bootIds[$currentBootId];
  }

  public function getServerBootIdDetails($server, $boot_id)
  {
    return $this->get("/dedicated/server/{$server}/boot/{$boot_id}");
  }

  public function rebootServer($server)
  {
    return $this->post("/dedicated/server/{$server}/reboot");
  }

  public function getVracksList()
  {
    $result = [];
    foreach ($this->getVracks() as $vrack_id) {
      $details = $this->getVrackDetails($vrack_id);
      $result[$vrack_id] = $details['name'];
    }

    return $result;
  }

  public function findVrack($name)
  {
    $vracks = $this->getVracksList();
    if (array_key_exists($name, $vracks)) {
      return $name;
    }
    $vracks = array_flip($vracks);
    if (!array_key_exists($name, $vracks)) {
      throw new \Exception("Unable to find vRack '{$name}'");
    }

    return $vracks[$name];
  }

  public function getServerVrackInterface($server)
  {
    $uuids = $this->getServerNetworkInterfaces($server);
    foreach ($uuids as $uuid) {
      $net = $this->getServerNetworkInterfaceDetails($server, $uuid);
      if ('vrack' != $net['mode']) {
        continue;
      }
      $vrack_nic_uuid = $uuid;

      break;
    }
    if (empty($vrack_nic_uuid)) {
      throw new \Exception("Unable to determine vRack dedicated interface for '{$server}'");
    }

    return $vrack_nic_uuid;
  }

  public function isInVrack($vrack_id, $vrack_nic_uuid)
  {
    $vrack_nic_uuids = $this->getVrackNetworkInterfaces($vrack_id);

    return in_array($vrack_nic_uuid, $vrack_nic_uuids);
  }

  public function findServerVrack($server)
  {
    $vrack_nic_uuid = $this->getServerVrackInterface($server);
    $in_vrack = false;
    foreach ($this->getVracksList() as $vrack_id => $vrack_name) {
      if ($this->isInVrack($vrack_id, $vrack_nic_uuid)) {
        $in_vrack = $vrack_id;

        break;
      }
    }

    return $in_vrack;
  }

  public function getVracks()
  {
    return $this->get('/vrack');
  }

  public function getVrackDetails($vrack_id)
  {
    return $this->get("/vrack/{$vrack_id}");
  }

  public function getVrackNetworkInterfaces($vrack_id)
  {
    return $this->get("/vrack/{$vrack_id}/dedicatedServerInterface");
  }

  public function getVrackNetworkInterfacesDetails($vrack_id)
  {
    return $this->get("/vrack/{$vrack_id}/dedicatedServerInterfaceDetails");
  }

  public function getVrackIpBlocks($vrack_id)
  {
    return $this->get("/vrack/{$vrack_id}/ip");
  }

  public function getVrackIpBlock($vrack_id, $ipblock)
  {
    return $this->get("/vrack/{$vrack_id}/ip/{$ipblock}");
  }

  public function getIps(array $params = [])
  {
    return $this->get('/ip', $params);
  }

  public function getFailoverIps()
  {
    return $this->getIps(['type' => 'failover']);
  }

  public function moveIp($ip, $destination)
  {
    $ip = urlencode($ip);

    return $this->post("/ip/{$ip}/move", [
      'nexthop' => null,
      'to'      => $destination,
    ]);
  }

  public function getIpBlockIps($block)
  {
    $block = urlencode($block);

    return $this->get("/ip/{$block}/reverse");
  }

  public function getIpReverse($block, $ip)
  {
    $ip = urlencode($ip);
    $block = urlencode($block);

    return $this->get("/ip/{$block}/reverse/{$ip}");
  }

  public function deleteIpReverse($block, $ip)
  {
    $ip = urlencode($ip);
    $block = urlencode($block);

    return $this->delete("/ip/{$block}/reverse/{$ip}");
  }

  public function updateIpReverse($block, $ip, $reverse)
  {
    if ($ip instanceof IP) {
      $ip = $ip->humanReadable();
    }
    $block = urlencode($block);

    return $this->post("/ip/{$block}/reverse", [
      'ipReverse' => $ip,
      'reverse'   => $reverse,
    ]);
  }

  public function getServices()
  {
    return $this->get('/service');
  }

  public function getService($service)
  {
    return $this->get("/service/{$service}");
  }

  public function requestServerIpmiAccess($server, array $params = [])
  {
    return $this->post("/dedicated/server/{$server}/features/ipmi/access", $params);
  }

  public function getServerIpmiAccessData($server, array $params = [])
  {
    return $this->get("/dedicated/server/{$server}/features/ipmi/access", $params);
  }

  public function resetServerIpmiInterface($server)
  {
    return $this->post("/dedicated/server/{$server}/features/ipmi/resetInterface");
  }

  public function getSupportTickets(array $params = [])
  {
    return $this->get('/support/tickets', $params);
  }

  public function createSupportTicket(array $params = [])
  {
    return $this->post('/support/tickets/create', $params);
  }

  public function reopenSupportTicket($id, $body)
  {
    return $this->post('/support/tickets/create', [
      'body' => $body,
    ]);
  }

  public function getSupportTicket($id)
  {
    $result = $this->get("/support/tickets/{$id}");
    ksort($result);

    return $result;
  }

  public function closeSupportTicket($id)
  {
    return $this->post("/support/tickets/{$id}/close");
  }

  public function getSupportTicketMessages($id)
  {
    return $this->get("/support/tickets/{$id}/messages");
  }

  public function replyToSupportTicket($id, $body)
  {
    return $this->post("/support/tickets/{$id}/reply", [
      'body' => $body,
    ]);
  }

  protected function shouldCache($url)
  {
    if (self::$disableCache) {
      return false;
    }
    // Some url should not be cached by default
    foreach ($this->cacheBlacklist as $regexp) {
      if (preg_match($regexp, $url)) {
        return false;
      }
    }

    return true;
  }

  protected function api($method, $args)
  {
    return call_user_func_array([$this->api, $method], $args);
  }

  protected function handleClientException(\GuzzleHttp\Exception\ClientException $e)
  {
    $response = $e->getResponse();
    $body = $response->getBody(true);
    $status = $response->getStatusCode();
    $json = @json_decode($body);

    throw new \Exception($json->message, $status);
  }
}
