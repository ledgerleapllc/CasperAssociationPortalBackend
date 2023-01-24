<?php
/**
 *
 * Data structure templates for Casper Association Portal encrypted PII objects
 *
 * @static user_info         Struct for user pii.
 * @static entity_info       Struct for entity pii.
 * @static office            Struct for office location info
 *
 */
class Structs {
	static $max_size = 64;

	public const user_info = array(
		"first_name"             => "",
		"middle_name"            => "",
		"last_name"              => "",
		"dob"                    => "",
		"registration_ip"        => "",
		"phone"                  => "",
		"country_of_citizenship" => ""
	);

	public const entity_info = array(
		"entity_name"          => "",
		"entity_type"          => "",
		"registration_number"  => "",
		"registration_country" => "",
		"tax_id"               => "",
		"document_url"         => "",
		"document_page"        => ""
	);

	public const office = array(
		"created_at"        => "YYYY-mm-dd HH:ii:ss UTC",
		"updated_at"        => "YYYY-mm-dd HH:ii:ss UTC",
		"country"           => "",
		"address1"          => "",
		"address2"          => "",
		"city"              => "",
		"state_or_province" => "",
		"postal_code"       => ""
	);
}

?>