<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 2003-2004 Kasper Skårhøj (kasper@typo3.com)
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
/**
 * Test script for RealURL encoding/decoding
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 */


	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require ('conf.php');
require ($BACK_PATH.'init.php');

if (!is_object($BE_USER) || !$BE_USER->isAdmin())	die('No access..');

	// Init Object:
require_once(t3lib_extMgm::extPath('realurl').'class.tx_realurl.php');


	// Test function:
function test($urls,$config,$title,$displayOnlyOnStateNo=0)	{

		// Init object:
	$urlObj = t3lib_div::makeInstance('tx_realurl');
	$urlObj->extConf = $config;

		// Traverse URLs:
	$urls = t3lib_div::trimExplode(chr(10),$urls,1);
	$resultPairs = array();
	foreach($urls as $k => $singleUrl)	{

			// Encode:
		$uParts = parse_url($singleUrl);
		$SpURL = $urlObj->encodeSpURL_doEncode($uParts['query']);

			// Decode:
		$uParts = parse_url($SpURL);
		$GETvars = $urlObj->decodeSpURL_doDecode($uParts['path']);
		$remainingP = $uParts['query'];

		list(,$origP) = explode('?',$singleUrl,2);
		$origP = orderParameters($origP);
		$vars = orderParameters('id='.$GETvars['id'].($remainingP ? '&'.$remainingP : '').t3lib_div::implodeArrayForUrl('', $GETvars['GET_VARS'], '', 0, 1));
		$stateNo = $origP!=$vars;

		if ($stateNo || !$displayOnlyOnStateNo)
			$resultPairs[$k] = array($singleUrl, $SpURL, $GETvars, $origP, $vars, $stateNo ? 'NO!!' : 'YES');
	}

	debug($resultPairs,$title);
}
function orderParameters($vars)	{

	$all = explode('&',$vars);
	$collect = array();
	foreach($all as $v)	{
		list($p,$val) = explode('=',$v,2);
		if (strlen($val))		$collect[rawurldecode($p)] = rawurldecode($val);
	}

	ksort($collect);
	return t3lib_div::implodeArrayForUrl('', $collect);
}


	// First test array:
$urls = '
	index.php?id=123
	index.php?id=123&type=1
	index.php?id=123&type=2
	index.php?id=123&type=1&L=1
	index.php?id=123&type=1&L=1&print=1
	index.php?id=123&type=1&L=1&print=1&no_cache=1
	index.php?id=123&type=1&L=1&print=1&tx_myExt[p1]=aaa
	index.php?id=123&type=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?id=123&type=1&L=2
	index.php?id=123&type=1&L=2&print=1
	index.php?id=123&type=1&L=2&print=1&no_cache=1
	index.php?id=123&type=1&L=2&print=1&tx_myExt[p1]=aaa
	index.php?id=123&type=1&L=2&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?id=123&type=1&L=3
';
$config = array(
	'preVars' => array(
		array(
			'GETvar' => 'L',
			'valueMap' => array(
				'dk' => '1',
				'danish' => '1',
				'nl' => '2',
				'dutch' => '2',
			),
			'noMatch' => 'bypass',
		),
	),
	'fileName' => array (
		'index' => array(
			'print.html' => array(
				'keyValues' => array (
					'print' => 1,
					'type' => 1,
				)
			),
			'page.html' => array(
				'keyValues' => array (
					'type' => 1,
				)
			),
			'top.html' => array(
				'keyValues' => array (
					'type' => 2,
				)
			),
			'_DEFAULT' => array(
				'keyValues' => array(
				)
			),
		),
	),
	'postVarSets' => array(
		'_DEFAULT' => array (
			'ext' => array(
				array(
					'GETvar' => 'tx_myExt[p1]',
				),
				array(
					'GETvar' => 'tx_myExt[p2]',
				),
			),
		),
	),
);
test($urls,$config,'Typical',1);



