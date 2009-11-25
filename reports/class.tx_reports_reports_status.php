<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
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
 * The status report
 *
 * @author		Ingo Renner <ingo@typo3.org>
 * @package		TYPO3
 * @subpackage	reports
 *
 * $Id$
 */
class tx_reports_reports_Status implements tx_reports_Report {

	protected $statusProviders = array();

	/**
	 * constructor for class tx_reports_report_Status
	 */
	public function __construct() {
		$this->getStatusProviders();

		$GLOBALS['LANG']->includeLLFile('EXT:reports/reports/locallang.xml');
	}

	/**
	 * Takes care of creating / rendering the status report
	 *
	 * @return	string	The status report as HTML
	 */
	public function getReport() {
		$status  = array();
		$content = '';

		foreach ($this->statusProviders as $statusProviderId => $statusProvidersList) {
			$status[$statusProviderId] = array();
			foreach ($statusProvidersList as $statusProvider) {
				$statuses = $statusProvider->getStatus();
				$status[$statusProviderId] = array_merge($status[$statusProviderId], $statuses);
			}
		}

		$content .= '<p class="help">'
			. $GLOBALS['LANG']->getLL('status_report_explanation')
			. '</p>';

		return $content . $this->renderStatus($status);
	}

	/**
	 * Gets all registered status providers and creates instances of them.
	 *
	 * @return	void
	 */
	protected function getStatusProviders() {
		foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers'] as $key => $statusProvidersList) {
			$this->statusProviders[$key] = array();
			foreach ($statusProvidersList as $statusProvider) {
				$statusProviderInstance = t3lib_div::makeInstance($statusProvider);
				if ($statusProviderInstance instanceof tx_reports_StatusProvider) {
					$this->statusProviders[$key][] = $statusProviderInstance;
				}
			}
		}
	}

	/**
	 * Renders the system's status
	 *
	 * @param	array	An array of statuses as returned by the available status providers
	 * @return	string	The system status as an HTML table
	 */
	protected function renderStatus(array $statusCollection) {
		$content = '';
		$template = '
		<div class="typo3-message message-###CLASS###">
			<div class="header-container">
				<div class="message-header message-left">###HEADER###</div>
				<div class="message-header message-right">###STATUS###</div>
			</div>
			<div class="message-body">###CONTENT###</div>
		</div>';

		$statuses = $this->sortStatusProviders($statusCollection);

		foreach($statuses as $provider => $providerStatus) {
			$providerState = $this->sortStatuses($providerStatus);

			$id = str_replace(' ', '-', $provider);
			if (isset($GLOBALS['BE_USER']->uc['reports']['states'][$id]) && $GLOBALS['BE_USER']->uc['reports']['states'][$id]) {
				$collapsedStyle = 'style="display:none"';
				$collapsedClass = 'collapsed';
			} else {
				$collapsedStyle = '';
				$collapsedClass = 'expanded';
			}


			$classes = array(
				tx_reports_reports_status_Status::NOTICE  => 'notice',
				tx_reports_reports_status_Status::INFO    => 'information',
				tx_reports_reports_status_Status::OK      => 'ok',
				tx_reports_reports_status_Status::WARNING => 'warning',
				tx_reports_reports_status_Status::ERROR   => 'error',
			);

			$icon[tx_reports_reports_status_Status::WARNING] = '<img' . t3lib_iconWorks::skinImg($this->doc->backPath, 'gfx/warning.png', 'width="16" height="16"') . ' alt="" />';
			$icon[tx_reports_reports_status_Status::ERROR] = '<img' . t3lib_iconWorks::skinImg($this->doc->backPath, 'gfx/error.png', 'width="16" height="16"') . ' alt="" />';
			$messages = '';
			$headerIcon = '';
			$sectionSeverity = 0;

			foreach ($providerState as $status) {
				$severity = $status->getSeverity();
				$sectionSeverity = $severity > $sectionSeverity ? $severity : $sectionSeverity;
				$messages .= strtr($template, array(
					'###CLASS###'   => $classes[$severity],
					'###HEADER###'  => $status->getTitle(),
					'###STATUS###'  => $status->getValue(),
					'###CONTENT###' => $status->getMessage(),
				));
			}
			if ($sectionSeverity > 0) {
				$headerIcon = $icon[$sectionSeverity];
			}
			$content .= '<h2 id="' . $id . '" class="section-header ' . $collapsedClass . '">' . $headerIcon . $provider . '</h2>
				<div ' . $collapsedStyle . '>' . $messages . '</div>';
		}
		return $content;
	}

	/**
	 * Sorts the status providers (alphabetically and puts primary status providers at the beginning)
	 *
	 * @param   array   A collection of statuses (with providers)
	 * @return  array   The collection of statuses sorted by provider (beginning with provider "_install")
	 */
	protected function sortStatusProviders(array $statusCollection) {
			// Extract the primary status collections, i.e. the status groups
			// that must appear on top of the status report
			// Change their keys to localized collection titles
		$primaryStatuses = array(
			$GLOBALS['LANG']->getLL('status_typo3')         => $statusCollection['typo3'],
			$GLOBALS['LANG']->getLL('status_system')        => $statusCollection['system'],
			$GLOBALS['LANG']->getLL('status_security')      => $statusCollection['security'],
			$GLOBALS['LANG']->getLL('status_configuration') => $statusCollection['configuration']
		);
		unset(
			$statusCollection['typo3'],
			$statusCollection['system'],
			$statusCollection['security'],
			$statusCollection['configuration']
		);
			// Assemble list of secondary status collections with left-over collections
			// Change their keys using localized labels if available
		$secondaryStatuses = array();
		foreach ($statusCollection as $statusProviderId => $collection) {
			$label = '';
			if (strpos($statusProviderId, 'LLL:') === 0) {
					// Label provided by extension
				$label = $GLOBALS['LANG']->sL($statusProviderId);
			} else {
					// Generic label
				$label = $GLOBALS['LANG']->getLL('status_' . $statusProviderId);
			}
			$providerLabel = (empty($label)) ? $statusProviderId : $label;
			$secondaryStatuses[$providerLabel] = $collection;
		}
			// Sort the secondary status collections alphabetically
		ksort($secondaryStatuses);
		$orderedStatusCollection = array_merge($primaryStatuses, $secondaryStatuses);

		return $orderedStatusCollection;
	}

	/**
	 * Sorts the statuses by severity
	 *
	 * @param   array   A collection of statuses per provider
	 * @return  array   The collection of statuses sorted by severity
	 */
	protected function sortStatuses(array $statusCollection) {
		$statuses  = array();
		$sortTitle = array();

		foreach ($statusCollection as $status) {
			if ($status->getTitle() === 'TYPO3') {
				$header = $status;
				continue;
			}

			$statuses[] = $status;
			$sortTitle[] = $status->getSeverity();
		}
		array_multisort($sortTitle, SORT_DESC, $statuses);

			// making sure that the core version information is always on the top
		if (is_object($header)) {
			array_unshift($statuses, $header);
		}
		return $statuses;
	}

	/**
	 * saves the section toggle state in the backend user's uc
	 *
	 * @param	array		array of parameters from the AJAX interface, currently unused
	 * @param	TYPO3AJAX	object of type TYPO3AJAX
	 * @return	void
	 */

	public function saveCollapseState(array $params, TYPO3AJAX $ajaxObj) {
		$item = t3lib_div::_POST('item');
		$state = (bool)t3lib_div::_POST('state');

		$GLOBALS['BE_USER']->uc['reports']['states'][$item] = $state;
		$GLOBALS['BE_USER']->writeUC();
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/reports/reports/class.tx_reports_reports_status.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/reports/reports/class.tx_reports_reports_status.php']);
}

?>