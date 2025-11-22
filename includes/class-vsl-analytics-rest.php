<?php
/**
 * Classe responsável pelo endpoint REST API para coleta de dados de analytics
 *
 * Implementa o endpoint /vsl-analytics/v1/collect com todas as verificações
 * de segurança necessárias, incluindo nonce, verificação de origem e rate limiting.
 *
 * @package    VSL_Player
 * @subpackage VSL_Player/includes
 * @author     Roberto Dutra
 * @since      1.4.0
 */

class VSL_Analytics_REST {

	/**
	 * Inicializa o endpoint REST API
	 *
	 * @since  1.4.0
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registra as rotas da REST API
	 *
	 * @since  1.4.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'vsl-analytics/v1', 
			'/collect', 
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'collect_handler' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args' => array(
					'event' => array( 'required' => true ),
					'sid'   => array( 'required' => true ),
					'pid'   => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);
	}

	/**
	 * Verifica a permissão para acessar o endpoint através do nonce
	 *
	 * @since  1.4.0
	 * @param  WP_REST_Request $request Objeto da requisição.
	 * @return bool
	 */
	public function check_permission( $request ) {
		// Verificação de nonce reativada para maior segurança
		$nonce = $request->get_header('x-wp-nonce');
		
		// Log condicional apenas em modo debug
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log('[VSL Analytics REST] Verificando nonce: ' . $nonce);
		}
		
		// Verificar o nonce
		$valid = wp_verify_nonce( $nonce, 'vsl_analytics_collect' );
		
		if ( ! $valid && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log('[VSL Analytics REST] Nonce inválido ou ausente');
		}
		
		return $valid;
	}

	/**
	 * Manipula a requisição de coleta de dados
	 *
	 * Implementa todas as medidas de segurança e validação antes
	 * de processar e armazenar os dados recebidos.
	 *
	 * @since  1.4.0
	 * @param  WP_REST_Request $req Objeto da requisição.
	 * @return WP_REST_Response
	 */
	public function collect_handler( $req ) {
		global $wpdb;
		$ip = $_SERVER['REMOTE_ADDR']; // usado só p/ rate‑limit, não salvo

		/* ---------- Rate‑limit 100 req / IP / min ---------- */
		$hits = (int) wp_cache_get( $ip, 'vsl_hits' );
		if ( $hits > 100 ) {
			return new WP_REST_Response( null, 429 );
		}
		wp_cache_set( $ip, $hits + 1, 'vsl_hits', 60 );

		/* ---------- Referer / Origin check ---------- */
		$origin  = $req->get_header( 'origin' );
		$referer = $req->get_header( 'referer' );
		$site    = get_site_url();
		if ( $origin && 0 !== strpos( $origin, $site ) ) {
			return new WP_REST_Response( null, 403 );
		}
		if ( $referer && 0 !== strpos( $referer, $site ) ) {
			return new WP_REST_Response( null, 403 );
		}

		/* ---------- Sanitização ---------- */
		$event   = sanitize_key( $req['event'] );
		$sid     = sanitize_text_field( $req['sid'] );
		$pid     = absint( $req['pid'] );
		$ytid    = isset( $req['ytid'] ) ? substr( preg_replace( '/[^a-zA-Z0-9_-]/', '', $req['ytid'] ), 0, 20 ) : '';
		$sec     = isset( $req['progress_sec'] ) ? max( 0, min( 32767, intval( $req['progress_sec'] ) ) ) : 0;
		$device  = isset( $req['device'] ) && in_array( $req['device'], array( 'desktop', 'tablet', 'mobile' ), true ) ? $req['device'] : '';
		$url     = isset( $req['url'] ) ? esc_url_raw( $req['url'] ) : '';
		$cta_inc = isset( $req['cta'] ) ? intval( $req['cta'] ) : 0;
		
		$utm_source   = isset( $req['utm_source'] ) ? sanitize_text_field( $req['utm_source'] ) : '';
		$utm_medium   = isset( $req['utm_medium'] ) ? sanitize_text_field( $req['utm_medium'] ) : '';
		$utm_campaign = isset( $req['utm_campaign'] ) ? sanitize_text_field( $req['utm_campaign'] ) : '';

		/* ---------- Validação de sequência ---------- */
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT first_play, max_progress_sec, completed
			FROM {$wpdb->prefix}vsl_sessions
			WHERE session_id=%s AND video_post_id=%d",
			$sid, $pid
		) );

		if ( 'progress' === $event && ( ! $row || empty( $row->first_play ) ) ) {
			return new WP_REST_Response( null, 400 ); // progresso sem play
		}

		/* ---------- UPSERT seguro ---------- */
		switch ( $event ) {
			case 'impression':
				$wpdb->query( $wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->prefix}vsl_sessions
					(session_id, video_post_id, youtube_video_id, first_impression,
					device_type, page_url, utm_source, utm_medium, utm_campaign)
					VALUES (%s,%d,%s,NOW(3),%s,%s,%s,%s,%s)",
					$sid, $pid, $ytid, $device, $url,
					$utm_source, $utm_medium, $utm_campaign
				) );
				break;

			case 'play':
				$wpdb->update(
					"{$wpdb->prefix}vsl_sessions",
					array( 'first_play' => current_time( 'mysql', true ) ),
					array( 'session_id' => $sid, 'video_post_id' => $pid ),
					array( '%s' ), array( '%s', '%d' )
				);
				break;

			case 'progress':
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}vsl_sessions
					SET max_progress_sec = GREATEST(max_progress_sec, %d)
					WHERE session_id=%s AND video_post_id=%d",
					$sec, $sid, $pid
				) );
				break;

			case 'cta_click':
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}vsl_sessions
					SET cta_clicks = cta_clicks + 1
					WHERE session_id=%s AND video_post_id=%d",
					$sid, $pid
				) );
				break;

			case 'complete':
			case 'exit':
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}vsl_sessions
					SET completed = IF(%s='complete',1,completed),
						max_progress_sec = GREATEST(max_progress_sec, %d)
					WHERE session_id=%s AND video_post_id=%d",
					$event, $sec, $sid, $pid
				) );
				break;
		}

		return new WP_REST_Response( null, 204 );
	}
}
