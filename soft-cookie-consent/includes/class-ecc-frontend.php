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
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 0 );
		add_shortcode( 'cookie_settings', array( __CLASS__, 'shortcode' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_tag' ), 20, 3 );
	}

	/**
	 * Capture final HTML so raw theme <script> tags can be delayed too.
	 */
	public static function maybe_start_buffer() {
		$s = ECC_Helpers::settings();
		if ( empty( $s['enabled'] ) || empty( $s['block_scripts'] ) || empty( $s['auto_block_known'] ) || is_admin() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( is_feed() || is_robots() || is_trackback() ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}

		ob_start( array( __CLASS__, 'rewrite_html_buffer' ) );
	}

	/**
	 * @param string $html
	 * @return string
	 */
	public static function rewrite_html_buffer( $html ) {
		if ( $html === '' || stripos( $html, '<script' ) === false ) {
			return $html;
		}

		$s = ECC_Helpers::settings();
		if ( empty( $s['enabled'] ) || empty( $s['block_scripts'] ) || empty( $s['auto_block_known'] ) ) {
			return $html;
		}

		$granted = self::granted_categories_from_cookie( $s );

		return (string) preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			static function ( $m ) use ( $s, $granted ) {
				return ECC_Frontend::rewrite_script_match( $m[0], $m[1], $m[2], $s, $granted );
			},
			$html
		);
	}

	/**
	 * @param string              $full
	 * @param string              $attrs
	 * @param string              $body
	 * @param array<string,mixed> $s
	 * @param array<string,bool>  $granted
	 * @return string
	 */
	public static function rewrite_script_match( $full, $attrs, $body, $s, $granted ) {
		$attrs_l = strtolower( $attrs );

		// Skip non-JS and already handled / ignored tags.
		if ( preg_match( '/\btype\s*=\s*(["\'])\s*(application\/ld\+json|application\/json|importmap|module|text\/template|text\/x-template|speculationrules)\1/i', $attrs ) ) {
			return $full;
		}
		if ( strpos( $attrs_l, 'data-ecc-category=' ) !== false || strpos( $attrs_l, 'data-ecc-ignore' ) !== false ) {
			return $full;
		}

		$src = '';
		if ( preg_match( '/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $sm ) ) {
			$src = $sm[2];
		}

		$src_l = strtolower( $src );
		if ( $src_l !== '' && ( strpos( $src_l, 'soft-cookie-consent' ) !== false || strpos( $src_l, 'ecc-frontend' ) !== false ) ) {
			return $full;
		}
		if ( stripos( $body, 'ECC_CFG' ) !== false || stripos( $body, 'function gtag(){dataLayer.push' ) !== false ) {
			// Keep our consent-default snippet and localize payload scripts.
			if ( stripos( $body, 'wait_for_update' ) !== false || stripos( $body, 'ECC_CFG' ) !== false ) {
				return $full;
			}
		}

		$detected = self::detect_script_category( '', $src, $s, $body );
		$category = apply_filters( 'ecc_script_category', $detected, '', $src );
		$category = sanitize_key( (string) $category );
		if ( $category === '' || $category === 'necessary' || ! isset( ECC_Helpers::categories()[ $category ] ) ) {
			return $full;
		}

		// Already allowed by stored consent — leave executable.
		if ( ! empty( $granted[ $category ] ) ) {
			return $full;
		}

		$clean = preg_replace( '/\stype\s*=\s*(["\']).*?\1/i', '', $attrs );
		$clean = is_string( $clean ) ? $clean : $attrs;

		return '<script type="text/plain" data-ecc-category="' . esc_attr( $category ) . '"' . $clean . '>' . $body . '</script>';
	}

	/**
	 * @param array<string,mixed> $s
	 * @return array<string,bool>
	 */
	private static function granted_categories_from_cookie( $s ) {
		$name = ECC_Helpers::COOKIE;
		if ( empty( $_COOKIE[ $name ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return array();
		}
		$raw  = wp_unslash( (string) $_COOKIE[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		if ( (string) ( $data['v'] ?? '' ) !== (string) ( $s['consent_version'] ?? '1' ) ) {
			return array();
		}
		$cats = is_array( $data['categories'] ?? null ) ? $data['categories'] : array();
		$out  = array();
		foreach ( $cats as $id => $on ) {
			if ( $on ) {
				$out[ sanitize_key( (string) $id ) ] = true;
			}
		}
		return $out;
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
				'autoBlockKnown' => ! empty( $s['auto_block_known'] ),
				'gtmConsentMode' => ! empty( $s['gtm_consent_mode'] ),
				'logConsent'     => ! empty( $s['log_consent'] ),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'logNonce'       => wp_create_nonce( 'ecc_log_consent' ),
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

		$detected = '';
		if ( ! empty( $s['auto_block_known'] ) ) {
			$detected = self::detect_script_category( $handle, $src, $s );
		}

		/**
		 * Return category slug to delay a script until consent, or empty to leave as-is.
		 * Auto-detected category (if enabled) is passed as the first argument — override or clear it.
		 *
		 * @param string $category
		 * @param string $handle
		 * @param string $src
		 */
		$category = apply_filters( 'ecc_script_category', $detected, $handle, $src );
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

	/**
	 * Detect consent category for common analytics / marketing scripts.
	 *
	 * @param string              $handle
	 * @param string              $src
	 * @param array<string,mixed>|null $s
	 * @param string              $inline Inline script body (for raw HTML snippets).
	 * @return string Category slug or empty string.
	 */
	public static function detect_script_category( $handle, $src, $s = null, $inline = '' ) {
		$s       = $s ?: ECC_Helpers::settings();
		$handle  = strtolower( (string) $handle );
		$src     = strtolower( (string) $src );
		$inline  = strtolower( (string) $inline );
		$haystack = trim( $src . "\n" . $inline );

		// When Consent Mode is on, let GTM/gtag load — they wait for consent updates.
		$skip_google_loader = ! empty( $s['gtm_consent_mode'] );

		$rules = array(
			'analytics'   => array(
				'handles'  => array(
					'google-analytics',
					'google_gtagjs',
					'google-tag-manager',
					'gtag',
					'gtm',
					'googlesitekit-gtag',
					'monsterinsights',
					'exactmetrics',
					'ga-gtag',
					'woocommerce-google-analytics',
					'yandex-metrika',
					'yandex_metrika',
					'yith-wc-google-analytics',
					'jetpack-google-analytics',
					'hotjar',
					'clarity',
					'ms-clarity',
					'matomo',
					'piwik',
					'plausible',
					'fathom',
					'umami',
				),
				'patterns' => array(
					'google-analytics.com',
					'googletagmanager.com/gtag',
					'googletagmanager.com/gtm',
					'www.googletagmanager.com',
					'googletagmanager.com',
					'mc.yandex.ru',
					'mc.yandex.com',
					'yandex.ru/metrika',
					'yandex.com/metrika',
					'cdn.jsdelivr.net/npm/yandex-metrica',
					'ym(',
					'yacounter',
					'hotjar.com',
					'static.hotjar.com',
					'hj(',
					'clarity.ms',
					'clarity(',
					'matomo.',
					'cdn.plausible.io',
					'cdn.usefathom.com',
					'umami.',
					"gtag('config'",
					"gtag('js'",
					"gtag('event'",
				),
			),
			'marketing'   => array(
				'handles'  => array(
					'facebook-pixel',
					'fb-pixel',
					'fbevents',
					'meta-pixel',
					'tiktok-pixel',
					'tiktok_pixel',
					'pinterest-tag',
					'pinterest-pixel',
					'linkedin-insight',
					'li-insight',
					'twitter-pixel',
					'ads-twitter',
					'vk-pixel',
					'mailchimp-woocommerce',
					'google-ads',
					'google_ads',
					'gtag-ads',
				),
				'patterns' => array(
					'connect.facebook.net',
					'facebook.net/en_us/fbevents',
					'fbevents.js',
					'fbq(',
					'analytics.tiktok.com',
					'ttq.',
					's.pinimg.com/ct',
					'pintrk(',
					'snap.licdn.com',
					'lintrk(',
					'ads-twitter.com',
					'static.ads-twitter.com',
					'twq(',
					'vk.com/js/api/openapi',
					'top-fwz1.mail.ru',
					'_tmr',
					'googleadservices.com',
					'google.com/pagead',
					'doubleclick.net',
				),
			),
			'preferences' => array(
				'handles'  => array(
					'optinmonster',
					'omapi',
					'intercom',
					'crisp',
					'tawk',
					'tidio',
					'jivosite',
					'carrotquest',
				),
				'patterns' => array(
					'optinmonster.com',
					'widget.intercom.io',
					'client.crisp.chat',
					'embed.tawk.to',
					'code.tidio.co',
					'code.jivosite.com',
					'cdn.carrotquest.io',
				),
			),
		);

		/**
		 * Filter auto-detection rules: [ category => [ 'handles' => [], 'patterns' => [] ] ].
		 *
		 * @param array<string,array{handles:string[],patterns:string[]}> $rules
		 * @param string                                                   $handle
		 * @param string                                                   $src
		 */
		$rules = apply_filters( 'ecc_auto_script_rules', $rules, $handle, $src );

		foreach ( $rules as $category => $rule ) {
			foreach ( (array) ( $rule['handles'] ?? array() ) as $needle ) {
				$needle = strtolower( (string) $needle );
				if ( $needle === '' || ! self::handle_matches( $handle, $needle ) ) {
					continue;
				}
				if ( $skip_google_loader && self::is_google_tag_loader( $handle, $src, $inline ) ) {
					return '';
				}
				return $category;
			}

			foreach ( (array) ( $rule['patterns'] ?? array() ) as $needle ) {
				$needle = strtolower( (string) $needle );
				if ( $needle === '' || $haystack === '' ) {
					continue;
				}
				if ( strpos( $haystack, $needle ) !== false ) {
					if ( $skip_google_loader && self::is_google_tag_loader( $handle, $src, $inline ) ) {
						return '';
					}
					return $category;
				}
			}
		}

		return '';
	}

	/**
	 * @param string $handle
	 * @param string $needle
	 * @return bool
	 */
	private static function handle_matches( $handle, $needle ) {
		if ( $handle === $needle ) {
			return true;
		}
		// Prefix: gtm-xyz, yandex_metrika-foo
		if ( strpos( $handle, $needle . '-' ) === 0 || strpos( $handle, $needle . '_' ) === 0 ) {
			return true;
		}
		// Contained as segment: plugin-gtm, my_facebook-pixel
		if ( strpos( $handle, '-' . $needle ) !== false || strpos( $handle, '_' . $needle ) !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $handle
	 * @param string $src
	 * @param string $inline
	 * @return bool
	 */
	private static function is_google_tag_loader( $handle, $src, $inline = '' ) {
		$handle  = strtolower( (string) $handle );
		$src     = strtolower( (string) $src );
		$inline  = strtolower( (string) $inline );
		$haystack = $src . "\n" . $inline;
		if ( preg_match( '/(^|[-_])(gtm|gtag|google-tag|google_gtag|googlesitekit-gtag)($|[-_])/', $handle ) ) {
			return true;
		}
		return ( strpos( $haystack, 'googletagmanager.com' ) !== false
			|| strpos( $haystack, 'google-analytics.com/analytics.js' ) !== false
			|| strpos( $haystack, 'google-analytics.com/ga.js' ) !== false
			|| strpos( $haystack, 'google-analytics.com/g/collect' ) !== false );
	}
}
