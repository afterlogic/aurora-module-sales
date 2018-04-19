<?php
/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Managers;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 */
class Customers extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var \Aurora\System\Managers\Eav
	 */
	public $oEavManager = null;

	/**
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule);

		$this->oEavManager = new \Aurora\System\Managers\Eav();
	}

	/**
	 * @param \Aurora\Modules\SaleObjects\Classes\Customer $oCustomer
	 * @return int|bool
	 */
	public function createCustomer(\Aurora\Modules\SaleObjects\Classes\Customer &$oCustomer)
	{
		$mResult = false;
		if ($oCustomer->validate())
		{
			$mResult = $this->oEavManager->saveEntity($oCustomer);
			if (!$mResult)
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::CustomerCreateFailed);
			}
		}
		return $mResult;
	}

	/**
	 * @param string $sEmail Email
	 * @return \Aurora\Modules\SaleObjects\Classes\Customer|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCustomerByEmail($sEmail)
	{
		$mCustomer = false;
		if (is_string($sEmail) && !empty($sEmail))
		{
			$oContact = $this->GetModule()->oApiContactsManager->getContactByEmail($sEmail);
			if ($oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact && isset($oContact->CustomerUUID))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Customer',
					[],
					0,
					1,
					[
						'UUID' => $oContact->CustomerUUID
					]
				);

				if (is_array($aResults) && isset($aResults[0]))
				{
					$mCustomer = $aResults[0];
				}
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		return $mCustomer;
	}

	/**
	 * @param array $aCustomersUUID Customers UUID
	 * @param array $aFieldsList Fields List
	 * @return array
	 */
	public function getCustomers($iLimit = 0, $iOffset = 0,  $aSearchFilters = [], $aViewAttributes = [])
	{
		$aCustomers = [];
		$aResults = $this->oEavManager->getEntities(
		\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Customer',
			$aViewAttributes,
			$iOffset,
			$iLimit,
			$aSearchFilters,
			[],
			\Aurora\System\Enums\SortOrder::ASC
		);

		if (is_array($aResults))
		{
			foreach ($aResults as $oCustomer)
			{
				$aCustomers[$oCustomer->UUID] = $oCustomer;
			}
		}
		return $aCustomers;
	}

	/**
	 *
	 * @param int|string $mIdOrUUID
	 * @return \Aurora\Modules\SaleObjects\Classes\Customer|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCustomerByIdOrUUID($mIdOrUUID)
	{
		$mCustomer = false;
		if ($mIdOrUUID)
		{
			$mCustomer = $this->oEavManager->getEntity($mIdOrUUID, '\Aurora\Modules\SaleObjects\Classes\Customer');
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		return $mCustomer;
	}
	
	/**
	 * @param \Aurora\Modules\SaleObjects\Classes\Customer $oCustomer
	 * @return bool
	 */
	public function updateCustomer(\Aurora\Modules\SaleObjects\Classes\Customer $oCustomer)
	{
		$bResult = false;
		if ($oCustomer->validate())
		{
			if (!$this->oEavManager->saveEntity($oCustomer))
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::CustomerCreateFailed);
			}
			$bResult = true;
		}
		return $bResult;
	}	
}