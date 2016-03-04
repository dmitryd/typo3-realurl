<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Speaking URLs for TYPO3',
	'description' => 'Makes TYPO3 URLs search egnine friendly. Donations are welcome to dmitry.dulepov@gmail.com. They help to support the extension!',
	'category' => 'services',
	'version' => '2.0.2',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => 'pages,sys_domain,pages_language_overlay,sys_template',
	'clearcacheonload' => 1,
	'author' => 'Dmitry Dulepov',
	'author_email' => 'dmitry.dulepov@gmail.com',
	'author_company' => '',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0-7.99.999',
			'php' => '5.3.2-7.0.999'
		),
		'conflicts' => array(
			'cooluri' => '',
			'simulatestatic' => ''
		),
		'suggests' => array(
			'static_info_tables' => '6.2.0-',
		),
	),
);
