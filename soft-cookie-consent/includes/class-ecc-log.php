<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consent audit log (date, IP, categories).
 */
class ECC_Log {

	const DB_VERSION = '1';
	const TABLE_SUFFIX = 'ecc_consent_log';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'wp_ajax_ecc_log_consent', array( __CLASS__, 'ajax_log' ) );
		add_action( 'wp_ajax_nopriv_ecc_log_consent', array( __CLASS__, 'ajax_log' ) );
		add_action( 'admin_post_ecc_purge_consent_log', array( __CLASS__, 'handle_purge' ) );
		add_action( 'admin_post_ecc_export_consent_log', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function maybe_install() {
		$ver = get_option( 'ecc_log_db_version', '' );
		if ( (string) $ver === self::DB_VERSION ) {
			return;
		}
		self::install();
	}

	public static function install() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			consent_version varchar(64) NOT NULL DEFAULT '',
			categories longtext NOT NULL,
			page_url text NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY ip (ip)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'ecc_log_db_version', self::DB_VERSION, false );
	}

	/**
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		/**
		 * Filter stored client IP (e.g. from Cloudflare / proxy headers).
		 *
		 * @param string $ip
		 */
		$ip = (string) apply_filters( 'ecc_consent_log_ip', $ip );

		if ( $ip !== '' && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return bool|int
	 */
	public static function insert( $payload ) {
		global $wpdb;

		$s = ECC_Helpers::settings();
		if ( empty( $s['log_consent'] ) ) {
			return false;
		}

		$categories = is_array( $payload['categories'] ?? null ) ? $payload['categories'] : array();
		$clean      = array();
		foreach ( ECC_Helpers::categories() as $id => $meta ) {
			if ( ! empty( $meta['essential'] ) ) {
				$clean[ $id ] = true;
			} else {
				$clean[ $id ] = ! empty( $categories[ $id ] );
			}
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ua = sanitize_text_field( substr( $ua, 0, 255 ) );

		$page = isset( $payload['page_url'] ) ? esc_url_raw( (string) $payload['page_url'] ) : '';
		if ( strlen( $page ) > 2000 ) {
			$page = substr( $page, 0, 2000 );
		}

		$version = sanitize_text_field( (string) ( $payload['v'] ?? $s['consent_version'] ?? '1' ) );
		$ip      = self::client_ip();

		// Soft dedupe: same IP + same categories within 60s.
		$recent = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . self::table() . " WHERE ip = %s AND created_at >= %s ORDER BY id DESC LIMIT 1",
				$ip,
				gmdate( 'Y-m-d H:i:s', time() - 60 )
			)
		);
		if ( $recent ) {
			$prev = $wpdb->get_var( $wpdb->prepare( 'SELECT categories FROM ' . self::table() . ' WHERE id = %d', (int) $recent ) );
			if ( $prev && $prev === wp_json_encode( $clean ) ) {
				return (int) $recent;
			}
		}

		$ok = $wpdb->insert(
			self::table(),
			array(
				'created_at'      => current_time( 'mysql', true ),
				'ip'              => $ip,
				'user_agent'      => $ua,
				'consent_version' => $version,
				'categories'      => wp_json_encode( $clean ),
				'page_url'        => $page,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $ok ) {
			self::maybe_purge_old( (int) ( $s['log_retention_days'] ?? 365 ) );
			return (int) $wpdb->insert_id;
		}
		return false;
	}

	/**
	 * @param int $days
	 */
	public static function maybe_purge_old( $days ) {
		$days = max( 0, (int) $days );
		if ( $days <= 0 ) {
			return;
		}
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', $cutoff ) );
	}

	public static function ajax_log() {
		$s = ECC_Helpers::settings();
		if ( empty( $s['enabled'] ) || empty( $s['log_consent'] ) ) {
			wp_send_json_error( array( 'message' => 'disabled' ), 403 );
		}

		check_ajax_referer( 'ecc_log_consent', 'nonce' );

		$raw = isset( $_POST['consent'] ) ? wp_unslash( $_POST['consent'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_string( $raw ) ) {
			$data = json_decode( $raw, true );
		} else {
			$data = null;
		}
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => 'bad_payload' ), 400 );
		}

		$page = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['page_url'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$data['page_url'] = $page;

		$id = self::insert( $data );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'insert_failed' ), 500 );
		}
		wp_send_json_success( array( 'id' => $id ) );
	}

	/**
	 * @param int $page
	 * @param int $per_page
	 * @return array{rows:array<int,object>,total:int}
	 */
	public static function query( $page = 1, $per_page = 50 ) {
		global $wpdb;
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 200, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = self::table();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$per_page,
				$offset
			)
		);

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	public static function handle_purge() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'soft-cookie-consent' ) );
		}
		check_admin_referer( 'ecc_purge_consent_log' );
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL
		wp_safe_redirect( admin_url( 'options-general.php?page=soft-cookie-consent&tab=log&purged=1' ) );
		exit;
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'soft-cookie-consent' ) );
		}
		check_admin_referer( 'ecc_export_consent_log' );

		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT 5000', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ecc-consent-log-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			exit;
		}
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $out, array( 'id', 'created_at_utc', 'ip', 'consent_version', 'categories', 'page_url', 'user_agent' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row['id'] ?? '',
					$row['created_at'] ?? '',
					$row['ip'] ?? '',
					$row['consent_version'] ?? '',
					$row['categories'] ?? '',
					$row['page_url'] ?? '',
					$row['user_agent'] ?? '',
				)
			);
		}
		fclose( $out );
		exit;
	}

	/**
	 * Admin UI for the log tab.
	 *
	 * @param array<string,mixed> $s
	 */
	public static function render_admin_tab( $s ) {
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data = self::query( $page, 50 );
		$pages = max( 1, (int) ceil( $data['total'] / 50 ) );

		if ( ! empty( $_GET['purged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Журнал очищен.', 'soft-cookie-consent' ) . '</p></div>';
		}
		?>
		<p class="description">
			<?php esc_html_e( 'Записи создаются при выборе согласия на сайте: дата/время (UTC), IP, версия политики, категории и URL страницы.', 'soft-cookie-consent' ); ?>
		</p>

		<form method="post" action="options.php" style="margin:16px 0;">
			<?php settings_fields( 'ecc_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Журналирование', 'soft-cookie-consent' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[log_consent]" value="1" <?php checked( ! empty( $s['log_consent'] ) ); ?>>
							<?php esc_html_e( 'Сохранять согласия в журнал', 'soft-cookie-consent' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Срок хранения', 'soft-cookie-consent' ); ?></th>
					<td>
						<input type="number" class="small-text" min="0" max="3650" name="<?php echo esc_attr( ECC_Helpers::OPTION ); ?>[log_retention_days]" value="<?php echo esc_attr( (string) ( $s['log_retention_days'] ?? 365 ) ); ?>">
						<?php esc_html_e( 'дней', 'soft-cookie-consent' ); ?>
						<p class="description"><?php esc_html_e( '0 — не удалять автоматически. IP относится к персональным данным: укажите хранение в политике конфиденциальности.', 'soft-cookie-consent' ); ?></p>
					</td>
				</tr>
			</table>
			<?php
			ECC_Settings::preserve_other_tabs_public( 'log', $s );
			submit_button( __( 'Сохранить настройки журнала', 'soft-cookie-consent' ) );
			?>
		</form>

		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ecc_export_consent_log' ), 'ecc_export_consent_log' ) ); ?>">
				<?php esc_html_e( 'Экспорт CSV', 'soft-cookie-consent' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ecc_purge_consent_log' ), 'ecc_purge_consent_log' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Очистить весь журнал?', 'soft-cookie-consent' ) ); ?>');">
				<?php esc_html_e( 'Очистить журнал', 'soft-cookie-consent' ); ?>
			</a>
			<span class="description" style="margin-left:8px;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of rows */
						__( 'Всего записей: %d', 'soft-cookie-consent' ),
						(int) $data['total']
					)
				);
				?>
			</span>
		</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Дата (UTC)', 'soft-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'IP', 'soft-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Версия', 'soft-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Категории', 'soft-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Страница', 'soft-cookie-consent' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $data['rows'] ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Пока нет записей.', 'soft-cookie-consent' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $data['rows'] as $row ) : ?>
						<?php
						$cats = json_decode( (string) $row->categories, true );
						$bits = array();
						if ( is_array( $cats ) ) {
							foreach ( $cats as $id => $on ) {
								if ( $on ) {
									$bits[] = $id;
								}
							}
						}
						?>
						<tr>
							<td><?php echo esc_html( (string) $row->created_at ); ?></td>
							<td><code><?php echo esc_html( (string) $row->ip ); ?></code></td>
							<td><?php echo esc_html( (string) $row->consent_version ); ?></td>
							<td><?php echo esc_html( implode( ', ', $bits ) ); ?></td>
							<td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
								<?php if ( $row->page_url ) : ?>
									<a href="<?php echo esc_url( (string) $row->page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $row->page_url ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( array( 'tab' => 'log', 'paged' => '%#%' ) ),
								'format'    => '',
								'current'   => $page,
								'total'     => $pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}
}
