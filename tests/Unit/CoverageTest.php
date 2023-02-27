<?php
/**
 * Unit/Integration test coverage test.
 * Indicates how much is actually tested out of all available endpoints.
 *
 * @group  unittests
 *
 * @method void testTestCoverage()
 *
 */
use PHPUnit\Framework\TestCase;

include_once(__DIR__.'/../../core.php');

final class CoverageTest extends TestCase
{
	public static $verbose         = true;
	public static $endpoint_groups = array(
		'user',
		'admin',
		'public'
	);

	public function testTestCoverage()
	{
		global $helper;

		elog("Coverage analysis:\n");

		foreach (self::$endpoint_groups as $group) {
			elog(ucfirst($group).' endpoints:');

			// compile list of all available endpoint names
			$total     = array();
			$endpoints = Helper::get_dir_contents(
				__DIR__.'/../../public/'.$group.'_api/',
				__DIR__.'/../../public/'.$group.'_api/'
			);

			foreach ($endpoints as $endpoint) {
				$split = explode('/', $endpoint);
				$name  = end($split);
				$name  = str_replace('.php', '', $name);

				if ($name != 'index') {
					$total[$name] = false;
				}
			}

			// compile list of test methods
			$tester = file_get_contents(__DIR__.'/../Integration/'.ucfirst($group).'EndpointsTest.php');
			$lines  = explode("\n", $tester);

			// verify method declaration count
			$declarations = substr_count($tester, '@method void');
			$functions    = substr_count($tester, 'public function');

			if ($declarations != $functions) {
				elog(ucfirst($group)." tests declared does not equal the actual test function count");
			}

			$this->assertEquals($declarations, $functions);

			foreach ($lines as $line) {
				if (strstr($line, '@method void')) {
					$method = explode(" ", $line);
					$method = $method[4] ?? '';
					$method = str_replace('()', '', $method);
					$method = substr($method, 4, strlen($method));
					$method = Helper::kebab_case($method);
					// elog($method);

					if (array_key_exists($method, $total)) {
						$total[$method] = true;
					}
				}
			}

			// calculate test coverage percentage
			$tested  = 0;
			$lstring = 0;

			if (self::$verbose) {
				foreach ($total as $key => $val) {
					if (strlen($key) > $lstring) {
						$lstring = strlen($key);
					}
				}
			}

			foreach ($total as $key => $val) {
				if (self::$verbose) {
					$buffer = str_repeat('.', $lstring - strlen($key) + 1);
				}

				if ($val == true) {
					$tested += 1;

					if (self::$verbose) {
						elog($key.$buffer.' COMPLETE');
					}
				} else {
					if (self::$verbose) {
						elog($key.$buffer.' missing');
					}
				}
			}

			$perc = round($tested / count($total) * 100, 2);
			elog($tested.' out of '.count($total).' endpoints tested.');
			elog('Coverage: '.$perc.'%');
			$this->assertGreaterThan(80, $perc);
		}

	}
}
