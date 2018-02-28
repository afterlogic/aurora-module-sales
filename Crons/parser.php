<?php
require_once __DIR__ . "/../../../system/autoload.php";
\Aurora\System\Api::Init(true);
set_time_limit(0);

class CrmParser
{
	public $oSalesModule = null;
	public $oImapClient = null;
	public $sFromPaypal = null;
	public $sSubjectPaypal = null;
	public $sFromShareit = null;
	public $sSubjectShareit = null;
	public $sParserIsRunning = null;
	public $sLastParsedUidPath = null;
	public $sIncomingServer = null;
	public $iIncomingPort = 993;
	public $bIncomingUseSsl = true;
	public $bVerifySsl = false;
	public $sIncomingLogin = null;
	public $sIncomingPassword = null;
	public $sFolderFullNameRaw = null;

	public function Init()
	{
		$this->oSalesModule = \Aurora\System\Api::GetModule('Sales');
		$this->sIncomingServer =  $this->oSalesModule->getConfig('IncomingServer', '');
		$this->sIncomingLogin = $this->oSalesModule->getConfig('IncomingLogin', '');
		$this->sIncomingPassword = \Aurora\System\Utils::DecryptValue($this->oSalesModule->getConfig('IncomingPassword', ''));
		$this->sFolderFullNameRaw = $this->oSalesModule->getConfig('FolderFullNameRaw', '');
		$this->sFromPaypal = $this->oSalesModule->getConfig('PayPalSearchFrom', '');
		$this->sSubjectPaypal = $this->oSalesModule->getConfig('PayPalSearchSubject', '');
		$this->sFromShareit = $this->oSalesModule->getConfig('ShareItSearchFrom', '');
		$this->sSubjectShareit = $this->oSalesModule->getConfig('ShareItSearchSubject', '');
		$this->oImapClient = \MailSo\Imap\ImapClient::NewInstance();
		$this->oImapClient->SetTimeOuts(10, 20);
		$this->oImapClient->SetLogger(\Aurora\System\Api::SystemLogger());
		$this->sParserIsRunning = \Aurora\System\Api::DataPath() . "/parser_is_running";
		$this->sLastParsedUidPath = \Aurora\System\Api::DataPath() . "/last_parsed_uid";
	}

