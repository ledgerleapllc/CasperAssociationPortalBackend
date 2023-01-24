<?php
include_once('../../core.php');
/**
 *
 * PUT /admin/update-setting
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $setting_name
 * @param string $setting_value
 *
 */
class AdminUpdateSetting extends Endpoints {
	function __construct(
		$setting_name = '',
		$setting_value = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth          = authenticate_session(2);
		$admin_guid    = $auth['guid'] ?? '';
		$setting_name  = parent::$params['setting_name'] ?? '';
		$setting_value = parent::$params['setting_value'] ?? '';

		$success = $helper->apply_setting($setting_name, $setting_value);

		if (!$success) {
			_exit(
				'error',
				'There was a problem updating administrative setting '.$setting_name.' at this time',
				400,
				'There was a problem updating administrative setting '.$setting_name.' at this time',
			);
		}

		_exit(
			'success',
			'Successfully updated admin setting'
		);
	}
}
new AdminUpdateSetting();