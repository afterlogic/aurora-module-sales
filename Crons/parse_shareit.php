<?php
require_once __DIR__ . "/../../../system/autoload.php";
\Aurora\System\Api::Init(true);

$oSalesModule = \Aurora\System\Api::GetModule('Sales');
$sIncomingServer =  $oSalesModule->getConfig('IncomingServer', '');
$iIncomingPort = 993;
$bIncomingUseSsl = true;
$bVerifySsl = false;
$sIncomingLogin = $oSalesModule->getConfig('IncomingLogin', '');
$sIncomingPassword = $oSalesModule->getConfig('IncomingPassword', '');
$sFolderFullNameRaw = $oSalesModule->getConfig('FolderFullNameRaw', '');
$sSearchStr = $oSalesModule->getConfig('ShareItSearchStr', '');

const ACCOUNT_CONNECT_TO_MAIL_SERVER_FAILED = 4003;
const ACCOUNT_LOGIN_FAILED = 4004;

$sLastParsedUidPath = \Aurora\System\Api::DataPath() . "/last_parsed_shareit_uid";
if (file_exists($sLastParsedUidPath))
{
	$iLastParsedUid = (int) @file_get_contents($sLastParsedUidPath);
}
if (!isset($iLastParsedUid))
{
	$iLastParsedUid = 0;
}

$oSalesModuleDecorator = \Aurora\System\Api::GetModuleDecorator('Sales');
$oImapClient = \MailSo\Imap\ImapClient::NewInstance();
$oImapClient->SetTimeOuts(10, 20);
$oImapClient->SetLogger(\Aurora\System\Api::SystemLogger());

try
{
	$oImapClient->Connect($sIncomingServer, $iIncomingPort, $bIncomingUseSsl
			? \MailSo\Net\Enumerations\ConnectionSecurityType::SSL
			: \MailSo\Net\Enumerations\ConnectionSecurityType::NONE, $bVerifySsl);
}
catch (\Exception $oEx)
{
	throw new \Aurora\System\Exceptions\Exception(
		$oEx->getMessage(),
		ACCOUNT_CONNECT_TO_MAIL_SERVER_FAILED,
		$oEx
	);

}

if ($oImapClient->IsConnected())
{
	try
	{
		$oImapClient->Login($sIncomingLogin, $sIncomingPassword, '');
	}
	catch (\MailSo\Imap\Exceptions\LoginBadCredentialsException $oEx)
	{
		throw new \Aurora\System\Exceptions\Exception(
			$oEx->getMessage(),
			ACCOUNT_LOGIN_FAILED,
			$oEx
		);
	}
}
else
{
	throw new \Aurora\System\Exceptions\Exception(
		$oEx->getMessage(),
		ACCOUNT_CONNECT_TO_MAIL_SERVER_FAILED,
		$oEx
	);
}

if ($oImapClient->IsLoggined())
{
	$oImapClient->FolderExamine($sFolderFullNameRaw);

	$sSearchCriterias = 'OR OR OR FROM "' . $sSearchStr . '" TO "' . $sSearchStr . '" CC "' . $sSearchStr . '" SUBJECT "' . $sSearchStr . '" UID ' . ($iLastParsedUid + 1) . ':*';
	$aIndexOrUids = $oImapClient->MessageSimpleSearch($sSearchCriterias, true);

	foreach ($aIndexOrUids as $UID)
	{
		if ($UID > $iLastParsedUid)
		{
			$oMessage = GetMessage($oImapClient, $sFolderFullNameRaw, $UID);
			$aData = ParseMessage($oMessage['Plain'], $oMessage['Subject']);
			if (isset($aData['Payment']) && $aData['Payment'] !== '' &&
				isset($aData['NetTotal']) && $aData['NetTotal'] !== 0 &&
				isset($aData['Email']) && $aData['Email'] !== '' &&
				isset($aData['RegName']) && $aData['RegName'] !== '' &&
				isset($aData['ProductName']) && $aData['ProductName'] !== ''
			)
			{
				$sAddress = implode(', ', [$aData['Street'], $aData['City'], $aData['State'], $aData['Zip'], $aData['Country']]);
				$oSalesModuleDecorator->CreateSale($aData['Payment'], \Aurora\Modules\Sales\Enums\PaymentSystem::ShareIt, $aData['NetTotal'],
					$aData['Email'], $aData['RegName'],
					$aData['ProductName'], null, null,
					'',
					$oMessage['Date'], $aData['LicenseKey'], $aData['RefNumber'], $aData['ShareItProductId'], $aData['ShareItPurchaseId'], false, true, false, 0, $aData['VatId'],
					$aData['Salutation'], $aData['Title'], $aData['FirstName'], $aData['LastName'], $aData['Company'], $sAddress, $aData['Phone'], $aData['Fax'], $aData['Language'], '',
					$oMessage['Plain'], \Aurora\Modules\Sales\Enums\RawDataType::PlainText
				);
				if ($UID > (int) @file_get_contents($sLastParsedUidPath))
				{
					file_put_contents($sLastParsedUidPath, $UID);
				}
			}
		}
	}
	
}
else
{
	throw new \Aurora\System\Exceptions\Exception(
		$oEx->getMessage(),
		ACCOUNT_CONNECT_TO_MAIL_SERVER_FAILED,
		$oEx
	);
}

