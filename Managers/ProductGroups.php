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

class ProductGroups extends \Aurora\System\Managers\AbstractManager
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
	 * @param \Aurora\Modules\SaleObjects\Classes\ProductGroup $oProductGroup
	 * @return bool
	 */
	public function createProductGroups(\Aurora\Modules\SaleObjects\Classes\ProductGroup &$oProductGroup)
	{
		$bResult = false;
		try
		{
			if ($oProductGroup->validate())
			{
				if (!$this->isExists($oProductGroup))
				{
					if (!$this->oEavManager->saveEntity($oProductGroup))
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
	 * @param \Aurora\Modules\SaleObjects\Classes\ProductGroup $oProductGroup
	 * @return bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function isExists(\Aurora\Modules\SaleObjects\Classes\ProductGroup &$oProductGroup)
	{
		return !!$this->getProductGroupById($oProductGroup->Id);
	}

	/**
	 * @param string $iId
	 * @return \Aurora\Modules\SaleObjects\Classes\ProductGroup|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getProductGroupById($iId)
	{
		$mCustomer = false;
		try
		{
			if (is_string($iId))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObject')->getNamespace() . '\Classes\ProductGroup',
					[],
					0,
					0,
					[
						'ProductId' => $iId
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
}