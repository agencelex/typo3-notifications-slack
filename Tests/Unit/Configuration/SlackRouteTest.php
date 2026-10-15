<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Unit\Configuration;

use InvalidArgumentException;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SlackRouteTest extends TestCase
{
    private const WEBHOOK = 'https://hooks.slack.com/services/T/B/secret';

    #[Test]
    public function factoriesSetTransportAndDestination(): void
    {
        $api = SlackRoute::api('C0123456789');
        $named = SlackRoute::webhookNamed('monitoring');
        $webhook = SlackRoute::webhook(self::WEBHOOK);

        self::assertSame(['api', 'C0123456789'], [$api->transport, $api->destination]);
        self::assertSame(['webhook_named', 'monitoring'], [$named->transport, $named->destination]);
        self::assertSame(['webhook', self::WEBHOOK], [$webhook->transport, $webhook->destination]);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function stringRoutes(): iterable
    {
        yield 'webhook alias' => ['webhook:monitoring', 'webhook_named', 'monitoring'];
        yield 'direct webhook' => [self::WEBHOOK, 'webhook', self::WEBHOOK];
        yield 'GovSlack webhook' => ['https://hooks.slack-gov.com/services/T/B/secret', 'webhook', 'https://hooks.slack-gov.com/services/T/B/secret'];
        yield 'channel ID' => ['C0123456789', 'api', 'C0123456789'];
        yield 'private channel ID' => ['G0123456789', 'api', 'G0123456789'];
        yield 'user ID' => ['U0123456789', 'api', 'U0123456789'];
        yield 'DM ID' => ['D0123456789', 'api', 'D0123456789'];
        yield 'enterprise user ID' => ['W0123456789', 'api', 'W0123456789'];
    }

    #[Test]
    #[DataProvider('stringRoutes')]
    public function fromStringDetectsTheTransport(string $value, string $transport, string $destination): void
    {
        $route = SlackRoute::fromString($value);

        self::assertSame($transport, $route->transport);
        self::assertSame($destination, $route->destination);
    }

    /** @return iterable<string, array{string}> */
    public static function badRoutes(): iterable
    {
        yield 'http' => ['http://hooks.slack.com/services/T/B/secret'];
        yield 'uppercase scheme' => ['HTTPS://hooks.slack.com/services/T/B/secret'];
        yield 'external https' => ['https://example.org/services/T/B/secret'];
        yield 'userinfo trick' => ['https://hooks.slack.com@evil.test/services/T/B/secret'];
        yield 'credentials' => ['https://user:pass@hooks.slack.com/services/T/B/secret'];
        yield 'port' => ['https://hooks.slack.com:444/services/T/B/secret'];
        yield 'query' => ['https://hooks.slack.com/services/T/B/secret?foo=bar'];
        yield 'fragment' => ['https://hooks.slack.com/services/T/B/secret#x'];
        yield 'wrong path' => ['https://hooks.slack.com/workflows/T/B/secret'];
        yield 'missing path segment' => ['https://hooks.slack.com/services/T/B'];
        yield 'extra path segment' => ['https://hooks.slack.com/services/T/B/secret/extra'];
        yield 'injected channel' => ["C012345\nInjected"];
        yield 'channel name' => ['#general'];
        yield 'lowercase ID' => ['c0123456789'];
        yield 'too short ID' => ['C0'];
        yield 'unknown ID prefix' => ['X0123456789'];
        yield 'named empty' => ['webhook:'];
        yield 'named path traversal' => ['webhook:../monitoring'];
        yield 'named starting with digit' => ['webhook:1monitoring'];
        yield 'custom scheme' => ['file:///etc/passwd'];
        yield 'empty string' => [''];
    }

    #[Test]
    #[DataProvider('badRoutes')]
    public function rejectsInvalidRoute(string $destination): void
    {
        $this->expectException(InvalidArgumentException::class);

        SlackRoute::fromString($destination);
    }

    #[Test]
    public function webhookNameLengthIsLimited(): void
    {
        self::assertSame(64, strlen(SlackRoute::webhookNamed('a' . str_repeat('b', 63))->destination));

        $this->expectException(InvalidArgumentException::class);
        SlackRoute::webhookNamed('a' . str_repeat('b', 64));
    }

    #[Test]
    public function namedWebhookSerializesOnlyItsAlias(): void
    {
        $serialized = serialize(SlackRoute::webhookNamed('monitoring'));

        self::assertStringNotContainsString('https://', $serialized);
        self::assertSame('monitoring', unserialize($serialized)->destination);
    }

    #[Test]
    public function directWebhookSerializesTheSecretUrl(): void
    {
        // Documented trade-off: prefer webhookNamed() for queued notifications.
        self::assertStringContainsString(self::WEBHOOK, serialize(SlackRoute::webhook(self::WEBHOOK)));
    }
}
