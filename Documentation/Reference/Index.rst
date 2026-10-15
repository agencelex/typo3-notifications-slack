..  _slack-api-reference:

=============
API reference
=============

Public identifiers
==================

``Lex\\Notifications\\Slack\\Channel\\SlackChannel``
    Implements the core ``ChannelInterface``. The channel name returned by
    ``getName()`` is ``slack``. ``send(object, Notification): void``
    resolves the route, calls ``toSlack()`` and hands the message to the
    transport. It does not return a Slack message timestamp.

``Lex\\Notifications\\Slack\\Configuration\\SlackRoute``
    Immutable destination value object with public readonly
    ``transport`` and ``destination`` properties.

``Lex\\Notifications\\Slack\\Message\\SlackMessage``
    Immutable message payload with ``JsonSerializable`` support.

``Lex\\Notifications\\Slack\\Transport\\SlackTransport``
    Sends an HTTPS request. ``send(SlackMessage, SlackRoute): array``
    returns the Slack acknowledgement; when using the API this may
    contain Slack's ``ts`` and ``channel`` fields.

``Lex\\Notifications\\Slack\\Configuration\\SlackConfiguration``
    Reads configuration through TYPO3's extension configuration service.
    Provides ``botToken()``, ``namedWebhook()``, ``apiEndpoint()``,
    ``timeout()``, and ``connectTimeout()``.

``Lex\\Notifications\\Slack\\Exception\\SlackTransportException``
    Exception carrying HTTP status, retry delay and retryability.

``Lex\\Notifications\\Slack\\Domain\\Model\\Ability\\CanSendToSlack``
    Trait declaring ``toSlack(object $notifiable): SlackMessage``.

``Lex\\Notifications\\Slack\\Domain\\Model\\Ability\\HasRouteNotificationForSlack``
    Trait declaring ``routeNotificationForSlack(?Notification $notification = null)``.

SlackRoute factory methods
==========================

..  csv-table::
    :header: "Method", "Result"
    :widths: 57, 43

    "``webhook(string $url)``", "Validate and store a direct webhook URL."
    "``webhookNamed(string $name)``", "Store a configuration alias."
    "``api(string $channel)``", "Validate and store a Slack ID."
    "``fromString(string $value)``", "Interpret a direct HTTPS URL, ``webhook:name`` or API ID."
    "``assertWebhookUrl(string $url)``", "Validate the Slack/GovSlack HTTPS URL; throws on failure."

SlackMessage factory and modifier methods
=========================================

..  csv-table::
    :header: "Method", "Purpose"
    :widths: 47, 53

    "``text(string $text)``", "Create a plain-text message."
    "``fromArray(array $payload)``", "Create from a permitted Slack payload array."
    "``fromJson(string $json)``", "Create from Block Kit Builder JSON or a block list."
    "``fromBlockKit(BlockKitMessage $message)``", "Validate and serialize a Block Kit PHP surface."
    "``withText(string $text)``", "Return a copy with fallback text."
    "``withBlocks(array $blocks)``", "Return a copy with blocks."
    "``withAttachments(array $attachments)``", "Return a copy with legacy attachments."
    "``inThread(string $timestamp, bool $broadcast = false)``", "Return a threaded message."
    "``withoutUnfurling()``", "Disable link/media previews."
    "``toArray()``", "Get payload array."
    "``toJson()``", "Serialize message as JSON."

Messages may not set ``channel`` or ``token``; these are managed by the
route and transport. Unsupported message keys and structurally invalid
payloads raise ``InvalidArgumentException``.

Service registration
====================

``SlackChannel`` is automatically registered by its
``#[AutoconfigureTag('notifications.channel')]`` attribute. TYPO3's
``Configuration/Services.yaml`` enables autowiring/autoconfiguration.
No additional manual service tagging is needed.

See :ref:`Error handling <slack-errors>` for exception details.
