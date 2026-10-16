..  _slack-configuration:

=============
Configuration
=============

The extension uses TYPO3 extension configuration under the **actual key**
``notifications_slack``. Configure the values in your TYPO3 installation's
``config/system/additional.php``. The extension repository also contains
``Configuration/ExampleAdditional.php`` as a reference; it is not loaded
by TYPO3 automatically.

Example configuration
=====================

..  code-block:: php
    :caption: config/system/additional.php

    <?php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['notifications_slack'] = [
        'botToken' => getenv('SLACK_BOT_TOKEN') ?: '',
        'webhooks' => [
            'monitoring' => getenv('SLACK_WEBHOOK_MONITORING') ?: '',
            'security' => getenv('SLACK_WEBHOOK_SECURITY') ?: '',
        ],
        'apiEndpoint' => 'https://slack.com/api',
        'timeout' => 10,
        'connectTimeout' => 5,
    ];

Configuration options
=====================

..  csv-table::
    :header: "Key", "Type / default", "Purpose"
    :widths: 24, 25, 51

    "``botToken``", "string", "Slack Bot Token, read when an API message is sent but the token is not explicitly specified."
    "``webhooks``", "array / empty", "Map of safe alias names to complete HTTPS webhook URLs."
    "``apiEndpoint``", "string / ``https://slack.com/api``", "Slack API root; GovSlack: ``https://slack-gov.com/api``."
    "``timeout``", "integer / 10", "Overall HTTP request timeout (seconds)."
    "``connectTimeout``", "integer / 5", "HTTP connection timeout (seconds)."

Both timeout values must be integers from **1 to 120**. The endpoint
setting is restricted to the two documented hosts. The client appends
``/chat.postMessage`` automatically; do not append it in your configuration.

Named webhooks
==============

The ``webhooks`` map may contain multiple destinations. An alias such
as ``monitoring`` can then be selected in application code:

..  code-block:: php

    use Lex\Notifications\Slack\Configuration\SlackRoute;

    $route = SlackRoute::webhookNamed('monitoring');
    // Equivalent route string: 'webhook:monitoring'

Aliases must start with a letter and can contain letters, numbers,
underscores, and hyphens (maximum 64 characters). A named webhook URL
is resolved from the configuration when the transport sends the message.

Using environment variables
===========================

The configuration can load its secrets from environment variables or
another protected provider. Configure both the HTTP PHP process and the
CLI/queue workers to receive the same secrets.

Do not commit tokens or webhook URLs, disclose configuration dumps, or
make ``additional.php`` publicly readable. See :ref:`Security <slack-security>`.

GovSlack
========

For GovSlack deployments, configure:

..  code-block:: php

    'apiEndpoint' => 'https://slack-gov.com/api',

Use the official GovSlack Incoming Webhook host
``https://hooks.slack-gov.com`` for webhook routes. The extension validates
approved Slack/GovSlack hosts and requires HTTPS.

..  note::

    These values are accepted by the source code. Availability of GovSlack
    methods, workspace access, and permission grants depends on your Slack
    organization and application configuration.
