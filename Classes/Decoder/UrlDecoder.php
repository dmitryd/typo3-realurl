<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Dmitry Dulepov (dmitry.dulepov@gmail.com)
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
namespace DmitryDulepov\Realurl\Decoder;

use DmitryDulepov\Realurl\Configuration\ConfigurationReader;

/**
 * This class contains URL decoder for the RealURL.
 *
 * @package DmitryDulepov\Realurl\Decoder
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class UrlDecoder {

	/** @var \DmitryDulepov\Realurl\Configuration\ConfigurationReader */
	protected $configuration;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->configuration = ConfigurationReader::getInstance();
	}

	/**
	 * Decodes the URL. This function is called from \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::checkAlternativeIdMethods()
	 *
	 * @param array $parameters
	 * @return void
	 */
	public function decodeUrl(array $parameters) {
		// Nothing here yet
	}
}
