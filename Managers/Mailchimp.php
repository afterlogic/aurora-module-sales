<?php
/**
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 */

namespace Aurora\Modules\Sales\Managers;

/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */
class Mailchimp extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var \Aurora\System\Managers\Eav
	 */
	public $oEavManager = null;
	private $oMailchimpApi = null;

	/**
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule);

		$this->oEavManager = new \Aurora\System\Managers\Eav();
		if (!empty($this->GetModule()->getConfig('MailchimpApiKey', '')))
		{
			$this->oMailchimpApi = new \DrewM\MailChimp\MailChimp($this->GetModule()->getConfig('MailchimpApiKey', ''));
		}
	}

	/**
	 * @param \Aurora\Modules\Sales\Classes\MailchimpList $oMailchimpList
	 * @return int|bool
	 */
	public function createMailchimpList(\Aurora\Modules\Sales\Classes\MailchimpList &$oMailchimpList)
	{
		$mResult = $this->oEavManager->saveEntity($oMailchimpList);
		if (!$mResult)
		{
			throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::ProductCreateFailed);
		}
		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\Sales\Classes\MailchimpList $oMailchimpList
	 * @return bool
	 */
	public function updateMailchimpList(\Aurora\Modules\Sales\Classes\MailchimpList &$oMailchimpList)
	{
		$bResult = !!$this->oEavManager->saveEntity($oMailchimpList);
		if (!$bResult)
		{
			throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Sales\Enums\ErrorCodes::ProductUpdateFailed);
		}
		return $bResult;
	}

	/**
	 * @return \Aurora\Modules\Sales\Classes\MailchimpList $oMailchimpList|bool
	 */
	public function getMailchimpList()
	{
		$mMailchimpList = false;

		$aResults = $this->oEavManager->getEntities($this->GetModule()->getNamespace() . '\Classes\MailchimpList',
			['Title', 'Description', 'ListId']);

		if (is_array($aResults) && isset($aResults[0]))
		{
			$mMailchimpList = $aResults[0];
		}
		return $mMailchimpList;
	}

	/**
	 * @return \Aurora\Modules\Sales\Classes\MailchimpList $oMailchimpList|bool
	 */
	public function addMemeberToList($sEmail)
	{
		$bResult = false;
		if (!filter_var($sEmail, FILTER_VALIDATE_EMAIL))
		{
			\Aurora\System\Api::Log('Error: Invalid input parameters', \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
		}
		else if (!$this->ping())
		{
			\Aurora\System\Api::Log('Error: Mailchimp connection failed', \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
		}
		else
		{
			$oMailchimpList = $this->getMailchimpList();
			if ($oMailchimpList instanceof \Aurora\Modules\Sales\Classes\MailchimpList)
			{
				//only for tests, remove after release
				if (true || strpos($sEmail, "@afterlogic.com") !== false)
				{
					$aResponse = $this->oMailchimpApi->post(
						"lists/{$oMailchimpList->ListId}/members",
						[
							'email_address'	=>	$sEmail,
							'status'	=>	'subscribed'
						]
					);
				}
				else
				{
					\Aurora\System\Api::Log('Email skiped: ' . $sEmail, \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
				}

				if (isset($aResponse['status']) && $aResponse['status'] === 'subscribed')
				{
					$bResult = true;
					\Aurora\System\Api::Log('Memeber added to list: ' . $sEmail, \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
				}
				else
				{
					\Aurora\System\Api::Log('Error: addMemeberToList ' . json_encode($aResponse), \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
				}
			}
			else
			{
				\Aurora\System\Api::Log('Error: Mailchimp List not found', \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
			}
		}
		return $bResult;
	}

	public function ping()
	{
		$this->oMailchimpApi->get('/ping');
		if ($this->oMailchimpApi->getLastError())
		{
			return false;
		}
		return true;
	}
}
