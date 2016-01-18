<?php
namespace DmitryDulepov\Realurl\Domain\Model;
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

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Alias extends AbstractEntity {

	/** @var int */
	protected $expire;

	/** @var string */
	protected $fieldAlias;

	/** @var string */
	protected $fieldId;

	/** @var int */
	protected $lang;

	/** @var string */
	protected $tablename;

	/** @var string */
	protected $valueAlias;

	/** @var int */
	protected $valueId;

	/**
	 * @return int
	 */
	public function getExpire() {
		return $this->expire;
	}

	/**
	 * @param int $expire
	 */
	public function setExpire($expire) {
		$this->expire = $expire;
	}

	/**
	 * @return string
	 */
	public function getFieldAlias() {
		return $this->fieldAlias;
	}

	/**
	 * @param string $fieldAlias
	 */
	public function setFieldAlias($fieldAlias) {
		$this->fieldAlias = $fieldAlias;
	}

	/**
	 * @return string
	 */
	public function getFieldId() {
		return $this->fieldId;
	}

	/**
	 * @param string $fieldId
	 */
	public function setFieldId($fieldId) {
		$this->fieldId = $fieldId;
	}

	/**
	 * @return int
	 */
	public function getLang() {
		return $this->lang;
	}

	/**
	 * @param int $lang
	 */
	public function setLang($lang) {
		$this->lang = $lang;
	}

	/**
	 * @return string
	 */
	public function getTablename() {
		return $this->tablename;
	}

	/**
	 * @param string $tablename
	 */
	public function setTablename($tablename) {
		$this->tablename = $tablename;
	}

	/**
	 * @return string
	 */
	public function getValueAlias() {
		return $this->valueAlias;
	}

	/**
	 * @param string $valueAlias
	 */
	public function setValueAlias($valueAlias) {
		$this->valueAlias = $valueAlias;
	}

	/**
	 * @return int
	 */
	public function getValueId() {
		return $this->valueId;
	}

	/**
	 * @param int $valueId
	 */
	public function setValueId($valueId) {
		$this->valueId = $valueId;
	}

	/**
	 * @return string
	 */
	public function getRecordFieldValue() {
		/** @noinspection PhpUndefinedMethodInspection */
		$row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
			$this->fieldAlias, $this->tablename,
			$this->fieldId . '=' . (int)$this->valueId
		);

		return is_array($row) && isset($row[$this->fieldAlias]) ? $row[$this->fieldAlias] : '';
	}

}
