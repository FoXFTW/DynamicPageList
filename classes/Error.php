<?php
/**
 * DynamicPageList3
 * DPL ErrorCodes Class
 *
 * @author FoXFTW
 * @license GPL
 * @package DynamicPageList3
 *
 **/

namespace DPL;

abstract class Error {
	/**
	 * User tried to include more categories than 'maxCategoryCount'
	 */
	const CRITICAL_TOO_MANY_CATEGORIES = 3;

	/**
	 * Given category count is less than 'minCategoryCount'
	 */
	const CRITICAL_TOO_FEW_CATEGORIES = 4;

	/**
	 * There was either no user input or no categories / selection criteria were found
	 */
	const CRITICAL_NO_SELECTION = 5;

	/**
	 * No categories were found
	 * ordermethod='categoryadd'
	 */
	const CRITICAL_NO_CATEGORIES_FOR_ORDER_METHOD = 6;

	/**
	 * Article add Date can't be added because no categories were found
	 * Param: addfirstcategorydate
	 */
	const CRITICAL_NO_CATEGORIES_FOR_ADD_DATE = 7;

	/**
	 * At least two of the following parameters were used:
	 * 'addpagetoucheddate', 'addfirstcategorydate', 'addeditdate'
	 */
	const CRITICAL_MORE_THAN_ONE_TYPE_OF_DATE = 8;

	/**
	 * The provided order method is not valid for the set mode
	 */
	const CRITICAL_WRONG_ORDER_METHOD = 9;

	/**
	 * Dominant section was not included in 'includepage'
	 */
	const CRITICAL_DOMINANT_SECTION_RANGE = 10;

	/**
	 * The Database view with 'cl_to=""' is missing
	 * The View has to be created on the 'categorylinks' table
	 * This is used is uncategorized pages shall be included
	 */
	const CRITICAL_NO_CL_VIEW = 11;

	/**
	 * Current option(s) are incompatible with 'openreferences'
	 */
	const CRITICAL_OPEN_REFERENCES = 12;

	/**
	 * DPL shall only run on protected pages. But has been called on a non protected page
	 */
	const CRITICAL_NOT_PROTECTED = 23;

	/**
	 * The generated SQL has caused an error / is invalid
	 */
	const CRITICAL_SQL_BUILD_ERROR = 24;


	/**
	 * The provided Parameter does not exist
	 */
	const WARN_UNKNOWN_PARAM = 13;

	/**
	 * Parameter value is missing for the given parameter
	 */
	const WARN_PARAM_NO_OPTION = 22;

	/**
	 * The provided parameter value is invalid
	 */
	const WARN_WRONG_PARAM = 14;

	/**
	 * The created query had no results
	 */
	const WARN_NO_RESULTS = 16;

	/**
	 * Mode was set to 'category' and at least one 'add*' parameter was used
	 * Only namespace and/or title can be viewed in 'category' mode
	 */
	const WARN_CAT_OUTPUT_BUT_WRONG_PARAMS = 17;

	/**
	 * At least two order methods need to be used with 'headingmode' true
	 */
	const WARN_HEADING_MODE_TOO_FEW_ORDER_METHODS = 18;

	/**
	 * A infinite transclusion loop was identified
	 */
	const WARN_TRANSCLUSION_LOOP = 20;
}
