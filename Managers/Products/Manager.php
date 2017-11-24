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
				if (!$this->oEavManager->saveEntity($oProduct))
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
	public function updateProduct(\Aurora\Modules\SaleObjects\Classes\Product $oProduct)
	{
		$bResult = false;
		try
		{
			if ($oProduct->validate())
			{
				if (!$this->oEavManager->saveEntity($oProduct))
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ProductManager_ProductUpdateFailed);
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
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::ProductCode' => $iProductCode
					]
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

	/**
	 * @param int $iProductCode Product code
	 * @return \Aurora\Modules\SaleObjects\Classes\Product|bool
	 */
	public function getProductByShareItProductId($iShareItProductId)
	{
		$oProduct = false;
		try
		{
			if (is_numeric($iShareItProductId))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Product',
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::ShareItProductId' => $iShareItProductId
					]
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

	/**
	 *
	 * @param int $iProductId Product ID
	 * @return \Aurora\Modules\SaleObjects\Classes\Product|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getProductById($iProductId)
	{
		$mProduct = false;
		try
		{
			if (is_numeric($iProductId))
			{
				$mProduct = $this->oEavManager->getEntity((int) $iProductId);
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mProduct = false;
			$this->setLastException($oException);
		}
		return $mProduct;
	}

	/**
	 * @param string $sName Product name
	 * @return \Aurora\Modules\SaleObjects\Classes\Product|bool
	 */
	public function getProductByName($sName)
	{
		$oProduct = false;
		try
		{
			if ($sName !== "")
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Product',
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::ProductName' => $sName
					]
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

	/**
	 * @param string $sPayPalItem PayPal product item
	 * @param int $iNetTotal  Payment amount
	 *
	 * @return \Aurora\Modules\SaleObjects\Classes\Product|bool
	 */
	public function getPayPalProducts($sPayPalItem = '')
	{
		$oProducts = false;
		try
		{
			if ($sPayPalItem === '')
			{
				$aFilters = [
					$this->GetModule()->GetName() . '::PayPalItem' => ['NULL', 'IS NOT']
				];
			}
			else
			{
				$aFilters = [
					$this->GetModule()->GetName() . '::PayPalItem' => $sPayPalItem
				];
			}
			$aResults = $this->oEavManager->getEntities(
			\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Product',
				[],
				0,
				0,
				$aFilters
			);

			if (is_array($aResults))
			{
				$oProducts = $aResults;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oProducts = false;
			$this->setLastException($oException);
		}
		return $oProducts;
	}

	/**
	 * @param int $iLimit Limit.
	 * @param int $iOffset Offset.
	 * @param array $aSearchFilters Search filters.
	 * @param array$aViewAttributes Fields List
	 * @return array
	 */
	public function getProducts($iLimit = 0, $iOffset = 0, $aSearchFilters = [], $aViewAttributes = [])
	{
		$aProducts = [];
		try
		{
			$aResults = $this->oEavManager->getEntities(
			\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Product',
				$aViewAttributes,
				$iOffset,
				$iLimit,
				$aSearchFilters,
				$this->GetModule()->GetName() . '::ProductName'
			);

			foreach ($aResults as $oProduct)
			{
				$aProducts[$oProduct->UUID] = $oProduct;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aProducts;
	}

	/**
	 * @return int
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getProductsCount($aSearchFilters = [])
	{
		$iResult = 0;
		try
		{
			$iResult = $this->oEavManager->getEntitiesCount(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Product',
				$aSearchFilters
			);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $iResult;
	}
}