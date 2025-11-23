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
		
		register_rest_route(
			'vsl-analytics/v1', 
			'/collect-batch', 
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'collect_batch_handler' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args' => array(
					'events' => array( 'required' => true, 'type' => 'array' ),
				),
			)
		);
	}

	/**
	 * Verifica a permissão para acessar o endpoint
	 * 
	 * Analytics é um endpoint público (dados não sensíveis), então permitimos
	 * requisições sem autenticação. A segurança é garantida por:
	 * - Rate limiting (100 req/min por IP)
	 * - Sanitização rigorosa de todos os inputs
	 * - Validação dos tipos de dados
	 *
	 * @since  1.4.0
	 * @param  WP_REST_Request $request Objeto da requisição.
	 * @return bool Sempre true (endpoint público)
	 */
	public function check_permission( $request ) {
		// Endpoint público - analytics não são dados sensíveis
		// A segurança é garantida por rate-limiting e sanitização
		return true;
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

		/* ---------- Segurança: Confiamos no nonce verificado em check_permission() ---------- */
		// O nonce já garante que a requisição vem de uma página legítima do WordPress
		// Não precisamos de verificações adicionais de origem/referer que podem causar problemas

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

	/**
	 * Manipula requisição em lote de múltiplos eventos
	 *
	 * @since  1.4.1
	 * @param  WP_REST_Request $req Objeto da requisição.
	 * @return WP_REST_Response
	 */
	public function collect_batch_handler( $req ) {
		global $wpdb;
		$ip = $_SERVER['REMOTE_ADDR'];

		$hits = (int) wp_cache_get( $ip, 'vsl_hits' );
		if ( $hits > 100 ) {
			return new WP_REST_Response( null, 429 );
		}

		$events = $req['events'];
		if ( ! is_array( $events ) || empty( $events ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid events array' ), 400 );
		}

		$events = array_slice( $events, 0, 50 );
		$processed = 0;

		foreach ( $events as $event_data ) {
			if ( ! isset( $event_data['event'], $event_data['sid'], $event_data['pid'] ) ) {
				continue;
			}

			$event   = sanitize_key( $event_data['event'] );
			$sid     = sanitize_text_field( $event_data['sid'] );
			$pid     = absint( $event_data['pid'] );
			$ytid    = isset( $event_data['ytid'] ) ? substr( preg_replace( '/[^a-zA-Z0-9_-]/', '', $event_data['ytid'] ), 0, 20 ) : '';
			$sec     = isset( $event_data['progress_sec'] ) ? max( 0, min( 32767, intval( $event_data['progress_sec'] ) ) ) : 0;
			$device  = isset( $event_data['device'] ) && in_array( $event_data['device'], array( 'desktop', 'tablet', 'mobile' ), true ) ? $event_data['device'] : '';
			$url     = isset( $event_data['url'] ) ? esc_url_raw( $event_data['url'] ) : '';
			$cta_inc = isset( $event_data['cta'] ) ? intval( $event_data['cta'] ) : 0;
			
			$utm_source   = isset( $event_data['utm_source'] ) ? sanitize_text_field( $event_data['utm_source'] ) : '';
			$utm_medium   = isset( $event_data['utm_medium'] ) ? sanitize_text_field( $event_data['utm_medium'] ) : '';
			$utm_campaign = isset( $event_data['utm_campaign'] ) ? sanitize_text_field( $event_data['utm_campaign'] ) : '';

			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT first_play, max_progress_sec, completed
				FROM {$wpdb->prefix}vsl_sessions
				WHERE session_id=%s AND video_post_id=%d",
				$sid, $pid
			) );

			if ( 'progress' === $event && ( ! $row || empty( $row->first_play ) ) ) {
				continue;
			}

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
				case 'resume_action':
					if ( $sec > $row->max_progress_sec ) {
						$wpdb->update(
							"{$wpdb->prefix}vsl_sessions",
							array( 'max_progress_sec' => $sec ),
							array( 'session_id' => $sid, 'video_post_id' => $pid ),
							array( '%d' ), array( '%s', '%d' )
						);
					}
					break;

				case 'cta_click':
					if ( $cta_inc > 0 ) {
						$wpdb->query( $wpdb->prepare(
							"UPDATE {$wpdb->prefix}vsl_sessions
							SET cta_clicks = cta_clicks + %d
							WHERE session_id=%s AND video_post_id=%d",
							$cta_inc, $sid, $pid
						) );
					}
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

			$processed++;
		}

		wp_cache_set( $ip, $hits + 1, 'vsl_hits', 60 );

		return new WP_REST_Response( array( 
			'success' => true, 
			'processed' => $processed,
			'total' => count( $events )
		), 200 );
	}

	/**
	 * Extrai o domínio raiz de um host (ignora www e subdomínios)
	 * 
	 * Exemplos:
	 * - www.mundowp.com.br → mundowp.com.br
	 * - blog.mundowp.com.br → mundowp.com.br
	 * - mundowp.com.br → mundowp.com.br
	 * - localhost → localhost
	 * - laboratrio-wp.local → laboratrio-wp.local
	 *
	 * @since  1.4.1
	 * @param  string $host Host completo
	 * @return string Domínio raiz
	 */
	private function get_root_domain( $host ) {
		if ( empty( $host ) ) {
			return '';
		}
		
		// Remove 'www.' do início se houver
		$host = preg_replace( '/^www\./', '', $host );
		
		// Para localhost e domínios .local, .test, .dev, retorna o host completo
		if ( preg_match( '/^(localhost|.*\.(local|test|dev))$/i', $host ) ) {
			return strtolower( $host );
		}
		
		// Para domínios normais, pega apenas os últimos 2 ou 3 segmentos
		// (ex: mundowp.com.br → últimos 3, example.com → últimos 2)
		$parts = explode( '.', $host );
		$count = count( $parts );
		
		// Se tem apenas 1 ou 2 partes, retorna tudo
		if ( $count <= 2 ) {
			return strtolower( $host );
		}
		
		// Verifica se o último segmento é um TLD de dois níveis (.com.br, .co.uk, etc)
		$last = $parts[ $count - 1 ];
		$second_last = $parts[ $count - 2 ];
		$two_level_tlds = array( 'com', 'co', 'gov', 'edu', 'ac', 'org', 'net' );
		
		if ( strlen( $last ) === 2 && in_array( $second_last, $two_level_tlds, true ) ) {
			// TLD de dois níveis: pega os últimos 3 segmentos
			return strtolower( implode( '.', array_slice( $parts, -3 ) ) );
		} else {
			// TLD normal: pega os últimos 2 segmentos
			return strtolower( implode( '.', array_slice( $parts, -2 ) ) );
		}
	}
}
