<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Unit\Message;

use InvalidArgumentException;
use Lex\Notifications\Slack\Message\SlackMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SlackPhp\BlockKit\Kit;
use SlackPhp\BlockKit\Validation\ValidationException;

final class SlackMessageTest extends TestCase
{
    private const BLOCK = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*Hello*']];

    #[Test]
    public function textAndImmutability(): void
    {
        $first = SlackMessage::text('before');
        $after = $first->withText('after');

        self::assertSame(['text' => 'before'], $first->toArray());
        self::assertSame(['text' => 'after'], $after->toArray());
    }

    #[Test]
    public function everyWitherReturnsANewInstance(): void
    {
        $message = SlackMessage::text('base');

        self::assertNotSame($message, $message->withBlocks([self::BLOCK]));
        self::assertNotSame($message, $message->withAttachments([['text' => 'x']]));
        self::assertNotSame($message, $message->inThread('1.2'));
        self::assertNotSame($message, $message->withoutUnfurling());
        self::assertSame(['text' => 'base'], $message->toArray());
    }

    #[Test]
    public function textWithBlocks(): void
    {
        self::assertSame(
            ['text' => 'fallback', 'blocks' => [self::BLOCK]],
            SlackMessage::text('fallback')->withBlocks([self::BLOCK])->toArray()
        );
    }

    #[Test]
    public function legacyAttachments(): void
    {
        $message = SlackMessage::text('fallback')->withAttachments([
            ['fallback' => 'Warning', 'color' => '#ff0000', 'text' => 'Watch out'],
        ]);

        self::assertSame('#ff0000', $message->toArray()['attachments'][0]['color']);
    }

    #[Test]
    public function builderJsonAcceptsObjectAndBareArray(): void
    {
        $object = json_encode(['blocks' => [self::BLOCK], 'text' => 'fallback'], JSON_THROW_ON_ERROR);
        $bare = json_encode([self::BLOCK], JSON_THROW_ON_ERROR);

        self::assertSame(['blocks' => [self::BLOCK], 'text' => 'fallback'], SlackMessage::fromJson($object)->toArray());
        self::assertSame(['blocks' => [self::BLOCK]], SlackMessage::fromJson($bare)->toArray());
    }

