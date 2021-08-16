<?php

namespace App\Console;

use Illuminate\Support\Facades\Http;

class Helper {
	// Get Token Price
	public static function getTokenPrice() {
		$url = 'https://pro-api.coinmarketcap.com/v1/tools/price-conversion';

		$apiKey = config('services.token_price.api_key');
		$response = Http::withHeaders([
			'X-CMC_PRO_API_KEY' => $apiKey
		])->get($url, [
			'amount' => 1,
			'symbol' => 'CSPR',
			'convert' => 'USD'
		]);

		return $response->json();
	}
}