	public function Start()
	{
		$this->Init();
		if (file_exists($this->sParserIsRunning) && !isset($_GET['forced']))
		{
			$bParserIsRunning = (int) @file_get_contents($this->sParserIsRunning);
			if ($bParserIsRunning === 1)
			{
				\Aurora\System\Api::Log("Error: parser already running", \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
				echo json_encode(["result" => false, "error_msg" => "Parser already running"]);
				exit();
			}
			else
			{
				file_put_contents($this->sParserIsRunning, 1);
			}
		}
		else
		{
			file_put_contents($this->sParserIsRunning, 1);
		}

		if (file_exists($this->sLastParsedUidPath))
		{
			$iLastParsedUid = (int) @file_get_contents($this->sLastParsedUidPath);
		}
		if (!isset($iLastParsedUid))
		{
			$iLastParsedUid = 0;
		}
		$oSalesModuleDecorator = \Aurora\System\Api::GetModuleDecorator('Sales');
		\Aurora\System\Api::Log("Parser is started", \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
		try
		{
			$this->oImapClient->Connect($this->sIncomingServer, $this->iIncomingPort, $this->bIncomingUseSsl
					? \MailSo\Net\Enumerations\ConnectionSecurityType::SSL
					: \MailSo\Net\Enumerations\ConnectionSecurityType::NONE, $this->bVerifySsl);
			if ($this->oImapClient->IsConnected())
			{
				$this->oImapClient->Login($this->sIncomingLogin, $this->sIncomingPassword, '');
				if ($this->oImapClient->IsLoggined())
				{
					$this->oImapClient->FolderExamine($this->sFolderFullNameRaw);

					$sSearchCriterias = ' UID ' . ($iLastParsedUid + 1) . ':*';
					$aIndexOrUids = $this->oImapClient->MessageSimpleSearch($sSearchCriterias, true);
					sort($aIndexOrUids);
					foreach ($aIndexOrUids as $UID)
					{
						if ($UID > $iLastParsedUid)
						{
							$oMessage = $this->GetMessage($this->sFolderFullNameRaw, $UID);
							$aData = $this->ParseMessage($oMessage, $UID);

							$oSalesModuleDecorator->CreateSale(
								isset($aData['Payment']) ? $aData['Payment'] : null,
								isset($aData['PaymentSystem']) ? $aData['PaymentSystem'] : null,
								isset($aData['NetTotal']) ? $aData['NetTotal'] : null,
								isset($aData['Email']) ? $aData['Email'] : null,
								isset($aData['RegName']) ? $aData['RegName'] : null,
								isset($aData['ProductName']) ? $aData['ProductName'] : null,
								null, null,
								isset($aData['TransactionId']) ? $aData['TransactionId'] : '',
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
								isset($aData['FullCity']) ? $aData['FullCity'] : null,
								isset($aData['Phone']) ? $aData['Phone'] : null,
								isset($aData['Fax']) ? $aData['Fax'] : null,
								isset($aData['Language']) ? $aData['Language'] : null,
								isset($aData['ProductPayPalItem']) ? $aData['ProductPayPalItem'] : null,
								$oMessage['Eml'],
								isset($aData['NumberOfLicenses']) ? $aData['NumberOfLicenses'] : null,
								$oMessage['Subject'],
								isset($aData['ParsingStatus']) ? $aData['ParsingStatus'] : \Aurora\Modules\Sales\Enums\ParsingStatus::NotParsed,
								isset($aData['Reseller']) ? $aData['Reseller'] : null,
								isset($aData['PromotionName']) ? $aData['PromotionName'] : null
							);
							if ($UID > (int) @file_get_contents($this->sLastParsedUidPath))
							{
								file_put_contents($this->sLastParsedUidPath, $UID);
							}
						}
					}
					\Aurora\System\Api::Log("Parser is stoped", \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
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
			$bIsKnownError = isset($this->oSalesModule->aErrors[$oEx->getCode()]);
			\Aurora\System\Api::Log("Error. " . ($bIsKnownError ? $this->oSalesModule->aErrors[$oEx->getCode()] : $oEx->getMessage()),
					\Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
			echo json_encode(["result" => false, "error_msg" => ($bIsKnownError ? $this->oSalesModule->aErrors[$oEx->getCode()] : 'Unknown error')]);
		}
		file_put_contents($this->sParserIsRunning, 0);
	}

	public function GetMessage($Folder, $Uid, $Rfc822MimeIndex = '')
	{
		$sDate = null;
		$sSubject = '';
		$sFrom = '';
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

		$this->oImapClient->FolderExamine($Folder);

		$aTextMimeIndexes = array();

		$aFetchResponse = $this->oImapClient->Fetch(array(
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

		$aFetchResponse = $this->oImapClient->Fetch($aFetchItems, $iUid, true);

		$sHeaders = trim($aFetchResponse[0]->GetHeaderFieldsValue($Rfc822MimeIndex));
		$sCharset = $oBodyStructure ? $oBodyStructure->SearchCharset() : '';
		if (!empty($sHeaders))
		{
			$oHeaders = \MailSo\Mime\HeaderCollection::NewInstance()->Parse($sHeaders, false, $sCharset);
			$bCharsetAutoDetect = 0 === \strlen($sCharset);
			$sSubject = $oHeaders->ValueByName(\MailSo\Mime\Enumerations\Header::SUBJECT, $bCharsetAutoDetect);
			$sFrom = $oHeaders->ValueByName(\MailSo\Mime\Enumerations\Header::FROM_, $bCharsetAutoDetect);

			$dt = new \DateTime($oHeaders->ValueByName(\MailSo\Mime\Enumerations\Header::DATE, $bCharsetAutoDetect));
			$dt->setTimezone(new \DateTimeZone('UTC'));
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

		$aFetchResponse = $this->oImapClient->Fetch($aFetchItems, $iUid, true);
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
		$this->directMessageToStream(function ($rResource, $sContentType, $sFileName) use (&$sMimeType, &$sEml) {
				if (is_resource($rResource))
				{
					$sMimeType = $sContentType;
					$sEml = @\stream_get_contents($rResource);
				}
			}, $Folder, $Uid
		);

		return ['Html' => $sHtml, 'Plain' => $sPlain, 'Subject' => $sSubject, 'From' => $sFrom, 'Date' => $sDate, 'Eml' => $sEml];
	}

	public function directMessageToStream($mCallback, $sFolderName, $iUid, $sMimeIndex = '')
	{
		if (!is_callable($mCallback))
		{
			throw new \MailSo\Base\Exceptions\InvalidArgumentException();
		}

		$this->oImapClient->FolderExamine($sFolderName);

		$sFileName = '';
		$sContentType = '';
		$sMailEncodingName = '';

		$sMimeIndex = trim($sMimeIndex);
		$aFetchResponse = $this->oImapClient->Fetch(array(
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

		$aFetchResponse = $this->oImapClient->Fetch(array(
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

	public function ParseMessage($aMessage, $UID)
	{
		$aData = [];
		$sLogMessage = "Message {$UID} saved without parsing.";
		if (isset($aMessage['From']))
		{
			if (strpos($aMessage['From'], $this->sFromPaypal) !== false)
			{
				$aData = $this->ParseMessagePaypal($aMessage['Html'], $aMessage['Subject']);
				$sLogMessage = "Message {$UID} was parsed with ParseMessagePaypal.";
			}
			elseif (strpos($aMessage['From'], $this->sFromShareit) !== false && strpos($aMessage['Subject'], $this->sSubjectShareit) !== false)
			{
				$aData = $this->ParseMessageShareit($aMessage['Plain'], $aMessage['Subject']);
				$sLogMessage = "Message {$UID} was parsed with ParseMessageShareit.";
			}
		}
		\Aurora\System\Api::Log($sLogMessage , \Aurora\System\Enums\LogLevel::Full, 'sales-parsing-');
		return $aData;
	}

	public function ParseMessageShareit($sMessagePlainText, $sSubject)
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
			'Reseller'				=> ['Reseller', 'string'],
			'Promotion name'		=> ['PromotionName', 'string'],
		];

		$LicenseKeysHeaders = ['WM', 'MN', 'MBC', 'AU'];
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
		preg_match("/(?:" . implode("|", $LicenseKeysHeaders) . ")[0-9]+-(?:[A-Z0-9-]*[\n\r])+/", $sMessagePlainText, $aLicenseKeyMatches);
		$aResult['LicenseKey'] =isset($aLicenseKeyMatches[0]) ? trim($aLicenseKeyMatches[0]) : '';
		//RefNumber
		$aSubjectMatches = [];
		preg_match('/Order No\.[\s]*([0-9]*)[\s]for/', $sSubject, $aSubjectMatches);
		$aResult['RefNumber'] = isset($aSubjectMatches[1]) ? (int) $aSubjectMatches[1] : '';

		$aAdressParts = [
			isset($aResult['Street']) ? $aResult['Street'] : '',
			isset($aResult['City']) ? $aResult['City'] : '',
			isset($aResult['State']) ? $aResult['State'] : '',
			isset($aResult['Zip']) ? $aResult['Zip'] : '',
			isset($aResult['Country']) ? $aResult['Country'] : ''
		];
		$aAdressPartsClear = [];
		foreach ($aAdressParts as $sPart)
		{

			if (trim($sPart) !== '')
			{
				array_push($aAdressPartsClear, trim($sPart));
			}
		}
		$aResult['FullCity'] = implode(', ', $aAdressPartsClear);
		$aResult['PaymentSystem'] = \Aurora\Modules\Sales\Enums\PaymentSystem::ShareIt;
		$aResult['ParsingStatus'] = \Aurora\Modules\Sales\Enums\ParsingStatus::ParsedWithShareItSuccesfully;
		return $aResult;
	}

	public function ParseMessagePaypal($sMessageHtml, $sSubject)
	{
		$aResult = [];
		$oDom = \Sunra\PhpSimple\HtmlDomParser::str_get_html($sMessageHtml);

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
						$aResult['NetTotal'] = $oPaymentAmount ? (double) preg_replace('/[^.0-9]/', ' ', $oPaymentAmount->plaintext) : '';
					}
				}
			}

			$oDom->clear();
			unset($oDom);
		}
		$aResult['Payment'] = 'PayPal';
		$aResult['PaymentSystem'] = \Aurora\Modules\Sales\Enums\PaymentSystem::PayPal;
		$aResult['ParsingStatus'] = \Aurora\Modules\Sales\Enums\ParsingStatus::ParsedWithPayPalSuccesfully;
		return $aResult;
	}
}
$oCrmParser = new CrmParser();
$oCrmParser->Start();
exit();
