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

		$this->oEavManager = new \Aurora\System\Managers\Eav();

	}

	/**
	 * @param \Aurora\Modules\ContactObjects\Classes\Company $oCompany
	 * @return int|bool
	 */
	public function createCompany(\Aurora\Modules\ContactObjects\Classes\Company &$oCompany)
	{
		$mResult = false;
		try
		{
			if ($oCompany->validate())
			{
				$mResult = $this->oEavManager->saveEntity($oCompany);
				if (!$mResult)
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::CompanyManager_CompanyCreateFailed);
				}
			}
		}
		catch (\Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
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
		try
		{
			if ($oCompany->validate())
			{
				if (!$this->oEavManager->saveEntity($oCompany))
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::CompanyManager_CompanyUpdateFailed);
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
	 *
	 * @param int|string $mIdOrUUID
	 * @return \Aurora\Modules\ContactObjects\Classes\Company|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCompanyByIdOrUUID($mIdOrUUID)
	{
		$mCompany = false;
		try
		{
			if ($mIdOrUUID)
			{
				$mCompany = $this->oEavManager->getEntity($mIdOrUUID);
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mCompany = false;
			$this->setLastException($oException);
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
		try
		{
			if (is_array($aSearchFilters) && count($aSearchFilters) > 0)
			{
				$aSearchFilters = ['$AND' => [
						'$AND' => $aSearchFilters,
						'Storage' => [$this->GetModule()->sStorage, 'LIKE']
					]
				];
			}
			else
			{
				$aSearchFilters = ['Storage' => [$this->GetModule()->sStorage, 'LIKE']];
			}

			$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('ContactObjects')->getNamespace() . '\Classes\Company',
				$aViewAttributes,
				$iOffset,
				$iLimit,
				$aSearchFilters,
				'FullName'
			);

			if (is_array($aResults) && count($aResults) > 0)
			{
				foreach ($aResults as $oCompany)
				{
					$aCompanies[$oCompany->UUID] = $oCompany;
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
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
		try
		{
			$iResult = $this->oEavManager->getEntitiesCount(
				\Aurora\System\Api::GetModule('ContactObjects')->getNamespace() . '\Classes\Company',
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
	 * @paramstring $sTitle Title
	 * @return \Aurora\Modules\ContactObjects\Classes\Company|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getCompanyByTitle($sTitle)
	{
		$mCompany = false;
		try
		{
			if (is_string($sTitle))
			{
				$aResults = $this->oEavManager->getEntities(
					\Aurora\System\Api::GetModule('ContactObjects')->getNamespace() . '\Classes\Company',
					[],
					0,
					1,
					[
						'Title' => [$sTitle, 'LIKE']
					]
				);
				if (is_array($aResults) && isset($aResults[0]))
				{
					$mCompany = $aResults[0];
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mCompany = false;
			$this->setLastException($oException);
		}
		return $mCompany;
	}
}