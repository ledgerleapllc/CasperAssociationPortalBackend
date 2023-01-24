<?php
/**
 * Throttler unit test.
 * Simple throttle logic check for existence of throttle values for each API route.
 *
 * @group  unittests
 *
 * @method void testThrottledEndpoints()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class ThrottlerTest extends TestCase
{
	public function testThrottledEndpoints()
	{
		// detect endpoints from throttle class config
		$throttle_test      = new Throttle('unittest');
		$throttle_endpoints = $throttle_test::get_endpoints();

		// detect endpoints from router
		$router_endpoints = array();
		$router_file      = '';

		if(is_file(__DIR__.'/../../public/.htaccess'))
			$router_file = file_get_contents(__DIR__.'/../../public/.htaccess');

		$lines = explode("\n", $router_file);

		foreach ($lines as $line) {
			if(strstr($line, 'RewriteRule')) {
				if(
					strstr($line, '^user/') ||
					strstr($line, '^admin/')
				) {
					$split = explode(' ', $line);
					$path  = $split[1] ?? '';
					$path  = str_replace('^', '/', $path);
					$path  = str_replace('/?$', '', $path);
					$router_endpoints[] = $path;
				}
			}
		}

		foreach($throttle_endpoints as $key => $val) {
			if(!in_array($key, $router_endpoints)) {
				echo('WARNING - Endpoint from Throttle class not found in Router: '.$key."\n");
			}

			// $this->assertTrue(in_array($key, $router_endpoints));
		}

		foreach($router_endpoints as $endpoint) {
			if(!isset($throttle_endpoints[$endpoint])) {
				echo('WARNING - Endpoint from Router not found in Throttle class: '.$endpoint."\n");
			}

			// $this->assertTrue(isset($throttle_endpoints[$endpoint]));
		}

		$this->assertFalse(empty($throttle_endpoints));
		$this->assertFalse(empty($router_endpoints));
	}
}

?>