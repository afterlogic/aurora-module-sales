<?php
require_once "../../system/autoload.php";
\Aurora\System\Api::Init(true);

$oSalesModule = \Aurora\System\Api::GetModule('Sales');
ob_start();
switch (filter_input(INPUT_GET, 'mode', FILTER_SANITIZE_SPECIAL_CHARS))
{
	case 'groups':
		$productsResult = $oSalesModule->GetProducts();
		$groupsResult = $oSalesModule->GetProductGroups();
		
		if (isset($productsResult['ItemsCount']) && $productsResult['ItemsCount'] === 0 && isset($groupsResult['ItemsCount']) && $groupsResult['ItemsCount'] === 0 ) 
		{
			$result = $oSalesModule->CreateGroups();
			echo $result;
		}
		break;
	case 'paypal':
		echo "Start import process for Paypal";
		ob_flush();
		flush();
		$oSalesModule->ImportPaypalSales();
		break;
	case 'sales':
		echo "Start import process for Sales";
		ob_flush();
		flush();
		$oSalesModule->ImportSales();
		break;
	default:
		echo "Unknown mode";
		break;
}
ob_end_flush();
exit("<br>Done");
