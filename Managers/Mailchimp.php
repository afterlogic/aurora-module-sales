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
class Mailchimp extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var \Aurora\System\Managers\Eav
	 */
	public $oEavManager = null;
	private $oMailchimpApi = null;
	private $oMailchimpList = null;

	/**
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule);

		$this->oEavManager = \Aurora\System\Managers\Eav::getInstance();
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
		if (!$this->oMailchimpList)
		{
			$aResults = $this->oEavManager->getEntities(\Aurora\Modules\Sales\Classes\MailchimpList::class,
				['Title', 'Description', 'ListId']);

			if (is_array($aResults) && isset($aResults[0]))
			{
				$this->oMailchimpList = $aResults[0];
			}
		}
		return $this->oMailchimpList;
	}

	/**
	 * @return \Aurora\Modules\Sales\Classes\MailchimpList $oMailchimpList|bool
	 */
	public function addMemberToList($sEmail)
	{
		$bResult = false;
		if (!filter_var($sEmail, FILTER_VALIDATE_EMAIL))
		{
			\Aurora\System\Api::Log('Error: Invalid input parameters', \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
		}
		else if (!$this->ping())
		{
			\Aurora\System\Api::Log('Error: Mailchimp connection failed. Member wasn\'t added to the list: ' . $sEmail, \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
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
					\Aurora\System\Api::Log('Member added to list: ' . $sEmail, \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
				}
				else
				{
					\Aurora\System\Api::Log('Error: addMemberToList ' . json_encode($aResponse), \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
				}
			}
			else
			{
				\Aurora\System\Api::Log('Error: Mailchimp List not found', \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
			}
		}
		return $bResult;
	}

	public function getGroups()
	{
		$aResult = [];
		if (!$this->ping())
		{
			\Aurora\System\Api::Log('Error: Mailchimp connection failed. Can\'t get group list ', \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
		}
		else
		{
			$oMailchimpList = $this->getMailchimpList();
			if ($oMailchimpList instanceof \Aurora\Modules\Sales\Classes\MailchimpList)
			{
				$Groups = $this->oMailchimpApi->get("lists/{$oMailchimpList->ListId}/interest-categories");
				if ($Groups && $Groups['categories'])
				{
					foreach ($Groups['categories'] as $aGroup)
					{
//						$aResult[$aGroup['id']] = [ 'title' => $aGroup['title']];
						$aGroupNames = $this->oMailchimpApi->get("lists/{$oMailchimpList->ListId}/interest-categories/{$aGroup['id']}/interests");
						if ($aGroupNames && isset($aGroupNames['interests']))
						{
							foreach ($aGroupNames['interests'] as $aGroupName)
							{
								$aResult[$aGroupName['id']] = $aGroupName['name'];
							}
						}
					}
				}
			}
		}

		return $aResult;
	}

	public function ping()
	{
		$mResult = false;
		if ($this->oMailchimpApi)
		{
			$this->oMailchimpApi->get('/ping');
			if ($this->oMailchimpApi->getLastError())
			{
				\Aurora\System\Api::Log('Error: Mailchimp connection failed. ' . $this->oMailchimpApi->getLastError(), \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
			}
			$mResult = true;
		}

		return $mResult;
	}

	public function getMembers()
	{
		$aResult = [];
		if (!$this->ping())
		{
			\Aurora\System\Api::Log('Error: Mailchimp connection failed. Can\'t get members list ', \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
		}
		else
		{
			$oMailchimpList = $this->getMailchimpList();
			if ($oMailchimpList instanceof \Aurora\Modules\Sales\Classes\MailchimpList)
			{
				$aMembers = $this->oMailchimpApi->get("lists/{$oMailchimpList->ListId}/members");
				if ($aMembers && $aMembers['members'])
				{
					foreach ($aMembers['members'] as $oMember)
					{
						$aResult[$oMember['email_address']] = $oMember['id'];
					}
				}
			}
		}

		return $aResult;
	}

	public function getMemberByEmail($sEmail)
	{
		$mResult = false;

		$sMemberId = md5(strtolower($sEmail));
		if (!empty($sMemberId))
		{
			$mResponse = $this->getMemberById($sMemberId);
			if (isset($mResponse['email_address']) && $mResponse['email_address'] === $sEmail)
			{
				$mResult = $mResponse;
			}
		}

		return $mResult;
	}

	public function getMemberById($id)
	{
		$mResult = false;

		$oMailchimpList = $this->getMailchimpList();
		if ($oMailchimpList instanceof \Aurora\Modules\Sales\Classes\MailchimpList)
		{
			$mResult = $this->oMailchimpApi->get("lists/{$oMailchimpList->ListId}/members/{$id}");
		}

		return $mResult;
	}

	public function updateMember($oMember)
	{
		$mResult = false;

		$oMailchimpList = $this->getMailchimpList();
		if ($oMailchimpList instanceof \Aurora\Modules\Sales\Classes\MailchimpList)
		{
			$mResult = $this->oMailchimpApi->patch("lists/{$oMailchimpList->ListId}/members/{$oMember['id']}", $oMember);
			if ($mResult)
			{
				\Aurora\System\Api::Log('Member information updated: ' . $oMember['email_address'], \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
			}
			else
			{
				\Aurora\System\Api::Log('Error: updateMember ' . json_encode($mResult), \Aurora\System\Enums\LogLevel::Full, 'mailchimp-');
			}
		}

		return $mResult;
	}
}
