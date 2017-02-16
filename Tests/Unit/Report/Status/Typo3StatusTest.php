<?php
namespace TYPO3\CMS\Reports\Tests\Unit\Report\Status;

/*
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

/**
 * Test case
 */
class Typo3StatusTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{
    /**
     * Set up
     */
    protected function setUp()
    {
        $GLOBALS['LANG'] = $this->createMock(\TYPO3\CMS\Lang\LanguageService::class);
    }

    /**
     * @test
     */
    public function getStatusReturnsXclassStatusObjectWithSeverityOkIfNoXclassExists()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'] = [];
        $fixture = new \TYPO3\CMS\Reports\Report\Status\Typo3Status;
        $result = $fixture->getStatus();
        $statusObject = $result['registeredXclass'];
        $this->assertSame(\TYPO3\CMS\Reports\Status::OK, $statusObject->getSeverity());
    }

    /**
     * @test
     */
    public function getStatusReturnsXclassStatusObjectWithSeverityNoticeIfXclassExists()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'] = [
            'foo' => [
                'className' => 'bar',
            ]
        ];
        $fixture = new \TYPO3\CMS\Reports\Report\Status\Typo3Status;
        $result = $fixture->getStatus();
        $statusObject = $result['registeredXclass'];
        $this->assertSame(\TYPO3\CMS\Reports\Status::NOTICE, $statusObject->getSeverity());
    }
}
