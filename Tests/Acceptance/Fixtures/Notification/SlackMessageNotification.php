<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification;

use Lex\Notifications\Notification;
use Lex\Notifications\Slack\Domain\Model\Ability\CanSendToSlack;
use Lex\Notifications\Slack\Message\SlackMessage;

/** Delivers a prepared SlackMessage; lets each test show one way of building the message. */
class SlackMessageNotification extends Notification
{
    use CanSendToSlack;

    /** @param list<string> $channels */
    public function __construct(
        private readonly SlackMessage $message,
        private readonly array $channels = ['slack'],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return $this->message;
    }
}
