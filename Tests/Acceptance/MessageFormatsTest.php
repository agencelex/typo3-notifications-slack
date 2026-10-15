<?php

declare(strict_types=1);

namespace Lex\Notifications\Slack\Tests\Acceptance;

use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Message\SlackMessage;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Http\FakeSlack;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notifiable\SlackRecipient;
use Lex\Notifications\Slack\Tests\Acceptance\Fixtures\Notification\SlackMessageNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SlackPhp\BlockKit\Kit;

/**
 * Every way to build the Slack message, checked on the JSON body that actually leaves TYPO3,
 * for both an Incoming Webhook and the Web API.
 */
final class MessageFormatsTest extends AbstractSlackAcceptanceTestCase
{
    private const BLOCK_KIT_BUILDER_JSON = <<<'JSON'
        {
            "blocks": [
                {"type": "header", "text": {"type": "plain_text", "text": "TYPO3 alert"}},
                {"type": "section", "text": {"type": "mrkdwn", "text": "*Disk usage* is above 90%"}},
                {"type": "actions", "elements": [
                    {"type": "button", "text": {"type": "plain_text", "text": "Open backend"}, "url": "https://example.com/typo3/"}
                ]}
            ]
        }
        JSON;

    /** @return array<string,mixed> the payload received by Slack, without the API routing field */
    private function deliver(SlackMessage $message, SlackRoute $route): array
    {
        (new SlackRecipient($route))->notify(new SlackMessageNotification($message));
        $payload = FakeSlack::lastRequest()->payload();
        unset($payload['channel']);
        return $payload;
    }

    /** @return iterable<string, array{SlackRoute}> */
    public static function routes(): iterable
    {
        yield 'Incoming Webhook' => [SlackRoute::webhookNamed('monitoring')];
        yield 'Web API' => [SlackRoute::api(self::CHANNEL_ID)];
    }

    #[Test]
    #[DataProvider('routes')]
    public function plainTextWithSlackMarkdown(SlackRoute $route): void
    {
        self::assertSame(
            ['text' => ':white_check_mark: *Job finished* <https://example.com|details>'],
            $this->deliver(SlackMessage::text(':white_check_mark: *Job finished* <https://example.com|details>'), $route)
        );
    }

    #[Test]
    #[DataProvider('routes')]
    public function unicodeAndSlashesAreSentVerbatim(SlackRoute $route): void
    {
        $this->deliver(SlackMessage::text('Déploiement réussi ✅ https://example.com/a/b'), $route);

        self::assertStringContainsString('Déploiement réussi ✅ https://example.com/a/b', FakeSlack::lastRequest()->rawBody());
    }

    #[Test]
    #[DataProvider('routes')]
    public function blockKitBuilderJsonObjectWithFallbackText(SlackRoute $route): void
    {
        $payload = $this->deliver(SlackMessage::fromJson(self::BLOCK_KIT_BUILDER_JSON)->withText('TYPO3 alert'), $route);

        self::assertSame('TYPO3 alert', $payload['text']);
        self::assertSame(json_decode(self::BLOCK_KIT_BUILDER_JSON, true)['blocks'], $payload['blocks']);
    }

    #[Test]
    #[DataProvider('routes')]
    public function bareJsonArrayOfBlocks(SlackRoute $route): void
    {
        $blocks = '[{"type":"section","text":{"type":"mrkdwn","text":"Only blocks"}}]';

        self::assertSame(['blocks' => json_decode($blocks, true)], $this->deliver(SlackMessage::fromJson($blocks), $route));
    }

    #[Test]
    #[DataProvider('routes')]
    public function rawJsonPassesNewerBlockTypesThroughUntouched(SlackRoute $route): void
    {
        // Not (yet) modelled by slack-php/slack-block-kit: raw JSON is not hydrated, so it still works.
        $json = '[{"type":"markdown","text":"**Future** block"}]';

        self::assertSame(['blocks' => [['type' => 'markdown', 'text' => '**Future** block']]], $this->deliver(SlackMessage::fromJson($json), $route));
    }

