<?php
include_once('../../core.php');

/**
 *
 * POST /public/stripe-payment
 *
 * @api
 * @param string $guid  Frontend supplied GUID. Must pass backend format check
 * @param string $email
 * @param string $phone
 * @param string $name_on_card
 * @param string $first_name
 * @param string $last_name
 * @param string $company_name
 * @param string $line1
 * @param string $line2
 * @param string $city
 * @param string $state
 * @param string $zip
 * @param string $promo_code
 * @param string $card_number
 * @param string $exp_month
 * @param string $exp_year
 * @param string $cvv
 * @param string $source
 * @param bool   $drip
 *
 */
class PublicStripePayment extends Endpoints {
	function __construct(
		$guid         = '',
		$email        = '',
		$phone        = '',
		$name_on_card = '',
		$first_name   = '',
		$last_name    = '',
		$company_name = '',
		$line1        = '',
		$line2        = '',
		$city         = '',
		$state        = '',
		$zip          = '',
		$promo_code   = '',
		$card_number  = '',
		$exp_month    = '',
		$exp_year     = '',
		$cvv          = '',
		$source       = '',
		$drip         = false
	) {
		global $db, $helper;

		require_method('POST');

		$guid         = parent::$params['guid'] ?? '';
		$email        = parent::$params['email'] ?? null;
		$phone        = parent::$params['phone'] ?? null;
		$name_on_card = parent::$params['name_on_card'] ?? null;
		$first_name   = parent::$params['first_name'] ?? null;
		$last_name    = parent::$params['last_name'] ?? null;
		$company_name = parent::$params['company_name'] ?? null;
		$line1        = parent::$params['line1'] ?? null;
		$line2        = parent::$params['line2'] ?? null;
		$city         = parent::$params['city'] ?? null;
		$state        = parent::$params['state'] ?? null;
		$zip          = parent::$params['zip'] ?? null;
		$promo_code   = parent::$params['promo_code'] ?? null;
		$card_number  = parent::$params['card_number'] ?? null;
		$exp_month    = parent::$params['exp_month'] ?? 0;
		$exp_year     = parent::$params['exp_year'] ?? 0;
		$cvv          = parent::$params['cvv'] ?? null;
		$source       = parent::$params['source'] ?? 'casper-network';
		$drip         = (bool)(parent::$params['drip'] ?? false);

		// elog(parent::$params);

		if (!$line2) {
			$line2 = null;
		}

		if (!$promo_code) {
			$promo_code = null;
		}

		if (!$company_name) {
			$company_name = null;
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit('error', 'Invalid email address', 400, 'Invalid email address');
		}

		if ($phone) {
			$phone = preg_replace('/[^0-9- ()+]/', '', $phone);
		}

		$helper->sanitize_input(
			$name_on_card,
			true,
			2,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'Name on Card'
		);

		$helper->sanitize_input(
			$first_name,
			true,
			2,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'First Name'
		);

		$helper->sanitize_input(
			$last_name,
			true,
			2,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'Last Name'
		);

		$helper->sanitize_input(
			$company_name,
			false,
			2,
			Regex::$company_name['char_limit'],
			Regex::$company_name['pattern'],
			'Company Name'
		);

		$helper->sanitize_input(
			$line1,
			true,
			3,
			Regex::$address['char_limit'],
			Regex::$address['pattern'],
			'Address line 1'
		);

		$helper->sanitize_input(
			$line2,
			false,
			3,
			Regex::$address['char_limit'],
			Regex::$address['pattern'],
			'Address line 2'
		);

		$helper->sanitize_input(
			$city,
			true,
			2,
			Regex::$city['char_limit'],
			Regex::$city['pattern'],
			'City'
		);

		$helper->sanitize_input(
			$state,
			true,
			2,
			Regex::$state_or_province['char_limit'],
			Regex::$state_or_province['pattern'],
			'State or Province'
		);

		$helper->sanitize_input(
			$zip,
			true,
			2,
			Regex::$postal_code['char_limit'],
			Regex::$postal_code['pattern'],
			'Postal Code'
		);

		$helper->sanitize_input(
			$promo_code,
			false,
			2,
			Regex::$postal_code['char_limit'],
			Regex::$postal_code['pattern'],
			'Promo Code'
		);

		$helper->sanitize_input(
			$card_number,
			true,
			14,
			Regex::$credit_card_number['char_limit'],
			Regex::$credit_card_number['pattern'],
			'Card Number'
		);

		$card_number = str_replace(' ', '', $card_number);
		$exp_month   = (string)((int)$exp_month);
		$exp_year    = (string)((int)$exp_year);
		$cvv         = (string)((int)$cvv);
		$created_at  = $helper->get_datetime();


		// Get settings
		$query = "
			SELECT stripe_coupon_code, discount
			FROM promo_codes
			WHERE code  = '$promo_code'
			AND enabled = 1
			AND (
				expires_at IS NULL
				OR 
				expires_at > '$created_at'
			)

		";
		$result   = $db->do_select($query);
		$coupon   = $result[0]['stripe_coupon_code'] ?? '';
		$discount = (int)($result[0]['discount'] ?? 0) / 100;

		$price    = $helper->fetch_setting('price');
		$price_id = $helper->fetch_setting('price_id');

		if (!$price_id) {
			_exit(
				'error',
				'Unable to start purchase, please try again later',
				400,
				'Unable to start purchase, please try again later'
			);
		}

		$amount = $price - ($price * $discount);


		// Setup Stripe
		$stripe_sk = $helper->fetch_setting('merchant_secret_key');
		$stripe    = new \Stripe\StripeClient($stripe_sk);


		// Create/update customer based on what we currently have on the user
		$user = false;

		$query = "
			SELECT
			a.guid, a.password, a.stripe_customer_id, a.pii_data
			FROM users AS a
			WHERE a.email = '$email'
		";
		$user = $db->do_select($query);
		$stripe_customer_id = $user[0]['stripe_customer_id'] ?? '';
		$pii_data_enc       = $user[0]['pii_data'] ?? '';
		$user_guid          = $user[0]['guid'] ?? '';
		$user_password      = $user[0]['password'] ?? '';
		$user_checkout_id   = $user[0]['checkout_id'] ?? '';
		$invoice_prefix     = '';

		if (
			!$helper->verify_guid($guid) ||
			!$helper->guid_available($guid)
		) {
			$guid = $helper->generate_guid();
		}

		if ($user_guid) {
			$guid = $user_guid;
		}

		if (!$stripe_customer_id) {
			$customer_obj = [
				'metadata' => array(
					'guid' => $guid
				),
				'email'           => $email,
				'phone'           => $phone,
				'name'            => $first_name.' '.$last_name,
				'address'         => array(
					'line1'       => $line1,
					'line2'       => $line2,
					'city'        => $city,
					'country'     => 'US',
					'state'       => $state,
					'postal_code' => $zip
				),
			];

			$customer           = $stripe->customers->create($customer_obj);
			$stripe_customer_id = $customer['id'] ?? '';
			$invoice_prefix     = $customer['invoice_prefix'] ?? '';
			// elog($customer);
		}

		// sleep(2);


		// Create payment method (always create new, never use stored method)
		$payment_method_obj = [
			'type' => 'card',
			'card' => array(
				'number'    => $card_number,
				'exp_month' => $exp_month,
				'exp_year'  => $exp_year,
				'cvc'       => $cvv
			),
			'billing_details'     => array(
				'address'         => array(
					'line1'       => $line1,
					'line2'       => $line2,
					'city'        => $city,
					'country'     => 'US',
					'state'       => $state,
					'postal_code' => $zip
				),
				'email' => $email,
				'name'  => $name_on_card,
				'phone' => $phone
			)
		];

		$payment_method    = $stripe->paymentMethods->create($payment_method_obj);
		$payment_method_id = $payment_method['id'] ?? '';
		// elog($payment_method);
		// sleep(2);


		// Attach payment method to customer
		$attach_payment_method = $stripe->paymentMethods->attach(
			$payment_method_id,
			[ 'customer' => $stripe_customer_id ]
		);


		// Create/pick up session
		$create_new_session = true;

		// Session is already started, pick it back up if not expired
		if ($user_checkout_id) {
			$checkout   = $stripe->checkout->sessions->retrieve($user_checkout_id);
			$expires_at = (int)($checkout['expires_at'] ?? 0);

			if ($expires_at > (int)time()) {
				$create_new_session = false;
			}
		}

		if ($create_new_session) {
			$session_obj = [
				'customer'            => $stripe_customer_id,
				'client_reference_id' => $guid,
				'line_items'          => [
					[
						'price'       => $price_id,
						'quantity'    => 1
					]
				],
				'mode'        => 'payment',
				'currency'    => 'usd',
				'billing_address_collection' => 'required',
				'success_url' => PROTOCOL.'://'.FRONTEND_URL.'/receipt/success',
				'cancel_url'  => PROTOCOL.'://'.FRONTEND_URL.'/receipt/failure'
			];

			if ($coupon) {
				$session_obj['discounts'] = [['coupon' => $coupon]];
			} else {
				$promo_code = '';
			}

			// Generate follow link to pay
			$checkout = $stripe->checkout->sessions->create($session_obj);
		}

		$checkout_id = $checkout['id'] ?? '';
		// elog($checkout);


		// Save/update user
		if ($user) {
			// update record
			$pii = $helper->decrypt_pii($pii_data_enc);
			$pii['first_name']      = $first_name;
			$pii['last_name']       = $last_name;
			$pii['registration_ip'] = $registration_ip;
			$pii['phone']           = $phone;
			$pii_enc = $helper->encrypt_pii($pii);
			$query = "
				UPDATE users
				SET
				pii_data = '$pii_enc',
				stripe_customer_id = '$stripe_customer_id'
				WHERE guid = '$guid'
			";
			$db->do_query($query);

			if ($create_new_session) {
				// // what ever the item sale is for
				// $query = "
				// 	INSERT INTO prep_kit_sales (
				// 		guid,
				// 		email,
				// 		amount,
				// 		created_at,
				// 		checkout_id,
				// 		promo_code
				// 	) VALUES (
				// 		'$guid',
				// 		'$email',
				// 		'$amount',
				// 		'$created_at',
				// 		'$checkout_id',
				// 		'$promo_code'
				// 	)
				// ";
				// $db->do_query($query);
			}
		} else {
			// insert new record
			$confirmation_code = $helper->generate_hash(6);
			$registration_ip   = $helper->get_real_ip();

			$pii = Structs::user_info;
			$pii['first_name']      = $first_name;
			$pii['last_name']       = $last_name;
			$pii['registration_ip'] = $registration_ip;
			$pii['phone']           = $phone;
			$pii_enc = $helper->encrypt_pii($pii);

			$query = "
				INSERT INTO users (
					guid,
					role,
					email,
					pii_data,
					created_at,
					confirmation_code,
					stripe_customer_id
				) VALUES (
					'$guid',
					'user',
					'$email',
					'$pii_enc',
					'$created_at',
					'$confirmation_code',
					'$stripe_customer_id'
				)
			";
			$db->do_query($query);

			// // what ever the item sale is for
			// $query = "
			// 	INSERT INTO prep_kit_sales (
			// 		guid,
			// 		email,
			// 		amount,
			// 		created_at,
			// 		checkout_id,
			// 		promo_code
			// 	) VALUES (
			// 		'$guid',
			// 		'$email',
			// 		'$amount',
			// 		'$created_at',
			// 		'$checkout_id',
			// 		'$promo_code'
			// 	)
			// ";
			// $db->do_query($query);
		}

		// Add to drip campaign, if applicable
		if (!in_array($source, WEB_SOURCES)) {
			elog('Web source: "'.(string)$source.'" not recognized. Using "casper-network"');
			$source = 'turbocta';
		}

		if ($drip) {
			$query = "
				SELECT guid
				FROM subscriptions
				WHERE email = '$email'
			";
			$sub = $db->do_select($query);

			if (!$sub) {
				$query = "
					INSERT INTO subscriptions (
						guid,
						email,
						created_at,
						source
					) VALUES (
						'$guid',
						'$email',
						'$created_at',
						'$source'
					)
				";
				$db->do_query($query);

				// Add to Drip campaign
				$drip_api_key    = base64_encode($helper->fetch_setting('drip_api_key'));
				$drip_account_id = $helper->fetch_setting('drip_account_id');
				$base_url        = "https://api.getdrip.com/v2/$drip_account_id/subscribers";
				$ch              = curl_init();
				$headers         = array();
				$headers[]       = "Content-Type: application/json";
				$headers[]       = "User-Agent: Casper Association (members-api.casper.network";
				$headers[]       = "Authorization: Basic $drip_api_key";
				$data = array(
					"subscribers" => array(
						array(
							"email" => $email,
							"custom_fields" => array(
								"source" => $source
							)
						)
					)
				);

				curl_setopt($ch, CURLOPT_URL, $base_url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				curl_setopt($ch, CURLOPT_USERPWD, $drip_api_key.':');

				$result = curl_exec($ch);
				elog($result);
				curl_close($ch);
			}
		}

		_exit(
			'success',
			array(
				'stripe' => $checkout
			)
		);
	}
}
new PublicStripePayment();