<?php

namespace OvhCli\Command;

use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use OvhCli\Cli;

class Ticket extends \OvhCli\Command
{
  public const DELIMITER = '====== ^^ WRITE YOUR TEXT ABOVE ^^ ==== DO NOT CHANGE THIS LINE ======';

  public $shortDescription = 'Manage Support Tickets';
  public $usageExamples = [
    '--list'               => 'List all open tickets',
    '--new'                => 'Create new ticket',
    '<ticket-id>'          => 'Shows all messages concerning a ticket',
    '<ticket-id> --last'   => 'Display just the last message',
    '<ticket-id> --reply'  => 'Reply to last message',
    '<ticket-id> --close'  => 'Close the ticket',
    '<ticket-id> --reopen' => 'Reopen a closed ticket',
  ];

  protected $categories = [
    'billing',
    'assistance',
    'incident',
  ];

  protected $subcategories = [
    'alerts',
    'autorenew',
    'bill',
    'down',
    'inProgress',
    'new',
    'other',
    'perfs',
    'start',
    'usage',
  ];

  protected $products = [
    'adsl',
    'cdn',
    'dedicated',
    'dedicated-billing',
    'dedicated-other',
    'dedicatedcloud',
    'domain',
    'exchange',
    'fax',
    'hosting',
    'housing',
    'iaas',
    'mail',
    'network',
    'publiccloud',
    'sms',
    'ssl',
    'storage',
    'telecom-billing',
    'telecom-other',
    'vac',
    'voip',
    'vps',
    'web-billing',
    'web-other',
  ];

  public function __construct()
  {
    parent::__construct($this->getName(), [$this, 'handle']);

    $this->addOperands([
      Operand::create('ticket-id', Operand::OPTIONAL)
        ->setDescription('Ticket id'),
    ]);

    $this->addOptions([
      Option::create('k', 'new', GetOpt::NO_ARGUMENT)
        ->setDescription('Open new ticket'),
      Option::create('l', 'list', GetOpt::NO_ARGUMENT)
        ->setDescription('List available tickets'),
      Option::create('x', 'last', GetOpt::NO_ARGUMENT)
        ->setDescription('Get last message'),
      Option::create('r', 'reply', GetOpt::NO_ARGUMENT)
        ->setDescription('Get last message'),
      Option::create('c', 'close', GetOpt::NO_ARGUMENT)
        ->setDescription('Close specified ticket'),
      Option::create('o', 'reopen', GetOpt::NO_ARGUMENT)
        ->setDescription('Reopen specified ticket'),
    ]);
  }

  public function handle(GetOpt $getopt)
  {
    \OvhCli\Ovh::disableCache();
    $ticketId = $getopt->getOperand('ticket-id');
    $list = (bool) $getopt->getOption('list');
    $reply = (bool) $getopt->getOption('reply');
    $last = (bool) $getopt->getOption('last');
    $open = (bool) $getopt->getOption('new');
    $close = (bool) $getopt->getOption('close');
    $reopen = (bool) $getopt->getOption('reopen');
    if ($open) {
      return $this->openTicket();
    }
    if ($list) {
      $ticketIds = $this->ovh()->getSupportTickets([
        'status' => 'open',
      ]);
      foreach ($ticketIds as $ticketId) {
        $ticket = $this->ovh()->getSupportTicket($ticketId);
        $awaitingReply = ('support' == $ticket['lastMessageFrom']);
        if ($awaitingReply) {
          $subject = Cli::red($ticket['subject']);
          $mark = Cli::boldRed('  *NEW*');
        } else {
          $subject = $ticket['subject'];
          $mark = null;
        }
        printf("%-10s %s\n", $ticketId, '[TICKET#'.$ticket['ticketNumber'].'] '.$subject.$mark);
      }
    } elseif (!empty($ticketId)) {
      $ticket = $this->ovh()->getSupportTicket($ticketId);
      $messages = $this->ovh()->getSupportTicketMessages($ticketId);
      if ($reply) {
        return $this->replyMessage(array_shift($messages));
      }
      if ($close) {
        return $this->closeTicket($ticket);
      }
      if ($reopen) {
        return $this->reopenTicket($ticket);
      }
      if ($last) {
        return $this->renderMessage(array_shift($messages));
      }
      Cli::format($ticket);
      foreach ($messages as $message) {
        $this->renderMessage($message);
      }
      echo PHP_EOL;
    } else {
      echo $getopt->getHelpText();

      exit();
    }
  }

