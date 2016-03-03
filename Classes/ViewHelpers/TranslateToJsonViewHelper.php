<?php
namespace DmitryDulepov\Realurl\ViewHelpers;

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\TranslateViewHelper;

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

class TranslateToJsonViewHelper extends TranslateViewHelper {

	/**
	 * Renders the translation and encodes to json string.
	 *
	 * @param string $key Translation Key
	 * @param string $id Translation Key compatible to TYPO3 Flow
	 * @param string $default If the given locallang key could not be found, this value is used. If this argument is not set, child nodes will be used to render the default
	 * @param bool $htmlEscape TRUE if the result should be htmlescaped. This won't have an effect for the default value
	 * @param array $arguments Arguments to be replaced in the resulting string
	 * @param string $extensionName UpperCamelCased extension key (for example BlogExample)
	 * @return string The translated key or tag body if key doesn't exist
	 */
	public function render($key = null, $id = null, $default = null, $htmlEscape = null, array $arguments = null, $extensionName = null) {
		$result = (string)parent::render($key, $id, $default, $htmlEscape, $arguments, $extensionName);

		return json_encode($result);
	}
}
