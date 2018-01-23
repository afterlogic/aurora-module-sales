<?php
require_once __DIR__ . "/../../../system/autoload.php";
\Aurora\System\Api::Init(true);
set_time_limit(0);
\Aurora\System\Api::Log("Parser for ShareIt is started", \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
$oSalesModule = \Aurora\System\Api::GetModule('Sales');
$sIncomingServer =  $oSalesModule->getConfig('IncomingServer', '');
$iIncomingPort = 993;
$bIncomingUseSsl = true;
$bVerifySsl = false;
$sIncomingLogin = $oSalesModule->getConfig('IncomingLogin', '');
$sIncomingPassword = $oSalesModule->getConfig('IncomingPassword', '');
$sFolderFullNameRaw = $oSalesModule->getConfig('FolderFullNameRaw', '');
$sSearchFrom = $oSalesModule->getConfig('ShareItSearchFrom', '');
$sSearchSubject = $oSalesModule->getConfig('ShareItSearchSubject', '');

const ACCOUNT_CONNECT_TO_MAIL_SERVER_FAILED = 4003;
const ACCOUNT_LOGIN_FAILED = 4004;

$sParserIsRunning = \Aurora\System\Api::DataPath() . "/parser_is_running_shareit";
if (file_exists($sParserIsRunning) && !isset($_GET['forced']))
{
	$bParserIsRunning = (int) @file_get_contents($sParserIsRunning);
	if ($bParserIsRunning === 1)
	{
		\Aurora\System\Api::Log("Error: ShareIt-parser already running", \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
		echo json_encode(["result" => false, "error_msg" => "Parser already running"]);
		exit();
	}
	else
	{
		file_put_contents($sParserIsRunning, 1);
	}
}
else
{
	file_put_contents($sParserIsRunning, 1);
}
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
	if ($oImapClient->IsConnected())
	{
		$oImapClient->Login($sIncomingLogin, $sIncomingPassword, '');
		if ($oImapClient->IsLoggined())
		{
			$oImapClient->FolderExamine($sFolderFullNameRaw);

			$sSearchCriterias = 'FROM "' . $sSearchFrom . '"  SUBJECT "' . $sSearchSubject . '" UID ' . ($iLastParsedUid + 1) . ':*';
			$aIndexOrUids = $oImapClient->MessageSimpleSearch($sSearchCriterias, true);
			sort($aIndexOrUids);
			foreach ($aIndexOrUids as $UID)
			{
				if ($UID > $iLastParsedUid)
				{
					$oMessage = GetMessage($oImapClient, $sFolderFullNameRaw, $UID);
					$aData = ParseMessage($oMessage['Plain'], $oMessage['Subject']);
					\Aurora\System\Api::Log("Message {$UID} was parsed successfully." , \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
					$aAdressParts = [$aData['Street'], $aData['City'], $aData['State'], $aData['Zip'], $aData['Country']];
					$aAdressPartsClear = [];
					foreach ($aAdressParts as $sPart)
					{

						if (trim($sPart) !== '')
						{
							array_push($aAdressPartsClear, trim($sPart));
						}
					}

					$sAddress = implode(', ', $aAdressPartsClear);
					$oSalesModuleDecorator->CreateSale(
						isset($aData['Payment']) ? $aData['Payment'] : null,
						\Aurora\Modules\Sales\Enums\PaymentSystem::ShareIt,
						isset($aData['NetTotal']) ? $aData['NetTotal'] : null,
						isset($aData['Email']) ? $aData['Email'] : null,
						isset($aData['RegName']) ? $aData['RegName'] : null,
						isset($aData['ProductName']) ? $aData['ProductName'] : null,
						null, null,'',
						$oMessage['Date'],
						isset($aData['LicenseKey']) ? $aData['LicenseKey'] : null,
						isset($aData['RefNumber']) ? $aData['RefNumber'] : null,
						'',
						isset($aData['ShareItProductId']) ? $aData['ShareItProductId'] : null,
						isset($aData['ShareItPurchaseId']) ? $aData['ShareItPurchaseId'] : null,
						false, true, false, 0,
						isset($aData['VatId']) ? $aData['VatId'] : null,
						isset($aData['Salutation']) ? $aData['Salutation'] : null,
						isset($aData['Title']) ? $aData['Title'] : null,
						isset($aData['FirstName']) ? $aData['FirstName'] : null,
						isset($aData['LastName']) ? $aData['LastName'] : null,
						isset($aData['Company']) ? $aData['Company'] : null,
						$sAddress,
						isset($aData['Phone']) ? $aData['Phone'] : null,
						isset($aData['Fax']) ? $aData['Fax'] : null,
						isset($aData['Language']) ? $aData['Language'] : null, '',
						$oMessage['Plain'],
						\Aurora\Modules\Sales\Enums\RawDataType::PlainText,
						isset($aData['NumberOfLicenses']) ? $aData['NumberOfLicenses'] : null
					);
					if ($UID > (int) @file_get_contents($sLastParsedUidPath))
					{
						file_put_contents($sLastParsedUidPath, $UID);
					}
				}
			}
			\Aurora\System\Api::Log("Parser for ShareIt is stoped", \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
			echo json_encode(["result" => true]);
		}
		else
		{
			\Aurora\System\Api::Log("Error. Connect to mail server failed.", \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
			echo json_encode(["result" => false, "error_msg" => "Connect to mail server failed"]);
		}
	}
	else
	{
		\Aurora\System\Api::Log("Error. Connect to mail server failed.", \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
		echo json_encode(["result" => false, "error_msg" => "Connect to mail server failed"]);
	}
}
catch (\Exception $oEx)
{
	$oSalesModule = \Aurora\System\Api::GetModule('Sales');
	$sErrorMsg = (isset($oSalesModule->aErrors[$oEx->getCode()]) ? $oSalesModule->aErrors[$oEx->getCode()] : 'Unknown error');
	\Aurora\System\Api::Log("Error. " . $sErrorMsg, \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
	echo json_encode(["result" => false, "error_msg" => $sErrorMsg]);
}
file_put_contents($sParserIsRunning, 0);

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
	$aParamTypes = [
		'Program'				=> ['ShareItProductId', 'int'],
		'Number of licenses'	=> ['NumberOfLicenses', 'int'],
		'ShareIt Ref #'			=> ['ShareItPurchaseId', 'int'],
		'Salutation'			=> ['Salutation', 'string'],
		'Title'					=> ['Title', 'string'],
		'Last Name'				=> ['LastName', 'string'],
		'First Name'			=> ['FirstName', 'string'],
		'Company'				=> ['Company', 'string'],
		'Street'				=> ['Street', 'string'],
		'ZIP'					=> ['Zip', 'string'],
		'City'					=> ['City', 'string'],
		'FullCity'				=> ['FullCity', 'string'],
		'Country'				=> ['Country', 'string'],
		'State / Province'		=> ['State', 'string'],
		'Phone'					=> ['Phone', 'string'],
		'Fax'					=> ['Fax', 'string'],
		'E-Mail'				=> ['Email', 'string'],
		'VAT ID'				=> ['VatId', 'string'],
		'Payment'				=> ['Payment', 'string'],
		'Registration name'		=> ['RegName', 'string'],
		'Language'				=> ['Language', 'string'],
		'Total'					=> ['NetTotal', 'double'],
	];

	$LicenseKeysHeaders = ['WM700', 'MN110', 'MBC900', 'AU700'];
	$aResult = [];
	$aParams = [];
	preg_match_all('/^(?:.+)=(?:.+)/m', $sMessagePlainText, $aParams);

	if (isset($aParams[0]) && is_array($aParams[0]))
	{
		foreach ($aParams[0] as $sParamString)
		{
			$aParts = explode("=", $sParamString);
			if (is_array($aParts) && isset($aParts[0]) && isset($aParts[1]))
			{
				$sParamName = trim(array_shift($aParts));
				$sParamValue = trim(implode('=', $aParts));	// if value contains "=" - return them back
				if (isset($aParamTypes[$sParamName]))
				{
					switch ($sParamName)
					{
						case "Total":
							$aNetTotalParts = explode(" ", $sParamValue);
							if (is_array($aNetTotalParts) && isset($aNetTotalParts[count($aNetTotalParts) - 1]))
							{
								if(settype($sParamValue, $aParamTypes[$sParamName][1]))
								{
									$aResult[$aParamTypes[$sParamName][0]] = (double) filter_var($aNetTotalParts[count($aNetTotalParts) - 1], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
								}
							}
							break;
						default:
							//Type casting
							if(settype($sParamValue, $aParamTypes[$sParamName][1]))
							{
								$aResult[$aParamTypes[$sParamName][0]] = $sParamValue;
							}
					}
				}
			}
		}
	}
	//Name
	$aNameMatches = [];
	preg_match('/for +"(.+)"/', $sSubject, $aNameMatches);
	$aResult['ProductName'] = isset($aNameMatches[1]) ? trim($aNameMatches[1]) : '';
	//LicenseKey
	$aLicenseKeyMatches = [];
	preg_match("/(?:" . implode("|", $LicenseKeysHeaders) . ")-(?:[A-Z0-9-]*[\n\r])+/", $sMessagePlainText, $aLicenseKeyMatches);
	$aResult['LicenseKey'] =isset($aLicenseKeyMatches[0]) ? trim($aLicenseKeyMatches[0]) : '';
	//RefNumber
	$aSubjectMatches = [];
	preg_match('/Order No\.[\s]*([0-9]*)[\s]for/', $sSubject, $aSubjectMatches);
	$aResult['RefNumber'] = isset($aSubjectMatches[1]) ? (int) $aSubjectMatches[1] : '';

	return $aResult;
}