$config = array(
	'fileName' => array (
		'index' => array(


				// Danish
			'print.html.dk' => array(
				'keyValues' => array (
					'L' => 1,
					'print' => 1,
					'type' => 1,
				)
			),
			'page.html.dk' => array(
				'keyValues' => array (
					'L' => 1,
					'type' => 1,
				)
			),
			'top.html.dk' => array(
				'keyValues' => array (
					'L' => 1,
					'type' => 2,
				)
			),
			'index.html.dk' => array(
				'keyValues' => array(
					'L' => 1,
				)
			),

				// Dutch
			'print.html.nl' => array(
				'keyValues' => array (
					'L' => 2,
					'print' => 1,
					'type' => 1,
				)
			),
			'page.html.nl' => array(
				'keyValues' => array (
					'L' => 2,
					'type' => 1,
				)
			),
			'top.html.nl' => array(
				'keyValues' => array (
					'L' => 2,
					'type' => 2,
				)
			),
			'index.html.nl' => array(
				'keyValues' => array(
					'L' => 2,
				)
			),


				// English (important that it is last so danish/dutch (having more parameters) are chosen if matched!)
			'print.html' => array(
				'keyValues' => array (
					'print' => 1,
					'type' => 1,
				)
			),
			'page.html' => array(
				'keyValues' => array (
					'type' => 1,
				)
			),
			'top.html' => array(
				'keyValues' => array (
					'type' => 2,
				)
			),
			'index.html' => array(
				'keyValues' => array(
				)
			),
		),
	),
	'postVarSets' => array(
		'_DEFAULT' => array (
			'ext' => array(
				array(
					'GETvar' => 'tx_myExt[p1]',
				),
				array(
					'GETvar' => 'tx_myExt[p2]',
				),
			),
		),
	),
);
test($urls,$config,'Language in filenames',1);




	// First test array:
$urls = '
	index.php?id=123
	index.php?id=123&type=1
	index.php?id=123&type=2
	index.php?id=123&type=1&L=1
	index.php?id=123&type=1&L=1&print=1
	index.php?id=123&type=1&L=1&print=1&no_cache=1
	index.php?id=123&type=1&L=1&print=1&tx_myExt[p1]=aaa
	index.php?id=123&type=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?id=123&type=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p3]=ccc
	index.php?id=123&type=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc&no_cache=1
	index.php?id=123&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=1

	index.php?&type=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p3]=ccc
	index.php?id=123&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc&no_cache=1
	index.php?id=123&tx_mininews[showUid]=123&tx_mininews[mode]=1

	index.php?id=123&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=456&type=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?id=123&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=2
	index.php?id=123&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=3&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=123&type=99&L=1&print=1&tx_myExt[p1]=aaa
	index.php?id=456&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=2
	index.php?id=456&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=3&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=456&type=99&L=1&print=1&tx_myExt[p1]=aaa
';
$config = array(
	'preVars' => array(
		array(
			'GETvar' => 'no_cache',
			'valueMap' => array(
				'no_cache' => 1,
			),
			'noMatch' => 'bypass',
		),
		array(
			'GETvar' => 'L',
			'valueMap' => array(
				'dk' => '1',
				'danish' => '1',
				'uk' => '2',
				'english' => '2',
			),
			'noMatch' => 'bypass',
		),
	),
	'fileName' => array (
		'index' => array(
			'print.html' => array(
				'keyValues' => array (
					'print' => 1,
					'type' => 1,
				)
			),
			'page.html' => array(
				'keyValues' => array (
					'type' => 1,
				)
			),
			'top.html' => array(
				'keyValues' => array (
					'type' => 2,
				)
			),
			'_DEFAULT' => array(
				'keyValues' => array(
					#'type' => ''
				)
			),
		),
	),
	'fixedPostVars' => array(
		'placeholder' => array(
			array(
				'GETvar' => 'tx_myExt[p1]',
			),
			array(
				'GETvar' => 'tx_myExt[p2]',
			),
			array(
				'GETvar' => 'tx_myExt[p3]',
			),
		),
		'456' => 'placeholder'
	),
	'postVarSets' => array(
		'_DEFAULT' => array (
			'plaintext' => array(
				'type' => 'single',	// Special feature of postVars
				'keyValues' => array (
					'type' => 99
				)
			),
			'ext' => array(
				array(
					'GETvar' => 'tx_myExt[p1]',
				),
				array(
					'GETvar' => 'tx_myExt[p2]',
				),
				array(
					'GETvar' => 'tx_myExt[p3]',
				),
			),
			'news' => array(
				array(
					'GETvar' => 'tx_mininews[mode]',
					'valueMap' => array(
						'list' => 1,
						'details' => 2,
					)
				),
				array(
					'GETvar' => 'tx_mininews[showUid]',
				),
			),
		),
	),
);
test($urls,$config,'Default',1);

