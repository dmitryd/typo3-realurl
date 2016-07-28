<?php

namespace DmitryDulepov\Realurl\Tests\Functional;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Dmitry Dulepov (dmitry.dulepov@gmail.com)
 *  (c) 2016 Robert Vock (robertvock82@gmail.com)
 *  All rights reserved
 *
 *  You may not remove or change the name of the author above. See:
 *  http://www.gnu.org/licenses/gpl-faq.html#IWantCredit
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

use DmitryDulepov\Realurl\Configuration\ConfigurationReader;
use DmitryDulepov\Realurl\Utility as RealurlUtility;

/**
 * Testcase for class \DmitryDulepov\Realurl\Utility
 */
class UtilityTest extends \TYPO3\CMS\Core\Tests\FunctionalTestCase {

	/** @var \DmitryDulepov\Realurl\Configuration\ConfigurationReader */
	protected $configuration;

	/** @var \DmitryDulepov\Realurl\Utility */
	protected $utility;

	/**
	 * Set up tests
	 */
	protected function setUp() {
		$this->testExtensionsToLoad[] = 'typo3conf/ext/realurl/';

		parent::setUp();

		$GLOBALS['LANG'] = GeneralUtility::makeInstance(\TYPO3\CMS\Lang\LanguageService::class);
		$GLOBALS['LANG']->init('default');

		$this->configuration = GeneralUtility::makeInstance(ConfigurationReader::class, ConfigurationReader::MODE_DECODE);
		$this->utility = GeneralUtility::makeInstance(RealurlUtility::class, $this->configuration);
	}

	/**
	 * Tests conversion of strings to safe url (without any special chars or whitespace)
	 *
	 * @test
	 */
	public function convertToSafeString() {
		// umlauts should be converted
		$this->assertEquals('umlauts-ae-ss-oe', $this->utility->convertToSafeString('umlauts ä ß Ö'), 'Umlauts should be converted');

		// no special chars should appear in the final string (some will be replaced with more readable output)
		$this->assertEquals('special-chars-eur-r-o-oe-p-aa-f-c-a-o-yen-c-u', $this->utility->convertToSafeString('special-chars-«-∑-€-®-†-Ω-¨-⁄-ø-π-å-‚-∂-ƒ-©-ª-º-∆-@-¥-≈-ç-√-∫-~-µ-∞-…-–'));
	}

	/**
	 * Test if whitespace is trimmed and spaces are treated the same as tabs and nbsp
	 *
	 * @test
	 * @see https://github.com/dmitryd/typo3-realurl/issues/218
	 */
	public function convertToSafeStringWithWhitespace() {
		// the string should be trimmed
		$this->assertEquals('trim', $this->utility->convertToSafeString("  trim  "));

		// the next line contains a non-breaking-sapce (\x20)
		$this->assertEquals('non-breaking-space-split', $this->utility->convertToSafeString("non-breaking-space split"), 'Non-breaking-spaces should be treated as whitespace');

		// tabs should be treated the same as white-space
		$this->assertEquals('tab-split', $this->utility->convertToSafeString("tab\tsplit"), 'tabs should be treated the same as white-space');
	}
}
