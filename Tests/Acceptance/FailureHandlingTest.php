<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Exception\SlackTransportException;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http\FakeSlack;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notifiable\SlackRecipient;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification\SlackMessageNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;

/**
 * What the caller of notify() sees when Slack refuses, fails or is misconfigured:
 * a typed, sanitized exception, a single attempt (no silent retry) and never a secret.
 */
final class FailureHandlingTest extends AbstractSlackAcceptanceTestCase
{
    private const SECRETS = [
        self::BOT_TOKEN,
        'monitoringWebhookSecret',
        'securityWebhookSecret',
        'directWebhookSecret',
        'must-not-leak',
    ];

    private function notifyAndCatch(SlackRoute|string $route): Throwable
    {
        try {
            (new SlackRecipient($route))->notify(new SlackMessageNotification(SlackMessage::text('Hello')));
        } catch (Throwable $exception) {
            $this->assertContainsNoSecret($exception);
            return $exception;
        }
        self::fail('Slack delivery was expected to fail.');
    }

    private function assertContainsNoSecret(Throwable $exception): void
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            foreach (self::SECRETS as $secret) {
                self::assertStringNotContainsString($secret, $current->getMessage());
                self::assertStringNotContainsString($secret, $current->getTraceAsString());
            }
        }
    }

    // HTTP-level failures

    #[Test]
    public function rateLimitExposesRetryAfterAndIsRetryable(): void
    {
        FakeSlack::respondWith(new Response(429, ['Retry-After' => '30'], 'rate_limited'));

        $exception = $this->notifyAndCatch(SlackRoute::webhookNamed('monitoring'));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertSame(429, $exception->httpStatus);
        self::assertSame(30, $exception->retryAfterSeconds);
        self::assertTrue($exception->retryable);
        self::assertCount(1, FakeSlack::$requests, 'No automatic retry.');
    }

    #[Test]
    public function rateLimitWithoutUsableRetryAfterHeader(): void
    {
        FakeSlack::respondWith(new Response(429, ['Retry-After' => 'soon']));

        $exception = $this->notifyAndCatch(SlackRoute::api(self::CHANNEL_ID));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertNull($exception->retryAfterSeconds);
        self::assertTrue($exception->retryable);
    }

    #[Test]
    public function serverErrorIsRetryableButDeliveryIsUnknown(): void
    {
        FakeSlack::respondWith(new Response(503, [], 'Service Unavailable'));

        $exception = $this->notifyAndCatch(SlackRoute::webhookNamed('monitoring'));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertSame(503, $exception->httpStatus);
        self::assertTrue($exception->retryable);
        self::assertStringContainsString('unknown', $exception->getMessage());
        self::assertCount(1, FakeSlack::$requests);
    }

    /** @return iterable<string, array{int, string}> */
    public static function rejectedWebhookResponses(): iterable
    {
        yield '400 invalid_payload' => [400, 'invalid_payload'];
        yield '403 action_prohibited' => [403, 'action_prohibited'];
        yield '404 channel_not_found' => [404, 'channel_not_found'];
        yield '410 channel_is_archived' => [410, 'channel_is_archived'];
    }

    #[Test]
    #[DataProvider('rejectedWebhookResponses')]
    public function clientErrorsAreNotRetryable(int $status, string $body): void
    {
        FakeSlack::respondWith(new Response($status, [], $body));

        $exception = $this->notifyAndCatch(SlackRoute::webhookNamed('monitoring'));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertSame($status, $exception->httpStatus);
        self::assertFalse($exception->retryable);
        self::assertStringNotContainsString($body, $exception->getMessage(), 'Response bodies are not reflected.');
    }

    #[Test]
    public function redirectIsRejectedAndNotFollowed(): void
    {
        FakeSlack::respondWith(new Response(302, ['Location' => 'https://evil.example.com/collect']));

        $exception = $this->notifyAndCatch(SlackRoute::webhookNamed('monitoring'));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertSame(302, $exception->httpStatus);
        self::assertCount(1, FakeSlack::$requests);
    }

    #[Test]
    public function connectionFailureIsRetryableAndDoesNotLeakTheWebhookUrl(): void
    {
        FakeSlack::respondWithConnectionFailure();

        $exception = $this->notifyAndCatch(SlackRoute::webhook('https://hooks.slack.com/services/T000/B000/must-not-leak'));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertNull($exception->httpStatus);
        self::assertTrue($exception->retryable);
        self::assertNull($exception->getPrevious(), 'The Guzzle exception (containing the URL) is not chained.');
    }

    #[Test]
    public function unexpectedWebhookAcknowledgementIsAnError(): void
    {
        FakeSlack::respondWith(new Response(200, [], 'not ok'));

        $exception = $this->notifyAndCatch(SlackRoute::webhookNamed('monitoring'));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertFalse($exception->retryable);
    }

    // Web API failures (HTTP 200 with "ok": false)

    /** @return iterable<string, array{string, bool}> */
    public static function webApiErrors(): iterable
    {
        yield 'not_in_channel' => ['not_in_channel', false];
        yield 'channel_not_found' => ['channel_not_found', false];
        yield 'invalid_auth' => ['invalid_auth', false];
        yield 'ratelimited' => ['ratelimited', true];
        yield 'internal_error' => ['internal_error', true];
        yield 'service_unavailable' => ['service_unavailable', true];
    }

    #[Test]
    #[DataProvider('webApiErrors')]
    public function webApiErrorCodesAreReportedWithTheirRetryability(string $error, bool $retryable): void
    {
        FakeSlack::respondWith(new Response(200, [], json_encode(['ok' => false, 'error' => $error])));

        $exception = $this->notifyAndCatch(SlackRoute::api(self::CHANNEL_ID));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertSame('Slack API error: ' . $error, $exception->getMessage());
        self::assertSame($retryable, $exception->retryable);
    }

    #[Test]
    public function untrustedWebApiErrorTextIsNotReflected(): void
    {
        FakeSlack::respondWith(new Response(200, [], json_encode(['ok' => false, 'error' => '<script>alert(1)</script>'])));

        $exception = $this->notifyAndCatch(SlackRoute::api(self::CHANNEL_ID));

        self::assertSame('Slack API error: unknown_error', $exception->getMessage());
    }

    #[Test]
    public function invalidJsonFromTheWebApiIsAnError(): void
    {
        FakeSlack::respondWith(new Response(200, [], '<html>maintenance</html>'));

        $exception = $this->notifyAndCatch(SlackRoute::api(self::CHANNEL_ID));

        self::assertInstanceOf(SlackTransportException::class, $exception);
        self::assertStringContainsString('invalid JSON', $exception->getMessage());
    }

    // Misconfiguration: detected before anything is sent

    #[Test]
    public function unknownNamedWebhook(): void
    {
        $exception = $this->notifyAndCatch(SlackRoute::webhookNamed('marketing'));

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('Named Slack webhook is not configured: marketing', $exception->getMessage());
        self::assertSame([], FakeSlack::$requests);
    }

    #[Test]
    public function namedWebhookWhoseEnvironmentVariableIsMissing(): void
    {
        $this->overrideSlackConfiguration(['webhooks' => ['monitoring' => '']]);

        $exception = $this->notifyAndCatch('webhook:monitoring');

        self::assertSame('Named Slack webhook is not configured: monitoring', $exception->getMessage());
        self::assertSame([], FakeSlack::$requests);
    }

    #[Test]
    public function configuredWebhookOutsideSlackIsRefused(): void
    {
        $this->overrideSlackConfiguration(['webhooks' => ['monitoring' => 'https://evil.example.com/services/T/B/x']]);

        $exception = $this->notifyAndCatch(SlackRoute::webhookNamed('monitoring'));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame([], FakeSlack::$requests);
    }

    #[Test]
    public function webApiWithoutBotToken(): void
    {
        $this->overrideSlackConfiguration(['botToken' => '']);

        $exception = $this->notifyAndCatch(SlackRoute::api(self::CHANNEL_ID));

        self::assertSame('Slack Bot Token is not configured.', $exception->getMessage());
        self::assertSame([], FakeSlack::$requests);
    }

    #[Test]
    public function unsupportedApiEndpointIsRefused(): void
    {
        $this->overrideSlackConfiguration(['apiEndpoint' => 'https://slack.example.com/api']);

        $exception = $this->notifyAndCatch(SlackRoute::api(self::CHANNEL_ID));

        self::assertSame('Invalid Slack API endpoint configuration.', $exception->getMessage());
        self::assertSame([], FakeSlack::$requests);
    }

    // Invalid destinations: rejected when the route is built or resolved

    /** @return iterable<string, array{string}> */
    public static function invalidDestinations(): iterable
    {
        yield 'plain HTTP webhook' => ['http://hooks.slack.com/services/T/B/x'];
        yield 'non-Slack HTTPS URL' => ['https://example.com/services/T/B/x'];
        yield 'Slack host, wrong path' => ['https://hooks.slack.com/workflows/T/B/x'];
        yield 'webhook with query string' => ['https://hooks.slack.com/services/T/B/x?redirect=1'];
        yield 'webhook with credentials' => ['https://user:pass@hooks.slack.com/services/T/B/x'];
        yield 'other scheme' => ['ftp://hooks.slack.com/services/T/B/x'];
        yield 'channel name instead of ID' => ['#general'];
        yield 'lowercase channel ID' => ['c0123456789'];
        yield 'invalid webhook alias' => ['webhook:../monitoring'];
    }

    #[Test]
    #[DataProvider('invalidDestinations')]
    public function invalidStringDestinationsAreRejectedWithoutAnyRequest(string $destination): void
    {
        $exception = $this->notifyAndCatch($destination);

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame([], FakeSlack::$requests);
    }
}
