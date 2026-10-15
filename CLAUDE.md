# Claude Code — `notifications_slack`

TYPO3 extension `notifications_slack`, Composer `agencelex/notifications-slack`, namespace `Lex\Notifications\Slack\`, PHP >=8.2, TYPO3 ^13.4 || ^14.3, notifications core ^1.5.
User-facing docs: `README.md` and `Documentation/` (RST). Keep both in sync.

## Architecture (do not rediscover the whole repository)
- `SlackChannel` implements `Lex\Notifications\Channel\ChannelInterface`, name `slack`; registered with `notifications.channel` service tag.
- `SlackMessage`: immutable JSON payload; text, legacy attachments, Block Kit raw JSON/array, native `SlackPhp\BlockKit` objects. Do not reimplement Block Kit classes.
- `SlackRoute`: API conversation ID, direct HTTPS webhook, or `webhookNamed()` / `webhook:name`.
- `SlackConfiguration`: receives the extension configuration.
- `SlackTransport`: TYPO3 RequestFactory; no automatic retries, sanitized exceptions.
- `CanSendToSlack` / `HasRouteNotificationForSlack`: traits matching notifications and notifiables.
- **Core prechecks `routeNotificationFor('slack', ...)` before invoking channel**. Routes are mandatory. Do not modify core to bypass this.

## Security
- Prefer named webhooks; direct URLs are secrets and serialize into Messenger queue.
- Bot token never goes into serialized message/route. Use additional.php + env/secret store.
- Never log tokens, webhook URLs, unredacted request exceptions or response bodies.
- Allow Slack official HTTPS endpoints only; no redirects.
- Do not silently retry messages: uncertain delivery can cause duplicates.
- Never claim Messenger honors `Retry-After` without configuring a retry policy.

## Working style / token efficiency
1. Read this file first; search only related files. Avoid `vendor/`, `.Build/`, caches unless necessary.
2. Use filename searches, targeted grep, and focused diff; don't dump whole source files.
3. Keep routine replies short: changed files, tests, meaningful risks only.
4. Prefer targeted checks; run complete relevant test suite before a release.
5. Never skip security tests or fabricate successful checks to save tokens.
6. Do not modify unrelated extensions, make commits, push, or publish without request.
7. Composer scripts: `composer test:lint`, `composer test:unit` (requires Composer dependencies).
8. Keep README/config example consistent when altering the public API.

## Expected behavior
- `toSlack(object $notifiable): SlackMessage` on notification; `via()` returns `['slack']`.
- `routeNotificationForSlack(?Notification $notification = null)` on notifiable, or `NotificationManager::route('slack', ...)`.
- `SlackRoute::webhookNamed('monitoring')` stores only alias in queued message; actual URL resolved at send time.
- 429 yields `SlackTransportException` with `retryAfterSeconds` and `retryable=true`.
- Raw Block Kit Builder JSON bypasses library hydration to support new blocks; library OOP messages validated by vendor library.

## Tests

```bash
composer install
.Build/bin/phpunit -c Build/phpunit-unit.xml          # no TYPO3 instance
.Build/bin/phpunit -c Build/phpunit-functional.xml    # SQLite by default (memory_limit 1G set in xml)
composer test:php:acceptance                          # = rm -rf .Build/public/typo3temp/var/tests + phpunit -c Build/phpunit-acceptance.xml
composer update -W --with "typo3/cms-core:^13.4"      # or ^14.3 to switch TYPO3 major
```
DDEV: `ddev start && ddev composer install && ddev exec .Build/bin/phpunit -c Build/...`; MySQL via env
`typo3DatabaseDriver=mysqli typo3DatabaseHost=db typo3DatabaseUsername=root typo3DatabasePassword=root typo3DatabaseName=func_test`.

Layout:
- `Tests/Acceptance/`: end-to-end on a real TYPO3 instance, config loaded from `Configuration/ExampleAdditional.php` via putenv.
  `Fixtures/Http/FakeSlack` = Guzzle handler in `TYPO3_CONF_VARS['HTTP']['handler']` (records requests, queue responses).
  Use `overrideSlackConfiguration()` BEFORE the first `get()`/`dispatcher()` (services are instantiated lazily).
  exFAT: stale instances in `.Build/public/typo3temp/var/tests` can't be removed by the framework; delete them and `._*` files under `Tests/`.
- `Tests/Functional/SlackLiveTest.php`: REAL Slack, group `live-slack` (excluded in phpunit-functional.xml; run with `--group live-slack`).
  Env `SLACK_TEST_WEBHOOK_URL`, `SLACK_TEST_BOT_TOKEN`, `SLACK_TEST_CHANNEL_ID`, optional `SLACK_TEST_USER_ID`, `SLACK_TEST_API_ENDPOINT`.
  Skips before booting TYPO3 when no var is set (custom tearDown guards that). Never run it without the user asking: it posts messages.
- Conventions: PHPUnit attributes (`#[Test]`), `createStub()` when no expectations (PHPUnit 12 notices), `final` test classes.
  Supported: testing-framework ^8.2 || ^9, PHPUnit ^11.2 || ^12.1.

## Docs

Render: `docker run --rm -v <clean copy>:/project ghcr.io/typo3-documentation/render-guides:latest --config=Documentation --fail-on-log`.