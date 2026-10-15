<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Unit\Configuration;

use InvalidArgumentException;
use Lex\Notifications\Slack\Configuration\SlackConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SlackConfigurationTest extends TestCase
{
    private const SETTINGS = [
        'botToken' => 'xoxb-test',
        'webhooks' => ['monitoring' => 'https://hooks.slack.com/services/T/B/secret'],
        'apiEndpoint' => 'https://slack.com/api',
        'timeout' => 10,
        'connectTimeout' => 5,
    ];

    private static function configuration(array $overrides = []): SlackConfiguration
    {
        return new SlackConfiguration(array_replace(self::SETTINGS, $overrides));
    }

    #[Test]
    public function readsAllSettings(): void
    {
        $config = self::configuration();

        self::assertSame('xoxb-test', $config->botToken());
        self::assertSame('https://hooks.slack.com/services/T/B/secret', $config->namedWebhook('monitoring'));
        self::assertSame('https://slack.com/api/chat.postMessage', $config->apiEndpoint());
        self::assertSame(10, $config->timeout());
        self::assertSame(5, $config->connectTimeout());
    }

    #[Test]
    public function appliesDefaultsWhenOptionalSettingsAreMissing(): void
    {
        $config = new SlackConfiguration([]);

        self::assertSame('https://slack.com/api/chat.postMessage', $config->apiEndpoint());
        self::assertSame(10, $config->timeout());
        self::assertSame(5, $config->connectTimeout());
    }

    #[Test]
    public function botTokenIsTrimmed(): void
    {
        self::assertSame('xoxb-test', self::configuration(['botToken' => "  xoxb-test\n"])->botToken());
    }

    /** @return iterable<string, array{mixed}> */
    public static function missingBotTokens(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace (unset environment variable)' => ['   '];
        yield 'not a string' => [123];
    }

    #[Test]
    #[DataProvider('missingBotTokens')]
    public function missingBotTokenIsRejected(mixed $token): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Slack Bot Token is not configured.');

        self::configuration(['botToken' => $token])->botToken();
    }

    #[Test]
    public function govSlackWebhookIsAccepted(): void
    {
        $url = 'https://hooks.slack-gov.com/services/T/B/secret';

        self::assertSame($url, self::configuration(['webhooks' => ['gov' => $url]])->namedWebhook('gov'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function unconfiguredWebhookLists(): iterable
    {
        yield 'unknown name' => [['security' => 'https://hooks.slack.com/services/T/B/secret']];
        yield 'empty value (unset environment variable)' => [['monitoring' => '']];
        yield 'not a string' => [['monitoring' => ['https://hooks.slack.com/services/T/B/secret']]];
        yield 'webhooks is not an array' => ['https://hooks.slack.com/services/T/B/secret'];
    }

    #[Test]
    #[DataProvider('unconfiguredWebhookLists')]
    public function unconfiguredNamedWebhookIsRejected(mixed $webhooks): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Named Slack webhook is not configured: monitoring');

        self::configuration(['webhooks' => $webhooks])->namedWebhook('monitoring');
    }

    #[Test]
    public function configuredWebhookOutsideSlackIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::configuration(['webhooks' => ['monitoring' => 'https://evil.example.com/services/T/B/secret']])
            ->namedWebhook('monitoring');
    }

    #[Test]
    public function govSlackApiEndpointIsAccepted(): void
    {
        self::assertSame(
            'https://slack-gov.com/api/chat.postMessage',
            self::configuration(['apiEndpoint' => 'https://slack-gov.com/api'])->apiEndpoint()
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidApiEndpoints(): iterable
    {
        yield 'other host' => ['https://example.com'];
        yield 'trailing slash' => ['https://slack.com/api/'];
        yield 'plain HTTP' => ['http://slack.com/api'];
        yield 'not a string' => [true];
    }

    #[Test]
    #[DataProvider('invalidApiEndpoints')]
    public function unknownApiEndpointIsRejected(mixed $endpoint): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Slack API endpoint configuration.');

        self::configuration(['apiEndpoint' => $endpoint])->apiEndpoint();
    }

    #[Test]
    public function timeoutBoundariesAreAccepted(): void
    {
        self::assertSame(1, self::configuration(['timeout' => 1])->timeout());
        self::assertSame(120, self::configuration(['connectTimeout' => 120])->connectTimeout());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidTimeouts(): iterable
    {
        yield 'timeout zero' => ['timeout', 0];
        yield 'timeout above 120' => ['timeout', 121];
        yield 'timeout as string' => ['timeout', '10'];
        yield 'connectTimeout negative' => ['connectTimeout', -1];
        yield 'connectTimeout as float' => ['connectTimeout', 2.5];
    }

    #[Test]
    #[DataProvider('invalidTimeouts')]
    public function invalidTimeoutIsRejected(string $name, mixed $value): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Slack timeout configuration: ' . $name);

        $config = self::configuration([$name => $value]);
        $name === 'timeout' ? $config->timeout() : $config->connectTimeout();
    }
}
