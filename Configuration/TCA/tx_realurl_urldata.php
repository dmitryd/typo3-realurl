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

$GLOBALS['TCA']['tx_realurl_urldata'] = array(
	'ctrl' => array(
		'title' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_urldata',
		'label' => 'speaking_url',
		'iconfile' => 'EXT:realurl/Resources/Public/Icons/Extension.svg',
		'hideTable' => 1,
		'rootLevel' => 1,
	),
	'columns' => array(
		'page_id' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_urldata.page_id',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
			)
		),
		'rootpage_id' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_urldata.rootpage_id',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
			)
		),
		'original_url' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_urldata.original_url',
			'config' => array(
				'type' => 'input',
				'eval' => 'trim,required',
			)
		),
		'speaking_url' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_urldata.speaking_url',
			'config' => array(
				'type' => 'input',
				'eval' => 'trim,required',
			)
		),
		'request_variables' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_urldata.request_variables',
			'config' => array(
				'type' => 'input',
			)
		),
		'expire' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:tx_realurl_urldata.expire',
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
