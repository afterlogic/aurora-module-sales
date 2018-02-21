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
	 * @return int|bool
	 */
	public function createProductGroup(\Aurora\Modules\SaleObjects\Classes\ProductGroup &$oProductGroup)
	{
		$mResult = false;
		if ($oProductGroup->validate())
		{
			$mResult = $this->oEavManager->saveEntity($oProductGroup);
			if (!$mResult)
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::ProductGroupCreateFailed);
			}
		}
		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\SaleObjects\Classes\ProductGroup $oProductGroup
	 * @return bool
	 */
	public function updateProductGroup(\Aurora\Modules\SaleObjects\Classes\ProductGroup $oProductGroup)
	{
		$bResult = false;
		if ($oProductGroup->validate())
		{
			if (!$this->oEavManager->saveEntity($oProductGroup))
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::ProductGroupUpdateFailed);
			}
			$bResult = true;
		}
		return $bResult;
	}

	/**
	 * @param int $iProductCode Product code
	 * @return \Aurora\Modules\SaleObjects\Classes\ProductGroup|bool
	 */
	public function getProductGroupByCode($iProductCode)
	{
		$oProductGroup = false;
		if (is_numeric($iProductCode))
		{
			$aResults = $this->oEavManager->getEntities(
			\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\ProductGroup',
				[],
				0,
				0,
				[
					$this->GetModule()->GetName() . '::ProductCode' => $iProductCode
				]
			);

			if (is_array($aResults) && isset($aResults[0]))
			{
				$oProductGroup = $aResults[0];
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		return $oProductGroup;
	}

	/**
	 *
	 * @param int|string $mIdOrUUID
	 * @return \Aurora\Modules\SaleObjects\Classes\ProductGroup|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getProductGroupByIdOrUUID($mIdOrUUID)
	{
		$mProductGroup = false;
		if ($mIdOrUUID)
		{
			$mProductGroup = $this->oEavManager->getEntity($mIdOrUUID, '\Aurora\Modules\SaleObjects\Classes\ProductGroup');
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		return $mProductGroup;
	}

	/**
	 * @param int $iLimit Limit.
	 * @param int $iOffset Offset.
	 * @param array $aSearchFilters Search filters.
	 * @param array$aViewAttributes Fields List
	 * @return array
	 */
	public function getProductGroups($iLimit = 0, $iOffset = 0, $aSearchFilters = [], $aViewAttributes = [])
	{
		$aProductGroups = [];
		$aResults = $this->oEavManager->getEntities(
		\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\ProductGroup',
			$aViewAttributes,
			$iOffset,
			$iLimit,
			$aSearchFilters,
			'Title'
		);

		if (is_array($aResults) && count($aResults) > 0)
		{
			$aProductGroups = $aResults;
		}
		return $aProductGroups;
	}

	/**
	 * @return int
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getProductGroupsCount($aSearchFilters = [])
	{
		$iResult = $this->oEavManager->getEntitiesCount(
			\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\ProductGroup',
			$aSearchFilters
		);
		return $iResult;
	}


	/**
	 * @param \Aurora\Modules\SaleObjects\Classes\ProductGroup $oProductGroup
	 * @return bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function deleteProductGroup(\Aurora\Modules\SaleObjects\Classes\ProductGroup $oProductGroup)
	{
		$bResult = $this->oEavManager->deleteEntity($oProductGroup->EntityId);
		return $bResult;
	}
}