function _getFolderInformation($oImapClient, $sFolderFullNameRaw)
{
	$aFolderStatus = $oImapClient->FolderStatus($sFolderFullNameRaw, array(
		\MailSo\Imap\Enumerations\FolderResponseStatus::MESSAGES,
		\MailSo\Imap\Enumerations\FolderResponseStatus::UNSEEN,
		\MailSo\Imap\Enumerations\FolderResponseStatus::UIDNEXT
	));

	$iStatusMessageCount = isset($aFolderStatus[\MailSo\Imap\Enumerations\FolderResponseStatus::MESSAGES])
		? (int) $aFolderStatus[\MailSo\Imap\Enumerations\FolderResponseStatus::MESSAGES] : 0;

	$iStatusMessageUnseenCount = isset($aFolderStatus[\MailSo\Imap\Enumerations\FolderResponseStatus::UNSEEN])
		? (int) $aFolderStatus[\MailSo\Imap\Enumerations\FolderResponseStatus::UNSEEN] : 0;

	$sStatusUidNext = isset($aFolderStatus[\MailSo\Imap\Enumerations\FolderResponseStatus::UIDNEXT])
		? (string) $aFolderStatus[\MailSo\Imap\Enumerations\FolderResponseStatus::UIDNEXT] : '0';

	if (0 === strlen($sStatusUidNext))
	{
		$sStatusUidNext = '0';
	}

	// gmail hack
	$oFolder = $oImapClient->FolderCurrentInformation();
	if ($oFolder && null !== $oFolder->Exists && $oFolder->FolderName === $sFolderFullNameRaw)
	{
		$iSubCount = (int) $oFolder->Exists;
		if (0 < $iSubCount && $iSubCount < $iStatusMessageCount)
		{
			$iStatusMessageCount = $iSubCount;
		}
	}

	return array($iStatusMessageCount, $iStatusMessageUnseenCount, $sStatusUidNext,
		\Aurora\System\Utils::GenerateFolderHash($sFolderFullNameRaw, $iStatusMessageCount, $iStatusMessageUnseenCount, $sStatusUidNext));
}

