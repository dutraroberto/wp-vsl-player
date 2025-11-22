<?php
/**
 * Classe responsável pelo arquivamento de dados antigos de analytics
 *
 * Implementa sistema de arquivamento automático de sessões antigas
 * para manter a performance do banco de dados
 *
 * @package    VSL_Player
 * @subpackage VSL_Player/includes
 * @author     Roberto Dutra
 * @since      1.4.1
 */

class VSL_Analytics_Archiver {

	/**
	 * Número de dias após os quais os dados devem ser arquivados
	 * 
	 * @var int
	 */
	private $archive_after_days = 90;

	/**
	 * Inicializa a classe e registra os hooks
	 */
	public function __construct() {
		// Registrar evento de cron para arquivamento automático
		add_action( 'vsl_archive_old_sessions', array( $this, 'archive_old_sessions' ) );
		
		// Registrar hook de ativação para agendar cron
		add_action( 'init', array( $this, 'schedule_archiving' ) );
	}

	/**
	 * Agenda o evento de cron para arquivamento automático
	 *
	 * @since 1.4.1
	 * @return void
	 */
	public function schedule_archiving() {
		if ( ! wp_next_scheduled( 'vsl_archive_old_sessions' ) ) {
			// Agendar para rodar uma vez por semana às 3h da manhã
			wp_schedule_event( strtotime( 'tomorrow 3:00am' ), 'weekly', 'vsl_archive_old_sessions' );
		}
	}

	/**
	 * Cria a tabela de arquivo se não existir
	 *
	 * @since 1.4.1
	 * @return bool True se criada com sucesso ou já existe
	 */
	private function create_archive_table() {
		global $wpdb;
		
		$archive_table = $wpdb->prefix . 'vsl_sessions_archive';
		$charset_collate = $wpdb->get_charset_collate();
		
		// Verificar se a tabela já existe
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$archive_table'" ) === $archive_table ) {
			return true;
		}
		
		// Criar tabela com mesma estrutura da tabela principal
		$sql = "CREATE TABLE $archive_table (
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
			archived_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (session_id, video_post_id),
			KEY video_date (video_post_id, first_impression),
			KEY archived_date (archived_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		
		return true;
	}

	/**
	 * Arquiva sessões antigas para manter a performance
	 *
	 * Move sessões mais antigas que $archive_after_days para a tabela de arquivo
	 *
	 * @since 1.4.1
	 * @return array Resultado do arquivamento com contadores
	 */
	public function archive_old_sessions() {
		global $wpdb;
		
		// Criar tabela de arquivo se não existir
		if ( ! $this->create_archive_table() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[VSL Archiver] Erro ao criar tabela de arquivo' );
			}
			return array(
				'success' => false,
				'message' => 'Erro ao criar tabela de arquivo'
			);
		}
		
		$sessions_table = $wpdb->prefix . 'vsl_sessions';
		$archive_table = $wpdb->prefix . 'vsl_sessions_archive';
		
		// Calcular data limite
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$this->archive_after_days} days" ) );
		
		// Iniciar transação
		$wpdb->query( 'START TRANSACTION' );
		
		try {
			// Copiar sessões antigas para a tabela de arquivo
			$copied = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$archive_table} 
					(session_id, video_post_id, youtube_video_id, first_impression, first_play, 
					max_progress_sec, completed, cta_clicks, device_type, page_url, 
					utm_source, utm_medium, utm_campaign)
					SELECT session_id, video_post_id, youtube_video_id, first_impression, first_play, 
					max_progress_sec, completed, cta_clicks, device_type, page_url, 
					utm_source, utm_medium, utm_campaign
					FROM {$sessions_table}
					WHERE first_impression < %s
					ON DUPLICATE KEY UPDATE session_id = session_id",
					$cutoff_date
				)
			);
			
			// Deletar sessões arquivadas da tabela principal
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$sessions_table} 
					WHERE first_impression < %s",
					$cutoff_date
				)
			);
			
			// Confirmar transação
			$wpdb->query( 'COMMIT' );
			
			// Log de sucesso
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "[VSL Archiver] Arquivamento concluído: {$deleted} sessões movidas" );
			}
			
			return array(
				'success' => true,
				'archived' => $deleted,
				'cutoff_date' => $cutoff_date
			);
			
		} catch ( Exception $e ) {
			// Reverter transação em caso de erro
			$wpdb->query( 'ROLLBACK' );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[VSL Archiver] Erro ao arquivar: ' . $e->getMessage() );
			}
			
			return array(
				'success' => false,
				'message' => $e->getMessage()
			);
		}
	}

	/**
	 * Obtém estatísticas do arquivamento
	 *
	 * @since 1.4.1
	 * @return array Estatísticas de arquivamento
	 */
	public function get_archive_stats() {
		global $wpdb;
		
		$sessions_table = $wpdb->prefix . 'vsl_sessions';
		$archive_table = $wpdb->prefix . 'vsl_sessions_archive';
		
		// Contar sessões ativas
		$active_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$sessions_table}" );
		
		// Contar sessões arquivadas
		$archived_count = 0;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$archive_table'" ) === $archive_table ) {
			$archived_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$archive_table}" );
		}
		
		// Calcular tamanho das tabelas
		$active_size = $wpdb->get_var( 
			$wpdb->prepare(
				"SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) 
				FROM information_schema.TABLES 
				WHERE table_schema = %s 
				AND table_name = %s",
				DB_NAME,
				$sessions_table
			)
		);
		
		$archived_size = 0;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$archive_table'" ) === $archive_table ) {
			$archived_size = $wpdb->get_var( 
				$wpdb->prepare(
					"SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) 
					FROM information_schema.TABLES 
					WHERE table_schema = %s 
					AND table_name = %s",
					DB_NAME,
					$archive_table
				)
			);
		}
		
		return array(
			'active_sessions' => intval( $active_count ),
			'archived_sessions' => intval( $archived_count ),
			'active_size_mb' => floatval( $active_size ),
			'archived_size_mb' => floatval( $archived_size ),
			'archive_after_days' => $this->archive_after_days,
			'next_archive' => wp_next_scheduled( 'vsl_archive_old_sessions' )
		);
	}

	/**
	 * Cancela o agendamento de arquivamento
	 *
	 * @since 1.4.1
	 * @return void
	 */
	public static function unschedule_archiving() {
		$timestamp = wp_next_scheduled( 'vsl_archive_old_sessions' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'vsl_archive_old_sessions' );
		}
	}
}