  public function closeTicket($ticket)
  {
    if ('closed' == $ticket['state']) {
      Cli::error("Ticket [{$ticket['ticketId']} : {$ticket['subject']}] is already closed");
    }
    if (!Cli::confirm("Are you sure to close ticket [{$ticket['ticketId']} : {$ticket['subject']}] ?", false)) {
      return false;
    }

    try {
      $this->ovh()->closeSupportTicket($ticket['ticketId']);
      Cli::success('Support ticket has been closed');
    } catch (\Exception $e) {
      Cli::error($e);
    }
  }

  public function reopenTicket($ticket)
  {
    if ('open' == $ticket['state']) {
      Cli::error("Ticket [{$ticket['ticketId']} : {$ticket['subject']}] is already open");
    }
    $reply = $this->editMessage($message);

    try {
      $this->ovh()->reopenSupportTicket($message['ticketId'], $reply);
      Cli::success('Ticket has been reopened and your reply has been sent!');
    } catch (\Exception $e) {
      Cli::error($e);
    }
  }

  public function renderMessage($message)
  {
    printf(
      "\n%s %s -- %s %s\n\n",
      Cli::boldWhite('Date:'),
      Cli::yellow($message['creationDate']),
      Cli::boldWhite('From:'),
      Cli::boldYellow(strtoupper($message['from']))
    );
    echo Cli::lightBlue(wordwrap(trim($message['body']), 80)).PHP_EOL;
  }

  public function editMessage($message, $tempFile = null)
  {
    if (!is_executable($this->config->editor)) {
      Cli::error('No editor has been set! Please run api:setup');
    }
    if (null == $tempFile) {
      $tempFile = Cli::tempFile(sprintf(
        "\n\n%s\n\n%s",
        self::DELIMITER,
        str_replace("\r", '', $message['body']), // Remove MS carriage return
      ));
    }
    $cmd = sprintf('%s %s > `tty`; clear', $this->config->editor, $tempFile);
    system($cmd);

    $reply = null;
    $delimiterFound = false;
    foreach (file($tempFile) as $line) {
      if (self::DELIMITER == trim($line)) {
        $delimiterFound = true;

        break;
      }
      $reply .= $line;
    }
    if (!$delimiterFound) {
      Cli::anykey(Cli::boldRed('ERROR: Unable to find message delimiter! I will create a new message!'));
      unlink($tempFile);

      return $this->editMessage($message);
    }
    $reply = trim($reply);
    if (empty($reply)) {
      Cli::anykey(Cli::boldRed('ERROR: Cannot send an empty message!'));

      return $this->editMessage($message, $tempFile);
    }
    echo PHP_EOL.Cli::lightBlue(wordwrap($reply, 80)).PHP_EOL.PHP_EOL;
    if (!Cli::confirm('Do you want to send this message?', false)) {
      return $this->editMessage($message, $tempFile);
    }
    unlink($tempFile);

    return $reply;
  }

  public function replyMessage($message)
  {
    $reply = $this->editMessage($message);

    try {
      $this->ovh()->replyToSupportTicket($message['ticketId'], $reply);
      Cli::success('Reply has been sent!');
    } catch (\Exception $e) {
      Cli::error($e);
    }
  }

  protected function openTicket()
  {
    $request = [];
    $request['category'] = Cli::multiChoicePrompt(
      'Ticket category',
      $this->categories,
      '3'
    );
    $request['subcategory'] = Cli::multiChoicePrompt(
      'Ticket subcategory',
      $this->subcategories,
      '6',
    );
    $request['product'] = Cli::multiChoicePrompt(
      'Related to product',
      $this->products,
      '3',
    );
    $request['subject'] = Cli::prompt('Ticket subject', 'Generic request');
    $request['serviceName'] = Cli::prompt('Service name');
    $request['body'] = $this->editMessage([ 'body' => null ]);
    $res = $this->ovh()->createSupportTicket($request);
    Cli::success('The ticket has been created!');
    return $res;
  }
}
