<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005 Kasper Skårhøj
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
 *
 * @coauthor	Kasper Skaarhoj <kasper@typo3.com>
 */





/**
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @package realurl
 * @subpackage tx_realurl
 */
class tx_realurl_userfunctest  {
	function main($params, $ref)	{

		if ($params['decodeAlias'])	{
			return $this->alias2id($params['value']);
		} else {
			return $this->id2alias($params['value']);
		}
	}
	function id2alias($value)	{
		return '--'.$value.'--';
	}
	function alias2id($value)	{
		$reg = array();
		if (preg_match('/^--([0-9]+)--$/',$value,$reg))	{
			return $reg[1];
		}
		return null;
	}
}

?>