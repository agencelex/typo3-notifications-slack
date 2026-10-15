<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Message;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use SlackPhp\BlockKit\Surfaces\Message as BlockKitMessage;

/** An immutable Slack message, deliberately free of routing and credentials. */
final class SlackMessage implements JsonSerializable
{
    /** @param array<string,mixed> $payload */
    private function __construct(private array $payload)
    {
        $this->validate();
    }

    public static function text(string $text): self
    {
        return new self(['text' => $text]);
    }

    /** @param array<string,mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    /** Accepts a Block Kit Builder JSON object or a bare JSON array of blocks. */
    public static function fromJson(string $json): self
    {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Invalid Slack message JSON.', 0, $exception);
        }
        if (!is_array($payload) || $payload === []) {
            throw new InvalidArgumentException('Expected a Slack message object or non-empty array of blocks.');
        }
        if (array_is_list($payload)) {
            return new self(['blocks' => $payload]);
        }
        return new self($payload);
    }

    public static function fromBlockKit(BlockKitMessage $message): self
    {
        $message->validate();
        $array = $message->toArray();
        // Library surfaces may contain parameters not supported by chat.postMessage.
        $allowed = ['text' => true, 'blocks' => true, 'attachments' => true];
        return new self(array_intersect_key($array, $allowed));
    }

    public function withText(string $text): self
    {
        return $this->with('text', $text);
    }

    /** @param list<array<string,mixed>> $blocks */
    public function withBlocks(array $blocks): self
    {
        return $this->with('blocks', $blocks);
    }

    /** @param list<array<string,mixed>> $attachments */
    public function withAttachments(array $attachments): self
    {
        return $this->with('attachments', $attachments);
    }

    public function inThread(string $timestamp, bool $broadcast = false): self
    {
        if (!preg_match('/^[0-9]+\.[0-9]+$/D', $timestamp)) {
            throw new InvalidArgumentException('Invalid Slack thread timestamp.');
        }
        $clone = clone $this;
        $clone->payload['thread_ts'] = $timestamp;
        if ($broadcast) {
            $clone->payload['reply_broadcast'] = true;
        }
        $clone->validate();
        return $clone;
    }

    public function withoutUnfurling(): self
    {
        $clone = clone $this;
        $clone->payload['unfurl_links'] = false;
        $clone->payload['unfurl_media'] = false;
        return $clone;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        // Arrays use PHP's copy-on-write semantics.
        return $this->payload;
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(): string
    {
        return json_encode(
            $this->payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function with(string $field, mixed $value): self
    {
        $clone = clone $this;
        $clone->payload[$field] = $value;
        $clone->validate();
        return $clone;
    }

    private function validate(): void
    {
        // Prevent routing overrides, leaking credentials and unreviewed payload options.
        $allowed = [
            'text', 'blocks', 'attachments', 'thread_ts', 'reply_broadcast',
            'unfurl_links', 'unfurl_media', 'mrkdwn', 'parse', 'metadata', 'link_names',
        ];
        foreach ($this->payload as $key => $_) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Slack message field: ' . (string)$key);
            }
        }
        if (!isset($this->payload['text']) && !isset($this->payload['blocks']) && !isset($this->payload['attachments'])) {
            throw new InvalidArgumentException('Slack message requires text, blocks or attachments.');
        }
        if (isset($this->payload['text']) && !is_string($this->payload['text'])) {
            throw new InvalidArgumentException('Slack text must be a string.');
        }
        if (($this->payload['text'] ?? null) === '' && !isset($this->payload['blocks']) && !isset($this->payload['attachments'])) {
            throw new InvalidArgumentException('Slack message text cannot be empty.');
        }
        foreach (['blocks', 'attachments'] as $field) {
            if (!array_key_exists($field, $this->payload)) {
                continue;
            }
            $items = $this->payload[$field];
            if (!is_array($items) || !array_is_list($items) || $items === []) {
                throw new InvalidArgumentException($field . ' must be a non-empty list.');
            }
            foreach ($items as $item) {
                if (!is_array($item) || array_is_list($item) || $item === []) {
                    throw new InvalidArgumentException($field . ' must contain non-empty objects.');
                }
            }
        }
        if (isset($this->payload['thread_ts']) && (
            !is_string($this->payload['thread_ts'])
            || !preg_match('/^[0-9]+\.[0-9]+$/D', $this->payload['thread_ts'])
        )) {
            throw new InvalidArgumentException('Invalid Slack thread timestamp.');
        }
        foreach (['reply_broadcast', 'unfurl_links', 'unfurl_media', 'mrkdwn', 'link_names'] as $field) {
            if (isset($this->payload[$field]) && !is_bool($this->payload[$field])) {
                throw new InvalidArgumentException($field . ' must be boolean.');
            }
        }
        if (isset($this->payload['parse']) && !in_array($this->payload['parse'], ['none', 'full'], true)) {
            throw new InvalidArgumentException('Invalid Slack parse setting.');
        }
        if (isset($this->payload['metadata']) && (
            !is_array($this->payload['metadata']) || array_is_list($this->payload['metadata'])
        )) {
            throw new InvalidArgumentException('Slack metadata must be an object.');
        }
    }
}
