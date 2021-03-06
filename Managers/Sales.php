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

		$this->oEavManager = \Aurora\System\Managers\Eav::getInstance();
	}

	/**
	 * @param \Aurora\Modules\SaleObjects\Classes\Sale $oSale
	 * @return bool
	 */
	public function createSale(\Aurora\Modules\SaleObjects\Classes\Sale &$oSale)
	{
		$bResult = false;
		if ($oSale->validate())
		{
			if (!$this->oEavManager->saveEntity($oSale))
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::SaleCreateFailed);
			}

			$bResult = true;
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
		$oSale->{\Aurora\Modules\Sales\Module::GetName() . '::Deleted'} = true;
		$bResult = $this->updateSale($oSale);
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

		$aSearchFilters = $this->getFilters($aSearchFilters);
		$mResult = $this->oEavManager->getEntities(
			\Aurora\Modules\SaleObjects\Classes\Sale::class,
			$aViewAttributes,
			$iOffset,
			$iLimit,
			$aSearchFilters,
			['Date'],
			\Aurora\System\Enums\SortOrder::DESC
		);
		return $mResult;
	}

	/**
	 * @return int
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getSalesCount($aSearchFilters = [])
	{
		$aSearchFilters = $this->getFilters($aSearchFilters);
		$iResult = $this->oEavManager->getEntitiesCount(
			\Aurora\Modules\SaleObjects\Classes\Sale::class,
			$aSearchFilters
		);
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
		if ($mIdOrUUID)
		{
			$mSale = $this->oEavManager->getEntity($mIdOrUUID, \Aurora\Modules\SaleObjects\Classes\Sale::class);
		}
		else
		{
			throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
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
		if ($oSale->validate())
		{
			if (!$this->oEavManager->saveEntity($oSale))
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::SaleUpdateFailed);
			}

			$bResult = true;
		}
		return $bResult;
	}

	public function getFilters($aSearchFilters = [])
	{
		if (is_array($aSearchFilters) && count($aSearchFilters) > 0)
		{
			$aSearchFilters = [
				'$AND' => $aSearchFilters,
				'$OR' => [
					'1@' . \Aurora\Modules\Sales\Module::GetName() . '::Deleted' => false,
					'2@' . \Aurora\Modules\Sales\Module::GetName() . '::Deleted' => ['NULL', 'IS']
				]
			];
		}
		else
		{
			$aSearchFilters = ['$OR' => [
				'1@' . \Aurora\Modules\Sales\Module::GetName() . '::Deleted' => false,
				'2@' . \Aurora\Modules\Sales\Module::GetName() . '::Deleted' => ['NULL', 'IS'],
			]];
		}
		return is_array($aSearchFilters) ? $aSearchFilters : [];
	}
}