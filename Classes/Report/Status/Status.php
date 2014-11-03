<?php
namespace TYPO3\CMS\Reports\Report\Status;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Reports\ExtendedStatusProviderInterface;
use TYPO3\CMS\Reports\ReportInterface;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * The status report
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Status implements ReportInterface {

	/**
	 * @var StatusProviderInterface[][]
	 */
	protected $statusProviders = array();

	/**
	 * Constructor for class tx_reports_report_Status
	 */
	public function __construct() {
		$this->getStatusProviders();
		$GLOBALS['LANG']->includeLLFile('EXT:reports/reports/locallang.xlf');
	}

	/**
	 * Takes care of creating / rendering the status report
	 *
	 * @return string The status report as HTML
	 */
	public function getReport() {
		$content = '';
		$status = $this->getSystemStatus();
		$highestSeverity = $this->getHighestSeverity($status);
		// Updating the registry
		$registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
		$registry->set('tx_reports', 'status.highestSeverity', $highestSeverity);
		$content .= '<p class="lead">' . $GLOBALS['LANG']->getLL('status_report_explanation') . '</p>';
		return $content . $this->renderStatus($status);
	}

	/**
	 * Gets all registered status providers and creates instances of them.
	 *
	 * @return void
	 */
	protected function getStatusProviders() {
		foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers'] as $key => $statusProvidersList) {
			$this->statusProviders[$key] = array();
			foreach ($statusProvidersList as $statusProvider) {
				$statusProviderInstance = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($statusProvider);
				if ($statusProviderInstance instanceof StatusProviderInterface) {
					$this->statusProviders[$key][] = $statusProviderInstance;
				}
			}
		}
	}

	/**
	 * Runs through all status providers and returns all statuses collected.
	 *
	 * @return \TYPO3\CMS\Reports\Status[]
	 */
	public function getSystemStatus() {
		$status = array();
		foreach ($this->statusProviders as $statusProviderId => $statusProviderList) {
			$status[$statusProviderId] = array();
			foreach ($statusProviderList as $statusProvider) {
				$statuses = $statusProvider->getStatus();
				$status[$statusProviderId] = array_merge($status[$statusProviderId], $statuses);
			}
		}
		return $status;
	}

	/**
	 * Runs through all status providers and returns all statuses collected, which are detailed.
	 *
	 * @return \TYPO3\CMS\Reports\Status[]
	 */
	public function getDetailedSystemStatus() {
		$status = array();
		foreach ($this->statusProviders as $statusProviderId => $statusProviderList) {
			$status[$statusProviderId] = array();
			foreach ($statusProviderList as $statusProvider) {
				if ($statusProvider instanceof ExtendedStatusProviderInterface) {
					$statuses = $statusProvider->getDetailedStatus();
					$status[$statusProviderId] = array_merge($status[$statusProviderId], $statuses);
				}
			}
		}
		return $status;
	}

	/**
	 * Determines the highest severity from the given statuses.
	 *
	 * @param array $statusCollection An array of \TYPO3\CMS\Reports\Status objects.
	 * @return int The highest severity found from the statuses.
	 */
	public function getHighestSeverity(array $statusCollection) {
		$highestSeverity = \TYPO3\CMS\Reports\Status::NOTICE;
		foreach ($statusCollection as $statusProvider => $providerStatuses) {
			/** @var $status \TYPO3\CMS\Reports\Status */
			foreach ($providerStatuses as $status) {
				if ($status->getSeverity() > $highestSeverity) {
					$highestSeverity = $status->getSeverity();
				}
				// Reached the highest severity level, no need to go on
				if ($highestSeverity == \TYPO3\CMS\Reports\Status::ERROR) {
					break;
				}
			}
		}
		return $highestSeverity;
	}

	/**
	 * Renders the system's status
	 *
	 * @param array $statusCollection An array of statuses as returned by the available status providers
	 * @return string The system status as an HTML table
	 */
	protected function renderStatus(array $statusCollection) {
		// TODO refactor into separate methods, status list and single status
		$content = '';
		$template = '
		<table class="t3-table">
			<thead>
				<tr>
					<th colspan="2" class="default">###HEADER###</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class="###CLASS### col-sm-2">###STATUS###</td>
					<td class="###CLASS### col-sm-10">###CONTENT###</td>
				</tr>
			</tbody>
		</table>';
		$statuses = $this->sortStatusProviders($statusCollection);
		foreach ($statuses as $provider => $providerStatus) {
			$headerIcon = '';
			$providerState = $this->sortStatuses($providerStatus);
			$id = str_replace(' ', '-', $provider);
			$classes = array(
				\TYPO3\CMS\Reports\Status::NOTICE => 'notice',
				\TYPO3\CMS\Reports\Status::INFO => 'info',
				\TYPO3\CMS\Reports\Status::OK => 'success',
				\TYPO3\CMS\Reports\Status::WARNING => 'warning',
				\TYPO3\CMS\Reports\Status::ERROR => 'danger'
			);
			$icon[\TYPO3\CMS\Reports\Status::NOTICE] = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-notification');
			$icon[\TYPO3\CMS\Reports\Status::INFO] = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-information');
			$icon[\TYPO3\CMS\Reports\Status::OK] = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-ok');
			$icon[\TYPO3\CMS\Reports\Status::WARNING] = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-warning');
			$icon[\TYPO3\CMS\Reports\Status::ERROR] = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('status-dialog-error');
			$messages = '';
			$sectionSeverity = 0;
			/** @var $status \TYPO3\CMS\Reports\Status */
			foreach ($providerState as $status) {
				$severity = $status->getSeverity();
				$sectionSeverity = $severity > $sectionSeverity ? $severity : $sectionSeverity;
				$messages .= strtr($template, array(
					'###CLASS###' => $classes[$severity],
					'###HEADER###' => $status->getTitle(),
					'###STATUS###' => $status->getValue(),
					'###CONTENT###' => $status->getMessage()
				));
			}
			if ($sectionSeverity > 0) {
				$headerIcon = $icon[$sectionSeverity];
			}
			$content .= $GLOBALS['TBE_TEMPLATE']->collapseableSection($headerIcon . $provider, $messages, $id, 'reports.states');
		}
		return $content;
	}

	/**
	 * Sorts the status providers (alphabetically and puts primary status providers at the beginning)
	 *
	 * @param array $statusCollection A collection of statuses (with providers)
	 * @return array The collection of statuses sorted by provider (beginning with provider "_install")
	 */
	protected function sortStatusProviders(array $statusCollection) {
		// Extract the primary status collections, i.e. the status groups
		// that must appear on top of the status report
		// Change their keys to localized collection titles
		$primaryStatuses = array(
			$GLOBALS['LANG']->getLL('status_typo3') => $statusCollection['typo3'],
			$GLOBALS['LANG']->getLL('status_system') => $statusCollection['system'],
			$GLOBALS['LANG']->getLL('status_security') => $statusCollection['security'],
			$GLOBALS['LANG']->getLL('status_configuration') => $statusCollection['configuration']
		);
		unset($statusCollection['typo3'], $statusCollection['system'], $statusCollection['security'], $statusCollection['configuration']);
		// Assemble list of secondary status collections with left-over collections
		// Change their keys using localized labels if available
		// TODO extract into getLabel() method
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
			$providerLabel = empty($label) ? $statusProviderId : $label;
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
	 * @param array $statusCollection A collection of statuses per provider
	 * @return array The collection of statuses sorted by severity
	 */
	protected function sortStatuses(array $statusCollection) {
		$statuses = array();
		$sortTitle = array();
		$header = NULL;
		/** @var $status \TYPO3\CMS\Reports\Status */
		foreach ($statusCollection as $status) {
			if ($status->getTitle() === 'TYPO3') {
				$header = $status;
				continue;
			}
			$statuses[] = $status;
			$sortTitle[] = $status->getSeverity();
		}
		array_multisort($sortTitle, SORT_DESC, $statuses);
		// Making sure that the core version information is always on the top
		if (is_object($header)) {
			array_unshift($statuses, $header);
		}
		return $statuses;
	}

}
