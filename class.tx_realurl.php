<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004 Kasper Skaarhoj (kasper@typo3.com)
*  All rights reserved
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
/**
 * Class for creating and parsing Speaking Urls
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   94: class tx_realurl 
 *
 *              SECTION: Translate parameters to a Speaking URL (t3lib_tstemplate::linkData)
 *  124:     function encodeSpURL(&$params, $ref)	
 *  164:     function encodeSpURL_doEncode($inputURL)	
 *  226:     function encodeSpURL_pathFromId(&$paramKeyValues, &$pathParts)	
 *  259:     function encodeSpURL_gettingPostVarSets(&$paramKeyValues, &$pathParts, $postVarSetCfg)	
 *  296:     function encodeSpURL_fileName(&$paramKeyValues)	
 *  318:     function encodeSpURL_setSequence($varSetCfg, &$paramKeyValues, &$pathParts)	
 *  406:     function encodeSpURL_setSingle($keyWord, $keyValues, &$paramKeyValues, &$pathParts)	
 *  439:     function encodeSpURL_encodeCache($urlToEncode, $setEncodedURL='')	
 *
 *              SECTION: Translate a Speaking URL to parameters (tslib_fe)
 *  477:     function decodeSpURL($params, $ref)	
 *  522:     function decodeSpURL_checkRedirects($speakingURIpath)	
 *  537:     function decodeSpURL_doDecode($speakingURIpath)	
 *  582:     function decodeSpURL_idFromPath(&$pathParts)	
 *  609:     function decodeSpURL_settingPreVars(&$pathParts, $config)	
 *  631:     function decodeSpURL_settingPostVarSets(&$pathParts, $postVarSetCfg)	
 *  669:     function decodeSpURL_fileName($fileName)	
 *  691:     function decodeSpURL_getSequence(&$pathParts,$setupArr)	
 *  770:     function decodeSpURL_getSingle($keyValues)	
 *  787:     function decodeSpURL_throw404($msg)	
 *  796:     function decodeSpURL_jumpAdmin()	
 *  819:     function decodeSpURL_decodeCache($speakingURIpath,$cachedInfo='')	
 *
 *              SECTION: General helper functions (both decode/encode)
 *  844:     function lookUpTranslation($cfg,$value,$aliasToUid=FALSE)	
 *  881:     function setConfig()	
 *  903:     function getPostVarSetConfig($page_id, $mainCat='postVarSets')	
 *  924:     function pageAliasToID($alias)	
 *  944:     function rawurlencodeParam($str)	
 *
 * TOTAL FUNCTIONS: 25
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */










/**
 * Class for creating and parsing Speaking Urls
 * This class interfaces with hooks in TYPO3 inside tslib_fe (for parsing speaking URLs to GET parameters) and in t3lib_tstemplate (for parsing GET parameters into a speaking URL)
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @package TYPO3
 * @subpackage tx_realurl
 */
class tx_realurl {

		// External, static:
	var $NA = '-';					// Substitute value for "blank" values
	var $maxLookUpLgd = 100;		// Max. length of look-up strings. Just a "brake"
	var $prefixEnablingSpURL = 'index.php';		// Only work Speaking URL on URLs starting with "index.php"

		// Internal:
	var $pObj;						// tslib_fe / GLOBALS['TSFE'] (for ->decodeSpURL())
	var $extConf;					// Configuration for extension, from $TYPO3_CONF_VARS['EXTCONF']['realurl']
	var $adminJumpSet = FALSE;		// Is set true (->encodeSpURL) if AdminJump is active in some way. Is set false again when captured first time!
	var $filePart;					// Contains the filename when a Speaking URL is decoded.
	var $orig_paramKeyValues = array();	// Contains the index of GETvars that the URL had when the encoding began.




	/************************************
	 *
	 * Translate parameters to a Speaking URL (t3lib_tstemplate::linkData)
	 *
	 ************************************/

