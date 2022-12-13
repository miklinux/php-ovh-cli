# PHP OVH CLI

A simple [OVH API](https://api.ovh.com/console/) client for managing OVH infrastructure, written with PHP.

## Features
**General**
- Configuration wizard
- Cache support
- PHAR package
- Easy to extend with new commands!
- Safe mode (dry-run)

**API**
- Application management

**Dedicated Servers**
- Search (using regular expressions)
- Retrieve server details
- Manage boot mode (rescue, harddisk)
- Manage OVH monitoring
- Access KVM console (HTML5, JNLP supported)
- Perform IPMI reset
- Request hardware reboot
- Manage service renewal
- Manage service engagement

**vRack**
- List associated servers
- Manage vRack servers assignment

**DNS**
- Reverse DNS management
- Resolve reverse DNS to OVH `ns*` hostnames

**Tickets**
- List open tickets
- Open ticket
- Reply to ticket
- Close ticket

**IPs**
- List failover IPs
- Resolve from service name to reverse
- Resolve from reverse to service name

## Requirements

* PHP >= 8
* PHP gmp extension (used by [rlanvin/php-ip](https://github.com/rlanvin/php-ip))
* [Composer](https://getcomposer.org)

## Install

```text
$ git clone https://github.com/miklinux/php-ovh-cli.git
$ cd php-ovh-cli
$ composer update
```

If you prefer to have a standalone executable, then create a PHAR package:
```
$ ./create-phar.php
```

This will package the script among with all its dependencies and install it in `$HOME/bin/ovh-cli`,
if executed as regular user, otherwise in `/usr/local/bin/ovh-cli` if executed as root.

## Configuration
To interact with OVH API you must already have an account at OVH.
The script will take care of requesting an API token for you.

```text
$ ./ovh-cli api:setup
Would you like me to open the browser and create a new OVH API token? [N]:
```
If you answer *YES*, the script will attempt to open the browser to a page where you can generate an OVH API token, pre-populating some fields.
In case you would like to do that manually here's the link: https://api.ovh.com/createToken

```text
Application key      [xxx]:
Application secret   [xxx]:
Consumer key         [xxx]:
Endpoint             [ovh-eu]:
JavaWS binary        [/usr/bin/javaws]:
Cache TTL in seconds [3600]:

Configuration file written: /home/user/.ovh-cli.config.json
```

Once the script is configured you can test if it's working properly:
```text
$ ./ovh-cli api:test
Hi John Doe, OVH API are working properly ;)
```

## Usage
Once the script is correctly configured, you can interact with OVH API using this script.
Please refer to its help for all the information you need.

```text
$ ./ovh-cli --help
```

**NOTE:** If you're concerned about damaging your systems by improper use of this tool, use the `--dry-run` or `-t` command line switch.
This will prevent the execution of potentially harmful **PUT/POST/DELETE** requests, so only **GET**s will be executed.

## Development
This script has been developed to be easily extended, so if you want to contribute and/or add your own commands,
you can do it without particular efforts. This project has been structured in this way:

| Path            | Description                                                          |
|-----------------|----------------------------------------------------------------------|
| ovh-cli         | Simple executable                                                    |
| cli.php         | Main script (to be used with PHAR)                                   |
| create-phar.php | Package the script into an executable: `ovh-cli`                     |
| src/            | Project directory                                                    |
| src/Cli.php     | Contains all the CLI decorators (colors, prompts, formatting, ...)   |
| src/Command.php | Class `OvhCli\Command`: contains all common command logic            |
| src/Config.php  | Class `OvhCli\Config`: manages configuration file                    |
| src/Ovh.php     | Class `OvhCli\Ovh`: contains wrappers to OVH API calls               |
| src/Command/    | Directory containing all the available commands                      |

### Creating new command
See: http://getopt-php.github.io/getopt-php/

In this example we will create a new `hello:world` command.
```
$ cd src/Command
$ mkdir Hello
$ touch Hello/World.php
```

**World.php**
```php
<?php

// The namespace should match the directory structure
namespace OvhCli\Command\Hello;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\Argument;
use OvhCli\Cli;

class World extends \OvhCli\Command
{
    // Command description, used for help
    public $shortDescription = "This is my hello world command";
    // Optional. Add some usage examples.
    public $usageExamples = [
        '-a' => 'Do action A',
        '-b' => 'Do action B',
    ];

    // Here you will define command options/operands
    public function __construct() {
        parent::__construct($this->getName(), [$this, 'handle']);
        $this->addOptions([
            Option::create('a', 'action-a', GetOpt::NO_ARGUMENT)
                ->setDescription('Perform action A'),
            Option::create('b', 'action-b', GetOpt::NO_ARGUMENT)
                ->setDescription('Perform action B'),
        ]);
    }

    // This function will contain the main logic of your command
    public function handle(GetOpt $getopt) {
      $a = $getopt->getOption('action-a');
      $b = $getopt->getOption('action-b');

      if ($a) {
        print "This is action A\n";
      } elseif($b) {
        print "This is action B\n";
      } else {
        print "No action specified. Use --help!\n";
      }
    }
}
```

You can then find your new command in the help (output omitted for brevity):
```
$ ./ovh-cli --help
Usage: ./ovh-cli <command> [options] [operands]

Options:
  -?, --help      Show this help and quit

Commands:
  hello:world        This is my hello world command
```

Get your new command help:
```
$ ./ovh-cli hello:world --help
Usage: ./ovh-cli hello:world [options] [operands]

This is my hello world command

Examples:
  hello:world -a                 Do action A
  hello:world -b                 Do action B

Options:
  -?, --help      Show this help and quit
  -a, --action-a  Perform action A
  -b, --action-b  Perform action B
```

And then, simply execute it!
```text
$ ./ovh-cli hello:world
No action specified. Use --help!
$ ./ovh-cli hello:world -a
This is action A
$ ./ovh-cli hello:world -b
This is action B
```
