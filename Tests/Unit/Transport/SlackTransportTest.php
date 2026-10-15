<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Unit\Transport;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Lex\Notifications\Slack\Configuration\SlackConfiguration;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Exception\SlackTransportException;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Transport\SlackTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TYPO3\CMS\Core\Http\RequestFactory;

final class SlackTransportTest extends TestCase
{
    private const BOT_TOKEN = 'xoxb-secret';
    private const NAMED_WEBHOOK = 'https://hooks.slack.com/services/T/B/secret';
    private const SETTINGS = [
        'botToken' => self::BOT_TOKEN,
        'webhooks' => ['monitoring' => self::NAMED_WEBHOOK],
        'timeout' => 10,
        'connectTimeout' => 5,
    ];

    private static function transport(RequestFactory $factory, array $overrides = []): SlackTransport
    {
        return new SlackTransport($factory, new SlackConfiguration(array_replace(self::SETTINGS, $overrides)));
    }

    private function factoryReturning(Response $response): RequestFactory
    {
        $factory = $this->createStub(RequestFactory::class);
        $factory->method('request')->willReturn($response);
        return $factory;
    }

    private function sendExpectingFailure(RequestFactory $factory, SlackRoute $route): SlackTransportException
    {
        try {
            self::transport($factory)->send(SlackMessage::text('test'), $route);
        } catch (SlackTransportException $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            return $exception;
        }
        self::fail('Expected SlackTransportException');
    }

    // Requests

    #[Test]
    public function namedWebhookIsResolvedAndDoesNotSendTheBotToken(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::once())->method('request')->with(
            self::NAMED_WEBHOOK,
            'POST',
            self::callback(static function (array $options): bool {
                return !isset($options['headers']['Authorization'])
                    && json_decode($options['body'], true) === ['text' => 'Hello'];
            })
        )->willReturn(new Response(200, [], 'ok'));

