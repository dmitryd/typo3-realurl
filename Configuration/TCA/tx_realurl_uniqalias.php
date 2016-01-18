<?php
$GLOBALS['TCA']['tx_realurl_uniqalias'] = array(
	'ctrl' => array(
		'label' => '',
		'hideTable' => 1,
	),
	'columns' => array(
		'expire' => array(
			'label' => '',
			'config' => array(
				'type' => 'input',
				'eval' => 'datetime',
				'default' => 0,
			)
		),
		'lang' => array(
			'label' => '',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
				'default' => 0,
			)
		),
		'tablename' => array(
			'label' => '',
			'config' => array(
				'type' => 'input',
				'eval' => 'required',
			)
		),
		'value_alias' => array(
			'label' => '',
			'config' => array(
				'type' => 'input',
				'eval' => 'required',
			)
		),
		'value_id' => array(
			'label' => '',
			'config' => array(
				'type' => 'input',
				'eval' => 'int,required',
			)
		),
		'field_alias' => array(
			'label' => '',
			'config' => array(
				'type' => 'input',
				'eval' => 'required',
			)
		),
		'field_id' => array(
			'label' => '',
			'config' => array(
				'type' => 'input',
				'eval' => 'required',
			)
		),
	)
);