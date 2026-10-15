<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance;

use InvalidArgumentException;
use Lex\Notifications\AnonymousNotifiable;
use Lex\Notifications\Slack\Channel\SlackChannel;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http\FakeSlack;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http\RecordedRequest;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notifiable\SlackRecipient;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification\DeploymentFailed;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification\SlackMessageNotification;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every public entry point of the notification system that can end up in Slack:
 * notifiable models, the dispatcher service, on-demand routes and the channel itself.
 */
final class EntryPointsTest extends AbstractSlackAcceptanceTestCase
{
    private static function textNotification(string $text = 'Cache flushed'): SlackMessageNotification
    {
        return new SlackMessageNotification(SlackMessage::text($text));
    }

    /** @return list<string> */
    private static function sentUrls(): array
    {
        return array_map(static fn(RecordedRequest $request): string => $request->url(), FakeSlack::$requests);
    }

    // Notifiable models

    #[Test]
    public function notifiableNotify(): void
    {
        (new SlackRecipient(SlackRoute::webhookNamed('monitoring')))->notify(self::textNotification());

        self::assertSame([self::MONITORING_WEBHOOK], self::sentUrls());
        self::assertSame(['text' => 'Cache flushed'], FakeSlack::lastRequest()->payload());
    }

    #[Test]
    public function notifiableNotifyNow(): void
    {
        (new SlackRecipient(SlackRoute::api(self::CHANNEL_ID)))->notifyNow(self::textNotification());

        self::assertSame([self::API_URL], self::sentUrls());
    }

    #[Test]
    public function notifyNowWithExplicitChannelsOverridesVia(): void
    {
        // via() only lists "mail", but the caller forces Slack.
        $notification = new SlackMessageNotification(SlackMessage::text('Forced to Slack'), ['mail']);

        (new SlackRecipient('webhook:security'))->notifyNow($notification, ['slack']);

        self::assertSame([self::SECURITY_WEBHOOK], self::sentUrls());
    }

    #[Test]
    public function notificationCanBuildItsMessageFromTheNotifiable(): void
    {
        (new SlackRecipient(SlackRoute::webhookNamed('monitoring'), 'Grace'))
            ->notify(new DeploymentFailed('www.example.com', 'a1b2c3d'));

        $payload = FakeSlack::lastRequest()->payload();
        self::assertSame('Deployment failed on www.example.com', $payload['text']);
        self::assertSame(['header', 'section', 'divider', 'context'], array_column($payload['blocks'], 'type'));
        self::assertSame('Owner: Grace', $payload['blocks'][3]['elements'][0]['text']);
    }

    #[Test]
    public function notifiableWithoutSlackRouteIsSkippedWithoutAnyRequest(): void
    {
        (new SlackRecipient(null))->notify(self::textNotification());
        (new SlackRecipient(''))->notify(self::textNotification());

        self::assertSame([], FakeSlack::$requests);
    }

    #[Test]
    public function notificationWhoseViaDoesNotListSlackIsNotSentToSlack(): void
    {
        (new SlackRecipient(SlackRoute::webhookNamed('monitoring')))
            ->notify(new SlackMessageNotification(SlackMessage::text('Not for Slack'), []));

        self::assertSame([], FakeSlack::$requests);
    }

    // NotificationDispatcherInterface (NotificationManager)

    #[Test]
    public function dispatcherSendToOneNotifiable(): void
    {
        $this->dispatcher()->send(new SlackRecipient(SlackRoute::webhookNamed('monitoring')), self::textNotification());

        self::assertSame([self::MONITORING_WEBHOOK], self::sentUrls());
    }

    #[Test]
    public function dispatcherSendToSeveralNotifiablesEachWithItsOwnDestination(): void
    {
        $this->dispatcher()->send([
            new SlackRecipient(SlackRoute::webhookNamed('monitoring')),
            new SlackRecipient('webhook:security'),
            new SlackRecipient(SlackRoute::api(self::CHANNEL_ID)),
            new SlackRecipient(null), // no Slack route: skipped
        ], self::textNotification());

        self::assertSame([self::MONITORING_WEBHOOK, self::SECURITY_WEBHOOK, self::API_URL], self::sentUrls());
    }

