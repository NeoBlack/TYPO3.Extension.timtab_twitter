<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Frank Nägler <mail@naegler.net>
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

require_once(t3lib_extMgm::extPath('timtab_twitter').'lib/domit/xml_domit_include.php');
require_once(t3lib_extMgm::extPath('fn_lib').'lib/class.tx_fnlib_base.php');
require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'my latest messages' for the 'timtab_twitter' extension.
 *
 * @author	Frank Nägler <mail@naegler.net>
 * @package	TYPO3
 * @subpackage	tx_timtabtwitter
 */
class tx_timtabtwitter_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_timtabtwitter_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_timtabtwitter_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'timtab_twitter';	// The extension key.
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;

		$this->init();

		$this->getPosts();
		
		$content = $this->getOutput();
	
		return $this->pi_wrapInBaseClass($content);
	}

	function init() {
		$this->local_cObj = t3lib_div::makeInstance('tslib_cObj');
		$this->parser = new DOMIT_Document();
		$this->pi_initPIflexForm();
		$this->lConf = array();
		$this->base = t3lib_div::makeInstance('tx_fnlib_base');
		$piFlexForm = $this->cObj->data['pi_flexform'];
		foreach ( $piFlexForm['data'] as $sheet => $data )
			foreach ( $data as $lang => $value )
				foreach ( $value as $key => $val )
					$this->lConf[$key] = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
		//debug($this->lConf);
	}
	
	function getPosts() {
		$twitter = $this->base->getTwitterClient($this->lConf['twitter_username'], $this->lConf['twitter_password']);
		$this->data = $twitter->getTimeline($this->lConf['view_mode']);
	}
	
	function getOutput() {
		$this->parser->parseXML($this->data);
		$matchingNodes =& $this->parser->getElementsByTagName("status");
		if ($matchingNodes != null) {
			$total = $matchingNodes->getLength();
			if (isset($this->lConf['view_count']) &&  $this->lConf['view_count'] < $total)
				$total = $this->lConf['view_count'];
			for ($i = 0; $i < $total; $i++) {
				$currNode =& $matchingNodes->item($i);
				$entrys[$i] = $currNode;
			}
			for ($i = 0; $i < count($entrys);$i++) {
				$tmp['entry']['created_at'] = $this->getStrValue('created_at', $entrys[$i]);
				$tmp['entry']['id'] 		= $this->getStrValue('id', $entrys[$i]);
				$tmp['entry']['text']		= $this->getStrValue('text', $entrys[$i]);

				$user = $entrys[$i]->getElementsByTagName("user");
				$user = $user->item(0);				
				$tmp['entry']['user_name'] 			= $this->getStrValue('name', $user);
				$tmp['entry']['user_screen_name'] 	= $this->getStrValue('screen_name', $user);
				$tmp['entry']['user_location'] 		= $this->getStrValue('location', $user);
				$tmp['entry']['user_description'] 	= $this->getStrValue('description', $user);
				$tmp['entry']['user_image'] 		= $this->getStrValue('profile_image_url', $user);
				$tmp['entry']['user_url'] 			= $this->getStrValue('url', $user);
				$tmp['entry']['user_protected'] 	= $this->getStrValue('protected', $user);
				$pEntrys[$i] = $tmp['entry'];
			}
			//debug($pEntrys);
			$templateContent = $this->local_cObj->fileResource($this->conf['template']);
			$templateCode = $this->local_cObj->getSubpart($templateContent, '###TEMPLATE_ENTRY###');
			
			$out = '';
			for ($i = 0; $i < count($pEntrys);$i++) {
				$outformat	= $this->conf['timeFormats.']['output'];
				$timestamp	= strtotime($pEntrys[$i]['created_at']);
				$markerArray['###CREATED_AT###'] = strftime($outformat , $timestamp);
				$markerArray['###MESSAGE###'] = ($this->conf['makeLinksClickable'] == 1) ? $this->makeClickable($pEntrys[$i]['text']) : $pEntrys[$i]['text'];
				$markerArray['###USER_REAL_NAME###'] = ($this->lConf['link_name'] == 1)?($this->getLink($pEntrys[$i]['user_name'], $pEntrys[$i]['user_url'])):($pEntrys[$i]['user_name']);
				$markerArray['###USER_SCREEN_NAME###'] = ($this->lConf['link_screenname'] == 1)?($this->getLink($pEntrys[$i]['user_screen_name'], $pEntrys[$i]['user_url'])):($pEntrys[$i]['user_screen_name']);
				$markerArray['###USER_LOCATION###'] = $pEntrys[$i]['user_location'];
				$markerArray['###USER_DESCRIPTION###'] = $pEntrys[$i]['user_description'];
				$markerArray['###USER_IMAGE###'] = ($this->lConf['link_picture'] == 1)?($this->getLink('<img src="'.$pEntrys[$i]['user_image'].'" alt="'.$pEntrys[$i]['user_screen_name'].'" title="'.$pEntrys[$i]['user_screen_name'].'" />', $pEntrys[$i]['user_url'])):('<img src="'.$pEntrys[$i]['user_image'].'" alt="'.$pEntrys[$i]['user_screen_name'].'" title="'.$pEntrys[$i]['user_screen_name'].'" />');
				$markerArray['###USER_IMAGE_SRC###'] = $pEntrys[$i]['user_image'];
				$markerArray['###USER_URL###'] = $pEntrys[$i]['user_url'];
				
				$out .= $this->local_cObj->substituteMarkerArray($templateCode, $markerArray);
			}
			return $out;
		}
	}
	
	function getLink($txt, $url) {
		$target = ($this->lConf['link_target'])?(' target="'.$this->lConf['link_target'].'"'):('');
		return '<a href="'.$url.'"'.$target.'>'.$txt.'</a>';
	}
	
	function makeClickable($text) {
		$search = array(
			'`((?:https?|ftp)://\S+[[:alnum:]]/?)`si',
			'`((?<!//)(www\.\S+[[:alnum:]]/?))`si'
		);
        $replace = array(
			'<a href="$1"  rel="nofollow">$1</a> ',
			'<a href="http://$1" rel="nofollow">$1</a>'
		);
		return preg_replace($search, $replace, $text);
	}
	
	function getMonthValue($str) {
		switch ($str) {
			case "Jan": return 1;
			case "Feb": return 2;
			case "Mar": return 3;
			case "Apr": return 4;
			case "May": return 5;
			case "Jun": return 6;
			case "Jul": return 7;
			case "Aug": return 8;
			case "Sep": return 9;
			case "Oct": return 10;
			case "Nov": return 11;
			case "Dec": return 12;
			default: return 1;
		}
	}

	function getStrValue($f, $o) {
		$tmp =& $o->getElementsByTagName($f);
		$tmp = $tmp->item(0);
		return $tmp->firstChild->nodeValue;
	}
	
	function getAttributeValue($f='', $o, $a) {
		if ($f != '') {
			$tmp =& $o->getElementsByTagName($f);
			$tmp = $tmp->item(0);
		} else {
			$tmp = $o;
		}
		return $tmp->getAttribute($a);
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/timtab_twitter/pi1/class.tx_timtabtwitter_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/timtab_twitter/pi1/class.tx_timtabtwitter_pi1.php']);
}

?>
