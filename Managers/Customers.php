<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Managers;

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
		try
		{
			if ($oCustomer->validate())
			{
				if (!$this->isExists($oCustomer))
				{
					$mResult = $this->oEavManager->saveEntity($oCustomer);
					if (!$mResult)
					{
						throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::CustomerManager_CustomerCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CustomerExists);
				}
			}
		}
		catch (\Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\SaleObjects\Classes\Customer $oCustomer
	 * @return bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function isExists(\Aurora\Modules\SaleObjects\Classes\Customer &$oCustomer)
	{
		return !!$this->getCustomerByEmail($oCustomer->{$this->GetModule()->GetName() . '::Email'});
	}

	/**
	 * @param string $sEmail Email
	 * @return \Aurora\Modules\SaleObjects\Classes\Customer|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCustomerByEmail($sEmail)
	{
		$mCustomer = false;
		try
		{
			if (is_string($sEmail))
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
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mCustomer = false;
			$this->setLastException($oException);
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
		try
		{
			$aResults = $this->oEavManager->getEntities(
			\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Customer',
				$aViewAttributes,
				0,
				0,
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
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
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
		try
		{
			if ($mIdOrUUID)
			{
				$mCustomer = $this->oEavManager->getEntity($mIdOrUUID);
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mCustomer = false;
			$this->setLastException($oException);
		}
		return $mCustomer;
	}
}