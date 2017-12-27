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

class Contacts extends \Aurora\System\Managers\AbstractManager
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
	 * @param \Aurora\Modules\ContactObjects\Classes\Contact $oContact
	 * @return int|bool
	 */
	public function createContact(\Aurora\Modules\ContactObjects\Classes\Contact &$oContact)
	{
		$mResult = false;
		try
		{
			if ($oContact->validate())
			{
				$oContact->Storage = $this->GetModule()->sStorage;
				$mResult = $this->oEavManager->saveEntity($oContact);
				if (!$mResult)
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ContactManager_ContactCreateFailed);
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
	 * @param \Aurora\Modules\ContactObjects\Classes\Contact $oContact
	 * @return bool
	 */
	public function updateContact(\Aurora\Modules\ContactObjects\Classes\Contact $oContact)
	{
		$bResult = false;
		try
		{
			if ($oContact->validate())
			{
				if (!$this->oEavManager->saveEntity($oContact))
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ContactManager_ContactUpdateFailed);
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
	 * @return \Aurora\Modules\ContactObjects\Classes\Contact|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getContactByIdOrUUID($mIdOrUUID)
	{
		$mContact = false;
		try
		{
			if ($mIdOrUUID)
			{
				$mContact = $this->oEavManager->getEntity($mIdOrUUID);
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mContact = false;
			$this->setLastException($oException);
		}
		return $mContact;
	}

	/**
	 *
	 * @paramstring $sEmail Email
	 * @return \Aurora\Modules\ContactObjects\Classes\Contact|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getContactByEmail($sEmail)
	{
		$mContact = false;
		try
		{
			if (is_string($sEmail) && !empty($sEmail))
			{
				$aResults = $this->oEavManager->getEntities(
					\Aurora\System\Api::GetModule('ContactObjects')->getNamespace() . '\Classes\Contact',
					[],
					0,
					1,
					[
						'$AND' => [
							'Email' => [$sEmail, 'LIKE'],
							'Storage' => [$this->GetModule()->sStorage, 'LIKE']
						]
					]
				);
				if (is_array($aResults) && isset($aResults[0]))
				{
					$mContact = $aResults[0];
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mContact = false;
			$this->setLastException($oException);
		}
		return $mContact;
	}

	/**
	 * @param int $iLimit Limit.
	 * @param int $iOffset Offset.
	 * @param array $aSearchFilters Search filters.
	 * @param array$aViewAttributes Fields List
	 * @return array
	 */
	public function getContacts($iLimit = 0, $iOffset = 0, $aSearchFilters = [], $aViewAttributes = [])
	{
		$aContacts = [];
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
				\Aurora\System\Api::GetModule('ContactObjects')->getNamespace() . '\Classes\Contact',
				$aViewAttributes,
				$iOffset,
				$iLimit,
				$aSearchFilters,
				'FullName'
			);

			if (is_array($aResults) && count($aResults) > 0)
			{
				foreach ($aResults as $oContact)
				{
					$aContacts[$oContact->UUID] = $oContact;
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aContacts;
	}

	/**
	 * @return int
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getContactsCount($aSearchFilters = [])
	{
		$iResult = 0;
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
			$iResult = $this->oEavManager->getEntitiesCount(
				\Aurora\System\Api::GetModule('ContactObjects')->getNamespace() . '\Classes\Contact',
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
	 * @param \Aurora\Modules\ContactObjects\Classes\Contact $oContact
	 * @return bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function deleteContact(\Aurora\Modules\ContactObjects\Classes\Contact $oContact)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oEavManager->deleteEntity($oContact->EntityId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}