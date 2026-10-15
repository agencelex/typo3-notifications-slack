<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Channel;

use InvalidArgumentException;
use Lex\Notifications\Channel\ChannelInterface;
use Lex\Notifications\Notification;
use Lex\Notifications\Slack\Extension;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Transport\SlackTransport;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('notifications.channel')]
final class SlackChannel implements ChannelInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const CHANNEL = Extension::CHANNEL_NAME;

    public function __construct(private readonly SlackTransport $transport) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notifiable, 'routeNotificationFor')) {
            throw new InvalidArgumentException('Notifiable must implement routeNotificationFor().');
        }
        $rawRoute = $notifiable->routeNotificationFor(self::CHANNEL, $notification);
        if ($rawRoute === null || $rawRoute === '') {
            // Normally filtered by NotificationSender before invoking the channel.
            return;
        }
        $route = match (true) {
            $rawRoute instanceof SlackRoute => $rawRoute,
            is_string($rawRoute) => SlackRoute::fromString($rawRoute),
            default => throw new InvalidArgumentException('Slack route must be SlackRoute or a destination string.'),
        };
        if (!method_exists($notification, 'toSlack')) {
            throw new InvalidArgumentException('Notification must implement toSlack().');
        }
        $message = $notification->toSlack($notifiable);
        if (!$message instanceof SlackMessage) {
            throw new InvalidArgumentException('toSlack() must return SlackMessage.');
        }
        $this->transport->send($message, $route);
    }

    public function getName(): string
    {
        return self::CHANNEL;
    }
}
