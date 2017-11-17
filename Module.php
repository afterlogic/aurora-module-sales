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

	/**
	 * Initializes Sales Module.
	 *
	 * @ignore
	 */

	public function init()
	{
		$this->oApiSalesManager = new Managers\Sales\Manager($this);
		$this->oApiCustomersManager = new Managers\Customers\Manager($this);
		$this->oApiProductsManager = new Managers\Products\Manager($this);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Sale',
			array(
				'ProductId' => array('int', 0),
				'Date' => array('datetime', date('Y-m-d H:i:s', 0)),
				'VatId' => array('string', ''),
				'Payment' => array('string', ''),
				'CustomerId' => array('int', 0),
				'LicenseKey' => array('string', ''),
				'RefNumber' => array('int', 0),
				'ShareItProductId' => array('int', 0),
				'NetTotal' => array('int', 0),
				'ShareItPurchaseId' => array('int', 0),
				'IsNotified' => array('bool', false),
				'MaintenanceExpirationDate' => array('datetime', date('Y-m-d H:i:s', 0)),
				'RecurrentMaintenance' => array('bool', true),
				'TwoMonthsEmailSent' => array('bool', false),
				'ParentSaleId' => array('int', 0),
				'PaymentSystem' => array('int', 0),
				'NumberOfLicenses' => array('int', 0)
			)
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Customer',
			array(
				'Email' => array('string', ''),
				'Salutation' => array('string', ''),
				'Title' => array('string', ''),
				'LastName' => array('string', ''),
				'FirstName' => array('string', ''),
				'Company' => array('string', ''),
				'Street' => array('string', ''),
				'Zip' => array('string', ''),
				'City' => array('string', ''),
				'FullCity' => array('string', ''),
				'Country' => array('string', ''),
				'State' => array('string', ''),
				'Phone' => array('string', ''),
				'Fax' => array('string', ''),
				'RegName' => array('string', ''),
				'Language' => array('string', ''),
			)
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Product',
			array(
				'ProductCode' => array('int', 0),
				'ProductName' => array('string', ''),
				'ShareItProductId' => array('int', 0),
				'IsAutocreated' => array('bool', true),
			)
		);
	}

	/**
	 * Creates sale.
	 * @param string $Payment Payment type
	 * @param string $PaymentSystem Payment system.
	 * @param datetime $MaintenanceExpirationDate Maintenance expiration date.
	 * @param string $IncomingLogin Login for IMAP connection.
	 * @param int $NetTotal Payment amount.
	 * @param string $Email Email.
	 * @param string $RegName Full name.
	 * @param string $ProductName Product name.
	 * @param string $ProductCode Product code.
	 * 
	 * @return \Aurora\Modules\SaleObjects\Classes\Sale|boolean
	 */
	public function CreateSale($Payment, $PaymentSystem, $NetTotal,
		$Email, $RegName,
		$ProductName, $ProductCode = null, $MaintenanceExpirationDate = null,
		$TransactionId = '',
		$Date = null, $LicenseKey ='', $RefNumber = 0, $ShareItProductId = 0, $ShareItPurchaseId = 0, $IsNotified = false, $RecurrentMaintenance = true, $TwoMonthsEmailSent = false, $ParentSaleId = 0, $VatId = '',
		$Salutation = '', $Title = '', $FirstName = '', $LastName = '', $Company = '', $Street = '', $Zip = '', $City = '', $FullCity = '', $Country = '', $State = '', $Phone = '', $Fax = '', $Language = '', $NumberOfLicenses = 0
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		if ($ShareItProductId > 0)
		{
			$oProduct = $this->oApiProductsManager->getProductByShareItProductId($ShareItProductId);
		}
		else
		{
			$oProduct = $this->oApiProductsManager->getProductByName($ProductName);
		}

		if (!$oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
		{
			$oProduct = new \Aurora\Modules\SaleObjects\Classes\Product($this->GetName());
			$oProduct->{$this->GetName() . '::ProductName'} = $ProductName;
			$oProduct->{$this->GetName() . '::ProductCode'} = $ProductCode;
			$oProduct->{$this->GetName() . '::ShareItProductId'} = $ShareItProductId;
			$oProduct->{$this->GetName() . '::IsAutocreated'} = true;
			$bProductResult = $this->oApiProductsManager->createProduct($oProduct);
			if ($bProductResult)
			{
				$oProduct = $this->oApiProductsManager->getProductByCode($ProductCode);
			}
			if (!$oProduct instanceof \Aurora\Modules\SaleObjects\Classes\Product)
			{
				return false;
			}
		}

		$oCustomer = $this->oApiCustomersManager->getCustomerByEmail($Email);
		if (!$oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
		{
			$oCustomer = new \Aurora\Modules\SaleObjects\Classes\Customer($this->GetName());
			$oCustomer->{$this->GetName() . '::Email'} = $Email;
			$oCustomer->{$this->GetName() . '::RegName'} = $RegName;
			$oCustomer->{$this->GetName() . '::Salutation'} = $Salutation;
			$oCustomer->{$this->GetName() . '::Title'} = $Title;
			$oCustomer->{$this->GetName() . '::FirstName'} = $FirstName;
			$oCustomer->{$this->GetName() . '::LastName'} = $LastName;
			$oCustomer->{$this->GetName() . '::Company'} = $Company;
			$oCustomer->{$this->GetName() . '::Street'} = $Street;
			$oCustomer->{$this->GetName() . '::Zip'} = $Zip;
			$oCustomer->{$this->GetName() . '::City'} = $City;
			$oCustomer->{$this->GetName() . '::FullCity'} = $FullCity;
			$oCustomer->{$this->GetName() . '::Country'} = $Country;
			$oCustomer->{$this->GetName() . '::State'} = $State;
			$oCustomer->{$this->GetName() . '::Phone'} = $Phone;
			$oCustomer->{$this->GetName() . '::Fax'} = $Fax;
			$oCustomer->{$this->GetName() . '::Language'} = $Language;
			$bCustomerResult = $this->oApiCustomersManager->CreateCustomer($oCustomer);
			if ($bCustomerResult)
			{
				$oCustomer = $this->oApiCustomersManager->getCustomerByEmail($Email);
			}
			if (!$oCustomer instanceof \Aurora\Modules\SaleObjects\Classes\Customer)
			{
				return false;
			}
		}

		$oSale = new \Aurora\Modules\SaleObjects\Classes\Sale($this->GetName());
		$oSale->{$this->GetName() . '::ProductId'} = $oProduct->EntityId;
		$oSale->{$this->GetName() . '::CustomerId'} = $oCustomer->EntityId;
		$oSale->{$this->GetName() . '::Payment'} = $Payment;
		$oSale->{$this->GetName() . '::LicenseKey'} = $LicenseKey;
		$oSale->{$this->GetName() . '::NetTotal'} = $NetTotal;
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
		$oSale->{$this->GetName() . '::NumberOfLicenses'} = $NumberOfLicenses;
		if (isset($Date))
		{
			$oSale->{$this->GetName() . '::Date'} = $Date;
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
		$aCustomersId = [];
		$aProductsId = [];
		$aSearchFilters = [];
		$aProductSearchFilters = [];
		if (!empty($Search))
		{
			$aProductSearchFilters = [
				$this->GetName() . '::ProductName' => ['%'.$Search.'%', 'LIKE']
			];
		}
		$aSearchProducts = $this->oApiProductsManager->getProducts(0, 0, $aProductSearchFilters, [
				$this->GetName() . '::ProductCode',
				$this->GetName() . '::ProductName',
				$this->GetName() . '::ShareItProductId',
			]);
		if (!empty($Search))
		{
			$aSearchCustomers = $this->oApiCustomersManager->searchCustomers($Search, [$this->GetName() . '::Email']);

			$aSearchFilters = [
				$this->GetName() . '::LicenseKey' => ['%'.$Search.'%', 'LIKE'],
				$this->GetName() . '::Date' => ['%'.$Search.'%', 'LIKE']
			];

			if (is_array($aSearchProducts) && count($aSearchProducts) > 0)
			{
				$aSearchFilters[$this->GetName() . '::ProductId'] = [array_keys($aSearchProducts), 'IN'];
			}
			if (is_array($aSearchCustomers) && count($aSearchCustomers) > 0)
			{
				$aSearchFilters[$this->GetName() . '::CustomerId'] = [array_keys($aSearchCustomers), 'IN'];
			}

			$aSearchFilters = ['$OR' => $aSearchFilters];
		}
		$iSalesCount = $this->oApiSalesManager->getSalesCount($aSearchFilters);
		$aSales = $this->oApiSalesManager->getSales($Limit, $Offset, $aSearchFilters);

		foreach ($aSales as $oSale)
		{
			$aCustomersId[] = $oSale->{$this->GetName() . '::CustomerId'};
			$aProductsId[] = $oSale->{$this->GetName() . '::ProductId'};
		}
		$aCustomers = $this->oApiCustomersManager->getCustomers(\array_unique($aCustomersId));
		if (is_array($aProductsId) && count($aProductsId) > 0)
		{
			$aProductsSearchFilter = [
				'EntityId' => [\array_unique($aProductsId), 'IN']
			];
			$aProducts = $this->oApiProductsManager->getProducts(0, 0, $aProductsSearchFilter);
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
				$this->GetName() . '::ProductName' => ['%'.$Search.'%', 'LIKE']
			];
		}
		$aProducts = $this->oApiProductsManager->getProducts($Limit, $Offset, $aSearchFilters);
		return [
			'Products' => is_array($aProducts) ? $aProducts : [],
			'ItemsCount' => $this->oApiProductsManager->getProductsCount($aSearchFilters)
		];
	}

	public function UpdateProduct($ProductId, $Name, $ProductCode = null, $ShareItProductId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oProduct = $this->oApiProductsManager->getProductById((int) $ProductId);
		$oProduct->{$this->GetName() . '::ProductName'} = $Name;
		if (isset($ProductCode))
		{
			$oProduct->{$this->GetName() . '::ProductCode'} = $ProductCode;
		}
		if (isset($ShareItProductId))
		{
			$oProduct->{$this->GetName() . '::ShareItProductId'} = $ShareItProductId;
		}
		return $this->oApiProductsManager->UpdateProduct($oProduct);
	}

	public function UpdateSale($SaleId,
		$ProductId = null,
		$Date = null,
		$VatId = null,
		$Payment = null,
		$CustomerId = null,
		$LicenseKey = null,
		$RefNumber = null,
		$ShareItProductId = null,
		$NetTotal = null,
		$ShareItPurchaseId = null,
		$IsNotified = null,
		$MaintenanceExpirationDate = null,
		$RecurrentMaintenance = null,
		$TwoMonthsEmailSent = null,
		$ParentSaleId = null,
		$PaymentSystem = null,
		$NumberOfLicenses = null
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oSale = $this->oApiSalesManager->getSaleById((int) $SaleId);
		if ($oSale instanceof \Aurora\Modules\SaleObjects\Classes\Sale)
		{
			$oSale->{$this->GetName() . '::ProductId'} = isset($ProductId) ? $ProductId : $oSale->{$this->GetName() . '::ProductId'};
			$oSale->{$this->GetName() . '::Date'} = isset($Date) ? $Date : $oSale->{$this->GetName() . '::Date'};
			$oSale->{$this->GetName() . '::VatId'} = isset($VatId) ? $VatId : $oSale->{$this->GetName() . '::VatId'};
			$oSale->{$this->GetName() . '::Payment'} = isset($Payment) ? $Payment : $oSale->{$this->GetName() . '::Payment'};
			$oSale->{$this->GetName() . '::CustomerId'} = isset($CustomerId) ? $CustomerId : $oSale->{$this->GetName() . '::CustomerId'};
			$oSale->{$this->GetName() . '::LicenseKey'} = isset($LicenseKey) ? $LicenseKey : $oSale->{$this->GetName() . '::LicenseKey'};
			$oSale->{$this->GetName() . '::RefNumber'} = isset($RefNumber) ? $RefNumber : $oSale->{$this->GetName() . '::RefNumber'};
			$oSale->{$this->GetName() . '::ShareItProductId'} = isset($ShareItProductId) ? $ShareItProductId : $oSale->{$this->GetName() . '::ShareItProductId'};
			$oSale->{$this->GetName() . '::NetTotal'} = isset($NetTotal) ? $NetTotal : $oSale->{$this->GetName() . '::NetTotal'};
			$oSale->{$this->GetName() . '::ShareItPurchaseId'} = isset($ShareItPurchaseId) ? $ShareItPurchaseId : $oSale->{$this->GetName() . '::ShareItPurchaseId'};
			$oSale->{$this->GetName() . '::IsNotified'} = isset($IsNotified) ? $IsNotified : $oSale->{$this->GetName() . '::IsNotified'};
			$oSale->{$this->GetName() . '::MaintenanceExpirationDate'} = isset($MaintenanceExpirationDate) ? $MaintenanceExpirationDate : $oSale->{$this->GetName() . '::MaintenanceExpirationDate'};
			$oSale->{$this->GetName() . '::RecurrentMaintenance'} = isset($RecurrentMaintenance) ? $RecurrentMaintenance : $oSale->{$this->GetName() . '::RecurrentMaintenance'};
			$oSale->{$this->GetName() . '::TwoMonthsEmailSent'} = isset($TwoMonthsEmailSent) ? $TwoMonthsEmailSent : $oSale->{$this->GetName() . '::TwoMonthsEmailSent'};
			$oSale->{$this->GetName() . '::ParentSaleId'} = isset($ParentSaleId) ? $ParentSaleId : $oSale->{$this->GetName() . '::ParentSaleId'};
			$oSale->{$this->GetName() . '::PaymentSystem'} = isset($PaymentSystem) ? $PaymentSystem : $oSale->{$this->GetName() . '::PaymentSystem'};
			$oSale->{$this->GetName() . '::NumberOfLicenses'} = isset($NumberOfLicenses) ? $NumberOfLicenses : $oSale->{$this->GetName() . '::NumberOfLicenses'};
		}
		else
		{
			return false;
		}

		return $this->oApiSalesManager->UpdateSale($oSale);
	}
}