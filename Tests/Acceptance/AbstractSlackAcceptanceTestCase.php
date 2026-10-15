<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance;

use Lex\Notifications\NotificationDispatcherInterface;
use Lex\Notifications\Slack\Extension;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http\FakeSlack;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Boots a real TYPO3 instance with lex_notifications + notifications_slack, configured exactly
 * like Configuration/ExampleAdditional.php (secrets from environment variables). Everything runs for
 * real — DI container, NotificationManager, Messenger, SlackChannel, RequestFactory, Guzzle — except
 * the network hop, which FakeSlack replaces.
 */
abstract class AbstractSlackAcceptanceTestCase extends FunctionalTestCase
{
    protected const BOT_TOKEN = 'xoxb-1111-2222-acceptanceBotToken';
    protected const MONITORING_WEBHOOK = 'https://hooks.slack.com/services/T0ACCEPT/B0MONITOR/monitoringWebhookSecret';
    protected const SECURITY_WEBHOOK = 'https://hooks.slack.com/services/T0ACCEPT/B0SECURITY/securityWebhookSecret';
    protected const DIRECT_WEBHOOK = 'https://hooks.slack.com/services/T0ACCEPT/B0DIRECT/directWebhookSecret';
    protected const CHANNEL_ID = 'C0123456789';
    protected const API_URL = 'https://slack.com/api/chat.postMessage';

    private const ENVIRONMENT = [
        'SLACK_BOT_TOKEN' => self::BOT_TOKEN,
        'SLACK_WEBHOOK_MONITORING' => self::MONITORING_WEBHOOK,
        'SLACK_WEBHOOK_SECURITY' => self::SECURITY_WEBHOOK,
    ];

    protected array $coreExtensionsToLoad = [];

    protected array $testExtensionsToLoad = [
        'agencelex/notifications',
        'agencelex/notifications-slack',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        FakeSlack::reset();
        $this->loadExampleAdditionalConfiguration();
        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']['fake-slack'] = FakeSlack::middleware();
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::ENVIRONMENT) as $name) {
            putenv($name);
        }
        FakeSlack::reset();
        parent::tearDown();
    }

    /**
     * Same as an integrator copying the example into config/system/additional.php.
     * Services are instantiated lazily, so this runs before SlackConfiguration reads it.
     */
    private function loadExampleAdditionalConfiguration(): void
    {
        foreach (self::ENVIRONMENT as $name => $value) {
            putenv($name . '=' . $value);
        }
        require __DIR__ . '/../../Configuration/ExampleAdditional.php';
    }

    /** Must be called before the first dispatcher()/get() of the test, like editing additional.php. */
    protected function overrideSlackConfiguration(array $overrides): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Extension::KEY] = array_replace(
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Extension::KEY],
            $overrides
        );
    }

    protected function dispatcher(): NotificationDispatcherInterface
    {
        return $this->get(NotificationDispatcherInterface::class);
    }
}
