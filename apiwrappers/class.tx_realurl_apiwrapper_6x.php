<?php

use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Backend\Utility\BackendUtility;

class tx_realurl_apiwrapper_6x extends tx_realurl_apiwrapper implements \TYPO3\CMS\Core\SingletonInterface {

	/** @var \TYPO3\CMS\Core\Charset\CharsetConverter $csConvObj */
	protected $csConvObj;

	public function __construct() {
		/** @noinspection PhpUndefinedClassInspection */
		$this->csConv = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Charset\\CharsetConverter');
	}

	/**
	 * @param $userFunc
	 * @param $parameters
	 * @param $object
	 * @return mixed
	 * @see t3lib_div::callUserFunction
	 */
	public function callUserFunction($userFunc, &$parameters, $object) {
		/** @noinspection PhpUndefinedClassInspection */
		return GeneralUtility::callUserFunction($userFunc, $parameters, $object);
	}

	/**
	 * @param string $cHashParameters
	 * @return string
	 */
	public function getRelevantChashParameters($cHashParameters) {
		$cacheHashCalculator = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\CacheHashCalculator');
		/* @var \TYPO3\CMS\Frontend\Page\CacheHashCalculator $cacheHashCalculator */

		$result = $cacheHashCalculator->getRelevantParameters($cHashParameters);

		return $result;
	}

	/**
	 * @param array $cHashParameters
	 * @return string
	 */
	public function calculateChash(array $cHashParameters) {
		$cacheHashCalculator = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\CacheHashCalculator');
		/* @var \TYPO3\CMS\Frontend\Page\CacheHashCalculator $cacheHashCalculator */

		$result = $cacheHashCalculator->calculateCacheHash($cHashParameters);

		return $result;
	}

	/**
	 * @param $delimiter
	 * @param $string
	 * @param bool $removeEmptyValues
	 * @param int $limit
	 * @return array
	 * @see t3lib_div::trimExplode
	 */
	public function trimExplode($delimiter, $string, $removeEmptyValues = FALSE, $limit = 0) {
		return GeneralUtility::trimExplode($delimiter, $string, $removeEmptyValues, $limit);
	}

	/**
	 * @param $path
	 * @return mixed
	 * @seee t3lib_div::locationHeaderUrl()
	 */
	public function locationHeaderUrl($path) {
		return GeneralUtility::locationHeaderUrl($path);
	}

	/**
	 * @param $fileref
	 * @return array
	 * @see t3lib_div::split_fileref
	 */
	public function split_fileref($fileref) {
		return GeneralUtility::split_fileref($fileref);
	}

	/**
	 * @param $str
	 * @return int
	 * @see t3lib_div::md5int()
	 */
	public function md5int($str) {
		return GeneralUtility::md5int($str);
	}

	/*
	* @param string $getEnvName Name of the "environment variable"/"server variable" you wish to use. Valid values are SCRIPT_NAME, SCRIPT_FILENAME, REQUEST_URI, PATH_INFO, REMOTE_ADDR, REMOTE_HOST, HTTP_REFERER, HTTP_HOST, HTTP_USER_AGENT, HTTP_ACCEPT_LANGUAGE, QUERY_STRING, TYPO3_DOCUMENT_ROOT, TYPO3_HOST_ONLY, TYPO3_HOST_ONLY, TYPO3_REQUEST_HOST, TYPO3_REQUEST_URL, TYPO3_REQUEST_SCRIPT, TYPO3_REQUEST_DIR, TYPO3_SITE_URL, _ARRAY
	* @return string Value based on the input key, independent of server/os environment.
	* @throws \UnexpectedValueException
	 * @see t3lib_div::getIndpEnv()
	*/
	public function getIndpEnv($getEnvName) {
		return GeneralUtility::getIndpEnv($getEnvName);
	}

