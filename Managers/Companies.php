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
class Companies extends \Aurora\System\Managers\AbstractManager
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
	 * @param \Aurora\Modules\ContactObjects\Classes\Company $oCompany
	 * @return int|bool
	 */
	public function createCompany(\Aurora\Modules\ContactObjects\Classes\Company &$oCompany)
	{
		$mResult = false;
		if ($oCompany->validate())
		{
			$mResult = $this->oEavManager->saveEntity($oCompany);
			if (!$mResult)
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::CompanyCreateFailed);
			}
		}
		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\ContactObjects\Classes\Company $oCompany
	 * @return bool
	 */
	public function updateCompany(\Aurora\Modules\ContactObjects\Classes\Company $oCompany)
	{
		$bResult = false;
		if ($oCompany->validate())
		{
			if (!$this->oEavManager->saveEntity($oCompany))
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::CompanyUpdateFailed);
			}

			$bResult = true;
		}
		return $bResult;
	}

	/**
	 *
	 * @param int|string $mIdOrUUID
	 * @return \Aurora\Modules\ContactObjects\Classes\Company|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCompanyByIdOrUUID($mIdOrUUID)
	{
		$mCompany = false;
		if ($mIdOrUUID)
		{
			$mCompany = $this->oEavManager->getEntity($mIdOrUUID, '\Aurora\Modules\ContactObjects\Classes\Company');
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		return $mCompany;
	}

	/**
	 * @param int $iLimit Limit.
	 * @param int $iOffset Offset.
	 * @param array $aSearchFilters Search filters.
	 * @param array$aViewAttributes Fields List
	 * @return array
	 */
	public function getCompanies($iLimit = 0, $iOffset = 0, $aSearchFilters = [], $aViewAttributes = [])
	{
		$aCompanies = [];

		$aResults = $this->oEavManager->getEntities(
			\Aurora\Modules\ContactObjects\Classes\Company::class,
			$aViewAttributes,
			$iOffset,
			$iLimit,
			$aSearchFilters,
			'Title'
		);

		if (is_array($aResults) && count($aResults) > 0)
		{
			foreach ($aResults as $oCompany)
			{
				$aCompanies[$oCompany->UUID] = $oCompany;
			}
		}
		return $aCompanies;
	}

	/**
	 * @return int
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCompaniesCount($aSearchFilters = [])
	{
		$iResult = 0;
		$iResult = $this->oEavManager->getEntitiesCount(
			\Aurora\Modules\ContactObjects\Classes\Company::class,
			$aSearchFilters
		);
		return $iResult;
	}

	/**
	 *
	 * @paramstring $sTitle Title
	 * @return \Aurora\Modules\ContactObjects\Classes\Company|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCompanyByTitle($sTitle)
	{
		$mCompany = false;
		if (is_string($sTitle))
		{
			$aResults = $this->oEavManager->getEntities(
				\Aurora\Modules\ContactObjects\Classes\Company::class,
				[],
				0,
				1,
				[
					'Title' => $sTitle
				]
			);
			if (is_array($aResults) && isset($aResults[0]))
			{
				$mCompany = $aResults[0];
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		return $mCompany;
	}
}