..  _slack-security:

========
Security
========

Credentials
===========

A Slack Bot Token and an Incoming Webhook URL are both credentials.
Avoid storing either in source control, notification content, or normal
application logs.

The recommended deployment pattern is:

* Inject secrets from environment variables, a protected configuration
  file, or an external secret store.
* Load them into TYPO3 configuration under ``notifications_slack``.
* Restrict access to TYPO3 configuration/debug tools and process environment.
* Ensure background workers have access to the required secret values.
* Rotate credentials immediately if they are disclosed.

..  warning::

    Using ``$GLOBALS['TYPO3_CONF_VARS']`` is not inherently unsafe, but
    any PHP code executing in the TYPO3 process may inspect it. Restrict
    who can install extensions and access sensitive configuration dumps.

Webhook routing
===============

Prefer ``SlackRoute::webhookNamed('monitoring')`` to a direct webhook URL.
A named route keeps the webhook URL out of the serializable route object
and resolves it from configuration during delivery.

``SlackRoute::webhook($url)`` is supported but should be avoided for
asynchronous/queued notifications if queue storage is accessible to
other systems or administrators.

HTTP and destinations
=====================

The transport:

* Restricts webhook hosts to ``hooks.slack.com`` and
  ``hooks.slack-gov.com`` with HTTPS.
* Restricts the Web API endpoint to the official Slack/GovSlack HTTPS roots.
* Disables HTTP redirects.
* Uses configured connection and total timeouts.
* Suppresses raw HTTP client exception details that could reveal secrets.
* Prevents ``SlackMessage`` from overriding ``channel`` or ``token``.

Operational security
====================

Use least-privilege permissions for the Slack application. For API
message posting, grant ``chat:write`` and only the additional permissions
actually required. Verify access to the destination conversation.

Protect queue storage, backups, error reporting integrations and CLI
worker process configuration. Where high-assurance isolation is needed,
consider a dedicated secret provider that avoids copying plaintext
credentials into broadly accessible configuration.

No retry guarantee
==================

Automatic retries are not implemented by the Slack transport. A failed
request does not necessarily prove Slack did not receive a message.
Review your Messenger retry policy, failure handling and duplicate
notification tolerance before production use.
