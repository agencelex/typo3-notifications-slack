..  _slack-message-formats:

===============
Message formats
===============

The ``SlackMessage`` value object can be created from text, a PHP payload
array, Block Kit Builder JSON, or a Block Kit PHP ``Message`` object. It
returns a new instance from each ``with...()`` method.

Plain text and mrkdwn
=====================

..  code-block:: php

    use Lex\Notifications\Slack\Message\SlackMessage;

    $message = SlackMessage::text(
        ':white_check_mark: *Deployment complete*' . "\n" .
        'The website is now online.'
    );

Slack interprets ``*bold*`` and other Slack formatting in supported text
surfaces. It is not the same syntax as arbitrary Markdown.

Block Kit Builder JSON
======================

Copy the **message JSON** from Slack's Block Kit Builder:

..  code-block:: php

    $json = <<<'JSON'
    {
      "blocks": [
        {
          "type": "header",
          "text": {"type": "plain_text", "text": "TYPO3 alert"}
        },
        {
          "type": "section",
          "text": {"type": "mrkdwn", "text": "*Status:* Needs attention"}
        }
      ]
    }
    JSON;

    $message = SlackMessage::fromJson($json)
        ->withText('TYPO3 alert: Needs attention');

A bare JSON array of blocks is accepted too. Raw blocks are passed
through without being converted into the library's PHP objects, so
newer Slack block types can be used if the Slack endpoint supports them.
The extension validates the payload structure, not every Slack-specific
nested block property; Slack can still reject invalid block contents.

Native Block Kit PHP objects
============================

The package depends on ``slack-php/slack-block-kit``:

..  code-block:: php

    use SlackPhp\BlockKit\Kit;
    use Lex\Notifications\Slack\Message\SlackMessage;

    $kit = Kit::message(blocks: [
        Kit::section('*TYPO3 alert*'),
        Kit::divider(),
    ]);

    $message = SlackMessage::fromBlockKit($kit)
        ->withText('TYPO3 alert');

``fromBlockKit()`` calls the library's ``validate()`` and ``toArray()``.
Only supported message content fields are taken from the library's
serialized surface.

Legacy attachments
==================

..  code-block:: php

    $message = SlackMessage::text('Production alert')
        ->withAttachments([
            [
                'fallback' => 'Production issue detected',
                'color' => '#D92D20',
                'title' => 'Production alert',
                'text' => 'Investigate the TYPO3 instance.',
                'fields' => [
                    [
                        'title' => 'Environment',
                        'value' => 'Production',
                        'short' => true,
                    ],
                ],
            ],
        ]);

Legacy attachments remain supported for existing integrations. Prefer
Block Kit for new layouts. ``text``, ``blocks`` and ``attachments`` may
be present together in the same payload.

PHP arrays
==========

..  code-block:: php

    $message = SlackMessage::fromArray([
        'text' => 'Monitoring update',
        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*All good*'],
            ],
        ],
    ]);

Thread replies
==============

..  code-block:: php

    $message = SlackMessage::text('Deployment follow-up')
        ->inThread('1729000000.123456')
        ->withoutUnfurling();

    // Optionally request broadcast to the channel (subject to Slack rules).
    $broadcast = SlackMessage::text('Important follow-up')
        ->inThread('1729000000.123456', true);

The thread timestamp must match ``digits.digits``. Whether a thread
operation is available through a selected transport is determined by
Slack; for workflows that require message identifiers in the response,
use the Web API directly through the service-level transport rather than
assuming a notification channel's ``send()`` returns one.

Accessibility
=============

For Block Kit notifications, provide a meaningful top-level ``text``
fallback using ``withText()``. It benefits screen readers and notification
previews. Avoid embedding critical information only in visual decorations.

Accepted top-level properties
=============================

The value object currently permits ``text``, ``blocks``, ``attachments``,
``thread_ts``, ``reply_broadcast``, ``unfurl_links``, ``unfurl_media``,
``mrkdwn``, ``parse``, ``metadata``, and ``link_names``. ``channel`` and
``token`` are rejected. Arrays of ``blocks`` and ``attachments`` must be
nonempty lists of nonempty objects.
