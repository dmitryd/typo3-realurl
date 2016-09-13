<?php
namespace DmitryDulepov\Realurl\Tests\Unit;

class RealurlTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	public function setUp() {
		parent::setUp();
	}

	/**
	 * @test
	 */
	public function testCase() {
		// write testcases here, which do not depend on a valid-typo3 system
		$this->assertEquals('1', '1');
	}
}