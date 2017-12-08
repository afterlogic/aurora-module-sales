<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales;

/**
 * @package Modules
 */

class Module extends \Aurora\System\Module\AbstractModule
{
	public $oApiSalesManager = null;
	public $oApiCustomersManager = null;
	public $oApiProductsManager = null;
	public $oApiProductGroupsManager = null;
	public $oApiDownloadsManager = null;
	public $oApiContactsManager = null;
	public $oApiCompaniesManager = null;
	public $sStorage = null;

	/**
	 * Initializes Sales Module.
	 *
	 * @ignore
	 */

	public function init()
	{
		$this->subscribeEvent('Contacts::GetStorage', array($this, 'onGetStorage'));

		$this->oApiSalesManager = new Managers\Sales($this);
		$this->oApiCustomersManager = new Managers\Customers($this);
		$this->oApiProductsManager = new Managers\Products($this);
		$this->oApiProductGroupsManager = new Managers\ProductGroups($this);
		$this->oApiDownloadsManager = new Managers\Downloads($this);
		$this->oApiContactsManager = new Managers\Contacts($this);
		$this->oApiCompaniesManager = new Managers\Companies($this);
		$this->sStorage = 'sales';

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Sale',
			[
				'VatId' => ['string', ''],
				'Payment' => ['string', ''],
				'LicenseKey' => ['string', ''],
				'RefNumber' => ['int', 0],
				'ShareItProductId' => ['int', 0],
				'ShareItPurchaseId' => ['int', 0],
				'IsNotified' => ['bool', false],
				'MaintenanceExpirationDate' => ['datetime', date('Y-m-d H:i:s', 0)],
				'RecurrentMaintenance' => ['bool', true],
				'TwoMonthsEmailSent' => ['bool', false],
				'ParentSaleId' => ['int', 0],
				'PaymentSystem' => ['int', 0],
				'NumberOfLicenses' => ['int', 0],
				'RawData' => ['text', ''],
				'RawDataType' => ['int', 0],
				'PayPalItem' => ['string', ''],
			]
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Customer',
			[
				'Company' => ['string', ''],
				'Language' => ['string', ''],
				'Notify' => ['bool', true],
				'GotGreeting' => ['bool', true],
				'GotGreeting2' => ['bool', true],
				'GotSurvey' => ['bool', true],
				'IsSale' => ['bool', true],
			]
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Product',
			[
				'ShareItProductId' => ['int', 0],
				'PayPalItem' => ['string', ''],
				'IsAutocreated' => ['bool', true],
			]
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\ProductGroup',
			[
				'ProductCode' => ['string', ''],
			]
		);

