<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings UI.
 */
class ECC_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_options_page(
			__( 'Soft Cookie Consent', 'soft-cookie-consent' ),
			__( 'Soft Cookie Consent', 'soft-cookie-consent' ),
			'manage_options',
			'soft-cookie-consent',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register() {
		register_setting(
			'ecc_settings_group',
			ECC_Helpers::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => ECC_Helpers::defaults(),
			)
		);
	}

	/**
	 * @param string $hook
	 */
	public static function assets( $hook ) {
		if ( $hook !== 'settings_page_soft-cookie-consent' ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'ecc-admin', ECC_URL . 'assets/admin.css', array(), ECC_VERSION );
		wp_enqueue_script( 'ecc-admin', ECC_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), ECC_VERSION, true );
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$d   = ECC_Helpers::defaults();
		$out = ECC_Helpers::settings();
		if ( ! is_array( $input ) ) {
			return $out;
		}

		$out['enabled']          = ! empty( $input['enabled'] ) ? 1 : 0;
		$out['show_reject']      = ! empty( $input['show_reject'] ) ? 1 : 0;
		$out['show_settings']    = ! empty( $input['show_settings'] ) ? 1 : 0;
		$out['show_reopen']      = ! empty( $input['show_reopen'] ) ? 1 : 0;
		$out['block_scripts']    = ! empty( $input['block_scripts'] ) ? 1 : 0;
		$out['auto_block_known'] = ! empty( $input['auto_block_known'] ) ? 1 : 0;
		$out['gtm_consent_mode'] = ! empty( $input['gtm_consent_mode'] ) ? 1 : 0;
		$out['log_consent']      = ! empty( $input['log_consent'] ) ? 1 : 0;
		$out['log_retention_days'] = max( 0, min( 3650, absint( $input['log_retention_days'] ?? 365 ) ) );

		$lang = sanitize_key( $input['language'] ?? 'ru' );
		$out['language'] = isset( ECC_Helpers::languages()[ $lang ] ) ? $lang : 'ru';

		$layout = sanitize_key( $input['layout'] ?? 'bottom' );
		$out['layout'] = in_array( $layout, array( 'bottom', 'modal', 'corner' ), true ) ? $layout : 'bottom';

		$corner = sanitize_key( $input['corner_side'] ?? 'right' );
		$out['corner_side'] = in_array( $corner, array( 'left', 'right' ), true ) ? $corner : 'right';

		$out['enter_delay'] = max( 0, min( 5000, absint( $input['enter_delay'] ?? 450 ) ) );

		$out['privacy_page']    = absint( $input['privacy_page'] ?? 0 );
		$out['privacy_page_en'] = absint( $input['privacy_page_en'] ?? 0 );
		// Keep legacy keys empty after save via page picker.
		$out['privacy_url']    = '';
		$out['privacy_url_en'] = '';

		$out['consent_version'] = sanitize_text_field( (string) ( $input['consent_version'] ?? '1' ) );
		$out['cookie_days']     = max( 1, min( 730, absint( $input['cookie_days'] ?? 182 ) ) );

		$cats = array();
		foreach ( array_keys( ECC_Helpers::categories() ) as $cat ) {
			if ( $cat === 'necessary' ) {
				$cats[ $cat ] = 1;
				continue;
			}
			$cats[ $cat ] = ! empty( $input['categories_enabled'][ $cat ] ) ? 1 : 0;
		}
		$out['categories_enabled'] = $cats;

		$text_keys = array_keys( ECC_Helpers::default_texts( 'ru' ) );
		foreach ( array( 'ru', 'en' ) as $l ) {
			$pack = array();
			$src  = is_array( $input['texts'][ $l ] ?? null ) ? $input['texts'][ $l ] : array();
			foreach ( $text_keys as $key ) {
				$raw = isset( $src[ $key ] ) ? wp_unslash( $src[ $key ] ) : ( $d['texts'][ $l ][ $key ] ?? '' );
				$pack[ $key ] = in_array( $key, array( 'banner_text', 'modal_intro', 'cat_necessary_desc', 'cat_preferences_desc', 'cat_analytics_desc', 'cat_marketing_desc', 'consent_revision_note' ), true )
					? sanitize_textarea_field( $raw )
					: sanitize_text_field( $raw );
			}
			$out['texts'][ $l ] = $pack;
		}

		$styles = array();
		foreach ( ECC_Helpers::default_styles() as $key => $default ) {
			$val = isset( $input['styles'][ $key ] ) ? sanitize_text_field( wp_unslash( $input['styles'][ $key ] ) ) : $default;
			if ( $val === '' ) {
				$val = $default;
			}
			// Auto px for bare numbers on size tokens.
			if ( preg_match( '/^(banner_radius|banner_max_width|banner_padding|font_size|title_size|btn_radius|btn_padding_y|btn_padding_x|btn_font_size|reopen_radius)$/', $key )
				&& preg_match( '/^-?\d+(\.\d+)?$/', $val ) ) {
				$val .= 'px';
			}
			$styles[ $key ] = $val;
		}
		$out['styles'] = $styles;

		return $out;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = ECC_Helpers::settings();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'general'  => __( 'Общие', 'soft-cookie-consent' ),
			'texts'    => __( 'Тексты RU / EN', 'soft-cookie-consent' ),
			'styles'   => __( 'Стили', 'soft-cookie-consent' ),
			'advanced' => __( 'Скрипты', 'soft-cookie-consent' ),
			'log'      => __( 'Журнал', 'soft-cookie-consent' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		?>
		<div class="wrap ecc-admin">
			<h1><?php esc_html_e( 'Soft Cookie Consent', 'soft-cookie-consent' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Баннер cookies с категориями согласия, языками RU/EN и редактируемым дизайном. Соответствует типичным требованиям GDPR/ePrivacy: отказ не сложнее принятия, детальная настройка, отзыв согласия, хранение версии согласия.', 'soft-cookie-consent' ); ?></p>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $id ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'options-general.php?page=soft-cookie-consent&tab=' . $id ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( $tab === 'log' ) : ?>
				<?php ECC_Log::render_admin_tab( $s ); ?>
			<?php else : ?>
			<form method="post" action="options.php" class="ecc-form">
				<?php settings_fields( 'ecc_settings_group' ); ?>

				<?php if ( $tab === 'general' ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Баннер', 'soft-cookie-consent' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Включить на сайте', 'soft-cookie-consent' ); ?></label></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Язык баннера', 'soft-cookie-consent' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[language]">
									<?php foreach ( ECC_Helpers::languages() as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['language'], $code ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Какой языковой пакет показывать посетителям. Тексты обоих языков редактируются во вкладке «Тексты».', 'soft-cookie-consent' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Расположение', 'soft-cookie-consent' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[layout]" id="ecc_layout">
									<option value="bottom" <?php selected( $s['layout'], 'bottom' ); ?>><?php esc_html_e( 'Снизу (бар)', 'soft-cookie-consent' ); ?></option>
									<option value="modal" <?php selected( $s['layout'], 'modal' ); ?>><?php esc_html_e( 'По центру (модалка)', 'soft-cookie-consent' ); ?></option>
									<option value="corner" <?php selected( $s['layout'], 'corner' ); ?>><?php esc_html_e( 'Угол', 'soft-cookie-consent' ); ?></option>
								</select>
							</td>
						</tr>
						<tr class="ecc-corner-side-row" <?php echo ( $s['layout'] ?? '' ) === 'corner' ? '' : 'hidden'; ?>>
							<th><?php esc_html_e( 'Угол', 'soft-cookie-consent' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[corner_side]" id="ecc_corner_side">
									<option value="left" <?php selected( $s['corner_side'] ?? 'right', 'left' ); ?>><?php esc_html_e( 'Левый нижний', 'soft-cookie-consent' ); ?></option>
									<option value="right" <?php selected( $s['corner_side'] ?? 'right', 'right' ); ?>><?php esc_html_e( 'Правый нижний', 'soft-cookie-consent' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Баннер выезжает с выбранной стороны. Центр — снизу вверх, бар снизу — тоже снизу вверх.', 'soft-cookie-consent' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Задержка появления', 'soft-cookie-consent' ); ?></th>
							<td>
								<input type="number"
									name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[enter_delay]"
									value="<?php echo esc_attr( (string) ( $s['enter_delay'] ?? 450 ) ); ?>"
									min="0"
									max="5000"
									step="50"
									class="small-text">
								<span><?php esc_html_e( 'мс', 'soft-cookie-consent' ); ?></span>
								<p class="description"><?php esc_html_e( 'Пауза перед анимацией появления баннера (0–5000). По умолчанию 450.', 'soft-cookie-consent' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Кнопки', 'soft-cookie-consent' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[show_reject]" value="1" <?php checked( $s['show_reject'] ); ?>> <?php esc_html_e( 'Показывать «Только необходимые» / Reject', 'soft-cookie-consent' ); ?></label><br>
								<label><input type="checkbox" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[show_settings]" value="1" <?php checked( $s['show_settings'] ); ?>> <?php esc_html_e( 'Показывать «Настроить»', 'soft-cookie-consent' ); ?></label><br>
								<label><input type="checkbox" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[show_reopen]" value="1" <?php checked( $s['show_reopen'] ); ?>> <?php esc_html_e( 'Кнопка повторного открытия настроек', 'soft-cookie-consent' ); ?></label>
								<p class="description"><?php esc_html_e( 'Также доступен шорткод [cookie_settings] и класс .ecc-open-settings.', 'soft-cookie-consent' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Категории', 'soft-cookie-consent' ); ?></th>
							<td>
								<?php foreach ( ECC_Helpers::categories() as $cat => $meta ) : ?>
									<label style="display:block;margin:0 0 6px;">
										<input type="checkbox"
											name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[categories_enabled][<?php echo esc_attr( $cat ); ?>]"
											value="1"
											<?php checked( ! empty( $s['categories_enabled'][ $cat ] ) ); ?>
											<?php disabled( ! empty( $meta['essential'] ) ); ?>>
										<code><?php echo esc_html( $cat ); ?></code>
										<?php if ( ! empty( $meta['essential'] ) ) : ?>
											— <?php esc_html_e( 'всегда включена', 'soft-cookie-consent' ); ?>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Политика (RU)', 'soft-cookie-consent' ); ?></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'              => ECC_Helpers::OPTION . '[privacy_page]',
										'id'                => 'ecc_privacy_page',
										'selected'          => (int) ( $s['privacy_page'] ?? 0 ),
										'show_option_none'  => __( '— Не выбрано —', 'soft-cookie-consent' ),
										'option_none_value' => '0',
									)
								);
								?>
								<p class="description"><?php esc_html_e( 'Страница политики конфиденциальности / cookies.', 'soft-cookie-consent' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Политика (EN)', 'soft-cookie-consent' ); ?></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'              => ECC_Helpers::OPTION . '[privacy_page_en]',
										'id'                => 'ecc_privacy_page_en',
										'selected'          => (int) ( $s['privacy_page_en'] ?? 0 ),
										'show_option_none'  => __( '— Как у RU —', 'soft-cookie-consent' ),
										'option_none_value' => '0',
									)
								);
								?>
								<p class="description"><?php esc_html_e( 'Если не выбрано — используется страница для RU.', 'soft-cookie-consent' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Срок согласия', 'soft-cookie-consent' ); ?></th>
							<td>
								<input type="number" class="small-text" min="1" max="730" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[cookie_days]" value="<?php echo esc_attr( (string) $s['cookie_days'] ); ?>">
								<?php esc_html_e( 'дней', 'soft-cookie-consent' ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Версия политики', 'soft-cookie-consent' ); ?></th>
							<td>
								<input type="text" class="regular-text" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[consent_version]" value="<?php echo esc_attr( $s['consent_version'] ); ?>">
								<p class="description"><?php esc_html_e( 'Увеличьте при изменении политики — посетителям снова покажется баннер.', 'soft-cookie-consent' ); ?></p>
							</td>
						</tr>
					</table>

				<?php elseif ( $tab === 'texts' ) : ?>
					<?php
					$text_labels = array(
						'banner_title'         => __( 'Заголовок баннера', 'soft-cookie-consent' ),
						'banner_text'          => __( 'Текст баннера', 'soft-cookie-consent' ),
						'btn_accept'           => __( 'Кнопка: принять все', 'soft-cookie-consent' ),
						'btn_reject'           => __( 'Кнопка: только необходимые', 'soft-cookie-consent' ),
						'btn_settings'         => __( 'Кнопка: настроить', 'soft-cookie-consent' ),
						'btn_save'             => __( 'Кнопка: сохранить выбор', 'soft-cookie-consent' ),
						'btn_close'            => __( 'Кнопка: закрыть', 'soft-cookie-consent' ),
						'modal_title'          => __( 'Заголовок модалки настроек', 'soft-cookie-consent' ),
						'modal_intro'          => __( 'Текст модалки', 'soft-cookie-consent' ),
						'privacy_label'        => __( 'Текст ссылки на политику', 'soft-cookie-consent' ),
						'cat_necessary'        => __( 'Категория: необходимые — название', 'soft-cookie-consent' ),
						'cat_necessary_desc'   => __( 'Категория: необходимые — описание', 'soft-cookie-consent' ),
						'cat_preferences'      => __( 'Категория: функциональные — название', 'soft-cookie-consent' ),
						'cat_preferences_desc' => __( 'Категория: функциональные — описание', 'soft-cookie-consent' ),
						'cat_analytics'        => __( 'Категория: аналитика — название', 'soft-cookie-consent' ),
						'cat_analytics_desc'   => __( 'Категория: аналитика — описание', 'soft-cookie-consent' ),
						'cat_marketing'        => __( 'Категория: маркетинг — название', 'soft-cookie-consent' ),
						'cat_marketing_desc'   => __( 'Категория: маркетинг — описание', 'soft-cookie-consent' ),
						'reopen_label'         => __( 'Кнопка / ссылка повторного открытия', 'soft-cookie-consent' ),
						'consent_revision_note'=> __( 'Сообщение при смене версии политики', 'soft-cookie-consent' ),
					);
					$long = array( 'banner_text', 'modal_intro', 'cat_necessary_desc', 'cat_preferences_desc', 'cat_analytics_desc', 'cat_marketing_desc', 'consent_revision_note' );
					?>
					<div class="ecc-lang-tabs" data-ecc-lang-tabs>
						<button type="button" class="button button-primary" data-ecc-lang="ru">RU</button>
						<button type="button" class="button" data-ecc-lang="en">EN</button>
					</div>
					<?php foreach ( array( 'ru', 'en' ) as $lang ) : ?>
						<div class="ecc-lang-panel" data-ecc-lang-panel="<?php echo esc_attr( $lang ); ?>" <?php echo $lang === 'ru' ? '' : 'hidden'; ?>>
							<h2><?php echo esc_html( ECC_Helpers::languages()[ $lang ] ); ?></h2>
							<table class="form-table" role="presentation">
								<?php foreach ( $text_labels as $key => $label ) : ?>
									<tr>
										<th><label for="ecc_<?php echo esc_attr( $lang . '_' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
										<td>
											<?php if ( in_array( $key, $long, true ) ) : ?>
												<textarea class="large-text" rows="3" id="ecc_<?php echo esc_attr( $lang . '_' . $key ); ?>"
													name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[texts][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $s['texts'][ $lang ][ $key ] ?? '' ); ?></textarea>
											<?php else : ?>
												<input type="text" class="large-text" id="ecc_<?php echo esc_attr( $lang . '_' . $key ); ?>"
													name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[texts][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $key ); ?>]"
													value="<?php echo esc_attr( $s['texts'][ $lang ][ $key ] ?? '' ); ?>">
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>
					<?php endforeach; ?>

				<?php elseif ( $tab === 'styles' ) : ?>
					<?php
					$style_groups = array(
						__( 'Баннер', 'soft-cookie-consent' ) => array(
							'overlay_bg'       => array( 'color', __( 'Оверлей', 'soft-cookie-consent' ) ),
							'banner_bg'        => array( 'color', __( 'Фон', 'soft-cookie-consent' ) ),
							'banner_text'      => array( 'color', __( 'Текст', 'soft-cookie-consent' ) ),
							'banner_muted'     => array( 'color', __( 'Приглушённый текст', 'soft-cookie-consent' ) ),
							'banner_border'    => array( 'color', __( 'Обводка', 'soft-cookie-consent' ) ),
							'banner_radius'    => array( 'text', __( 'Радиус', 'soft-cookie-consent' ) ),
							'banner_shadow'    => array( 'text', __( 'Тень', 'soft-cookie-consent' ) ),
							'banner_max_width' => array( 'text', __( 'Макс. ширина', 'soft-cookie-consent' ) ),
							'banner_padding'   => array( 'text', __( 'Padding', 'soft-cookie-consent' ) ),
							'font_family'      => array( 'text', __( 'Шрифт', 'soft-cookie-consent' ) ),
							'font_size'        => array( 'text', __( 'Размер текста', 'soft-cookie-consent' ) ),
							'title_size'       => array( 'text', __( 'Размер заголовка', 'soft-cookie-consent' ) ),
							'title_weight'     => array( 'text', __( 'Насыщенность заголовка', 'soft-cookie-consent' ) ),
							'link_color'       => array( 'color', __( 'Цвет ссылок', 'soft-cookie-consent' ) ),
							'z_index'          => array( 'text', __( 'z-index', 'soft-cookie-consent' ) ),
						),
						__( 'Кнопки', 'soft-cookie-consent' ) => array(
							'btn_radius'        => array( 'text', __( 'Радиус кнопок', 'soft-cookie-consent' ) ),
							'btn_padding_y'     => array( 'text', __( 'Отступ сверху/снизу', 'soft-cookie-consent' ) ),
							'btn_padding_x'     => array( 'text', __( 'Отступ слева/справа', 'soft-cookie-consent' ) ),
							'btn_font_size'     => array( 'text', __( 'Размер шрифта', 'soft-cookie-consent' ) ),
							'btn_font_weight'   => array( 'text', __( 'Насыщенность шрифта', 'soft-cookie-consent' ) ),
							'btn_accept_bg'     => array( 'color', __( '«Принять все» — фон', 'soft-cookie-consent' ) ),
							'btn_accept_text'   => array( 'color', __( '«Принять все» — текст', 'soft-cookie-consent' ) ),
							'btn_accept_hover'  => array( 'color', __( '«Принять все» — при наведении', 'soft-cookie-consent' ) ),
							'btn_reject_bg'     => array( 'color', __( '«Только необходимые» — фон', 'soft-cookie-consent' ) ),
							'btn_reject_text'   => array( 'color', __( '«Только необходимые» — текст', 'soft-cookie-consent' ) ),
							'btn_reject_border' => array( 'color', __( '«Только необходимые» — обводка', 'soft-cookie-consent' ) ),
							'btn_settings_bg'   => array( 'color', __( '«Настроить» — фон', 'soft-cookie-consent' ) ),
							'btn_settings_text' => array( 'color', __( '«Настроить» — текст', 'soft-cookie-consent' ) ),
						),
						__( 'Тумблеры и кнопка повторного открытия', 'soft-cookie-consent' ) => array(
							'toggle_on'     => array( 'color', __( 'Тумблер включён', 'soft-cookie-consent' ) ),
							'toggle_off'    => array( 'color', __( 'Тумблер выключен', 'soft-cookie-consent' ) ),
							'reopen_bg'     => array( 'color', __( 'Кнопка «Настройки cookies» — фон', 'soft-cookie-consent' ) ),
							'reopen_text'   => array( 'color', __( 'Кнопка «Настройки cookies» — текст', 'soft-cookie-consent' ) ),
							'reopen_radius' => array( 'text', __( 'Кнопка «Настройки cookies» — радиус', 'soft-cookie-consent' ) ),
						),
					);
					?>
					<?php foreach ( $style_groups as $group_label => $fields ) : ?>
						<h2><?php echo esc_html( $group_label ); ?></h2>
						<table class="form-table" role="presentation">
							<?php foreach ( $fields as $key => $meta ) : ?>
								<tr>
									<th><?php echo esc_html( $meta[1] ); ?></th>
									<td>
										<input
											type="text"
											class="<?php echo $meta[0] === 'color' ? 'ecc-color' : 'regular-text'; ?>"
											name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[styles][<?php echo esc_attr( $key ); ?>]"
											value="<?php echo esc_attr( $s['styles'][ $key ] ?? '' ); ?>"
											data-default-color="<?php echo esc_attr( ECC_Helpers::default_styles()[ $key ] ?? '' ); ?>">
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php endforeach; ?>

				<?php else : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Блокировка скриптов', 'soft-cookie-consent' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[block_scripts]" value="1" <?php checked( $s['block_scripts'] ); ?>>
									<?php esc_html_e( 'Не запускать необязательные скрипты до согласия', 'soft-cookie-consent' ); ?></label>
								<p class="description">
									<?php esc_html_e( 'Включает механизм отложенного запуска. Скрипты с type="text/plain" data-ecc-category="…" или через фильтр ecc_script_category стартуют только после согласия.', 'soft-cookie-consent' ); ?>
								</p>
								<label style="display:block;margin-top:10px;">
									<input type="checkbox" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[auto_block_known]" value="1" <?php checked( ! empty( $s['auto_block_known'] ) ); ?> <?php disabled( empty( $s['block_scripts'] ) ); ?>>
									<?php esc_html_e( 'Автоматически блокировать известные счётчики', 'soft-cookie-consent' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Метрика, GA/GTM, Facebook Pixel и др. — в том числе из HTML темы. Для этого страница кратко обрабатывается перед отдачей (обычно незаметно; на очень тяжёлых страницах можно выключить и помечать скрипты вручную). При включённом Consent Mode GTM/gtag не блокируются.', 'soft-cookie-consent' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Google Consent Mode v2', 'soft-cookie-consent' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[gtm_consent_mode]" value="1" <?php checked( $s['gtm_consent_mode'] ); ?>>
									<?php esc_html_e( 'Обновлять gtag/dataLayer consent при выборе', 'soft-cookie-consent' ); ?></label>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Интеграция', 'soft-cookie-consent' ); ?></th>
							<td>
								<ul class="ul-disc">
									<li><code>[cookie_settings]</code> — <?php esc_html_e( 'ссылка открыть настройки', 'soft-cookie-consent' ); ?></li>
									<li><code>.ecc-open-settings</code> — <?php esc_html_e( 'класс на любой кнопке/ссылке', 'soft-cookie-consent' ); ?></li>
									<li><code>window.ECC.openSettings()</code> / <code>window.ECC.getConsent()</code></li>
									<li><code>data-ecc-category="analytics"</code> — <?php esc_html_e( 'на &lt;script&gt; для отложенного запуска', 'soft-cookie-consent' ); ?></li>
								</ul>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<?php
				// Preserve other tabs.
				self::preserve_other_tabs( $tab, $s );
				submit_button( __( 'Сохранить', 'soft-cookie-consent' ) );
				?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param string              $tab
	 * @param array<string,mixed> $s
	 */
	public static function preserve_other_tabs_public( $tab, $s ) {
		self::preserve_other_tabs( $tab, $s );
	}

	/**
	 * @param string              $tab
	 * @param array<string,mixed> $s
	 */
	private static function preserve_other_tabs( $tab, $s ) {
		$opt = ECC_Helpers::OPTION;
		$map = array(
			'general'  => array( 'enabled', 'language', 'layout', 'corner_side', 'enter_delay', 'show_reject', 'show_settings', 'show_reopen', 'categories_enabled', 'privacy_page', 'privacy_page_en', 'privacy_url', 'privacy_url_en', 'cookie_days', 'consent_version' ),
			'texts'    => array( 'texts' ),
			'styles'   => array( 'styles' ),
			'advanced' => array( 'block_scripts', 'auto_block_known', 'gtm_consent_mode' ),
			'log'      => array( 'log_consent', 'log_retention_days' ),
		);
		$visible = $map[ $tab ] ?? array();

		foreach ( $s as $key => $val ) {
			if ( in_array( $key, $visible, true ) ) {
				continue;
			}
			if ( $key === 'texts' && is_array( $val ) ) {
				foreach ( $val as $lang => $pack ) {
					if ( ! is_array( $pack ) ) {
						continue;
					}
					foreach ( $pack as $tk => $tv ) {
						printf(
							'<input type="hidden" name="%s[texts][%s][%s]" value="%s" />' . "\n",
							esc_attr( $opt ),
							esc_attr( (string) $lang ),
							esc_attr( (string) $tk ),
							esc_attr( (string) $tv )
						);
					}
				}
				continue;
			}
			if ( $key === 'styles' && is_array( $val ) ) {
				foreach ( $val as $sk => $sv ) {
					printf(
						'<input type="hidden" name="%s[styles][%s]" value="%s" />' . "\n",
						esc_attr( $opt ),
						esc_attr( (string) $sk ),
						esc_attr( (string) $sv )
					);
				}
				continue;
			}
			if ( $key === 'categories_enabled' && is_array( $val ) ) {
				foreach ( $val as $ck => $cv ) {
					if ( $cv ) {
						printf(
							'<input type="hidden" name="%s[categories_enabled][%s]" value="1" />' . "\n",
							esc_attr( $opt ),
							esc_attr( (string) $ck )
						);
					}
				}
				continue;
			}
			if ( is_scalar( $val ) ) {
				printf(
					'<input type="hidden" name="%s[%s]" value="%s" />' . "\n",
					esc_attr( $opt ),
					esc_attr( (string) $key ),
					esc_attr( (string) $val )
				);
			}
		}
	}
}
