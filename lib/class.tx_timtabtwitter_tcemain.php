<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005-2007 Frank Nägler (typo3@naegler.net)
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
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
require_once(t3lib_extMgm::extPath('fn_lib').'lib/class.tx_fnlib_base.php');

/**
 * Class 'tx_timtabtwitter_tcemain' for the timtab_twitter extension.
 *
 * @author     Frank Nägler <typo3@naegler.net>
 */
class tx_timtabtwitter_tcemain {
	/**
	 * This method is called by a hook in the TYPO3 Core Engine (TCEmain) when a record is saved.
	 * We use it to send a twitter message.
	 *
	 * @param       string	  $status: The TCEmain operation status, fx. 'update'
	 * @param       string	  $table: The table TCEmain is currently processing
	 * @param       string	  $id: The records id (if any)
	 * @param       array	   $fieldArray: The field names and their values to be processed (passed by reference)
	 * @param       object	  $pObj: Reference to the parent object (TCEmain)
	 * @return      void
	 * @access public
	 */
	function processDatamap_afterDatabaseOperations($status, $table, $id, &$fieldArray, &$pObj) {
	//function processDatamap_postProcessFieldArray ($status, $table, $id, &$fieldArray, &$pObj) {
		$twitter = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['timtab_twitter']);
		if ($table == 'tt_news') {
			if (
				(($status == 'new') && ($fieldArray['hidden'] != 1) && ($twitter['twitter_on_new'] == 1))
				||
				(($status == 'update') && ($fieldArray['hidden'] != 1) && ($twitter['twitter_on_update'] == 1))
			) {
				$itemID = ($status == 'new') ? ($pObj->substNEWwithIDs[$id]) : $id;
				if ( isset($twitter['twitter_username']) && (strlen($twitter['twitter_username'])) && isset($twitter['twitter_password']) && (strlen($twitter['twitter_password'])) ) {
					$base = t3lib_div::makeInstance('tx_fnlib_base');
					$twitterClient = $base->getTwitterClient($twitter['twitter_username'], $twitter['twitter_password']);
					if ($twitter['Pid']) {
						$addWhere = ' AND pid = ' . intval($twitter['Pid']);
					}
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'title',
						'tt_news',
						'uid = ' . $itemID . $addWhere
					);
					$data = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					
					$msg = $twitter['twitter_message'];
					// replace %TITLE% and %LINK%
					$title = $data['title'];
					$baseUrl = (isset($twitter['baseUrl']) && strlen($twitter['baseUrl'])) ? ($twitter['baseUrl']) : (t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
					$link = $baseUrl . 'index.php?id=' . intval($twitter['singlePid']) . '&tx_ttnews[tt_news]=' . $itemID;
					$link =	$twitterClient->getTinyUrl($link);
					$msg = str_replace('%TITLE%', $title, $msg);
					$msg = str_replace('%LINK%', $link, $msg);
					$twitterClient->update($msg);
				}
			}
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/timtab_twitter/class.tx_timtabtwitter_tcemain.php'])    {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/timtab_twitter/class.tx_timtabtwitter_tcemain.php']);
}

?>
