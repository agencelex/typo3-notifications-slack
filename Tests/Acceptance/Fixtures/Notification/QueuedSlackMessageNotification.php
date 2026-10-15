<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification;

use Illuminate\Contracts\Queue\ShouldQueue;

/** Goes through Symfony Messenger (NotificationQueued) instead of being sent in-line. */
final class QueuedSlackMessageNotification extends SlackMessageNotification implements ShouldQueue {}
