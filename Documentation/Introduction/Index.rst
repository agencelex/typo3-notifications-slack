..  _slack-introduction:

========
Overview
========

The ``notifications_slack`` TYPO3 extension provides the ``slack``
channel for `Lex Notifications <https://github.com/agencelex/typo3-notifications>`__.
It converts a notification's ``toSlack()`` result into an HTTPS request
using TYPO3's HTTP ``RequestFactory``.

Features
========

* Slack Incoming Webhooks on approved HTTPS hosts.
* Slack Web API ``chat.postMessage`` with a Bot Token.
* GovSlack webhook hosts and API endpoint configuration.
* Plain text and Slack ``mrkdwn`` content.
* Legacy Slack attachments.
* Block Kit from ``slack-php/slack-block-kit`` objects.
* Raw Block Kit Builder JSON (including top-level block arrays).
* Thread replies and optional reply broadcasting.
* Integration with the core's on-demand, model-based, and queued delivery.
* HTTP rate-limit/error reporting without automatic transport retries.

What this extension does not provide
====================================

This is a **delivery channel**, not a general Slack client. It does not
create Slack applications, manage OAuth installation, upload files,
process interactive Slack callbacks, or subscribe to Slack events.

Message delivery requires an explicitly configured route. The notification
core evaluates ``routeNotificationFor('slack', ...)`` before calling
``SlackChannel``; setting a destination inside the message is neither
supported nor sufficient.

Technical identifiers
=====================

..  csv-table::
    :header: "Item", "Value"
    :widths: 30, 70

    "Composer package", "``agencelex/notifications-slack``"
    "TYPO3 extension key", "``notifications_slack``"
    "Channel name", "``slack``"
    "PHP namespace", "``Lex\\Notifications\\Slack``"
    "Core dependency", "``agencelex/notifications:^1.5``"
    "Block Kit dependency", "``slack-php/slack-block-kit:^2.3``"

Next: :ref:`install the extension <slack-installation>`.