		$this->extendObject(
			'Aurora\Modules\ContactObjects\Classes\Contact',
			[
				'Fax' => ['string', ''],
				'Salutation' => ['string', ''],
				'LastName' => ['string', ''],
				'FirstName' => ['string', ''],
			]
		);
	}

	/**
	 * Creates sale.
	 * @param string $Payment Payment type
	 * @param string $PaymentSystem Payment system.
	 * @param datetime $MaintenanceExpirationDate Maintenance expiration date.
	 * @param string $IncomingLogin Login for IMAP connection.
	 * @param int $Price Payment amount.
	 * @param string $Email Email.
	 * @param string $FullName Customer full name.
	 * @param string $ProductTitle Product name.
	 * @param string $ProductCode Product code.
	 * 
	 * @return \Aurora\Modules\SaleObjects\Classes\Sale|boolean
	 */
	public function CreateSale($Payment, $PaymentSystem, $Price,
		$Email, $FullName,
		$ProductTitle, $ProductCode = null, $MaintenanceExpirationDate = null,
		$TransactionId = '',
		$Date = null, $LicenseKey ='', $RefNumber = 0, $ShareItProductId = 0, $ShareItPurchaseId = 0, $IsNotified = false, $RecurrentMaintenance = true, $TwoMonthsEmailSent = false, $ParentSaleId = 0, $VatId = '',
		$Salutation = '', $CustomerTitle = '', $FirstName = '', $LastName = '', $Company = '', $Address = '', $Phone = '', $Fax = '', $Language = '',
		$PayPalItem = '', $RawData = '', $RawDataType = 0
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		if ($ShareItProductId > 0)
		{
			$oProduct = $this->oApiProductsManager->getProductByShareItProductId($ShareItProductId);
		}
		else
		{
			$oProduct = null;
		}

		if (!$oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product && $PaymentSystem !== \Aurora\Modules\Sales\Enums\PaymentSystem::PayPal)
		{
			$ProductGroupUUID = '';
			if (isset($ProductCode))
			{
				$oProductGroup = $this->oApiProductGroupsManager->getProductGroupByCode($ProductCode);
				if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
				{
					$ProductGroupUUID = $oProductGroup->UUID;
				}
			}
			$iProductId = $this->createProduct($ProductTitle, $ShareItProductId, true, $ProductGroupUUID);
			if ($iProductId)
			{
				$oProduct = $this->oApiProductsManager->getProductByIdOrUUID($iProductId);
			}
			if (!$oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
			{
				return false;
			}
		}

		$oCustomer = $this->oApiCustomersManager->getCustomerByEmail($Email);
		if (!$oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
		{
			$oCustomer = $this->CreateCustomerWithContact(
				$FullName,
				$CustomerTitle, '', 0, $Language,
				$Address, $Phone,  $Email, $FirstName, $LastName, $Fax, $Salutation,
				$Company
			);
			if (!$oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
			{
				return false;
			}
		}

		$oSale = new \Aurora\Modules\SaleObjects\Classes\Sale($this->GetName());
		$oSale->ProductUUID = isset($oProduct->UUID) ? $oProduct->UUID : '';
		$oSale->CustomerUUID = $oCustomer->UUID;
		$oSale->{$this->GetName() . '::Payment'} = $Payment;
		$oSale->{$this->GetName() . '::LicenseKey'} = $LicenseKey;
		$oSale->Price = $Price;
		$oSale->{$this->GetName() . '::RefNumber'} = $RefNumber;
		$oSale->{$this->GetName() . '::ShareItProductId'} = $ShareItProductId;
		$oSale->{$this->GetName() . '::ShareItPurchaseId'} = $ShareItPurchaseId;
		$oSale->{$this->GetName() . '::IsNotified'} = $IsNotified;
		$oSale->{$this->GetName() . '::RecurrentMaintenance'} = $RecurrentMaintenance;
		$oSale->{$this->GetName() . '::TwoMonthsEmailSent'} = $TwoMonthsEmailSent;
		$oSale->{$this->GetName() . '::ParentSaleId'} = $ParentSaleId;
		$oSale->{$this->GetName() . '::VatId'} = $VatId;
		$oSale->{$this->GetName() . '::PaymentSystem'} = $PaymentSystem;
		$oSale->{$this->GetName() . '::TransactionId'} = $TransactionId;
		$oSale->{$this->GetName() . '::RawData'} = $RawData;
		$oSale->{$this->GetName() . '::RawDataType'} = $RawDataType;
		$oSale->{$this->GetName() . '::PayPalItem'} = $PayPalItem;
		if (isset($Date))
		{
			$oSale->Date = $Date;
		}
		if (isset($MaintenanceExpirationDate))
		{
			$oSale->{$this->GetName() . '::MaintenanceExpirationDate'} = $MaintenanceExpirationDate;
		}
		$bSaleResult = $this->oApiSalesManager->createSale($oSale);
		if ($bSaleResult)
		{
			return $oSale;
		}

		return false;
	}

	/**
	 * Get all sales.
	 *
	 * @param int $Limit Limit.
	 * @param int $Offset Offset.
	 * @param string $Search Search string.
	 * @return array
	 */
	public function GetSales($Limit = 20, $Offset = 0, $Search = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aCustomersUUID = [];
		$aProductsUUID = [];
		$aSalesSearchFilters = [];
		$aProductSearchFilters = [];
		$aSearchCustomers = [];
		if (!empty($Search))
		{
			$aProductSearchFilters = [
				'Title' => ['%'.$Search.'%', 'LIKE']
			];
			$aSearchProducts = $this->oApiProductsManager->getProducts(0, 0, $aProductSearchFilters, [
				'ProductGroupUUID',
				'Title',
				$this->GetName() . '::ShareItProductId',
			]);
			$aContactsSearchFilters = ['$OR' => [
					'Email' => ['%'.$Search.'%', 'LIKE'],
					'FullName' => ['%'.$Search.'%', 'LIKE'],
					$this->GetName() . '::FirstName' => ['%'.$Search.'%', 'LIKE'],
					$this->GetName() . '::LastName' => ['%'.$Search.'%', 'LIKE']
				]
			];
			$aSearchContacts = $this->oApiContactsManager->getContacts(0, 0, $aContactsSearchFilters, ['CustomerUUID']);
			if (is_array($aSearchContacts) && count($aSearchContacts) > 0)
			{
				$aCustomersSearchFilters['UUID'] = [$aSearchContacts, 'IN'];
				$aSearchCustomers = $this->oApiCustomersManager->getCustomers(0, 0, $aCustomersSearchFilters, [$this->GetName() . '::Email']);
			}

			$aSalesSearchFilters = [
				$this->GetName() . '::LicenseKey' => ['%'.$Search.'%', 'LIKE'],
				'Date' => ['%'.$Search.'%', 'LIKE']
			];

			if (is_array($aSearchProducts) && count($aSearchProducts) > 0)
			{
				$aSalesSearchFilters['ProductUUID'] = [array_keys($aSearchProducts), 'IN'];
			}
			if (is_array($aSearchCustomers) && count($aSearchCustomers) > 0)
			{
				$aSalesSearchFilters['CustomerUUID'] = [array_keys($aSearchCustomers), 'IN'];
			}

			$aSalesSearchFilters = ['$OR' => $aSalesSearchFilters];
		}
		$iSalesCount = $this->oApiSalesManager->getSalesCount($aSalesSearchFilters);
		$aSales = $this->oApiSalesManager->getSales($Limit, $Offset, $aSalesSearchFilters, [
			'CustomerUUID',
			'ProductUUID',
			'Date',
			$this->GetName() . '::LicenseKey',
			'Price',
			$this->GetName() . '::MaintenanceExpirationDate'
		]);

		foreach ($aSales as $oSale)
		{
			$aCustomersUUID[] = $oSale->CustomerUUID;
			$aProductsUUID[] = $oSale->ProductUUID;
		}
		$aCustomers = count($aCustomersUUID) > 0 ? $this->oApiCustomersManager->getCustomers(0, 0, ['UUID' => [\array_unique($aCustomersUUID), 'IN']]) : [];
		$aProducts = count($aProductsUUID) > 0 ? $this->oApiProductsManager->getProducts(0, 0, ['UUID' => [$aProductsUUID, 'IN']]) : [];

		//add Contact information to oCustomer
		if (count($aCustomersUUID) > 0)
		{
			$aContacts = $this->oApiContactsManager->getContacts(0, 0, ['CustomerUUID' => [\array_unique($aCustomersUUID), 'IN']]);
			foreach ($aContacts as $oContact)
			{
				if(isset($aCustomers[$oContact->CustomerUUID]) && !isset($aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::FullName'}))
				{
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::FullName'} = $oContact->FullName;
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::Email'} = $oContact->Email;
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::Address'} = $oContact->Address;
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::Phone'} = $oContact->Phone;
				}
			}
		}

		return [
			'ItemsCount' => $iSalesCount,
			'Sales' => is_array($aSales) ? array_reverse($aSales) : [],
			'Customers' => is_array($aCustomers) ? $aCustomers : [],
			'Products' => is_array($aProducts) ? $aProducts : []
		];
	}

	/**
	 * Get all products.
	 *
	 * @param int $Limit Limit.
	 * @param int $Offset Offset.
	 * @param string $Search Search string.
	 * @return array
	 */
	public function GetProducts($Limit = 0, $Offset = 0, $Search = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aSearchFilters = [];
		if (!empty($Search))
		{
			$aSearchFilters = [
				'Title' => ['%'.$Search.'%', 'LIKE']
			];
		}
		$aProducts = $this->oApiProductsManager->getProducts($Limit, $Offset, $aSearchFilters);
		return [
			'Products' => is_array($aProducts) ? $aProducts : [],
			'ItemsCount' => $this->oApiProductsManager->getProductsCount($aSearchFilters)
		];
	}

	/**
	 * Get all products groups.
	 *
	 * @param int $Limit Limit.
	 * @param int $Offset Offset.
	 * @param string $Search Search string.
	 * @return array
	 */
	public function GetProductGroups($Limit = 0, $Offset = 0, $Search = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aSearchFilters = [];
		if (!empty($Search))
		{
			$aSearchFilters = [
				'Title' => ['%'.$Search.'%', 'LIKE']
			];
		}
		$aProductGroups = $this->oApiProductGroupsManager->getProductGroups($Limit, $Offset, $aSearchFilters);
		return [
			'ProductGroups' => is_array($aProductGroups) ? $aProductGroups : [],
			'ItemsCount' => $this->oApiProductGroupsManager->getProductGroupsCount($aSearchFilters)
		];
	}

	public function UpdateProduct($ProductId, $Title = null, $ShareItProductId = null, $IsAutocreated = null, $ProductGroupUUID = null, $Description = null, $Homepage = null, $ProductPrice = null, $Status = 0, $PayPalItem = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oProduct = $this->oApiProductsManager->getProductByIdOrUUID($ProductId);
		if (isset($Title))
		{
			$oProduct->Title = $Title;
		}
		if (isset($ProductGroupUUID))
		{
			$oProduct->ProductGroupUUID = $ProductGroupUUID;
		}
		if (isset($ShareItProductId))
		{
			$oProduct->{$this->GetName() . '::ShareItProductId'} = $ShareItProductId;
		}
		if (isset($PayPalItem))
		{
			$oProduct->{$this->GetName() . '::PayPalItem'} = $PayPalItem;
		}
		if (isset($IsAutocreated))
		{
			$oProduct->{$this->GetName() . '::IsAutocreated'} = $IsAutocreated;
		}
		if (isset($ProductPrice))
		{
			$oProduct->Price = $ProductPrice;
		}
		if (isset($Description))
		{
			$oProduct->Description = $Description;
		}
		if (isset($Homepage))
		{
			$oProduct->Homepage = $Homepage;
		}
		if (isset($Status))
		{
			$oProduct->Status = $Status;
		}
		return $this->oApiProductsManager->UpdateProduct($oProduct);
	}

	public function UpdateProductGroup($ProductGroupId, $Title = null, $Homepage = null, $ProductCode = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oProductGroup = $this->oApiProductGroupsManager->getProductGroupByIdOrUUID($ProductGroupId);
		if (isset($Title))
		{
			$oProductGroup->Title = $Title;
		}
		if (isset($Homepage))
		{
			$oProductGroup->Homepage = $Homepage;
		}
		if (isset($ProductCode))
		{
			$oProductGroup->{$this->GetName() . '::ProductCode'} = (int) $ProductCode;
		}
		return $this->oApiProductGroupsManager->UpdateProductGroup($oProductGroup);
	}

	public function UpdateSale($SaleId,
		$ProductUUID = null,
		$Date = null,
		$VatId = null,
		$Payment = null,
		$CustomerUUID = null,
		$LicenseKey = null,
		$RefNumber = null,
		$ShareItProductId = null,
		$Price = null,
		$ShareItPurchaseId = null,
		$IsNotified = null,
		$MaintenanceExpirationDate = null,
		$RecurrentMaintenance = null,
		$TwoMonthsEmailSent = null,
		$ParentSaleId = null,
		$PaymentSystem = null,
		$PayPalItem = null
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oSale = $this->oApiSalesManager->getSaleByIdOrUUID((int) $SaleId);
		if ($oSale instanceof \Aurora\Modules\SaleObjects\Classes\Sale)
		{
			$oSale->ProductUUID = isset($ProductUUID) ? $ProductUUID : $oSale->ProductUUID;
			$oSale->Date = isset($Date) ? $Date : $oSale->Date;
			$oSale->{$this->GetName() . '::VatId'} = isset($VatId) ? $VatId : $oSale->{$this->GetName() . '::VatId'};
			$oSale->{$this->GetName() . '::Payment'} = isset($Payment) ? $Payment : $oSale->{$this->GetName() . '::Payment'};
			$oSale->CustomerUUID = isset($CustomerUUID) ? $CustomerUUID : $oSale->CustomerUUID;
			$oSale->{$this->GetName() . '::LicenseKey'} = isset($LicenseKey) ? $LicenseKey : $oSale->{$this->GetName() . '::LicenseKey'};
			$oSale->{$this->GetName() . '::RefNumber'} = isset($RefNumber) ? $RefNumber : $oSale->{$this->GetName() . '::RefNumber'};
			$oSale->{$this->GetName() . '::ShareItProductId'} = isset($ShareItProductId) ? $ShareItProductId : $oSale->{$this->GetName() . '::ShareItProductId'};
			$oSale->Price = isset($Price) ? $Price : $oSale->Price;
			$oSale->{$this->GetName() . '::ShareItPurchaseId'} = isset($ShareItPurchaseId) ? $ShareItPurchaseId : $oSale->{$this->GetName() . '::ShareItPurchaseId'};
			$oSale->{$this->GetName() . '::IsNotified'} = isset($IsNotified) ? $IsNotified : $oSale->{$this->GetName() . '::IsNotified'};
			$oSale->{$this->GetName() . '::MaintenanceExpirationDate'} = isset($MaintenanceExpirationDate) ? $MaintenanceExpirationDate : $oSale->{$this->GetName() . '::MaintenanceExpirationDate'};
			$oSale->{$this->GetName() . '::RecurrentMaintenance'} = isset($RecurrentMaintenance) ? $RecurrentMaintenance : $oSale->{$this->GetName() . '::RecurrentMaintenance'};
			$oSale->{$this->GetName() . '::TwoMonthsEmailSent'} = isset($TwoMonthsEmailSent) ? $TwoMonthsEmailSent : $oSale->{$this->GetName() . '::TwoMonthsEmailSent'};
			$oSale->{$this->GetName() . '::ParentSaleId'} = isset($ParentSaleId) ? $ParentSaleId : $oSale->{$this->GetName() . '::ParentSaleId'};
			$oSale->{$this->GetName() . '::PaymentSystem'} = isset($PaymentSystem) ? $PaymentSystem : $oSale->{$this->GetName() . '::PaymentSystem'};
			$oSale->{$this->GetName() . '::PayPalItem'} = isset($PayPalItem) ? $PayPalItem : $oSale->{$this->GetName() . '::PayPalItem'};
		}
		else
		{
			return false;
		}

		return $this->oApiSalesManager->UpdateSale($oSale);
	}

	/**
	 * Get all products.
	 *
	 * @param int $Limit Limit.
	 * @param int $Offset Offset.
	 * @param string $Search Search string.
	 * @return array
	 */
	public function GetDownloads($Limit = 0, $Offset = 0, $Search = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aSearchFilters = [];
		if (!empty($Search))
		{
			$aSearchFilters = []; // TODO
		}
		$aDownloads = $this->oApiDownloadsManager->getDownloads($Limit, $Offset, $aSearchFilters);
		return [
			'Downloads' => is_array($aDownloads) ? $aDownloads : [],
			'ItemsCount' => $this->oApiDownloadsManager->getDownloadsCount($aSearchFilters)
		];
	}

	/**
	 */
	public function CreateDownload(
		$DownloadId, 
		$ProductCode, 
		$Date, 
		$Referer, 
		$Ip, 
		$Gad, 
		$ProductVersion, 
		$TrialKey, 
		$LicenseType, 
		$ReferrerPage, 
		$IsUpgrade,
		$PlatformType,
		$CustomerUUID,
		$ProductUUID)
	{
//		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oDownload = new Classes\Download($this->GetName());

		$oDownload->DownloadId = $DownloadId; 
		$oDownload->ProductCode = $ProductCode; 
		$oDownload->Date = $Date;
		$oDownload->Referer = $Referer;
		$oDownload->Ip = $Ip;
		$oDownload->Gad = $Gad; 
		$oDownload->ProductVersion = $ProductVersion; 
		$oDownload->TrialKey = $TrialKey; 
		$oDownload->LicenseType = $LicenseType; 
		$oDownload->ReferrerPage = $ReferrerPage; 
		$oDownload->IsUpgrade = $IsUpgrade;
		$oDownload->PlatformType = $PlatformType;
		$oDownload->CustomerUUID = $CustomerUUID;
		$oDownload->ProductUUID = $ProductUUID;

		$bResult = $this->oApiDownloadsManager->createDownload($oDownload);
		if ($bResult)
		{
			return $oDownload;
		}

		return false;
	}
	public function ImportDownloads()
	{
		$sDbHost = "127.0.0.1:3309";
		$sDbLogin = "root";
		$sDbPassword = "12345";
		$oPdo = @new \PDO('mysql:dbname=sales' . (empty($sDbHost) ? '' : ';host='.$sDbHost), $sDbLogin, $sDbPassword);
		$sQuery = "SELECT * FROM downloads LIMIT 10;";
		$stmt = $oPdo->prepare($sQuery);
		$stmt->execute();
		$aResult = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($aResult as $aDownload)
		{
			$oCustomer = $this->oApiCustomersManager->getCustomerByEmail($aDownload['email']);
			if (!$oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
			{
				$oCustomer = new \Aurora\Modules\SaleObjects\Classes\Customer($this->GetName());

				$oCustomer->{$this->GetName() . '::Email'} = $aDownload['email'];
				$oCustomer->{$this->GetName() . '::Notify'} = $aDownload['notify'];
				$oCustomer->{$this->GetName() . '::GotGreeting'} = $aDownload['got_greeting'];
				$oCustomer->{$this->GetName() . '::GotGreeting2'} = $aDownload['got_greeting2'];
				$oCustomer->{$this->GetName() . '::GotSurvey'} = $aDownload['got_survey'];
				$oCustomer->{$this->GetName() . '::IsSale'} = $aDownload['is_sale'];

				$this->oApiCustomersManager->createCustomer($oCustomer);
			}			
			if ($oCustomer)
			{
				$CustomerUUID = $oCustomer->UUID;
			}
			
			$oProduct = $this->oApiProductsManager->getProductByCode($aDownload['product_id']);
			if ($oProduct)
			{
				$ProductUUID = $oProduct->UUID;
			}
			
			$this->createDownload(
				$aDownload['download_id'], 
				$aDownload['product_id'], 
				$aDownload['download_date'], 
				$aDownload['referer'], 
				$aDownload['ip'], 
				$aDownload['gad'], 
				$aDownload['product_version'], 
				$aDownload['trial_key'], 
				$aDownload['license_type'], 
				$aDownload['referrer_page'], 
				$aDownload['is_upgrade'], 
				$aDownload['platform_type'], 
				$CustomerUUID, 
				$ProductUUID
			);
		}

		return true;
	}	
	
	public function CreateProducts()
	{
		\Aurora\System\Api::skipCheckUserRole(true);
		$aProductGroups = $this->oApiProductGroupsManager->getProductGroups();
		if (is_array($aProductGroups))
		{
			foreach ($aProductGroups as $oProductGroup)
			{
				if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
				{
					$oProduct = new \Aurora\Modules\SaleObjects\Classes\Product($this->GetName());
					$oProduct->ProductGroupUUID = $oProductGroup->UUID;
					$oProduct->Title = 'Free';
					$oProduct->{$this->GetName() . '::ProductCode'} = $oProductGroup->{$this->GetName() . '::ProductCode'};
					$this->oApiProductsManager->updateProduct($oProduct);
				}
			}
		}
	}
	
	public function CreateGroups()
	{
		\Aurora\System\Api::skipCheckUserRole(true);
		$aGroups = [
		["Title" => "MailBee Objects","ProductCode" => "1"],
		["Title" => "MailBee POP3 Component","ProductCode" => "2"],
		["Title" => "MailBee SMTP Component","ProductCode" => "3"],
		["Title" => "MailBee IMAP4 Component","ProductCode" => "4"],
		["Title" => "MailBee Message Queue","ProductCode" => "5"],
		["Title" => "MailBee S\/MIME Component","ProductCode" => "6"],
		["Title" => "AfterLogic WebMail Pro ASP.NET","ProductCode" => "17"],
		["Title" => "AfterLogic WebMail Pro PHP","ProductCode" => "22"],
		["Title" => "MailBee.NET Objects","ProductCode" => "32"],
		["Title" => "MailBee.NET POP3 Component","ProductCode" => "33"],
		["Title" => "MailBee.NET SMTP Component","ProductCode" => "34"],
		["Title" => "MailBee.NET IMAP Component","ProductCode" => "35"],
		["Title" => "MailBee.NET Security Component","ProductCode" => "36"],
		["Title" => "MailBee.NET AntiSpam Component","ProductCode" => "37"],
		["Title" => "MailBee.NET Outlook Converter","ProductCode" => "38"],
		["Title" => "PRODUCT_XMAIL_SERVER_PRO_WIN","ProductCode" => "41"],
		["Title" => "PRODUCT_XMAIL_SERVER_PRO_LINUX","ProductCode" => "42"],
		["Title" => "AfterLogic MailSuite Pro","ProductCode" => "44"],
		["Title" => "MailBee.NET IMAP Bundle","ProductCode" => "48"],
		["Title" => "MailBee.NET POP3 Bundle","ProductCode" => "49"],
		["Title" => "Undefined 95","ProductCode" => "95"],
		["Title" => "Undefined 96","ProductCode" => "96"],
		["Title" => "Undefined 97","ProductCode" => "97"],
		["Title" => "Undefined 98","ProductCode" => "98"],
		["Title" => "Undefined 99","ProductCode" => "99"]
		];
		foreach ($aGroups as $aGroup)
		{
			$oProductGroup = new \Aurora\Modules\SaleObjects\Classes\ProductGroup($this->GetName());
			$oProductGroup->Title = $aGroup['Title'];
			$oProductGroup->{$this->GetName() . '::ProductCode'} = (int) $aGroup['ProductCode'];
			$iProductGroupId = $this->oApiProductGroupsManager->createProductGroup($oProductGroup);
			unset($oProductGroup);
		}
	}

	/**
	 * Creates product.
	 * @param string $Title Product name.
	 * @param string $ShareItProductId ShareIt product ID.
	 * @param boolean $IsAutocreated Is product was created automatically.
	 * @param string $ProductGroupUUID UUID of product group.
	 * @param string $Description Description.
	 * @param string $Homepage Homepage.
	 * @param int $Price Product price.
	 * @param int  $Status Product status.
	 *
	 * @return  int|boolean
	 */
	public function CreateProduct($Title, $ShareItProductId = '', $IsAutocreated = false, $ProductGroupUUID = '', $Description = '', $Homepage = '', $Price = 0, $Status = 0, $PayPalItem = '')
	{
		$oProduct = new \Aurora\Modules\SaleObjects\Classes\Product($this->GetName());
		if (isset($ProductGroupUUID))
		{
			$oProductGroup = $this->oApiProductGroupsManager->getProductGroupByIdOrUUID($ProductGroupUUID);
			if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
			{
				$oProduct->ProductGroupUUID = $oProductGroup->UUID;
			}
		}
		$oProduct->{$this->GetName() . '::ShareItProductId'} = $ShareItProductId;
		$oProduct->{$this->GetName() . '::IsAutocreated'} = $IsAutocreated;
		$oProduct->{$this->GetName() . '::PayPalItem'} = $PayPalItem;
		$oProduct->Title = $Title;
		$oProduct->Description = $Description;
		$oProduct->Homepage = $Homepage;
		$oProduct->Price = $Price;
		$oProduct->Status = $Status;

		return $this->oApiProductsManager->createProduct($oProduct);
	}

	/**
	 * Creates product.
	 * @param string $Title Title.
	 * @param string $Description Description.
	 * @param string $Homepage Homepage.
	 * @param string $ProductCode Product code.
	 *
	 * @return int|boolean
	 */
	public function CreateProductGroup($Title, $Description = '', $Homepage = '', $ProductCode = '')
	{
		$oProductGroup = new \Aurora\Modules\SaleObjects\Classes\ProductGroup($this->GetName());
		$oProductGroup->{$this->GetName() . '::ProductCode'} = $ProductCode;
		$oProductGroup->Title = $Title;
		$oProductGroup->Description = $Description;
		$oProductGroup->Homepage = $Homepage;

		return  $this->oApiProductGroupsManager->createProductGroup($oProductGroup);
	}

	/**
	 * Creates contact.
	 * @param string $FullName.
	 * @param string $CustomerUUID
	 * @param string $CompanyUUID.
	 * @param string $Address.
	 * @param string $Phone.
	 * @param string $Email.
	 * @param string $FirstName
	 * @param string $LastName.
	 * @param string $Fax.
	 * @param string $Salutation.
	 *
	 * @return int|boolean
	 */
	public function CreateContact($FullName, $CustomerUUID = '', $CompanyUUID = '', $Address = '', $Phone = '',  $Email = '', $FirstName = '', $LastName = '', $Fax = '', $Salutation = '')
	{
		$mResult = false;
		if (!empty($FullName))
		{
			$oContact = new \Aurora\Modules\ContactObjects\Classes\Contact($this->GetName());
			$oContact->CustomerUUID = $CustomerUUID;
			$oContact->CompanyUUID = $CompanyUUID;
			$oContact->FullName = $FullName;
			$oContact->Address = $Address;
			$oContact->Phone = $Phone;
			$oContact->Email = $Email;
			$oContact->{$this->GetName() . '::FirstName'} = $FirstName;
			$oContact->{$this->GetName() . '::LastName'} = $LastName;
			$oContact->{$this->GetName() . '::Fax'} = $Fax;
			$oContact->{$this->GetName() . '::Salutation'} = $Salutation;
			$mResult = $this->oApiContactsManager->createContact($oContact);
		}
		return $mResult;
	}

	/**
	 * Creates customer.
	 * @param string $Title Title.
	 * @param string $Description Description.
	 * @param int $Status Status.
	 * @param string $Language Language.
	 *
	 * @return int|boolean
	 */
	public function CreateCustomer($Title = '', $Description = '', $Status = 0, $Language = '')
	{
		$oCustomer = new \Aurora\Modules\SaleObjects\Classes\Customer($this->GetName());
		$oCustomer->Title = $Title;
		$oCustomer->Description = $Description;
		$oCustomer->Status = $Status;
		$oCustomer->{$this->GetName() . '::Language'} = $Language;
		return $this->oApiCustomersManager->createCustomer($oCustomer);
	}

	/**
	 * Creates customer with contact.
	 * @param string $ContactFullName.
	 * @param string $CustomerTitle.
	 * @param string $CustomerDescription.
	 * @param int $CustomerStatus.
	 * @param string $CustomerLanguage.
	 * @param string $Address.
	 * @param string $Phone.
	 * @param string $Email.
	 * @param string $FirstName
	 * @param string $LastName.
	 * @param string $Fax.
	 * @param string $Salutation.
	 * @param string $Company.
	 *
	 * @return \Aurora\Modules\SaleObjects\Classes\Customer|boolean
	 */
	public function CreateCustomerWithContact(
		$ContactFullName,
		$CustomerTitle, $CustomerDescription = '', $CustomerStatus = 0, $CustomerLanguage = '',
		$Address = '', $Phone = '',  $Email = '', $FirstName = '', $LastName = '', $Fax = '', $Salutation = '',
		$Company = ''
	)
	{
		$mResult = false;
		if (!empty($ContactFullName))
		{
			$iCustomerId = $this->CreateCustomer($CustomerTitle, $CustomerDescription, $CustomerStatus, $CustomerLanguage);
			if ($iCustomerId)
			{
				$oCustomer = $this->oApiCustomersManager->getCustomerByIdOrUUID($iCustomerId);
			}
			if (!$oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
			{
				return false;
			}

			$oContact = $this->oApiContactsManager->getContactByEmail($Email);
			if (!$oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact)
			{
				if ($Company !== '')
				{
					$oCompany = $this->oApiCompaniesManager->getCompanyByTitle($Company);
					if (!$oCompany instanceof \Aurora\Modules\ContactObjects\Classes\Company)
					{
						$oCompany = new \Aurora\Modules\ContactObjects\Classes\Company($this->GetName());
						$oCompany->Title = $Company;
						$oCompany->CustomerUUID = $oCustomer->UUID;
						$iCompanyId = $this->oApiCompaniesManager->createCompany($oCompany);
						if ($iCompanyId)
						{
							$oCompany = $this->oApiCompaniesManager->getCompanyByIdOrUUID($iCompanyId);
						}
						if (!$oCompany instanceof \Aurora\Modules\ContactObjects\Classes\Company)
						{
							return false;
						}
					}
				}
				$iContactId = $this->CreateContact(
						$ContactFullName,
						$oCustomer->UUID,
						isset($oCompany->UUID) ? $oCompany->UUID : '',
						$Address,
						$Phone,
						$Email,
						$FirstName,
						$LastName,
						$Fax,
						$Salutation
				);
				if (!$iContactId)
				{
					return false;
				}
			}
			$mResult = $oCustomer;
		}
		return $mResult;
	}

	public function onGetStorage(&$aStorages)
	{
		$aStorages[] = $this->sStorage;
	}

	/**
	 * Delete product group.
	 * @param int|string $mIdOrUUID ID or UUID of product group
	 *
	 * @return int|boolean
	 */
	public function DeleteProductGroup($mIdOrUUID)
	{
		$oProductGroup = $this->oApiProductGroupsManager->getProductGroupByIdOrUUID($mIdOrUUID);
		if (!$oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
		{
			return false;
		}
		return  $this->oApiProductGroupsManager->deleteProductGroup($oProductGroup);
	}

	/**
	 * Delete product.
	 * @param int|string $mIdOrUUID Product ID or UUID
	 *
	 * @return int|boolean
	 */
	public function DeleteProduct($mIdOrUUID)
	{
		$oProduct = $this->oApiProductsManager->getProductByIdOrUUID($mIdOrUUID);
		if (!$oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
		{
			return false;
		}
		return  $this->oApiProductsManager->deleteProduct($oProduct);
	}
}