    #[Test]
    public function dispatcherSendNow(): void
    {
        $this->dispatcher()->sendNow(
            [new SlackRecipient(SlackRoute::api(self::CHANNEL_ID)), new SlackRecipient(SlackRoute::api('C9876543210'))],
            self::textNotification()
        );

        self::assertSame([self::CHANNEL_ID, 'C9876543210'], array_map(
            static fn(RecordedRequest $request): string => $request->payload()['channel'],
            FakeSlack::$requests
        ));
    }

    #[Test]
    public function dispatcherSendNowWithExplicitChannels(): void
    {
        $this->dispatcher()->sendNow(
            new SlackRecipient(SlackRoute::webhookNamed('monitoring')),
            new SlackMessageNotification(SlackMessage::text('Forced'), ['mail']),
            ['slack']
        );

        self::assertSame([self::MONITORING_WEBHOOK], self::sentUrls());
    }

    // On-demand notifications (no notifiable model)

    #[Test]
    public function onDemandRouteWithEveryKindOfDestination(): void
    {
        $dispatcher = $this->dispatcher();

        $dispatcher->route('slack', SlackRoute::webhookNamed('monitoring'))->notify(self::textNotification());
        $dispatcher->route('slack', 'webhook:security')->notify(self::textNotification());
        $dispatcher->route('slack', SlackRoute::webhook(self::DIRECT_WEBHOOK))->notify(self::textNotification());
        $dispatcher->route('slack', SlackRoute::api(self::CHANNEL_ID))->notify(self::textNotification());
        $dispatcher->route('slack', self::CHANNEL_ID)->notify(self::textNotification());

        self::assertSame(
            [self::MONITORING_WEBHOOK, self::SECURITY_WEBHOOK, self::DIRECT_WEBHOOK, self::API_URL, self::API_URL],
            self::sentUrls()
        );
    }

    #[Test]
    public function onDemandRouteNotifyNow(): void
    {
        $this->dispatcher()->route('slack', SlackRoute::webhookNamed('monitoring'))->notifyNow(self::textNotification());

        self::assertSame([self::MONITORING_WEBHOOK], self::sentUrls());
    }

    #[Test]
    public function onDemandRoutes(): void
    {
        $this->dispatcher()->routes(['slack' => SlackRoute::api(self::CHANNEL_ID)])->notify(self::textNotification());

        self::assertSame([self::API_URL], self::sentUrls());
    }

    #[Test]
    public function anonymousNotifiableBuiltByHand(): void
    {
        (new AnonymousNotifiable())->route('slack', 'webhook:monitoring')->notify(self::textNotification());

        self::assertSame([self::MONITORING_WEBHOOK], self::sentUrls());
    }

    #[Test]
    public function onDemandRouteRequiresTheSlackChannelToBeRegistered(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dispatcher()->route('slak', SlackRoute::webhookNamed('monitoring'));
    }

    // The channel service

    #[Test]
    public function slackChannelIsRegisteredInTheDispatcher(): void
    {
        $channel = $this->dispatcher()->channel('slack');

        self::assertInstanceOf(SlackChannel::class, $channel);
        self::assertSame('slack', $channel->getName());
    }

    #[Test]
    public function slackChannelSendDirectly(): void
    {
        $this->dispatcher()->channel('slack')->send(
            new SlackRecipient(SlackRoute::webhookNamed('monitoring')),
            self::textNotification('Sent through the channel')
        );

        self::assertSame([self::MONITORING_WEBHOOK], self::sentUrls());
        self::assertSame(['text' => 'Sent through the channel'], FakeSlack::lastRequest()->payload());
    }

    #[Test]
    public function slackChannelServiceCanBeInjected(): void
    {
        $this->get(SlackChannel::class)->send(
            (new AnonymousNotifiable())->route('slack', SlackRoute::api(self::CHANNEL_ID)),
            self::textNotification()
        );

        self::assertSame([self::API_URL], self::sentUrls());
    }
}