	/**
	 * Translates a URL with query string (GET parameters) into Speaking URL.
	 * Called from t3lib_tstemplate::linkData
	 *
	 * @param	array		Array of parameters from t3lib_tstemplate::linkData - the function creating all links inside TYPO3
	 * @param	object		Copy of parent caller. Not used.
	 * @return	void
	 */
	function encodeSpURL(&$params, $ref)	{
		if (TYPO3_DLOG)	t3lib_div::devLog('Starting encoding: '.$params['LD']['totalURL'], 'realurl');

			// Return directly, if simulateStaticDocuments is set:
		if ($GLOBALS['TSFE']->config['config']['simulateStaticDocuments'])		return;

			// Return directly, if realurl is not enabled:
		if (!$GLOBALS['TSFE']->config['config']['tx_realurl_enable'])		return;

			// Checking prefix:
		if (substr($params['LD']['totalURL'],0,strlen($this->prefixEnablingSpURL)) != $this->prefixEnablingSpURL)	return;

			// Initializing config / request URL:
		$this->setConfig();

			// Init "Admin Jump"; If frontend edit was enabled by the current URL of the page, set it again in the generated URL (and disable caching!)
		if ($GLOBALS['TSFE']->applicationData['tx_realurl']['adminJumpActive'])	{
			$GLOBALS['TSFE']->set_no_cache();
			$this->adminJumpSet = TRUE;
		}

			// Parse current URL into main parts:
		$uParts = parse_url($params['LD']['totalURL']);

			// Look in memory cache first:
		$newUrl = $this->encodeSpURL_encodeCache($uParts['query']);
		if (!$newUrl)	{

				// Encode URL:
			$newUrl = $this->encodeSpURL_doEncode($uParts['query']);

				// Set new URL in cache:
			$this->encodeSpURL_encodeCache($uParts['query'], $newUrl);
		}

			// Adding any anchor there might be:
		if ($uParts['fragment'])	$newUrl.= '#'.$uParts['fragment'];

			// Setting the encoded URL in the LD key of the params array - that value is passed by reference and thus returned to the linkData function!
		$params['LD']['totalURL'] = $newUrl;
	}

	/**
	 * Transforms a query string into a speaking URL according to the configuration in ->extConf
	 *
	 * @param	string		Input URL (with GET parameters)
	 * @return	string		Output Speaking URL (with as many GET parameters encoded into the URL as possible).
	 * @see encodeSpURL()
	 */
	function encodeSpURL_doEncode($inputQuery)	{

			// Extract all GET parameters into an ARRAY:
		$paramKeyValues = array();
		$GETparams = explode('&', $inputQuery);
		foreach($GETparams as $paramAndValue)	{
			list($p,$v) = explode('=', $paramAndValue, 2);
			if (strlen($p))	$paramKeyValues[rawurldecode($p)] = rawurldecode($v);
		}
		$this->orig_paramKeyValues = $paramKeyValues;

			// Init array in which to collect the "directories" of the URL:
		$pathParts = array();

			// Pre-vars:
		$this->encodeSpURL_setSequence($this->extConf['preVars'],$paramKeyValues,$pathParts);

			// Create path from ID value:
		$page_id = $paramKeyValues['id'];
		$this->encodeSpURL_pathFromId($paramKeyValues,$pathParts);

			// Fixed Post-vars:
		$fixedPostVarSetCfg = $this->getPostVarSetConfig($page_id, 'fixedPostVars');
		if (is_array($fixedPostVarSetCfg))	{
			$this->encodeSpURL_setSequence($fixedPostVarSetCfg,$paramKeyValues,$pathParts);
		}

			// Post var sets:
		$postVarSetCfg = $this->getPostVarSetConfig($page_id);
		$this->encodeSpURL_gettingPostVarSets($paramKeyValues,$pathParts,$postVarSetCfg);

			// Compile Speaking URL path
		$newUrl = ereg_replace('\/*$','',implode('/',$pathParts)).'/';

			// Add filename, if any:
		$fileName = $this->encodeSpURL_fileName($paramKeyValues);
		$newUrl.= rawurlencode($fileName);

			// Manage remaining GET parameters:
		if (count($paramKeyValues))	{
			$q = array();
			foreach($paramKeyValues as $k => $v)	{
				$q[] = $this->rawurlencodeParam($k).'='.rawurlencode($v);
			}
			$newUrl.= '?'.implode('&', $q);
		}

			// Return new, Speaking URL encoded URL:
		return $newUrl;
	}

	/**
	 * Creating the TYPO3 Page path into $pathParts from the "id" value in $paramKeyValues
	 *
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts: Here we take out "id" GET var.
	 * @param	array		Numerical array of path-parts, continously being filled. Here, the "page path" is being added by which-ever method is preferred. Passed by reference.
	 * @return	void		Unsetting "id" from $paramKeyValues / Setting page path in $pathParts
	 * @see encodeSpURL_doEncode()
	 */
	function encodeSpURL_pathFromId(&$paramKeyValues, &$pathParts)	{

			// Return immediately if no GET vars remain to be translated:
		if (!count($paramKeyValues))	return;

			// Creating page path:
		switch((string)$this->extConf['pagePath']['type'])	{
			case 'user':
				$params = array(
					'paramKeyValues' => &$paramKeyValues,
					'pathParts' => &$pathParts,
					'pObj' => &$this,
					'conf' => $this->extConf['pagePath'],
     				'mode' => 'encode',
				);
				return t3lib_div::callUserFunction($this->extConf['pagePath']['userFunc'], $params, $this);
			break;
			default:	// Default: Just passing through the ID/alias of the page:
				$pathParts[] = rawurlencode($paramKeyValues['id']);
				unset($paramKeyValues['id']);
			break;
		}
	}

