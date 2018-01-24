<?php

use Sunra\PhpSimple\HtmlDomParser;

require_once __DIR__ . "/../../../system/autoload.php";
\Aurora\System\Api::Init(true);
set_time_limit(0);
$oSalesModule = \Aurora\System\Api::GetModule('Sales');
$sIncomingServer =  $oSalesModule->getConfig('IncomingServer', '');
$iIncomingPort = 993;
$bIncomingUseSsl = true;
$bVerifySsl = false;
$sIncomingLogin = $oSalesModule->getConfig('IncomingLogin', '');
$sIncomingPassword = $oSalesModule->getConfig('IncomingPassword', '');
$sFolderFullNameRaw = $oSalesModule->getConfig('FolderFullNameRaw', '');
$sSearchFrom = $oSalesModule->getConfig('PayPalSearchFrom', '');
$sSearchSubject = $oSalesModule->getConfig('PayPalSearchSubject', '');

const ACCOUNT_CONNECT_TO_MAIL_SERVER_FAILED = 4003;
const ACCOUNT_LOGIN_FAILED = 4004;

$sParserIsRunning = \Aurora\System\Api::DataPath() . "/parser_is_running_paypal";
if (file_exists($sParserIsRunning) && !isset($_GET['forced']))
{
	$bParserIsRunning = (int) @file_get_contents($sParserIsRunning);
	if ($bParserIsRunning === 1)
	{
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

			$sSearchCriterias = 'OR FROM "' . $sSearchFrom . '"  SUBJECT "' . $sSearchSubject . '" UID ' . ($iLastParsedUid + 1) . ':*';
			$aIndexOrUids = $oImapClient->MessageSimpleSearch($sSearchCriterias, true);
			sort($aIndexOrUids);
			foreach ($aIndexOrUids as $UID)
			{
				if ($UID > $iLastParsedUid)
				{
					$oMessage = GetMessage($oImapClient, $sFolderFullNameRaw, $UID);
					$aData = ParseMessage($oMessage['Html'], $oMessage['Subject']);

					$oSalesModuleDecorator->CreateSale('PayPal', \Aurora\Modules\Sales\Enums\PaymentSystem::PayPal,
						isset($aData['NetTotal']) ? $aData['NetTotal'] : null,
						isset($aData['Email']) ? $aData['Email'] : null,
						isset($aData['RegName']) ? $aData['RegName'] : null,
						isset($aData['ProductName']) ? $aData['ProductName'] : null,
						null, null,
						isset($aData['TransactionId']) ? $aData['TransactionId'] : '',
						$oMessage['Date'],
						'', 0,  '', '', 0, false, true, false, 0, '', '', '', '', '', '',
						isset($aData['FullCity']) ? $aData['FullCity'] : null,
						'', '', '',
						isset($aData['ProductPayPalItem']) ? $aData['ProductPayPalItem'] : null,
						$oMessage['Eml'],
						null,
						$oMessage['Subject']
					);
					if ($UID > (int) @file_get_contents($sLastParsedUidPath))
					{
						file_put_contents($sLastParsedUidPath, $UID);
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
	$oSalesModule = \Aurora\System\Api::GetModule('Sales');
	echo json_encode(["result" => false, "error_msg" => (isset($oSalesModule->aErrors[$oEx->getCode()]) ? $oSalesModule->aErrors[$oEx->getCode()] : 'Unknown error')]);
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
	$sMimeType = 'message/rfc822';
	$sEml = '';
	directMessageToStream($oImapClient,
		function ($rResource, $sContentType, $sFileName) use (&$sMimeType, &$sEml) {
			if (is_resource($rResource))
			{
				$sMimeType = $sContentType;
				$sEml = @\stream_get_contents($rResource);
			}
		}, $Folder, $Uid
	);
	$sCharset = isset($sTextCharset) ? $sTextCharset : $sCharset;
	$sEml = \Aurora\System\Utils::ConvertEncoding($sEml, $sCharset, \MailSo\Base\Enumerations\Charset::UTF_8);
	return ['Html' => $sHtml, 'Plain' => $sPlain, 'Subject' => $sSubject, 'Date' => $sDate, 'Eml' => $sEml];
}

function ParseMessage($sMessageHtml, $sSubject)
{
	$aResult = [];
	$oDom = HtmlDomParser::str_get_html($sMessageHtml);

	if ($oDom)
	{
		$oTransactionId = $oDom->find('table table a', 0);
		$aResult['TransactionId'] = $oTransactionId ? trim($oTransactionId->plaintext) : '';

		$oData = $oDom->find('td.ppsans div div table', 0);
		$oData = $oData ? $oData : $oDom->find('td.ppsans div table', 1);
		if ($oData)
		{
			$oSubData =  $oData->find('tr', 0);
			$oBuyer = $oSubData ? $oSubData->find('td span', 1) : null;
			$aResult['RegName'] = $oBuyer ? trim($oBuyer->plaintext) : '';
			$oEmail =  $oSubData ? $oSubData->find('td span', 2) : null;
			$aResult['Email'] = $oEmail ? trim($oEmail->plaintext) : '';
			$oSubData = $oData->find('tr', 1);
			$oShipping =  $oSubData ? $oSubData->find('td span', 0) : null;
			$aShipping = $oShipping ? explode("\r\n", trim($oShipping->plaintext)) : [];
			unset($aShipping[0]);
			$aResult['FullCity'] = preg_replace('/ {2,}/', ' ', trim(implode("; ", $aShipping)));

			$oData = $oDom->find('td.ppsans div div table', 1);
			$oData = $oData ? $oData : $oDom->find('td.ppsans div table', 2);
			$oData = $oData ? $oData->find('tr', 1) : null;
			if ($oData)
			{
				$oDescription = $oData->find('td', 0);
				$aDescription = $oDescription ? explode("\r\n", trim($oDescription->plaintext)) : [];
				$aResult['ProductName'] = isset($aDescription[0]) ? trim($aDescription[0]) : '';
				$aResult['ProductPayPalItem'] = isset($aDescription[1]) ? trim(str_replace("Item# ", "", $aDescription[1])) : '';

				$oData = $oDom->find('td.ppsans div div table', 2);
				$oData = $oData ? $oData : $oDom->find('td.ppsans div table', 3);
				if ($oData)
				{
					$oSubData = $oData->find('table tr', 2);
					$oPaymentAmount =  $oSubData ? $oSubData->find('td', 1) : null;
					$aResult['NetTotal'] = $oPaymentAmount ? (int) preg_replace('/[^.0-9]/', ' ', $oPaymentAmount->plaintext) : '';
				}
			}
		}

		$oDom->clear();
		unset($oDom);
	}
	return $aResult;
}

function directMessageToStream($oImapClient, $mCallback, $sFolderName, $iUid, $sMimeIndex = '')
{
	if (!is_callable($mCallback))
	{
		throw new \MailSo\Base\Exceptions\InvalidArgumentException();
	}

	$oImapClient->FolderExamine($sFolderName);

	$sFileName = '';
	$sContentType = '';
	$sMailEncodingName = '';

	$sMimeIndex = trim($sMimeIndex);
	$aFetchResponse = $oImapClient->Fetch(array(
		0 === strlen($sMimeIndex)
			? \MailSo\Imap\Enumerations\FetchType::BODY_HEADER_PEEK
			: \MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$sMimeIndex.'.MIME]'
	), $iUid, true);

	if (0 < count($aFetchResponse))
	{
		$sMime = $aFetchResponse[0]->GetFetchValue(
			0 === strlen($sMimeIndex)
				? \MailSo\Imap\Enumerations\FetchType::BODY_HEADER
				: \MailSo\Imap\Enumerations\FetchType::BODY.'['.$sMimeIndex.'.MIME]'
		);

		if (!empty($sMime))
		{
			$oHeaders = \MailSo\Mime\HeaderCollection::NewInstance()->Parse($sMime);

			if (!empty($sMimeIndex))
			{
				$sFileName = $oHeaders->ParameterValue(
					\MailSo\Mime\Enumerations\Header::CONTENT_DISPOSITION,
					\MailSo\Mime\Enumerations\Parameter::FILENAME);

				if (empty($sFileName))
				{
					$sFileName = $oHeaders->ParameterValue(
						\MailSo\Mime\Enumerations\Header::CONTENT_TYPE,
						\MailSo\Mime\Enumerations\Parameter::NAME);
				}

				$sMailEncodingName = $oHeaders->ValueByName(
					\MailSo\Mime\Enumerations\Header::CONTENT_TRANSFER_ENCODING);

				$sContentType = $oHeaders->ValueByName(
					\MailSo\Mime\Enumerations\Header::CONTENT_TYPE);
			}
			else
			{
				$sSubject = trim($oHeaders->ValueByName(\MailSo\Mime\Enumerations\Header::SUBJECT));
				$sFileName = (empty($sSubject) ? 'message-'.$iUid : trim($sSubject)).'.eml';
				$sFileName = '.eml' === $sFileName ? 'message.eml' : $sFileName;
				$sContentType = 'message/rfc822';
			}
		}
	}

	$aFetchResponse = $oImapClient->Fetch(array(
		array(\MailSo\Imap\Enumerations\FetchType::BODY_PEEK.'['.$sMimeIndex.']',
			function ($sParent, $sLiteralAtomUpperCase, $rImapLiteralStream) use ($mCallback, $sMimeIndex, $sMailEncodingName, $sContentType, $sFileName)
			{
				if (!empty($sLiteralAtomUpperCase))
				{
					if (is_resource($rImapLiteralStream) && 'FETCH' === $sParent)
					{
						$rMessageMimeIndexStream = (empty($sMailEncodingName))
							? $rImapLiteralStream
							: \MailSo\Base\StreamWrappers\Binary::CreateStream($rImapLiteralStream,
								\MailSo\Base\StreamWrappers\Binary::GetInlineDecodeOrEncodeFunctionName(
									$sMailEncodingName, true));

						call_user_func($mCallback, $rMessageMimeIndexStream, $sContentType, $sFileName, $sMimeIndex);
					}
				}
			}
		)), $iUid, true);

	return ($aFetchResponse && 1 === count($aFetchResponse));
}
