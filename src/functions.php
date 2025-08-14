<?php

require_once (__DIR__ . '/../config/base_config.php'); // load base config with paths to classes etc.

function load_model($class_name)
{
	global $baseClassModelDir;
	$path_to_file = $baseClassModelDir. $class_name . '.php';
	$allowed_include = 1; // set allow to 1 in order to include the user.php script
	if (file_exists($path_to_file)) {
		require $path_to_file;
	}
}

spl_autoload_register('load_model');
?>