	/**
	 * @param string $name Name prefix for entries. Set to blank if you wish none.
	 * @param array $theArray The (multidimensional) array to implode
	 * @param string $str (keep blank)
	 * @param bool $skipBlank If set, parameters which were blank strings would be removed.
	 * @param bool $rawurlencodeParamName If set, the param name itself (for example "param[key][key2]") would be rawurlencoded as well.
	 * @return string Imploded result, fx. &param[key][key2]=value2&param[key][key3]=value3
	 * @see t3lib_div::implodeArrayForUrl();
	 */
	public function implodeArrayForUrl($name, array $theArray, $str = '', $skipBlank = FALSE, $rawurlencodeParamName = FALSE) {
		return GeneralUtility::implodeArrayForUrl($name, $theArray, $str, $skipBlank, $rawurlencodeParamName);
	}

	/**
	 * @param $varName
	 * @return mixed
	 * @see t3lib_div::_GET()
	 */
	public function _GET($varName) {
		return GeneralUtility::_GET($varName);
	}

	/**
	 * @param $varName
	 * @return mixed
	 * @see t3lib_div::_POST()
	 */
	public function _POST($varName) {
		return GeneralUtility::_POST($varName);
	}

	/**
	 * @param $varName
	 * @return mixed
	 * @see t3lib_div::_GET()
	 */
	public function _GP($varName) {
		return GeneralUtility::_GP($varName);
	}

	/**
	 * @param array $theArray Multidimensional input array, (REFERENCE!)
	 * @return array
	 */
	public function stripSlashesOnArray(array &$theArray) {
		return GeneralUtility::stripSlashesOnArray($theArray);
	}

	/**
	 * @param string $delimiter Delimiter string to explode with
	 * @param string $string The string to explode
	 * @param int $count Number of array entries
	 * @return array Exploded values
	 */
	public function revExplode($delimiter, $string, $count = 0) {
		return GeneralUtility::revExplode($delimiter, $string, $count);
	}

	/**
	 * @param string $list Comma-separated list of items (string)
	 * @param string $item Item to check for
	 * @return bool TRUE if $item is in $list
	 */
	public function inList($list, $item) {
		return GeneralUtility::inList($list, $item);
	}

	/**
	 * @param $charset
	 * @param $string
	 * @return mixed
	 * @see t3lib_cs::strlen()
	 */
	public function strlen($charset, $string) {
		$this->csConv->strlen($charset, $string);
	}

	/**
	 * @param string $charset The character set
	 * @param string $string Character string
	 * @param int $len Length (in characters)
	 * @param string $crop Crop signifier
	 * @return string The shortened string
	 * @see substr(), mb_strimwidth()
	 */
	public function crop($charset, $string, $len, $crop = '') {
		return $this->csConv->crop($charset, $string, $len, $crop);
	}

	/**
	 * @param string $input Input string to be md5-hashed
	 * @param int $len The string-length of the output
	 * @return string Substring of the resulting md5-hash, being $len chars long (from beginning)
	 */
	public function shortMD5($input, $len = 10) {
		return GeneralUtility::shortMD5($input, $len);
	}

	/**
	 * @param string $className name of the class to instantiate, must not be empty
	 * @return object the created instance
	 */
	public function makeInstance($className) {
		return call_user_func_array(array('TYPO3\\CMS\\Core\\Utility\\GeneralUtility', 'makeInstance'), func_get_args());
	}

	/**
	 * @param string $msg Message (in english).
	 * @param string $extKey Extension key (from which extension you are calling the log)
	 * @param int $severity Severity: 0 is info, 1 is notice, 2 is warning, 3 is fatal error, -1 is "OK" message
	 * @param mixed $dataVar Additional data you want to pass to the logger.
	 * @return void
	 */
	public function devLog($msg, $extKey, $severity = 0, $dataVar = FALSE) {
		GeneralUtility::devLog($msg, $extKey, $severity, $dataVar);
	}

	/**
	 * @param mixed $value
	 * @return bool
	 */
	public function testInt($value) {
		return \TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($value);
	}

	/**
	 * @param array $array1
	 * @param array $array2
	 * @return array
	 */
	public function array_merge_recursive_overrule($array1, $array2) {
		if (version_compare(TYPO3_branch, '6.2', '<')) {
			$array1 = GeneralUtility::array_merge_recursive_overrule($array1, $array2);
		} else {
			\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($array1, $array2);
		}
		return $array1;
	}

