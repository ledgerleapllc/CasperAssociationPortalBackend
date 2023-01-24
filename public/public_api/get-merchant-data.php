<?php
include_once('../../core.php');
/**
 *
 * GET /public/get-merchant-data
 *
 * @api
 *
 */
class PublicGetMerchantData extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$merchant_name       = $helper->fetch_setting('merchant_name');
		$merchant_public_key = $helper->fetch_setting('merchant_public_key');
		$merchant_id         = $helper->fetch_setting('merchant_id');
		$badge_discount      = $helper->fetch_setting('badge_discount');
		$price               = $helper->fetch_setting('price');
		$price_id            = $helper->fetch_setting('price_id');
		$drip_account_id     = $helper->fetch_setting('drip_account_id');
		$drip_api_key        = $helper->fetch_setting('drip_api_key');

		_exit(
			'success',
			array(
				'merchant_name'       => $merchant_name,
				'merchant_public_key' => $merchant_public_key,
				'merchant_id'         => $merchant_id,
				'badge_discount'      => $badge_discount,
				'price'               => $price,
				'drip_account_id'     => $drip_account_id,
				'drip_api_key'        => $drip_api_key
			)
		);
	}
}
new PublicGetMerchantData();