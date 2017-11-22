<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Managers\Licenses;

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
	 * @param \Aurora\Modules\SaleObjects\Classes\License $oLicense
	 * @return bool
	 */
	public function CreateLicense(\Aurora\Modules\SaleObjects\Classes\License &$oLicense)
	{
		$bResult = false;
		try
		{
			if ($oLicense->validate())
			{
				if (!$this->oEavManager->saveEntity($oLicense))
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::LicenseManager_LicenseCreateFailed);
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
	 * @param \Aurora\Modules\SaleObjects\Classes\License $oLicense
	 * @return bool
	 */
	public function updateLicense(\Aurora\Modules\SaleObjects\Classes\License $oLicense)
	{
		$bResult = false;
		try
		{
			if ($oLicense->validate())
			{
				if (!$this->oEavManager->saveEntity($oLicense))
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::LicenseManager_LicenseUpdateFailed);
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
	 * @param int $iLicenseCode License code
	 * @return \Aurora\Modules\SaleObjects\Classes\License|bool
	 */
	public function getLicenseByCode($iLicenseCode)
	{
		$oLicense = false;
		try
		{
			if (is_numeric($iLicenseCode))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\License',
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::LicenseCode' => $iLicenseCode
					]
				);

				if (is_array($aResults) && isset($aResults[0]))
				{
					$oLicense = $aResults[0];
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oLicense = false;
			$this->setLastException($oException);
		}
		return $oLicense;
	}

	/**
	 * @param int $iLicenseCode License code
	 * @return \Aurora\Modules\SaleObjects\Classes\License|bool
	 */
	public function getLicenseByShareItLicenseId($iShareItLicenseId)
	{
		$oLicense = false;
		try
		{
			if (is_numeric($iShareItLicenseId))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\License',
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::ShareItLicenseId' => $iShareItLicenseId
					]
				);

				if (is_array($aResults) && isset($aResults[0]))
				{
					$oLicense = $aResults[0];
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oLicense = false;
			$this->setLastException($oException);
		}
		return $oLicense;
	}

	/**
	 *
	 * @param int $iLicenseId License ID
	 * @return \Aurora\Modules\SaleObjects\Classes\License|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getLicenseById($iLicenseId)
	{
		$mLicense = false;
		try
		{
			if (is_numeric($iLicenseId))
			{
				$mLicense = $this->oEavManager->getEntity((int) $iLicenseId);
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mLicense = false;
			$this->setLastException($oException);
		}
		return $mLicense;
	}

	/**
	 * @param string $sName License name
	 * @return \Aurora\Modules\SaleObjects\Classes\License|bool
	 */
	public function getLicenseByName($sName)
	{
		$oLicense = false;
		try
		{
			if ($sName !== "")
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\License',
					[],
					0,
					0,
					[
						$this->GetModule()->GetName() . '::LicenseName' => $sName
					]
				);

				if (is_array($aResults) && isset($aResults[0]))
				{
					$oLicense = $aResults[0];
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oLicense = false;
			$this->setLastException($oException);
		}
		return $oLicense;
	}

	/**
	 * @param string $sPayPalItem PayPal license item
	 * @param int $iNetTotal  Payment amount
	 *
	 * @return \Aurora\Modules\SaleObjects\Classes\License|bool
	 */
	public function getPayPalLicenses($sPayPalItem = '')
	{
		$oLicenses = false;
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
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\License',
				[],
				0,
				0,
				$aFilters
			);

			if (is_array($aResults))
			{
				$oLicenses = $aResults;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oLicenses = false;
			$this->setLastException($oException);
		}
		return $oLicenses;
	}

	/**
	 * @param int $iLimit Limit.
	 * @param int $iOffset Offset.
	 * @param array $aSearchFilters Search filters.
	 * @param array$aViewAttributes Fields List
	 * @return array
	 */
	public function getLicenses($iLimit = 0, $iOffset = 0, $aSearchFilters = [], $aViewAttributes = [])
	{
		$aLicenses = [];
		try
		{
			$aResults = $this->oEavManager->getEntities(
			\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\License',
				$aViewAttributes,
				$iOffset,
				$iLimit,
				$aSearchFilters,
				$this->GetModule()->GetName() . '::LicenseName'
			);

			foreach ($aResults as $oLicense)
			{
				$aLicenses[$oLicense->EntityId] = $oLicense;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aLicenses;
	}

	/**
	 * @return int
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getLicensesCount($aSearchFilters = [])
	{
		$iResult = 0;
		try
		{
			$iResult = $this->oEavManager->getEntitiesCount(
				\Aurora\System\Api::GetModule('SaleObjects')->getNamespace() . '\Classes\License',
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