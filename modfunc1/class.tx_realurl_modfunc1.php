<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004-2005 Kasper Skaarhoj (kasperYYYY@typo3.com)
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
 * Speaking Url management extension
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   78: class tx_realurl_modfunc1 extends t3lib_extobjbase
 *   89:     function modMenu()
 *  113:     function main()
 *
 *              SECTION: Path Cache rendering:
 *  215:     function renderModule($tree)
 *  435:     function getPathCache($pageId)
 *  471:     function linkSelf($addParams)
 *  480:     function renderSearchForm()
 *  524:     function deletePathCacheEntry($cache_id)
 *  535:     function editPathCacheEntry($cache_id,$value)
 *  547:     function edit_save()
 *  562:     function saveCancelButtons($extra='')
 *
 *              SECTION: Decode view
 *  593:     function decodeView($tree)
 *
 *              SECTION: Encode view
 *  698:     function encodeView($tree)
 *
 *              SECTION: Unique Alias
 *  806:     function uniqueAlias()
 *  939:     function editUniqAliasEntry($cache_id,$value)
 *  951:     function edit_save_uniqAlias()
 *
 * TOTAL FUNCTIONS: 15
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(PATH_t3lib.'class.t3lib_pagetree.php');
require_once(PATH_t3lib.'class.t3lib_extobjbase.php');



/**
 * Speaking Url management extension
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage tx_realurl
 */
class tx_realurl_modfunc1 extends t3lib_extobjbase {


		// Internal, dynamic:
	var $searchResultCounter = 0;

	/**
	 * Returns the menu array
	 *
	 * @return	array
	 */
	function modMenu()	{
		global $LANG;

		return array (
			'depth' => array(
				0 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_0'),
				1 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_1'),
				2 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_2'),
				3 => $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.depth_3'),
			),
			'type' => array(
				'pathcache' => 'ID-to-path mapping',
				'decode' => 'Decode cache',
				'encode' => 'Encode cache',
				'uniqalias' => 'Unique Aliases',
			)
		);
	}