    #[Test]
    public function rawJsonIsNotHydratedSoNewBlockTypesPassThrough(): void
    {
        $blocks = [['type' => 'markdown', 'text' => '**future block**']];

        self::assertSame(['blocks' => $blocks], SlackMessage::fromJson(json_encode($blocks, JSON_THROW_ON_ERROR))->toArray());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidJson(): iterable
    {
        yield 'syntax error' => ['{not valid'];
        yield 'empty object' => ['{}'];
        yield 'empty array' => ['[]'];
        yield 'scalar' => ['"text"'];
        yield 'null' => ['null'];
        yield 'object without content' => ['{"parse":"none"}'];
    }

    #[Test]
    #[DataProvider('invalidJson')]
    public function rejectsInvalidJson(string $json): void
    {
        $this->expectException(InvalidArgumentException::class);

        SlackMessage::fromJson($json);
    }

    #[Test]
    public function blockKitLibraryMessage(): void
    {
        $built = Kit::message(blocks: [Kit::section('Hello'), Kit::divider()]);
        $payload = SlackMessage::fromBlockKit($built)->withText('Fallback')->toArray();

        self::assertSame('Fallback', $payload['text']);
        self::assertSame(['section', 'divider'], array_column($payload['blocks'], 'type'));
    }

    #[Test]
    public function blockKitLibraryMessageKeepsOnlyChatPostMessageContent(): void
    {
        // The library serializes e.g. threadTs in camelCase; threads are set with inThread() instead.
        $built = Kit::message(blocks: [Kit::divider()], text: 'x', mrkdwn: true, threadTs: '1729000000.123456');

        self::assertSame(['blocks' => [['type' => 'divider']], 'text' => 'x'], SlackMessage::fromBlockKit($built)->toArray());
    }

    #[Test]
    public function invalidBlockKitLibraryMessageIsRejectedByTheLibrary(): void
    {
        $this->expectException(ValidationException::class);

        SlackMessage::fromBlockKit(Kit::message(blocks: [Kit::section()]));
    }

    #[Test]
    public function threadReply(): void
    {
        self::assertSame(
            ['text' => 'update', 'thread_ts' => '1729000000.123456'],
            SlackMessage::text('update')->inThread('1729000000.123456')->toArray()
        );
    }

    #[Test]
    public function threadReplyWithBroadcast(): void
    {
        self::assertSame(
            ['text' => 'update', 'thread_ts' => '1729000000.123456', 'reply_broadcast' => true],
            SlackMessage::text('update')->inThread('1729000000.123456', broadcast: true)->toArray()
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidThreadTimestamps(): iterable
    {
        yield 'no dot' => ['1729000000'];
        yield 'letters' => ['abc.def'];
        yield 'empty' => [''];
        yield 'trailing newline' => ["1729000000.123456\n"];
    }

    #[Test]
    #[DataProvider('invalidThreadTimestamps')]
    public function rejectsInvalidThreadTimestamp(string $timestamp): void
    {
        $this->expectException(InvalidArgumentException::class);

        SlackMessage::text('update')->inThread($timestamp);
    }

    #[Test]
    public function withoutUnfurling(): void
    {
        self::assertSame(
            ['text' => 'link', 'unfurl_links' => false, 'unfurl_media' => false],
            SlackMessage::text('link')->withoutUnfurling()->toArray()
        );
    }

    #[Test]
    public function acceptsEveryAllowedOptionalField(): void
    {
        $payload = [
            'text' => 'hi',
            'mrkdwn' => true,
            'link_names' => true,
            'parse' => 'full',
            'metadata' => ['event_type' => 'deploy', 'event_payload' => ['id' => 1]],
        ];

        self::assertSame($payload, SlackMessage::fromArray($payload)->toArray());
    }

    #[Test]
    public function jsonEncodingKeepsUnicodeAndSlashes(): void
    {
        $message = SlackMessage::text('Réussi ✅ https://example.com/a');

        self::assertSame('{"text":"Réussi ✅ https://example.com/a"}', $message->toJson());
        self::assertSame($message->toArray(), $message->jsonSerialize());
    }

    /** @return iterable<string, array{array<string,mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield 'routing override' => [['text' => 'hi', 'channel' => 'C0123456789']];
        yield 'credential field' => [['text' => 'hi', 'token' => 'secret']];
        yield 'no content' => [['parse' => 'none']];
        yield 'empty text only' => [['text' => '']];
        yield 'text not a string' => [['text' => 42]];
        yield 'blocks empty' => [['blocks' => []]];
        yield 'blocks not a list' => [['blocks' => ['first' => self::BLOCK]]];
        yield 'block not an object' => [['blocks' => ['section']]];
        yield 'block is a list' => [['blocks' => [['section']]]];
        yield 'attachments not an array' => [['attachments' => 'x']];
        yield 'boolean option as string' => [['text' => 'hi', 'unfurl_links' => 'false']];
        yield 'invalid parse' => [['text' => 'hi', 'parse' => 'html']];
        yield 'metadata is a list' => [['text' => 'hi', 'metadata' => ['a', 'b']]];
        yield 'thread_ts not a string' => [['text' => 'hi', 'thread_ts' => 1729000000.123]];
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function rejectsInvalidPayload(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        SlackMessage::fromArray($payload);
    }

    #[Test]
    public function emptyTextIsAllowedWithBlocks(): void
    {
        self::assertSame(['text' => '', 'blocks' => [self::BLOCK]], SlackMessage::fromArray(['text' => '', 'blocks' => [self::BLOCK]])->toArray());
    }

    #[Test]
    public function witherValidatesItsValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SlackMessage::text('hi')->withBlocks([]);
    }
}
