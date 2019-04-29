<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	protected $aManagers = [
		'Sales' => null,
		'Customers' => null,
		'Products' => null,
		'ProductGroups' => null,
		'Contacts' => null,
		'Companies' => null,
		'Mailchimp' => null
	];
	
	public $sStorage = null;
	public $oSxGeo = null;

	protected $aRequireModules = [
		'ContactObjects',
		'SaleObjects'
	];
	
	protected $oPDO = null;

	public function getManager($sManager)
	{
		if ($this->aManagers[$sManager] === null)
		{
			$sManagerClass = Module::getNamespace() . "\\Managers\\" . $sManager;
			$this->aManagers[$sManager] = new $sManagerClass($this);
		}

		return $this->aManagers[$sManager];
	}


	/**
	 * Initializes Sales Module.
	 *
	 * @ignore
	 */

	public function init()
	{
		$this->oPDO = \Aurora\System\Api::GetPDO();
		
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
			Enums\ErrorCodes::CompanyUpdateFailed			=> $this->i18N('ERROR_COMPANY_UPDATE_FAILED'),
			Enums\ErrorCodes::MailchimpConnectionFailed		=> $this->i18N('ERROR_MAILCHIMP_CONNECTION_FAILED')
		];

		$this->subscribeEvent('Contacts::GetStorage', array($this, 'onGetStorage'));
		$this->subscribeEvent('Sales::CreateSale::after', array($this, 'onCreateSale'));

		$this->AddEntries(
			array(
				'download-sale-eml' => 'EntryDownloadEmlFile'
			)
		);

		$this->sStorage = 'sales';

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Sale',
			[
				'VatId'						=> ['string', ''],
				'Payment'					=> ['string', ''],
				'LicenseKey'				=> ['text', ''],
				'RefNumber'					=> ['int', 0],
				'ShareItPurchaseId'			=> ['string', ''],
				'IsNotified'				=> ['bool', false],
				'MaintenanceExpirationDate'	=> ['datetime', date('Y-m-d H:i:s', 0)],
				'RecurrentMaintenance'		=> ['bool', true],
				'TwoMonthsEmailSent'		=> ['bool', false],
				'ParentSaleId'				=> ['int', 0],
				'PaymentSystem'				=> ['int', 0],
				'NumberOfLicenses'			=> ['int', 0],
				'RawEmlData'				=> ['mediumblob', ''],
				'PayPalItem'				=> ['string', ''],
				'MessageSubject'			=> ['string', ''],
				'Deleted'					=> ['bool', false],
				'ParsingStatus'				=> ['int', \Aurora\Modules\Sales\Enums\ParsingStatus::Unknown],
				'TransactionId'				=> ['string', ''],
				'Reseller'					=> ['string', ''],
				'PromotionName'				=> ['string', ''],

				// Download section
				'DownloadId'		=> array('int', 0),
				'Referer'			=> array('text', ''),
				'Ip'				=> array('string', ''),
				'Gad'				=> array('string', ''),
				'ProductVersion'	=> array('string', ''),
				'LicenseType'		=> array('int', 0),
				'ReferrerPage'		=> array('int', 0),
				'IsUpgrade'			=> array('bool', false),
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
				'GotSurvey'		=> ['bool', true],
				'IsSale'		=> ['bool', true],
			]
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Product',
			[
				'ShareItProductId'		=> ['string', ''],
				'PayPalItem'			=> ['string', ''],
				'CrmProductId'			=> ['string', ''],
				'IsAutocreated'			=> ['bool', true],
				'IsDefault'				=> ['bool', false],
				'MailchimpGroupTitle'	=> ['string', ''],
				'MailchimpGroupUUID'	=> ['string', '']
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
				'Salutation'	=> ['string', ''],
				'LastName'		=> ['string', ''],
				'FirstName'		=> ['string', ''],
			]
		);

		try
		{
			if (!class_exists("SxGeo")) //TODO: remove after AfterlogicDownloadsWebclient removing
			{
				include_once(__DIR__ ."/SxGeo.php");
			}
			$this->SxGeo = new \SxGeo(__DIR__.'/SxGeoCity.dat');
		}
		catch (Exception $ex)
		{}
	}

	/**
	 * Creates sale.
	 * @param string $Payment Payment type
	 * @param string $PaymentSystem Payment system.
	 * @param datetime $MaintenanceExpirationDate Maintenance expiration date.
	 * @param string $IncomingLogin Login for IMAP connection.
	 * @param double $Price Payment amount.
	 * @param string $Email Email.
	 * @param string $FullName Customer full name.
	 * @param string $ProductTitle Product name.
	 * @param string $ProductCode Product code.
	 * 
	 * @return \Aurora\Modules\SaleObjects\Classes\Sale|boolean
	 */
	public function CreateSale($Payment = '', $PaymentSystem = '', $Price = null,
		$Email = null, $FullName = null,
		$ProductTitle = null, $ProductCode = null, $MaintenanceExpirationDate = null,
		$TransactionId = '',
		$Date = null, $LicenseKey ='', $RefNumber = 0, $CrmProductId = '', $ShareItProductId = '', $ShareItPurchaseId = '', $IsNotified = false, $RecurrentMaintenance = true, $TwoMonthsEmailSent = false, $ParentSaleId = 0, $VatId = '',
		$Salutation = '', $CustomerTitle = '', $FirstName = '', $LastName = '', $Company = '', $Address = '', $Phone = '', $Fax = '', $Language = '',
		$PayPalItem = '', $RawEmlData = '', $NumberOfLicenses = 0, $MessageSubject = '', $ParsingStatus = \Aurora\Modules\Sales\Enums\ParsingStatus::Unknown, $Reseller = '', $PromotionName = '',
		$Subscribe = false
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		if (!empty($ShareItProductId))
		{
			$oProduct = $this->getManager('Products')->getProductByShareItProductId($ShareItProductId);
		}
		elseif (!empty($CrmProductId))
		{
			$oProduct = $this->getManager('Products')->getProductByCrmProductId($CrmProductId);
		}
		else
		{
			$oProduct = null;
		}

		if (!$oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product && (!empty($ShareItProductId) || !empty($CrmProductId)))
		{
			$ProductGroupUUID = '';
			if (isset($ProductCode))
			{
				$oProductGroup = $this->getManager('ProductGroups')->getProductGroupByCode($ProductCode);
				if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
				{
					$ProductGroupUUID = $oProductGroup->UUID;
				}
			}
			$iProductId = $this->createProduct($ProductTitle, $ShareItProductId, $CrmProductId, true, $ProductGroupUUID);
			if ($iProductId)
			{
				$oProduct = $this->getManager('Products')->getProductByIdOrUUID($iProductId);
			}
			if (!$oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
			{
				return false;
			}
		}

		$oCustomer = $Email ? $this->getManager('Customers')->getCustomerByEmail($Email) : null;
		if (!$oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer &&
			(!empty($FullName) || !empty($CustomerTitle) || !empty($Email) || !empty($FirstName) || !empty($LastName) || !empty($Company))
		)
		{
			$oCustomer = $this->CreateCustomerWithContact(
				$FullName,
				$CustomerTitle, '', 0, $Language,
				$Address, $Phone, $Email, $FirstName, $LastName, $Fax, $Salutation,
				$Company
			);
		}
		else if ($oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
		{
			//Update contact if needed
			$oContact = $Email ? $this->getManager('Contacts')->getContactByEmail($Email) : null;
			if ($oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact)
			{
				$bNeedToUpdate = false;
				if (!empty($FullName) && empty($oContact->FullName))
				{
					$oContact->FullName = $FullName;
					$bNeedToUpdate = true;
				}
				if (!empty($Address) && empty($oContact->Address))
				{
					$oContact->Address = $Address;
					$bNeedToUpdate = true;
				}
				if (!empty($Phone) && empty($oContact->Phone))
				{
					$oContact->Phone = $Phone;
					$bNeedToUpdate = true;
				}
				if (!empty($FirstName) && empty($oContact->{self::GetName() . '::FirstName'}))
				{
					$oContact->{self::GetName() . '::FirstName'} = $FirstName;
					$bNeedToUpdate = true;
				}
				if (!empty($LastName) && empty($oContact->{self::GetName() . '::LastName'}))
				{
					$oContact->{self::GetName() . '::LastName'} = $LastName;
					$bNeedToUpdate = true;
				}
				if (!empty($Fax) && empty($oContact->{self::GetName() . '::Fax'}))
				{
					$oContact->{self::GetName() . '::Fax'} = $Fax;
					$bNeedToUpdate = true;
				}
				if (!empty($Salutation) && empty($oContact->{self::GetName() . '::Salutation'}))
				{
					$oContact->{self::GetName() . '::Salutation'} = $Salutation;
					$bNeedToUpdate = true;
				}
				if ($bNeedToUpdate)
				{
					$this->getManager('Contacts')->UpdateContact($oContact);
				}
			}
		}

		$oSale = new \Aurora\Modules\SaleObjects\Classes\Sale(self::GetName());
		$oSale->ProductUUID = isset($oProduct->UUID) ? $oProduct->UUID : '';
		$oSale->CustomerUUID = ($oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer) ? $oCustomer->UUID : '';
		$oSale->{self::GetName() . '::Payment'} = $Payment;
		$oSale->{self::GetName() . '::LicenseKey'} = $LicenseKey;
		$oSale->Price = $Price;
		$oSale->{self::GetName() . '::RefNumber'} = $RefNumber;
		$oSale->{self::GetName() . '::ShareItPurchaseId'} = $ShareItPurchaseId;
		$oSale->{self::GetName() . '::IsNotified'} = $IsNotified;
		$oSale->{self::GetName() . '::RecurrentMaintenance'} = $RecurrentMaintenance;
		$oSale->{self::GetName() . '::TwoMonthsEmailSent'} = $TwoMonthsEmailSent;
		$oSale->{self::GetName() . '::ParentSaleId'} = $ParentSaleId;
		$oSale->{self::GetName() . '::VatId'} = $VatId;
		$oSale->{self::GetName() . '::PaymentSystem'} = $PaymentSystem;
		$oSale->{self::GetName() . '::TransactionId'} = $TransactionId;
		$oSale->{self::GetName() . '::RawEmlData'} = $RawEmlData;
		$oSale->{self::GetName() . '::PayPalItem'} = $PayPalItem;
		$oSale->{self::GetName() . '::NumberOfLicenses'} = $NumberOfLicenses;
		$oSale->{self::GetName() . '::MessageSubject'} = substr($MessageSubject, 0, 255);
		$oSale->{self::GetName() . '::ParsingStatus'} = $ParsingStatus;
		$oSale->{self::GetName() . '::Reseller'} = $Reseller;
		$oSale->{self::GetName() . '::PromotionName'} = $PromotionName;
		if (isset($Date))
		{
			$oSale->Date = $Date;
		}
		if (isset($MaintenanceExpirationDate))
		{
			$oSale->{self::GetName() . '::MaintenanceExpirationDate'} = $MaintenanceExpirationDate;
		}
		$bSaleResult = $this->getManager('Sales')->createSale($oSale);
		if ($bSaleResult)
		{
			return $oSale;
		}

		return false;
	}

	/**
	 * Prepare filters from search string and filters.
	 *
	 * @param string $Search Search string.
	 * @param array $Filters
	 * @return array
	 */
	protected function getSalesFilters($Search = '', $Filters = [])
	{
		$aSalesSearchFilters = [];

		if (is_array($Filters) && !empty($Filters))
		{
			if (isset($Filters['ProductUUID']) && $Filters['ProductUUID'])
			{
				//search all sales/downloads with selected product
				//ignoring other filters and the search string
				if (isset($Filters['GetDownloads']) && $Filters['GetDownloads'])
				{
					$aSalesSearchFilters = ['$AND' => [
						self::GetName() . '::PaymentSystem' => Enums\PaymentSystem::Download
					]];
				}
				else
				{
					$aSalesSearchFilters = ['$AND' => [
						'$OR' => [
							'1@' . self::GetName() . '::PaymentSystem' => [Enums\PaymentSystem::Download, '!='],
							'2@' . self::GetName() . '::PaymentSystem' => ['NULL', 'IS']
						]
					]];
				}
				$aSalesSearchFilters['$AND']['ProductUUID'] = $Filters['ProductUUID'];
			}
			else
			{
				//apply filters after search
				$mSalesSearchFilters = $this->getSalesSearch($Search);
				if (isset($Filters['GetDownloads']) && $Filters['GetDownloads'])
				{
					$aSalesSearchFilters = ['$AND' => [
							self::GetName() . '::PaymentSystem' => Enums\PaymentSystem::Download
						]
					];
					if ($mSalesSearchFilters)
					{
						$aSalesSearchFilters['$AND']['$AND'] = $mSalesSearchFilters;
					}
				}
				else
				{
					$aSalesSearchFilters = [
						'$OR' => [
							'1@' . self::GetName() . '::PaymentSystem' => [Enums\PaymentSystem::Download, '!='],
							'2@' . self::GetName() . '::PaymentSystem' => ['NULL', 'IS']
						]
					];
					if ($mSalesSearchFilters)
					{
						$aSalesSearchFilters['1@$AND'] = $mSalesSearchFilters;
					}
					if (isset($Filters['NotParsed']) && $Filters['NotParsed'])
					{
						$aSalesSearchFilters['2@$AND'] = [self::GetName() . '::ParsingStatus' => \Aurora\Modules\Sales\Enums\ParsingStatus::NotParsed];
					}
				}
			}
		}
		else
		{
			$mSalesSearchFilters = $this->getSalesSearch($Search);
			//Select sales by default
			$aSalesSearchFilters = [
				'$OR' => [
					'1@' . self::GetName() . '::PaymentSystem' => [Enums\PaymentSystem::Download, '!='],
					'2@' . self::GetName() . '::PaymentSystem' => ['NULL', 'IS']
				]
			];
			if ($mSalesSearchFilters !== false)
			{
				$aSalesSearchFilters['$AND'] = $mSalesSearchFilters;
			}
		}

		return $aSalesSearchFilters;
	}

	/**
	 * Prepare filters from search string.
	 *
	 * @param string $Search Search string.
	 * @return array|false
	 */
	protected function getSalesSearch($Search = '')
	{
		$aSalesSearchFilters = [];
		if (!empty($Search))
		{
			$aSearchCustomers = [];

			$aProductSearchFilters = [
				'Title' => ['%'.$Search.'%', 'LIKE']
			];
			$aSearchProducts = $this->getManager('Products')->getProducts(0, 0, $aProductSearchFilters, [
				'ProductGroupUUID',
				'Title',
				self::GetName() . '::ShareItProductId',
			]);
			$aContactsSearchFilters = ['$OR' => [
					'Email' => ['%'.$Search.'%', 'LIKE'],
					'FullName' => ['%'.$Search.'%', 'LIKE'],
					self::GetName() . '::FirstName' => ['%'.$Search.'%', 'LIKE'],
					self::GetName() . '::LastName' => ['%'.$Search.'%', 'LIKE']
				]
			];
			$aSearchContacts = $this->getManager('Contacts')->getContacts(0, 0, $aContactsSearchFilters, ['CustomerUUID']);
			if (is_array($aSearchContacts) && count($aSearchContacts) > 0)
			{
				$aCustomerUIDs = [];
				foreach ($aSearchContacts as $oContact)
				{
					$aCustomerUIDs[] = $oContact->CustomerUUID;
				}
				$aCustomersSearchFilters['UUID'] = [$aCustomerUIDs, 'IN'];
				$aSearchCustomers = $this->getManager('Customers')->getCustomers(0, 0, $aCustomersSearchFilters, [self::GetName() . '::Email']);
			}

			$aSalesSearchFilters = [
				self::GetName() . '::LicenseKey' => ['%'.$Search.'%', 'LIKE'],
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
		return empty($aSalesSearchFilters) ? false : $aSalesSearchFilters;
	}

	/**
	 * Get all sales.
	 *
	 * @param int $Limit Limit.
	 * @param int $Offset Offset.
	 * @param string $Search Search string.
	 * @param string $ProductUUID UUID of searched product.
	 * @param bool $GetDownloads
	 * @return array
	 */
	public function GetSales($Limit = 20, $Offset = 0, $Search = '', $Filters = [])
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$aCustomersUUID = [];
		$aProductsUUID = [];
		$Search = \trim($Search);
		$aSalesSearchFilters = $this->getSalesFilters($Search, $Filters);
		$iSalesCount = (int)$this->getManager('Sales')->getSalesCount($aSalesSearchFilters);
		$aSales = $this->getManager('Sales')->getSales($Limit, $Offset, $aSalesSearchFilters, [
			'CustomerUUID',
			'ProductUUID',
			'Date',
			'Price',
			self::GetName() . '::LicenseKey',
			self::GetName() . '::MaintenanceExpirationDate',
			self::GetName() . '::Payment',
			self::GetName() . '::PayPalItem',
			self::GetName() . '::VatId',
			self::GetName() . '::RefNumber',
			self::GetName() . '::ShareItPurchaseId',
			self::GetName() . '::IsNotified',
			self::GetName() . '::RecurrentMaintenance',
			self::GetName() . '::TwoMonthsEmailSent',
			self::GetName() . '::ParentSaleId',
			self::GetName() . '::PaymentSystem',
			self::GetName() . '::NumberOfLicenses',
			self::GetName() . '::MessageSubject',
			self::GetName() . '::ParsingStatus',
			self::GetName() . '::RawEmlData',
			self::GetName() . '::TransactionId',
			self::GetName() . '::Reseller',
			self::GetName() . '::PromotionName',
			// Download section
			self::GetName() . '::DownloadId',
			self::GetName() . '::Referer',
			self::GetName() . '::Ip',
			self::GetName() . '::Gad',
			self::GetName() . '::ProductVersion',
			self::GetName() . '::LicenseType',
			self::GetName() . '::ReferrerPage',
			self::GetName() . '::IsUpgrade',
			self::GetName() . '::PlatformType'
		]);

		foreach ($aSales as &$oSale)
		{
			if (isset($oSale->CustomerUUID) && !empty($oSale->CustomerUUID))
			{
				$aCustomersUUID[] = $oSale->CustomerUUID;
			}
			if (isset($oSale->ProductUUID) && !empty($oSale->ProductUUID))
			{
				$aProductsUUID[] = $oSale->ProductUUID;
			}
			if (isset($oSale->{self::GetName() . '::RawEmlData'}) && !empty($oSale->{self::GetName() . '::RawEmlData'}))
			{
				$oSale->IsEmlAvailable = 1;
				$oSale->{self::GetName() . '::RawEmlData'} = null;
			}
			if (!empty($this->SxGeo) && !empty($oSale->{self::GetName() . '::Ip'}))
			{
				$aCity = $this->SxGeo->getCityFull($oSale->{self::GetName() . '::Ip'});
				$oSale->City = !empty($aCity["city"]["name_en"]) ? $aCity["city"]["name_en"] : '';
				$oSale->Country =!empty($aCity["country"]["name_en"]) ? $aCity["country"]["name_en"] : '';
			}
		}
		$aCustomers = count($aCustomersUUID) > 0 ? $this->getManager('Customers')->getCustomers(0, 0, ['UUID' => [\array_unique($aCustomersUUID), 'IN']]) : [];
		$aProducts = count($aProductsUUID) > 0 ? $this->getManager('Products')->getProducts(0, 0, ['UUID' => [\array_unique($aProductsUUID), 'IN']]) : [];
		$aCompanies = count($aCustomersUUID) > 0 ? $this->getManager('Companies')->getCompanies(0, 0, ['CustomerUUID' => [\array_unique($aCustomersUUID), 'IN']]) : [];

		//add Contact and Company information to oCustomer
		if (count($aCustomersUUID) > 0)
		{
			$aContacts = $this->getManager('Contacts')->getContacts(0, 0, ['CustomerUUID' => [\array_unique($aCustomersUUID), 'IN']]);
			foreach ($aContacts as $oContact)
			{
				if (isset($aCustomers[$oContact->CustomerUUID]) && !isset($aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::FullName'}))
				{
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::ContactId'} = $oContact->EntityId;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::ContactUUID'} = $oContact->UUID;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::FullName'} = $oContact->FullName;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::Email'} = $oContact->Email;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::Address'} = $oContact->Address;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::Phone'} = $oContact->Phone;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::Facebook'} = $oContact->Facebook;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::LinkedIn'} = $oContact->LinkedIn;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::Instagram'} = $oContact->Instagram;
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::Fax'} = $oContact->{self::GetName() . '::Fax'};
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::Salutation'} = $oContact->{self::GetName() . '::Salutation'};
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::LastName'	} = $oContact->{self::GetName() . '::LastName'};
					$aCustomers[$oContact->CustomerUUID]->{self::GetName() . '::FirstName'} = $oContact->{self::GetName() . '::FirstName'};
				}
			}
			foreach ($aCompanies as $oCompany)
			{
				if (isset($aCustomers[$oCompany->CustomerUUID]))
					{
						$aCustomers[$oCompany->CustomerUUID]->{self::GetName() . '::Company_Id'} = $oCompany->EntityId;
						$aCustomers[$oCompany->CustomerUUID]->{self::GetName() . '::Company_UUID'} = $oCompany->UUID;
						$aCustomers[$oCompany->CustomerUUID]->{self::GetName() . '::Company_Title'} = $oCompany->Title;
						$aCustomers[$oCompany->CustomerUUID]->{self::GetName() . '::Company_Description'} = $oCompany->Description;
						$aCustomers[$oCompany->CustomerUUID]->{self::GetName() . '::Company_Address'} = $oCompany->Address;
						$aCustomers[$oCompany->CustomerUUID]->{self::GetName() . '::Company_Phone'} = $oCompany->Phone;
						$aCustomers[$oCompany->CustomerUUID]->{self::GetName() . '::Company_Website'} = $oCompany->Website;
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

	public function GetChartSales($FromDate = '', $TillDate = '', $Search = '', $GetDownloads = false)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$aFilters = $this->getSalesFilters($Search, ['GetDownloads' => $GetDownloads]);

		if ($FromDate && $TillDate)
		{
			$aFilters['1@Date'] = [
				(string) $FromDate,
				'>='
			];
			$aFilters['2@Date'] = [
				(string) $TillDate . ' 23:59:59',
				'<='
			];
		}
		if (!$GetDownloads)
		{
			if (isset($aFilters['$OR']))
			{
				$i = 1;
				do {
					$sOR = $i++ . "@\$OR";
				} while (isset($aFilters[$sOR]));
			}
			else
			{
				$sOR = '$OR';
			}

			$aFilters[$sOR] = [
				'1@' . self::GetName() . '::ParsingStatus' => [\Aurora\Modules\Sales\Enums\ParsingStatus::NotParsed, '!='],
				'2@' . self::GetName() . '::ParsingStatus' => ['NULL', 'IS']
			];
		}
		$aSales = $this->getManager('Sales')->getSales(0, 0, $aFilters, ['Date', 'Price']);

		$fGetChartData = function($value) {
			return ['Date' => $value->Date, 'Price' => $value->Price];
		};

		return array_map($fGetChartData, $aSales);
	}
	
	/**
	 * Get all products.
	 *
	 * @param int $Limit Limit.
	 * @param int $Offset Offset.
	 * @param string $Search Search string.
	 * @return array
	 */
	public function GetProducts($Limit = 0, $Offset = 0, $Search = "", $Filters = [])
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aSearchFilters = [];
		$aProductGroupsWithUUIDs = [];
		$Search = \trim($Search);
		if (!empty($Search))
		{
			$aSearchFilters = [
				'Title' => ['%'.$Search.'%', 'LIKE']
			];
		}
		if (is_array($Filters) && !empty($Filters))
		{
			foreach ($Filters as $sFilter => $bValue)
			{
				if ($sFilter === 'Autocreated' && !!$bValue)
				{
					if (is_array($aSearchFilters) && !empty($aSearchFilters))
					{
						$aSearchFilters = ['$AND' => $aSearchFilters];
					}
					$aSearchFilters['$OR'] = [
						'1@' . self::GetName() . '::IsAutocreated' => true,
						'2@' . self::GetName() . '::IsAutocreated' => ['NULL', 'IS']
					];
				}
			}
		}
		$aProductGroups = $this->getManager('ProductGroups')->getProductGroups(0, 0, [], ['Title']);
		foreach ($aProductGroups as $oProductGroup)
		{
			$aProductGroupsWithUUIDs[$oProductGroup->UUID] = $oProductGroup;
		}
		$aProducts = $this->getManager('Products')->getProducts($Limit, $Offset, $aSearchFilters);
		foreach ($aProducts as &$oProduct)
		{
			$oProductGroup = isset($aProductGroupsWithUUIDs[$oProduct->ProductGroupUUID]) ? $aProductGroupsWithUUIDs[$oProduct->ProductGroupUUID] : null;
			$oProduct->ProductGroupTitle = isset($oProductGroup) ? $oProductGroup->Title : '';
		}
		return [
			'Products' => is_array($aProducts) ? $aProducts : [],
			'ItemsCount' => (int)$this->getManager('Products')->getProductsCount($aSearchFilters)
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
		$Search = \trim($Search);
		if (!empty($Search))
		{
			$aSearchFilters = [
				'Title' => ['%'.$Search.'%', 'LIKE']
			];
		}
		$aProductGroups = $this->getManager('ProductGroups')->getProductGroups($Limit, $Offset, $aSearchFilters);
		return [
			'ProductGroups' => is_array($aProductGroups) ? $aProductGroups : [],
			'ItemsCount' => (int)$this->getManager('ProductGroups')->getProductGroupsCount($aSearchFilters)
		];
	}

	public function UpdateProduct($ProductId,
		$Title = null,
		$CrmProductId = null,
		$ShareItProductId = null,
		$IsAutocreated = null,
		$ProductGroupUUID = null,
		$Description = null,
		$Homepage = null,
		$ProductPrice = null,
		$Status = 0,
		$PayPalItem = null,
		$MailchimpGroupTitle = null,
		$MailchimpGroupUUID = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oProduct = $this->getManager('Products')->getProductByIdOrUUID($ProductId);
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
			$oProduct->{self::GetName() . '::ShareItProductId'} = $ShareItProductId;
		}
		if (isset($CrmProductId))
		{
			$oProduct->{self::GetName() . '::CrmProductId'} = $CrmProductId;
		}
		if (isset($PayPalItem))
		{
			$oProduct->{self::GetName() . '::PayPalItem'} = $PayPalItem;
		}
		if (isset($IsAutocreated))
		{
			$oProduct->{self::GetName() . '::IsAutocreated'} = $IsAutocreated;
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
		if (isset($MailchimpGroupTitle))
		{
			$oProduct->{self::GetName() . '::MailchimpGroupTitle'} = $MailchimpGroupTitle;
		}
		if (isset($MailchimpGroupUUID))
		{
			$oProduct->{self::GetName() . '::MailchimpGroupUUID'} = $MailchimpGroupUUID;
		}
		return $this->getManager('Products')->UpdateProduct($oProduct);
	}

	public function UpdateProductGroup($ProductGroupId, $Title = null, $Homepage = null, $ProductCode = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oProductGroup = $this->getManager('ProductGroups')->getProductGroupByIdOrUUID($ProductGroupId);
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
			$oProductGroup->{self::GetName() . '::ProductCode'} = (int) $ProductCode;
		}
		return $this->getManager('ProductGroups')->UpdateProductGroup($oProductGroup);
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

		$oSale = $this->getManager('Sales')->getSaleByIdOrUUID((int) $SaleId);
		if ($oSale instanceof \Aurora\Modules\SaleObjects\Classes\Sale)
		{
			if (isset($ProductIdOrUUID))
			{
				$Product = $this->getManager('Products')->getProductByIdOrUUID($ProductIdOrUUID);
			}
			$oSale->ProductUUID = isset($Product, $Product->UUID) ? $Product->UUID : $oSale->ProductUUID;
			$oSale->Date = isset($Date) ? $Date : $oSale->Date;
			$oSale->{self::GetName() . '::VatId'} = isset($VatId) ? $VatId : $oSale->{self::GetName() . '::VatId'};
			$oSale->{self::GetName() . '::Payment'} = isset($Payment) ? $Payment : $oSale->{self::GetName() . '::Payment'};
			$oSale->CustomerUUID = isset($CustomerUUID) ? $CustomerUUID : $oSale->CustomerUUID;
			$oSale->{self::GetName() . '::LicenseKey'} = isset($LicenseKey) ? $LicenseKey : $oSale->{self::GetName() . '::LicenseKey'};
			$oSale->{self::GetName() . '::RefNumber'} = isset($RefNumber) ? $RefNumber : $oSale->{self::GetName() . '::RefNumber'};
			$oSale->Price = isset($Price) ? $Price : $oSale->Price;
			$oSale->{self::GetName() . '::ShareItPurchaseId'} = isset($ShareItPurchaseId) ? $ShareItPurchaseId : $oSale->{self::GetName() . '::ShareItPurchaseId'};
			$oSale->{self::GetName() . '::IsNotified'} = isset($IsNotified) ? $IsNotified : $oSale->{self::GetName() . '::IsNotified'};
			$oSale->{self::GetName() . '::MaintenanceExpirationDate'} = isset($MaintenanceExpirationDate) ? $MaintenanceExpirationDate : $oSale->{self::GetName() . '::MaintenanceExpirationDate'};
			$oSale->{self::GetName() . '::RecurrentMaintenance'} = isset($RecurrentMaintenance) ? $RecurrentMaintenance : $oSale->{self::GetName() . '::RecurrentMaintenance'};
			$oSale->{self::GetName() . '::TwoMonthsEmailSent'} = isset($TwoMonthsEmailSent) ? $TwoMonthsEmailSent : $oSale->{self::GetName() . '::TwoMonthsEmailSent'};
			$oSale->{self::GetName() . '::ParentSaleId'} = isset($ParentSaleId) ? $ParentSaleId : $oSale->{self::GetName() . '::ParentSaleId'};
			$oSale->{self::GetName() . '::PaymentSystem'} = isset($PaymentSystem) ? $PaymentSystem : $oSale->{self::GetName() . '::PaymentSystem'};
			$oSale->{self::GetName() . '::PayPalItem'} = isset($PayPalItem) ? $PayPalItem : $oSale->{self::GetName() . '::PayPalItem'};
		}
		else
		{
			return false;
		}

		return $this->getManager('Sales')->UpdateSale($oSale);
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
		$CrmProductId = '',
		$Subscribe = false
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$bResult = false;
		if ($ProductCode !== 0 && !$CrmProductId)
		{
			$oProduct = $this->getManager('Products')->getDefaultProductByGroupCode($ProductCode);
			if ($oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
			{
				$CrmProductId = $oProduct->{self::GetName() . '::CrmProductId'};
			}
		}

		$mSale = self::Decorator()->CreateSale(
			'Download',
			 Enums\PaymentSystem::Download,
			 0,
			 $Email,
			 '',
			 $ProductTitle,
			 $ProductCode,
			 null,
			 '',
			 $Date,
			 $TrialKey,
			 0,
			 $CrmProductId,
			 '',
			 '',
			 false,
			 true,
			 false,
			 0,
			 '',
			 '',
			 '',
			 '',
			 '',
			 '',
			 '',
			 '',
			 '',
			 '',
			 '',
			 '',
			 0,
			 '',
			 \Aurora\Modules\Sales\Enums\ParsingStatus::Unknown,
			 '',
			 '',
			 $Subscribe
		);
		if ($mSale)
		{
			$mSale->{self::GetName() . '::DownloadId'} = $DownloadId;
			$mSale->{self::GetName() . '::Referer'} = $Referer;
			$mSale->{self::GetName() . '::Ip'} = $Ip;
			$mSale->{self::GetName() . '::Gad'} = $Gad; 
			$mSale->{self::GetName() . '::ProductVersion'} = $ProductVersion; 
			$mSale->{self::GetName() . '::LicenseType'} = $LicenseType; 
			$mSale->{self::GetName() . '::ReferrerPage'} = $ReferrerPage; 
			$mSale->{self::GetName() . '::IsUpgrade'} = $IsUpgrade;
			$mSale->{self::GetName() . '::PlatformType'} = $PlatformType;

			$bResult = $this->getManager('Sales')->updateSale($mSale);
		}

		if ($bResult)
		{
			return $mSale;
		}

		return false;
	}

	public function CreateProducts()
	{
		$bPrevState = \Aurora\System\Api::skipCheckUserRole(true);
		$aProductGroups = $this->getManager('ProductGroups')->getProductGroups();
		if (is_array($aProductGroups))
		{
			foreach ($aProductGroups as $oProductGroup)
			{
				if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
				{
					$oProduct = new \Aurora\Modules\SaleObjects\Classes\Product(self::GetName());
					$oProduct->ProductGroupUUID = $oProductGroup->UUID;
					$oProduct->Title = 'Free';
					$oProduct->{self::GetName() . '::ProductCode'} = $oProductGroup->{self::GetName() . '::ProductCode'};
					$this->getManager('Products')->updateProduct($oProduct);
				}
			}
		}
		\Aurora\System\Api::skipCheckUserRole($bPrevState);
	}
	
	public function CreateGroups()
	{
		$bPrevState = \Aurora\System\Api::skipCheckUserRole(true);
		$aGroups = [
			["Title" => "MailBee Objects","ProductCode" => "1"],
			["Title" => "MailBee POP3 Component","ProductCode" => "2"],
			["Title" => "MailBee SMTP Component","ProductCode" => "3"],
			["Title" => "MailBee IMAP4 Component","ProductCode" => "4"],
			["Title" => "MailBee Message Queue","ProductCode" => "5"],
			["Title" => "MailBee S\/MIME Component","ProductCode" => "6"],
			["Title" => "Undefined 7","ProductCode" => "7"],
			["Title" => "Undefined 11","ProductCode" => "11"],
			["Title" => "Undefined 16","ProductCode" => "16"],
			["Title" => "Afterlogic WebMail Pro ASP.NET","ProductCode" => "17"],
			["Title" => "Undefined 18","ProductCode" => "18"],
			["Title" => "Undefined 19","ProductCode" => "19"],
			["Title" => "Undefined 20","ProductCode" => "20"],
			["Title" => "Undefined 21","ProductCode" => "21"],
			["Title" => "Afterlogic WebMail Pro PHP","ProductCode" => "22"],
			["Title" => "Undefined 24","ProductCode" => "24"],
			["Title" => "Undefined 30","ProductCode" => "30"],
			["Title" => "Undefined 31","ProductCode" => "31"],
			["Title" => "MailBee.NET Objects","ProductCode" => "32"],
			["Title" => "MailBee.NET POP3 Component","ProductCode" => "33"],
			["Title" => "MailBee.NET SMTP Component","ProductCode" => "34"],
			["Title" => "MailBee.NET IMAP Component","ProductCode" => "35"],
			["Title" => "MailBee.NET Security Component","ProductCode" => "36"],
			["Title" => "MailBee.NET AntiSpam Component","ProductCode" => "37"],
			["Title" => "MailBee.NET Outlook Converter","ProductCode" => "38"],
			["Title" => "Undefined 39","ProductCode" => "39"],
			["Title" => "Undefined 40","ProductCode" => "40"],
			["Title" => "PRODUCT_XMAIL_SERVER_PRO_WIN","ProductCode" => "41"],
			["Title" => "PRODUCT_XMAIL_SERVER_PRO_LINUX","ProductCode" => "42"],
			["Title" => "Undefined 43","ProductCode" => "43"],
			["Title" => "Afterlogic MailSuite Pro","ProductCode" => "44"],
			["Title" => "Undefined 45","ProductCode" => "45"],
			["Title" => "Undefined 46","ProductCode" => "46"],
			["Title" => "MailBee.NET IMAP Bundle","ProductCode" => "48"],
			["Title" => "MailBee.NET POP3 Bundle","ProductCode" => "49"],
			["Title" => "Undefined 50","ProductCode" => "50"],
			["Title" => "Undefined 51","ProductCode" => "51"],
			["Title" => "Undefined 52","ProductCode" => "52"],
			["Title" => "Undefined 53","ProductCode" => "53"],
			["Title" => "Undefined 54","ProductCode" => "54"],
			["Title" => "Undefined 55","ProductCode" => "55"],
			["Title" => "Undefined 95","ProductCode" => "95"],
			["Title" => "Undefined 96","ProductCode" => "96"],
			["Title" => "Undefined 97","ProductCode" => "97"],
			["Title" => "Undefined 98","ProductCode" => "98"],
			["Title" => "Undefined 99","ProductCode" => "99"]
		];
		foreach ($aGroups as $aGroup)
		{
			$oProductGroup = new \Aurora\Modules\SaleObjects\Classes\ProductGroup(self::GetName());
			$oProductGroup->Title = $aGroup['Title'];
			$oProductGroup->{self::GetName() . '::ProductCode'} = (int) $aGroup['ProductCode'];
			$iProductGroupId = $this->getManager('ProductGroups')->createProductGroup($oProductGroup);
			unset($oProductGroup);

			$oProductGroup = $this->getManager('ProductGroups')->getProductGroupByIdOrUUID($iProductGroupId);
			$oProduct = new \Aurora\Modules\SaleObjects\Classes\Product(self::GetName());
			$oProduct->ProductGroupUUID = $oProductGroup->UUID;
			$oProduct->{self::GetName() . '::IsAutocreated'} = true;
			$oProduct->Title = $aGroup['Title'] . " free license";
			$oProduct->{self::GetName() . '::IsDefault'} = true;
			$oProduct->{self::GetName() . '::CrmProductId'} = "CRM" . $aGroup['ProductCode'];
			$this->getManager('Products')->createProduct($oProduct);
		}
		\Aurora\System\Api::skipCheckUserRole($bPrevState);
		return true;
	}

	/**
	 * Creates product.
	 * @param string $Title Product name.
	 * @param string $ShareItProductId ShareIt product ID.
	 * @param boolean $IsAutocreated Is product was created automatically.
	 * @param string $ProductGroupUUID UUID of product group.
	 * @param string $Description Description.
	 * @param string $Homepage Homepage.
	 * @param double $ProductPrice Product price.
	 * @param int $Status Product status.
	 *
	 * @return int|boolean
	 */
	public function CreateProduct($Title,
		$ShareItProductId = '',
		$CrmProductId = '',
		$IsAutocreated = false,
		$ProductGroupUUID = '',
		$Description = '',
		$Homepage = '',
		$ProductPrice = 0,
		$Status = 0,
		$PayPalItem = '',
		$MailchimpGroupTitle = '',
		$MailchimpGroupUUID = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oProduct = new \Aurora\Modules\SaleObjects\Classes\Product(self::GetName());
		if (isset($ProductGroupUUID) && !empty($ProductGroupUUID))
		{
			$oProductGroup = $this->getManager('ProductGroups')->getProductGroupByIdOrUUID($ProductGroupUUID);
			if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
			{
				$oProduct->ProductGroupUUID = $oProductGroup->UUID;
			}
		}
		$oProduct->{self::GetName() . '::ShareItProductId'} = $ShareItProductId;
		$oProduct->{self::GetName() . '::CrmProductId'} = $CrmProductId;
		$oProduct->{self::GetName() . '::IsAutocreated'} = $IsAutocreated;
		$oProduct->{self::GetName() . '::PayPalItem'} = $PayPalItem;
		$oProduct->Title = $Title;
		$oProduct->Description = $Description;
		$oProduct->Homepage = $Homepage;
		$oProduct->Price = $ProductPrice;
		$oProduct->Status = $Status;
		$oProduct->{self::GetName() . '::MailchimpGroupTitle'} = $MailchimpGroupTitle;
		$oProduct->{self::GetName() . '::MailchimpGroupUUID'} = $MailchimpGroupUUID;

		return $this->getManager('Products')->createProduct($oProduct);
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
		$oProductGroup = new \Aurora\Modules\SaleObjects\Classes\ProductGroup(self::GetName());
		$oProductGroup->{self::GetName() . '::ProductCode'} = $ProductCode;
		$oProductGroup->Title = $Title;
		$oProductGroup->Description = $Description;
		$oProductGroup->Homepage = $Homepage;

		return $this->getManager('ProductGroups')->createProductGroup($oProductGroup);
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
	public function CreateContact($FullName = '', $CustomerUUID = '', $CompanyUUID = '', $Address = '', $Phone = '', $Email = '', $FirstName = '', $LastName = '', $Fax = '', $Salutation = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oContact = new \Aurora\Modules\ContactObjects\Classes\Contact(self::GetName());
		$oContact->CustomerUUID = $CustomerUUID;
		$oContact->CompanyUUID = $CompanyUUID;
		$oContact->FullName = $FullName;
		$oContact->Address = $Address;
		$oContact->Phone = $Phone;
		$oContact->Email = $Email;
		$oContact->{self::GetName() . '::FirstName'} = $FirstName;
		$oContact->{self::GetName() . '::LastName'} = $LastName;
		$oContact->{self::GetName() . '::Fax'} = $Fax;
		$oContact->{self::GetName() . '::Salutation'} = $Salutation;
		return $this->getManager('Contacts')->createContact($oContact);
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
		$oCustomer = new \Aurora\Modules\SaleObjects\Classes\Customer(self::GetName());
		$oCustomer->Title = $Title;
		$oCustomer->Description = $Description;
		$oCustomer->Status = $Status;
		$oCustomer->{self::GetName() . '::Language'} = $Language;
		return $this->getManager('Customers')->createCustomer($oCustomer);
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
		$ContactFullName = '',
		$CustomerTitle = '', $CustomerDescription = '', $CustomerStatus = 0, $CustomerLanguage = '',
		$Address = '', $Phone = '', $Email = '', $FirstName = '', $LastName = '', $Fax = '', $Salutation = '',
		$Company = ''
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$Company = \trim($Company);
		$iCustomerId = $this->CreateCustomer($CustomerTitle, $CustomerDescription, $CustomerStatus, $CustomerLanguage);
		if ($iCustomerId)
		{
			$oCustomer = $this->getManager('Customers')->getCustomerByIdOrUUID($iCustomerId);
		}
		if (!$oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
		{
			return false;
		}
		if ($Company !== '')
		{
			$oCompany = new \Aurora\Modules\ContactObjects\Classes\Company(self::GetName());
			$oCompany->Title = $Company;
			$oCompany->CustomerUUID = $oCustomer->UUID;
			$iCompanyId = $this->getManager('Companies')->createCompany($oCompany);
			if ($iCompanyId)
			{
				$oCompany = $this->getManager('Companies')->getCompanyByIdOrUUID($iCompanyId);
			}
			if (!$oCompany instanceof \Aurora\Modules\ContactObjects\Classes\Company)
			{
				return false;
			}
		}

		$oContact = $Email ? $this->getManager('Contacts')->getContactByEmail($Email) : null;
		if (!$oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact)
		{
			if (!empty($ContactFullName) || !empty($Email) || !empty($Address) || !empty($Phone) || !empty($FirstName) || !empty($LastName))
			{
				$iContactId = self::Decorator()->CreateContact(
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

		$oProductGroup = $this->getManager('ProductGroups')->getProductGroupByIdOrUUID($IdOrUUID);

		if ($oProductGroup instanceof \Aurora\Modules\SaleObjects\Classes\ProductGroup)
		{
			$aProducts = $this->getManager('Products')->getProductsByGroup($oProductGroup->UUID);
			if (is_array($aProducts) && count($aProducts) > 0)
			{
				throw new \Aurora\System\Exceptions\BaseException(Enums\ErrorCodes::DataIntegrity);
			}
			$mResult = $this->getManager('ProductGroups')->deleteProductGroup($oProductGroup);
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

		$oProduct = $this->getManager('Products')->getProductByIdOrUUID($IdOrUUID);
		if ($oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
		{
			$aSearchFilters = ['ProductUUID' => $oProduct->UUID];
			$aSales = $this->getManager('Sales')->getSales(0, 0, $aSearchFilters, ['UUID']);
			if (is_array($aSales) && count($aSales) > 0)
			{
				throw new \Aurora\System\Exceptions\BaseException(Enums\ErrorCodes::DataIntegrity);
			}
			$mResult = $this->getManager('Products')->deleteProduct($oProduct);
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

		$oContact = $this->getManager('Contacts')->getContactByIdOrUUID($IdOrUUID);
		if ($oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact)
		{
			$mResult = $this->getManager('Contacts')->deleteContact($oContact);
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
		$Search = \trim($Search);
		if (!empty($Search))
		{
			$aSearchFilters = ['$OR' => [
				'FullName' => ['%'.$Search.'%', 'LIKE'],
				'Address' => ['%'.$Search.'%', 'LIKE'],
				'Phone' => ['%'.$Search.'%', 'LIKE'],
				'Email' => ['%'.$Search.'%', 'LIKE'],
				'Facebook' => ['%'.$Search.'%', 'LIKE'],
				'LinkedIn' => ['%'.$Search.'%', 'LIKE'],
				'Instagram' => ['%'.$Search.'%', 'LIKE'],
				self::GetName() . '::Fax' => ['%'.$Search.'%', 'LIKE'],
				self::GetName() . '::Salutation' => ['%'.$Search.'%', 'LIKE'],
				self::GetName() . '::LastName' => ['%'.$Search.'%', 'LIKE'],
				self::GetName() . '::FirstName' =>['%'.$Search.'%', 'LIKE']
			]];
		}
		$aContacts = $this->getManager('Contacts')->getContacts($Limit, $Offset, $aSearchFilters);
		return [
			'Contacts' => is_array($aContacts) ? $aContacts : [],
			'ItemsCount' => (int)$this->getManager('Contacts')->getContactsCount($aSearchFilters)
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
		$oContact = $this->getManager('Contacts')->getContactByIdOrUUID($ContactUUID);
		if ($oContact instanceof \Aurora\Modules\ContactObjects\Classes\Contact && isset($oContact->CustomerUUID))
		{
			$oCustomer = $this->getManager('Customers')->getCustomerByIdOrUUID($oContact->CustomerUUID);
			if ($oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
			{
				$aSalesFilters = ['CustomerUUID' => $oCustomer->UUID];
				$aSales = $this->getManager('Sales')->getSales(0, 0, $aSalesFilters);

				$aResult = [
					'Sales' => is_array($aSales) ? $aSales : [],
					'SalesCount' => $this->getManager('Sales')->getSalesCount($aSalesFilters)
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

		$oContact = $this->getManager('Contacts')->getContactByIdOrUUID($ContactId);
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
			$oContact->{self::GetName() . '::Fax'} = $Fax;
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
			$oContact->{self::GetName() . '::Salutation'} = $Salutation;
		}
		if (isset($LastName))
		{
			$oContact->{self::GetName() . '::LastName'} = $LastName;
		}
		if (isset($FirstName))
		{
			$oContact->{self::GetName() . '::FirstName'} = $FirstName;
		}
		return $this->getManager('Contacts')->UpdateContact($oContact);
	}

	public function EntryDownloadEmlFile()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$UUID = (string) \Aurora\System\Router::getItemByIndex(1, '');
		if (!empty($UUID))
		{
			$oSale = $this->getManager('Sales')->getSaleByIdOrUUID($UUID);
			if ($oSale instanceof \Aurora\Modules\SaleObjects\Classes\Sale)
			{
				$sFileName = $oSale->{self::GetName() . '::MessageSubject'} . '.eml';
				\header('Content-Type: message/rfc822', true);
				\header('Content-Disposition: attachment; '.
					\trim(\MailSo\Base\Utils::EncodeHeaderUtf8AttributeValue('filename', $sFileName)), true);
				echo $oSale->{self::GetName() . '::RawEmlData'};
			}
		}
	}

	/**
	 * Delete sale.
	 * @param string $UUID Sale UUID
	 *
	 * @return int|boolean
	 */
	public function DeleteSale($UUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$mResult = false;
		$oSale = $this->getManager('Sales')->getSaleByIdOrUUID($UUID);
		if ($oSale instanceof \Aurora\Modules\SaleObjects\Classes\Sale)
		{
			$mResult = $this->getManager('Sales')->deleteSale($oSale);
		}
		return $mResult;
	}
	
	public function ImportSales()
	{
		set_time_limit(0);
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		\Aurora\System\Api::Log('Start import ', \Aurora\System\Enums\LogLevel::Full, 'import-sales-');
		
		if (!empty($this->oPDO))
		{
			$sQuery = "SELECT * FROM sale";//OFFSET 1000
			$stmt = $this->oPDO->prepare($sQuery);
			$stmt->execute();
			$aResult = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			foreach ($aResult as $aSale)
			{
				if ((int) $aSale['shareit_product_id'] < 1)
				{
					continue;
				}
				$aAdressParts = [$aSale['street'], $aSale['city'], $aSale['state'], $aSale['zip'], $aSale['country']];
				$aAdressPartsClear = [];
				foreach ($aAdressParts as $sPart)
				{
					if (trim($sPart) !== '')
					{
						array_push($aAdressPartsClear, trim($sPart));
					}
				}

				$sAddress = implode(', ', $aAdressPartsClear);
				\Aurora\System\Api::Log('START: Create sale ['. $aSale['sale_id'] .']', \Aurora\System\Enums\LogLevel::Full, 'import-sales-');
				$oResult = $this->CreateSale($aSale['payment'], \Aurora\Modules\Sales\Enums\PaymentSystem::ShareIt, $aSale['net_total'],
					$aSale['email'], $aSale['reg_name'],
					$aSale['product'], $aSale['product_code'], $aSale['maintenance_expiration_date'],
					'',
					$aSale['date'], $aSale['license_key'], $aSale['ref_number'], null, $aSale['shareit_product_id'], $aSale['share_it_purchase_id'], $aSale['is_notified'], $aSale['recurrent_maintenance'], $aSale['two_months_email_sent'], $aSale['parent_sale_id'], $aSale['vat_id'],
					$aSale['salutation'], $aSale['title'], $aSale['first_name'], $aSale['last_name'], $aSale['company'], $sAddress, $aSale['phone'], $aSale['fax'], $aSale['language']
				);
				$sResult = $oResult ? 'true' : 'false';
				\Aurora\System\Api::Log('END: ' . $sResult, \Aurora\System\Enums\LogLevel::Full, 'import-sales-');
			}
		}
		\Aurora\System\Api::Log('End import ', \Aurora\System\Enums\LogLevel::Full, 'import-sales-');
		return true;
	}

	public function ImportPaypalSales()
	{
		set_time_limit(0);
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		\Aurora\System\Api::Log('Start import ', \Aurora\System\Enums\LogLevel::Full, 'import-pay-pal-');
		
		if (!empty($this->oPDO))
		{
			$sQuery = "SELECT * FROM paypal_sale";
			$stmt = $this->oPDO->prepare($sQuery);
			$stmt->execute();
			$aResult = $stmt->fetchAll(\PDO::FETCH_ASSOC);

			foreach ($aResult as $aSale)
			{
				\Aurora\System\Api::Log('START: Create sale ['. $aSale['sale_id'] .']', \Aurora\System\Enums\LogLevel::Full, 'import-pay-pal-');
				$oResult = $this->CreateSale('PayPal', \Aurora\Modules\Sales\Enums\PaymentSystem::PayPal, $aSale['payment_amount'],
					$aSale['email'], $aSale['full_name'],
					$aSale['product'], $aSale['product_id'], $aSale['maintenance_expiration_date'],
					$aSale['transaction_id'],
					$aSale['date'] . " 00:00:00"
				);
				$sResult = $oResult ? 'true' : 'false';
				\Aurora\System\Api::Log('END: ' . $sResult, \Aurora\System\Enums\LogLevel::Full, 'import-pay-pal-');
			}
		}
		else
		{
			\Aurora\System\Api::Log('Can\'t connect to source db ', \Aurora\System\Enums\LogLevel::Full, 'import-pay-pal-');
		}
		\Aurora\System\Api::Log('End import ', \Aurora\System\Enums\LogLevel::Full, 'import-pay-pal-');
		return true;
	}

	public function CreateMailchimpList($Title, $ListId, $Description = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oMailchimpList = new \Aurora\Modules\Sales\Classes\MailchimpList(self::GetName());
		$oMailchimpList->Title = $Title;
		$oMailchimpList->Description = $Description;
		$oMailchimpList->ListId = $ListId;

		return $this->getManager('Mailchimp')->createMailchimpList($oMailchimpList);
	}

	public function GetMailchimpList()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		return $this->getManager('Mailchimp')->getMailchimpList();
	}

	public function UpdateMailchimpList($Title = null, $Description = null, $ListId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$bResult = false;
		$oMailchimpList = $this->getManager('Mailchimp')->getMailchimpList();
		if (!$oMailchimpList instanceof \Aurora\Modules\Sales\Classes\MailchimpList)
		{
			$bResult = !!$this->CreateMailchimpList($Title, $ListId, $Description);
		}
		else
		{
			if (isset($Title))
			{
				$oMailchimpList->Title = $Title;
			}
			if (isset($ListId))
			{
				$oMailchimpList->ListId = $ListId;
			}
			if (isset($Description))
			{
				$oMailchimpList->Description = $Description;
			}
			$bResult = !!$this->getManager('Mailchimp')->updateMailchimpList($oMailchimpList);
		}
		return $bResult;
	}

	public function AddMemeberToMailchimpList($Email)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		return $this->getManager('Mailchimp')->addMemberToList($Email);
	}

	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oMailchimpList = $this->GetMailchimpList();
		if ($oMailchimpList instanceof \Aurora\Modules\Sales\Classes\MailchimpList)
		{
			return array(
				'Title' => $oMailchimpList->Title,
				'Description' => $oMailchimpList->Description,
				'ListId' => $oMailchimpList->ListId
			);
		}

		return null;
	}

	public function GetMailchimpGroups()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		return  [
			'Groups' =>$this->getManager('Mailchimp')->getGroups()
		];
	}

	public function UpdateSettings($Title = null, $Description = null, $ListId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		return $this->UpdateMailchimpList($Title, $Description, $ListId);
	}

	public function onCreateSale(&$aArgs, &$oSale)
	{
		if ($oSale instanceof \Aurora\Modules\SaleObjects\Classes\Sale && !empty($aArgs['Email']) && !empty($oSale->ProductUUID))
		{
			$oProduct = $this->getManager('Products')->getProductByIdOrUUID($oSale->ProductUUID);

			if ($oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product
				&& isset($oProduct->{self::GetName() . '::MailchimpGroupUUID'})
				&& $aArgs['Subscribe'] === true
			)
			{
				$oMember = $this->getManager('Mailchimp')->getMemberByEmail($aArgs['Email']);
				if (!$oMember)
				{//add member if not exists
					$this->AddMemeberToMailchimpList($aArgs['Email']);
					$oMember = $this->getManager('Mailchimp')->getMemberByEmail($aArgs['Email']);
				}
				if (isset($oMember['interests']) && is_array($oMember['interests']))
				{
					foreach ($oMember['interests'] as $GroupUUID => $bGroupValue)
					{
						if($GroupUUID === $oProduct->{self::GetName() . '::MailchimpGroupUUID'})
						{
							$oMember['interests'][$GroupUUID] = true;
						}
					}
					$this->getManager('Mailchimp')->updateMember($oMember);
				}
			}
		}
	}
}
