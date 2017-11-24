<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Managers\Customers;

class Manager extends \Aurora\System\Managers\AbstractManager
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
	 * @return bool
	 */
	public function createCustomer(\Aurora\Modules\SaleObjects\Classes\Customer &$oCustomer)
	{
		$bResult = false;
		try
		{
			if ($oCustomer->validate())
			{
				if (!$this->isExists($oCustomer))
				{
					if (!$this->oEavManager->saveEntity($oCustomer))
					{
						throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::CustomerManager_CustomerCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CustomerExists);
				}

				$bResult = true;
			}
		}
		catch (\Exception $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
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
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Customer',
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::Email' => $sEmail
					]
				);

				if (is_array($aResults) && isset($aResults[0]))
				{
					$mCustomer = $aResults[0];
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
	public function getCustomers($aCustomersUUID, $aFieldsList = [])
	{
		$aCustomers = [];
		try
		{
			if (is_array($aCustomersUUID) && count($aCustomersUUID) > 0)
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Customer',
					$aFieldsList,
					0,
					0,
					['UUID' => [$aCustomersUUID, 'IN']],
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
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
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
	 * @param int $iCustomerId
	 * @return \Aurora\Modules\SaleObjects\Classes\Customer|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCustomerById($iCustomerId)
	{
		$mCustomer = false;
		try
		{
			if (is_numeric($iCustomerId))
			{
				$mCustomer = $this->oEavManager->getEntity((int) $iCustomerId);
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
	 * @param string $sSearch Search string
	 * @param array $aFieldsList Fields List
	 * @return array
	 */
	public function searchCustomers($sSearch, $aFieldsList = [])
	{
		$aCustomers = [];
		try
		{
			if (!empty($sSearch))
			{
				$aSearchFilters = ['$OR' => [
						$this->GetModule()->GetName() . '::Email' => ['%'.$sSearch.'%', 'LIKE'],
						$this->GetModule()->GetName() . '::RegName' => ['%'.$sSearch.'%', 'LIKE'],
						$this->GetModule()->GetName() . '::FirstName' => ['%'.$sSearch.'%', 'LIKE'],
						$this->GetModule()->GetName() . '::LastName' => ['%'.$sSearch.'%', 'LIKE']
					]
				];
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Customer',
					$aFieldsList,
					0,
					0,
					$aSearchFilters
				);

				if (is_array($aResults))
				{
					foreach ($aResults as $oCustomer)
					{
						$aCustomers[$oCustomer->EntityId] = $oCustomer;
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
			$this->setLastException($oException);
		}
		return $aCustomers;
	}
}