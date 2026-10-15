<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance;

use Lex\Notifications\Queue\Message\NotificationQueued;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http\FakeSlack;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notifiable\SlackRecipient;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification\QueuedSlackMessageNotification;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/**
 * ShouldQueue notifications travel through Symfony Messenger. TYPO3 routes messages to the synchronous
 * transport by default; with an async transport the message is serialized, so what it contains matters.
 */
final class QueuedDeliveryTest extends AbstractSlackAcceptanceTestCase
{
    private static function queuedNotification(): QueuedSlackMessageNotification
    {
        return new QueuedSlackMessageNotification(SlackMessage::text('Nightly import done'));
    }

    #[Test]
    public function queuedNotificationIsDeliveredThroughMessenger(): void
    {
        (new SlackRecipient(SlackRoute::webhookNamed('monitoring')))->notify(self::queuedNotification());

        self::assertCount(1, FakeSlack::$requests);
        self::assertSame(self::MONITORING_WEBHOOK, FakeSlack::lastRequest()->url());
    }

    #[Test]
    public function queuedOnDemandNotificationIsDelivered(): void
    {
        $this->dispatcher()->route('slack', SlackRoute::api(self::CHANNEL_ID))->notify(self::queuedNotification());

        self::assertSame(self::API_URL, FakeSlack::lastRequest()->url());
    }

    #[Test]
    public function queuedNotificationToSeveralNotifiables(): void
    {
        $this->dispatcher()->send(
            [new SlackRecipient('webhook:monitoring'), new SlackRecipient('webhook:security')],
            self::queuedNotification()
        );

        self::assertCount(2, FakeSlack::$requests);
    }

    #[Test]
    public function serializedQueueMessageWithANamedWebhookContainsNoSecret(): void
    {
        $serialized = $this->serializeForAsyncTransport(
            new NotificationQueued([new SlackRecipient(SlackRoute::webhookNamed('monitoring'))], self::queuedNotification())
        );

        self::assertStringContainsString('monitoring', $serialized);
        self::assertStringNotContainsString('monitoringWebhookSecret', $serialized);
        self::assertStringNotContainsString(self::BOT_TOKEN, $serialized);
    }

    #[Test]
    public function serializedQueueMessageWithTheWebApiContainsNoBotToken(): void
    {
        $serialized = $this->serializeForAsyncTransport(
            new NotificationQueued([new SlackRecipient(SlackRoute::api(self::CHANNEL_ID))], self::queuedNotification())
        );

        self::assertStringContainsString(self::CHANNEL_ID, $serialized);
        self::assertStringNotContainsString(self::BOT_TOKEN, $serialized);
    }

    #[Test]
    public function serializedQueueMessageWithADirectWebhookUrlContainsTheSecretUrl(): void
    {
        // Documented trade-off: prefer SlackRoute::webhookNamed() for queued notifications.
        $serialized = $this->serializeForAsyncTransport(
            new NotificationQueued([new SlackRecipient(SlackRoute::webhook(self::DIRECT_WEBHOOK))], self::queuedNotification())
        );

        self::assertStringContainsString(self::DIRECT_WEBHOOK, $serialized);
    }

    #[Test]
    public function messageConsumedLaterByAWorkerResolvesTheWebhookFromTheWorkerConfiguration(): void
    {
        $serialized = $this->serializeForAsyncTransport(
            new NotificationQueued([new SlackRecipient(SlackRoute::webhookNamed('monitoring'))], self::queuedNotification())
        );

        // The webhook was rotated between dispatch and consumption.
        $rotated = 'https://hooks.slack.com/services/T0ACCEPT/B0MONITOR/rotatedWebhookSecret';
        $this->overrideSlackConfiguration(['webhooks' => ['monitoring' => $rotated]]);

        $envelope = (new PhpSerializer())->decode(['body' => $serialized]);
        $this->get(MessageBusInterface::class)->dispatch($envelope->getMessage());

        self::assertSame($rotated, FakeSlack::lastRequest()->url());
        self::assertSame(['text' => 'Nightly import done'], FakeSlack::lastRequest()->payload());
    }

    private function serializeForAsyncTransport(NotificationQueued $message): string
    {
        return (new PhpSerializer())->encode(new Envelope($message))['body'];
    }
}