	public function isExtLoaded($extKey) {
		return \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($extKey);
	}

	/**
	 * @param string $msg Message (in English).
	 * @param string $extKey Extension key (from which extension you are calling the log) or "Core
	 * @param int $severity \TYPO3\CMS\Core\Utility\GeneralUtility::SYSLOG_SEVERITY_* constant
	 * @return void
	 */
	public function sysLog($msg, $extKey, $severity = 0) {
		GeneralUtility::sysLog($msg, $extKey, $severity);
	}

	/**
	 * @param string $in_list Accept multiple parameters which can be comma-separated lists of values and arrays.
	 * @param mixed $secondParameter Dummy field, which if set will show a warning!
	 * @return string Returns the list without any duplicates of values, space around values are trimmed
	 */
	public function uniqueList($in_list, $secondParameter = NULL) {
		return GeneralUtility::uniqueList($in_list, $secondParameter);
	}

	/**
	 * @return t3lib_pageSelect|\TYPO3\CMS\Frontend\Page\PageRepository
	 */
	public function getPageRepository() {
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
	}

	/**
	 * @param array $arr1 First array
	 * @param array $arr2 Second array
	 * @return array Merged result.
	 */
	public function array_merge(array $arr1, array $arr2) {
		return GeneralUtility::array_merge($arr1, $arr2);
	}


	/**
	 * @param array $modTSconfig Module TS config array
	 * @param array $itemArray Array of items from which to remove items.
	 * @param string $TSref $TSref points to the "object string" in $modTSconfig
	 * @return array The modified $itemArray is returned.
	 */
	public function unsetMenuItems($modTSconfig, $itemArray, $TSref) {
		return BackendUtility::unsetMenuItems($modTSconfig, $itemArray, $TSref);
	}

	/**
	 * @param mixed $mainParams The "&id=" parameter value to be sent to the module, but it can be also a parameter array which will be passed instead of the &id=...
	 * @param string $elementName The form elements name, probably something like "SET[...]
	 * @param string $currentValue The value to be selected currently.
	 * @param array	 $menuItems An array with the menu items for the selector box
	 * @param string $script The script to send the &id to, if empty it's automatically found
	 * @param string $addParams Additional parameters to pass to the script.
	 * @return string HTML code for selector box
	 */
	public function getFuncMenu($mainParams, $elementName, $currentValue, $menuItems, $script = '', $addParams = '') {
		/** @noinspection PhpDeprecationInspection PhpUndefinedClassInspection */
		return BackendUtility::getFuncMenu($mainParams, $elementName, $currentValue, $menuItems, $script, $addParams);
	}

	/**
	 * @return \TYPO3\CMS\Backend\Tree\View\PageTreeView
	 */
	public function getPageTree() {
		/** @noinspection PhpDeprecationInspection PhpUndefinedClassInspection */
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Tree\\View\\PageTreeView');
	}

	/**
	 * @param string $table Table name present in $GLOBALS['TCA']
	 * @param int $uid UID of record
	 * @param string $fields List of fields to select
	 * @param string $where Additional WHERE clause, eg. " AND blablabla = 0
	 * @param bool $useDeleteClause Use the deleteClause to check if a record is deleted (default TRUE)
	 * @return array|NULL Returns the row if found, otherwise NULL
	 */
	public function getRecord($table, $uid, $fields = '*', $where = '', $useDeleteClause = TRUE) {
		return BackendUtility::getRecord($table, $uid, $fields, $where, $useDeleteClause);
	}