        self::assertSame(['ok' => true], self::transport($factory)->send(
            SlackMessage::text('Hello'),
            SlackRoute::webhookNamed('monitoring')
        ));
    }

    #[Test]
    public function directWebhookIsPostedAsIs(): void
    {
        $url = 'https://hooks.slack.com/services/T/B/direct';
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::once())->method('request')
            ->with($url, 'POST', self::anything())
            ->willReturn(new Response(200, [], "ok\n"));

        self::assertSame(['ok' => true], self::transport($factory)->send(SlackMessage::text('Hi'), SlackRoute::webhook($url)));
    }

    #[Test]
    public function apiSendsBearerTokenAndChannelAndReturnsTheSlackResponse(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::once())->method('request')->with(
            'https://slack.com/api/chat.postMessage',
            'POST',
            self::callback(static function (array $options): bool {
                return $options['headers']['Authorization'] === 'Bearer ' . self::BOT_TOKEN
                    && json_decode($options['body'], true) === ['text' => 'Hi', 'channel' => 'C0123456789'];
            })
        )->willReturn(new Response(200, [], '{"ok":true,"channel":"C0123456789","ts":"123.456"}'));

        $response = self::transport($factory)->send(SlackMessage::text('Hi'), SlackRoute::api('C0123456789'));

        self::assertSame(['ok' => true, 'channel' => 'C0123456789', 'ts' => '123.456'], $response);
    }

    #[Test]
    public function govSlackApiEndpointIsUsedWhenConfigured(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::once())->method('request')
            ->with('https://slack-gov.com/api/chat.postMessage', 'POST', self::anything())
            ->willReturn(new Response(200, [], '{"ok":true}'));

        self::transport($factory, ['apiEndpoint' => 'https://slack-gov.com/api'])
            ->send(SlackMessage::text('Hi'), SlackRoute::api('C0123456789'));
    }

    #[Test]
    public function requestOptionsAreSafe(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::once())->method('request')->with(
            self::anything(),
            'POST',
            self::callback(static fn(array $options): bool => $options['allow_redirects'] === false
                && $options['http_errors'] === false
                && $options['timeout'] === 30
                && $options['connect_timeout'] === 2
                && $options['headers']['Content-Type'] === 'application/json; charset=utf-8'
                && $options['headers']['Accept'] === 'application/json'
                && $options['body'] === '{"text":"Réussi ✅ https://example.com/a"}')
        )->willReturn(new Response(200, [], 'ok'));

        self::transport($factory, ['timeout' => 30, 'connectTimeout' => 2])
            ->send(SlackMessage::text('Réussi ✅ https://example.com/a'), SlackRoute::webhookNamed('monitoring'));
    }

    // Configuration errors: nothing is sent

    #[Test]
    public function missingBotTokenFailsBeforeAnyRequest(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::never())->method('request');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Slack Bot Token is not configured.');

        self::transport($factory, ['botToken' => ''])->send(SlackMessage::text('Hi'), SlackRoute::api('C0123456789'));
    }

    #[Test]
    public function unknownNamedWebhookFailsBeforeAnyRequest(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::never())->method('request');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Named Slack webhook is not configured: security');

        self::transport($factory)->send(SlackMessage::text('Hi'), SlackRoute::webhookNamed('security'));
    }

    #[Test]
    public function messageThatCannotBeEncodedFailsBeforeAnyRequest(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects(self::never())->method('request');

        $this->expectException(SlackTransportException::class);
        $this->expectExceptionMessage('Slack message cannot be encoded as JSON.');

        self::transport($factory)->send(SlackMessage::text("invalid UTF-8 \xB1\x31"), SlackRoute::webhookNamed('monitoring'));
    }

    // HTTP failures

    #[Test]
    public function rateLimitHasRetryAfter(): void
    {
        $exception = $this->sendExpectingFailure(
            $this->factoryReturning(new Response(429, ['Retry-After' => '30'], 'ratelimited')),
            SlackRoute::webhookNamed('monitoring')
        );

        self::assertSame(429, $exception->httpStatus);
        self::assertSame(30, $exception->retryAfterSeconds);
        self::assertTrue($exception->retryable);
    }

    #[Test]
    public function rateLimitWithNonNumericRetryAfter(): void
    {
        $exception = $this->sendExpectingFailure(
            $this->factoryReturning(new Response(429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'])),
            SlackRoute::api('C0123456789')
        );

        self::assertNull($exception->retryAfterSeconds);
        self::assertTrue($exception->retryable);
    }

    #[Test]
    public function serverErrorIsRetryable(): void
    {
        $exception = $this->sendExpectingFailure(
            $this->factoryReturning(new Response(503, [], 'Service Unavailable')),
            SlackRoute::webhookNamed('monitoring')
        );

        self::assertSame(503, $exception->httpStatus);
        self::assertTrue($exception->retryable);
        self::assertSame('Slack server failure; delivery status unknown.', $exception->getMessage());
    }

    /** @return iterable<string, array{int}> */
    public static function nonRetryableStatuses(): iterable
    {
        yield 'redirect' => [302];
        yield 'bad request' => [400];
        yield 'forbidden' => [403];
        yield 'not found' => [404];
        yield 'gone' => [410];
    }

    #[Test]
    #[DataProvider('nonRetryableStatuses')]
    public function otherNonSuccessStatusesAreNotRetryable(int $status): void
    {
        $exception = $this->sendExpectingFailure(
            $this->factoryReturning(new Response($status, [], 'invalid_token')),
            SlackRoute::webhookNamed('monitoring')
        );

        self::assertSame($status, $exception->httpStatus);
        self::assertFalse($exception->retryable);
        self::assertSame('Slack rejected the request (HTTP ' . $status . ').', $exception->getMessage());
    }

    #[Test]
    public function unexpectedWebhookBodyIsAnError(): void
    {
        $exception = $this->sendExpectingFailure(
            $this->factoryReturning(new Response(200, [], 'no_service')),
            SlackRoute::webhookNamed('monitoring')
        );

        self::assertSame('Unexpected Slack webhook response.', $exception->getMessage());
        self::assertFalse($exception->retryable);
    }

    #[Test]
    public function networkErrorNeverLeaksTheWebhookSecret(): void
    {
        $factory = $this->createStub(RequestFactory::class);
        $factory->method('request')->willThrowException(new ConnectException(
            'cURL error 28 for ' . self::NAMED_WEBHOOK,
            new Request('POST', self::NAMED_WEBHOOK)
        ));

        $exception = $this->sendExpectingFailure($factory, SlackRoute::webhookNamed('monitoring'));

        self::assertNull($exception->httpStatus);
        self::assertTrue($exception->retryable);
        self::assertSame('Slack request failed. Delivery status is unknown.', $exception->getMessage());
    }

    // Web API errors

    /** @return iterable<string, array{string, bool}> */
    public static function apiErrors(): iterable
    {
        yield 'channel_not_found' => ['channel_not_found', false];
        yield 'not_in_channel' => ['not_in_channel', false];
        yield 'invalid_auth' => ['invalid_auth', false];
        yield 'ratelimited' => ['ratelimited', true];
        yield 'rate_limited' => ['rate_limited', true];
        yield 'internal_error' => ['internal_error', true];
        yield 'fatal_error' => ['fatal_error', true];
        yield 'service_unavailable' => ['service_unavailable', true];
    }

    #[Test]
    #[DataProvider('apiErrors')]
    public function apiOkFalseIsAnError(string $error, bool $retryable): void
    {
        $exception = $this->sendExpectingFailure(
            $this->factoryReturning(new Response(200, ['Retry-After' => '5'], json_encode(['ok' => false, 'error' => $error]))),
            SlackRoute::api('C0123456789')
        );

        self::assertSame('Slack API error: ' . $error, $exception->getMessage());
        self::assertSame(200, $exception->httpStatus);
        self::assertSame(5, $exception->retryAfterSeconds);
        self::assertSame($retryable, $exception->retryable);
    }

    /** @return iterable<string, array{string}> */
    public static function unknownApiErrorBodies(): iterable
    {
        yield 'untrusted error text' => ['{"ok":false,"error":"<script>alert(1)</script>"}'];
        yield 'missing error' => ['{"ok":false}'];
        yield 'ok is not true' => ['{"ok":"true"}'];
        yield 'not an object' => ['"ok"'];
    }

    #[Test]
    #[DataProvider('unknownApiErrorBodies')]
    public function untrustedOrMissingApiErrorIsReportedAsUnknown(string $body): void
    {
        $exception = $this->sendExpectingFailure($this->factoryReturning(new Response(200, [], $body)), SlackRoute::api('C0123456789'));

        self::assertSame('Slack API error: unknown_error', $exception->getMessage());
        self::assertFalse($exception->retryable);
    }

    #[Test]
    public function invalidApiJsonIsAnError(): void
    {
        $exception = $this->sendExpectingFailure(
            $this->factoryReturning(new Response(200, [], '<html>maintenance</html>')),
            SlackRoute::api('C0123456789')
        );

        self::assertSame('Slack API response contains invalid JSON.', $exception->getMessage());
    }
}
