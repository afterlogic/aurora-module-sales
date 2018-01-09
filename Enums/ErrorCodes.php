<?php
/*
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or Afterlogic Software License
 *
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Sales\Enums;

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
}
