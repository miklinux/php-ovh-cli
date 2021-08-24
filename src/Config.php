<?php

namespace OvhCli;

class Config
{
  private static $instance;

  private $filename;
  private $data = [];

  private function __construct($filename)
  {
    $this->filename = $filename;
    if (file_exists($filename)) {
      $json = @file_get_contents($filename);
      if (!empty($json)) {
        $this->data = json_decode($json, true);
      }
    }
  }

  public function __get($key)
  {
    return $this->data[$key];
  }

  public function __set($key, $value)
  {
    $this->data[$key] = $value;
  }

  public function __isset($key)
  {
    return isset($this->data[$key]) && !empty($this->data[$key]);
  }

  public static function getInstance($filename)
  {
    if (null == self::$instance) {
      self::$instance = new self($filename);
    }

    return self::$instance;
  }

  public function isValid()
  {
    return isset($this->applicationKey)
      && isset($this->applicationSecret, $this->consumerKey, $this->endpoint);
  }

  public function getFilename()
  {
    return $this->filename;
  }

  public function save()
  {
    $json = json_encode($this->data, JSON_PRETTY_PRINT);
    file_put_contents($this->filename, $json.PHP_EOL);
    chmod($this->filename, 0600);
  }
}
