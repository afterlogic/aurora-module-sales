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
	public $oApiContactsManager = null;
	public $oApiCompaniesManager = null;
	public $sStorage = null;

	protected $aRequireModules = [
		'ContactObjects',
		'SaleObjects'
	];

	/**
	 * Initializes Sales Module.
	 *
	 * @ignore
	 */

	public function init()
	{
		$this->aErrors = [
			Enums\ErrorCodes::DataIntegrity				=> $this->i18N('ERROR_DATA_INTEGRITY'),
			Enums\ErrorCodes::SaleCreateFailed				=> $this->i18N('ERROR_SALE_CREATE_FAILED'),
			Enums\ErrorCodes::Validation_InvalidParameters	=> $this->i18N('ERROR_INVALID_PARAMETERS'),
			Enums\ErrorCodes::SaleUpdateFailed			=> $this->i18N('ERROR_SALE_UPDATE_FAILED'),
			Enums\ErrorCodes::ProductCreateFailed			=> $this->i18N('ERROR_PRODUCT_CREATE_FAILED'),
			Enums\ErrorCodes::ProductUpdateFailed			=> $this->i18N('ERROR_PRODUCT_UPDATE_FAILED'),
			Enums\ErrorCodes::ProductGroupCreateFailed		=> $this->i18N('ERROR_PRODUCT_GROUP_CREATE_FAILED'),
			Enums\ErrorCodes::ProductGroupUpdateFailed		=> $this->i18N('ERROR_PRODUCT_GROUP_UPDATE_FAILED'),
			Enums\ErrorCodes::CustomerCreateFailed			=> $this->i18N('ERROR_CUSTOMER_CREATE_FAILED'),
			Enums\ErrorCodes::CustomerExists				=> $this->i18N('ERROR_CUSTOMER_EXISTS'),
			Enums\ErrorCodes::ContactCreateFailed			=> $this->i18N('ERROR_CONTACT_CREATE_FAILED'),
			Enums\ErrorCodes::ContactUpdateFailed			=> $this->i18N('ERROR_CONTACT_UPDATE_FAILED'),
			Enums\ErrorCodes::CompanyCreateFailed			=> $this->i18N('ERROR_COMPANY_CREATE_FAILED'),
			Enums\ErrorCodes::CompanyUpdateFailed			=> $this->i18N('ERROR_COMPANY_UPDATE_FAILED')
		];
		$this->subscribeEvent('Contacts::GetStorage', array($this, 'onGetStorage'));

		$this->oApiSalesManager = new Managers\Sales($this);
		$this->oApiCustomersManager = new Managers\Customers($this);
		$this->oApiProductsManager = new Managers\Products($this);
		$this->oApiProductGroupsManager = new Managers\ProductGroups($this);
		$this->oApiContactsManager = new Managers\Contacts($this);
		$this->oApiCompaniesManager = new Managers\Companies($this);
		$this->sStorage = 'sales';

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Sale',
			[
				'VatId'					=> ['string', ''],
				'Payment'					=> ['string', ''],
				'LicenseKey'				=> ['string', ''],
				'RefNumber'				=> ['int', 0],
				'ShareItPurchaseId'			=> ['string', ''],
				'IsNotified'					=> ['bool', false],
				'MaintenanceExpirationDate'	=> ['datetime', date('Y-m-d H:i:s', 0)],
				'RecurrentMaintenance'		=> ['bool', true],
				'TwoMonthsEmailSent'		=> ['bool', false],
				'ParentSaleId'				=> ['int', 0],
				'PaymentSystem'			=> ['int', 0],
				'NumberOfLicenses'			=> ['int', 0],
				'RawData'					=> ['text', ''],
				'RawDataType'				=> ['int', 0],
				'PayPalItem'				=> ['string', ''],

				// Download section
				'DownloadId'		=> array('int', 0),
				'Referer'			=> array('text', ''),
				'Ip'				=> array('string', ''),
				'Gad'				=> array('string', ''),
				'ProductVersion'		=> array('string', ''),
				'LicenseType'		=> array('int', 0),
				'ReferrerPage'		=> array('int', 0),
				'IsUpgrade'		=> array('bool', false),
				'PlatformType'		=> array('int', 0),
			]
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Customer',
			[
				'Language'		=> ['string', ''],
				'Notify'		=> ['bool', true],
				'GotGreeting'	=> ['bool', true],
				'GotGreeting2'	=> ['bool', true],
				'GotSurvey'	=> ['bool', true],
				'IsSale'		=> ['bool', true],
			]
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Product',
			[
				'ShareItProductId'	=> ['string', ''],
				'PayPalItem'		=> ['string', ''],
				'CrmProductId'		=> ['string', ''],
				'IsAutocreated'		=> ['bool', true],
			]
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\ProductGroup',
			[
				'ProductCode'	=> ['string', ''],
			]
		);

		$this->extendObject(
			'Aurora\Modules\ContactObjects\Classes\Contact',
			[
				'Fax'			=> ['string', ''],
				'Salutation'		=> ['string', ''],
				'LastName'		=> ['string', ''],
				'FirstName'	=> ['string', ''],
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
		$Date = null, $LicenseKey ='', $RefNumber = 0, $CrmProductId = '', $ShareItProductId = '', $ShareItPurchaseId = '', $IsNotified = false, $RecurrentMaintenance = true, $TwoMonthsEmailSent = false, $ParentSaleId = 0, $VatId = '',
		$Salutation = '', $CustomerTitle = '', $FirstName = '', $LastName = '', $Company = '', $Address = '', $Phone = '', $Fax = '', $Language = '',
		$PayPalItem = '', $RawData = '', $RawDataType = 0
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		if (!empty($ShareItProductId))
		{
			$oProduct = $this->oApiProductsManager->getProductByShareItProductId($ShareItProductId);
		}
		elseif (!empty($CrmProductId))
		{
			$oProduct = $this->oApiProductsManager->getProductByCrmProductId($CrmProductId);
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
			$iProductId = $this->createProduct($ProductTitle, $ShareItProductId, $CrmProductId, true, $ProductGroupUUID);
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

	protected function getSalesSearchFilters($Search = '')
	{
		$aSalesSearchFilters = [];
		
		if (!empty($Search))
		{
			$aSearchCustomers = [];
			
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
				$aCustomerUIDs = [];
				foreach ($aSearchContacts as $oContact)
				{
					$aCustomerUIDs[] = $oContact->CustomerUUID;
				}
				$aCustomersSearchFilters['UUID'] = [$aCustomerUIDs, 'IN'];
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
		
		return $aSalesSearchFilters;
	}
	
	/**
	 * Get all sales.
	 *
	 * @param int $Limit Limit.
	 * @param int $Offset Offset.
	 * @param string $Search Search string.
	 * @return array
	 */
	public function GetSales($Limit = 20, $Offset = 0, $Search = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$aCustomersUUID = [];
		$aProductsUUID = [];
		
		$aSalesSearchFilters = $this->getSalesSearchFilters($Search);
		$iSalesCount = $this->oApiSalesManager->getSalesCount($aSalesSearchFilters);
		$aSales = $this->oApiSalesManager->getSales($Limit, $Offset, $aSalesSearchFilters, [
			'CustomerUUID',
			'ProductUUID',
			'Date',
			'Price',
			$this->GetName() . '::LicenseKey',
			$this->GetName() . '::MaintenanceExpirationDate',
			$this->GetName() . '::Payment',
			$this->GetName() . '::PayPalItem',
			$this->GetName() . '::VatId',
			$this->GetName() . '::RefNumber',
			$this->GetName() . '::ShareItPurchaseId',
			$this->GetName() . '::IsNotified',
			$this->GetName() . '::RecurrentMaintenance',
			$this->GetName() . '::TwoMonthsEmailSent',
			$this->GetName() . '::ParentSaleId',
			$this->GetName() . '::PaymentSystem',
			$this->GetName() . '::NumberOfLicenses',
			// Download section
			$this->GetName() . '::DownloadId',
			$this->GetName() . '::Referer',
			$this->GetName() . '::Ip',
			$this->GetName() . '::Gad',
			$this->GetName() . '::ProductVersion',
			$this->GetName() . '::LicenseType',
			$this->GetName() . '::ReferrerPage',
			$this->GetName() . '::IsUpgrade',
			$this->GetName() . '::PlatformType'
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
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::Facebook'} = $oContact->Facebook;
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::LinkedIn'} = $oContact->LinkedIn;
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::Instagram'} = $oContact->Instagram;
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::Fax'} = $oContact->{$this->GetName() . '::Fax'};
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::Salutation'} = $oContact->{$this->GetName() . '::Salutation'};
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::LastName'	} = $oContact->{$this->GetName() . '::LastName'};
					$aCustomers[$oContact->CustomerUUID]->{$this->GetName() . '::FirstName'} = $oContact->{$this->GetName() . '::FirstName'};
				}
			}
		}

		return [
			'ItemsCount' => $iSalesCount,
			'Sales' => is_array($aSales) ? $aSales : [],
			'Customers' => is_array($aCustomers) ? $aCustomers : [],
			'Products' => is_array($aProducts) ? $aProducts : []
		];
	}

	public function GetChartSales($FromDate = '', $TillDate = '', $Search = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$aFilters = [];
		
		if ($Search)
		{
			$aFilters = $this->getSalesSearchFilters($Search);
		}
		
		if ($FromDate && $TillDate)
		{
			$aFilters['1@Date'] = [
				(string) $FromDate,
				'>'
			];
			$aFilters['2@Date'] = [
				(string) $TillDate,
				'<'
			];
		}
		
		$aSales = $this->oApiSalesManager->getSales(0, 0, $aFilters, ['Date']);
		
		$fGetOnlyDate = function($value) {
			return ['Date' => $value->Date];
		};

		return array_map($fGetOnlyDate, $aSales);
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

	public function UpdateProduct($ProductId, $Title = null, $CrmProductId = null, $ShareItProductId = null, $IsAutocreated = null, $ProductGroupUUID = null, $Description = null, $Homepage = null, $ProductPrice = null, $Status = 0, $PayPalItem = null)
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
		if (isset($CrmProductId))
		{
			$oProduct->{$this->GetName() . '::CrmProductId'} = $CrmProductId;
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
		if (!$oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
		{
			return false;
		}
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
		$ProductIdOrUUID = null,
		$Date = null,
		$VatId = null,
		$Payment = null,
		$CustomerUUID = null,
		$LicenseKey = null,
		$RefNumber = null,
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
			if (isset($ProductIdOrUUID))
			{
				$Product =  $this->oApiProductsManager->getProductByIdOrUUID($ProductIdOrUUID);
			}
			$oSale->ProductUUID = isset($Product, $Product->UUID) ? $Product->UUID : $oSale->ProductUUID;
			$oSale->Date = isset($Date) ? $Date : $oSale->Date;
			$oSale->{$this->GetName() . '::VatId'} = isset($VatId) ? $VatId : $oSale->{$this->GetName() . '::VatId'};
			$oSale->{$this->GetName() . '::Payment'} = isset($Payment) ? $Payment : $oSale->{$this->GetName() . '::Payment'};
			$oSale->CustomerUUID = isset($CustomerUUID) ? $CustomerUUID : $oSale->CustomerUUID;
			$oSale->{$this->GetName() . '::LicenseKey'} = isset($LicenseKey) ? $LicenseKey : $oSale->{$this->GetName() . '::LicenseKey'};
			$oSale->{$this->GetName() . '::RefNumber'} = isset($RefNumber) ? $RefNumber : $oSale->{$this->GetName() . '::RefNumber'};
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
	 */
	public function CreateDownload(
		$DownloadId, 
		$ProductCode, 
		$Date, 
		$Email,
		$Referer, 
		$Ip, 
		$Gad, 
		$ProductVersion, 
		$TrialKey, 
		$LicenseType, 
		$ReferrerPage, 
		$IsUpgrade,
		$PlatformType,
		$ProductTitle = '',
		$CrmProductId = ''
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$bResult = false;
		$mSale = $this->CreateSale('Download', Enums\PaymentSystem::Download, 0, $Email, '', $ProductTitle, $ProductCode, null, '', $Date, $TrialKey, 0, $CrmProductId);
		if ($mSale)
		{
			$mSale->{$this->GetName() . '::DownloadId'} = $DownloadId;
			$mSale->{$this->GetName() . '::Referer'} = $Referer;
			$mSale->{$this->GetName() . '::Ip'} = $Ip;
			$mSale->{$this->GetName() . '::Gad'} = $Gad; 
			$mSale->{$this->GetName() . '::ProductVersion'} = $ProductVersion; 
			$mSale->{$this->GetName() . '::LicenseType'} = $LicenseType; 
			$mSale->{$this->GetName() . '::ReferrerPage'} = $ReferrerPage; 
			$mSale->{$this->GetName() . '::IsUpgrade'} = $IsUpgrade;
			$mSale->{$this->GetName() . '::PlatformType'} = $PlatformType;
			$mSale->{$this->GetName() . '::CrmProductId'} = $CrmProductId;
			
			$bResult = $this->oApiSalesManager->updateSale($mSale);
		}

		if ($bResult)
		{
			return $mSale;
		}

		return false;
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
	 * @param int $ProductPrice Product price.
	 * @param int  $Status Product status.
	 *
	 * @return  int|boolean
	 */
	public function CreateProduct($Title, $ShareItProductId = '', $CrmProductId = '', $IsAutocreated = false, $ProductGroupUUID = '', $Description = '', $Homepage = '', $ProductPrice = 0, $Status = 0, $PayPalItem = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oProduct = new \Aurora\Modules\SaleObjects\Classes\Product($this->GetName());
		if (isset($ProductGroupUUID) && !empty($ProductGroupUUID))
		{
			$oProductGroup = $this->oApiProductGroupsManager->getProductGroupByIdOrUUID($ProductGroupUUID);
			if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
			{
				$oProduct->ProductGroupUUID = $oProductGroup->UUID;
			}
		}
		$oProduct->{$this->GetName() . '::ShareItProductId'} = $ShareItProductId;
		$oProduct->{$this->GetName() . '::CrmProductId'} = $CrmProductId;
		$oProduct->{$this->GetName() . '::IsAutocreated'} = $IsAutocreated;
		$oProduct->{$this->GetName() . '::PayPalItem'} = $PayPalItem;
		$oProduct->Title = $Title;
		$oProduct->Description = $Description;
		$oProduct->Homepage = $Homepage;
		$oProduct->Price = $ProductPrice;
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
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
	public function CreateContact($FullName = '', $CustomerUUID = '', $CompanyUUID = '', $Address = '', $Phone = '',  $Email = '', $FirstName = '', $LastName = '', $Fax = '', $Salutation = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
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
		return $this->oApiContactsManager->createContact($oContact);
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oCustomer = new \Aurora\Modules\SaleObjects\Classes\Customer($this->GetName());
		$oCustomer->Title = $Title;
		$oCustomer->Description = $Description;
		$oCustomer->Status = $Status;
		$oCustomer->{$this->GetName() . '::Language'} = $Language;
		return $this->oApiCustomersManager->createCustomer($oCustomer);
	}

	/**
	 * Creates customer with contact.
	 * @param string $ContactFullName
	 * @param string $CustomerTitle
	 * @param string $CustomerDescription
	 * @param int $CustomerStatus
	 * @param string $CustomerLanguage
	 * @param string $Address
	 * @param string $Phone
	 * @param string $Email
	 * @param string $FirstName
	 * @param string $LastName
	 * @param string $Fax
	 * @param string $Salutation
	 * @param string $Company
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
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
		return $oCustomer;
	}

	public function onGetStorage(&$aStorages)
	{
		$aStorages[] = $this->sStorage;
	}

	/**
	 * Delete product group.
	 * @param int|string $IdOrUUID ID or UUID of product group
	 *
	 * @return int|boolean
	 */
	public function DeleteProductGroup($IdOrUUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$mResult = false;

		$oProductGroup = $this->oApiProductGroupsManager->getProductGroupByIdOrUUID($IdOrUUID);

		if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
		{
			$aProducts = $this->oApiProductsManager->getProductsByGroupe($oProductGroup->UUID);
			if (is_array($aProducts) && count($aProducts) > 0)
			{
				throw new \Aurora\System\Exceptions\BaseException(Enums\ErrorCodes::DataIntegrity);
			}
			$mResult =  $this->oApiProductGroupsManager->deleteProductGroup($oProductGroup);
		}
		return $mResult;
	}

	/**
	 * Delete product.
	 * @param int|string $IdOrUUID Product ID or UUID
	 *
	 * @return int|boolean
	 */
	public function DeleteProduct($IdOrUUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$mResult = false;

		$oProduct = $this->oApiProductsManager->getProductByIdOrUUID($IdOrUUID);
		if ($oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
		{
			$aSearchFilters = ['ProductUUID' => $oProduct->UUID];
			$aSales = $this->oApiSalesManager->getSales(0, 0, $aSearchFilters, ['UUID']);
			if (is_array($aSales) && count($aSales) > 0)
			{
				throw new \Aurora\System\Exceptions\BaseException(Enums\ErrorCodes::DataIntegrity);
			}
			$mResult =  $this->oApiProductsManager->deleteProduct($oProduct);
		}
		return $mResult;
	}

	/**
	 * Delete contact.
	 * @param int|string $IdOrUUID Contact ID or UUID
	 *
	 * @return int|boolean
	 */
	public function DeleteContact($IdOrUUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$mResult = false;

		$oContact = $this->oApiContactsManager->getContactByIdOrUUID($IdOrUUID);
		if ($oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact)
		{
			$mResult =  $this->oApiContactsManager->deleteContact($oContact);
		}
		
		return $mResult;
	}

	/**
	 * Get all contacts.
	 *
	 * @param int $Limit Limit.
	 * @param int $Offset Offset.
	 * @param string $Search Search string.
	 * @return array
	 */
	public function GetContacts($Limit = 0, $Offset = 0, $Search = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aSearchFilters = [];
		if (!empty($Search))
		{
			$aSearchFilters = ['$OR' => [
				'FullName'=> ['%'.$Search.'%', 'LIKE'],
				'Address'=> ['%'.$Search.'%', 'LIKE'],
				'Phone'=> ['%'.$Search.'%', 'LIKE'],
				'Email'=> ['%'.$Search.'%', 'LIKE'],
				'Facebook'=> ['%'.$Search.'%', 'LIKE'],
				'LinkedIn'=> ['%'.$Search.'%', 'LIKE'],
				'Instagram'=> ['%'.$Search.'%', 'LIKE'],
				$this->GetName() . '::Fax' => ['%'.$Search.'%', 'LIKE'],
				$this->GetName() . '::Salutation' => ['%'.$Search.'%', 'LIKE'],
				$this->GetName() . '::LastName' => ['%'.$Search.'%', 'LIKE'],
				$this->GetName() . '::FirstName' =>['%'.$Search.'%', 'LIKE']
			]];
		}
		$aContacts = $this->oApiContactsManager->getContacts($Limit, $Offset, $aSearchFilters);
		return [
			'Contacts' => is_array($aContacts) ? $aContacts : [],
			'ItemsCount' => $this->oApiContactsManager->getContactsCount($aSearchFilters)
		];
	}

	/**
	 * Get sales by contact UUID.
	 *
	 * @param string $ContactUUID
	 * @return array
	 */
	public function GetSalesByContactUUID($ContactUUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aResult = [];
		if (!$ContactUUID)
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\System\Exceptions\Errs::Validation_InvalidParameters);
		}
		$oContact = $this->oApiContactsManager->getContactByIdOrUUID($ContactUUID);
		if ($oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact && isset($oContact->CustomerUUID))
		{
			$oCustomer = $this->oApiCustomersManager->getCustomerByIdOrUUID($oContact->CustomerUUID);
			if ($oCustomer  instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
			{
				$aSalesFilters = ['CustomerUUID' => $oCustomer->UUID];
				$aSales = $this->oApiSalesManager->getSales(0, 0, $aSalesFilters);

				$aResult = [
					'Sales' => is_array($aSales) ? $aSales : [],
					'SalesCount' =>  $this->oApiSalesManager->getSalesCount($aSalesFilters)
				];
			}
		}
		return $aResult;
	}

	/**
	 * Update contact.
	 *
	 * @param int $ContactId
	 * @param string $FullName Contact name.
	 * @param string $Email Contact email.
	 * @param string $Address Contact Address.
	 * @return bool
	 */
	public function UpdateContact($ContactId,
		$FullName = null,
		$Email = null,
		$Address = null,
		$Phone = null,
		$Fax = null,
		$Facebook = null,
		$LinkedIn = null,
		$Instagram = null,
		$Salutation = null,
		$LastName = null,
		$FirstName = null
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oContact = $this->oApiContactsManager->getContactByIdOrUUID($ContactId);
		if (!$oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact)
		{
			return false;
		}
		if (isset($FullName))
		{
			$oContact->FullName = $FullName;
		}
		if (isset($Email))
		{
			$oContact->Email = $Email;
		}
		if (isset($Address))
		{
			$oContact->Address = $Address;
		}
		if (isset($Phone))
		{
			$oContact->Phone = $Phone;
		}
		if (isset($Fax))
		{
			$oContact->{$this->GetName() . '::Fax'} = $Fax;
		}
		if (isset($Facebook))
		{
			$oContact->Facebook = $Facebook;
		}
		if (isset($LinkedIn))
		{
			$oContact->LinkedIn = $LinkedIn;
		}
		if (isset($Instagram))
		{
			$oContact->Instagram = $Instagram;
		}
		if (isset($Salutation))
		{
			$oContact->{$this->GetName() . '::Salutation'} = $Salutation;
		}
		if (isset($LastName))
		{
			$oContact->{$this->GetName() . '::LastName'} = $LastName;
		}
		if (isset($FirstName))
		{
			$oContact->{$this->GetName() . '::FirstName'} = $FirstName;
		}
		return $this->oApiContactsManager->UpdateContact($oContact);
	}
}
