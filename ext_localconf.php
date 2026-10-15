<?php declare(strict_types=1);

/**
 * ext_localconf.php is always included in global scope of the script, in the frontend, backend and CLI context.
 *
 * It should contain additional configuration of $GLOBALS['TYPO3_CONF_VARS'].
 *
 * This file contains hook definitions and plugin configuration. It must not contain a PHP encoding declaration.
 *
 * All ext_localconf.php files of loaded extensions are included right after the files config/system/settings.php and
 * config/system/additional.php during TYPO3 bootstrap[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/ExtLocalconf.html#:~:text=php%20during%20TYPO3-,bootstrap,-.].
 *
 * Pay attention to the rules for the contents of these files. For more details, see the section below[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/Configuration/Index.html#extension-configuration-files].
 *
 * Should Not Be Used For :
 * While you can put functions and classes into ext_localconf.php, it considered bad practice because such classes and
 * functions would always be loaded. Move such functionality to services or utility classes instead.
 * Registering hooks[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/Events/Concept/Index.html#hooks-concept],
 * XCLASSes[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/Xclasses/Index.html#xclasses] or any
 * simple array assignments to $GLOBALS['TYPO3_CONF_VARS'] options will not work for the following:
 * - class loader
 * - package manager
 * - cache manager
 * - configuration manager
 * - log manager (= Logging Framework[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/Logging/Index.html#logging])
 * - time zone
 * - memory limit
 * - locales
 * - stream wrapper
 * - error handler[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/ErrorAndExceptionHandling/Extending/Index.html#error-handling-extending]
 * - Icon registration. Icons should be registered in Icons.php[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/Configuration/Icons.html#extension-configuration-icons-php].
 *
 * This would not work because the extension files ext_localconf.php are included (loadTypo3LoadedExtAndExtLocalconf)
 * after the creation of the mentioned objects in the Bootstrap[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/RequestLifeCycle/Bootstrapping.html#bootstrapping] class.
 *
 * In most cases, these assignments should be placed in config/system/additional.php.
 *
 *
 * Should be used for :
 * These are the typical functions that extension authors should place within file:ext_localconf.php
 * - Registering hooks, XCLASSes or any simple array assignments to $GLOBALS['TYPO3_CONF_VARS'] options
 * - Registering additional Request Handlers within the Bootstrap[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/RequestLifeCycle/Bootstrapping.html#bootstrapping]
 * - Adding default TypoScript via \TYPO3\CMS\Core\Utility\ExtensionManagementUtility APIs
 * - Registering Scheduler Tasks
 * - Adding reports to the reports module
 * - Registering Services via the Service API[https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/Services/Developer/ServiceApi.html#services-developer-service-api]
 */

// Prevent Script from being called directly
use Lex\Notifications\Slack\Extension;

defined('TYPO3') or die();

// Encapsulate all locally defined variables
(function (string $extensionKey) {

})(Extension::KEY);