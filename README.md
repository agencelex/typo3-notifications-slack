# Laravel-style Notification System - Slack Channel

Slack channel provider for [lex_notifications](https://github.com/agencelex/typo3-notifications). Send notifications from TYPO3 to Slack using **Incoming Webhooks** or the **Slack Web API**. Compose **messages** using **plain text**, **legacy attachments**, **Block Kit** PHP objects, or **JSON** copied from Slack's Block Kit Builder.

TYPO3 13.4 / 14.3+, PHP 8.2+, `lex_notifications` 1.5+, and `slack-php/slack-block-kit` 2.3+.

Supports Slack `chat.postMessage` (Bot Token), HTTPS Incoming Webhooks, GovSlack, plain text / mrkdwn, legacy attachments, native PHP Block Kit objects, **raw JSON pasted from Block Kit Builder**.

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | `^8.2` |
| TYPO3 | `^13.4 \|\| ^14.3` |
| agencelex/notifications | `^1.5` |
| slack-php/slack-block-kit | `^2.3` |

---

## Install

```bash
composer require agencelex/notifications-slack:^1.0
```

Activate the extension in the TYPO3 Extension Manager or via `composer` (Composer-mode installations activate it automatically).

## Configuration

Place the following configuration in your **TYPO3 installation** at `config/system/additional.php` (not in the extension itself). `Configuration/ExampleAdditional.php` is a non-loaded example:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['notifications_slack'] = [
    'botToken' => getenv('SLACK_BOT_TOKEN') ?: '',
    'webhooks' => [
        'monitoring' => getenv('SLACK_WEBHOOK_MONITORING') ?: '',
    ],
    'apiEndpoint' => 'https://slack.com/api', // GovSlack: https://slack-gov.com/api
    'timeout' => 10,
    'connectTimeout' => 5,
];
```

Only put secrets into environment variables (or a protected secret provider). The example loads them into `$GLOBALS` at bootstrap. Treat configuration/debug dumps as sensitive. The service reads the configuration when sending. Named webhooks are strongly recommended for queued notifications.

### Slack setup

- **Incoming Webhook:** Enable Incoming Webhooks in Slack app configuration and obtain a URL resembling `https://hooks.slack.com/services/T…/B…/…`. Each URL posts to the configured conversation.
- **Web API:** Install the app with `chat:write`, invite the bot to the target channel if necessary, store the `xoxb-…` token securely and use a channel ID (e.g. `C0123456789`). GovSlack requires appropriate endpoints and app setup.
- Never commit tokens or URLs. Rotate a token/webhook if exposed.

## Send via `NotificationManager` (on demand)

```php
use Lex\Notifications\Slack\Extension;
use Lex\Notifications\Notification;
use Lex\Notifications\NotificationManager;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Message\SlackMessage;

final class JobFinished extends Notification
{
    public function via(object $notifiable): array { return [Extension::CHANNEL_NAME]; }

    public function toSlack(object $notifiable): SlackMessage
    {
        return SlackMessage::text(':white_check_mark: *Job finished*');
    }
}

// Inject NotificationDispatcherInterface using TYPO3 DI:
$dispatcher->route(Extension::CHANNEL_NAME, SlackRoute::webhookNamed('monitoring'))->notify(new JobFinished());
$dispatcher->route(Extension::CHANNEL_NAME, SlackRoute::api('C0123456789'))->notify(new JobFinished());
// shorthand:
$dispatcher->route(Extension::CHANNEL_NAME, 'https://hooks.slack.com/services/...')->notify(new JobFinished());
$dispatcher->route(Extension::CHANNEL_NAME, 'webhook:monitoring')->notify(new JobFinished());
```

`NotificationManager::route()` checks channel registration before dispatching. Do not define a message-only destination: the notification core checks the notifiable route **before** building Slack content.

## Send from a Notifiable model

```php
use Lex\Notifications\Domain\Model\Ability\Notifiable;
use Lex\Notifications\Notification;
use Lex\Notifications\Slack\Configuration\SlackRoute;
use Lex\Notifications\Slack\Domain\Model\Ability\HasRouteNotificationForSlack;

final class MyRecipient
{
    use Notifiable, HasRouteNotificationForSlack;

    public function routeNotificationForSlack(?Notification $notification = null): SlackRoute|string|null
    {
        return SlackRoute::webhookNamed('monitoring'); // Or any logic to determine the route
    }
}

(new MyRecipient())->notify(new JobFinished());
```

## Message examples

### Block Kit Builder JSON (without building classes)

Generate your message using the [Block Kit Builder](https://api.slack.com/block-kit-builder), then copy the JSON and use it in your code.

```php
$builderJson = <<<'JSON'
{"blocks":[{"type":"section","text":{"type":"mrkdwn","text":"*TYPO3 alert*"}}]}
JSON;

$message = SlackMessage::fromJson($builderJson)->withText('TYPO3 alert');
// Bare JSON arrays of blocks are supported as well.
```

### Native Block Kit library

```php
use SlackPhp\BlockKit\Kit;

$message = SlackMessage::fromBlockKit(
    Kit::message(blocks: [Kit::section('*TYPO3 alert*'), Kit::divider()])
)->withText('TYPO3 alert');
```

### Legacy attachments

```php
$message = SlackMessage::text('Alert')->withAttachments([
    ['fallback' => 'Production issue', 'color' => '#D92D20', 'title' => 'Alert', 'text' => 'Investigate TYPO3'],
]);
```

### Thread

```php
$message = SlackMessage::text('Update')->inThread('1729000000.123456')->withoutUnfurling();
```

**Accessibility:** Include `withText()` as readable fallback when sending Block Kit. This also improves push notification previews.

## Security & errors

- `SlackRoute::webhookNamed('monitoring')` resolves the URL at delivery time; queued objects contain only its alias. Direct `SlackRoute::webhook($url)` is supported but **serializes a secret** into queued messages.
- API tokens are resolved at send time.
- HTTP requests disable redirects, use 5 s connection/10 s total timeouts by default, and do not expose raw HTTP exception details. Webhook responses must contain `ok`; API responses must contain JSON `ok: true`.
- `SlackTransportException` exposes `httpStatus`, `retryAfterSeconds`, and `retryable`. The extension does NOT itself implement automatic retries, and Symfony Messenger does not automatically use `retryAfterSeconds`. Set up transport retry policy separately. Unknown delivery can duplicate messages on retry.
- Transport `send()` returns Slack API acknowledgement; notification channel interface returns `void` per the core contract.

## Testing

Install Composer dependencies before running tests:

```bash
composer install
composer test:lint
composer test:unit
composer test:php:acceptance
```

### Acceptance tests

`Tests/Acceptance/` documents, as executable examples, every way to notify via Slack. Each test boots a real TYPO3 instance (SQLite) with `lex_notifications` and this extension, configured exactly like `Configuration/ExampleAdditional.php` (secrets read from environment variables). Only the network hop is replaced: a Guzzle handler registered in `$GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']` records the request and answers like Slack, so no message ever leaves the machine.

| Test class | Covers |
|---|---|
| `DestinationsTest` | Named webhooks (`SlackRoute::webhookNamed()`, `webhook:name`), direct webhook URLs, Web API IDs (`C…`, `G…`, `U…`, `D…`, `W…`), GovSlack, timeouts, no redirects |
| `EntryPointsTest` | `notify()`, `notifyNow()` (with channel override), dispatcher `send()` / `sendNow()`, `route()`, `routes()`, `AnonymousNotifiable`, `channel('slack')->send()`, skipped recipients |
| `MessageFormatsTest` | Text, Block Kit Builder JSON, bare block arrays, unknown block types, native `Kit` messages, arrays, legacy attachments, threads, broadcast, unfurling |
| `QueuedDeliveryTest` | `ShouldQueue` through Messenger, serialized payload contains no token / named-webhook URL, URL resolved when consumed |
| `FailureHandlingTest` | 429 + `Retry-After`, 5xx, 4xx, redirects, connection errors, Web API errors, misconfiguration, invalid routes, no secret in exceptions, no retry |

The script clears `.Build/public/typo3temp/var/tests` first: on exFAT volumes the testing framework cannot remove previous test instances.

### Live tests against a real Slack workspace

`Tests/Functional/SlackLiveTest.php` posts **real** messages (webhook, named webhook, bot message to a channel, Block Kit, thread reply and broadcast, DM to a user, legacy attachment) and checks real error answers (`channel_not_found`, `invalid_auth`). It belongs to the `live-slack` group, which `Build/phpunit-functional.xml` excludes, so it only runs when requested. Each test is skipped when its variables are missing.

```bash
export SLACK_TEST_WEBHOOK_URL='https://hooks.slack.com/services/…'  # Incoming Webhook
export SLACK_TEST_BOT_TOKEN='xoxb-…'                                # app with chat:write
export SLACK_TEST_CHANNEL_ID='C…'                                   # the bot must be a member
export SLACK_TEST_USER_ID='U…'                                      # optional: DM from the bot
# export SLACK_TEST_API_ENDPOINT='https://slack-gov.com/api'        # optional: GovSlack
.Build/bin/phpunit -c Build/phpunit-functional.xml --group live-slack
```

Use a dedicated test channel and app. Slack limits `chat.postMessage` to about one message per second per channel; the tests never retry.

PHPUnit uses `Tests/bootstrap.php`. Unit tests mock TYPO3 RequestFactory and do not make Slack API calls. Run integration tests on **both TYPO3 13.4 and TYPO3 14.3** before deploying. PHP 8.2/8.3/8.4 support must be checked against the exact installed CMS and PHPUnit versions.

## Development

See `CLAUDE.md` for short Claude Code project rules. Files and conventions mirror `lex_notifications` 1.5.0 and `notifications_microsoft_teams` 1.1.0.
