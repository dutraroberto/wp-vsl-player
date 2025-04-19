<?php
/**
 * Elementor Preview Loader para WP VSL Player
 *
 * Carrega os estilos e scripts necessários no editor do Elementor
 * para garantir uma visualização consistente entre editor e frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VSL_Player_Elementor_Preview {
    
    /**
     * Inicializa a classe
     */
    public function __construct() {
        // Enqueue estilos e scripts apenas no preview/editor do Elementor
        add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview_styles' ) );
        add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor_styles' ) );
        add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
    }
    
    /**
     * Enqueue estilos para o preview do Elementor
     */
    public function enqueue_preview_styles() {
        // Carregar os estilos do VSL Player
        wp_enqueue_style(
            'vsl-player-youtube',
            VSL_PLAYER_URL . 'public/css/vsl-player-youtube.css',
            array(),
            VSL_PLAYER_VERSION
        );
        
        wp_enqueue_style(
            'vsl-player-loading',
            VSL_PLAYER_URL . 'public/css/vsl-player-loading.css',
            array(),
            VSL_PLAYER_VERSION
        );
        
        wp_enqueue_style(
            'vsl-player-progress-bar',
            VSL_PLAYER_URL . 'public/css/vsl-player-progress-bar.css',
            array('vsl-player-youtube'),
            VSL_PLAYER_VERSION
        );
        
        wp_enqueue_style(
            'vsl-player-resume',
            VSL_PLAYER_URL . 'public/css/vsl-player-resume.css',
            array(),
            VSL_PLAYER_VERSION
        );
        
        // CSS específico para o editor
        wp_add_inline_style('vsl-player-youtube', '
            /* Ajustes específicos para o editor do Elementor */
            .elementor-editor-active .vsl-player-container {
                min-height: 250px;
                aspect-ratio: 16/9;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: #000;
            }
            
            .elementor-editor-active .vsl-play-button-svg {
                opacity: 1 !important;
                visibility: visible !important;
                display: block !important;
                position: absolute !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
            }
            
            .elementor-editor-active .vsl-start-overlay {
                display: flex !important;
                opacity: 1 !important;
                visibility: visible !important;
                background-color: rgba(0, 0, 0, 0.4);
            }
        ');
    }
    
    /**
     * Enqueue estilos para o editor do Elementor
     */
    public function enqueue_editor_styles() {
        // Carregar os mesmos estilos do preview para consistência
        $this->enqueue_preview_styles();
    }
    
    /**
     * Enqueue scripts para o frontend do Elementor
     */
    public function enqueue_frontend_scripts() {
        // No frontend, garantimos que os scripts necessários sejam carregados
        if (is_singular() && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
            // Carregar os scripts apenas quando estamos no modo de preview
            wp_enqueue_script('youtube-iframe-api', 'https://www.youtube.com/iframe_api', array(), null, true);
            wp_enqueue_script('vsl-player-loading', VSL_PLAYER_URL . 'public/js/vsl-player-loading.js', array('jquery'), VSL_PLAYER_VERSION, true);
            wp_enqueue_script('vsl-player-youtube', VSL_PLAYER_URL . 'public/js/vsl-player-youtube.js', array('jquery', 'youtube-iframe-api', 'vsl-player-loading'), VSL_PLAYER_VERSION, true);
            wp_enqueue_script('vsl-player-progress-bar', VSL_PLAYER_URL . 'public/js/vsl-player-progress-bar.js', array('jquery', 'vsl-player-youtube'), VSL_PLAYER_VERSION, true);
            wp_enqueue_script('vsl-player-resume', VSL_PLAYER_URL . 'public/js/vsl-player-resume.js', array('jquery', 'vsl-player-youtube'), VSL_PLAYER_VERSION, true);
            
            // Precisamos pré-configurar os dados globais do player
            $vsl_players_data = $this->get_vsl_players_data();
            
            wp_localize_script('vsl-player-youtube', 'vslPlayerData', array(
                'plugin_url' => VSL_PLAYER_URL,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vsl_player_ajax_nonce'),
                'players' => $vsl_players_data,
            ));
        }
    }
    
    /**
     * Obter dados de todos os VSL Players
     * 
     * @return array Configurações de todos os players
     */
    private function get_vsl_players_data() {
        $players = array();
        
        // Query todos os players vsl_player
        $args = array(
            'post_type' => 'vsl_player',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );
        
        $player_posts = get_posts($args);
        
        foreach ($player_posts as $player) {
            $video_id = get_post_meta($player->ID, '_vsl_youtube_id', true);
            $fake_progress = get_post_meta($player->ID, '_vsl_fake_progress', true) == 'yes';
            
            $players[] = array(
                'id' => $player->ID,
                'video_id' => $video_id,
                'fake_progress' => $fake_progress,
            );
        }
        
        return $players;
    }
}

// Iniciar a classe
new VSL_Player_Elementor_Preview();