unset($config['preVars']);
test($urls,$config,'No prevars',1);

unset($config['fileName']);
test($urls,$config,'No prevars, no files',1);


$config['fileName'] = array (
	'index' => array(
		'print.html' => array(
			'keyValues' => array (
				'print' => 1,
				'type' => 1,
			)
		),
		'_DEFAULT' => array(
			'keyValues' => array (
				'type' => 1,
			)
		),
		'top.html' => array(
			'keyValues' => array (
				'type' => 2,
			)
		),
		'page.html' => array(
			'keyValues' => array(
				#'type' => ''
			)
		),
	),
);
#debug('filename test');
test($urls,$config,'Filename test',1);








	// First test array:
$urls = '
	index.php?id=123&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=2
	index.php?id=123&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=3&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=123&type=99&L=1&print=1&tx_myExt[p1]=aaa
	index.php?id=456&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=2
	index.php?id=456&type=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=3&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=456&type=99&L=1&print=1&tx_myExt[p1]=aaa
';
$config = array(
	'postVarSets' => array(
		'_DEFAULT' => array (
			'plaintext' => array(
				'type' => 'single',	// Special feature of postVars
				'keyValues' => array (
					'type' => 99
				)
			),
			'ext' => array(
				array(
					'GETvar' => 'tx_myExt[p1]',
				),
				array(
					'GETvar' => 'tx_myExt[p2]',
				),
				array(
					'GETvar' => 'tx_myExt[p3]',
				),
			),
			'news' => array(
				array(
					'GETvar' => 'tx_mininews[mode]',
					'valueMap' => array(
						'list' => 1,
						'details' => 2,
					)
				),
				array(
					'GETvar' => 'tx_mininews[showUid]',
				),
			),
		),
		'456' => array(
			'news' => array(
				array(
					'GETvar' => 'tx_mininews[showUid]',
				),
				array(
					'GETvar' => 'tx_mininews[mode]',
					'valueMap' => array(
						'list' => 1,
						'details' => 2,
					)
				),
			),
		)
	),
);

test($urls,$config,'Various',1);








	// First test array:
$urls = '
	index.php?id=123
	index.php?id=123&ty%2588pe=1
	index.php?id=123&ty%2588pe=2
	index.php?id=123&ty%2588pe=1&L=1
	index.php?id=123&ty%2588pe=1&L=1&print=1
	index.php?id=123&ty%2588pe=1&L=1&print=1&no_cache=1
	index.php?id=123&ty%2588pe=1&L=1&print=1&tx_myExt[p1]=aaa
	index.php?id=123&ty%2588pe=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?id=123&ty%2588pe=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p3]=ccc
	index.php?id=123&ty%2588pe=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc&no_cache=1
	index.php?id=123&ty%2588pe=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=1

	index.php?&ty%2588pe=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p3]=ccc
	index.php?id=123&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc&no_cache=1
	index.php?id=123&tx_mininews[showUid]=123&tx_mininews[mode]=1

	index.php?id=123&ty%2588pe=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=456&ty%2588pe=1&L=1&print=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?id=123&ty%2588pe=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=2
	index.php?id=123&ty%2588pe=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=3&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=123&ty%2588pe=99&L=1&print=1&tx_myExt[p1]=aaa
	index.php?id=456&ty%2588pe=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=2
	index.php?id=456&ty%2588pe=1&L=1&print=1&tx_mininews[showUid]=123&tx_mininews[mode]=3&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=456&ty%2588pe=99&L=1&print=1&tx_myExt[p1]=aaa
