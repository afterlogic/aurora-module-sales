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

		$this->oEavManager = \Aurora\System\Managers\Eav::getInstance();

	}

	/**
	 * @param \Aurora\Modules\ContactObjects\Classes\Contact $oContact
	 * @return int|bool
	 */
	public function createContact(\Aurora\Modules\ContactObjects\Classes\Contact &$oContact)
	{
		$mResult = false;
		if ($oContact->validate())
		{
			$oContact->Storage = $this->GetModule()->sStorage;
			$mResult = $this->oEavManager->saveEntity($oContact);
			if (!$mResult)
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::ContactCreateFailed);
			}
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
		if ($oContact->validate())
		{
			if (!$this->oEavManager->saveEntity($oContact))
			{
				throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::ContactUpdateFailed);
			}

			$bResult = true;
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
		if ($mIdOrUUID)
		{
			$mContact = $this->oEavManager->getEntity($mIdOrUUID, '\Aurora\Modules\ContactObjects\Classes\Contact');
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
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
		if (is_string($sEmail) && !empty($sEmail))
		{
			$aResults = $this->oEavManager->getEntities(
				\Aurora\Modules\ContactObjects\Classes\Contact::class,
				[],
				0,
				1,
				[
					'$AND' => [
						'Email' => $sEmail,
						'Storage' => $this->GetModule()->sStorage
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
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
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
		if (is_array($aSearchFilters) && count($aSearchFilters) > 0)
		{
			$aSearchFilters = ['$AND' => [
					'$AND' => $aSearchFilters,
					'Storage' => $this->GetModule()->sStorage
				]
			];
		}
		else
		{
			$aSearchFilters = ['Storage' => $this->GetModule()->sStorage];
		}

		$aResults = $this->oEavManager->getEntities(
			\Aurora\Modules\ContactObjects\Classes\Contact::class,
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
		return $aContacts;
	}

	/**
	 * @return int
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getContactsCount($aSearchFilters = [])
	{
		$iResult = 0;
		if (is_array($aSearchFilters) && count($aSearchFilters) > 0)
		{
			$aSearchFilters = ['$AND' => [
					'$AND' => $aSearchFilters,
					'Storage' => $this->GetModule()->sStorage
				]
			];
		}
		else
		{
			$aSearchFilters = ['Storage' => $this->GetModule()->sStorage];
		}
		$iResult = $this->oEavManager->getEntitiesCount(
			\Aurora\Modules\ContactObjects\Classes\Contact::class,
			$aSearchFilters
		);
		return $iResult;
	}
	
	/**
	 * @param \Aurora\Modules\ContactObjects\Classes\Contact $oContact
	 * @return bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function deleteContact(\Aurora\Modules\ContactObjects\Classes\Contact $oContact)
	{
		$bResult = $this->oEavManager->deleteEntity($oContact->EntityId, \Aurora\Modules\ContactObjects\Classes\Contact::class);
		return $bResult;
	}
}