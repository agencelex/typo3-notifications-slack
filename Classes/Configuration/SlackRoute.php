<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Configuration;

use InvalidArgumentException;
use Lex\Notifications\Slack\Extension;

/**
 * Serializable destination. Prefer named webhooks for queued notifications:
 * a direct webhook URL is itself a secret and would be serialized into the queue.
 */
final readonly class SlackRoute
{
    private function __construct(
        public string  $transport,
        public string  $destination,
        public ?string $token = null,
    ) {}

    public static function webhook(string $url): self
    {
        self::assertWebhookUrl($url);
        return new self(Extension::TRANSPORT_NAME_WEBHOOK, $url);
    }

    public static function webhookNamed(string $name): self
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $name)) {
            throw new InvalidArgumentException('Invalid Slack webhook name.');
        }
        return new self('webhook_named', $name);
    }

    public static function api(string $channel, ?string $token = null): self
    {
        // Require a conversation ID instead of ambiguous or renamed channel names.
        if (!preg_match('/^[CDGUW][A-Z0-9]{2,}$/D', $channel)) {
            throw new InvalidArgumentException('Slack API route requires a channel/conversation/user ID.');
        }

        return new self(Extension::TRANSPORT_NAME_API, $channel, $token);
    }

    public static function fromString(string $value): self
    {
        if (str_starts_with(strtolower($value), 'https://')) {
            return self::webhook($value);
        }
        if (str_contains($value, '://')) {
            throw new InvalidArgumentException('Slack URLs must be approved HTTPS Incoming Webhooks.');
        }
        // Explicit aliases avoid passing secrets into the queue.
        if (str_starts_with($value, Extension::TRANSPORT_NAME_WEBHOOK . ':')) {
            return self::webhookNamed(substr($value, strlen(Extension::TRANSPORT_NAME_WEBHOOK . ':')));
        }
        return self::api($value);
    }

    public static function assertWebhookUrl(string $url): void
    {
        $parts = parse_url($url);
        if (
            $parts === false
            || ($parts['scheme'] ?? '') !== 'https'
            || !in_array($parts['host'] ?? '', ['hooks.slack.com', 'hooks.slack-gov.com'], true)
            || !preg_match('~^/services/[^/]+/[^/]+/[^/]+$~D', $parts['path'] ?? '')
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException('Invalid Slack Incoming Webhook URL.');
        }
    }
}
