..  _slack-routing:

=======
Routing
=======

Slack routing is independent of the message content. It is selected via
``NotificationManager::route()``, ``NotificationManager::routes()``, or a
notifiable's ``routeNotificationForSlack()`` method.

Three route styles
==================

..  csv-table::
    :header: "Route", "Example", "Notes"
    :widths: 23, 41, 36

    "Named webhook", "``SlackRoute::webhookNamed('monitoring')``", "Recommended for queued delivery; no URL secret in the route object."
    "Direct webhook", "``SlackRoute::webhook($url)``", "Approved HTTPS Slack URL; the URL is a secret."
    "Web API", "``SlackRoute::api('C0123456789')``", "Requires a configured Bot Token."

Named webhooks
==============

..  code-block:: php

    $manager->route(
        'slack',
        SlackRoute::webhookNamed('security')
    )->notify(new SecurityAlert());

    // Shorthand accepted by SlackRoute::fromString():
    $manager->route('slack', 'webhook:security')
        ->notify(new SecurityAlert());

The alias must exist in ``webhooks`` inside
``$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['notifications_slack']``.
If it does not exist, delivery fails with a configuration exception.

Direct webhook
==============

..  code-block:: php

    $manager->route('slack', SlackRoute::webhook($webhookUrl))
        ->notify(new SecurityAlert());

The validator permits HTTPS URLs with a Slack or GovSlack webhook host
and a ``/services/.../.../...`` path. A webhook URL is a credential.
Avoid serializing it into Messenger jobs; use a named webhook instead.

Slack Web API
=============

..  code-block:: php

    $manager->route('slack', SlackRoute::api('C0123456789'))
        ->notify(new SecurityAlert());

    // An ID string is also accepted:
    $manager->route('slack', 'C0123456789')
        ->notify(new SecurityAlert());

``SlackRoute::api()`` accepts a supported Slack conversation/user ID
prefix (``C``, ``D``, ``G``, ``U`` or ``W``) followed by uppercase
letters/digits. The Slack API ultimately determines whether that ID can
receive messages and whether the app has access.

Multiple recipients
===================

The core notification dispatcher can send one notification to a collection
of notifiable objects, each returning a different Slack route:

..  code-block:: php

    $manager->send(
        [$monitoringRecipient, $securityRecipient],
        new SecurityAlert(),
    );

``SlackMessage`` intentionally does **not** accept a ``channel`` or ``token``
field: routing and credentials belong to the channel and transport.
