..  _slack-usage:

===========
Quick start
===========

This example creates a TYPO3 notification and sends it using an on-demand
Slack route. The core notification manager handles channel selection and
dispatch.

Create a notification
=====================

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace Vendor\Site\Notification;

    use Lex\Notifications\Notification;
    use Lex\Notifications\Slack\Domain\Model\Ability\CanSendToSlack;
    use Lex\Notifications\Slack\Message\SlackMessage;

    final class DeploymentCompleted extends Notification
    {
        use CanSendToSlack;

        public function via(object $notifiable): array
        {
            return ['slack'];
        }

        public function toSlack(object $notifiable): SlackMessage
        {
            return SlackMessage::text(
                ':white_check_mark: *TYPO3 deployment completed*'
            );
        }
    }

Send on demand
==============

Inject ``Lex\\Notifications\\NotificationManager`` into an application
service and choose a route:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace Vendor\Site\Service;

    use Lex\Notifications\NotificationManager;
    use Lex\Notifications\Slack\Configuration\SlackRoute;
    use Vendor\Site\Notification\DeploymentCompleted;

    final readonly class DeploymentReporter
    {
        public function __construct(
            private NotificationManager $notifications,
        ) {}

        public function report(): void
        {
            $this->notifications
                ->route('slack', SlackRoute::webhookNamed('monitoring'))
                ->notify(new DeploymentCompleted());
        }
    }

To send via the Web API instead, use:

..  code-block:: php

    $this->notifications
        ->route('slack', SlackRoute::api('C0123456789'))
        ->notify(new DeploymentCompleted());

The Bot Token is obtained from :ref:`TYPO3 configuration <slack-configuration>`;
do not supply it with the notification.

Send from a notifiable model
============================

A notifiable domain object can define its own Slack route:

..  code-block:: php

    <?php

    declare(strict_types=1);

    use Lex\Notifications\Domain\Model\Ability\Notifiable;
    use Lex\Notifications\Notification;
    use Lex\Notifications\Slack\Configuration\SlackRoute;
    use Lex\Notifications\Slack\Domain\Model\Ability\HasRouteNotificationForSlack;

    final class TeamRecipient
    {
        use Notifiable;
        use HasRouteNotificationForSlack;

        public function routeNotificationForSlack(
            ?Notification $notification = null
        ): SlackRoute|string|null {
            return SlackRoute::webhookNamed('monitoring');
        }
    }

    (new TeamRecipient())->notify(new DeploymentCompleted());

A ``null`` or empty-string Slack route causes the core to skip that
recipient. The ``via()`` method must include ``slack`` unless the caller
uses an explicit channel override supported by the core.

Next steps
==========

See :ref:`Routing <slack-routing>`,
:ref:`Message formats <slack-message-formats>`, and
:ref:`Queued notifications <slack-queued-notifications>`.
