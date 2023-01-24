<?php
include_once(__DIR__.'/../../core.php');

global $db, $helper;

$cmc_key = getenv('COINMARKETCAP_KEY');

//// rm
$cmc_key = '';

if ($cmc_key) {
	$url = 'https://pro-api.coinmarketcap.com/v1/tools/price-conversion';

	$headers = array(
		'X-CMC_PRO_API_KEY: '.$cmc_key
	);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url.'?amount=1&symbol=CSPR&convert=USD');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		elog('Coin Market Cap CURL error: '.curl_error($ch));
	}

	try {
		$json = json_decode($response);
	} catch (Exception $e) {
		elog('Coin Market Cap JSON error: ');
		elog($e);
		$json = array();
	}

	curl_close($ch);

	$price = (float)$json->data->quote->USD->price ?? null;

	if ($price) {
		$price = round($price, 5);
		$now   = $helper->get_datetime();
		$past  = $helper->get_datetime(-2592000); // one month ago

		$db->do_query("
			INSERT INTO token_price (
				price,
				created_at,
				updated_at
			) VALUES (
				$price,
				'$now',
				'$now'
			)
		");

		// cleanup
		$db->do_query("
			DELETE FROM token_price
			WHERE created_at < '$past'
		");
	}
}
