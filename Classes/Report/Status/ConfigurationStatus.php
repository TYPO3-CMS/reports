<?php
namespace TYPO3\CMS\Reports\Report\Status;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2011 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * Performs some checks about the install tool protection status
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackag reports
 */
class ConfigurationStatus implements \TYPO3\CMS\Reports\StatusProviderInterface {

	/**
	 * 10MB
	 *
	 * @var integer
	 */
	protected $deprecationLogFileSizeWarningThreshold = 10485760;

	/**
	 * 100MB
	 *
	 * @var integer
	 */
	protected $deprecationLogFileSizeErrorThreshold = 104857600;

	/**
	 * Backpath to the typo3 main directory
	 *
	 * @var string
	 */
	protected $backPath = '../';

	/**
	 * Determines the Install Tool's status, mainly concerning its protection.
	 *
	 * @return array List of statuses
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$this->executeAdminCommand();
		$statuses = array(
			'emptyReferenceIndex' => $this->getReferenceIndexStatus(),
			'deprecationLog' => $this->getDeprecationLogStatus()
		);
		// Do not show status about non-existent features
		if (version_compare(phpversion(), '5.4', '<')) {
			$statuses['safeModeEnabled'] = $this->getPhpSafeModeStatus();
			$statuses['magicQuotesGpcEnabled'] = $this->getPhpMagicQuotesGpcStatus();
		}
		if ($this->isMemcachedUsed()) {
			$statuses['memcachedConnection'] = $this->getMemcachedConnectionStatus();
		}
		if (TYPO3_OS !== 'WIN') {
			$statuses['createdFilesWorldWritable'] = $this->getCreatedFilesWorldWritableStatus();
			$statuses['createdDirectoriesWorldWritable'] = $this->getCreatedDirectoriesWorldWritableStatus();
		}
		return $statuses;
	}

	/**
	 * Checks if sys_refindex is empty.
	 *
	 * @return \TYPO3\CMS\Reports\Status An tx_reports_reports_status_Status object representing whether the reference index is empty or not
	 */
	protected function getReferenceIndexStatus() {
		$value = $GLOBALS['LANG']->getLL('status_ok');
		$message = '';
		$severity = \TYPO3\CMS\Reports\Status::OK;
		$count = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('*', 'sys_refindex');
		$registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
		$lastRefIndexUpdate = $registry->get('core', 'sys_refindex_lastUpdate');
		if (!$count && $lastRefIndexUpdate) {
			$value = $GLOBALS['LANG']->getLL('status_empty');
			$severity = \TYPO3\CMS\Reports\Status::WARNING;
			$url = 'sysext/lowlevel/dbint/index.php?&id=0&SET[function]=refindex';
			$message = sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xml:warning.backend_reference_index'), ('<a href="' . $url) . '">', '</a>', \t3lib_BeFunc::dateTime($lastRefIndexUpdate));
		}
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $GLOBALS['LANG']->getLL('status_referenceIndex'), $value, $message, $severity);
	}

	/**
	 * Checks if PHP safe_mode is enabled.
	 *
	 * @return \TYPO3\CMS\Reports\Status A tx_reports_reports_status_Status object representing whether the safe_mode is enabled or not
	 */
	protected function getPhpSafeModeStatus() {
		$value = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:disabled');
		$message = '';
		$severity = \TYPO3\CMS\Reports\Status::OK;
		if (\TYPO3\CMS\Core\Utility\PhpOptionsUtility::isSafeModeEnabled()) {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:enabled');
			$severity = \TYPO3\CMS\Reports\Status::WARNING;
			$message = $GLOBALS['LANG']->getLL('status_configuration_PhpSafeModeEnabled');
		}
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $GLOBALS['LANG']->getLL('status_PhpSafeMode'), $value, $message, $severity);
	}

	/**
	 * Checks if PHP magic_quotes_gpc is enabled.
	 *
	 * @return \TYPO3\CMS\Reports\Status A tx_reports_reports_status_Status object representing whether the magic_quote_gpc is enabled or not
	 */
	protected function getPhpMagicQuotesGpcStatus() {
		$value = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:disabled');
		$message = '';
		$severity = \TYPO3\CMS\Reports\Status::OK;
		if (\TYPO3\CMS\Core\Utility\PhpOptionsUtility::isMagicQuotesGpcEnabled()) {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:enabled');
			$severity = \TYPO3\CMS\Reports\Status::WARNING;
			$message = $GLOBALS['LANG']->getLL('status_configuration_PhpMagicQuotesGpcEnabled');
		}
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $GLOBALS['LANG']->getLL('status_PhpMagicQuotesGpc'), $value, $message, $severity);
	}

	/**
	 * Checks whether memcached is configured, if that's the case we asume it's also used.
	 *
	 * @return boolean TRUE if memcached is used, FALSE otherwise.
	 */
	protected function isMemcachedUsed() {
		$memcachedUsed = FALSE;
		$memcachedServers = $this->getConfiguredMemcachedServers();
		if (count($memcachedServers)) {
			$memcachedUsed = TRUE;
		}
		return $memcachedUsed;
	}

	/**
	 * Gets the configured memcached server connections.
	 *
	 * @return array An array of configured memcached server connections.
	 */
	protected function getConfiguredMemcachedServers() {
		$memcachedServers = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'] as $table => $conf) {
				if (is_array($conf)) {
					foreach ($conf as $key => $value) {
						if (!is_array($value) && $value === 'TYPO3\\CMS\\Core\\Cache\\Backend\\MemcachedBackend') {
							$memcachedServers = $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$table]['options']['servers'];
							break;
						}
					}
				}
			}
		}
		return $memcachedServers;
	}

	/**
	 * Checks whether TYPO3 can connect to the configured memcached servers.
	 *
	 * @return \TYPO3\CMS\Reports\Status An tx_reports_reports_status_Status object representing whether TYPO3 can connect to the configured memcached servers
	 */
	protected function getMemcachedConnectionStatus() {
		$value = $GLOBALS['LANG']->getLL('status_ok');
		$message = '';
		$severity = \TYPO3\CMS\Reports\Status::OK;
		$failedConnections = array();
		$defaultMemcachedPort = ini_get('memcache.default_port');
		$memcachedServers = $this->getConfiguredMemcachedServers();
		if (function_exists('memcache_connect') && is_array($memcachedServers)) {
			foreach ($memcachedServers as $testServer) {
				$configuredServer = $testServer;
				if (substr($testServer, 0, 7) == 'unix://') {
					$host = $testServer;
					$port = 0;
				} else {
					if (substr($testServer, 0, 6) === 'tcp://') {
						$testServer = substr($testServer, 6);
					}
					if (strstr($testServer, ':') !== FALSE) {
						list($host, $port) = explode(':', $testServer, 2);
					} else {
						$host = $testServer;
						$port = $defaultMemcachedPort;
					}
				}
				$memcachedConnection = @memcache_connect($host, $port);
				if ($memcachedConnection != NULL) {
					memcache_close($memcachedConnection);
				} else {
					$failedConnections[] = $configuredServer;
				}
			}
		}
		if (count($failedConnections)) {
			$value = $GLOBALS['LANG']->getLL('status_connectionFailed');
			$severity = \TYPO3\CMS\Reports\Status::WARNING;
			$message = ((($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xml:warning.memcache_not_usable') . '<br /><br />') . '<ul><li>') . implode('</li><li>', $failedConnections)) . '</li></ul>';
		}
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $GLOBALS['LANG']->getLL('status_memcachedConfiguration'), $value, $message, $severity);
	}

	/**
	 * Provides status information on the deprecation log, whether it's enabled
	 * and if so whether certain limits in file size are reached.
	 *
	 * @return \TYPO3\CMS\Reports\Status The deprecation log status.
	 */
	protected function getDeprecationLogStatus() {
		$title = $GLOBALS['LANG']->getLL('status_configuration_DeprecationLog');
		$value = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:disabled');
		$message = '';
		$severity = \TYPO3\CMS\Reports\Status::OK;
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enableDeprecationLog']) {
			$value = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:enabled');
			$message = ('<p>' . $GLOBALS['LANG']->getLL('status_configuration_DeprecationLogEnabled')) . '</p>';
			$severity = \TYPO3\CMS\Reports\Status::NOTICE;
			$logFile = \TYPO3\CMS\Core\Utility\GeneralUtility::getDeprecationLogFileName();
			$logFileSize = 0;
			if (@file_exists($logFile)) {
				$logFileSize = filesize($logFile);
				$message .= ('<p>' . sprintf($GLOBALS['LANG']->getLL('status_configuration_DeprecationLogFile'), $this->getDeprecationLogFileLink())) . '</p>';
				$removeDeprecationLogFileUrl = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL') . '&amp;adminCmd=removeDeprecationLogFile';
				$message .= ((((('<p>' . sprintf($GLOBALS['LANG']->getLL('status_configuration_DeprecationLogSize'), \TYPO3\CMS\Core\Utility\GeneralUtility::formatSize($logFileSize))) . ' <a href="') . $removeDeprecationLogFileUrl) . '">') . $GLOBALS['LANG']->getLL('status_configuration_DeprecationLogDeleteLink')) . '</a></p>';
			}
			if ($logFileSize > $this->deprecationLogFileSizeWarningThreshold) {
				$severity = \TYPO3\CMS\Reports\Status::WARNING;
			}
			if ($logFileSize > $this->deprecationLogFileSizeErrorThreshold) {
				$severity = \TYPO3\CMS\Reports\Status::ERROR;
			}
		}
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $title, $value, $message, $severity);
	}

	/**
	 * Warning, if fileCreateMask has write bit for 'others' set.
	 *
	 * @return \TYPO3\CMS\Reports\Status The writable status for 'others'
	 */
	protected function getCreatedFilesWorldWritableStatus() {
		$value = $GLOBALS['LANG']->getLL('status_ok');
		$message = '';
		$severity = \TYPO3\CMS\Reports\Status::OK;
		if ((int) $GLOBALS['TYPO3_CONF_VARS']['BE']['fileCreateMask'] % 10 & 2) {
			$value = $GLOBALS['TYPO3_CONF_VARS']['BE']['fileCreateMask'];
			$severity = \TYPO3\CMS\Reports\Status::WARNING;
			$message = $GLOBALS['LANG']->getLL('status_CreatedFilePermissions.writable');
		}
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $GLOBALS['LANG']->getLL('status_CreatedFilePermissions'), $value, $message, $severity);
	}

	/**
	 * Warning, if folderCreateMask has write bit for 'others' set.
	 *
	 * @return \TYPO3\CMS\Reports\Status The writable status for 'others'
	 */
	protected function getCreatedDirectoriesWorldWritableStatus() {
		$value = $GLOBALS['LANG']->getLL('status_ok');
		$message = '';
		$severity = \TYPO3\CMS\Reports\Status::OK;
		if ((int) $GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask'] % 10 & 2) {
			$value = $GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask'];
			$severity = \TYPO3\CMS\Reports\Status::WARNING;
			$message = $GLOBALS['LANG']->getLL('status_CreatedDirectoryPermissions.writable');
		}
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status', $GLOBALS['LANG']->getLL('status_CreatedDirectoryPermissions'), $value, $message, $severity);
	}

	/**
	 * Creates a link to the deprecation log file with the absolute path as the
	 * link text.
	 *
	 * @return string Link to the deprecation log file
	 */
	protected function getDeprecationLogFileLink() {
		$logFile = \TYPO3\CMS\Core\Utility\GeneralUtility::getDeprecationLogFileName();
		$relativePath = \TYPO3\CMS\Core\Utility\GeneralUtility::resolveBackPath($this->backPath . substr($logFile, strlen(PATH_site)));
		$link = ((('<a href="' . $relativePath) . '">') . $logFile) . '</a>';
		return $link;
	}

	/**
	 * Executes admin commands.
	 *
	 * Currently implemented commands are:
	 * - Remove deprecation log file
	 *
	 * @return void
	 */
	protected function executeAdminCommand() {
		$command = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET('adminCmd');
		switch ($command) {
		case 'removeDeprecationLogFile':
			self::removeDeprecationLogFile();
			break;
		default:
			// intentionally left blank
			break;
		}
	}

	/**
	 * Remove deprecation log file.
	 *
	 * @return void
	 */
	static protected function removeDeprecationLogFile() {
		if (@unlink(\TYPO3\CMS\Core\Utility\GeneralUtility::getDeprecationLogFileName())) {
			$message = $GLOBALS['LANG']->getLL('status_configuration_DeprecationLogDeletedSuccessful');
			$severity = \TYPO3\CMS\Core\Messaging\FlashMessage::OK;
		} else {
			$message = $GLOBALS['LANG']->getLL('status_configuration_DeprecationLogDeletionFailed');
			$severity = \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR;
		}
		\TYPO3\CMS\Core\Messaging\FlashMessageQueue::addMessage(\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, '', $severity, TRUE));
	}

}


?>