	/**
	 * Traversing setup for variables AFTER the page path.
	 *
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts, continously shortend. Passed by reference.
	 * @param	array		Numerical array of path-parts, continously being filled. Passed by reference.
	 * @param	array		$postVarSetCfg config
	 * @return	void		Removing values from $paramKeyValues / Setting values in $pathParts
	 * @see encodeSpURL_doEncode(), decodeSpURL_settingPostVarSets()
	 */
	function encodeSpURL_gettingPostVarSets(&$paramKeyValues, &$pathParts, $postVarSetCfg)	{

			// Traverse setup for postVarSets. If any of those matches
		if (is_array($postVarSetCfg))	{
		   foreach($postVarSetCfg as $keyWord => $cfg)	{
				switch((string)$cfg['type'])	{
					case 'admin':
						if ($this->adminJumpSet)	{
							$pathParts[] = rawurlencode($keyWord);
							$this->adminJumpSet = FALSE;		// ... this makes sure that any subsequent "admin-jump" activation is set...
						}
					break;
					case 'single':
						$this->encodeSpURL_setSingle($keyWord, $cfg['keyValues'], $paramKeyValues, $pathParts);
					break;
					default:
						unset($cfg['type']);	// Just to make sure it is NOT set.
						foreach($cfg as $Gcfg)	{
							if (isset($paramKeyValues[$Gcfg['GETvar']]))	{
								$pathParts[] = rawurlencode($keyWord);
								$this->encodeSpURL_setSequence($cfg, $paramKeyValues, $pathParts);
								break;
							}
						}
					break;
				}
			}
  		}
	}

	/**
	 * Setting a filename if any filename is configured to match remaining variables.
	 *
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts, continously shortend. Passed by reference.
	 * @return	string		Returns the filename to prepend, if any
	 * @see encodeSpURL_doEncode(), decodeSpURL_fileName()
	 */
	function encodeSpURL_fileName(&$paramKeyValues)	{

			// Look if any filename matches the remaining variables:
		if (is_array($this->extConf['fileName']['index']))	{
			foreach($this->extConf['fileName']['index'] as $keyWord => $cfg)	{
				$pathParts = array();
				if ($this->encodeSpURL_setSingle($keyWord, $cfg['keyValues'], $paramKeyValues, $pathParts))	{
					return $keyWord != '_DEFAULT' ? $keyWord : '';
				}
			}
		}
	}

