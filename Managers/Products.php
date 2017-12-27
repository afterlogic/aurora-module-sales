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

class Products extends \Aurora\System\Managers\AbstractManager
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
	 * @return int|bool
	 */
	public function createProduct(\Aurora\Modules\SaleObjects\Classes\Product &$oProduct)
	{
		$mResult = false;
		if ($this->validate($oProduct))
		{
			$mResult = $this->oEavManager->saveEntity($oProduct);
			if (!$mResult)
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ProductManager_ProductCreateFailed);
			}
		}
		return $mResult;
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
	 * @param string $sProductGroupUUID UUID of product group.
	 * @return array
	 */
	public function getProductsByGroupe($sProductGroupUUID)
	{
		$aResult = [];
		if ($sProductGroupUUID)
		{
			$aSearchFilters = ['ProductGroupUUID' => $sProductGroupUUID];
			$aResult = $this->getProducts(0, 0, $aSearchFilters);
		}
		return $aResult;
	}

	/**
	 * @param stirng $sShareItProductId Product ID
	 * @return \Aurora\Modules\SaleObjects\Classes\Product|bool
	 */
	public function getProductByShareItProductId($sShareItProductId)
	{
		$oProduct = false;
		try
		{
			if (!empty($sShareItProductId))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Product',
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::ShareItProductId' => $sShareItProductId
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
	 * @param string $sCrmProductId Product ID in CRM
	 * @return \Aurora\Modules\SaleObjects\Classes\Product|bool
	 */
	public function getProductByCrmProductId($sCrmProductId)
	{
		$oProduct = false;
		try
		{
			if (!empty($sCrmProductId))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Product',
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::CrmProductId' => $sCrmProductId
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
	 * @param int|string $mIdOrUUID
	 * @return \Aurora\Modules\SaleObjects\Classes\Product|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getProductByIdOrUUID($mIdOrUUID)
	{
		$mProduct = false;
		try
		{
			if ($mIdOrUUID)
			{
				$mProduct = $this->oEavManager->getEntity($mIdOrUUID);
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
						'Title' => $sName
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
				'Title'
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

	/**
	 * @param \Aurora\Modules\SaleObjects\Classes\Product $oProduct
	 * @return bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function deleteProduct(\Aurora\Modules\SaleObjects\Classes\Product $oProduct)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oEavManager->deleteEntity($oProduct->EntityId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}

	public function validate(\Aurora\Modules\SaleObjects\Classes\Product $oProduct)
	{
		if (!\Aurora\System\Utils\Validate::IsEmpty($oProduct->{$this->GetModule()->GetName() . '::ShareItProductId'}))
		{
			$mResult = $this->getProductByShareItProductId($oProduct->{$this->GetModule()->GetName() . '::ShareItProductId'});
			if (!!$mResult)
			{
				throw new \Aurora\System\Exceptions\ValidationException('Validation - Invalid parameters', \Aurora\System\Exceptions\ErrorCodes::Validation_InvalidParameters);
			}
		}
		if (!\Aurora\System\Utils\Validate::IsEmpty($oProduct->{$this->GetModule()->GetName() . '::CrmProductId'}))
		{
			$mResult = $this->getProductByShareItProductId($oProduct->{$this->GetModule()->GetName() . '::CrmProductId'});
			if (!!$mResult)
			{
				throw new \Aurora\System\Exceptions\ValidationException('Validation - Invalid parameters', \Aurora\System\Exceptions\ErrorCodes::Validation_InvalidParameters);
			}
		}
		return true;
	}
}