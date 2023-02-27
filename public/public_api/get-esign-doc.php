<?php
/**
 *
 * GET /public/get-esign-doc
 *
 * @api
 *
 */
class PublicGetEsignDoc extends Endpoints {
	function __construct() {
		global $helper;

		require_method('GET');

		$url  = $helper->fetch_setting('esign_doc');
		$name = explode('/', $url);
		$name = end($name);
		$ext  = explode('.', $name);
		$ext  = strtolower($ext[1] ?? '');

		_exit(
			'success',
			array(
				'url'  => $url,
				'name' => $name,
				'ext'  => $ext
			)
		);
	}
}
new PublicGetEsignDoc();
