<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notifiable;

use Lex\Notifications\Domain\Model\Ability\Notifiable;
use Lex\Notifications\Notification;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Domain\Model\Ability\HasRouteNotificationForSlack;

/** A domain object (user, team, site…) that knows where its Slack notifications go. */
final class SlackRecipient
{
    use Notifiable;
    use HasRouteNotificationForSlack;

    public function __construct(
        private readonly SlackRoute|string|null $slackRoute,
        public readonly string $name = 'Ada',
    ) {}

    public function routeNotificationForSlack(?Notification $notification = null): SlackRoute|string|null
    {
        return $this->slackRoute;
    }
}
