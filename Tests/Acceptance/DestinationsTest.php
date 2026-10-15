<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance;

use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http\FakeSlack;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notifiable\SlackRecipient;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification\SlackMessageNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every kind of Slack destination: named Incoming Webhooks, direct webhook URLs and the Web API
 * (chat.postMessage with the bot token), as SlackRoute objects or as plain strings.
 */
final class DestinationsTest extends AbstractSlackAcceptanceTestCase
{
    private function notifyRecipientRoutedTo(SlackRoute|string $route): void
    {
        (new SlackRecipient($route))->notify(new SlackMessageNotification(SlackMessage::text('Hello from TYPO3')));
    }

    /** @return iterable<string, array{SlackRoute|string, string}> */
    public static function webhookRoutes(): iterable
    {
        yield 'SlackRoute::webhookNamed() (recommended)' => [SlackRoute::webhookNamed('monitoring'), self::MONITORING_WEBHOOK];
        yield '"webhook:<name>" string alias' => ['webhook:monitoring', self::MONITORING_WEBHOOK];
        yield 'another named webhook' => [SlackRoute::webhookNamed('security'), self::SECURITY_WEBHOOK];
        yield 'SlackRoute::webhook() with a direct URL' => [SlackRoute::webhook(self::DIRECT_WEBHOOK), self::DIRECT_WEBHOOK];
        yield 'direct webhook URL string' => [self::DIRECT_WEBHOOK, self::DIRECT_WEBHOOK];
    }

    #[Test]
    #[DataProvider('webhookRoutes')]
    public function incomingWebhookRoutesPostTheMessageToTheWebhookUrl(SlackRoute|string $route, string $expectedUrl): void
    {
        $this->notifyRecipientRoutedTo($route);

        self::assertCount(1, FakeSlack::$requests);
        $request = FakeSlack::lastRequest();
        self::assertSame('POST', $request->method());
        self::assertSame($expectedUrl, $request->url());
        self::assertSame(['text' => 'Hello from TYPO3'], $request->payload(), 'The webhook decides the channel; no "channel" is sent.');
        self::assertSame('application/json; charset=utf-8', $request->header('Content-Type'));
        self::assertSame('', $request->header('Authorization'), 'The bot token must never be sent to a webhook.');
    }

    /** @return iterable<string, array{SlackRoute|string, string}> */
    public static function webApiRoutes(): iterable
    {
        yield 'SlackRoute::api() with a channel ID' => [SlackRoute::api(self::CHANNEL_ID), self::CHANNEL_ID];
        yield 'channel ID string' => [self::CHANNEL_ID, self::CHANNEL_ID];
        yield 'private channel (group) ID' => [SlackRoute::api('G0123456789'), 'G0123456789'];
        yield 'user ID (direct message from the bot)' => [SlackRoute::api('U0123456789'), 'U0123456789'];
        yield 'DM conversation ID' => ['D0123456789', 'D0123456789'];
        yield 'enterprise user ID' => ['W0123456789', 'W0123456789'];
    }

    #[Test]
    #[DataProvider('webApiRoutes')]
    public function webApiRoutesCallChatPostMessageWithTheBotToken(SlackRoute|string $route, string $expectedChannel): void
    {
        $this->notifyRecipientRoutedTo($route);

        self::assertCount(1, FakeSlack::$requests);
        $request = FakeSlack::lastRequest();
        self::assertSame(self::API_URL, $request->url());
        self::assertSame('Bearer ' . self::BOT_TOKEN, $request->header('Authorization'));
        self::assertSame(['text' => 'Hello from TYPO3', 'channel' => $expectedChannel], $request->payload());
    }

    #[Test]
    public function transferOptionsComeFromTheConfigurationAndRedirectsAreNeverFollowed(): void
    {
        $this->notifyRecipientRoutedTo(SlackRoute::webhookNamed('monitoring'));

        $options = FakeSlack::lastRequest()->options;
        self::assertSame(10, $options['timeout']);
        self::assertSame(5, $options['connect_timeout']);
        self::assertFalse($options['allow_redirects']);
    }

    #[Test]
    public function customTimeoutsAreHonoured(): void
    {
        $this->overrideSlackConfiguration(['timeout' => 30, 'connectTimeout' => 2]);

        $this->notifyRecipientRoutedTo(SlackRoute::api(self::CHANNEL_ID));

        self::assertSame(30, FakeSlack::lastRequest()->options['timeout']);
        self::assertSame(2, FakeSlack::lastRequest()->options['connect_timeout']);
    }

    #[Test]
    public function govSlackWebApiEndpointIsUsedWhenConfigured(): void
    {
        $this->overrideSlackConfiguration(['apiEndpoint' => 'https://slack-gov.com/api']);

        $this->notifyRecipientRoutedTo(SlackRoute::api(self::CHANNEL_ID));

        self::assertSame('https://slack-gov.com/api/chat.postMessage', FakeSlack::lastRequest()->url());
        self::assertSame('Bearer ' . self::BOT_TOKEN, FakeSlack::lastRequest()->header('Authorization'));
    }

    #[Test]
    public function govSlackIncomingWebhooksAreAccepted(): void
    {
        $govWebhook = 'https://hooks.slack-gov.com/services/T0GOV/B0GOV/govWebhookSecret';
        $this->overrideSlackConfiguration(['webhooks' => ['gov' => $govWebhook]]);

        $this->notifyRecipientRoutedTo(SlackRoute::webhookNamed('gov'));
        $this->notifyRecipientRoutedTo($govWebhook);

        self::assertSame([$govWebhook, $govWebhook], array_map(
            static fn($request): string => $request->url(),
            FakeSlack::$requests
        ));
    }

    #[Test]
    public function aNamedWebhookIsResolvedFromTheConfigurationAtSendTime(): void
    {
        $rotated = 'https://hooks.slack.com/services/T0ACCEPT/B0MONITOR/rotatedWebhookSecret';
        $this->overrideSlackConfiguration(['webhooks' => ['monitoring' => $rotated]]);

        $this->notifyRecipientRoutedTo(SlackRoute::webhookNamed('monitoring'));

        self::assertSame($rotated, FakeSlack::lastRequest()->url());
    }
}
