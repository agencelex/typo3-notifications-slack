..  _slack-installation:

============
Installation
============

System requirements
===================

* PHP **8.2+** (subject to the selected TYPO3 version's PHP requirements).
* TYPO3 CMS **13.4** or **14.3+**.
* Composer-based TYPO3 installation (required for the Block Kit library).
* ``agencelex/notifications`` version **1.5+**.
* Outbound HTTPS connectivity to the selected Slack endpoint.

Install with Composer
=====================

From the TYPO3 project root, run:

..  code-block:: bash

    composer require agencelex/notifications-slack:^1.0
    vendor/bin/typo3 cache:flush

..  note::

    If the package is not yet available in your configured Composer
    repositories, add the package's Git repository or a local path repository
    before running ``composer require``. The extension source alone does not
    install the third-party Block Kit dependency.

The Composer package identifies itself as extension key
``notifications_slack``. Ensure the main ``lex_notifications`` extension
is installed and active as well.

Configure Slack
===============

Choose at least one transport:

**Incoming Webhook**
    Create/configure a Slack app, enable Incoming Webhooks, and add a webhook
    to the intended conversation. Save the complete HTTPS URL as a secret.
    A webhook is tied to its configured destination.

**Slack Web API**
    Install a Slack app with the required ``chat:write`` permission. Obtain
    its Bot Token and select the target conversation ID. Ensure the bot
    is permitted to post in the conversation.

Follow :ref:`Configuration <slack-configuration>` to register these credentials
and :ref:`Quick start <slack-usage>` to send a notification.

..  seealso::

    `Slack Incoming Webhooks <https://api.slack.com/messaging/webhooks>`__ and
    `chat.postMessage <https://api.slack.com/methods/chat.postMessage>`__.