	/**
	 * @param string $table Table name
	 * @param array $row Record array passed by reference. As minimum, the "uid" and  "pid" fields must exist! Fake fields cannot exist since the fields in the array is used as field names in the SQL look up. It would be nice to have fields like "t3ver_state" and "t3ver_mode_id" as well to avoid a new lookup inside movePlhOL().
	 * @param int $wsid Workspace ID, if not specified will use static::getBackendUserAuthentication()->workspace
	 * @param bool $unsetMovePointers If TRUE the function does not return a "pointer" row for moved records in a workspace
	 * @return void
	 * @see fixVersioningPid()
	 */
	public function workspaceOL($table, &$row, $wsid = -99, $unsetMovePointers = FALSE) {
		BackendUtility::workspaceOL($table, $row, $wsid, $unsetMovePointers);
	}

	/**
	 * @param string $table Table name, present in TCA
	 * @param array $row Row from table
	 * @param bool $prep If set, result is prepared for output: The output is cropped to a limited length (depending on BE_USER->uc['titleLen']) and if no value is found for the title, '<em>[No title]</em>' is returned (localized). Further, the output is htmlspecialchars()'ed
	 * @param bool $forceResult If set, the function always returns an output. If no value is found for the title, '[No title]' is returned (localized).
	 * @return string
	 */
	public function getRecordTitle($table, $row, $prep = FALSE, $forceResult = TRUE) {
		return BackendUtility::getRecordTitle($table, $row, $prep, $forceResult);
	}

	/**
	 * @param string $theTable Table name present in $GLOBALS['TCA']
	 * @param string $theField Field to select on
	 * @param string $theValue Value that $theField must match
	 * @param string $whereClause Optional additional WHERE clauses put in the end of the query. DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @param bool $useDeleteClause Use the deleteClause to check if a record is deleted (default TRUE)
	 * @return mixed Multidimensional array with selected records (if any is selected)
	 */
	public function getRecordsByField($theTable, $theField, $theValue, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '', $useDeleteClause = TRUE) {
		return BackendUtility::getRecordsByField($theTable, $theField, $theValue, $whereClause, $groupBy, $orderBy, $limit, $useDeleteClause);
	}

	/**
	 * Returns a JavaScript string (for an onClick handler) which will load the alt_doc.php script that shows the form for editing of the record(s) you have send as params.
	 * REMEMBER to always htmlspecialchar() content in href-properties to ampersands get converted to entities (XHTML requirement and XSS precaution)
	 *
	 * @param string $params Parameters sent along to alt_doc.php. This requires a much more details description which you must seek in Inside TYPO3s documentation of the alt_doc.php API. And example could be '&edit[pages][123] = edit' which will show edit form for page record 123.
	 * @param string $backPath Must point back to the TYPO3_mainDir directory (where alt_doc.php is)
	 * @param string $requestUri An optional returnUrl you can set - automatically set to REQUEST_URI.
	 *
	 * @return string
	 * @see \TYPO3\CMS\Backend\Template\DocumentTemplate::issueCommand()
	 */
	public function editOnClick($params, $backPath = '', $requestUri = '') {
		return BackendUtility::editOnClick($params, $backPath, $requestUri);
	}

	/**
	 * Returns a JavaScript string for viewing the page id, $id
	 * It will detect the correct domain name if needed and provide the link with the right back path.
	 * Also it will re-use any window already open.
	 *
	 * @param int $pageUid Page UID
	 * @param string $backPath Must point back to TYPO3_mainDir (where the site is assumed to be one level above)
	 * @param array|NULL $rootLine If root line is supplied the function will look for the first found domain record and use that URL instead (if found)
	 * @param string $anchorSection Optional anchor to the URL
	 * @param string $alternativeUrl An alternative URL that, if set, will ignore other parameters except $switchFocus: It will return the window.open command wrapped around this URL!
	 * @param string $additionalGetVars Additional GET variables.
	 * @param bool $switchFocus If TRUE, then the preview window will gain the focus.
	 * @return string
	 */
	public function viewOnClick($pageUid, $backPath = '', $rootLine = NULL, $anchorSection = '', $alternativeUrl = '', $additionalGetVars = '', $switchFocus = TRUE) {
		return BackendUtility::viewOnClick($pageUid, $backPath, $rootLine, $anchorSection, $alternativeUrl, $additionalGetVars, $switchFocus);
	}

