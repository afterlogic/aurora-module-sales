<?php
/**
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 */

namespace Aurora\Modules\Sales\Classes;

/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 *
 * @package SaleObjects
 * @subpackage Classes
 */
class MailchimpList extends \Aurora\System\EAV\Entity
{
	protected $aStaticMap = [
		'Title' => ['string', ''],
		'Description' => ['string', ''],
		'ListId' => ['string', '']
	];

	public function toResponseArray()
	{
		$aResponse = parent::toResponseArray();
		$aResponse['MailchimpListId'] = $this->EntityId;
		return $aResponse;
	}
}