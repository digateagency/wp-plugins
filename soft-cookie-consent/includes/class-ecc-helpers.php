<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defaults, i18n packs, settings merge.
 */
class ECC_Helpers {

	const OPTION = 'ecc_settings';
	const COOKIE = 'ecc_consent';

	/**
	 * @return string[]
	 */
	public static function languages() {
		return array(
			'ru' => 'Русский',
			'en' => 'English',
		);
	}

	/**
	 * Consent categories (necessary is always on).
	 *
	 * @return array<string,array{label_key:string,essential:bool}>
	 */
	public static function categories() {
		return array(
			'necessary'   => array( 'essential' => true ),
			'preferences' => array( 'essential' => false ),
			'analytics'   => array( 'essential' => false ),
			'marketing'   => array( 'essential' => false ),
		);
	}

	/**
	 * Default copy for a language.
	 *
	 * @param string $lang
	 * @return array<string,string>
	 */
	public static function default_texts( $lang = 'ru' ) {
		$ru = array(
			'banner_title'       => 'Мы используем cookies',
			'banner_text'        => 'Мы используем необходимые cookies для работы сайта и опциональные — для аналитики и маркетинга. Вы можете принять все, отклонить необязательные или настроить категории.',
			'btn_accept'         => 'Принять все',
			'btn_reject'         => 'Необходимые',
			'btn_settings'       => 'Настроить',
			'btn_save'           => 'Сохранить выбор',
			'btn_close'          => 'Закрыть',
			'modal_title'        => 'Настройки cookies',
			'modal_intro'        => 'Выберите, какие категории cookies разрешить. Необходимые cookies всегда активны — без них сайт не работает корректно.',
			'privacy_label'      => 'Политика конфиденциальности',
			'cat_necessary'      => 'Необходимые',
			'cat_necessary_desc' => 'Нужны для базовой работы сайта, безопасности и сохранения вашего выбора по cookies. Нельзя отключить.',
			'cat_preferences'    => 'Функциональные',
			'cat_preferences_desc' => 'Запоминают ваши предпочтения (язык, регион, отображение) для более удобной работы с сайтом.',
			'cat_analytics'      => 'Аналитика',
			'cat_analytics_desc' => 'Помогают понимать, как используют сайт (посещаемость, источники трафика), чтобы улучшать сервис.',
			'cat_marketing'      => 'Маркетинг',
			'cat_marketing_desc' => 'Используются для рекламы и ремаркетинга на этом и других сайтах.',
			'reopen_label'       => 'Настройки cookies',
			'consent_revision_note' => 'Политика cookies обновлена. Пожалуйста, подтвердите выбор снова.',
		);

		$en = array(
			'banner_title'       => 'We use cookies',
			'banner_text'        => 'We use essential cookies to run the site and optional cookies for analytics and marketing. You can accept all, reject non-essential, or customize categories.',
			'btn_accept'         => 'Accept all',
			'btn_reject'         => 'Essential only',
			'btn_settings'       => 'Customize',
			'btn_save'           => 'Save choices',
			'btn_close'          => 'Close',
			'modal_title'        => 'Cookie settings',
			'modal_intro'        => 'Choose which cookie categories to allow. Essential cookies are always on — the site cannot work properly without them.',
			'privacy_label'      => 'Privacy policy',
			'cat_necessary'      => 'Essential',
			'cat_necessary_desc' => 'Required for basic site operation, security, and storing your cookie preferences. Cannot be disabled.',
			'cat_preferences'    => 'Preferences',
			'cat_preferences_desc' => 'Remember your choices (language, region, display) to make the site more convenient.',
			'cat_analytics'      => 'Analytics',
			'cat_analytics_desc' => 'Help us understand how visitors use the site so we can improve it.',
			'cat_marketing'      => 'Marketing',
			'cat_marketing_desc' => 'Used for advertising and remarketing on this and other sites.',
			'reopen_label'       => 'Cookie settings',
			'consent_revision_note' => 'Our cookie policy has been updated. Please confirm your choices again.',
		);

		return $lang === 'en' ? $en : $ru;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'            => 1,
			'language'           => 'ru',
			'privacy_url'        => '', // legacy, migrated to privacy_page
			'privacy_url_en'     => '',
			'privacy_page'       => 0,
			'privacy_page_en'    => 0,
			'layout'             => 'bottom', // bottom | modal | corner
			'corner_side'        => 'right', // left | right
			'enter_delay'        => 450, // ms before banner animation starts
			'show_reject'        => 1,
			'show_settings'      => 1,
			'show_reopen'        => 1,
			'block_scripts'      => 1,
			'consent_version'    => '1',
			'cookie_days'        => 182,
			'gtm_consent_mode'   => 1,
			'categories_enabled' => array(
				'necessary'   => 1,
				'preferences' => 1,
				'analytics'   => 1,
				'marketing'   => 1,
			),
			'texts'              => array(
				'ru' => self::default_texts( 'ru' ),
				'en' => self::default_texts( 'en' ),
			),
			'styles'             => self::default_styles(),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function default_styles() {
		return array(
			'overlay_bg'         => 'rgba(15, 23, 42, 0.45)',
			'banner_bg'          => '#ffffff',
			'banner_text'        => '#111827',
			'banner_muted'       => '#6b7280',
			'banner_border'      => '#e5e7eb',
			'banner_radius'      => '16px',
			'banner_shadow'      => '0 12px 40px rgba(15, 23, 42, 0.14)',
			'banner_max_width'   => '720px',
			'banner_padding'     => '20px',
			'font_family'        => 'inherit',
			'font_size'          => '14px',
			'title_size'         => '18px',
			'title_weight'       => '600',
			'btn_radius'         => '999px',
			'btn_padding_y'      => '10px',
			'btn_padding_x'      => '18px',
			'btn_font_size'      => '14px',
			'btn_font_weight'    => '600',
			'btn_accept_bg'      => '#0d9488',
			'btn_accept_text'    => '#ffffff',
			'btn_accept_hover'   => '#0f766e',
			'btn_reject_bg'      => '#ffffff',
			'btn_reject_text'    => '#111827',
			'btn_reject_border'  => '#d1d5db',
			'btn_settings_bg'    => '#f3f4f6',
			'btn_settings_text'  => '#111827',
			'link_color'         => '#0d9488',
			'toggle_on'          => '#0d9488',
			'toggle_off'         => '#d1d5db',
			'reopen_bg'          => '#111827',
			'reopen_text'        => '#ffffff',
			'reopen_radius'      => '999px',
			'z_index'            => '2147483000',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function settings() {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$s = self::deep_merge( self::defaults(), $stored );

		// Ensure both language packs exist.
		foreach ( array( 'ru', 'en' ) as $lang ) {
			$s['texts'][ $lang ] = wp_parse_args(
				is_array( $s['texts'][ $lang ] ?? null ) ? $s['texts'][ $lang ] : array(),
				self::default_texts( $lang )
			);
		}
		$s['styles'] = wp_parse_args(
			is_array( $s['styles'] ?? null ) ? $s['styles'] : array(),
			self::default_styles()
		);

		if ( ! in_array( $s['language'], array( 'ru', 'en' ), true ) ) {
			$s['language'] = 'ru';
		}
		if ( ! in_array( $s['layout'], array( 'bottom', 'modal', 'corner' ), true ) ) {
			$s['layout'] = 'bottom';
		}
		if ( ! in_array( $s['corner_side'] ?? '', array( 'left', 'right' ), true ) ) {
			$s['corner_side'] = 'right';
		}
		$s['enter_delay'] = max( 0, min( 5000, absint( $s['enter_delay'] ?? 450 ) ) );

		$s['privacy_page']    = absint( $s['privacy_page'] ?? 0 );
		$s['privacy_page_en'] = absint( $s['privacy_page_en'] ?? 0 );

		// Migrate legacy URL fields → page IDs once.
		if ( ! $s['privacy_page'] && ! empty( $s['privacy_url'] ) && is_string( $s['privacy_url'] ) ) {
			$from_url = url_to_postid( $s['privacy_url'] );
			if ( $from_url ) {
				$s['privacy_page'] = $from_url;
			}
		}
		if ( ! $s['privacy_page_en'] && ! empty( $s['privacy_url_en'] ) && is_string( $s['privacy_url_en'] ) ) {
			$from_url = url_to_postid( $s['privacy_url_en'] );
			if ( $from_url ) {
				$s['privacy_page_en'] = $from_url;
			}
		}

		return $s;
	}

	/**
	 * Active frontend language pack.
	 *
	 * @param array<string,mixed>|null $s
	 * @return array<string,string>
	 */
	public static function active_texts( $s = null ) {
		$s    = $s ?: self::settings();
		$lang = $s['language'] ?? 'ru';
		return is_array( $s['texts'][ $lang ] ?? null ) ? $s['texts'][ $lang ] : self::default_texts( $lang );
	}

	/**
	 * Privacy policy URL for active language.
	 *
	 * @param array<string,mixed>|null $s
	 * @return string
	 */
	public static function privacy_url( $s = null ) {
		$s = $s ?: self::settings();
		$page_id = 0;
		if ( ( $s['language'] ?? 'ru' ) === 'en' && ! empty( $s['privacy_page_en'] ) ) {
			$page_id = (int) $s['privacy_page_en'];
		} elseif ( ! empty( $s['privacy_page'] ) ) {
			$page_id = (int) $s['privacy_page'];
		}

		if ( $page_id > 0 ) {
			$link = get_permalink( $page_id );
			return $link ? esc_url_raw( $link ) : '';
		}

		// Legacy fallback for installs that still only have URLs.
		if ( ( $s['language'] ?? 'ru' ) === 'en' && ! empty( $s['privacy_url_en'] ) ) {
			return esc_url_raw( (string) $s['privacy_url_en'] );
		}
		return ! empty( $s['privacy_url'] ) ? esc_url_raw( (string) $s['privacy_url'] ) : '';
	}

	/**
	 * @param array<string,mixed> $base
	 * @param array<string,mixed> $over
	 * @return array<string,mixed>
	 */
	public static function deep_merge( $base, $over ) {
		foreach ( $over as $k => $v ) {
			if ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) && self::is_assoc( $v ) ) {
				$base[ $k ] = self::deep_merge( $base[ $k ], $v );
			} else {
				$base[ $k ] = $v;
			}
		}
		return $base;
	}

	/**
	 * @param array $arr
	 * @return bool
	 */
	private static function is_assoc( $arr ) {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
