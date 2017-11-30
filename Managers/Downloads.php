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

class Downloads extends \Aurora\System\Managers\AbstractManager
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
	 * @param \Aurora\Modules\Sales\Classes\Download $oDownload
	 * @return bool
	 */
	public function createDownload(\Aurora\Modules\Sales\Classes\Download &$oDownload)
	{
		$bResult = false;
		try
		{
			if ($oDownload->validate())
			{
				if (!$this->isExists($oDownload))
				{
					if (!$this->oEavManager->saveEntity($oDownload))
					{
						throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::CustomerManager_CustomerCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CustomerExists);
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
	 * @param \Aurora\Modules\Sales\Classes\Download $oDownload
	 * @return bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function isExists(\Aurora\Modules\Sales\Classes\Download &$oDownload)
	{
		return !!$this->getDownloadByDownloadId($oDownload->DownloadId);
	}

	/**
	 * @param string $iId
	 * @return \Aurora\Modules\Sales\Classes\Download|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function getDownloadByDownloadId($iId)
	{
		$mCustomer = false;
		try
		{
			if (is_string($iId))
			{
				$aResults = $this->oEavManager->getEntities(
				\Aurora\System\Api::GetModule('Sales')->getNamespace() . '\Classes\Download',
					[],
					0,
					0,
					[
						'DownloadId' => $iId
					]
				);

				if (is_array($aResults) && isset($aResults[0]))
				{
					$mCustomer = $aResults[0];
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mCustomer = false;
			$this->setLastException($oException);
		}
		return $mCustomer;
	}
}