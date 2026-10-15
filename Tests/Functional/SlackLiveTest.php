<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Functional;

use Lex\Notifications\NotificationDispatcherInterface;
use Lex\Notifications\Slack\Configuration\SlackConfiguration;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Exception\SlackTransportException;
use Lex\Notifications\Slack\Extension;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notifiable\SlackRecipient;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification\SlackMessageNotification;
use Lex\Notifications\Slack\Transport\SlackTransport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SlackPhp\BlockKit\Kit;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Sends REAL messages to a Slack workspace. Excluded from the functional suite by default;
 * run explicitly, with secrets coming from the environment only:
 *
 *   SLACK_TEST_WEBHOOK_URL=https://hooks.slack.com/services/…   # Incoming Webhook
 *   SLACK_TEST_BOT_TOKEN=xoxb-…                                 # Slack app with chat:write
 *   SLACK_TEST_CHANNEL_ID=C…                                    # channel the bot is a member of
 *   SLACK_TEST_USER_ID=U…                                       # optional: receives a DM from the bot
 *   SLACK_TEST_API_ENDPOINT=https://slack-gov.com/api           # optional: GovSlack
 *   .Build/bin/phpunit -c Build/phpunit-functional.xml --group live-slack
 *
 * Each test only needs its own variables; the others are skipped. Nothing is retried.
 */
#[Group('live-slack')]
final class SlackLiveTest extends FunctionalTestCase
{
    private const ENVIRONMENT = [
        'SLACK_TEST_WEBHOOK_URL',
        'SLACK_TEST_BOT_TOKEN',
        'SLACK_TEST_CHANNEL_ID',
        'SLACK_TEST_USER_ID',
    ];

    protected array $coreExtensionsToLoad = [];

    protected array $testExtensionsToLoad = [
        'agencelex/notifications',
        'agencelex/notifications-slack',
    ];

    private bool $instanceBooted = false;

