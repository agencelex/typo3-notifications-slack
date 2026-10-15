<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Unit\Channel;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Lex\Notifications\AnonymousNotifiable;
use Lex\Notifications\Notification;
use Lex\Notifications\Slack\Channel\SlackChannel;
use Lex\Notifications\Slack\Configuration\SlackConfiguration;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Transport\SlackTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\RequestFactory;

final class SlackChannelTest extends TestCase
{
    private const WEBHOOK = 'https://hooks.slack.com/services/T/B/secret';

    private static function channel(RequestFactory $factory): SlackChannel
    {
        return new SlackChannel(new SlackTransport($factory, new SlackConfiguration([
            'botToken' => 'xoxb-test',
            'webhooks' => ['monitoring' => self::WEBHOOK],
        ])));
    }

    private static function notification(mixed $message = null): Notification
    {
        return new class ($message ?? SlackMessage::text('From TYPO3')) extends Notification {
            public function __construct(private readonly mixed $message) {}
            public function via(object $notifiable): array { return ['slack']; }
            public function toSlack(object $notifiable): mixed { return $this->message; }
        };
    }

    private static function routedTo(mixed $route): AnonymousNotifiable
    {
        return (new AnonymousNotifiable())->route('slack', $route);
    }

    /** RequestFactory expecting exactly one request to $url whose JSON body equals $payload. */
    private function factoryExpecting(string $url, array $payload, string $responseBody): RequestFactory
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::once())->method('request')->with(
            $url,
            'POST',
            self::callback(static fn(array $options): bool => json_decode($options['body'], true) === $payload)
        )->willReturn(new Response(200, [], $responseBody));
        return $factory;
    }

    private function factoryExpectingNoRequest(): RequestFactory
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::never())->method('request');
        return $factory;
    }

    #[Test]
    public function nameIsSlack(): void
    {
        self::assertSame('slack', self::channel($this->createStub(RequestFactory::class))->getName());
        self::assertSame('slack', SlackChannel::CHANNEL);
    }

    /** @return iterable<string, array{SlackRoute|string, string, array<string,mixed>, string}> */
    public static function routes(): iterable
    {
        $api = ['https://slack.com/api/chat.postMessage', ['text' => 'From TYPO3', 'channel' => 'C0123456789'], '{"ok":true,"ts":"1.234"}'];
        $webhook = [self::WEBHOOK, ['text' => 'From TYPO3'], 'ok'];

        yield 'SlackRoute::api()' => [SlackRoute::api('C0123456789'), ...$api];
        yield 'channel ID string' => ['C0123456789', ...$api];
        yield 'SlackRoute::webhookNamed()' => [SlackRoute::webhookNamed('monitoring'), ...$webhook];
        yield '"webhook:<name>" string' => ['webhook:monitoring', ...$webhook];
        yield 'SlackRoute::webhook()' => [SlackRoute::webhook(self::WEBHOOK), ...$webhook];
        yield 'webhook URL string' => [self::WEBHOOK, ...$webhook];
    }

    #[Test]
    #[DataProvider('routes')]
    public function routedNotificationIsSent(SlackRoute|string $route, string $url, array $payload, string $responseBody): void
    {
        self::channel($this->factoryExpecting($url, $payload, $responseBody))
            ->send(self::routedTo($route), self::notification());
    }

    #[Test]
    public function routeIsResolvedWithTheNotification(): void
    {
        $notification = self::notification();
        $notifiable = new class {
            public ?Notification $received = null;
            public function routeNotificationFor(string $channel, Notification $notification): ?string
            {
                $this->received = $notification;
                return $channel === 'slack' ? 'webhook:monitoring' : null;
            }
        };

        self::channel($this->factoryExpecting(self::WEBHOOK, ['text' => 'From TYPO3'], 'ok'))->send($notifiable, $notification);

        self::assertSame($notification, $notifiable->received);
    }

    /** @return iterable<string, array{mixed}> */
    public static function emptyRoutes(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
    }

    #[Test]
    #[DataProvider('emptyRoutes')]
    public function notifiableWithoutRouteIsSkipped(mixed $route): void
    {
        self::channel($this->factoryExpectingNoRequest())->send(self::routedTo($route), self::notification());
    }

    #[Test]
    public function notifiableWithoutRouteNotificationForIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Notifiable must implement routeNotificationFor().');

        self::channel($this->factoryExpectingNoRequest())->send(new \stdClass(), self::notification());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidRouteTypes(): iterable
    {
        yield 'integer' => [42];
        yield 'array' => [['C0123456789']];
        yield 'true' => [true];
    }

    #[Test]
    #[DataProvider('invalidRouteTypes')]
    public function unsupportedRouteTypeIsRejected(mixed $route): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slack route must be SlackRoute or a destination string.');

        self::channel($this->factoryExpectingNoRequest())->send(self::routedTo($route), self::notification());
    }

    #[Test]
    public function invalidDestinationStringIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::channel($this->factoryExpectingNoRequest())->send(self::routedTo('#general'), self::notification());
    }

    #[Test]
    public function notificationWithoutToSlackIsRejected(): void
    {
        $notification = new class extends Notification {
            public function via(object $notifiable): array { return ['slack']; }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification must implement toSlack().');

        self::channel($this->factoryExpectingNoRequest())->send(self::routedTo('webhook:monitoring'), $notification);
    }

    #[Test]
    public function toSlackMustReturnASlackMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('toSlack() must return SlackMessage.');

        self::channel($this->factoryExpectingNoRequest())
            ->send(self::routedTo('webhook:monitoring'), self::notification(['text' => 'array instead of SlackMessage']));
    }

    #[Test]
    public function toSlackReceivesTheNotifiable(): void
    {
        $notifiable = self::routedTo('webhook:monitoring');
        $notification = new class extends Notification {
            public ?object $received = null;
            public function via(object $notifiable): array { return ['slack']; }
            public function toSlack(object $notifiable): SlackMessage
            {
                $this->received = $notifiable;
                return SlackMessage::text('Hi');
            }
        };

        self::channel($this->factoryExpecting(self::WEBHOOK, ['text' => 'Hi'], 'ok'))->send($notifiable, $notification);

        self::assertSame($notifiable, $notification->received);
    }
}