	/**
	 * @param string $str Full string to check
	 * @param string $partStr Reference string which must be found as the "first part" of the full string
	 * @return bool TRUE if $partStr was found to be equal to the first part of $str
	 */
	public function isFirstPartOfStr($str, $partStr) {
		return GeneralUtility::isFirstPartOfStr($str, $partStr);
	}

	/**
	 * @param int $tstamp Time stamp, seconds
	 * @param int $prefix 1/-1 depending on polarity of age.
	 * @param string $date $date=="date" will yield "dd:mm:yy" formatting, otherwise "dd:mm:yy hh:mm
	 * @return string
	 */
	public function dateTimeAge($tstamp, $prefix = 1, $date = '') {
		return BackendUtility::dateTimeAge($tstamp, $prefix, $date);
	}

	/**
	 * Returns the Page TSconfig for page with id, $id
	 *
	 * @param int $id Page uid for which to create Page TSconfig
	 * @param array $rootLine If $rootLine is an array, that is used as rootline, otherwise rootline is just calculated
	 * @param bool $returnPartArray If $returnPartArray is set, then the array with accumulated Page TSconfig is returned non-parsed. Otherwise the output will be parsed by the TypoScript parser.
	 * @return array Page TSconfig
	 * @see \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser
	 */
	public function getPagesTSconfig($id, $rootLine = NULL, $returnPartArray = FALSE) {
		return BackendUtility::getPagesTSconfig($id, $rootLine, $returnPartArray);
	}

	/**
	 * @param int $value Time stamp, seconds
	 * @return string Formatted time
	 */
	public function datetime($value) {
		return BackendUtility::datetime($value);
	}

	/**
	 * @param int $seconds Seconds could be the difference of a certain timestamp and time()
	 * @param string $labels Labels should be something like ' min| hrs| days| yrs| min| hour| day| year'. This value is typically delivered by this function call: $GLOBALS["LANG"]->sL("LLL:EXT:lang/locallang_core.xlf:labels.minutesHoursDaysYears")
	 * @return string Formatted time
	 */
	public function calcAge($seconds, $labels = ' min| hrs| days| yrs| min| hour| day| year') {
		return BackendUtility::datetime($seconds, $labels);
	}

	/**
	 * @return t3lib_arrayBrowser|\TYPO3\CMS\Lowlevel\Utility\ArrayBrowser
	 */
	public function getArrayBrowser() {
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Lowlevel\\Utility\\ArrayBrowser');
	}

	/**
	 * @param int $id Page uid
	 * @param string $TSref An object string which determines the path of the TSconfig to return.
	 * @return array
	 */
	public function getModTSconfig($id, $TSref) {
		return BackendUtility::getModTSconfig($id, $TSref);
	}

	/**
	 * @param string $backPath Current backpath to PATH_typo3 folder
	 * @param string $src Icon file name relative to PATH_typo3 folder
	 * @param string $wHattribs Default width/height, defined like 'width="12" height="14"'
	 * @param int $outputMode Mode: 0 (zero) is default and returns src/width/height. 1 returns value of src+backpath, 2 returns value of w/h.
	 * @return string Returns ' src="[backPath][src]" [wHattribs]'
	 * @see skinImgFile()
	 */
	public function skinImg($backPath, $src, $wHattribs = '', $outputMode = 0) {
		return \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($backPath, $src, $wHattribs, $outputMode);
	}

	/**
	 * Converts a one dimensional array to a one line string which can be used for logging or debugging output
	 * Example: "loginType: FE; refInfo: Array; HTTP_HOST: www.example.org; REMOTE_ADDR: 192.168.1.5; REMOTE_HOST:; security_level:; showHiddenRecords: 0;"
	 *
	 * @param array $arr Data array which should be outputted
	 * @param mixed $valueList List of keys which should be listed in the output string. Pass a comma list or an array. An empty list outputs the whole array.
	 * @param int $valueLength Long string values are shortened to this length. Default: 20
	 * @return string Output string with key names and their value as string
	 */
	public function arrayToLogString(array $arr, $valueList = array(), $valueLength = 20) {
		return GeneralUtility::arrayToLogString($arr, $valueLength, $valueLength);
	}

