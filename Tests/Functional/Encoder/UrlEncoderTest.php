<?php

namespace DmitryDulepov\Realurl\Tests\Functional\Encoder;

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
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

use DmitryDulepov\Realurl\Configuration\ConfigurationReader;
use DmitryDulepov\Realurl\Utility as RealurlUtility;
use DmitryDulepov\Realurl\Encoder\UrlEncoder;

/**
 * Testcase for class \DmitryDulepov\Realurl\Encoder\UrlEncoder
 */
class UrlEncoderTest extends \TYPO3\CMS\Core\Tests\FunctionalTestCase {

	/**
	 * Set up tests
	 */
	protected function setUp() {
		$this->testExtensionsToLoad[] = 'typo3conf/ext/realurl/';

		parent::setUp();

		$GLOBALS['LANG'] = GeneralUtility::makeInstance(\TYPO3\CMS\Lang\LanguageService::class);
		$GLOBALS['LANG']->init('default');

		$this->importDataSet(__DIR__ . '/../../Fixtures/realurl.xml');

		$GLOBALS['TSFE'] = $this->getMock(TypoScriptFrontendController::class, array(), array(), '', false);
		$GLOBALS['TSFE']->config = ['config' => ['tx_realurl_enable' => 1]];
		$GLOBALS['TSFE']->id = 1;

		$_SERVER['HTTP_HOST'] = 'www.example.com';

		$GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl'] = serialize([
			'configFile' => 'typo3conf/realurl_conf.php',
			'enableAutoConf' => 1,
			'autoConfFormat' => 0,
			'enableDevLog' => 0,
		]);
	}

	protected function getParametersForPage($pageUid) {
		// only totalURL is needed for tests
		return [
			'LD' => [
				'totalURL' => 'index.php?id=' . $pageUid,
			],
			'args' => [], // ignored
			'typeNum' => null,
		];
	}

	/**
	 * Tests encoding the home URL
	 *
	 * @test
	 */
	public function testEncodeHomeUrl() {
		$parameters = $this->getParametersForPage(1);

		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertNotNull($encoder->getConfiguration(), 'encoder configuration is null');
		$this->assertEquals($encoder->getConfiguration()->get('init/emptyUrlReturnValue') ?: '/', $parameters['LD']['totalURL']);
	}

	/**
	 * Tests encoding of a normal pages
	 *
	 * @test
	 */
	public function testEncodeNormalPages() {
		// Normal page without child-pages
		$parameters = $this->getParametersForPage(3);
		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertEquals('page3/', $parameters['LD']['totalURL'], 'Normal page without child-pages');

		// Normal page with child-pages
		$parameters = $this->getParametersForPage(2);
		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertEquals('page2/', $parameters['LD']['totalURL'], 'Normal page with child-pages');
	}

	/**
	 * Tests encoding of a normal child-page
	 *
	 * @test
	 */
	public function testEncodeNormalChildPages() {
		// Normal Child
		$parameters = $this->getParametersForPage(8);
		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertEquals('page2/subpage8/', $parameters['LD']['totalURL'], 'Normal Child');

		// Child with tx_realurl_pathoverride
		$parameters = $this->getParametersForPage(5);
		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertEquals('page5-override/', $parameters['LD']['totalURL'], 'Child with tx_realurl_pathoverride');

		// Child with alias
		$parameters = $this->getParametersForPage(6);
		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertEquals('page2/page6-alias/', $parameters['LD']['totalURL'], 'Child with alias');
	}

	/**
	 * Tests encoding of a page with a parent with tx_realurl_exclude=1
	 *
	 * @test
	 */
	public function testEncodeExcludeFromUrl() {
		// Page with tx_realurl_exclude=1 should appear in url, if it is the last segment
		$parameters = $this->getParametersForPage(4);
		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertEquals('page4/', $parameters['LD']['totalURL'], 'Page with tx_realurl_exclude=1');

		// Child with a parent with tx_realurl_exclude=1
		$parameters = $this->getParametersForPage(7);
		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertEquals('subpage7-without-parent/', $parameters['LD']['totalURL'], 'Child with a parent with tx_realurl_exclude=1');
	}

	/**
	 * Tests encoding of a page with "0" as title
	 *
	 * @test
	 * @see https://github.com/dmitryd/typo3-realurl/issues/207
	 */
	public function testEncodePageTitle0() {
		// Page with tx_realurl_exclude=1 should appear in url, if it is the last segment
		$parameters = $this->getParametersForPage(9);
		$encoder = GeneralUtility::makeInstance(UrlEncoder::class);
		$encoder->encodeUrl($parameters);
		$this->assertEquals('page2/0/', $parameters['LD']['totalURL'], 'Page with title="0" is not encoded correctly');
	}
}
