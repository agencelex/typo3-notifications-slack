..  _slack-errors:

==============
Error handling
==============

Delivery failure is reported by throwing an exception, rather than
quietly marking a failed message as successfully delivered.

``SlackTransportException``
===========================

The exception defines these readonly properties:

..  csv-table::
    :header: "Property", "Type", "Meaning"
    :widths: 28, 22, 50

    "``httpStatus``", "``?int``", "HTTP status if a response was received."
    "``retryAfterSeconds``", "``?int``", "Parsed Slack Retry-After header, when available."
    "``retryable``", "``bool``", "Whether retrying might be appropriate."

Transport behavior
==================

..  csv-table::
    :header: "Case", "Outcome"
    :widths: 33, 67

    "Webhook HTTP 2xx + ``ok``", "Success, returns ``['ok' => true]``."
    "API HTTP 2xx + JSON ``ok: true``", "Success, returns the decoded Slack response."
    "HTTP 429", "Exception; retryable; parses ``Retry-After``."
    "HTTP 5xx", "Exception; retryable; delivery may have occurred."
    "Other non-2xx responses", "Exception; generally non-retryable."
    "Request-level network exception", "Exception; retryable; delivery state unknown."
    "API JSON ``ok: false``", "Exception with a sanitized Slack error code; selected errors retryable."
    "Unexpected/malformed response", "Exception; not considered successful."

Network errors are intentionally not chained into public exceptions;
some HTTP client exception strings can contain a webhook URL, which is
itself a secret.

Catching failures in application code
=====================================

..  code-block:: php

    use Lex\Notifications\Slack\Exception\SlackTransportException;

    try {
        $manager->route('slack', 'webhook:monitoring')
            ->notify(new DeploymentCompleted());
    } catch (SlackTransportException $exception) {
        // Log a safe operational category; never log webhook URLs or tokens.
        $retryable = $exception->retryable;
        $retryAfter = $exception->retryAfterSeconds;
    }

For deferred delivery, let the Messenger/core notification worker handle
exceptions according to its configured failure and retry policy. Reading
``retryAfterSeconds`` does not itself schedule a retry. A failed send
may have reached Slack before a network error was observed.