';
$config = array(
	'preVars' => array(
		array(
			'GETvar' => 'no_cache',
			'valueMap' => array(
				'no_cache' => 1,
			),
			'noMatch' => 'bypass',
		),
		array(
			'GETvar' => 'L',
			'valueMap' => array(
				'dkÆØÅ%88' => '1',
				'ukÆØÅ%88' => '2',
			),
			'noMatch' => 'bypass',
		),
	),
	'fileName' => array (
		'index' => array(
			'printÆØÅ%88.html' => array(
				'keyValues' => array (
					'print' => 1,
					'ty%88pe' => 1,
				)
			),
			'pageÆØÅ%88.html' => array(
				'keyValues' => array (
					'ty%88pe' => 1,
				)
			),
			'topÆØÅ%88.html' => array(
				'keyValues' => array (
					'ty%88pe' => 2,
				)
			),
			'_DEFAULT' => array(
				'keyValues' => array(
					#'type' => ''
				)
			),
		),
	),
	'fixedPostVars' => array(
		'placeholder' => array(
			array(
				'GETvar' => 'tx_myExt[p1]',
			),
			array(
				'GETvar' => 'tx_myExt[p2]',
			),
			array(
				'GETvar' => 'tx_myExt[p3]',
			),
		),
		'456' => 'placeholder'
	),
	'postVarSets' => array(
		'_DEFAULT' => array (
			'plainÆØÅte%88xt' => array(
				'type' => 'single',	// Special feature of postVars
				'keyValues' => array (
					'ty%88pe' => 99
				)
			),
			'extÆØÅ%88' => array(
				array(
					'GETvar' => 'tx_myExt[p1]',
				),
				array(
					'GETvar' => 'tx_myExt[p2]',
				),
				array(
					'GETvar' => 'tx_myExt[p3]',
				),
			),
			'newsÆØÅ%88' => array(
				array(
					'GETvar' => 'tx_mininews[mode]',
					'valueMap' => array(
						'list' => 1,
						'details' => 2,
					)
				),
				array(
					'GETvar' => 'tx_mininews[showUid]',
				),
			),
		),
	),
);
test($urls,$config,'Special chars',1);






	// Example 1
$urls = '
	index.php?id=123&type=1&L=1&tx_mininews[mode]=1&tx_mininews[showUid]=456
';

$config = array(
	'preVars' => array(
		array(
			'GETvar' => 'L',
			'valueMap' => array(
				'dk' => '1',
			),
			'noMatch' => 'bypass',
		),
	),
	'fileName' => array (
		'index' => array(
			'page.html' => array(
				'keyValues' => array (
					'type' => 1,
				)
			),
			'_DEFAULT' => array(
				'keyValues' => array(
				)
			),
		),
	),
	'postVarSets' => array(
		'_DEFAULT' => array (
			'news' => array(
				array(
					'GETvar' => 'tx_mininews[mode]',
					'valueMap' => array(
						'list' => 1,
						'details' => 2,
					)
				),
				array(
					'GETvar' => 'tx_mininews[showUid]',
				),
			),
		),
	),
);
test($urls,$config,'Example 1',1);





	// Example 2: Post vars
$urls = '
	index.php?id=123&tx_myExt[p1]=aaa
	index.php?id=123&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb
	index.php?id=123&tx_myExt[p1]=aaa&tx_myExt[p3]=ccc
	index.php?id=123&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=123&tx_mininews[showUid]=123&tx_mininews[mode]=1
	index.php?id=123&tx_mininews[showUid]=123&tx_mininews[mode]=1&tx_myExt[p1]=aaa&tx_myExt[p2]=bbb&tx_myExt[p3]=ccc
	index.php?id=123&tx_mininews[showUid]=123&tx_myExt[p1]=aaa&tx_myExt[p3]=ccc
	index.php?id=123&type=99&tx_myExt[p1]=aaa&unknownGetVar=foo
';
$config = array(
	'fixedPostVars' => array(
		'placeholder' => array(
			array(
				'GETvar' => 'tx_myExt[p1]',
			),
			array(
				'GETvar' => 'tx_myExt[p2]',
			),
			array(
				'GETvar' => 'tx_myExt[p3]',
			),
		),
		'456' => 'placeholder'
	),
	'postVarSets' => array(
		'_DEFAULT' => array (
			'plaintext' => array(
				'type' => 'single',	// Special feature of postVars
				'keyValues' => array (
					'type' => 99
				)
			),
			'ext' => array(
				array(
					'GETvar' => 'tx_myExt[p1]',
				),
				array(
					'GETvar' => 'tx_myExt[p2]',
				),
				array(
					'GETvar' => 'tx_myExt[p3]',
				),
			),
			'news' => array(
				array(
					'GETvar' => 'tx_mininews[mode]',
					'valueMap' => array(
						'list' => 1,
						'details' => 2,
					)
				),
				array(
					'GETvar' => 'tx_mininews[showUid]',
				),
			),
		),
	),
);
test($urls,$config,'Example 2',1);

?>