function GetMessage($oImapClient, $Folder, $Uid, $Rfc822MimeIndex = '')
{
	$sDate = null;
	$sSubject = '';
	$iBodyTextLimit = 600000;

	$iUid = 0 < \strlen($Uid) && \is_numeric($Uid) ? (int) $Uid : 0;

	if (0 === \strlen(\trim($Folder)) || 0 >= $iUid)
	{
		throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
	}

	if (0 === \strlen($Folder) || !\is_numeric($iUid) || 0 >= (int) $iUid)
	{
		throw new \Aurora\System\Exceptions\InvalidArgumentException();
	}

	$oImapClient->FolderExamine($Folder);

	$aTextMimeIndexes = array();

	$aFetchResponse = $oImapClient->Fetch(array(
		\MailSo\Imap\Enumerations\FetchType::BODYSTRUCTURE), $iUid, true);

	$oBodyStructure = (0 < \count($aFetchResponse)) ? $aFetchResponse[0]->GetFetchBodyStructure($Rfc822MimeIndex) : null;
	
	$aCustomParts = array();
	if ($oBodyStructure)
	{
		$aTextParts = $oBodyStructure->SearchHtmlOrPlainParts();
		if (\is_array($aTextParts) && 0 < \count($aTextParts))
		{
			foreach ($aTextParts as $oPart)
			{
				$aTextMimeIndexes[] = array($oPart->PartID(), $oPart->Size());
			}
		}

		$aParts = $oBodyStructure->GetAllParts();
	}

	$aFetchItems = array(
		\MailSo\Imap\Enumerations\FetchType::INDEX,
		\MailSo\Imap\Enumerations\FetchType::UID,
		\MailSo\Imap\Enumerations\FetchType::RFC822_SIZE,
		\MailSo\Imap\Enumerations\FetchType::INTERNALDATE,
		\MailSo\Imap\Enumerations\FetchType::FLAGS,
		0 < strlen($Rfc822MimeIndex)
			? \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$Rfc822MimeIndex.'.HEADER]'
			: \MailSo\Imap\Enumerations\FetchType::BODY_HEADER_PEEK
	);

	if (0 < \count($aTextMimeIndexes))
	{
		if (0 < \strlen($Rfc822MimeIndex) && \is_numeric($Rfc822MimeIndex))
		{
			$sLine = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$aTextMimeIndexes[0][0].'.1]';
			if (\is_numeric($iBodyTextLimit) && 0 < $iBodyTextLimit && $iBodyTextLimit < $aTextMimeIndexes[0][1])
			{
				$sLine .= '<0.'.((int) $iBodyTextLimit).'>';
			}

			$aFetchItems[] = $sLine;
		}
		else
		{
			foreach ($aTextMimeIndexes as $aTextMimeIndex)
			{
				$sLine = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$aTextMimeIndex[0].']';
				if (\is_numeric($iBodyTextLimit) && 0 < $iBodyTextLimit && $iBodyTextLimit < $aTextMimeIndex[1])
				{
					$sLine .= '<0.'.((int) $iBodyTextLimit).'>';
				}

				$aFetchItems[] = $sLine;
			}
		}
	}

	foreach ($aCustomParts as $oCustomPart)
	{
		$aFetchItems[] = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$oCustomPart->PartID().']';
	}

	if (!$oBodyStructure)
	{
		$aFetchItems[] = \MailSo\Imap\Enumerations\FetchType::BODYSTRUCTURE;
	}

	$aFetchResponse = $oImapClient->Fetch($aFetchItems, $iUid, true);

	$sHeaders = trim($aFetchResponse[0]->GetHeaderFieldsValue($Rfc822MimeIndex));
	$sCharset = $oBodyStructure ? $oBodyStructure->SearchCharset() : '';
	if (!empty($sHeaders))
	{
		$oHeaders = \MailSo\Mime\HeaderCollection::NewInstance()->Parse($sHeaders, false, $sCharset);
		$bCharsetAutoDetect = 0 === \strlen($sCharset);
		$sSubject = $oHeaders->ValueByName(\MailSo\Mime\Enumerations\Header::SUBJECT, $bCharsetAutoDetect);

		$dt = new \DateTime($oHeaders->ValueByName(\MailSo\Mime\Enumerations\Header::DATE, $bCharsetAutoDetect), new \DateTimeZone('UTC'));
		$sDate = $dt->format('Y-m-d H:i:s');
	}
	
	if (0 < \count($aTextMimeIndexes))
	{
		if (0 < \strlen($Rfc822MimeIndex) && \is_numeric($Rfc822MimeIndex))
		{
			$sLine = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$aTextMimeIndexes[0][0].'.1]';
			if (\is_numeric($iBodyTextLimit) && 0 < $iBodyTextLimit && $iBodyTextLimit < $aTextMimeIndexes[0][1])
			{
				$sLine .= '<0.'.((int) $iBodyTextLimit).'>';
			}

			$aFetchItems[] = $sLine;
		}
		else
		{
			foreach ($aTextMimeIndexes as $aTextMimeIndex)
			{
				$sLine = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$aTextMimeIndex[0].']';
				if (\is_numeric($iBodyTextLimit) && 0 < $iBodyTextLimit && $iBodyTextLimit < $aTextMimeIndex[1])
				{
					$sLine .= '<0.'.((int) $iBodyTextLimit).'>';
				}

				$aFetchItems[] = $sLine;
			}
		}
	}

	foreach ($aCustomParts as $oCustomPart)
	{
		$aFetchItems[] = \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$oCustomPart->PartID().']';
	}

	if (!$oBodyStructure)
	{
		$aFetchItems[] = \MailSo\Imap\Enumerations\FetchType::BODYSTRUCTURE;
	}

	$aFetchResponse = $oImapClient->Fetch($aFetchItems, $iUid, true);
	if (0 < \count($aFetchResponse))
	{
		$sPlain = '';
		$sHtml = '';
		$sCharset = $oBodyStructure ? $oBodyStructure->SearchCharset() : '';
		$sCharset = \MailSo\Base\Utils::NormalizeCharset($sCharset);

		$aTextParts = $oBodyStructure ? $oBodyStructure->SearchHtmlOrPlainParts() : array();
		if (is_array($aTextParts) && 0 < count($aTextParts))
		{
			if (0 === \strlen($sCharset))
			{
				$sCharset = \MailSo\Base\Enumerations\Charset::UTF_8;
			}

			$sHtmlParts = array();
			$sPlainParts = array();

			$iHtmlSize = 0;
			$iPlainSize = 0;
			foreach ($aTextParts as /* @var $oPart \MailSo\Imap\BodyStructure */ $oPart)
			{
				if ($oPart)
				{
					if ('text/html' === $oPart->ContentType())
					{
						$iHtmlSize += $oPart->EstimatedSize();
					}
					else
					{
						$iPlainSize += $oPart->EstimatedSize();
					}
				}

				$sText = $aFetchResponse[0]->GetFetchValue(\MailSo\Imap\Enumerations\FetchType::BODY.'['.$oPart->PartID().
					('' !== $Rfc822MimeIndex && is_numeric($Rfc822MimeIndex) ? '.1' : '').']');

				if (is_string($sText) && 0 < strlen($sText))
				{
					$sTextCharset = $oPart->Charset();
					if (empty($sTextCharset))
					{
						$sTextCharset = $sCharset;
					}

					$sTextCharset = \MailSo\Base\Utils::NormalizeCharset($sTextCharset, true);

					$sText = \MailSo\Base\Utils::DecodeEncodingValue($sText, $oPart->MailEncodingName());
					$sText = \MailSo\Base\Utils::ConvertEncoding($sText, $sTextCharset, \MailSo\Base\Enumerations\Charset::UTF_8);
					$sText = \MailSo\Base\Utils::Utf8Clear($sText);

					if ('text/html' === $oPart->ContentType())
					{
						$sHtmlParts[] = $sText;
					}
					else
					{
						$sPlainParts[] = $sText;
					}
				}
			}

			if (0 < count($sHtmlParts))
			{
				$sHtml = trim(implode('<br />', $sHtmlParts));
			}
			else
			{
				$sPlain = trim(implode("\n", $sPlainParts));
			}

			unset($sHtmlParts, $sPlainParts);
		}

	}

	return ['Html' => $sHtml, 'Plain' => $sPlain, 'Subject' => $sSubject, 'Date' => $sDate];
}

