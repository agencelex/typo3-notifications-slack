<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Stands in for slack.com at the Guzzle handler level: TYPO3's RequestFactory, GuzzleClientFactory and
 * the HTTP client run for real, only the network hop is replaced. Registered through
 * $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'], exactly like any TYPO3 HTTP middleware.
 */
final class FakeSlack
{
    /** @var list<RecordedRequest> */
    public static array $requests = [];

    /** @var list<ResponseInterface|Throwable> */
    private static array $responses = [];

    public static function reset(): void
    {
        self::$requests = [];
        self::$responses = [];
    }

    public static function respondWith(ResponseInterface|Throwable ...$responses): void
    {
        array_push(self::$responses, ...$responses);
    }

    public static function respondWithConnectionFailure(): void
    {
        self::$responses[] = new ConnectException(
            'cURL error 28: Operation timed out for https://hooks.slack.com/services/T000/B000/must-not-leak',
            new \GuzzleHttp\Psr7\Request('POST', 'https://hooks.slack.com/services/T000/B000/must-not-leak')
        );
    }

    public static function lastRequest(): RecordedRequest
    {
        return self::$requests[array_key_last(self::$requests)]
            ?? throw new \LogicException('No request was sent to Slack.');
    }

    /** Guzzle middleware that records the request and short-circuits the network. */
    public static function middleware(): callable
    {
        return static fn(callable $next): callable => static function (RequestInterface $request, array $options): PromiseInterface {
            self::$requests[] = new RecordedRequest($request, $options);
            $response = array_shift(self::$responses) ?? self::defaultResponseFor($request);
            return $response instanceof Throwable
                ? Create::rejectionFor($response)
                : Create::promiseFor($response);
        };
    }

    /** Mimics Slack's success acknowledgements: plain "ok" for webhooks, JSON for the Web API. */
    private static function defaultResponseFor(RequestInterface $request): ResponseInterface
    {
        if (str_starts_with($request->getUri()->getHost(), 'hooks.')) {
            return new Response(200, ['Content-Type' => 'text/html'], 'ok');
        }
        $body = json_decode((string)$request->getBody(), true);
        return new Response(200, ['Content-Type' => 'application/json'], (string)json_encode([
            'ok' => true,
            'channel' => $body['channel'] ?? null,
            'ts' => '1729000000.000100',
        ]));
    }
}
