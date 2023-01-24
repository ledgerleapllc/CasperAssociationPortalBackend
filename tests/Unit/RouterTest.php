<?php
/**
 * Router integrity check unit test.
 * Checks for valid router paths not found in directory structure,
 * and valid endpoint paths not found in router.
 *
 * @group  unittests
 *
 * @method void testDirectoryRoutes()
 * @method void testRouterRules()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class RouterTest extends TestCase
{
	public function testDirectoryRoutes()
	{
		/* scan folder structure first */
		$this->dir_user_scan   = array();
		$this->dir_admin_scan  = array();
		$this->dir_public_scan = array();

		$dir = __DIR__.'/../../public/user_api';

		if(is_dir($dir)) {
			$this->dir_user_scan   = scandir($dir);
		}

		$dir = __DIR__.'/../../public/admin_api';

		if(is_dir($dir)) {
			$this->dir_admin_scan  = scandir($dir);
		}

		$dir = __DIR__.'/../../public/public_api';

		if(is_dir($dir)) {
			$this->dir_public_scan = scandir($dir);
		}

		for($i = 0; $i < count($this->dir_user_scan); $i++) {
			if(
				$this->dir_user_scan[$i] == '.' ||
				$this->dir_user_scan[$i] == '..' ||
				$this->dir_user_scan[$i] == 'index.php'
			) {
				unset($this->dir_user_scan[$i]);
			}
		}

		for($i = 0; $i < count($this->dir_admin_scan); $i++) {
			if(
				$this->dir_admin_scan[$i] == '.' ||
				$this->dir_admin_scan[$i] == '..' ||
				$this->dir_admin_scan[$i] == 'index.php'
			) {
				unset($this->dir_admin_scan[$i]);
			}
		}

		for($i = 0; $i < count($this->dir_public_scan); $i++) {
			if(
				$this->dir_public_scan[$i] == '.' ||
				$this->dir_public_scan[$i] == '..' ||
				$this->dir_public_scan[$i] == 'index.php'
			) {
				unset($this->dir_public_scan[$i]);
			}
		}

		/* scan router rules */
		$this->router_user_scan   = array();
		$this->router_admin_scan  = array();
		$this->router_public_scan = array();

		$router_file = '';

		if(is_file(__DIR__.'/../../public/.htaccess')) {
			$router_file = file_get_contents(__DIR__.'/../../public/.htaccess');
		}

		$lines = explode("\n", $router_file);

		foreach ($lines as $line) {
			if(strstr($line, 'RewriteRule')) {
				$s          = explode(' ', $line);
				$route      = $s[1] ?? '';
				$path       = $s[2] ?? '';
				$path_split = explode('/', $path);
				$type       = $path_split[1] ?? '';
				$name       = $path_split[count($path_split) - 1] ?? '';
				$name       = explode('?', $name)[0];

				if($type == 'user_api') {
					$this->router_user_scan[]   = $name;
				}

				if($type == 'admin_api') {
					$this->router_admin_scan[]  = $name;
				}

				if($type == 'public_api') {
					$this->router_public_scan[] = $name;
				}
			}
		}

		foreach ($this->router_user_scan as $name) {
			$in_array = in_array($name, $this->dir_user_scan);

			if(!$in_array) {
				echo "\nMISSING  ".$name." from folder structure";
			}

			$this->assertTrue($in_array);
		}

		foreach ($this->router_admin_scan as $name) {
			$in_array = in_array($name, $this->dir_admin_scan);

			if(!$in_array) {
				echo "\nMISSING  ".$name." from folder structure";
			}

			$this->assertTrue($in_array);
		}

		foreach ($this->router_public_scan as $name) {
			$in_array = in_array($name, $this->dir_public_scan);

			if(!$in_array) {
				echo "\nMISSING  ".$name." from folder structure";
			}

			$this->assertTrue($in_array);
		}
	}

	public function testRouterRules()
	{
		/* scan folder structure first */
		$this->dir_user_scan   = array();
		$this->dir_admin_scan  = array();
		$this->dir_public_scan = array();

		if(is_dir(__DIR__.'/../../public/user_api')) {
			$this->dir_user_scan   = scandir(__DIR__.'/../../public/user_api');
		}

		if(is_dir(__DIR__.'/../../public/admin_api')) {
			$this->dir_admin_scan  = scandir(__DIR__.'/../../public/admin_api');
		}

		if(is_dir(__DIR__.'/../../public/public_api')) {
			$this->dir_public_scan = scandir(__DIR__.'/../../public/public_api');
		}

		foreach ($this->dir_user_scan as $key => $val) {
			if(
				$val == '.' ||
				$val == '..' ||
				$val == 'index.php'
			) {
				unset($this->dir_user_scan[$key]);
			}
		}

		foreach ($this->dir_admin_scan as $key => $val) {
			if(
				$val == '.' ||
				$val == '..' ||
				$val == 'index.php'
			) {
				unset($this->dir_admin_scan[$key]);
			}
		}

		foreach ($this->dir_public_scan as $key => $val) {
			if(
				$val == '.' ||
				$val == '..' ||
				$val == 'index.php'
			) {
				unset($this->dir_public_scan[$key]);
			}
		}

		/* scan router rules */
		$this->router_user_scan   = array();
		$this->router_admin_scan  = array();
		$this->router_public_scan = array();

		$router_file = '';

		if(is_file(__DIR__.'/../../public/.htaccess')) {
			$router_file = file_get_contents(__DIR__.'/../../public/.htaccess');
		}

		$lines = explode("\n", $router_file);

		foreach ($lines as $line) {
			if(strstr($line, 'RewriteRule')) {
				$s          = explode(' ', $line);
				$route      = $s[1] ?? '';
				$path       = $s[2] ?? '';
				$path_split = explode('/', $path);
				$type       = $path_split[1] ?? '';
				$name       = $path_split[count($path_split) - 1] ?? '';
				$name       = explode('?', $name)[0];

				if($type == 'user_api') {
					$this->router_user_scan[]   = $name;
				}

				if($type == 'admin_api') {
					$this->router_admin_scan[]  = $name;
				}

				if($type == 'public_api') {
					$this->router_public_scan[] = $name;
				}
			}
		}

		foreach ($this->dir_user_scan as $file) {
			$in_array = in_array($file, $this->router_user_scan);

			if(!$in_array) {
				echo "\nMISSING  ".$file." from htaccess user router";
			}

			$this->assertTrue($in_array);
		}

		foreach ($this->dir_admin_scan as $file) {
			$in_array = in_array($file, $this->router_admin_scan);

			if(!$in_array) {
				echo "\nMISSING  ".$file." from htaccess admin router";
			}

			$this->assertTrue($in_array);
		}

		foreach ($this->dir_public_scan as $file) {
			$in_array = in_array($file, $this->router_public_scan);

			if(!$in_array) {
				echo "\nMISSING  ".$file." from htaccess public router";
			}

			$this->assertTrue($in_array);
		}
	}
}

?>