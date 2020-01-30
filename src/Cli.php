<?php
/**
 * Class for all cli related stuff such as
 *  - coloring output
 *  - formatting data
 *  - user input
 */
namespace OvhCli;

class Cli {

    const COLOR_RED     = '0;31';
    const COLOR_GREEN   = '0;32';
    const COLOR_YELLOW  = '0;33';
    const COLOR_BLUE    = '0;34';
    const COLOR_MAGENTA = '0;35';
    const COLOR_CYAN    = '0;36';
    const COLOR_WHITE   = '0;37';

    const COLOR_BOLD_RED     = '1;31';
    const COLOR_BOLD_GREEN   = '1;32';
    const COLOR_BOLD_YELLOW  = '1;33';
    const COLOR_BOLD_BLUE    = '1;34';
    const COLOR_BOLD_MAGENTA = '1;35';
    const COLOR_BOLD_CYAN    = '1;36';
    const COLOR_BOLD_WHITE   = '1;37';

    const COLOR_LIGHT_RED     = '0;91';
    const COLOR_LIGHT_GREEN   = '0;92';
    const COLOR_LIGHT_YELLOW  = '0;93';
    const COLOR_LIGHT_BLUE    = '0;94';
    const COLOR_LIGHT_MAGENTA = '0;95';
    const COLOR_LIGHT_CYAN    = '0;96';
    const COLOR_LIGHT_WHITE   = '0;97';

    public static function render($code, $text) {
        return sprintf("\e[%sm%s\e[0m", $code, $text);
    }

    /** 
     * Magic method used for coloring output
     */
    public static function __callStatic($method, $args) {
        // camelCase to CAMEL_CASE
        $name = strtoupper(preg_replace_callback('/([A-Z])/', function ($c) {
            return "_" . $c[1];
        } , $method));
        $color = sprintf('%s::COLOR_%s', __CLASS__, $name);
        if (!defined($color)) {
            throw new \Exception('Invalid color name: '. $method);
        }
        return self::render(constant($color), $args[0]);
    }

    public static function out() {
        $args = func_get_args();
        $args[0] .= PHP_EOL;
        call_user_func_array('printf', $args);
    }

    public static function error() {
        $args = func_get_args();
        if ($args[0] instanceof \Exception) {
            $args[0] = $args[0]->getMessage();
        }
        $args[0] = self::boldRed('ERROR: '). self::red($args[0]);
        call_user_func_array([__CLASS__, 'out'], $args);
        exit(1);
    }

    public static function warning() {
        $args = func_get_args();
        if ($args[0] instanceof \Exception) {
            $args[0] = $args[0]->getMessage();
        }
        $args[0] = self::boldYellow('WARNING: '). self::yellow($args[0]);
        call_user_func_array([__CLASS__, 'out'], $args);
    }

    public static function success() {
        $args = func_get_args();
        $args[0] = self::green($args[0]);
        call_user_func_array([__CLASS__, 'out'], $args);
    }

    public static function read($default = null) {
        $line = trim(fgets(STDIN));
        return empty($line) ? $default : $line;
    }

    public static function prompt($message, $default = null, $default_empty = null) {
        if (strlen($default) == 0) {
            $default = $default_empty;
        }
        printf("%-20s [%s]: ", $message, self::boldWhite($default));
        return self::read($default);
    }

    public static function confirm($message, bool $default) {
        $answer = self::prompt($message, $default ? 'Y' : 'N');
        return strtoupper($answer) == 'Y';
    } 

    public static function formatGrep($data) {
        $ri = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));
        $result = array();
        foreach ($ri as $val) {
            $keys = array();
            foreach (range(0, $ri->getDepth()) as $depth) {
                $keys[] = $ri->getSubIterator($depth)->key();
            }
            self::out("%s=%s", join('|', $keys), $val);
        }
    }

    public static function format($data, array $options = []) {
        if (isset($options['grep']) && $options['grep'] === true) {
            return self::formatGrep($data);
        }
        if (!isset($options['maxSize']))    { $options['maxSize']    = 40; }
        if (!isset($options['indentSize'])) { $options['indentSize'] = 0;  }
        if (!isset($options['indentIncr'])) { $options['indentIncr'] = 2;  }
        $spaces = str_repeat(' ', $options['indentSize']);
        $size = $options['maxSize'] - $options['indentSize'];
        foreach($data as $key => $value) {
          if (is_array($value)) {
            self::out("${spaces}%-${size}s", self::boldWhite($key));
            self::format($value, [
                'maxSize'    => $options['maxSize'], 
                'indentSize' => $options['indentSize'] + $options['indentIncr'],
                'indentIncr' => $options['indentIncr'],
            ]);
            continue;
          }
          if (is_bool($value)) {
            $value = $value ? self::boldGreen('TRUE') : self::boldRed('FALSE');
          } elseif (empty($value)) {
             $value = '-';
          }
          self::out("${spaces}%-${size}s %-${size}s", $key, $value);
        }
    }

    public static function tempFile($contents) {
        $file = tempnam(sys_get_temp_dir(), 'php-ovhcli_');
        file_put_contents($file, $contents);
        return $file;
    }
}