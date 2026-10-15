<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http;

use Psr\Http\Message\RequestInterface;

/** A request as it would have left the TYPO3 instance, with the Guzzle transfer options. */
final readonly class RecordedRequest
{
    /** @param array<string,mixed> $options */
    public function __construct(
        public RequestInterface $request,
        public array $options,
    ) {}

    public function url(): string
    {
        return (string)$this->request->getUri();
    }

    public function method(): string
    {
        return $this->request->getMethod();
    }

    public function header(string $name): string
    {
        return $this->request->getHeaderLine($name);
    }

    public function rawBody(): string
    {
        return (string)$this->request->getBody();
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return json_decode($this->rawBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