	/**
	 * Truncates a string with appended/prepended "..." and takes current character set into consideration.
	 *
	 * @param string $string String to truncate
	 * @param int $chars Must be an integer with an absolute value of at least 4. if negative the string is cropped from the right end.
	 * @param string $appendString Appendix to the truncated string
	 * @return string Cropped string
	 */
	public function fixed_lgd_cs($string, $chars, $appendString = '...') {
		return GeneralUtility::fixed_lgd_cs($string, $chars, $appendString);
	}

	/**
	 * Returns an icon image tag, 18x16 pixels, based on input information.
	 * This function is recommended to use in your backend modules.
	 * Usage: 60
	 *
	 * @param	string		The table name
	 * @param	array		The table row ("enablefields" are at least needed for correct icon display and for pages records some more fields in addition!)
	 * @param	string		The backpath to the main TYPO3 directory (relative path back to PATH_typo3)
	 * @param	string		Additional attributes for the image tag
	 * @param	boolean		If set, the icon will be grayed/shaded
	 * @return	string		<img>-tag
	 * @see getIcon()
	 */
	public function getIconImage($table, $row = array(), $backPath, $params = '', $shaded = FALSE) {
		$str = '<img' .
			   $this->skinImg($backPath, \TYPO3\CMS\Backend\Utility\IconUtility::getIcon($table, $row, $shaded), 'width="18" height="16"') .
			   (trim($params) ? ' ' . trim($params) : '');
		if (!stristr($str, 'alt="')) {
			$str .= ' alt=""';
		}
		$str .= ' />';
		return $str;
	}

	/**
	 * @param int $uid
	 * @param string $clause
	 * @param boolean $workspaceOL
	 * @return array
	 */
	public function BEgetRootLine($uid, $clause = '', $workspaceOL = FALSE) {
		return BackendUtility::BEgetRootLine($uid, $clause, $workspaceOL);
	}

	/**
	 * Makes the page tree class instance.
	 *
	 * @return t3lib_pageTree|\TYPO3\CMS\Backend\Tree\View\PageTreeView
	 */
	public function makePageTreeInstance() {
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Tree\\View\\PageTreeView');
	}

	/**
	 * Obtains the lock object with a given name.
	 *
	 * @param string $lockObjectName
	 * @return t3lib_lock|\TYPO3\CMS\Core\Locking\Locker
	 */
	public function getLockObject($lockObjectName) {
		$lockObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Locking\\Locker', $lockObjectName, $GLOBALS['TYPO3_CONF_VARS']['SYS']['lockingMode']);
		/** @var t3lib_lock $lockObject */
		$lockObject->setEnableLogging(FALSE);
		$lockObject->acquireExclusiveLock();

		return $lockObject;
	}

	/**
	 * Sets the file system mode and group ownership of a file or a folder.
	 *
	 * @param string $path Path of file or folder, must not be escaped. Path can be absolute or relative
	 * @param bool $recursive If set, also fixes permissions of files and folders in the folder (if $path is a folder)
	 * @return mixed TRUE on success, FALSE on error, always TRUE on Windows OS
	 */
	public function fixPermissions($path, $recursive = FALSE) {
		return GeneralUtility::fixPermissions($path, $recursive);
	}

	/**
	 * Creates a database connection if it is not exist.
	 *
	 * @return NULL|t3lib_db|\TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	public function getDatabaseConnection() {
		$db = NULL;
		if (!isset($GLOBALS['TYPO3_DB'])) {
			if (TYPO3_db)	{
				$db = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\DatabaseConnection');
				if (!$db->sql_pconnect(TYPO3_db_host, TYPO3_db_username, TYPO3_db_password) || !$db->sql_select_db(TYPO3_db)) {
					// Cannot connect to database
					$db = NULL;
				}
			}
		}
		else {
			$db = &$GLOBALS['TYPO3_DB'];
		}

		return $db;
	}
}