	/**
	 * Traverses a set of GETvars configured (array of segments)
	 *
	 * @param	array		Array of segment-configurations.
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts, continously shortend. Passed by reference.
	 * @param	array		Numerical array of path-parts, continously being filled. Passed by reference.
	 * @return	void		Removing values from $paramKeyValues / Setting values in $pathParts
	 * @see encodeSpURL_doEncode(), encodeSpURL_gettingPostVarSets(), decodeSpURL_getSequence()
	 */
	function encodeSpURL_setSequence($varSetCfg, &$paramKeyValues, &$pathParts)	{

			// Traverse array of segments configuration
		$prevVal = '';
		if (is_array($varSetCfg))	{
			foreach($varSetCfg as $setup)	{
				switch($setup['type'])	{
					case 'action':
						$pathPartVal = '';

							// Look for admin jump:
						if ($this->adminJumpSet)	{
							foreach($setup['index'] as $pKey => $pCfg)	{
								if ((string)$pCfg['type']=='admin')	{
									$pathPartVal = $pKey;
									$this->adminJumpSet = FALSE;
									break;
								}
							}
						}

							// If either pathPartVal has been set OR if _DEFAULT type is not bypass, set a value:
						if (strlen($pathPartVal) || $setup['index']['_DEFAULT']['type']!='bypass')	{

								// If admin jump did not set $pathPartVal, look for first pass-through (no "type" set):
							if (!strlen($pathPartVal))	{
								foreach($setup['index'] as $pKey => $pCfg)	{
									if (!strlen($pCfg['type']))	{
										$pathPartVal = $pKey;
										break;
									}
								}
							}

								// Setting part of path:
							$pathParts[] = rawurlencode(strlen($pathPartVal) ? $pathPartVal : $this->NA);
						}
					break;
					default:
						if (!isset($setup['condPrevValue']) || !strcmp($prevVal,$setup['condPrevValue']))	{

								// Looking if the GET var is found in parameter index:
							$GETvarVal = isset($paramKeyValues[$setup['GETvar']]) ? $paramKeyValues[$setup['GETvar']] : '';

								// Set reverse map:
							$revMap = is_array($setup['valueMap']) ? array_flip($setup['valueMap']) : array();

								// Looking for value in value map
							if (isset($revMap[$GETvarVal]))	{
								$prevVal = $GETvarVal;
								$pathParts[] = rawurlencode($revMap[$GETvarVal]);
							} elseif ($setup['noMatch']=='bypass')	{	// If no match in reverse value map and "bypass" is set, then return the value to $pathParts and break
								// Do nothing...
							} elseif ($setup['noMatch']=='null')	{	// If no match and "null" is set, then set "dummy" value
								// Set "dummy" value (?)
								$prevVal = '';
								$pathParts[] = '';
							} elseif (is_array($setup['lookUpTable']))	{
								$prevVal = $GETvarVal;
								$GETvarVal = $this->lookUpTranslation($setup['lookUpTable'],$GETvarVal);
								$pathParts[] = rawurlencode($GETvarVal);
							} elseif (isset($setup['valueDefault']))	{
								$prevVal = $setup['valueDefault'];
								$pathParts[] = rawurlencode($setup['valueDefault']);
							} else {
								$prevVal = $GETvarVal;
								$pathParts[] = rawurlencode($GETvarVal);
							}

								// Finally, unset GET var so it doesn't get processed once more:
							unset($paramKeyValues[$setup['GETvar']]);
						}
					break;
				}
			}
		}
	}

