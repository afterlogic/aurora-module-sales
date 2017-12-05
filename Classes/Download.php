<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Classes;

/**
 *
 * @package SaleObjects
 * @subpackage Classes
 */

class Download extends \Aurora\System\EAV\Entity
{
	public function __construct($sModule)
	{
		$this->aStaticMap = array(
			'DownloadId'		=> array('int', 0),
			'ProductCode'		=> array('int', 0),
			'Date'				=> array('datetime', date('Y-m-d H:i:s')),
			'Referer'			=> array('text', ''),
			'Ip'				=> array('string', ''),
			'Gad'				=> array('string', ''),
			'ProductVersion'	=> array('string', ''),
			'TrialKey'			=> array('string', ''),
			'LicenseType'		=> array('int', 0),
			'ReferrerPage'		=> array('int', 0),
			'IsUpgrade'			=> array('bool', false),
			'PlatformType'		=> array('int', 0),
			'CustomerUUID'		=> array('string', ''),
			'ProductUUID'	=> array('string', '')
		);
		parent::__construct($sModule);
	}
	
	public function toResponseArray()
	{
		$aResponse = parent::toResponseArray();
		$aResponse['Id'] = $this->EntityId;
		return $aResponse;
	}
}