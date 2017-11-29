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
		$this->oApiSalesManager = new Managers\Sales($this);
		$this->oApiCustomersManager = new Managers\Customers($this);
		$this->oApiProductsManager = new Managers\Products($this);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Sale',
			array(
				'ProductUUID' => array('string', ''),
				'VatId' => array('string', ''),
				'Payment' => array('string', ''),
				'CustomerUUID' => array('string', ''),
				'LicenseKey' => array('string', ''),
				'RefNumber' => array('int', 0),
				'ShareItProductId' => array('int', 0),
				'ShareItPurchaseId' => array('int', 0),
				'IsNotified' => array('bool', false),
				'MaintenanceExpirationDate' => array('datetime', date('Y-m-d H:i:s', 0)),
				'RecurrentMaintenance' => array('bool', true),
				'TwoMonthsEmailSent' => array('bool', false),
				'ParentSaleId' => array('int', 0),
				'PaymentSystem' => array('int', 0),
				'NumberOfLicenses' => array('int', 0),
				'RawData' => array('text', ''),
				'RawDataType' => array('int', 0),
				'PayPalItem' => array('string', '')
			)
		);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Customer',
			array(
				'Email' => array('string', ''),
				'Salutation' => array('string', ''),
				'LastName' => array('string', ''),
				'FirstName' => array('string', ''),
				'Company' => array('string', ''),
				'Address' => array('string', ''),
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
				'PayPalItem' => array('string', ''),
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
	 * @param int $Price Payment amount.
	 * @param string $Email Email.
	 * @param string $RegName Full name.
	 * @param string $ProductName Product name.
	 * @param string $ProductCode Product code.
	 * 
	 * @return \Aurora\Modules\SaleObjects\Classes\Sale|boolean
	 */
	public function CreateSale($Payment, $PaymentSystem, $Price,
		$Email, $RegName,
		$ProductName, $ProductCode = null, $MaintenanceExpirationDate = null,
		$TransactionId = '',
		$Date = null, $LicenseKey ='', $RefNumber = 0, $ShareItProductId = 0, $ShareItPurchaseId = 0, $IsNotified = false, $RecurrentMaintenance = true, $TwoMonthsEmailSent = false, $ParentSaleId = 0, $VatId = '',
		$Salutation = '', $Title = '', $FirstName = '', $LastName = '', $Company = '', $Address = '', $Phone = '', $Fax = '', $Language = '',
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
			$oProduct = new \Aurora\Modules\SaleObjects\Classes\Product($this->GetName());
			$oProduct->{$this->GetName() . '::ProductName'} = $ProductName;
			$oProduct->{$this->GetName() . '::ProductCode'} = $ProductCode;
			$oProduct->{$this->GetName() . '::ShareItProductId'} = $ShareItProductId;
			$oProduct->{$this->GetName() . '::IsAutocreated'} = true;
			$iProductId = $this->oApiProductsManager->createProduct($oProduct);
			if ($iProductId)
			{
				$oProduct = $this->oApiProductsManager->getProductById($iProductId);
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
			$oCustomer->Title = $Title;
			$oCustomer->{$this->GetName() . '::FirstName'} = $FirstName;
			$oCustomer->{$this->GetName() . '::LastName'} = $LastName;
			$oCustomer->{$this->GetName() . '::Company'} = $Company;
			$oCustomer->{$this->GetName() . '::Address'} = $Address;
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
		$oSale->{$this->GetName() . '::ProductUUID'} = isset($oProduct->UUID) ? $oProduct->UUID : '';
		$oSale->{$this->GetName() . '::CustomerUUID'} = $oCustomer->UUID;
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
				$this->Date => ['%'.$Search.'%', 'LIKE']
			];

			if (is_array($aSearchProducts) && count($aSearchProducts) > 0)
			{
				$aSearchFilters[$this->GetName() . '::ProductUUID'] = [array_keys($aSearchLicenses), 'IN'];
			}
			if (is_array($aSearchCustomers) && count($aSearchCustomers) > 0)
			{
				$aSearchFilters[$this->GetName() . '::CustomerUUID'] = [array_keys($aSearchCustomers), 'IN'];
			}

			$aSearchFilters = ['$OR' => $aSearchFilters];
		}
		$iSalesCount = $this->oApiSalesManager->getSalesCount($aSearchFilters);
		$aSales = $this->oApiSalesManager->getSales($Limit, $Offset, $aSearchFilters);

		foreach ($aSales as $oSale)
		{
			$aCustomersUUID[] = $oSale->{$this->GetName() . '::CustomerUUID'};
		}
		$aCustomers = $this->oApiCustomersManager->getCustomers(\array_unique($aCustomersUUID));
		$aProducts = $this->oApiProductsManager->getProducts(0, 0);

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

	public function UpdateProduct($ProductId, $Name, $ProductCode = null, $ShareItProductId = null, $PayPalItem = null, $ProductPrice = null)
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
		if (isset($PayPalItem))
		{
			$oProduct->{$this->GetName() . '::PayPalItem'} = $PayPalItem;
		}
		if (isset($ProductPrice))
		{
			$oProduct->Price = $ProductPrice;
		}
		return $this->oApiProductsManager->UpdateProduct($oProduct);
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

		$oSale = $this->oApiSalesManager->getSaleById((int) $SaleId);
		if ($oSale instanceof \Aurora\Modules\SaleObjects\Classes\Sale)
		{
			$oSale->{$this->GetName() . '::ProductUUID'} = isset($ProductUUID) ? $ProductUUID : $oSale->{$this->GetName() . '::ProductUUID'};
			$oSale->Date = isset($Date) ? $Date : $oSale->Date;
			$oSale->{$this->GetName() . '::VatId'} = isset($VatId) ? $VatId : $oSale->{$this->GetName() . '::VatId'};
			$oSale->{$this->GetName() . '::Payment'} = isset($Payment) ? $Payment : $oSale->{$this->GetName() . '::Payment'};
			$oSale->{$this->GetName() . '::CustomerUUID'} = isset($CustomerUUID) ? $CustomerUUID : $oSale->{$this->GetName() . '::CustomerUUID'};
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
}