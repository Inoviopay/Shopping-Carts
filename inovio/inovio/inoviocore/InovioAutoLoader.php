<?php

/**
 * Use to load all src folder's files
 */
if (function_exists('__autoload'))
{
	trigger_error("InovioPayment: It looks like your code is using an __autoload() function."
	    . " Inovio uses spl_autoload_register() which will bypass your __autoload()"
	    . " function and may break autoloading.", E_USER_WARNING);
}

spl_autoload_register(array('InovioAutoLoader', 'autoload'));

/**
 * Class InovioAutoLoader
 *
 * InovioAutoLoader include required classes on the fly.
 *
 * @package Inovio
 */
class InovioAutoLoader
{

	/**
	 * $classes class listing for Inovio SDK
	 * @var array
	 */
	private static $classes = [
	  'InovioServiceConfig' => '/InovioServiceConfig.php',
	  'InovioConnection' => '/InovioConnection.php',
	  'InovioException' => '/InovioException.php',
	  'InovioXmlParser' => '/InovioXmlParser.php',
	  'InovioProcessor' => '/InovioProcessor.php',
	  'Logger' => '/php4log/Logger.php'
	];

	public static function autoload($className)
	{
		if (isset(self::$classes[$className]))
		{
			$fullClassName = str_replace('/', DIRECTORY_SEPARATOR, self::$classes[$className]);
			include_once dirname(__FILE__) . $fullClassName;
		}
	}

}
