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

class Sales extends \Aurora\System\Managers\AbstractManager
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
	 * @param \Aurora\Modules\SaleObjects\Classes\Sale $oSale
	 * @return bool
	 */
	public function createSale(\Aurora\Modules\SaleObjects\Classes\Sale &$oSale)
	{
		$bResult = false;
		try
		{
			if ($oSale->validate())
			{
				if (!$this->oEavManager->saveEntity($oSale))
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::SaleManager_SaleCreateFailed);
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
	 * @param \Aurora\Modules\SaleObjects\Classes\Sale $oSale
	 * @return bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function deleteSale(\Aurora\Modules\SaleObjects\Classes\Sale $oSale)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oEavManager->deleteEntity($oSale->EntityId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}

	/**
	 * @param int $iLimit Limit
	 * @param int $iOffset Offset
	 * @return array|bool
	 */
	public function getSales($iLimit = 0, $iOffset = 0, $aSearchFilters = [], $aViewAttributes = [])
	{
		$mResult = false;
		try
		{
			$mResult = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Sale',
				$aViewAttributes,
				$iOffset,
				$iLimit,
				$aSearchFilters,
				['Date'],
				\Aurora\System\Enums\SortOrder::DESC
			);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $mResult;
	}

	/**
	 * @return int
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getSalesCount($aSearchFilters = [])
	{
		$iResult = 0;
		try
		{
			$iResult = $this->oEavManager->getEntitiesCount(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\Sale',
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
	 *
	 * @param int|string $mIdOrUUID
	 * @return \Aurora\Modules\SaleObjects\Classes\Sale|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getSaleByIdOrUUID($mIdOrUUID)
	{
		$mSale = false;
		try
		{
			if ($mIdOrUUID)
			{
				$mSale = $this->oEavManager->getEntity($mIdOrUUID);
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mSale = false;
			$this->setLastException($oException);
		}
		return $mSale;
	}

	/**
	 * @param \Aurora\Modules\SaleObjects\Classes\Sale $oSale
	 * @return bool
	 */
	public function updateSale(\Aurora\Modules\SaleObjects\Classes\Sale $oSale)
	{
		$bResult = false;
		try
		{
			if ($oSale->validate())
			{
				if (!$this->oEavManager->saveEntity($oSale))
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::SaleManager_SaleUpdateFailed);
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
}