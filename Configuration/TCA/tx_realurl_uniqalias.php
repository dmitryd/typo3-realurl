<?php
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

$GLOBALS['TCA']['tx_realurl_uniqalias'] = array(
	'ctrl' => array(
		'title' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_uniqalias',
		'label' => 'value_alias',
		'iconfile' => 'EXT:realurl/Resources/Public/Icons/Extension.svg',
		'hideTable' => 1,
		'rootLevel' => 1,
	),
	'columns' => array(
		'expire' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_uniqalias.expire',
			'config' => array(
				'type' => 'input',
				'renderType' => 'inputDateTime',
				'eval' => 'datetime',
				'default' => 0,
			)
		),
		'lang' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_uniqalias.lang',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
				'default' => 0,
			)
		),
		'tablename' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_uniqalias.tablename',
			'config' => array(
				'type' => 'input',
				'eval' => 'required',
			)
		),
		'value_alias' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_uniqalias.value_alias',
			'config' => array(
				'type' => 'input',
				'eval' => 'required',
			)
		),
		'value_id' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_uniqalias.value_id',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
			)
		),
		'field_alias' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_uniqalias.field_alias',
			'config' => array(
				'type' => 'input',
				'eval' => 'required',
			)
		),
		'field_id' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_uniqalias.field_id',
			'config' => array(
				'type' => 'input',
				'eval' => 'required',
			)
		),
	),
	'types' => array(
		'types' => array(
			0 => array(
				'showitem' => '',
			),
		),
	),
);