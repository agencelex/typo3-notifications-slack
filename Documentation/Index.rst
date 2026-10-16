..  _start:

===============================
Laravel-style Notification System - Slack Channel
===============================

Send notifications from TYPO3 to Slack using Incoming Webhooks or the
Slack Web API. Compose messages using plain text, legacy attachments,
Block Kit PHP objects, or JSON copied from Slack's Block Kit Builder.

**Requirements:** TYPO3 13.4 or 14.3+, PHP 8.2+, and
``agencelex/notifications`` 1.5+. The Composer package is
``agencelex/notifications-slack``; its TYPO3 extension key is
``notifications_slack``.

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: :ref:`Overview <slack-introduction>`

        Learn what the channel supports and how it fits into the
        LEX Notifications ecosystem.

    ..  card:: :ref:`Installation <slack-installation>`

        Install the Composer package and prepare a Slack application
        with an Incoming Webhook or Bot Token.

    ..  card:: :ref:`Configuration <slack-configuration>`

        Configure named webhooks, Bot Tokens, GovSlack, and HTTP timeouts
        in TYPO3's ``additional.php``.

    ..  card:: :ref:`Quick start <slack-usage>`

        Create your first notification and deliver it with
        ``NotificationManager`` or from a notifiable model.

    ..  card:: :ref:`Routing <slack-routing>`

        Choose between named webhooks, direct webhooks, and
        Slack Web API conversation IDs.

    ..  card:: :ref:`Message formats <slack-message-formats>`

        Build plain-text messages, Block Kit layouts, legacy attachments,
        and thread replies.

    ..  card:: :ref:`Queued notifications <slack-queued-notifications>`

        Use the core notification queue while keeping webhook secrets
        out of serialized route values.

    ..  card:: :ref:`API reference <slack-api-reference>`

        Review the public PHP classes, methods, message options, and
        exception properties.

    ..  card:: :ref:`Security <slack-security>`

        Protect credentials, configure least-privilege access, and
        understand delivery failure modes.

    ..  card:: :ref:`Troubleshooting <slack-troubleshooting>`

        Diagnose invalid routes, Slack API errors, authorization
        failures, and queued delivery issues.

    ..  card:: :ref:`Development and tests <slack-development>`

        Run the PHPUnit suites and extend the channel without altering
        the notification core.

..  toctree::
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
    Usage/Routing
    Usage/MessageFormats
    Usage/QueuedNotifications
    Reference/Index
    Reference/ErrorHandling
    Security/Index
    Troubleshooting/Index
    Developer/Index
