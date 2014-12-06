<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Reports\Task\SystemStatusUpdateTask::class] = array(
	'extension' => $_EXTKEY,
	'title' => 'LLL:EXT:' . $_EXTKEY . '/reports/locallang.xlf:status_updateTaskTitle',
	'description' => 'LLL:EXT:' . $_EXTKEY . '/reports/locallang.xlf:status_updateTaskDescription',
	'additionalFields' => \TYPO3\CMS\Reports\Task\SystemStatusUpdateTaskNotificationEmailField::class
);

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['displayWarningMessages']['tx_reports_WarningMessagePostProcessor'] = \TYPO3\CMS\Reports\Report\Status\WarningMessagePostProcessor::class;
