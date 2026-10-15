..  _slack-queued-notifications:

====================
Queued notifications
====================

The parent ``agencelex/notifications`` extension integrates with Symfony
Messenger. Slack uses that existing delivery mechanism and does **not**
register a separate queue transport.

Mark a notification for queued delivery
=======================================

..  code-block:: php

    <?php

    declare(strict_types=1);

    use Illuminate\Contracts\Queue\ShouldQueue;
    use Lex\Notifications\Notification;
    use Lex\Notifications\Slack\Message\SlackMessage;

    final class ImportFinished extends Notification implements ShouldQueue
    {
        public function via(object $notifiable): array
        {
            return ['slack'];
        }

        public function toSlack(object $notifiable): SlackMessage
        {
            return SlackMessage::text('Nightly import completed.');
        }
    }

    $manager->route('slack', 'webhook:monitoring')
        ->notify(new ImportFinished());

``ShouldQueue`` is provided by the notification core's queue contract
(depending on your installation, the resolved interface belongs to
``illuminate/contracts``). Use the version supplied by the core.

..  important::

    TYPO3 can route Messenger messages to a synchronous transport by
    default. Declaring ``ShouldQueue`` does **not** guarantee that
    messages will be processed in the background. Configure an asynchronous
    Messenger transport and workers through the notification core for
    truly deferred delivery.

Why named webhooks matter
=========================

The ``SlackRoute::webhookNamed('monitoring')`` value contains only an
alias. Its secret URL is looked up in TYPO3 configuration during the
send operation, including when a worker later processes the message.

By contrast, ``SlackRoute::webhook($url)`` contains the full secret URL;
serialization into an asynchronous queue may expose that URL to anyone
who can inspect the queue storage.

Retries and duplicates
======================

SlackTransport never automatically resends a message. It throws an
exception on HTTP and Slack application errors, with information about
whether an error appears retryable.

Slack rate limits may include ``Retry-After``. That value is exposed as
``SlackTransportException::retryAfterSeconds``, but the channel does
not configure Messenger retries or automatically enforce the delay.

A timeout or server failure leaves the actual delivery outcome
uncertain. Retrying could post a duplicate. Configure retries according
to your operational tolerance and monitor the failure transport/worker
logs of the notification core.

Next: :ref:`Error handling <slack-errors>` and
:ref:`Security <slack-security>`.
