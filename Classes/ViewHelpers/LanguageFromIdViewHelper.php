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

class LanguageFromIdViewHelper extends AbstractViewHelper {

	static protected $languageCache = array();

	/**
	 * Obtains language title from its id.
	 *
	 * @param string $language
	 * @return string
	 */
	public function render($language) {
		if ($language == 0) {
			$result = $GLOBALS['LANG']->sL('LLL:EXT:realurl/Resources/Private/Language/locallang.xlf:viewhelper.languageFromId.defaultLanguage');
		}
		else {
			if (!isset(self::$languageCache[$language])) {
				/** @noinspection PhpUndefinedMethodInspection */
				$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'sys_language', 'uid=' . (int)$language);
				self::$languageCache[$language] = is_array($record) ? $record['title'] : '(' . $language . ')';
			}
			$result = self::$languageCache[$language];
		}

		return $result;
	}
}
