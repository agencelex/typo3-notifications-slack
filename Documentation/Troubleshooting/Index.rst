..  _slack-troubleshooting:

===============
Troubleshooting
===============

No Slack message appears
========================

Check the following in order:

#. ``lex_notifications`` and ``notifications_slack`` are installed.
#. The notification's ``via()`` method includes ``slack``.
#. ``routeNotificationForSlack()`` returns a nonempty route, or an
   on-demand route is configured.
#. The named webhook alias or Bot Token exists in TYPO3 configuration.
#. TYPO3/PHP can reach the relevant Slack HTTPS host.
#. An asynchronous Messenger worker is running if delivery is deferred.

``Slack Bot Token is not configured``
=====================================

The notification uses an API route, but ``botToken`` is missing or empty.
Verify the environment variable supplied to ``additional.php`` and make
sure the CLI worker sees the same configuration as PHP-FPM.

``Named Slack webhook is not configured``
=========================================

A named webhook alias was selected, but no matching nonempty entry was
found under ``webhooks``. Check spelling and configuration loading.

``Invalid Slack Incoming Webhook URL``
======================================

Direct/named webhooks must use HTTPS, approved Slack/GovSlack hosts and
a ``/services/.../.../...`` URL path, without custom ports or queries.

``channel_not_found`` or ``not_in_channel``
===========================================

Confirm that the Slack conversation ID is correct, that the app is
installed in the intended workspace and that the bot has access to the
conversation. Slack decides authorization; a syntactically valid ID is
not proof of permission.

HTTP 429 / rate limits
======================

Slack rate-limited the request. The exception may contain
``retryAfterSeconds``. Configure a Messenger retry strategy; it is not
applied automatically by this extension.

HTTP 5xx / network errors
=========================

Delivery may have occurred despite the error. Consider the potential
for duplicate Slack messages before retrying.

Block Kit message rejected
==========================

Check the JSON with `Slack Block Kit Builder <https://app.slack.com/block-kit-builder/>`__.
The extension only validates top-level structure for raw JSON. Nested
block limits and requirements are ultimately enforced by Slack.

Documentation and support
=========================

Issues can be reported at
`github.com/agencelex/typo3-notifications-slack/issues
<https://github.com/agencelex/typo3-notifications-slack/issues>`__.
