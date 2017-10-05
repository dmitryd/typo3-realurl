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

$GLOBALS['TCA']['tx_realurl_pathdata'] = array(
	'ctrl' => array(
		'title' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_pathdata',
		'label' => 'pagepath',
		'iconfile' => 'EXT:realurl/Resources/Public/Icons/Extension.svg',
		'hideTable' => 1,
		'rootLevel' => 1,
	),
	'columns' => array(
		'page_id' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_pathdata.page_id',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
			)
		),
		'rootpage_id' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_pathdata.rootpage_id',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
			)
		),
		'language_id' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_pathdata.language_id',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
			)
		),
		'mpvar' => array(
				'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_pathdata.mpvar',
			'config' => array(
				'type' => 'input',
				'eval' => 'trim',
			)
		),
		'pagepath' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_pathdata.pagepath',
			'config' => array(
				'type' => 'input',
				'eval' => 'trim,required',
			)
		),
		'expire' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_pathdata.expire',
			'config' => array(
				'type' => 'input',
				'renderType' => 'inputDateTime',
				'eval' => 'datetime',
				'default' => 0,
			)
		),
	),
	'types' => array(
		0 => array(
			'showitem' => '',
		),
	),
);
