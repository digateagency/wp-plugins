<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front banner, assets, shortcode, script gating.
 */
class ECC_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'consent_defaults_head' ), 1 );
		add_shortcode( 'cookie_settings', array( __CLASS__, 'shortcode' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_tag' ), 20, 3 );
	}

	public static function assets() {
		$s = ECC_Helpers::settings();
		if ( empty( $s['enabled'] ) || is_admin() ) {
			return;
		}

		wp_enqueue_style( 'ecc-frontend', ECC_URL . 'assets/frontend.css', array(), ECC_VERSION );
		wp_enqueue_script( 'ecc-frontend', ECC_URL . 'assets/frontend.js', array(), ECC_VERSION, true );

		$texts = ECC_Helpers::active_texts( $s );
		$cats  = array();
		foreach ( ECC_Helpers::categories() as $id => $meta ) {
			if ( empty( $s['categories_enabled'][ $id ] ) && empty( $meta['essential'] ) ) {
				continue;
			}
			$cats[] = array(
				'id'        => $id,
				'essential' => ! empty( $meta['essential'] ),
				'label'     => $texts[ 'cat_' . $id ] ?? $id,
				'desc'      => $texts[ 'cat_' . $id . '_desc' ] ?? '',
			);
		}

		wp_localize_script(
			'ecc-frontend',
			'ECC_CFG',
			array(
				'cookieName'     => ECC_Helpers::COOKIE,
				'version'        => (string) $s['consent_version'],
				'days'           => (int) $s['cookie_days'],
				'layout'         => $s['layout'],
				'cornerSide'     => $s['corner_side'] ?? 'right',
				'enterDelay'     => (int) ( $s['enter_delay'] ?? 450 ),
				'language'       => $s['language'],
				'showReject'     => ! empty( $s['show_reject'] ),
				'showSettings'   => ! empty( $s['show_settings'] ),
				'showReopen'     => ! empty( $s['show_reopen'] ),
				'blockScripts'   => ! empty( $s['block_scripts'] ),
				'gtmConsentMode' => ! empty( $s['gtm_consent_mode'] ),
				'privacyUrl'     => ECC_Helpers::privacy_url( $s ),
				'texts'          => $texts,
				'categories'     => $cats,
				'styles'         => $s['styles'],
			)
		);
	}

	/**
	 * Default denied consent before interactive choice (Consent Mode v2).
	 */
	public static function consent_defaults_head() {
		$s = ECC_Helpers::settings();
		if ( empty( $s['enabled'] ) || empty( $s['gtm_consent_mode'] ) || is_admin() ) {
			return;
		}
		?>
		<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('consent', 'default', {
			ad_storage: 'denied',
			ad_user_data: 'denied',
			ad_personalization: 'denied',
			analytics_storage: 'denied',
			functionality_storage: 'denied',
			personalization_storage: 'denied',
			security_storage: 'granted',
			wait_for_update: 500
		});
		</script>
		<?php
	}

	public static function render() {
		$s = ECC_Helpers::settings();
		if ( empty( $s['enabled'] ) || is_admin() ) {
			return;
		}
		// Markup is built in JS from config for i18n flexibility; mount root only.
		echo '<div id="ecc-root" class="ecc-root" hidden aria-live="polite"></div>' . "\n";
	}

	/**
	 * @param array<string,string> $atts
	 * @return string
	 */
	public static function shortcode( $atts = array() ) {
		$s     = ECC_Helpers::settings();
		$texts = ECC_Helpers::active_texts( $s );
		$label = $texts['reopen_label'] ?? 'Cookie settings';
		return '<button type="button" class="ecc-open-settings ecc-shortcode-btn">' . esc_html( $label ) . '</button>';
	}

	/**
	 * Optionally mark registered scripts for delayed execution.
	 *
	 * @param string $tag
	 * @param string $handle
	 * @param string $src
	 * @return string
	 */
	public static function filter_script_tag( $tag, $handle, $src ) {
		$s = ECC_Helpers::settings();
		if ( empty( $s['enabled'] ) || empty( $s['block_scripts'] ) ) {
			return $tag;
		}

		/**
		 * Return category slug to delay a script until consent, or empty to leave as-is.
		 *
		 * @param string $category
		 * @param string $handle
		 * @param string $src
		 */
		$category = apply_filters( 'ecc_script_category', '', $handle, $src );
		$category = sanitize_key( (string) $category );
		if ( $category === '' || $category === 'necessary' ) {
			return $tag;
		}
		if ( ! isset( ECC_Helpers::categories()[ $category ] ) ) {
			return $tag;
		}

		// Convert to inert script until consent unlocks it.
		if ( preg_match( '/\stype=(["\'])(.*?)\1/i', $tag ) ) {
			$tag = preg_replace( '/\stype=(["\'])(.*?)\1/i', ' type="text/plain" data-ecc-category="' . esc_attr( $category ) . '"', $tag, 1 );
		} else {
			$tag = str_replace( '<script ', '<script type="text/plain" data-ecc-category="' . esc_attr( $category ) . '" ', $tag );
		}
		return $tag;
	}
}
