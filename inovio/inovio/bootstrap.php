<?php

/**
 * Use to load autoloader file
 */
$srcPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'inoviocore' . DIRECTORY_SEPARATOR;
if (!file_exists($srcPath . 'InovioAutoLoader.php'))
{
	throw new Exception('Invalid Configuration, Autoload file missing.');
}
require_once $srcPath . 'InovioAutoLoader.php';
