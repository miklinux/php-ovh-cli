<?php
/**
 * Class for all cli related stuff such as
 *  - coloring output
 *  - formatting data
 *  - user input.
 */

namespace OvhCli;

class Cli
{
  public const COLOR_RED = '0;31';
  public const COLOR_GREEN = '0;32';
  public const COLOR_YELLOW = '0;33';
  public const COLOR_BLUE = '0;34';
  public const COLOR_MAGENTA = '0;35';
  public const COLOR_CYAN = '0;36';
  public const COLOR_WHITE = '0;37';

  public const COLOR_BOLD_RED = '1;31';
  public const COLOR_BOLD_GREEN = '1;32';
  public const COLOR_BOLD_YELLOW = '1;33';
  public const COLOR_BOLD_BLUE = '1;34';
  public const COLOR_BOLD_MAGENTA = '1;35';
  public const COLOR_BOLD_CYAN = '1;36';
  public const COLOR_BOLD_WHITE = '1;37';

  public const COLOR_LIGHT_RED = '0;91';
  public const COLOR_LIGHT_GREEN = '0;92';
  public const COLOR_LIGHT_YELLOW = '0;93';
  public const COLOR_LIGHT_BLUE = '0;94';
  public const COLOR_LIGHT_MAGENTA = '0;95';
  public const COLOR_LIGHT_CYAN = '0;96';
  public const COLOR_LIGHT_WHITE = '0;97';

  /**
   * Magic method used for coloring output.
   *
   * @param mixed $method
   * @param mixed $args
   */
  public static function __callStatic($method, $args)
  {
    // camelCase to CAMEL_CASE
    $name = strtoupper(preg_replace_callback('/([A-Z])/', function ($c) {
      return '_'.$c[1];
    }, $method));
    $color = sprintf('%s::COLOR_%s', __CLASS__, $name);
    if (!defined($color)) {
      throw new \Exception('Invalid color name: '.$method);
    }

    return self::render(constant($color), $args[0]);
  }

  public static function render($code, $text)
  {
    return sprintf("\e[%sm%s\e[0m", $code, $text);
  }

  public static function out()
  {
    $args = func_get_args();
    $args[0] .= PHP_EOL;
    call_user_func_array('printf', $args);
  }

  public static function error()
  {
    $args = func_get_args();
    if ($args[0] instanceof \Exception) {
      $args[0] = $args[0]->getMessage();
    }
    $args[0] = self::boldRed('ERROR: ').self::red($args[0]);
    call_user_func_array([__CLASS__, 'out'], $args);

    exit(1);
  }

  public static function warning()
  {
    $args = func_get_args();
    if ($args[0] instanceof \Exception) {
      $args[0] = $args[0]->getMessage();
    }
    $args[0] = self::boldYellow('WARNING: ').self::yellow($args[0]);
    call_user_func_array([__CLASS__, 'out'], $args);
  }

  public static function success()
  {
    $args = func_get_args();
    $args[0] = self::boldGreen('OK: ').self::green($args[0]);
    call_user_func_array([__CLASS__, 'out'], $args);
  }

  public static function read($default = null)
  {
    $line = trim(fgets(STDIN));

    return empty($line) ? $default : $line;
  }

  public static function prompt($message, $default = null, $default_empty = null)
  {
    if (0 == strlen($default)) {
      $default = $default_empty;
    }
    if (strlen($default) > 0) {
      printf('%-20s [%s]: ', $message, self::boldWhite($default));
    } else {
      printf('%s: ', $message);
    }

    return self::read($default);
  }

  public static function multiChoicePrompt($message, $items, $default = null)
  {
    foreach ($items as $i => $v) {
      $n = $i + 1;
      $option = ($n == $default) ? Cli::boldWhite($v) : $v;
      printf("  %-3s %s\n", $n.')', $option);
    }
    // Old-style programming to the rescue!!!
    retry:
    $choice = self::prompt($message, $default);
    if (!is_numeric($choice)) {
      goto retry;
    }
    $newvalue = $items[$choice - 1];
    if (empty($newvalue)) {
      goto retry;
    }

    return $newvalue;
  }

  public static function anykey($message)
  {
    printf("%s\nPress any key to continue\n", $message);
    self::read($none);
  }

  public static function confirm($message, bool $default)
  {
    $answer = self::prompt($message, $default ? 'Y' : 'N');

    return 'Y' == strtoupper($answer);
  }

  public static function formatGrep($data)
  {
    $ri = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));
    $result = [];
    foreach ($ri as $val) {
      $keys = [];
      foreach (range(0, $ri->getDepth()) as $depth) {
        $keys[] = $ri->getSubIterator($depth)->key();
      }
      if (is_bool($val)) {
        $val = $val ? 'true' : 'false';
      }
      self::out('%s=%s', join('|', $keys), $val);
    }
  }

  public static function format($data, array $options = [])
  {
    if (isset($options['grep']) && true === (bool) $options['grep']) {
      return self::formatGrep($data);
    }
    if (!isset($options['maxSize'])) {
      $options['maxSize'] = 40;
    }
    if (!isset($options['indentSize'])) {
      $options['indentSize'] = 0;
    }
    if (!isset($options['indentIncr'])) {
      $options['indentIncr'] = 2;
    }
    if (!isset($options['noArrayIndex'])) {
      $options['noArrayIndex'] = false;
    }
    $spaces = str_repeat(' ', $options['indentSize']);
    $size = $options['maxSize'] - $options['indentSize'];
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        self::out("{$spaces}%-{$size}s", self::boldWhite($key));
        self::format($value, array_merge($options, [
          'maxSize'    => $options['maxSize'],
          'indentSize' => $options['indentSize'] + $options['indentIncr'],
          'indentIncr' => $options['indentIncr'],
        ]));

        continue;
      }
      if (is_bool($value)) {
        $value = $value ? self::boldGreen('true') : self::boldRed('false');
      } elseif (empty($value)) {
        $value = '-';
      }
      if (true == $options['noArrayIndex']) {
        self::out("{$spaces}%-{$size}s", $value);
      } else {
        self::out("{$spaces}%-{$size}s %-{$size}s", $key, $value);
      }
    }
  }

  public static function tempFile($contents)
  {
    $file = tempnam(sys_get_temp_dir(), 'php-ovhcli_');
    file_put_contents($file, $contents);

    return $file;
  }
}
