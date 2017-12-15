<?php

use Sunra\PhpSimple\HtmlDomParser;

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
$sSearchStr = $oSalesModule->getConfig('PayPalSearchStr', '');

const ACCOUNT_CONNECT_TO_MAIL_SERVER_FAILED = 4003;
const ACCOUNT_LOGIN_FAILED = 4004;

$sParserIsRunning = \Aurora\System\Api::DataPath() . "/parser_is_running_paypal";
if (file_exists($sParserIsRunning))
{
	$bParserIsRunning = (int) @file_get_contents($sParserIsRunning);
	if ($bParserIsRunning === 1)
	{
		echo json_encode(["result" => false, "error_msg" => "Parser already ranned"]);
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
$sLastParsedUidPath = \Aurora\System\Api::DataPath() . "/last_parsed_paypal_uid";
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

			$sSearchCriterias = 'OR OR OR FROM "' . $sSearchStr . '" TO "' . $sSearchStr . '" CC "' . $sSearchStr . '" SUBJECT "' . $sSearchStr . '" UID ' . ($iLastParsedUid + 1) . ':*';
			$aIndexOrUids = $oImapClient->MessageSimpleSearch($sSearchCriterias, true);

			foreach ($aIndexOrUids as $UID)
			{
				if ($UID > $iLastParsedUid)
				{
					$oMessage = GetMessage($oImapClient, $sFolderFullNameRaw, $UID);
					$aData = ParseMessage($oMessage['Html'], $oMessage['Subject']);

					if (isset($aData['NetTotal']) && $aData['NetTotal'] !== 0 &&
						isset($aData['Email']) && $aData['Email'] !== '' &&
						isset($aData['RegName']) && $aData['RegName'] !== '' &&
						isset($aData['ProductName']) && $aData['ProductName'] !== '' &&
						isset($aData['ProductPayPalItem']) && $aData['ProductPayPalItem'] !== ''
					)
					{
						$oSalesModuleDecorator->CreateSale('PayPal', \Aurora\Modules\Sales\Enums\PaymentSystem::PayPal, $aData['NetTotal'],
							$aData['Email'], $aData['RegName'],
							$aData['ProductName'], null, null,
							isset($aData['TransactionId']) ? $aData['TransactionId'] : '',
							$oMessage['Date'], '', 0, 0, 0, false, true, false, 0, '',
							'', '', '', '', '', $aData['FullCity'],'', '', '', $aData['ProductPayPalItem'],
							$oMessage['Html'], \Aurora\Modules\Sales\Enums\RawDataType::Html
						);
						if ($UID > (int) @file_get_contents($sLastParsedUidPath))
						{
							file_put_contents($sLastParsedUidPath, $UID);
						}
					}
				}
			}
			echo json_encode(["result" => true]);
		}
		else
		{
			echo json_encode(["result" => false, "error_msg" => "Connect to mail server failed"]);
		}
	}
	else
	{
		echo json_encode(["result" => false, "error_msg" => "Connect to mail server failed"]);
	}
}
catch (\Exception $oEx)
{
	echo json_encode(["result" => false, "error_msg" => $oEx->getMessage()]);
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

function ParseMessage($sMessageHtml, $sSubject)
{
	$aResult = [];
	$dom = HtmlDomParser::str_get_html($sMessageHtml);

	$oTransactionId = $dom->find('table table a', 0);
	$aResult['TransactionId'] = trim($oTransactionId->plaintext);

	$oData = $dom->find('td.ppsans div div table', 0);
	$oBuyer = $oData->find('tr', 0)->find('td span', 1);
	$aResult['RegName'] = trim($oBuyer->plaintext);
	$oEmail = $oData->find('tr', 0)->find('td span', 2);
	$aResult['Email'] = trim($oEmail->plaintext);

	$oShipping = $oData->find('tr', 1)->find('td span', 0);
	$aShipping = explode("\r\n", trim($oShipping->plaintext));
	unset($aShipping[0]);
	$aResult['FullCity'] = preg_replace('/ {2,}/', ' ', trim(implode("; ", $aShipping)));

	$oData = $dom->find('td.ppsans div div table', 1)->find('tr', 1);
	$oDescription = $oData->find('td', 0);
	$aDescription = explode("\r\n", trim($oDescription->plaintext));
	$aResult['ProductName'] = trim($aDescription[0]);
	$aResult['ProductPayPalItem'] = trim(str_replace("Item# ", "", $aDescription[1]));
//	$oUnitPrice = $oData->find('td', 1);
//	$oQty = $oData->find('td', 2);
//	$oAmount = $oData->find('td', 3);

	$oData = $dom->find('td.ppsans div div table', 2);
	$oPaymentAmount = $oData->find('table tr', 2)->find('td', 1);
	$aResult['NetTotal'] = (int) preg_replace('/[^.0-9]/', ' ', $oPaymentAmount->plaintext);

	$dom->clear();
	unset($dom);
	return $aResult;
}