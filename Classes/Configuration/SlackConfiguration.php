<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Configuration;

use Lex\Notifications\Slack\Extension;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Centralized TYPO3 configuration; secrets are resolved only at send time. */
readonly class SlackConfiguration
{
    public function __construct(
        #[Autowire(expression: 'service("extension-configuration").get("' . Extension::KEY . '")')]
        protected array $settings
    ){}

    public function botToken(): string
    {
        $value = $this->settings['botToken'] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Slack Bot Token is not configured.');
        }
        return trim($value);
    }

    public function namedWebhook(string $name): string
    {
        $webhooks = $this->settings['webhooks'] ?? [];
        $value = is_array($webhooks) ? ($webhooks[$name] ?? null) : null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Named Slack webhook is not configured: ' . $name);
        }
        SlackRoute::assertWebhookUrl($value);
        return $value;
    }

    public function apiEndpoint(): string
    {
        $value = $this->settings['apiEndpoint'] ?? 'https://slack.com/api';
        if (!is_string($value) || !in_array($value, [
            'https://slack.com/api',
            'https://slack-gov.com/api',
        ], true)) {
            throw new RuntimeException('Invalid Slack API endpoint configuration.');
        }
        return $value . '/chat.postMessage';
    }

    public function timeout(): int
    {
        return $this->positiveInt('timeout', 10);
    }

    public function connectTimeout(): int
    {
        return $this->positiveInt('connectTimeout', 5);
    }

    private function positiveInt(string $name, int $fallback): int
    {
        $value = $this->settings[$name] ?? $fallback;
        if (!is_int($value) || $value < 1 || $value > 120) {
            throw new RuntimeException('Invalid Slack timeout configuration: ' . $name);
        }
        return $value;
    }
}
