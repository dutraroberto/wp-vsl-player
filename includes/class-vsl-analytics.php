<?php
/**
 * Classe principal do sistema de analytics do VSL Player
 *
 * Gerencia o carregamento de scripts e integração com o restante do plugin
 *
 * @package    VSL_Player
 * @subpackage VSL_Player/includes
 * @author     Roberto Dutra
 * @since      1.4.0
 */

class VSL_Analytics {

	/**
	 * Instância da classe REST API
	 *
	 * @since  1.4.0
	 * @access private
	 * @var    VSL_Analytics_REST $rest_api Instância da classe REST API
	 */
	private $rest_api;

	/**
	 * Inicializa a classe e define suas propriedades
	 *
	 * @since 1.4.0
	 */
	public function __construct() {
		$this->rest_api = new VSL_Analytics_REST();
	}

	/**
	 * Registra os hooks necessários para o funcionamento do sistema
	 *
	 * @since  1.4.0
	 * @return void
	 */
	public function init() {
		// Inicializa a API REST
		$this->rest_api->init();

		// Registra o script de analytics
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		
		// Disponibiliza globalmente a instância do analytics
		global $vsl_analytics;
		$vsl_analytics = $this;
		
		error_log( '[VSL Analytics] Sistema inicializado' );
	}

	/**
	 * Registra e carrega os scripts de analytics quando necessário
	 *
	 * @since  1.4.0
	 * @return void
	 */
	public function register_scripts() {
		// Registra o script, mas não carrega imediatamente
		wp_register_script(
			'vsl-analytics',
			VSL_PLAYER_URL . 'public/js/vsl-analytics.js',
			array( 'wp-api-fetch' ),
			VSL_PLAYER_VERSION,
			true
		);
		
		error_log( '[VSL Analytics] Script registrado: ' . VSL_PLAYER_URL . 'public/js/vsl-analytics.js' );
	}

	/**
	 * Enfileira o script de analytics quando um player é renderizado
	 *
	 * @since  1.4.0
	 * @param  int    $post_id ID do post onde o player está sendo exibido
	 * @param  string $youtube_id ID do vídeo do YouTube
	 * @return void
	 */
	public function enqueue_analytics( $post_id, $youtube_id ) {
		// Log para depuração
		error_log( '[VSL Analytics] Ativando analytics para post_id: ' . $post_id . ', youtube_id: ' . $youtube_id );
		
		// Verifica parâmetros válidos
		if ( empty( $post_id ) || empty( $youtube_id ) ) {
			error_log( '[VSL Analytics] ERRO: post_id ou youtube_id vazios' );
			return;
		}
		
		// Apenas carrega se ainda não tiver sido carregado
		if ( ! wp_script_is( 'vsl-analytics', 'enqueued' ) ) {
			// Verifica se o script está registrado
			if ( ! wp_script_is( 'vsl-analytics', 'registered' ) ) {
				error_log( '[VSL Analytics] ERRO: Script não está registrado. Chamando register_scripts()' );
				$this->register_scripts();
			}
			
			// Enfileira o script
			wp_enqueue_script( 'vsl-analytics' );
			
			// Adiciona os dados necessários para o script
			$analytics_data = array(
				'nonce'    => wp_create_nonce( 'vsl_analytics_collect' ),
				'post_id'  => $post_id,
				'yt_id'    => $youtube_id,
				'device'   => $this->detect_device(),
				'debug'    => true,
			);
			
			wp_localize_script(
				'vsl-analytics',
				'VSL_ANALYTICS',
				$analytics_data
			);
			
			error_log( '[VSL Analytics] Script enfileirado com sucesso. Dados: ' . wp_json_encode( $analytics_data ) );
		} else {
			error_log( '[VSL Analytics] Script já estava enfileirado' );
		}
	}

	/**
	 * Detecta o tipo de dispositivo do usuário
	 *
	 * @since  1.4.0
	 * @return string Tipo de dispositivo ('desktop', 'tablet' ou 'mobile')
	 */
	private function detect_device() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		
		// Detecção simples de dispositivo
		if ( preg_match( '/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $user_agent ) ) {
			return 'tablet';
		}
		
		if ( preg_match( '/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $user_agent ) ) {
			return 'mobile';
		}
		
		return 'desktop';
	}
}
