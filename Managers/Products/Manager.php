<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Managers\Products;

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
	 * @param \Aurora\Modules\SaleObjects\Classes\Product $oProduct
	 * @return bool
	 */
	public function CreateProduct(\Aurora\Modules\SaleObjects\Classes\Product &$oProduct)
	{
		$bResult = false;
		try
		{
			if ($oProduct->validate())
			{
				if (!$this->oEavManager->saveEntity($oProduct) && $this->canCreate($oProduct->ProductCode))
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ProductManager_ProductCreateFailed);
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
	 * @param \Aurora\Modules\SaleObjects\Classes\Product $oProduct
	 * @return bool
	 */
	public function canCreate($iProductCode)
	{
		$bResult = false;

		$oProduct = $this->getProductByCode($iProductCode);

		if (!$oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
		{
			$bResult = true;
		}

		return $bResult;
	}

	/**
	 * @param int $iProductCode Product code
	 * @return \Aurora\Modules\SaleObjects\Classes\Product|bool
	 */
	public function getProductByCode($iProductCode)
	{
		$oProduct = false;
		try
		{
			if (is_numeric($iProductCode))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Product',
					array(),
					0,
					0,
					array(
						$this->GetModule()->GetName() . '::ProductCode' => $iProductCode
					)
				);

				if (is_array($aResults) && isset($aResults[0]))
				{
					$oProduct = $aResults[0];
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oProduct = false;
			$this->setLastException($oException);
		}
		return $oProduct;
	}
}