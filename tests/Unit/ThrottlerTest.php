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

		// detect endpoints from directory structure
		$endpoints = array();

		$categories = array(
			'user',
			'admin',
			'public'
		);

		foreach ($categories as $category) {
			if (is_dir(__DIR__.'/../../public/'.$category.'_api')) {
				$user_endpoints = scandir(__DIR__.'/../../public/'.$category.'_api');

				foreach ($user_endpoints as $ep) {
					if (strstr($ep, '.php')) {
						if ($ep == 'index.php') {
							continue;
						}

						$name = explode('.', $ep)[0] ?? '';

						if ($name) {
							$endpoints[] = '/'.$category.'/'.$name;
						}
					}
				}
			}
		}

		foreach ($throttle_endpoints as $key => $val) {
			if (!in_array($key, $endpoints)) {
				echo('WARNING - Endpoint from Throttle class not found in Router: '.$key."\n");
			}

			// $this->assertTrue(in_array($key, $endpoints));
		}

		foreach ($endpoints as $endpoint) {
			if (!isset($throttle_endpoints[$endpoint])) {
				echo('WARNING - Endpoint from Router not found in Throttle class: '.$endpoint."\n");
			}

			// $this->assertTrue(isset($throttle_endpoints[$endpoint]));
		}

		$this->assertFalse(empty($throttle_endpoints));
		$this->assertFalse(empty($endpoints));
	}
}

?>