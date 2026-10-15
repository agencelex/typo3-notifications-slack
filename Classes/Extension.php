<?php declare(strict_types=1);

/*
 * This file is part of the "tmf_distribution" for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Lex\Notifications\Slack;

/**
 * @internal
 */
final class Extension {
    public const KEY = 'notifications_slack';

    public const CHANNEL_NAME = 'slack';
    public const TRANSPORT_NAME_WEBHOOK = 'webhook';

    public const TRANSPORT_NAME_API = 'api';

    public static function extensionKeyCamelCase() : string
    {
        // return GeneralUtility::underscoredToUpperCamelCase(self::KEY);
        return join('', array_map('ucfirst', explode('_', Extension::KEY)));
    }

    public static function extensionKeyLowerCamelCase() : string
    {
        return lcfirst(Extension::extensionKeyCamelCase());
    }

    public static function extensionKeyLowerCase() : string
    {
        return strtolower(Extension::extensionKeyLowerCamelCase());
    }
}
