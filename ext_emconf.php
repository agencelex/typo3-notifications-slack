<?php declare(strict_types=1);

/**
 * The ext_emconf.php is used in legacy installations not based on Composer to supply information about an extension in
 * the Admin Tools > Extensions module. In these installations the ordering of installed extensions and their dependencies
 * are loaded from this file as well.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Laravel-style Notification System - Slack Channel',
    'description' => 'Slack channel provider for the Laravel-style Notification System (lex_notifications). Slack Block Kit, legacy messages, Incoming Webhooks and Slack Web API.',
    'category' => 'services',
    'version' => '1.0.0',
    'state' => 'stable',
    'author' => 'Agence Lex',
    'author_email' => 'contact@agencelex.com',
    'author_company' => 'Agence Lex',

    'autoload' => [
        'psr-4' => [
            'Lex\\Notifications\\Slack\\' => 'Classes',
        ],
    ],

    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'lex_notifications' => '1.5.0-1.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];