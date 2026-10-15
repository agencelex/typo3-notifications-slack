<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification;

use Lex\Notifications\Notification;
use Lex\Notifications\Slack\Domain\Model\Ability\CanSendToSlack;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notifiable\SlackRecipient;
use SlackPhp\BlockKit\Kit;

/** A typical notification: builds a native Block Kit message from its own data and the notifiable. */
final class DeploymentFailed extends Notification
{
    use CanSendToSlack;

    public function __construct(
        private readonly string $site,
        private readonly string $commit,
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $owner = $notifiable instanceof SlackRecipient ? $notifiable->name : 'team';

        return SlackMessage::fromBlockKit(Kit::message(
            blocks: [
                Kit::header(':rotating_light: Deployment failed'),
                Kit::section(sprintf('*%s* — commit `%s`', $this->site, $this->commit)),
                Kit::divider(),
                Kit::context([Kit::mrkdwnText('Owner: ' . $owner)]),
            ],
            text: 'Deployment failed on ' . $this->site,
        ));
    }
}