function ParseMessage($sMessagePlainText, $sSubject)
{
	$aParams = [
		'ShareItProductId'	=> [8, 'int'],
		'NumberOfLicenses'	=> [10, 'int'],
		'ShareItPurchaseId'	=> [11, 'int'],
		'Salutation'			=> [25, 'string'],
		'Title'				=> [26, 'string'],
		'LastName'			=> [27, 'string'],
		'FirstName'		=> [28, 'string'],
		'Company'			=> [29, 'string'],
		'Street'			=> [30, 'string'],
		'Zip'				=> [31, 'string'],
		'City'				=> [32, 'string'],
		'FullCity'			=> [33, 'string'],
		'Country'			=> [34, 'string'],
		'State'			=> [35, 'string'],
		'Phone'			=> [36, 'string'],
		'Fax'				=> [37, 'string'],
		'Email'			=> [38, 'string'],
		'VatId'			=> [39, 'string'],
		'Payment'			=> [40, 'string'],
		'RegName'			=> [41, 'string'],
		'Language'			=> [42, 'string']
	];
	$aResult = [];
	$aStrings = explode("\r\n", $sMessagePlainText);

	foreach ($aParams as $sParamName => $aParamProperties)
	{
		$aParts = explode("=", $aStrings[$aParamProperties[0]]);
		if (is_array($aParts) && isset($aParts[1]))
		{
			//Type casting
			if(settype( $aParts[1], $aParamProperties[1]))
			{
				$aResult[$sParamName] = $aParts[1];
			}
		}
	}
	//Name
	$aMatches = [];
	preg_match('/\"([\s\S]*)\"/', $aStrings[2], $aMatches);
	$aResult['ProductName'] = isset($aMatches[1]) ? $aMatches[1] : '';
	//Total
	$aNetTotalParts = explode("=", $aStrings[21]);
	if (is_array($aNetTotalParts) && isset($aNetTotalParts[1]))
	{
		$aNetTotalParts = explode(" ", trim($aNetTotalParts[1]));
		if (is_array($aNetTotalParts) && isset($aNetTotalParts[count($aNetTotalParts) - 1]))
		{
			$aResult['NetTotal'] = (float) $aNetTotalParts[count($aNetTotalParts) - 1];
		}
	}
	//LicenseKey
	$aResult['LicenseKey'] = trim($aStrings[48]);
	//RefNumber
	$aSubjectMatches = [];
	preg_match('/Order No\.[\s]*([0-9]*)[\s]for/', $sSubject, $aSubjectMatches);
	$aResult['RefNumber'] = isset($aSubjectMatches[1]) ? (int) $aSubjectMatches[1] : '';

	return $aResult;
}