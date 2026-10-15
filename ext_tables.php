<?php declare(strict_types=1);

/**
 * ext_tables.php is not always included in the global scope of the frontend context.
 *
 * This file is only included when :
 * - a TYPO3 Backend or CLI request is happening
 * - or the TYPO3 Frontend is called and a valid Backend User is authenticated
 *
 * This file usually gets included later within the request and after TCA information is loaded, and a backend user is authenticated.
 * In many cases, the file ext_tables.php is no longer needed, since TCA definitions must be placed in files located at
 * Configuration/TCA/[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/Configuration/TCA/Index.html#extension-configuration-tca].
 *
 * Should Not Be Used For :
 * - TCA configurations for new tables. They should go in Configuration/TCA/sometable.php[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/Configuration/TCA/Index.html#extension-configuration-tca].
 * - TCA overrides of existing tables. They should go in Configuration/TCA/Overrides/somefile.php[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/Configuration/TCA/Index.html#extension-configuration-tca-overrides].
 * - calling ExtensionManagementUtility::addToInsertRecords() as this might break the frontend. They should go in Configuration/TCA/Overrides/somefile.php.
 * - calling ExtensionManagementUtility::addStaticFile() as this might break the frontend. They should go in Configuration/TCA/Overrides/sys_template.php
 * - Adding table options via ExtensionManagementUtility::allowTableOnStandardPages() Example[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/ExtTables.html#extension-configuration-files-allow-table-standard]
 *
 * Should Be Used For :
 * These are the typical functions that should be placed inside ext_tables.php
 * - Registering a scheduler tasks: Registering a scheduler task[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/ExtTables.html#extension-configuration-files-scheduler]
 * - Registration of custom page types[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/PageTypes/CreateNewPageType.html#page-types-example]
 * - Extending the Backend user settings[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/Configuration/UserSettingsConfiguration/Extending.html#user-settings-extending]
 */

use Lex\Notifications\Slack\Extension;

defined('TYPO3') or die();

// Encapsulate all locally defined variables
(function (string $extensionKey) {


})(Extension::KEY);