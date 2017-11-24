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
	public $oApiLicensesManager = null;

	/**
	 * Initializes Sales Module.
	 *
	 * @ignore
	 */

	public function init()
	{
		$this->oApiSalesManager = new Managers\Sales\Manager($this);
		$this->oApiCustomersManager = new Managers\Customers\Manager($this);
		$this->oApiLicensesManager = new Managers\Licenses\Manager($this);

		$this->extendObject(
			'Aurora\Modules\SaleObjects\Classes\Sale',
			array(
				'LicenseUUID' => array('string', ''),
				'Date' => array('datetime', date('Y-m-d H:i:s', 0)),
				'VatId' => array('string', ''),
				'Payment' => array('string', ''),
				'CustomerUUID' => array('string', ''),
				'LicenseKey' => array('string', ''),
				'RefNumber' => array('int', 0),
				'NetTotal' => array('int', 0),
				'ShareItPurchaseId' => array('int', 0),
				'IsNotified' => array('bool', false),
				'MaintenanceExpirationDate' => array('datetime', date('Y-m-d H:i:s', 0)),
				'RecurrentMaintenance' => array('bool', true),
				'TwoMonthsEmailSent' => array('bool', false),
				'ParentSaleId' => array('int', 0),
				'PaymentSystem' => array('int', 0),
				'NumberOfLicenses' => array('int', 0),
				'RawData' => array('text', ''),
				'RawDataType' => array('int', 0)
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
			'Aurora\Modules\SaleObjects\Classes\License',
			array(
				'LicenseCode' => array('int', 0),
				'LicenseName' => array('string', ''),
				'ShareItLicenseId' => array('int', 0),
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
	 * @param int $NetTotal Payment amount.
	 * @param string $Email Email.
	 * @param string $RegName Full name.
	 * @param string $LicenseName License name.
	 * @param string $LicenseCode License code.
	 * 
	 * @return \Aurora\Modules\SaleObjects\Classes\Sale|boolean
	 */
	public function CreateSale($Payment, $PaymentSystem, $NetTotal,
		$Email, $RegName,
		$LicenseName, $LicenseCode = null, $MaintenanceExpirationDate = null,
		$TransactionId = '',
		$Date = null, $LicenseKey ='', $RefNumber = 0, $ShareItLicenseId = 0, $ShareItPurchaseId = 0, $IsNotified = false, $RecurrentMaintenance = true, $TwoMonthsEmailSent = false, $ParentSaleId = 0, $VatId = '',
		$Salutation = '', $Title = '', $FirstName = '', $LastName = '', $Company = '', $Street = '', $Zip = '', $City = '', $FullCity = '', $Country = '', $State = '', $Phone = '', $Fax = '', $Language = '', $NumberOfLicenses = 0,
		$PayPalItem = '', $RawData = '', $RawDataType = 0
	)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		if ($ShareItLicenseId > 0)
		{
			$oLicense = $this->oApiLicensesManager->getLicenseByShareItLicenseId($ShareItLicenseId);
		}
		else
		{
			$oLicense = null;
		}

		if (!$oLicense instanceof \Aurora\Modules\SaleObjects\Classes\License && $PaymentSystem !== \Aurora\Modules\Sales\Enums\PaymentSystem::PayPal)
		{
			$oLicense = new \Aurora\Modules\SaleObjects\Classes\License($this->GetName());
			$oLicense->{$this->GetName() . '::LicenseName'} = $LicenseName;
			$oLicense->{$this->GetName() . '::LicenseCode'} = $LicenseCode;
			$oLicense->{$this->GetName() . '::ShareItLicenseId'} = $ShareItLicenseId;
			$oLicense->{$this->GetName() . '::IsAutocreated'} = true;
			$bLicenseResult = $this->oApiLicensesManager->createLicense($oLicense);
			if ($bLicenseResult)
			{
				$oLicense = $this->oApiLicensesManager->getLicenseByCode($LicenseCode);
			}
			if (!$oLicense instanceof \Aurora\Modules\SaleObjects\Classes\License)
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
		$oSale->{$this->GetName() . '::LicenseUUID'} = isset($oLicense->UUID) ? $oLicense->UUID : '';
		$oSale->{$this->GetName() . '::CustomerUUID'} = $oCustomer->UUID;
		$oSale->{$this->GetName() . '::Payment'} = $Payment;
		$oSale->{$this->GetName() . '::LicenseKey'} = $LicenseKey;
		$oSale->{$this->GetName() . '::NetTotal'} = $NetTotal;
		$oSale->{$this->GetName() . '::RefNumber'} = $RefNumber;
		$oSale->{$this->GetName() . '::ShareItPurchaseId'} = $ShareItPurchaseId;
		$oSale->{$this->GetName() . '::IsNotified'} = $IsNotified;
		$oSale->{$this->GetName() . '::RecurrentMaintenance'} = $RecurrentMaintenance;
		$oSale->{$this->GetName() . '::TwoMonthsEmailSent'} = $TwoMonthsEmailSent;
		$oSale->{$this->GetName() . '::ParentSaleId'} = $ParentSaleId;
		$oSale->{$this->GetName() . '::VatId'} = $VatId;
		$oSale->{$this->GetName() . '::PaymentSystem'} = $PaymentSystem;
		$oSale->{$this->GetName() . '::TransactionId'} = $TransactionId;
		$oSale->{$this->GetName() . '::NumberOfLicenses'} = $NumberOfLicenses;
		$oSale->{$this->GetName() . '::RawData'} = $RawData;
		$oSale->{$this->GetName() . '::RawDataType'} = $RawDataType;
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
		$aCustomersUUID = [];
		$aSearchFilters = [];
		$aLicenseSearchFilters = [];
		if (!empty($Search))
		{
			$aLicenseSearchFilters = [
				$this->GetName() . '::LicenseName' => ['%'.$Search.'%', 'LIKE']
			];
		}
		$aSearchLicenses = $this->oApiLicensesManager->getLicenses(0, 0, $aLicenseSearchFilters, [
				$this->GetName() . '::LicenseCode',
				$this->GetName() . '::LicenseName',
				$this->GetName() . '::ShareItLicenseId',
			]);
		if (!empty($Search))
		{
			$aSearchCustomers = $this->oApiCustomersManager->searchCustomers($Search, [$this->GetName() . '::Email']);

			$aSearchFilters = [
				$this->GetName() . '::LicenseKey' => ['%'.$Search.'%', 'LIKE'],
				$this->GetName() . '::Date' => ['%'.$Search.'%', 'LIKE']
			];

			if (is_array($aSearchLicenses) && count($aSearchLicenses) > 0)
			{
				$aSearchFilters[$this->GetName() . '::LicenseUUID'] = [array_keys($aSearchLicenses), 'IN'];
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
		$aLicenses = $this->oApiLicensesManager->getLicenses(0, 0);

		return [
			'ItemsCount' => $iSalesCount,
			'Sales' => is_array($aSales) ? array_reverse($aSales) : [],
			'Customers' => is_array($aCustomers) ? $aCustomers : [],
			'Licenses' => is_array($aLicenses) ? $aLicenses : []
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
	public function GetLicenses($Limit = 0, $Offset = 0, $Search = "")
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aSearchFilters = [];
		if (!empty($Search))
		{
			$aSearchFilters = [
				$this->GetName() . '::LicenseName' => ['%'.$Search.'%', 'LIKE']
			];
		}
		$aLicenses = $this->oApiLicensesManager->getLicenses($Limit, $Offset, $aSearchFilters);
		return [
			'Licenses' => is_array($aLicenses) ? $aLicenses : [],
			'ItemsCount' => $this->oApiLicensesManager->getLicensesCount($aSearchFilters)
		];
	}

	public function UpdateLicense($LicenseId, $Name, $LicenseCode = null, $ShareItLicenseId = null, $PayPalItem = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oLicense = $this->oApiLicensesManager->getLicenseById((int) $LicenseId);
		$oLicense->{$this->GetName() . '::LicenseName'} = $Name;
		if (isset($LicenseCode))
		{
			$oLicense->{$this->GetName() . '::LicenseCode'} = $LicenseCode;
		}
		if (isset($ShareItLicenseId))
		{
			$oLicense->{$this->GetName() . '::ShareItLicenseId'} = $ShareItLicenseId;
		}
		if (isset($PayPalItem))
		{
			$oLicense->{$this->GetName() . '::PayPalItem'} = $PayPalItem;
		}
		return $this->oApiLicensesManager->UpdateLicense($oLicense);
	}

	public function UpdateSale($SaleId,
		$LicenseUUID = null,
		$Date = null,
		$VatId = null,
		$Payment = null,
		$CustomerUUID = null,
		$LicenseKey = null,
		$RefNumber = null,
		$ShareItLicenseId = null,
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
			$oSale->{$this->GetName() . '::LicenseUUID'} = isset($LicenseUUID) ? $LicenseUUID : $oSale->{$this->GetName() . '::LicenseUUID'};
			$oSale->{$this->GetName() . '::Date'} = isset($Date) ? $Date : $oSale->{$this->GetName() . '::Date'};
			$oSale->{$this->GetName() . '::VatId'} = isset($VatId) ? $VatId : $oSale->{$this->GetName() . '::VatId'};
			$oSale->{$this->GetName() . '::Payment'} = isset($Payment) ? $Payment : $oSale->{$this->GetName() . '::Payment'};
			$oSale->{$this->GetName() . '::CustomerUUID'} = isset($CustomerUUID) ? $CustomerUUID : $oSale->{$this->GetName() . '::CustomerUUID'};
			$oSale->{$this->GetName() . '::LicenseKey'} = isset($LicenseKey) ? $LicenseKey : $oSale->{$this->GetName() . '::LicenseKey'};
			$oSale->{$this->GetName() . '::RefNumber'} = isset($RefNumber) ? $RefNumber : $oSale->{$this->GetName() . '::RefNumber'};
			$oSale->{$this->GetName() . '::ShareItLicenseId'} = isset($ShareItLicenseId) ? $ShareItLicenseId : $oSale->{$this->GetName() . '::ShareItLicenseId'};
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