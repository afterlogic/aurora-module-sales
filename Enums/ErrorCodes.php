<?php
/*
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Enums;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class ErrorCodes
{
	const DataIntegrity				= 1001;
	const SaleCreateFailed			= 1002;
	const Validation_InvalidParameters	= 1003;
	const SaleUpdateFailed			= 1004;
	const ProductCreateFailed			= 1005;
	const ProductUpdateFailed			= 1006;
	const ProductGroupCreateFailed	= 1007;
	const ProductGroupUpdateFailed	= 1008;
	const CustomerCreateFailed		= 1009;
	const CustomerExists				= 1010;
	const ContactCreateFailed			= 1011;
	const ContactUpdateFailed			= 1012;
	const CompanyCreateFailed		= 1013;
	const CompanyUpdateFailed		= 1014;
	const MailchimpConnectionFailed	= 1015;

	/**
	 * @var array
	 */
	protected $aConsts = [
		'DataIntegrity'					=> self::DataIntegrity,
		'SaleCreateFailed'				=> self::SaleCreateFailed,
		'Validation_InvalidParameters'	=> self::Validation_InvalidParameters,
		'SaleUpdateFailed'				=> self::SaleUpdateFailed,
		'ProductCreateFailed'			=> self::ProductCreateFailed,
		'ProductUpdateFailed'			=> self::ProductUpdateFailed,
		'ProductGroupCreateFailed'		=> self::ProductGroupCreateFailed,
		'ProductGroupUpdateFailed'		=> self::ProductGroupUpdateFailed,
		'CustomerCreateFailed'			=> self::CustomerCreateFailed,
		'CustomerExists'				=> self::CustomerExists,
		'ContactCreateFailed'			=> self::ContactCreateFailed,
		'ContactUpdateFailed'			=> self::ContactUpdateFailed,
		'CompanyCreateFailed'			=> self::CompanyCreateFailed,
		'CompanyUpdateFailed'			=> self::CompanyUpdateFailed,
		'MailchimpConnectionFailed'		=> self::MailchimpConnectionFailed,
	];
}
