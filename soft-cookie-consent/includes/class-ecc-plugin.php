<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap.
 */
class ECC_Plugin {

	public static function init() {
		ECC_Settings::init();
		ECC_Frontend::init();
		ECC_Log::init();
	}

	public static function activate() {
		if ( get_option( ECC_Helpers::OPTION, null ) === null ) {
			update_option( ECC_Helpers::OPTION, ECC_Helpers::defaults(), false );
		}
		ECC_Log::install();
	}
}