	/**
	 * MAIN function for cache information
	 *
	 * @return	string		Output HTML for the module.
	 */
	function main()	{
		global $BACK_PATH,$LANG,$SOBE;

		if ($this->pObj->id)	{
			$theOutput = '';

				// Depth selector:
			$h_func = t3lib_BEfunc::getFuncMenu($this->pObj->id,'SET[type]',$this->pObj->MOD_SETTINGS['type'],$this->pObj->MOD_MENU['type'],'index.php').'<br/>';
			if ($this->pObj->MOD_SETTINGS['type']!='uniqalias')	{
				$h_func.= t3lib_BEfunc::getFuncMenu($this->pObj->id,'SET[depth]',$this->pObj->MOD_SETTINGS['depth'],$this->pObj->MOD_MENU['depth'],'index.php');
			}
			$theOutput.= $h_func;

			if ($this->pObj->MOD_SETTINGS['type']!='uniqalias')	{
					// Showing the tree:
					// Initialize starting point of page tree:
				$treeStartingPoint = intval($this->pObj->id);
				$treeStartingRecord = t3lib_BEfunc::getRecord('pages', $treeStartingPoint);
				$depth = $this->pObj->MOD_SETTINGS['depth'];

					// Initialize tree object:
				$tree = t3lib_div::makeInstance('t3lib_pageTree');
				$tree->addField('nav_title',1);
				$tree->addField('alias',1);
				$tree->addField('tx_realurl_pathsegment',1);
				$tree->init('AND '.$GLOBALS['BE_USER']->getPagePermsClause(1));

					// Creating top icon; the current page
				$HTML = t3lib_iconWorks::getIconImage('pages', $treeStartingRecord, $GLOBALS['BACK_PATH'],'align="top"');
				$tree->tree[] = array(
					'row' => $treeStartingRecord,
					'HTML' => $HTML
				);

					// Create the tree from starting point:
				if ($depth>0)	{
					$tree->getTree($treeStartingPoint, $depth, '');
				}
			}

				// Add CSS needed:
			$this->pObj->content = str_replace('/*###POSTCSSMARKER###*/','
				TABLE.c-list TR TD { white-space: nowrap; vertical-align: top; }
				TABLE#tx-realurl-pathcacheTable TD { vertical-align: top; }
			',$this->pObj->content);


				// Branching:
			switch($this->pObj->MOD_SETTINGS['type'])	{
				case 'pathcache':

						// Save editing if any:
					$this->edit_save();

						// Render information table:
					$treeHTML = $this->renderModule($tree);

						// Render Search Form:
					$theOutput.= $this->renderSearchForm();

						// Add tree table:
					$theOutput.= $treeHTML;
				break;
				case 'encode':
					$theOutput.= $this->encodeView($tree);
				break;
				case 'decode':
					$theOutput.= $this->decodeView($tree);
				break;
				case 'uniqalias':
					$this->edit_save_uniqAlias();
					$theOutput.= $this->uniqueAlias();
				break;
			}
		}

		return $theOutput;
	}












	/****************************
	 *
	 * Path Cache rendering:
	 *
	 ****************************/

	/**
	 * Rendering the information
	 *
	 * @param	array		The Page tree data
	 * @return	string		HTML for the information table.
	 */
	function renderModule($tree)	{

			// Initialize:
		$searchPath = trim(t3lib_div::_GP('pathPrefixSearch'));
		$cmd = t3lib_div::_GET('cmd');
		$entry = t3lib_div::_GET('entry');
		$searchForm_replace = t3lib_div::_POST('_replace');
		$searchForm_delete = t3lib_div::_POST('_delete');

		$trackSameUrl = array();
		$this->searchResultCounter = 0;

			// Traverse tree:
		$output = '';
		$cc=0;
		foreach($tree->tree as $row)	{

				// Get all pagepath entries for page:
			$pathCacheInfo = $this->getPathCache($row['row']['uid']);

				// Row title:
			$rowTitle = $row['HTML'].t3lib_BEfunc::getRecordTitle('pages',$row['row'],TRUE);

				// Add at least one empty element:
			if (!count($pathCacheInfo))	{

						// Add title:
					$tCells = array();
					$tCells[]='<td nowrap="nowrap">'.$rowTitle.'</td>';

						// Empty row:
					$tCells[]='<td colspan="9" align="center">&nbsp;</td>';

						// Compile Row:
					$output.= '
						<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
							'.implode('
							',$tCells).'
						</tr>';
					$cc++;
			} else {
				foreach($pathCacheInfo as $c => $inf)	{

						// Init:
					$deletedEntry = FALSE;
					$hash = $inf['pagepath'].'|'.$inf['language_id'].'|'.$inf['rootpage_id'];

						// Add icon/title and ID:
					$tCells = array();
					if (!$c)	{
						$tCells[]='<td nowrap="nowrap" rowspan="'.count($pathCacheInfo).'">'.$rowTitle.'</td>';
						$tCells[]='<td rowspan="'.count($pathCacheInfo).'">'.$inf['page_id'].'</td>';
					}

						// Add values from alternative field used to generate URL:
					$baseRow = $row['row'];	// page row as base.
					$onClick = t3lib_BEfunc::editOnClick('&edit[pages]['.$row['row']['uid'].']=edit&columnsOnly=title,nav_title,alias,tx_realurl_pathsegment',$this->pObj->doc->backPath);
					$editIcon = '<a href="#" onclick="'.htmlspecialchars($onClick).'">'.
								'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/edit2.gif','width="11" height="12"').' title="" alt="" />'.
								'</a>';
					$onClick = t3lib_BEfunc::viewOnClick($row['row']['uid'],$this->pObj->doc->backPath,'','','','');
					$editIcon.= '<a href="#" onclick="'.htmlspecialchars($onClick).'">'.
								'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/zoom.gif','width="12" height="12"').' title="" alt="" />'.
								'</a>';
					if ($inf['language_id']>0)	{	// For alternative languages, show another list of fields, form page overlay record:
						$editIcon = '';
						list($olRec) = t3lib_BEfunc::getRecordsByField('pages_language_overlay','pid',$row['row']['uid'],' AND sys_language_uid='.intval($inf['language_id']));
						if (is_array($olRec))	{
							$baseRow = array_merge($baseRow,$olRec);
							$onClick = t3lib_BEfunc::editOnClick('&edit[pages_language_overlay]['.$olRec['uid'].']=edit&columnsOnly=title,nav_title',$this->pObj->doc->backPath);
							$editIcon = '<a href="#" onclick="'.htmlspecialchars($onClick).'">'.
										'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/edit2.gif','width="11" height="12"').' title="" alt="" />'.
										'</a>';
							$onClick = t3lib_BEfunc::viewOnClick($row['row']['uid'],$this->pObj->doc->backPath,'','','','&L='.$olRec['sys_language_uid']);
							$editIcon.= '<a href="#" onclick="'.htmlspecialchars($onClick).'">'.
										'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/zoom.gif','width="12" height="12"').' title="" alt="" />'.
										'</a>';
						} else {
							$baseRow = array();
						}
					}
					$tCells[]='<td>'.$editIcon.'</td>';

						// 	Sources for segment:
					$sources = count($baseRow) ? implode(' | ',array($baseRow['tx_realurl_pathsegment'], $baseRow['alias'], $baseRow['nav_title'], $baseRow['title'])) : '';
					$tCells[]='<td nowrap="nowrap">'.htmlspecialchars($sources).'</td>';

						// Show page path:
					if (strcmp($searchPath,'') && t3lib_div::isFirstPartOfStr($inf['pagepath'],$searchPath))	{

							// Delete entry:
						if ($searchForm_delete)	{
							$this->deletePathCacheEntry($inf['cache_id']);
							$deletedEntry = TRUE;
							$pagePath = '[DELETED]';
						} elseif ($searchForm_replace) {
							$replacePart = trim(t3lib_div::_POST('pathPrefixReplace'));
							$this->editPathCacheEntry($inf['cache_id'],
								$replacePart.substr($inf['pagepath'],strlen($searchPath)));

							$pagePath =
									'<span class="typo3-red">'.
									htmlspecialchars($replacePart).
									'</span>'.
									htmlspecialchars(substr($inf['pagepath'],strlen($searchPath)));
						} else {
							$pagePath =
									'<span class="typo3-red">'.
									htmlspecialchars(substr($inf['pagepath'],0,strlen($searchPath))).
									'</span>'.
									htmlspecialchars(substr($inf['pagepath'],strlen($searchPath)));
							$this->searchResultCounter++;
						}
					} else {
							// Delete entries:
						if ($cmd==='edit' && (!strcmp($entry,$inf['cache_id']) || !strcmp($entry,'ALL')))	{
							$pagePath = '<input type="text" name="edit['.$inf['cache_id'].']" value="'.htmlspecialchars($inf['pagepath']).'" />';
							if ($cmd==='edit' && $entry!='ALL')	{
								$pagePath.= $this->saveCancelButtons();
							}

						} else {
							$pagePath = htmlspecialchars($inf['pagepath']);
						}
					}

					$tCells[]='<td>'.$pagePath.'</td>';

					if ($deletedEntry)	{
						$tCells[]='<td>&nbsp;</td>';
					} else {
						$tCells[]='<td>'.
								'<a href="'.$this->linkSelf('&cmd=delete&entry='.$inf['cache_id']).'">'.
								'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/garbage.gif','width="11" height="12"').' alt="" />'.
								'</a>'.
								'<a href="'.$this->linkSelf('&cmd=edit&entry='.$inf['cache_id']).'">'.
								'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/edit2.gif','width="11" height="12"').' title="" alt="" />'.
								'</a>'.
								'<a href="'.$this->linkSelf('&pathPrefixSearch='.rawurlencode($inf['pagepath'])).'">'.
								'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/clip_copy.gif','width="12" height="12"').' title="" alt="" />'.
								'</a>'.
								'</td>';
					}

						// Set error msg:
					if (!strcmp($inf['pagepath'],''))	{
						if ($row['row']['uid']!=$this->pObj->id)	{	// Show error of "Empty" only for levels under the root. Yes, we cannot know that the pObj->id is the true root of the site, but at least any SUB page should probably have a path string!
							$error = $this->pObj->doc->icons(2).'Empty';
						}
					} elseif (isset($trackSameUrl[$hash]))	{
						$error = $this->pObj->doc->icons(2).'Duplicate';
					} else {
						$error = '&nbsp;';
					}
					$tCells[]='<td>'.$error.'</td>';

					$tCells[]='<td>'.htmlspecialchars($inf['language_id']).'</td>';
					$tCells[]='<td>'.htmlspecialchars($inf['mpvar']).'</td>';
					$tCells[]='<td>'.htmlspecialchars($inf['rootpage_id']).'</td>';


					#$tCells[]='<td nowrap="nowrap">'.htmlspecialchars(t3lib_BEfunc::datetime($inf['expire'])).' / '.htmlspecialchars(t3lib_BEfunc::calcAge($inf['expire']-time())).'</td>';

					$trackSameUrl[$hash] = TRUE;

						// Compile Row:
					$output.= '
						<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
							'.implode('
							',$tCells).'
						</tr>';
					$cc++;
				}
			}
		}

			// Create header:
		$tCells = array();
		$tCells[]='<td>Title:</td>';
		$tCells[]='<td>ID:</td>';
		$tCells[]='<td>&nbsp;</td>';
		$tCells[]='<td>PathSegment | Alias | NavTitle | Title:</td>';
		$tCells[]='<td>Pagepath:</td>';
		$tCells[]='<td>'.
					'<a href="'.$this->linkSelf('&cmd=delete&entry=ALL').'" onclick="return confirm(\'Are you sure you want to flush all cached page paths?\');">'.
					'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/garbage.gif','width="11" height="12"').' alt="" />'.
					'</a>'.
					'<a href="'.$this->linkSelf('&cmd=edit&entry=ALL').'">'.
					'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/edit2.gif','width="11" height="12"').' title="" alt="" />'.
					'</a>'.
					'</td>';
		$tCells[]='<td>Errors:</td>';
		$tCells[]='<td>Lang:</td>';
		$tCells[]='<td>&MP:</td>';
		$tCells[]='<td>RootPage ID:</td>';
		#$tCells[]='<td>Expire:</td>';
		$output = '
			<tr class="bgColor5 tableheader">
				'.implode('
				',$tCells).'
			</tr>'.$output;

			// Compile final table and return:
		$output = '
		<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">'.$output.'
		</table>';

		if ($cmd==='edit' && $entry=='ALL')	{
			$output.= $this->saveCancelButtons();
		}

		return $output;
	}

	/**
	 * Fetch patch caching information for page.
	 *
	 * @param	integer		Page ID
	 * @return	array		Path Cache records
	 */
	function getPathCache($pageId)	{

		$showLanguage = t3lib_div::_GP('showLanguage');
		$cmd = t3lib_div::_GET('cmd');
		$entry = t3lib_div::_GET('entry');

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'*',
					'tx_realurl_pathcache',
					'page_id='.intval($pageId).
						((string)$showLanguage!=='' ? ' AND language_id='.intval($showLanguage) : ''),
					'',
					'language_id'
				);

			// Traverse result:
		$output = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{

				// Delete entries:
			if ($cmd==='delete' && (!strcmp($entry,$row['cache_id']) || !strcmp($entry,'ALL')))	{
				$this->deletePathCacheEntry($row['cache_id']);
			} else {	// ... or add:
				$output[] = $row;
			}
		}

		return $output;
	}

	/**
	 * Links to the module script and sets necessary parameters (only for pathcache display)
	 *
	 * @param	string		Additional GET vars
	 * @return	string		script + query
	 */
	function linkSelf($addParams)	{
		return htmlspecialchars('index.php?id='.$this->pObj->id.'&showLanguage='.rawurlencode(t3lib_div::_GP('showLanguage')).$addParams);
	}

	/**
	 * Create search form
	 *
	 * @return	string		HTML
	 */
	function renderSearchForm()	{

		$output.= '<br/>';
		$output.= '<br/>';

			// Language selector:
		$sys_languages = t3lib_BEfunc::getRecordsByField('sys_language','pid',0,'','','title');
		array_unshift($sys_languages,array('uid' => 0, 'title' => 'Default'));
		array_unshift($sys_languages,array('uid' => '', 'title' => 'All languages'));

		$options = array();
		$showLanguage = t3lib_div::_GP('showLanguage');
		foreach($sys_languages as $record)	{
			$options[] = '
				<option value="'.htmlspecialchars($record['uid']).'"'.(!strcmp($showLanguage,$record['uid']) ? 'selected="selected"' : '').'>'.htmlspecialchars($record['title'].' ['.$record['uid'].']').'</option>';
		}

		$output.= 'Only language: <select name="showLanguage">'.implode('', $options).'</select><br/>';

			// Search path:
		$output.= 'Path: <input type="text" name="pathPrefixSearch" value="'.htmlspecialchars(t3lib_div::_GP('pathPrefixSearch')).'" />';
		$output.= '<input type="submit" name="_" value="Look up" />';
		$output.= '<br/>';

			// Search / Replace part:
		if ($this->searchResultCounter && !t3lib_div::_POST('_replace') && !t3lib_div::_POST('_delete'))	{
			$output.= '<br/><b>'.sprintf('%s results found.',$this->searchResultCounter).'</b><br/>';
			$output.= 'Replace with: <input type="text" name="pathPrefixReplace" value="'.htmlspecialchars(t3lib_div::_GP('pathPrefixSearch')).'" />';
			$output.= '<input type="submit" name="_replace" value="Replace" /> - <input type="submit" name="_delete" value="Delete" /><br/>';
		}

			// Hidden fields:
		$output.= '<input type="hidden" name="id" value="'.htmlspecialchars($this->pObj->id).'" />';
		$output.= '<br/>';

		return $output;
	}

	/**
	 * Deletes an entry in pathcache table
	 *
	 * @param	integer		Path Cache id (cache_id)
	 * @return	void
	 */
	function deletePathCacheEntry($cache_id)	{
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_pathcache','cache_id='.intval($cache_id));
	}

	/**
	 * Changes the "pagepath" value of an entry in the pathcache table
	 *
	 * @param	integer		Path Cache id (cache_id)
	 * @param	string		New value for the pagepath
	 * @return	void
	 */
	function editPathCacheEntry($cache_id,$value)	{
		$field_values = array(
			'pagepath' => $value
		);
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_pathcache','cache_id='.intval($cache_id), $field_values);
	}

	/**
	 * Will look for submitted pagepath cache entries to save
	 *
	 * @return	void
	 */
	function edit_save()	{
		if (t3lib_div::_POST('_edit_save'))	{
			$editArray = t3lib_div::_POST('edit');
			foreach($editArray as $cache_id => $value)	{
				$this->editPathCacheEntry($cache_id,trim($value));
			}
		}
	}

	/**
	 * Save / Cancel buttons
	 *
	 * @param	string		Extra code.
	 * @return	string		Form elements
	 */
	function saveCancelButtons($extra='')	{
		$output.= '<input type="submit" name="_edit_save" value="Save" />';
		$output.= '<input type="submit" name="_edit_cancel" value="Cancel" />';
		$output.= $extra;

		return $output;
	}











	/**************************
	 *
	 * Decode view
	 *
	 **************************/


	/**
	 * Rendering the decode-cache content
	 *
	 * @param	array		The Page tree data
	 * @return	string		HTML for the information table.
	 */
	function decodeView($tree)	{

			// Traverse tree:
		$output = '';
		$cc=0;
		foreach($tree->tree as $row)	{

				// Select rows:
			$displayRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*','tx_realurl_urldecodecache','page_id='.intval($row['row']['uid']),'','spurl');

				// Row title:
			$rowTitle = $row['HTML'].t3lib_BEfunc::getRecordTitle('pages',$row['row'],TRUE);

				// Add at least one empty element:
			if (!count($displayRows))	{
						// Add title:
					$tCells = array();
					$tCells[]='<td nowrap="nowrap">'.$rowTitle.'</td>';

						// Empty row:
					$tCells[]='<td colspan="4" align="center">&nbsp;</td>';

						// Compile Row:
					$output.= '
						<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
							'.implode('
							',$tCells).'
						</tr>';
					$cc++;
			} else {
				foreach($displayRows as $c => $inf)	{

						// Add icon/title and ID:
					$tCells = array();
					if (!$c)	{
						$tCells[]='<td nowrap="nowrap" rowspan="'.count($displayRows).'">'.$rowTitle.'</td>';
					}

						// Path:
					$tCells[]='<td>'.htmlspecialchars($inf['spurl']).'</td>';

						// Get vars:
					$queryValues = unserialize($inf['content']);
					$queryParams = '?id='.$queryValues['id'].
									(is_array($queryValues['GET_VARS']) ? t3lib_div::implodeArrayForUrl('',$queryValues['GET_VARS']) : '');
					$tCells[]='<td>'.htmlspecialchars($queryParams).'</td>';

						// Timestamp:
					$tCells[]='<td>'.htmlspecialchars(t3lib_BEfunc::datetime($inf['tstamp'])).' / '.htmlspecialchars(t3lib_BEfunc::calcAge(time()-$inf['tstamp'])).'</td>';

						// Compile Row:
					$output.= '
						<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
							'.implode('
							',$tCells).'
						</tr>';
					$cc++;
				}
			}
		}

			// Create header:
		$tCells = array();
		$tCells[]='<td>Title:</td>';
		$tCells[]='<td>Path:</td>';
		$tCells[]='<td>GET variables:</td>';
		$tCells[]='<td>Timestamp:</td>';

		$output = '
			<tr class="bgColor5 tableheader">
				'.implode('
				',$tCells).'
			</tr>'.$output;

			// Compile final table and return:
		$output = '
		<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">'.$output.'
		</table>';

		return $output;
	}











	/**************************
	 *
	 * Encode view
	 *
	 **************************/


	/**
	 * Rendering the encode-cache content
	 *
	 * @param	array		The Page tree data
	 * @return	string		HTML for the information table.
	 */
	function encodeView($tree)	{

		$duplicates = array();

			// Traverse tree:
		$cc = 0;
		$output = '';
		foreach($tree->tree as $row)	{

				// Select rows:
			$displayRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*','tx_realurl_urlencodecache','page_id='.intval($row['row']['uid']),'','content');

				// Row title:
			$rowTitle = $row['HTML'].t3lib_BEfunc::getRecordTitle('pages',$row['row'],TRUE);

				// Add at least one empty element:
			if (!count($displayRows))	{
						// Add title:
					$tCells = array();
					$tCells[]='<td nowrap="nowrap">'.$rowTitle.'</td>';

						// Empty row:
					$tCells[]='<td colspan="4" align="center">&nbsp;</td>';

						// Compile Row:
					$output.= '
						<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
							'.implode('
							',$tCells).'
						</tr>';
					$cc++;
			} else {
				foreach($displayRows as $c => $inf)	{

						// Add icon/title and ID:
					$tCells = array();
					if (!$c)	{
						$tCells[]='<td nowrap="nowrap" rowspan="'.count($displayRows).'">'.$rowTitle.'</td>';
					}

						// Get vars:
					$tCells[]='<td>'.htmlspecialchars(t3lib_div::fixed_lgd($inf['origparams'],100)).'</td>';

						// Error:
					$tCells[]='<td>'.($duplicates[$inf['content']] ? $this->pObj->doc->icons(2).'Duplicate' : '&nbsp;').'</td>';

						// Path:
					$tCells[]='<td>'.htmlspecialchars(t3lib_div::fixed_lgd($inf['content'],100)).'</td>';

						// Timestamp:
					$tCells[]='<td>'.htmlspecialchars(t3lib_BEfunc::datetime($inf['tstamp'])).' / '.htmlspecialchars(t3lib_BEfunc::calcAge(time()-$inf['tstamp'])).'</td>';

						// Compile Row:
					$output.= '
						<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
							'.implode('
							',$tCells).'
						</tr>';
					$cc++;

					$duplicates[$inf['content']] = TRUE;
				}
			}
		}

			// Create header:
		$tCells = array();
		$tCells[]='<td>Title:</td>';
		$tCells[]='<td>GET variables:</td>';
		$tCells[]='<td>Error:</td>';
		$tCells[]='<td>Path:</td>';
		$tCells[]='<td>Timestamp:</td>';

		$output = '
			<tr class="bgColor5 tableheader">
				'.implode('
				',$tCells).'
			</tr>'.$output;

			// Compile final table and return:
		$output = '
		<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">'.$output.'
		</table>';

		return $output;
	}











	/*****************************
	 *
	 * Unique Alias
	 *
	 *****************************/

	/**
	 * Shows the mapping between aliases and unique IDs of arbitrary tables
	 *
	 * @return	string		HTML
	 */
	function uniqueAlias()	{

		$tableName = t3lib_div::_GP('table');
		$cmd = t3lib_div::_GET('cmd');
		$entry = t3lib_div::_GET('entry');

			// Select rows:
		$overviewRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('tablename,count(*) as number_of_rows','tx_realurl_uniqalias','','tablename','','','tablename');

		if ($tableName && isset($overviewRows[$tableName]))	{	// Show listing of single table:

				// Select rows:
			$tableContent = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*','tx_realurl_uniqalias','tablename='.$GLOBALS['TYPO3_DB']->fullQuoteStr($tableName,'tx_realurl_uniqalias'),'','value_id');

			$cc=0;
			$duplicates = array();
			foreach($tableContent as $aliasRecord)	{
					// Add data:
				$tCells = array();
				$tCells[]='<td>'.htmlspecialchars($aliasRecord['value_id']).'</td>';

				if ((string)$cmd==='edit' && ($entry==='ALL' || !strcmp($entry,$aliasRecord['uid'])))	{
					$tCells[]='<td>'.
								'<input type="text" name="edit['.$aliasRecord['uid'].']" value="'.htmlspecialchars($aliasRecord['value_alias']).'" />'.
								($entry!=='ALL' ? $this->saveCancelButtons('<input type="hidden" name="table" value="'.htmlspecialchars($tableName).'" /><input type="hidden" name="id" value="'.htmlspecialchars($this->pObj->id).'" />') : '').
								'</td>';
				} else {
					$tCells[]='<td>'.htmlspecialchars($aliasRecord['value_alias']).'</td>';
				}
				$tCells[]='<td>'.
							'<a href="'.$this->linkSelf('&table='.rawurlencode($tableName).'&cmd=edit&entry='.$aliasRecord['uid']).'">'.
							'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/edit2.gif','width="11" height="12"').' title="" alt="" />'.
							'</a>'.
							'</td>';



				$tCells[]='<td>'.
						(isset($duplicates[$aliasRecord['value_alias']]) ? $this->pObj->doc->icons(2).'Already used by ID '.$duplicates[$aliasRecord['value_alias']] :'&nbsp;').
						'</td>';

				$field_id = $aliasRecord['field_id'];
				$field_alias = $aliasRecord['field_alias'];

					// Compile Row:
				$output.= '
					<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
						'.implode('
						',$tCells).'
					</tr>';
				$cc++;

				$duplicates[$aliasRecord['value_alias']] = $aliasRecord['value_id'];
			}

				// Create header:
			$tCells = array();
			$tCells[]='<td>ID (Field: '.$field_id.')</td>';
			$tCells[]='<td>Alias (Field: '.$field_alias.'):</td>';
			$tCells[]='<td>'.
						'<a href="'.$this->linkSelf('&table='.rawurlencode($tableName).'&cmd=edit&entry=ALL').'">'.
						'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/edit2.gif','width="11" height="12"').' title="" alt="" />'.
						'</a>'.
						'</td>';
			$tCells[]='<td>Error:</td>';

			$output = '
				<tr class="bgColor5 tableheader">
					'.implode('
					',$tCells).'
				</tr>'.$output;
					// Compile final table and return:
			$output = '

			<br/>
			Table: <b>'.htmlspecialchars($tableName).'</b><br/>
			Aliases: <b>'.htmlspecialchars(count($tableContent)).'</b><br/>
			<br/>
			<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">'.$output.'
			</table>';

			if ($entry==='ALL')	{
				$output.= $this->saveCancelButtons('<input type="hidden" name="table" value="'.htmlspecialchars($tableName).'" /><input type="hidden" name="id" value="'.htmlspecialchars($this->pObj->id).'" />');
			}
		} else {	// Create overview:
			$cc=0;
			$output='';
			if (count($overviewRows))	{
				foreach($overviewRows as $aliasRecord)	{

						// Add data:
					$tCells = array();
					$tCells[]='<td><a href="'.$this->linkSelf('&table='.rawurlencode($aliasRecord['tablename'])).'">'.$aliasRecord['tablename'].'</a></td>';
					$tCells[]='<td>'.$aliasRecord['number_of_rows'].'</td>';

						// Compile Row:
					$output.= '
						<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
							'.implode('
							',$tCells).'
						</tr>';
					$cc++;
				}

					// Create header:
				$tCells = array();
				$tCells[]='<td>Table:</td>';
				$tCells[]='<td>Aliases:</td>';

				$output = '
					<tr class="bgColor5 tableheader">
						'.implode('
						',$tCells).'
					</tr>'.$output;

					// Compile final table and return:
				$output = '
				<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">'.$output.'
				</table>';
			}
		}

		return $output;
	}


	/**
	 * Changes the "alias" value of an entry in the unique alias table
	 *
	 * @param	integer		UID of unique alias
	 * @param	string		New value for the alias
	 * @return	void
	 */
	function editUniqAliasEntry($cache_id,$value)	{
		$field_values = array(
			'value_alias' => $value
		);
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_uniqalias','uid='.intval($cache_id), $field_values);
	}

	/**
	 * Will look for submitted unique alias entries to save
	 *
	 * @return	void
	 */
	function edit_save_uniqAlias()	{
		if (t3lib_div::_POST('_edit_save'))	{
			$editArray = t3lib_div::_POST('edit');
			foreach($editArray as $cache_id => $value)	{
				$this->editUniqAliasEntry($cache_id,trim($value));
			}
		}
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cachemgm/modfunc1/class.tx_realurl_modfunc1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cachemgm/modfunc1/class.tx_realurl_modfunc1.php']);
}
?>