    #[Test]
    #[DataProvider('routes')]
    public function nativeBlockKitLibraryMessage(SlackRoute $route): void
    {
        $payload = $this->deliver(SlackMessage::fromBlockKit(Kit::message(
            blocks: [
                Kit::header('Weekly report'),
                Kit::section(Kit::mrkdwnText('*42* new frontend users')),
                Kit::divider(),
            ],
            text: 'Weekly report',
        )), $route);

        self::assertSame('Weekly report', $payload['text']);
        self::assertSame(['header', 'section', 'divider'], array_column($payload['blocks'], 'type'));
        self::assertSame('*42* new frontend users', $payload['blocks'][1]['text']['text']);
    }

    #[Test]
    #[DataProvider('routes')]
    public function phpArrayPayload(SlackRoute $route): void
    {
        $payload = [
            'text' => 'Fallback',
            'blocks' => [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*From an array*']]],
            'mrkdwn' => true,
            'link_names' => true,
            'parse' => 'none',
            'metadata' => ['event_type' => 'typo3_deploy', 'event_payload' => ['site' => 'main']],
        ];

        self::assertSame($payload, $this->deliver(SlackMessage::fromArray($payload), $route));
    }

    #[Test]
    #[DataProvider('routes')]
    public function textEnrichedWithBlocks(SlackRoute $route): void
    {
        $blocks = [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => 'Details']]];

        self::assertSame(
            ['text' => 'Summary', 'blocks' => $blocks],
            $this->deliver(SlackMessage::text('Summary')->withBlocks($blocks), $route)
        );
    }

    #[Test]
    #[DataProvider('routes')]
    public function legacyAttachments(SlackRoute $route): void
    {
        $attachments = [[
            'color' => '#d00000',
            'title' => 'Error 500',
            'text' => 'Uncaught exception on /checkout',
            'fields' => [['title' => 'Site', 'value' => 'main', 'short' => true]],
        ]];

        self::assertSame(
            ['text' => 'Alert', 'attachments' => $attachments],
            $this->deliver(SlackMessage::text('Alert')->withAttachments($attachments), $route)
        );
    }

    #[Test]
    public function threadReplyViaTheWebApi(): void
    {
        $payload = $this->deliver(SlackMessage::text('Update')->inThread('1729000000.123456'), SlackRoute::api(self::CHANNEL_ID));

        self::assertSame(['text' => 'Update', 'thread_ts' => '1729000000.123456'], $payload);
    }

    #[Test]
    public function threadReplyAlsoBroadcastToTheChannel(): void
    {
        $payload = $this->deliver(
            SlackMessage::text('Resolved')->inThread('1729000000.123456', broadcast: true),
            SlackRoute::api(self::CHANNEL_ID)
        );

        self::assertSame(['text' => 'Resolved', 'thread_ts' => '1729000000.123456', 'reply_broadcast' => true], $payload);
    }

    #[Test]
    #[DataProvider('routes')]
    public function withoutLinkAndMediaUnfurling(SlackRoute $route): void
    {
        self::assertSame(
            ['text' => 'See https://example.com', 'unfurl_links' => false, 'unfurl_media' => false],
            $this->deliver(SlackMessage::text('See https://example.com')->withoutUnfurling(), $route)
        );
    }

    #[Test]
    public function threadReplyThatIsAlsoNotUnfurled(): void
    {
        $payload = $this->deliver(
            SlackMessage::fromJson(self::BLOCK_KIT_BUILDER_JSON)->withText('TYPO3 alert')->inThread('1729000000.123456')->withoutUnfurling(),
            SlackRoute::api(self::CHANNEL_ID)
        );

        self::assertSame('1729000000.123456', $payload['thread_ts']);
        self::assertFalse($payload['unfurl_links']);
        self::assertCount(3, $payload['blocks']);
    }

    #[Test]
    public function aMessageCannotOverrideTheRoutedChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Slack message field: channel');

        SlackMessage::fromArray(['text' => 'Hijack', 'channel' => 'C0EVIL00000']);
    }
}
