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
	 */
	public function isExists(\Aurora\Modules\SaleObjects\Classes\Customer &$oCustomer)
	{
		$bResult = false;
		try
		{
			$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Customer',
				['Email'],
				0,
				0,
				[
					'Email' => [$oCustomer->Email, '=']
				]
			);

			if ($aResults && count($aResults) > 0)
			{
				$bResult = true;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param string $sEmail Email
	 * @return \Aurora\Modules\SaleObjects\Classes\Customer|bool
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
					array(),
					0,
					0,
					array(
						$this->GetModule()->GetName() . '::Email' => $sEmail
					)
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
}