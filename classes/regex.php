<?php
/**
 *
 * Data structure regex for encrypted PII objects
 *
 * Defining various regex patterns and character limits for all kinds of variables.
 *
 * @var array $shufti               Regex for shufti reference ID.
 * @var array $letter               Regex for letter content.
 * @var array $title                Regex for a ballot/discussion titles.
 * @var array $pseudonym            Regex for a user pseudonym.
 * @var array $db_setting           Regex for a DB settings table key.
 * @var array $cookie               Regex for cookie strings.
 * @var array $sha256_hash          Regex for sha256_hash strings.
 * @var array $validator_id         Regex for validator_id hex strings.
 * @var array $guid                 Regex for guid strings.
 * @var array $company_name         Regex for company_name strings.
 * @var array $year                 Regex for year strings.
 * @var array $registration_number  Regex for registration_number strings.
 * @var array $human_name           Regex for human_name strings.
 * @var array $suffix               Regex for suffix strings.
 * @var array $date                 Regex for date strings.
 * @var array $date_extended        Regex for date strings.
 * @var array $address              Regex for address strings.
 * @var array $city                 Regex for city strings.
 * @var array $state_or_province    Regex for state_or_province strings.
 * @var array $postal_code          Regex for postal_code strings.
 * @var array $float                Regex for float strings.
 *
 */

class Regex {
	public static $shufti = array(
		"char_limit" => 70,
		"pattern"    => "/SHUFTI_[a-fA-F0-9-]{36}[0-9.]+$/"
	);

	public static $letter = array(
		"char_limit" => 8388608,
		"pattern"    => "/^[a-zA-Z0-9-_\"'.,;:\/+=)(&%$@#!?*^|<> àáâãăäāåæćčçèéêĕëēìíîĭïðłñòóôõöőøšùúûüűýÿþÀÁÂÃĂÄĀÅÆĆČÇÈÉÊĔËĒÌÍÎĬÏÐŁÑÒÓÔÕÖŐØŠÙÚÛÜŰÝÞß]+$/"
	);

	public static $title = array(
		"char_limit" => 64,
		"pattern"    => "/^[a-zA-Z0-9)(_\-,.':+|&*$%#!@\/ ]+$/"
	);

	public static $pseudonym = array(
		"char_limit" => 32,
		"pattern"    => "/^[a-zA-Z0-9_-]+$/"
	);

	public static $telegram = array(
		"char_limit" => 32,
		"pattern"    => "/@([a-zA-Z0-9._-]){4,32}$/"
	);

	public static $db_setting = array(
		"char_limit" => 64,
		"pattern"    => "/^[a-zA-Z_]+$/"
	);

	public static $cookie = array(
		"char_limit" => 32,
		"pattern"    => "/^[ABCDEFGHJKLMNPQRSTUVWXYZ2-9]+$/"
	);

	public static $sha256_hash = array(
		"char_limit" => 64,
		"pattern"    => "/^[a-fA-F0-9]+$/"
	);

	public static $validator_id = array(
		"char_limit" => 68,
		"pattern"    => "/(01|02)([a-fA-F0-9]){64,66}$/"
	);

	public static $guid = array(
		"char_limit" => 36,
		"pattern"    => "/^[0-9a-fA-F-]+$/"
	);

	public static $company_name = array(
		"char_limit" => 64,
		"pattern"    => "/^[a-zA-Z0-9-_.,' àáâãăäāåæćčçèéêĕëēìíîĭïðłñòóôõöőøšùúûüűýÿþÀÁÂÃĂÄĀÅÆĆČÇÈÉÊĔËĒÌÍÎĬÏÐŁÑÒÓÔÕÖŐØŠÙÚÛÜŰÝÞß]+$/"
	);

	public static $year = array(
		"char_limit" => 4,
		"pattern"    => "/^[0-9]+$/"
	);

	public static $registration_number = array(
		"char_limit" => 32,
		"pattern"    => "/^[a-zA-Z0-9-]+$/"
	);

	public static $human_name = array(
		"char_limit" => 32,
		"pattern"    => "/^[a-zA-Z-\' àáâãăäāåæćčçèéêĕëēìíîĭïðłñòóôõöőøšùúûüűýÿþÀÁÂÃĂÄĀÅÆĆČÇÈÉÊĔËĒÌÍÎĬÏÐŁÑÒÓÔÕÖŐØŠÙÚÛÜŰÝÞß]+$/"
	);

	public static $suffix = array(
		"char_limit" => 16,
		"pattern"    => "/^[a-zA-Z-.]+$/"
	);

	/*
	Intended format like: YYYY-mm-dd. 
	Will pass <=> string comparison checks this way. 
	*/
	public static $date = array(
		"char_limit" => 10,
		"pattern"    => "/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|1[0-9]|2[0-9]|3[0-1])$/"
	);

	/*
	Intended format like: YYYY-mm-dd hh:ii:ss. 
	Will pass <=> string comparison checks this way. 
	*/
	public static $date_extended = array(
		"char_limit" => 19,
		"pattern"    => "/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|1[0-9]|2[0-9]|3[0-1]) ([0-2][0-9]):([0-5][0-9]):([0-5][0-9])$/"
	);

	public static $address = array(
		"char_limit" => 64,
		"pattern"    => "/^[a-zA-Z0-9-.,\'#& ]+$/"
	);

	public static $city = array(
		"char_limit" => 32,
		"pattern"    => "/^[a-zA-Z-,\' ]+$/"
	);

	public static $state_or_province = array(
		"char_limit" => 32,
		"pattern"    => "/^[a-zA-Z- ]+$/"
	);

	public static $postal_code = array(
		"char_limit" => 16,
		"pattern"    => "/^[a-zA-Z0-9-]+$/"
	);

	public static $float = array(
		"char_limit" => 5,
		"pattern"    => "/^(0\.[0-9]+|\.[0-9]+)$/"
	);
}