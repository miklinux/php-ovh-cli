<?php

namespace OvhCli;

use OvhCli\Config;
use OvhCli\Cli;
use Ovh\Api;
use GuzzleHttp\Client;
use Phpfastcache\Drivers\Files\Driver as CacheDriver;
use PhpIP\IP;

class Ovh {

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
    
    private function __construct(Config $config) {
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

    public static function setDryRun(bool $flag = true) {
        self::$dryRun = $flag;
    }

    public static function disableCache(bool $flag = true) {
        self::$disableCache = $flag;
    }

    public static function setCacheManager(CacheDriver $driver) {
        self::$cache = $driver;
    }

    public static function getCacheManager() {
        return self::$cache;
    }

    public function getConfig() {
        return $this->config;
    }

    public static function setTimeout($seconds) {
        self::$timeout = (int) $seconds;
        return self;
    }

    public static function setConnectTimeout($seconds) {
        self::$connectTimeout = (int) $seconds;
        return self;
    }

    public static function getInstance(Config $config) {
        if (self::$instance == null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    protected function shouldCache($url) {
        if (self::$disableCache) {
            return false;
        }
        // Some url should not be cached by default
        foreach($this->cacheBlacklist as $regexp) {
            if (preg_match($regexp, $url)) {
                return false;
            }
        }
        return true;
    }

    protected function api($method, $args) {
        return call_user_func_array([ $this->api, $method ], $args);
    }

    public function cachingProxy($method, $args) {
        $url = $args[0];
        $shouldCache = $this->shouldCache($url);
        $key = sha1($url);
        $item = self::$cache->getItem($key);
        switch($method) {
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
                    print(
                        Cli::boldBlue("[DRY-RUN] ") . 
                        Cli::lightBlue(strtoupper($method) ." ${args[0]}\n")
                    );
                    return null;
                }
                // POST, PUT, DELETE calls on same url, invalidate cache
                if ($shouldCache && $item->isHit()) {
                    // Cache PURGE!
                    self::$cache->deleteItem($key);
                }
                // XXX: break intentionally omitted here!
            default:
                $this->api($method, $args);
                break;
        }     
    }

    protected function handleClientException(\GuzzleHttp\Exception\ClientException $e) {
        $response = $e->getResponse();
        $body = $response->getBody(true);
        $status = $response->getStatusCode();
        $json = @json_decode($body);
        throw new \Exception($json->message, $status);
    }

    // Proxy
    public function __call($method, $args) {
        try {
            return $this->cachingProxy($method, $args);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->handleClientException($e);
        }
    }

    public function getUser() {
        return $this->get('/me');
    }

    public function getApiApps() {
        return $this->get('/me/api/application');
    }

    public function getApiApp($app) {
        $app = urlencode($app);
        return $this->get("/me/api/application/${app}");
    }

    public function deleteApiApp($app) {
        $app = urlencode($app);
        return $this->delete("/me/api/application/${app}");
    }

    public function getServers() {                
        return $this->get("/dedicated/server/");
    }

    public function getServerDetails($server) {
        $server = urlencode($server);
        return $this->get("/dedicated/server/${server}");
    }

    public function getServerHardwareSpecs($server) {
        $server = urlencode($server);
        return $this->get("/dedicated/server/${server}/specifications/hardware");
    }

    public function updateServer($server, array $params) {
        $server = urlencode($server);
        return $this->put("/dedicated/server/${server}", $params);
    }

    public function getServerNetworkInterfaces($server) {
        $server = urlencode($server);
        return $this->get("/dedicated/server/${server}/virtualNetworkInterface");
    }

    public function getServerNetworkInterfaceDetails($server, $nic_uuid) {
        $server = urlencode($server);
        $nic_uuid = urlencode($nic_uuid);
        return $this->get("/dedicated/server/${server}/virtualNetworkInterface/${nic_uuid}");
    }

    public function getServerServiceInfo($server) {
        $server = urlencode($server);
        return $this->get("/dedicated/server/${server}/serviceInfos");
    }

    public function getServerIps($server) {
        $server = urlencode($server);
        return $this->get("/dedicated/server/${server}/ips");
    }

    public function getIpDetails($ip) {
        $ip = urlencode($ip);
        return $this->get("/ip/${ip}");
    }

    public function assignServerNetworkInterfaceToVrack($vrack_id, $nic_uuid) {
        $vrack_id = urlencode($vrack_id);
        $nic_uuid = urlencode($nic_uuid);
        return $this->post("/vrack/${vrack_id}/dedicatedServerInterface", [
            'dedicatedServerInterface' => $nic_uuid
        ]);
    }

    public function getServerBootIds($server) {
        $server = urlencode($server);
        return $this->get("/dedicated/server/${server}/boot/");
    }

    public function getServerBootIdDetails($server, $boot_id) {
        $server = urlencode($server);
        $boot_id = urlencode($boot_id);
        return $this->get("/dedicated/server/${server}/boot/${boot_id}");
    }

    public function rebootServer($server) {
        $server = urlencode($server);
        return $this->post("/dedicated/server/${server}/reboot");
    }

    public function getVracks() {
        return $this->get("/vrack");
    }

    public function getVrackDetails($vrack_id) {
        $vrack_id = urlencode($vrack_id);
        return $this->get("/vrack/${vrack_id}");
    }
    
    public function getVrackNetworkInterfaces($vrack_id) {
        $vrack_id = urlencode($vrack_id);
        return $this->get("/vrack/${vrack_id}/dedicatedServerInterface");
    }

    public function getVrackNetworkInterfacesDetails($vrack_id) {
        $vrack_id = urlencode($vrack_id);
        return $this->get("/vrack/${vrack_id}/dedicatedServerInterfaceDetails");
    }

    public function getVrackIpBlocks($vrack_id) {
        $vrack_id = urlencode($vrack_id);
        return $this->get("/vrack/${vrack_id}/ip");
    }

    public function getVrackIpBlock($vrack_id, $ipblock) {
        $vrack_id = urlencode($vrack_id);
        $ipblock = urlencode($ipblock);
        return $this->get("/vrack/${vrack_id}/ip/${ipblock}");
    }

    public function getIpBlockIps($block) {
        $block = urlencode($block);
        return $this->get("/ip/${block}/reverse");
    }

    public function getIpReverse($block, $ip) {
        $block = urlencode($block);
        $ip = urlencode($ip);
        return $this->get("/ip/${block}/reverse/${ip}");
    }

    public function deleteIpReverse($block, $ip) {
        $block = urlencode($block);
        $ip = urlencode($ip);
        return $this->delete("/ip/${block}/reverse/${ip}");
    }

    public function updateIpReverse($block, $ip, $reverse) {
        if ($ip instanceof IP) {
            $ip = $ip->humanReadable();
        }
        $block = urlencode($block);
        return $this->post("/ip/${block}/reverse", [
            'ipReverse' => $ip,
            'reverse'   => $reverse
        ]);
    }
    
    public function getServices() {
      return $this->get('/service');
    }

    public function getService($service) {
      $service = urlencode($service);
      return $this->get("/service/${service}");
    }

    public function requestServerIpmiAccess($server, array $params = []) {
        $server = urlencode($server);
        return $this->post("/dedicated/server/${server}/features/ipmi/access", $params);
    }

    public function getServerIpmiAccessData($server, array $params = []) {
        $server = urlencode($server);
        return $this->get("/dedicated/server/${server}/features/ipmi/access", $params);
    }

    public function resetServerIpmiInterface($server) {
        $server = urlencode($server);
        return $this->post("/dedicated/server/${server}/features/ipmi/resetInterface");
    }
}
