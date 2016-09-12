<?php
namespace DmitryDulepov\Realurl\ViewHelpers;
/***************************************************************
*  Copyright notice
*
*  (c) 2016 Dmitry Dulepov <dmitry.dulepov@gmail.com>
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

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * This class provides a viewhelper to set the variable from the fluid template.
 * It is an exact copy of EXT:vhs'es <v:variable.set>. Thanks to Claus Due!
 *
 * @author Claus Due <claus@namelesscoder.net>
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class SetVariableViewHelper extends AbstractViewHelper {

	/**
	 * Set (override) the variable in $name.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function render($name, $value = NULL) {
		if ($value === NULL) {
			$value = $this->renderChildren();
		}
		if (FALSE === strpos($name, '.')) {
			if ($this->templateVariableContainer->exists($name) === TRUE) {
				$this->templateVariableContainer->remove($name);
			}
			$this->templateVariableContainer->add($name, $value);
		} elseif (1 == substr_count($name, '.')) {
			$parts = explode('.', $name);
			$objectName = array_shift($parts);
			$path = implode('.', $parts);
			if (FALSE === $this->templateVariableContainer->exists($objectName)) {
				return NULL;
			}
			$object = $this->templateVariableContainer->get($objectName);
			try {
				\TYPO3\CMS\Extbase\Reflection\ObjectAccess::setProperty($object, $path, $value);
				// Note: re-insert the variable to ensure unreferenced values like arrays also get updated
				$this->templateVariableContainer->remove($objectName);
				$this->templateVariableContainer->add($objectName, $object);
			} catch (\Exception $error) {
				return NULL;
			}
		}
		return NULL;
	}

}
