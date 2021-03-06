<?php

namespace Utils\Crypto;

use ParagonIE\Halite\{Alerts as CryptoException, Config, Halite, Symmetric\Config as SymmetricConfig, Util as CryptoUtil};

class Coder {
	
	public static function encode($value, $key = '') {
        $output = self::xor_coding($value, $key);
        return base64_encode($output);
    }
	
    public static function decode($value, $key = '') {
        $output = base64_decode($value);
        return self::xor_coding($output, $key);
    }
	
	private static function xor_coding($value, $key = '') {
		$output = '';
		$value_len = strlen($value);
		$key_len  = strlen($key);
        for($i = 0; $i < $value_len; $i++) {
            $output .= ($value[$i] ^ $key[$i % $key_len] );
        }
        return $output;
    }
	
	private static function xor_nonce_coding($value, $key = '') {
		$nonce = \Sodium\randombytes_buf(\Sodium\CRYPTO_SECRETBOX_NONCEBYTES);
		$output = \Sodium\crypto_stream_xor($value, $nonce, $key);
		return $output;
	}
}