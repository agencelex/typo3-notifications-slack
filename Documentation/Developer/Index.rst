..  _slack-development:

=====================
Development and tests
=====================

Architecture
============

* ``Channel/SlackChannel.php`` — selects the route, calls ``toSlack()``,
  and invokes the transport; implements ``ChannelInterface``.
* ``Configuration/SlackRoute.php`` — validated destination value object.
* ``Configuration/SlackConfiguration.php`` — TYPO3 extension configuration
  and secrets at the delivery boundary.
* ``Message/SlackMessage.php`` — immutable Slack message content.
* ``Transport/SlackTransport.php`` — HTTPS request/response handling.
* ``Exception/SlackTransportException.php`` — delivery failure details.

The extension uses service autowiring and the
``notifications.channel`` tag. Changes to the notification core are not
required to register the channel.

Run tests
=========

From the extension source root, install development dependencies:

..  code-block:: bash

    composer install

    composer test:php:unit
    composer test:php:acceptance
    composer phpstan
    composer cgl:ci

The ``composer.json`` also defines ``composer test``, which includes
lint, unit, functional, and acceptance scripts. Consult the repository
for the exact scripts available in the checkout.

Tests are grouped under ``Tests/Unit/`` and ``Tests/Acceptance/``.
Acceptance tests boot TYPO3 with a simulated Slack HTTP handler and do
not send live Slack notifications. They cover the main routing entry
points, formats, queued message serialization and failures.

..  note::

    A repository checkout may require its own testing dependencies and
    a valid TYPO3/PHP test matrix. Test both TYPO3 13.4 and 14.3 before
    asserting full cross-version compatibility.

Build and preview documentation
===============================

TYPO3's documentation renderer supports the reStructuredText files in
``Documentation/``. From the root of the extension, with Docker
available, run:

..  code-block:: bash

    docker run --rm --pull always \
      -v "$(pwd):/project" \
      ghcr.io/typo3-documentation/render-guides:latest

For renderer options and local preview instructions, see the
`TYPO3 documentation guide
<https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/>`__.

Contributing
============

Keep the public API backward compatible and add or adjust tests for
message validation, route changes, network errors, and security behavior.
When changing configuration keys or APIs, update these documentation
pages and ``README.md`` together.
