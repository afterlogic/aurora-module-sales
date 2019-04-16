<?php
/**
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
class ParsingStatus extends \Aurora\System\Enums\AbstractEnumeration
{
	const Unknown = 0;
	const NotParsed = 1;
	const ParsedSuccessfully = 2;
	const ParsedWithWarning = 3;

	/**
	 * @var array
	 */
	protected $aConsts = [
		'Unknown'				=> self::Unknown,
		'NotParsed'				=> self::NotParsed,
		'ParsedSuccessfully'	=> self::ParsedSuccessfully,
		'ParsedWithWarning'		=> self::ParsedWithWarning,
	];
}
