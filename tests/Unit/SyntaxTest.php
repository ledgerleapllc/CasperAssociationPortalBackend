<?php
/**
 * Syntax check unit test. 
 * Starts at base DIR and iterates through all *.php files recursively.
 * Spawns child processes shell_exec -l syntax check on all files found.
 *
 * @group  unittests
 *
 * @method void testPhpSyntax()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class SyntaxTest extends TestCase
{
	public const base_folders = array(
		'public',
		'classes',
		'templates',
		'tests',
		'crontab'
	);

	public function testPhpSyntax()
	{
		global $helper;

		foreach (self::base_folders as $folder) {
			$list = Helper::get_dir_contents(__DIR__.'/../../', $folder);
			$err_list = array();

			foreach ($list as $item) {
				if (strstr($item, '.php')) {
					$result = shell_exec("php -l ".$item);

					if (strstr($result, 'Errors parsing')) {
						$err_list[] = trim($result);
					}
				}
			}

			foreach ($err_list as $e) {
				echo $e."\n";
			}

			if (empty($err_list)) {
				echo "Good syntax on ".$folder." PHP files\n";
			}

			$this->assertTrue(empty($err_list));
		}
	}
}