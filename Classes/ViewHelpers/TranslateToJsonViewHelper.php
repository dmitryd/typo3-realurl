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

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

class TranslateToJsonViewHelper extends AbstractViewHelper  {

	/**
	 * @var boolean
	 */
	protected $escapeOutput = FALSE;

    /**
     * Initialize view helper arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('key', 'string', 'Translation Key', true);
    }

	/**
	 * Renders the translation and encodes to json string.
	 *
	 * @return string The translated key or tag body if key doesn't exist
	 */
	public function render() {
		$result = LocalizationUtility::translate($this->arguments['key'], 'realurl');

		return json_encode($result);
	}
}