	/**
	 * Traversing an array of GETvar => value pairs and checking if both variable names AND values are matching any found in $paramKeyValues; If so, the keyword representing those values is set and the GEtvars are unset from $paramkeyValues array
	 *
	 * @param	string		Keyword to set as a representation of the GETvars configured.
	 * @param	array		Array of GETvar => values which content in $paramKeyvalues must match exactly in order to be substituted with the keyword, $keyWord
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts, continously shortend. Passed by reference.
	 * @param	array		Numerical array of path-parts, continously being filled. Passed by reference.
	 * @return	boolean		Return true, if any value from $paramKeyValues was removed.
	 * @see encodeSpURL_fileName(), encodeSpURL_gettingPostVarSets(), decodeSpURL_getSingle()
	 */
	function encodeSpURL_setSingle($keyWord, $keyValues, &$paramKeyValues, &$pathParts)	{
		if (is_array($keyValues))	{
			$allSet = TRUE;

				// Check if all GETvars configured are found in $paramKeyValues:
			foreach($keyValues as $getVar => $value)	{
				if (!isset($paramKeyValues[$getVar]) || strcmp($paramKeyValues[$getVar],$value))	{
					$allSet = FALSE;
					break;
				}
			}

				// If all is set, unset the GETvars and set the value.
			if ($allSet)	{
				$pathParts[] = rawurlencode($keyWord);
				foreach($keyValues as $getVar => $value)	{
					unset($paramKeyValues[$getVar]);
				}

				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Setting / Getting encoded URL to/from cache (memory cache, but could be extended to database cache)
	 *
	 * @param	string		The original URL with GET parameters - identifying the cached version to find.
	 * @param	string		If set, this URL will be cached as the encoded version of $urlToEncode. Otherwise the function will look for and return the cached version of $urlToEncode
	 * @return	mixed		If $setEncodedURL is true, this will be STORED as the cached version and the function returns false, otherwise the cached version is returned (string).
	 * @see encodeSpURL()
	 */
	function encodeSpURL_encodeCache($urlToEncode, $setEncodedURL='')	{
		if (!$setEncodedURL)	{	// Asking for cached encoded URL:
			return $GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE'][md5($urlToEncode)];
		} else {	// Setting encoded URL in cache:
			$GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE'][md5($urlToEncode)] = $setEncodedURL;
		}
	}











	/************************************
	 *
	 * Translate a Speaking URL to parameters (tslib_fe)
	 *
	 ************************************/

	/**
	 * Parse speaking URL and translate it to parameters understood by TYPO3
	 * Function is called from tslib_fe
	 * The overall format of a speaking URL is these five parts [TYPO3_SITE_URL] / [pre-var] / [page-identification] / [post-vars] / [file.ext]
	 * - "TYPO3_SITE_URL" is fixed value from the environment,
	 * - "pre-var" is any number of segments separated by "/" mapping to GETvars AND with a known lenght,
	 * - "page-identification" identifies the page id in TYPO3 possibly with multiple segments separated by "/" BUT with an UNKNOWN length,
	 * - "post-vars" is sets of segments offering the same features as "pre-var"
	 * - "file.ext" is any filename that might apply
	 *
	 * @param	array		Params for hook
	 * @param	object		Reference to parent object (copy)
	 * @return	void		Setting internal variables.
	 */
	function decodeSpURL($params, $ref)	{

			// Setting parent object reference (which is $GLOBALS['TSFE'])
		$this->pObj = &$params['pObj'];

			// Initializing config / request URL:
		$this->setConfig();

		if ($GLOBALS['HTTP_SERVER_VARS']['REDIRECT_URL'])	{		// If there has been a redirect (basically; we arrived here otherwise than via "index.php" in the URL) this can happend either due to a CGI-script or because of reWrite rule.

				// Getting the path which is above the current site url:
				// For instance "first/second/third/index.html?&param1=value1&param2=value2" should be the result of the URL "http://localhost/typo3/dev/dummy_1/first/second/third/index.html?&param1=value1&param2=value2"
			$speakingURIpath = substr(t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'),strlen(t3lib_div::getIndpEnv('TYPO3_SITE_URL')));

				// Redirecting if needed (exits if so).
			$this->decodeSpURL_checkRedirects($speakingURIpath);

				// Looking for cached information:
			$cachedInfo = $this->decodeSpURL_decodeCache($speakingURIpath);

				// If no cached info was found, create it:
			if (!is_array($cachedInfo))	{

					// Decode URL:
				$cachedInfo = $this->decodeSpURL_doDecode($speakingURIpath);

					// Storing cached information:
				$this->decodeSpURL_decodeCache($speakingURIpath, $cachedInfo);
			}

				// Setting info in TSFE:
			$this->pObj->mergingWithGetVars($cachedInfo['GET_VARS']);
			$this->pObj->id = $cachedInfo['id'];
		}
	}

	/**
	 * Look for redirect configuration.
	 * If the input path is found as key in $this->extConf['redirects'] this method redirects to the URL found as value
	 *
	 * @param	string		Path from SpeakingURL.
	 * @return	void
	 * @see decodeSpURL_doDecode()
	 */
	function decodeSpURL_checkRedirects($speakingURIpath)	{
		$speakingURIpath = trim($speakingURIpath);
		if (isset($this->extConf['redirects'][$speakingURIpath]))	{
			header('Location: '.t3lib_div::locationHeaderUrl($this->extConf['redirects'][$speakingURIpath]));
			exit;
		}
	}

	/**
	 * Decodes a speaking URL path into an array of GET parameters and a page id.
	 *
	 * @param	string		Speaking URL path (after the "root" path of the website!)
	 * @return	array		Array with id and GET parameters.
	 * @see decodeSpURL()
	 */
	function decodeSpURL_doDecode($speakingURIpath)	{

			// Cached info:
		$cachedInfo = array();

			// Split URL + resolve parts of path:
		$uParts = parse_url($speakingURIpath);
		$pathParts = explode('/', $uParts['path']);
		$this->filePart = array_pop($pathParts);

			// Setting "preVars":
		$pre_GET_VARS = $this->decodeSpURL_settingPreVars($pathParts, $this->extConf['preVars']);

			// Setting page id:
		$cachedInfo['id'] = $this->decodeSpURL_idFromPath($pathParts);

			// Fixed Post-vars:
		$fixedPostVarSetCfg = $this->getPostVarSetConfig($cachedInfo['id'], 'fixedPostVars');
		$fixedPost_GET_VARS = $this->decodeSpURL_settingPreVars($pathParts, $fixedPostVarSetCfg);

			// Setting "postVarSets":
		$postVarSetCfg = $this->getPostVarSetConfig($cachedInfo['id']);
		$post_GET_VARS = $this->decodeSpURL_settingPostVarSets($pathParts, $postVarSetCfg);

			// Setting filename:
		$file_GET_VARS = $this->decodeSpURL_fileName($this->filePart);

			// Merge Get vars together:
		$cachedInfo['GET_VARS'] = array();
		if (is_array($pre_GET_VARS)) $cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'],$pre_GET_VARS);
		if (is_array($fixedPost_GET_VARS)) $cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'],$fixedPost_GET_VARS);
		if (is_array($post_GET_VARS)) $cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'],$post_GET_VARS);
		if (is_array($file_GET_VARS)) $cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'],$file_GET_VARS);

			// Return information found:
		return $cachedInfo;
	}

	/**
	 * Extracts the page ID from URL.
	 *
	 * @param	array		Parts of path. NOTICE: Passed by reference.
	 * @return	integer		Page ID
	 * @see decodeSpURL_doDecode()
	 */
	function decodeSpURL_idFromPath(&$pathParts)	{
			// Creating page path:
		switch((string)$this->extConf['pagePath']['type'])	{
			case 'user':
				$params = array(
					'pathParts' => &$pathParts,
					'pObj' => &$this,
					'conf' => $this->extConf['pagePath'],
					'mode' => 'decode',
				);
				return t3lib_div::callUserFunction($this->extConf['pagePath']['userFunc'], $params, $this);
			break;
			default:	// Default: Just passing through the ID/alias of the page:
				$value = array_shift($pathParts);
				return $value;
			break;
		}
	}

	/**
	 * Analysing the path BEFORE the page identification part of the URL
	 *
	 * @param	array		The path splitted by "/". NOTICE: Passed by reference and shortend for each time a segment is matching configuration
	 * @param	array		Configuration
	 * @return	array		GET-vars resulting from the analysis
	 * @see decodeSpURL_doDecode()
	 */
	function decodeSpURL_settingPreVars(&$pathParts, $config)	{
		if (is_array($config))	{

				// Pulling vars of the pathParts
			$GET_string = $this->decodeSpURL_getSequence($pathParts,$config);

				// If a get string is created, then:
			if ($GET_string)	{
				parse_str($GET_string,$GET_VARS);
				return $GET_VARS;
			}
		}
	}

	/**
	 * Analysing the path AFTER the page identification part of the URL
	 *
	 * @param	array		The path splitted by "/". NOTICE: Passed by reference and shortend for each time a segment is matching configuration
	 * @param	array		$postVarSetCfg config
	 * @return	array		GET-vars resulting from the analysis
	 * @see decodeSpURL_doDecode(), encodeSpURL_gettingPostVarSets()
	 */
	function decodeSpURL_settingPostVarSets(&$pathParts, $postVarSetCfg)	{
		if (is_array($postVarSetCfg))	{
			$GET_string = '';

				// Getting first value, the key (and keep stripping of sets of segments until the end is reached!)
			while($key = array_shift($pathParts))	{
				$key = rawurldecode($key);
				if (is_array($postVarSetCfg[$key]))	{
					switch((string)$postVarSetCfg[$key]['type'])	{
						case 'admin':
							$this->decodeSpURL_jumpAdmin();
						break;
						case 'single':	//
							$GET_string.= $this->decodeSpURL_getSingle($postVarSetCfg[$key]['keyValues']);
						break;
						default:
							unset($postVarSetCfg[$key]['type']);	// Just to make sure it is not set!
							$GET_string.= $this->decodeSpURL_getSequence($pathParts, $postVarSetCfg[$key]);
						break;
					}
				} else {
					$this->decodeSpURL_throw404('Segment "'.$key.'" was not a keyword for a postVarSet as expected!');
				}
			}

				// If a get string is created, then:
			if ($GET_string)	{
				parse_str($GET_string,$GET_VARS);
				return $GET_VARS;
			}
		}
	}

	/**
	 * Analysing the filename segment
	 *
	 * @param	string		Filename
	 * @return	array		GET-vars resulting from the analysis
	 * @see decodeSpURL_doDecode(), encodeSpURL_fileName()
	 */
	function decodeSpURL_fileName($fileName)	{

			// Create basic GET string:
		$fileName = rawurldecode($fileName);
		$idx = isset($this->extConf['fileName']['index'][$fileName]) ? $fileName : '_DEFAULT';
		$GET_string = $this->decodeSpURL_getSingle($this->extConf['fileName']['index'][$idx]['keyValues']);

			// If a get string is created, then:
		if ($GET_string)	{
			parse_str($GET_string,$GET_VARS);
			return $GET_VARS;
		}
	}

	/**
	 * Pulling variables of the path parts
	 *
	 * @param	array		Parts of path. NOTICE: Passed by reference.
	 * @param	array		Setup array for segments in set.
	 * @return	string		GET parameter string
	 * @see decodeSpURL_settingPreVars(), decodeSpURL_settingPostVarSets()
	 */
	function decodeSpURL_getSequence(&$pathParts,$setupArr)	{

		$GET_string='';
		$prevVal = '';
		foreach($setupArr as $setup)	{

				// Get value and remove from path parts:
			$value = $origValue = array_shift($pathParts);
			$value = rawurldecode($value);

			switch($setup['type'])	{
				case 'action':
						// Find index key:
					$idx = isset($setup['index'][$value]) ? $value : '_DEFAULT';

						// Look up type:
					switch((string)$setup['index'][$idx]['type'])	{
						case 'redirect':
							$url = (string)$setup['index'][$idx]['url'];
							$url = str_replace('###INDEX###', rawurlencode($value), $url);
							$pathParts[] = $this->filePart;
							$url = str_replace('###REMAIN_PATH###', rawurlencode(rawurldecode(implode('/',$pathParts))), $url);

							header('Location: '.t3lib_div::locationHeaderUrl($url));
							exit;
						break;
						case 'admin':
							$this->decodeSpURL_jumpAdmin();
						break;
						case 'notfound':
							$this->decodeSpURL_throw404('A required value from "'.@implode(',',@array_keys($setup['match'])).'" of path was not matching "'.$value.'" which was actually found.');
						break;
						case 'bypass':
							array_unshift($pathParts,$origValue);
						break;
					}
				break;
				default:
					if (!isset($setup['condPrevValue']) || !strcmp($prevVal,$setup['condPrevValue']))	{

							// Map value if applicable:
						if (isset($setup['valueMap'][$value]))	{
							$value = $setup['valueMap'][$value];
						} elseif ($setup['noMatch']=='bypass')	{	// If no match and "bypass" is set, then return the value to $pathParts and break
							array_unshift($pathParts,$origValue);
							break;
						} elseif ($setup['noMatch']=='null')	{	// If no match and "null" is set, then break (without setting any value!)
							break;
						} elseif (is_array($setup['lookUpTable']))	{
							$value = $this->lookUpTranslation($setup['lookUpTable'],$value,TRUE);
						} elseif (isset($setup['valueDefault']))	{	// If no matching value and a default value is given, set that:
							$value = $setup['valueDefault'];
						}

							// Set previous value:
						$prevVal = $value;

							// Add to GET string:
						if ($setup['GETvar'])	{
							$GET_string.= '&'.rawurlencode($setup['GETvar']).'='.rawurlencode($value);
						}
					} else {
						array_unshift($pathParts,$origValue);
						break;
					}
				break;
			}
		}

		return $GET_string;
	}

	/**
	 * Traverses incoming array of GET-var => value pairs and implodes that to a string of GET parameters
	 *
	 * @param	array		Parameters
	 * @return	string		GET parameters
	 * @see decodeSpURL_fileName(), decodeSpURL_settingPostVarSets(), encodeSpURL_setSingle()
	 */
	function decodeSpURL_getSingle($keyValues)	{
		$GET_string = '';
		if (is_array($keyValues))	{
			foreach($keyValues as $kkey => $vval)	{
				$GET_string.= '&'.rawurlencode($kkey).'='.rawurlencode($vval);
			}
		}
		return $GET_string;
	}

	/**
	 * Throws a 404 message.
	 * Currently it just "die()s"
	 *
	 * @param	string		Message string
	 * @return	void
	 */
	function decodeSpURL_throw404($msg)	{
		die($msg);
	}

	/**
	 * This function either a) jumps to the Backend Login page with redirect URL to current page (that is if no BE-login is currently found) or b) it enables edit icons on the page
	 *
	 * @return	void
	 */
	function decodeSpURL_jumpAdmin()	{
		if ($this->pObj->beUserLogin && is_object($GLOBALS['BE_USER']))	{
			if ($GLOBALS['BE_USER']->extAdmEnabled)	{
    			$GLOBALS['TSFE']->displayFieldEditIcons = 1;
				$GLOBALS['BE_USER']->uc['TSFE_adminConfig']['edit_editNoPopup'] = 1;

				$GLOBALS['TSFE']->applicationData['tx_realurl']['adminJumpActive'] = 1;
				$GLOBALS['TSFE']->set_no_cache();
			}
		} else {
			$adminUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL').TYPO3_mainDir.'index.php?redirect_url='.rawurlencode(t3lib_div::getIndpEnv('REQUEST_URI'));
			header('Location: '.t3lib_div::locationHeaderUrl($adminUrl));
			exit;
		}
	}

	/**
	 * Manages caching of URLs to be decoded.
	 *
	 * @param	string		Speaking URL path to be decoded
	 * @param	array		Optional; If supplied array then this array is stored as the cached information for the input $speakingURIpath. If this argument is not set the method tries to look up such an array associated with input speakingURIpath
	 * @return	mixed		Returns array with cached information related to $speakingURIpath (unless $cachedInfo is an array in which case it is stored back to database).
	 */
	function decodeSpURL_decodeCache($speakingURIpath,$cachedInfo='')	{
		#debug(array($speakingURIpath));
	}








	/*******************************
	 *
	 * General helper functions (both decode/encode)
	 *
	 ******************************/

	/**
	 * Doing database lookup between "alias values" and "id numbers". Translation is bi-directional.
	 *
	 * @param	array		Configuration of look-up table, field names etc.
	 * @param	string		Value to match field in database to.
	 * @param	boolean		If TRUE, the input $value is an alias-string that needs translation to an ID integer. FALSE (default) means the reverse direction
	 * @return	string		Result value of lookup. If no value was found the $value is returned.
	 */
	function lookUpTranslation($cfg,$value,$aliasToUid=FALSE)	{
		global $TCA;

		if ($aliasToUid)	{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						$cfg['id_field'],
						$cfg['table'],
						$cfg['alias_field'].'="'.$GLOBALS['TYPO3_DB']->quoteStr($value,$cfg['table']).'" '.$cfg['addWhereClause']
					);
			if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				return $row[$cfg['id_field']];
			}
		} else {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						$cfg['alias_field'],
						$cfg['table'],
						$cfg['id_field'].'="'.$GLOBALS['TYPO3_DB']->quoteStr($value,$cfg['table']).'" '.$cfg['addWhereClause']
					);
			if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$mLength = $cfg['maxLength'] ? $cfg['maxLength'] : $this->maxLookUpLgd;
				if (strlen($row[$cfg['alias_field']]) <= $mLength)	{
					return $row[$cfg['alias_field']];
				} else {
					return $value;
				}
			}
		}

		return $value;
	}

