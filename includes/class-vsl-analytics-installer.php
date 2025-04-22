<?php
/**
 * Classe de instalação para o sistema de analytics do VSL Player
 *
 * Responsável por criar a tabela wp_vsl_sessions no ativação do plugin
 *
 * @package    VSL_Player
 * @subpackage VSL_Player/includes
 * @author     Roberto Dutra
 * @since      1.4.0
 */

class VSL_Analytics_Installer {

	/**
	 * Executa a instalação ou atualização do sistema de analytics
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function install() {
		self::create_tables();
	}

	/**
	 * Cria a tabela wp_vsl_sessions no banco de dados
	 *
	 * @since 1.4.0
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'vsl_sessions';

		// Verifica se a tabela já existe para evitar erros
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			$sql = "CREATE TABLE $table_name (
				session_id        CHAR(36)    NOT NULL,
				video_post_id     BIGINT      UNSIGNED NOT NULL,
				youtube_video_id  VARCHAR(20) NOT NULL,
				first_impression  DATETIME(3) NOT NULL,
				first_play        DATETIME(3) NULL,
				max_progress_sec  SMALLINT    DEFAULT 0,
				completed         TINYINT(1)  DEFAULT 0,
				cta_clicks        SMALLINT    DEFAULT 0,
				device_type       VARCHAR(10) NULL,
				page_url          TEXT        NULL,
				utm_source        VARCHAR(100) NULL,
				utm_medium        VARCHAR(100) NULL,
				utm_campaign      VARCHAR(100) NULL,
				PRIMARY KEY (session_id, video_post_id),
				KEY video_date (video_post_id, first_impression)
			) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}
}