    protected function setUp(): void
    {
        // Skip before booting a TYPO3 instance when no live Slack target is configured at all.
        if (array_filter(array_map(self::env(...), self::ENVIRONMENT)) === []) {
            self::markTestSkipped('No SLACK_TEST_* environment variable is configured.');
        }
        parent::setUp();
        $this->instanceBooted = true;

        // Same place an integrator uses (config/system/additional.php); read when the services are built.
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Extension::KEY] = array_filter([
            'botToken' => self::env('SLACK_TEST_BOT_TOKEN'),
            'webhooks' => array_filter(['live' => self::env('SLACK_TEST_WEBHOOK_URL')]),
            'apiEndpoint' => self::env('SLACK_TEST_API_ENDPOINT'),
            'timeout' => 10,
            'connectTimeout' => 5,
        ]);
    }

    protected function tearDown(): void
    {
        // The framework's tearDown() needs an instance; there is none when setUp() skipped early.
        if ($this->instanceBooted) {
            parent::tearDown();
        }
    }

    private static function env(string $name): string
    {
        return trim((string)getenv($name));
    }

    private static function requireEnv(string ...$names): void
    {
        foreach ($names as $name) {
            if (self::env($name) === '') {
                self::markTestSkipped($name . ' is not configured.');
            }
        }
    }

    private function dispatcher(): NotificationDispatcherInterface
    {
        return $this->get(NotificationDispatcherInterface::class);
    }

    /** The container's transport: returns Slack's acknowledgement (ts/channel for the Web API). */
    private function transport(): SlackTransport
    {
        return $this->get(SlackTransport::class);
    }

    /** Identifies the run in the Slack channel, so a human can check what arrived. */
    private function label(): string
    {
        return sprintf(
            'TYPO3 %s live test `%s` (%s)',
            (new Typo3Version())->getVersion(),
            $this->name(),
            date('Y-m-d H:i:s T')
        );
    }

    // Incoming Webhooks

    #[Test]
    public function realWebhookDeliveryThroughTheNotificationSystem(): void
    {
        self::requireEnv('SLACK_TEST_WEBHOOK_URL');

        $message = SlackMessage::fromJson(<<<'JSON'
                {
                  "blocks": [
                    {
                      "type": "header",
                      "text": {
                        "type": "plain_text",
                        "text": "TYPO3 - Slack Integration Test"
                      }
                    },
                    {
                      "type": "section",
                      "text": {
                        "type": "mrkdwn",
                        "text": ":white_check_mark: *Notification received successfully!*"
                      }
                    },
                    {
                      "type": "section",
                      "fields": [
                        {
                          "type": "mrkdwn",
                          "text": "*Source:*\nTYPO3"
                        },
                        {
                          "type": "mrkdwn",
                          "text": "*Channel:*\nSlack"
                        },
                        {
                          "type": "mrkdwn",
                          "text": "*Transport:*\nIncoming Webhook"
                        },
                        {
                          "type": "mrkdwn",
                          "text": "*Format:*\nBlock Kit"
                        }
                      ]
                    },
                    {
                      "type": "divider"
                    },
                    {
                      "type": "context",
                      "elements": [
                        {
                          "type": "mrkdwn",
                          "text": "Powered by Agence Lex Notifications"
                        }
                      ]
                    }
                  ]
                }
                JSON)->withText($this->label());

        // On-demand route with the direct URL, as documented.
        $this->dispatcher()
            ->route('slack', SlackRoute::webhook(self::env('SLACK_TEST_WEBHOOK_URL')))
            ->notifyNow(new SlackMessageNotification($message));

        // No exception: Slack answered HTTP 200 "ok" (SlackTransport rejects anything else).
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function realNamedWebhookResolvedFromTheExtensionConfiguration(): void
    {
        self::requireEnv('SLACK_TEST_WEBHOOK_URL');

        $response = $this->transport()->send(
            SlackMessage::text(':label: Named webhook `webhook:live` — ' . $this->label())->withoutUnfurling(),
            SlackRoute::webhookNamed('live')
        );

        self::assertSame(['ok' => true], $response);
    }

    // Web API (chat.postMessage with the bot token)

    #[Test]
    public function realBotMessageToAChannelThroughTheNotificationSystem(): void
    {
        self::requireEnv('SLACK_TEST_BOT_TOKEN', 'SLACK_TEST_CHANNEL_ID');

        (new SlackRecipient(SlackRoute::api(self::env('SLACK_TEST_CHANNEL_ID'))))->notifyNow(
            new SlackMessageNotification(SlackMessage::text(':robot_face: Bot message via `$notifiable->notifyNow()` — ' . $this->label()))
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function realBotMessageReturnsTheChannelAndTimestamp(): void
    {
        self::requireEnv('SLACK_TEST_BOT_TOKEN', 'SLACK_TEST_CHANNEL_ID');
        $channel = self::env('SLACK_TEST_CHANNEL_ID');

        $response = $this->transport()->send(
            SlackMessage::fromBlockKit(Kit::message(
                blocks: [
                    Kit::header('TYPO3 Slack Web API test'),
                    Kit::section(Kit::mrkdwnText(':robot_face: *Native Block Kit* message posted by the bot')),
                    Kit::context([Kit::mrkdwnText($this->label())]),
                ],
                text: 'TYPO3 Slack Web API test',
            )),
            SlackRoute::api($channel)
        );

        self::assertTrue($response['ok']);
        self::assertSame($channel, $response['channel']);
        self::assertMatchesRegularExpression('/^[0-9]+\.[0-9]+$/D', $response['ts']);
    }

    #[Test]
    public function realBotThreadReplyAndBroadcast(): void
    {
        self::requireEnv('SLACK_TEST_BOT_TOKEN', 'SLACK_TEST_CHANNEL_ID');
        $route = SlackRoute::api(self::env('SLACK_TEST_CHANNEL_ID'));

        $parent = $this->transport()->send(SlackMessage::text(':thread: Thread parent — ' . $this->label()), $route);
        $reply = $this->transport()->send(SlackMessage::text('Reply in thread')->inThread($parent['ts']), $route);
        $broadcast = $this->transport()->send(
            SlackMessage::text('Reply also sent to the channel')->inThread($parent['ts'], broadcast: true),
            $route
        );

        self::assertTrue($reply['ok']);
        self::assertSame($parent['ts'], $reply['message']['thread_ts'] ?? null);
        self::assertSame($parent['ts'], $broadcast['message']['thread_ts'] ?? null);
    }

    #[Test]
    public function realBotDirectMessageToAUser(): void
    {
        self::requireEnv('SLACK_TEST_BOT_TOKEN', 'SLACK_TEST_USER_ID');

        // A user ID posts to the user's DM with the app ("Messages" tab of the app).
        $response = $this->transport()->send(
            SlackMessage::text(':wave: Direct message from the TYPO3 bot — ' . $this->label()),
            SlackRoute::api(self::env('SLACK_TEST_USER_ID'))
        );

        self::assertTrue($response['ok']);
        self::assertArrayHasKey('ts', $response);
    }

    #[Test]
    public function realOnDemandBotMessageWithAChannelIdString(): void
    {
        self::requireEnv('SLACK_TEST_BOT_TOKEN', 'SLACK_TEST_CHANNEL_ID');

        $this->dispatcher()
            ->route('slack', self::env('SLACK_TEST_CHANNEL_ID'))
            ->notifyNow(new SlackMessageNotification(
                SlackMessage::text('Legacy attachment via `route(\'slack\', \'C…\')` — ' . $this->label())->withAttachments([[
                    'color' => '#2eb886',
                    'title' => 'Legacy attachment',
                    'text' => 'Still rendered by Slack.',
                ]])
            ));

        $this->addToAssertionCount(1);
    }

    // Real errors: nothing is posted

    #[Test]
    public function realUnknownChannelIsReportedAsANonRetryableApiError(): void
    {
        self::requireEnv('SLACK_TEST_BOT_TOKEN');

        try {
            $this->transport()->send(SlackMessage::text('Must not arrive'), SlackRoute::api('C00000000000'));
            self::fail('Slack accepted a message for a channel that does not exist.');
        } catch (SlackTransportException $exception) {
            self::assertContains($exception->getMessage(), ['Slack API error: channel_not_found', 'Slack API error: not_in_channel']);
            self::assertFalse($exception->retryable);
            self::assertStringNotContainsString(self::env('SLACK_TEST_BOT_TOKEN'), $exception->getMessage());
        }
    }

    #[Test]
    public function realInvalidBotTokenIsReportedWithoutLeakingIt(): void
    {
        self::requireEnv('SLACK_TEST_BOT_TOKEN', 'SLACK_TEST_CHANNEL_ID');
        $invalidToken = 'xoxb-0000000000-0000000000-invalidTokenForTests';
        $transport = new SlackTransport(
            $this->get(RequestFactory::class),
            new SlackConfiguration(['botToken' => $invalidToken, 'apiEndpoint' => self::env('SLACK_TEST_API_ENDPOINT') ?: 'https://slack.com/api'])
        );

        try {
            $transport->send(SlackMessage::text('Must not arrive'), SlackRoute::api(self::env('SLACK_TEST_CHANNEL_ID')));
            self::fail('Slack accepted an invalid bot token.');
        } catch (SlackTransportException $exception) {
            self::assertSame('Slack API error: invalid_auth', $exception->getMessage());
            self::assertFalse($exception->retryable);
            self::assertStringNotContainsString($invalidToken, $exception->getMessage());
        }
    }
}