	/**
	 * Sets configuration in $this->extConf, taking host domain into account
	 *
	 * @return	void
	 * @see encodeSpURL(), decodeSpURL()
	 */
	function setConfig()	{

			// Finding host-name / IP, always in lowercase:
		$host = strtolower(t3lib_div::getIndpEnv('TYPO3_HOST_ONLY'));

			// First pass, finding configuration OR pointer string:
		$this->extConf = isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$host]) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$host] : $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT'];

			// If it turned out to be a string pointer, then look up the real config:
		if (is_string($this->extConf))	{
			$this->extConf = is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$this->extConf]) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$this->extConf] : $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT'];
		}
	}

	/**
	 * Returns configuration for a postVarSet (default) based on input page id
	 *
	 * @param	integer		Page id
	 * @param	string		Main key in realurl configuration array. Default is "postVarSets" but could be "fixedPostVars"
	 * @return	array		Configuration array
	 * @see decodeSpURL_doDecode()
	 */
	function getPostVarSetConfig($page_id, $mainCat='postVarSets')	{

			// If the page id is NOT an integer, it's an alias we have to look up:
		if (!t3lib_div::testInt($page_id))	{
			$page_id = $this->pageAliasToID($page_id);
		}

			// Checking if the value is not an array but a pointer to another key:
		if (isset($this->extConf[$mainCat][$page_id]) && !is_array($this->extConf[$mainCat][$page_id]))	{
			$page_id = $this->extConf[$mainCat][$page_id];
		}

		return is_array($this->extConf[$mainCat][$page_id]) ? $this->extConf[$mainCat][$page_id] : $this->extConf[$mainCat]['_DEFAULT'];
	}

	/**
	 * Page alias-to-id translation including memory caching.
	 *
	 * @param	string		Page Alias string
	 * @return	integer		Page id, zero if none was found.
	 */
	function pageAliasToID($alias)	{

			// Look in memory cache first, and if not there, look it up:
		if (!isset($GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE_aliases'][$page_id]))	{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid','pages','alias="'.$GLOBALS['TYPO3_DB']->quoteStr($page_id, 'pages').'" AND NOT pages.deleted');
			$pageRec = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE_aliases'][$page_id] = intval($pageRec['uid']);
		}

			// Return ID:
		return $GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE_aliases'][$page_id];
	}

	/**
	 * Rawurlencodes the input string; used for GET parameter names of variables that were NOT SpURL encoded. Offers the possibility of NOT encoding them...
	 *
	 * @param	string		Input string
	 * @return	string		Output string
	 * @see encodeSpURL()
	 */
	function rawurlencodeParam($str)	{
		if (!$this->extConf['init']['doNotRawUrlEncodeParameterNames'])	{
			return rawurlencode($str);
		} else return $str;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl.php']);
}
?>
