<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Domain\Model\Ability;

use Lex\Notifications\Notification;
use Lex\Notifications\Slack\Configuration\SlackRoute;

trait HasRouteNotificationForSlack
{
    abstract public function routeNotificationForSlack(?Notification $notification = null): SlackRoute|string|null;
}
