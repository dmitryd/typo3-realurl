<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Speaking URLs for TYPO3',
	'description' => '',
	'category' => 'services',
	'version' => '2.0.0',
	'state' => 'alpha',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => 'pages,sys_domain,pages_language_overlay,sys_template',
	'clearcacheonload' => 1,
	'author' => 'Dmitry Dulepov',
	'author_email' => 'dmitry.dulepov@gmail.com',
	'author_company' => 'SIA ACCIO',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0-7.99.99',
			'php' => '5.3.2-'
		),
		'conflicts' => array(
			'cooluri' => ''
		),
		'suggests' => array(
			'static_info_tables' => '6.2.0-',
		),
	),
);
