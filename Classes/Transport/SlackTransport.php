<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Transport;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Lex\Notifications\Slack\Configuration\SlackConfiguration;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Exception\SlackTransportException;
use Lex\Notifications\Slack\Message\SlackMessage;
use TYPO3\CMS\Core\Http\RequestFactory;

/** Handles both Incoming Webhooks and chat.postMessage, with no implicit retries. */
final readonly class SlackTransport
{
    public function __construct(
        private RequestFactory $requestFactory,
        private SlackConfiguration $configuration,
    ) {}

    /** @return array<string,mixed> Slack acknowledgement; API responses include ts/channel. */
    public function send(SlackMessage $message, SlackRoute $route): array
    {
        $payload = $message->toArray();
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];
        if ($route->transport === 'webhook') {
            $url = $route->destination;
        } elseif ($route->transport === 'webhook_named') {
            $url = $this->configuration->namedWebhook($route->destination);
        } elseif ($route->transport === 'api') {
            $url = $this->configuration->apiEndpoint();
            $payload['channel'] = $route->destination;
            $token = $route->token ?? $this->configuration->botToken();
            if(empty($token)) {
                throw new SlackTransportException('Slack API route requires an authentication token.');
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        } else {
            throw new SlackTransportException('Unknown Slack transport configuration.');
        }

        try {
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new SlackTransportException('Slack message cannot be encoded as JSON.');
        }

        try {
            $response = $this->requestFactory->request($url, 'POST', [
                'headers' => $headers,
                'body' => $json,
                'connect_timeout' => $this->configuration->connectTimeout(),
                'timeout' => $this->configuration->timeout(),
                'http_errors' => false,
                'allow_redirects' => false,
            ]);
        } catch (GuzzleException $exception) {
            // Never chain an HTTP exception: its message may contain a secret webhook URL.
            throw new SlackTransportException(
                'Slack request failed. Delivery status is unknown.',
                retryable: true,
            );
        }

        $status = $response->getStatusCode();
        $retryHeader = trim($response->getHeaderLine('Retry-After'));
        $retryAfter = ctype_digit($retryHeader) ? (int)$retryHeader : null;

        if ($status === 429) {
            throw new SlackTransportException('Slack rate limit exceeded.', $status, $retryAfter, true);
        }
        if ($status >= 500) {
            throw new SlackTransportException('Slack server failure; delivery status unknown.', $status, null, true);
        }
        if ($status < 200 || $status >= 300) {
            throw new SlackTransportException('Slack rejected the request (HTTP ' . $status . ').', $status);
        }

        $body = trim((string)$response->getBody());
        if ($route->transport !== 'api') {
            if ($body !== 'ok') {
                throw new SlackTransportException('Unexpected Slack webhook response.', $status);
            }
            return ['ok' => true];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SlackTransportException('Slack API response contains invalid JSON.', $status);
        }

        if (!is_array($decoded) || ($decoded['ok'] ?? null) !== true) {
            $error = is_array($decoded) && is_string($decoded['error'] ?? null)
                ? $decoded['error'] : 'unknown_error';
            // Avoid reflecting untrusted free text from Slack into exception logs.
            $safeError = preg_match('/^[a-z0-9_]{1,100}$/D', $error) ? $error : 'unknown_error';
            throw new SlackTransportException(
                'Slack API error: ' . $safeError,
                $status,
                $retryAfter,
                in_array($safeError, ['ratelimited', 'rate_limited', 'internal_error', 'fatal_error', 'service_unavailable'], true),
            );
        }
        return $decoded;
    }
}
