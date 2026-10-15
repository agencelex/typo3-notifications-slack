<?php declare(strict_types=1);

use Lex\Notifications\Slack\Extension;

// EXAMPLE ONLY; copy the relevant array into config/system/additional.php
// (this file is NOT loaded automatically by the extension).
// Use secret injection (environment variables, protected config or Key Vault).

$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Extension::KEY] = [
    'botToken' => getenv('SLACK_BOT_TOKEN') ?: '',
    'webhooks' => [
        'monitoring' => getenv('SLACK_WEBHOOK_MONITORING') ?: '',
        'security' => getenv('SLACK_WEBHOOK_SECURITY') ?: '',
    ],
    'apiEndpoint' => 'https://slack.com/api', // or https://slack-gov.com/api
    'timeout' => 10,
    'connectTimeout' => 5,
];