<?php
/**
 *
 * Master cron controller
 * One cron to rule them all - intended to run every minute
 *
 * Example async command that locks and logs during execution:
 *
 * /usr/bin/flock -w 0 BASE_DIR/crontab/locks/schedule.lock php BASE_DIR/crontab/crons/node-info 2>&1 | tee -a BASE_DIR/crontab/cron.log > /dev/null &
 *
 */
class Cron {
	/**
	 *
	 * interval described in minutes.
	 * 60 minutes = every hour
	 * 1440 minutes = once a day
	 *
	 */

	// default definition
	public $crons = array(
		array(
			"name"     => "node-info",
			"interval" => 15
		),
		array(
			"name"     => "schedule",
			"interval" => 1
		),
		array(
			"name"     => "garbage",
			"interval" => 15
		),
		array(
			"name"     => "ballots",
			"interval" => 1
		),
		array(
			"name"     => "members",
			"interval" => 1
		),
		array(
			"name"     => "protocol-upgrades",
			"interval" => 5
		),
		array(
			"name"     => "token-price",
			"interval" => 30
		)
	);

	function __construct($cron_array = array()) {
		if (
			gettype($cron_array) == 'array' &&
			!empty($cron_array)
		) {
			$this->crons = $cron_array;
		}
	}

	function __destruct() {
		// do nothing yet
	}

	/**
	 *
	 * Runs all crons defined in $this->crons
	 *
	 * @param  string $target_cron Specify a cron to trigger only that job
	 * @return bool
	 *
	 */
	public function run_crons(
		string $target_cron = ''
	) {
		global $helper;

		if ($target_cron) {
			foreach ($this->crons as $cron) {
				$name = $cron['name'] ?? '';

				if ($name == $target_cron) {
					// found target cron

					if (!DOCKER_BUILD) {
						// verify lock file first
						$exists = file_exists(BASE_DIR."/crontab/locks/$name.lock");

						if (!$exists) {
							file_put_contents(
								BASE_DIR."/crontab/locks/$name.lock",
								''
							);
						}
					}

					self::run_cron($name);
					return true;
				}
			}

			cronlog("Cron->$target_cron not found");
			elog("Cron->$target_cron not found");
			return false;
		}

		$time   = explode(' ', $helper->get_datetime());
		$time   = $time[1] ?? '';
		$split  = explode(':', $time);
		$hour   = $split[0] ?? '';
		$minute = $split[1] ?? '';
		$second = $split[2] ?? '';
		// elog($hour.' '.$minute.' '.$second);

		if (!$hour || !$minute || !$second) {
			cronlog('Cron controller broken - not running');
			elog('Cron controller broken - not running');
			return false;
		}

		foreach ($this->crons as $cron) {
			$name     = $cron['name'] ?? '';
			$interval = (int)($cron['interval'] ?? 0);

			if ($name) {
				// verify lock file first
				$exists = file_exists(BASE_DIR."/crontab/locks/$name.lock");

				if (!$exists) {
					file_put_contents(
						BASE_DIR."/crontab/locks/$name.lock",
						''
					);
				}

				// check interval
				if (
					$interval > 0 &&
					$interval <= 60
				) {
					if ((int)$minute % $interval == 0) {
						self::run_cron($name);
					}
				}

				if (
					$interval > 60 &&
					(int)$minute == 0
				) {
					$div = $interval / 60;

					if (
						gettype($div) == 'integer' &&
						(int)$hour % $div == 0
					) {
						self::run_cron($name);
					}
				}
			}
		}

		return true;
	}

	/**
	 *
	 * Runs particular cron
	 *
	 * @return bool
	 *
	 */
	private static function run_cron(
		string $name
	) {
		if (DOCKER_BUILD) {
			$command = "php ".BASE_DIR."/crontab/crons/$name.php 2>&1";
		}

		else {
			$command = "/usr/bin/flock -w 0 ".BASE_DIR."/crontab/locks/$name.lock php ".BASE_DIR."/crontab/crons/$name.php 2>&1 | tee -a ".BASE_DIR."/crontab/cron.log > /dev/null &";
		}

		cronlog("Running cron - $name");
		elog("Running cron - $name");
		$proc = shell_exec($command);
		// cronlog($proc);
	}
}
