<?php
namespace TYPO3\CMS\Reports\ViewHelpers;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2013 Georg Ringer <typo3@ringerge.org>
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
 * Render an item of the menu
 *
 */
class ActionMenuItemViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper {

	/**
	 * @var string
	 */
	protected $tagName = 'option';

	/**
	 * Renders an ActionMenu option tag
	 *
	 * @param string $label label of the option tag
	 * @param string $controller controller to be associated with this ActionMenuItem
	 * @param string $action the action to be associated with this ActionMenuItem
	 * @param array $arguments additional controller arguments to be passed to the action when this ActionMenuItem is selected
	 * @return string the rendered option tag
	 * @see Tx_Fluid_ViewHelpers_Be_Menus_ActionMenuViewHelper
	 */
	public function render($label, $controller, $action, array $arguments = array()) {
		$uriBuilder = $this->controllerContext->getUriBuilder();
		$uri = $uriBuilder->reset()->uriFor($action, $arguments, $controller);
		$this->tag->addAttribute('value', $uri);
		$currentRequest = $this->controllerContext->getRequest();
		$currentController = $currentRequest->getControllerName();
		$currentAction = $currentRequest->getControllerActionName();
		$currentArguments = $currentRequest->getArguments();
		unset($currentArguments['action']);
		unset($currentArguments['controller']);
		unset($currentArguments['redirect']);

		if ($action === $currentAction && $controller === $currentController && $currentArguments === $arguments) {
			$this->tag->addAttribute('selected', 'selected');
		}
		$this->tag->setContent($label);
		return $this->tag->render();
	}

}


?>