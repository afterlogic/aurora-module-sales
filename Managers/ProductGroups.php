<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Managers;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
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

		$this->oEavManager = \Aurora\System\Managers\Eav::getInstance();
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
			\Aurora\Modules\SaleObjects\Classes\ProductGroup::class,
				[],
				0,
				0,
				[
					\Aurora\Modules\Sales\Module::GetName() . '::ProductCode' => $iProductCode
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
		\Aurora\Modules\SaleObjects\Classes\ProductGroup::class,
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
			\Aurora\Modules\SaleObjects\Classes\ProductGroup::class,
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
		$bResult = $this->oEavManager->deleteEntity($oProductGroup->EntityId, \Aurora\Modules\SaleObjects\Classes\ProductGroup::class);
		return $bResult;
	}
}