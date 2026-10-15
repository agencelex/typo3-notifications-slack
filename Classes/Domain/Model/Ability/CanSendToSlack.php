<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Domain\Model\Ability;

use Lex\Notifications\Slack\Message\SlackMessage;

trait CanSendToSlack
{
    abstract public function toSlack(object $notifiable): SlackMessage;
}
