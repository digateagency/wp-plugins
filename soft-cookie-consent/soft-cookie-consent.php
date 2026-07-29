<?php
/**
 * Plugin Name: Soft Cookie Consent
 * Description: GDPR/ePrivacy cookie banner: категории согласия, RU/EN тексты, кастомные стили, блокировка скриптов до согласия.
 * Version: 1.1.2
 * Author: Denis Chernyshov
 * Text Domain: soft-cookie-consent
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ECC_VERSION', '1.2.0' );
define( 'ECC_FILE', __FILE__ );
define( 'ECC_PATH', plugin_dir_path( __FILE__ ) );
define( 'ECC_URL', plugin_dir_url( __FILE__ ) );

require_once ECC_PATH . 'includes/class-ecc-helpers.php';
require_once ECC_PATH . 'includes/class-ecc-settings.php';
require_once ECC_PATH . 'includes/class-ecc-frontend.php';
require_once ECC_PATH . 'includes/class-ecc-log.php';
require_once ECC_PATH . 'includes/class-ecc-plugin.php';

add_action( 'plugins_loaded', array( 'ECC_Plugin', 'init' ), 5 );
register_activation_hook( __FILE__, array( 'ECC_Plugin', 'activate' ) );
