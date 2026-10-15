<?php declare(strict_types=1);

namespace Lex\Notifications\Slack\Exception;

use RuntimeException;

final class SlackTransportException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfterSeconds = null,
        public readonly bool $retryable = false,
    ) {
        // Do not pass through any exception that might leak a webhook URL/token.
        parent::__construct($message);
    }
}
