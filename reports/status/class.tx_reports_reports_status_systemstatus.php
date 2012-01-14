<?php
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
 * Performs several checks about the system's health
 *
 * @author		Ingo Renner <ingo@typo3.org>
 * @package		TYPO3
 * @subpackage	reports
 */
class tx_reports_reports_status_SystemStatus implements tx_reports_StatusProvider {

		// PHP modules which are required. Can be changed by hook in getMissingPhpModules()
	protected $requiredPhpModules = array(
		'filter', 'gd', 'json', 'mysql', 'pcre', 'session', 'SPL', 'standard', 'openssl', 'xml', 'zlib', 'soap'
	);

	/**
	 * Determines the Install Tool's status, mainly concerning its protection.
	 *
	 * @return	array	List of statuses
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$this->executeAdminCommand();

		$statuses = array(
			'Php'                 => $this->getPhpStatus(),
			'PhpMemoryLimit'      => $this->getPhpMemoryLimitStatus(),
			'PhpPeakMemory'       => $this->getPhpPeakMemoryStatus(),
			'PhpRegisterGlobals'  => $this->getPhpRegisterGlobalsStatus(),
			'Webserver'           => $this->getWebserverStatus(),
			'PhpModules'          => $this->getMissingPhpModules(),
		);

		return $statuses;
	}


	/**
	 * Checks the current PHP version against a minimum required version.
	 *
	 * @return	tx_reports_reports_status_Status	A status of whether a minimum PHP version requirment is met
	 */
	protected function getPhpStatus() {
		$message  = '';
		$severity = tx_reports_reports_status_Status::OK;

		if (version_compare(phpversion(), TYPO3_REQUIREMENTS_MINIMUM_PHP) < 0) {
			$message  = $GLOBALS['LANG']->getLL('status_phpTooOld');
			$severity = tx_reports_reports_status_Status::ERROR;
		}

		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$GLOBALS['LANG']->getLL('status_phpVersion'),
			phpversion(),
			$message,
			$severity
		);
	}

	/**
	 * Checks the current memory limit against a minimum required version.
	 *
	 * @return	tx_reports_reports_status_Status	A status of whether a minimum memory limit requirment is met
	 */
	protected function getPhpMemoryLimitStatus() {
		$memoryLimit = ini_get('memory_limit');
		$memoryLimitBytes = t3lib_div::getBytesFromSizeMeasurement($memoryLimit);
		$message     = '';
		$severity    = tx_reports_reports_status_Status::OK;

		if ($memoryLimitBytes > 0) {
			if ($memoryLimitBytes < t3lib_div::getBytesFromSizeMeasurement(TYPO3_REQUIREMENTS_RECOMMENDED_PHP_MEMORY_LIMIT)) {
				$message = sprintf($GLOBALS['LANG']->getLL('status_phpMemoryRecommendation'), $memoryLimit, TYPO3_REQUIREMENTS_RECOMMENDED_PHP_MEMORY_LIMIT);
				$severity = tx_reports_reports_status_Status::WARNING;
			}

			if ($memoryLimitBytes < t3lib_div::getBytesFromSizeMeasurement(TYPO3_REQUIREMENTS_MINIMUM_PHP_MEMORY_LIMIT)) {
				$message = sprintf($GLOBALS['LANG']->getLL('status_phpMemoryRequirement'), $memoryLimit, TYPO3_REQUIREMENTS_MINIMUM_PHP_MEMORY_LIMIT);
				$severity = tx_reports_reports_status_Status::ERROR;
			}

			if ($severity > tx_reports_reports_status_Status::OK) {
				if ($php_ini_path = get_cfg_var('cfg_file_path')) {
					$message .= ' ' . sprintf($GLOBALS['LANG']->getLL('status_phpMemoryEditLimit'), $php_ini_path);
				} else {
					$message .= ' ' . $GLOBALS['LANG']->getLL('status_phpMemoryContactAdmin');
				}
			}
		}

		return t3lib_div::makeInstance(
			'tx_reports_reports_status_Status',
			$GLOBALS['LANG']->getLL('status_phpMemory'),
			($memoryLimitBytes > 0 ? $memoryLimit : $GLOBALS['LANG']->getLL('status_phpMemoryUnlimited')),
			$message,
			$severity
		);
	}

	/**
	 * Executes commands like clearing the memory status flag
	 *
	 * @return	void
	 */
	protected function executeAdminCommand() {
		$command = t3lib_div::_GET('adminCmd');

		switch ($command) {
			case 'clear_peak_memory_usage_flag':
				/** @var $registry t3lib_Registry */
				$registry = t3lib_div::makeInstance('t3lib_Registry');
				$registry->remove('core', 'reports-peakMemoryUsage');
				break;
		}
	}

	/**
	 * Checks if there was a request in the past which approached the memory limit
	 *
	 * @return tx_reports_reports_status_Status	A status of whether the memory limit was approached by one of the requests
	 */
	protected function getPhpPeakMemoryStatus() {
		/** @var $registry t3lib_Registry */
		$registry = t3lib_div::makeInstance('t3lib_Registry');
		$peakMemoryUsage = $registry->get('core', 'reports-peakMemoryUsage');
		$memoryLimit = t3lib_div::getBytesFromSizeMeasurement(ini_get('memory_limit'));
		$value = $GLOBALS['LANG']->getLL('status_ok');

		$message = '';
		$severity = tx_reports_reports_status_Status::OK;
		$bytesUsed = $peakMemoryUsage['used'];
		$percentageUsed = $memoryLimit ? number_format($bytesUsed / $memoryLimit * 100, 1) . '%' : '?';
		$dateOfPeak = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $peakMemoryUsage['tstamp']);
		$urlOfPeak = '<a href="' . htmlspecialchars($peakMemoryUsage['url']) . '">' . htmlspecialchars($peakMemoryUsage['url']) . '</a>';
		$clearFlagUrl = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL') . '&amp;adminCmd=clear_peak_memory_usage_flag';

		if ($peakMemoryUsage['used']) {
			$message = sprintf(
				$GLOBALS['LANG']->getLL('status_phpPeakMemoryTooHigh'),
				t3lib_div::formatSize($peakMemoryUsage['used']),
				$percentageUsed,
				t3lib_div::formatSize($memoryLimit),
				$dateOfPeak,
				$urlOfPeak
			);
			$message .= ' <a href="' . $clearFlagUrl . '">' . $GLOBALS['LANG']->getLL('status_phpPeakMemoryClearFlag') . '</a>.';
			$severity = tx_reports_reports_status_Status::WARNING;
			$value = $percentageUsed;
		}

		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$GLOBALS['LANG']->getLL('status_phpPeakMemory'), $value, $message, $severity
		);
	}

	/**
	 * checks whether register globals is on or off.
	 *
	 * @return	tx_reports_reports_status_Status	A status of whether register globals is on or off
	 */
	protected function getPhpRegisterGlobalsStatus() {
		$value    = $GLOBALS['LANG']->getLL('status_disabled');
		$message  = '';
		$severity = tx_reports_reports_status_Status::OK;

		$registerGlobals = trim(ini_get('register_globals'));

			// can't reliably check for 'on', therefore checking for the oposite 'off', '', or 0
		if (!empty($registerGlobals) && strtolower($registerGlobals) != 'off') {
			$registerGlobalsHighlight = '<em>register_globals</em>';
			$phpManualLink .= '<a href="http://php.net/configuration.changes">' . $GLOBALS['LANG']->getLL('status_phpRegisterGlobalsHowToChange') . '</a>';
			$message  = sprintf($GLOBALS['LANG']->getLL('status_phpRegisterGlobalsEnabled'), $registerGlobalsHighlight);
			$message .= ' ' . sprintf($GLOBALS['LANG']->getLL('status_phpRegisterGlobalsSecurity'), $registerGlobalsHighlight);
			$message .= ' ' . sprintf($GLOBALS['LANG']->getLL('status_phpRegisterGlobalsPHPManual'), $phpManualLink);
			$severity = tx_reports_reports_status_Status::ERROR;
			$value = $GLOBALS['LANG']->getLL('status_enabled')
				. ' (\'' . $registerGlobals . '\')';
		}

		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$GLOBALS['LANG']->getLL('status_phpRegisterGlobals'), $value, $message, $severity
		);
	}

	/**
	 * Reports the webserver TYPO3 is running on.
	 *
	 * @return	tx_reports_reports_status_Status	The server software as a status
	 */
	protected function getWebserverStatus() {
		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$GLOBALS['LANG']->getLL('status_webServer'),
			$_SERVER['SERVER_SOFTWARE']
		);
	}

	/**
	 * Reports whether any of the required PHP modules are missing
	 *
	 * @return tx_reports_reports_status_Status A status of missing PHP modules
	 */
	protected function getMissingPhpModules() {
			// Hook to adjust the required PHP modules
		$modules = $this->requiredPhpModules;
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install/mod/class.tx_install.php']['requiredPhpModules'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install/mod/class.tx_install.php']['requiredPhpModules'] as $classData) {
				$hookObject = t3lib_div::getUserObj($classData);
				$modules = $hookObject->setRequiredPhpModules($modules, $this);
			}
		}
		$missingPhpModules = array();
		foreach ($modules as $module) {
			if (is_array($module)) {
				$detectedSubmodules = FALSE;
				foreach ($module as $submodule) {
					if (extension_loaded($submodule)) {
						$detectedSubmodules = TRUE;
					}
				}
				if ($detectedSubmodules === FALSE) {
					$missingPhpModules[] = sprintf($GLOBALS['LANG']->getLL('status_phpModulesGroup'), '(' . implode(', ', $module) . ')');
				}
			} elseif (!extension_loaded($module)) {
				$missingPhpModules[] = $module;
			}
		}
		if (count($missingPhpModules) > 0) {
			$value = $GLOBALS['LANG']->getLL('status_phpModulesMissing');
			$message = sprintf($GLOBALS['LANG']->getLL('status_phpModulesList'), implode(', ', $missingPhpModules));
			$message .= ' ' . $GLOBALS['LANG']->getLL('status_phpModulesInfo');
			$severity = tx_reports_reports_status_Status::ERROR;
		} else {
			$value = $GLOBALS['LANG']->getLL('status_phpModulesPresent');
			$message = '';
			$severity = tx_reports_reports_status_Status::OK;
		}
		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			$GLOBALS['LANG']->getLL('status_phpModules'), $value, $message, $severity
		);
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/reports/reports/status/class.tx_reports_reports_status_systemstatus.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/reports/reports/status/class.tx_reports_reports_status_systemstatus.php']);
}

?>