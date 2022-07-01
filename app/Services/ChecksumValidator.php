<?php

namespace App\Services;

use App\Services\Blake2b;

class ChecksumValidator {
	function __construct($vid = null) {
		$this->validator_id = null;
		$this->keytag = '01';
		$this->algo = 'ed25519';

		if($vid) {
			$this->validator_id = $vid;
		}

		$this->HEX_CHARS = array(
			'0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
			'a', 'b', 'c', 'd', 'e', 'f',
			'A', 'B', 'C', 'D', 'E', 'F'
		);

		$this->SMALL_BYTES_COUNT = 75;
	}

	function _blake_hash($public_key) {
		$blake = new Blake2b($size = 32);
		$hash = $blake->hash($public_key);
		return $hash;
	}

	function _bytes_to_nibbles($v) {
		$output_nibbles = array();

		foreach(str_split($v) as $byte) {
			$byte = ord($byte);
			$output_nibbles[] = ($byte >> 4);
			$output_nibbles[] = ($byte & 0x0f);
		}

		return $output_nibbles;
	}

	function _bytes_to_bits_cycle($v) {
		$_blake_hash = $this->_blake_hash($v);
		$ret = array();

		foreach(str_split($_blake_hash) as $b) {
			$b = ord($b);

			for($j = 0; $j < 8; $j++) {
				$ret[] = (($b >> $j) & 0x01);
			}
		}

		return $ret;
	}

	function _encode($public_key) {
		$nibbles = $this->_bytes_to_nibbles($public_key);
		$hash_bits = $this->_bytes_to_bits_cycle($public_key);
		$ret = array();
		$k = 0;

		foreach($nibbles as $nibble) {
			if($nibble >= 10) {
				if($hash_bits[$k] == 1) {
					$nibble += 6;
				}

				$k += 1;
			}

			$ret[] = $this->HEX_CHARS[$nibble];
		}

		$join = array_reduce(
			$ret,
			function($out, $in) {
				return ord($out) << 8 | ord($in);
			}
		);

		$join = '';

		foreach($ret as $char) {
			$join = $join.$char;
		}

		return $join;
	}

	function do($_v = null) {
		if($this->validator_id) {
			$this->keytag = substr($this->validator_id, 0, 2);
			$reference_validator_id = $this->validator_id;
			$this->validator_id = strtolower(substr($reference_validator_id, 2));

			if($this->keytag == '01') {
				$this->algo = 'ed25519';
			} elseif ($this->keytag == '02') {
				$this->algo = 'secp256k1';
			} else {
				return false;
			}

			$v = hex2bin($this->validator_id);

			if(mb_strlen($v, '8bit') > $this->SMALL_BYTES_COUNT) {
				return true;
			}

			$join = $this->keytag . $this->_encode($v);

			return $join == $reference_validator_id;

		} else {
			if (!$_v) {
				return false;
			}

			$this->keytag = substr($_v, 0, 2);
			$_v = substr($_v, 2);

			if($this->keytag == '01') {
				$this->algo = 'ed25519';
			} elseif ($this->keytag == '02') {
				$this->algo = 'secp256k1';
			} else {
				return false;
			}

			$v = hex2bin($_v);

			if(mb_strlen($v, '8bit') > $this->SMALL_BYTES_COUNT) {
				return  strtolower($_v);
			}

			return $this->keytag . $this->_encode($v);
		}
	}
}
?>
