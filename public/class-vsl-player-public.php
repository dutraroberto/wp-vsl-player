<?php
/**
 * The public-facing functionality of the plugin.
 */
class VSL_Player_Public {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Add actions for public-facing functionality
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX action for loading progress bar assets
        add_action('wp_ajax_vsl_load_progress_bar_assets', array($this, 'load_progress_bar_assets'));
        add_action('wp_ajax_nopriv_vsl_load_progress_bar_assets', array($this, 'load_progress_bar_assets'));
        
        // Add AJAX action for getting image URL
        add_action('wp_ajax_vsl_get_image_url', array($this, 'get_image_url_ajax'));
        add_action('wp_ajax_nopriv_vsl_get_image_url', array($this, 'get_image_url_ajax'));
        
        // Register shortcode
        add_shortcode('vsl_player', array($this, 'vsl_player_shortcode'));
        
        // Add preconnect for YouTube domains
        add_action('wp_head', array($this, 'add_youtube_preconnect'));
    }

    /**
     * Register and enqueue public-facing scripts and styles
     */
    public function enqueue_scripts() {
        global $post;

        // First, register the YouTube player assets (will only be loaded when needed by shortcode)
        wp_register_script(
            'youtube-iframe-api',
            'https://www.youtube.com/iframe_api',
            array(),
            null,
            true
        );
        
        // Register loading assets (must be loaded before YouTube player)
        wp_register_style(
            'vsl-player-loading',
            VSL_PLAYER_URL . 'public/css/vsl-player-loading.css',
            array(),
            VSL_PLAYER_VERSION
        );
        
        wp_register_script(
            'vsl-player-loading',
            VSL_PLAYER_URL . 'public/js/vsl-player-loading.js',
            array('jquery'),
            VSL_PLAYER_VERSION,
            true
        );
        
        wp_register_script(
            'vsl-player-youtube',
            VSL_PLAYER_URL . 'public/js/vsl-player-youtube.js',
            array('jquery', 'youtube-iframe-api', 'vsl-player-loading'),
            VSL_PLAYER_VERSION,
            true
        );
        
        wp_register_style(
            'vsl-player-youtube',
            VSL_PLAYER_URL . 'public/css/vsl-player-youtube.css',
            array(),
            VSL_PLAYER_VERSION
        );
        
        // Registrar os assets da barra de progresso falsa
        wp_register_script(
            'vsl-player-progress-bar',
            VSL_PLAYER_URL . 'public/js/vsl-player-progress-bar.js',
            array('jquery', 'vsl-player-youtube'),
            VSL_PLAYER_VERSION,
            true
        );
        
        wp_register_style(
            'vsl-player-progress-bar',
            VSL_PLAYER_URL . 'public/css/vsl-player-progress-bar.css',
            array('vsl-player-youtube'),
            VSL_PLAYER_VERSION
        );
        
        // Registrar o CSS e JS do recurso de continuar assistindo
        wp_register_style(
            'vsl-player-resume',
            VSL_PLAYER_URL . 'public/css/vsl-player-resume.css',
            array(),
            VSL_PLAYER_VERSION
        );
        
        wp_register_script(
            'vsl-player-resume',
            VSL_PLAYER_URL . 'public/js/vsl-player-resume.js',
            array('jquery', 'vsl-player-youtube'),
            VSL_PLAYER_VERSION,
            true
        );
        
        // Registrar o CSS e JS do recurso de revelar oferta
        wp_register_style(
            'vsl-player-offer-reveal',
            VSL_PLAYER_URL . 'public/css/vsl-player-offer-reveal.css',
            array(),
            VSL_PLAYER_VERSION
        );
        
        wp_register_script(
            'vsl-player-offer-reveal',
            VSL_PLAYER_URL . 'public/js/vsl-player-offer-reveal.js',
            array('jquery', 'vsl-player-youtube'),
            VSL_PLAYER_VERSION,
            true
        );
        
        // Registrar o CSS e JS do recurso de rastreamento de conversões
        wp_register_style(
            'vsl-player-conversions',
            VSL_PLAYER_URL . 'public/css/vsl-player-conversions.css',
            array(),
            VSL_PLAYER_VERSION
        );
        
        wp_register_script(
            'vsl-player-conversions',
            VSL_PLAYER_URL . 'public/js/vsl-player-conversions.js',
            array('jquery', 'vsl-player-youtube'),
            VSL_PLAYER_VERSION,
            true
        );
        
        // Collect all VSL Player data for the page
        $players_data = $this->get_vsl_players_data();
        
        // Localize script with player data
        if (!empty($players_data)) {
            wp_localize_script(
                'vsl-player-youtube',
                'vslPlayerData',
                array(
                    'plugin_url' => VSL_PLAYER_URL,
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('vsl_player_ajax_nonce'),
                    'rest_url' => rest_url(),
                    'players' => $players_data,
                    'is_mobile' => wp_is_mobile()
                )
            );
        }

        // Only proceed with reveal functionality if we're on a single page/post
        if (!is_singular() || empty($post)) {
            return;
        }

        $current_page_id = $post->ID;
        
        // Check if the current page is configured in any VSL Reveal post
        $reveals = $this->get_active_reveals_for_page($current_page_id);
        
        if (!empty($reveals)) {
            // Register and enqueue the main script
            wp_register_script(
                'vsl-reveal-js',
                VSL_PLAYER_URL . 'public/js/vsl-player-reveal.js',
                array('jquery'),
                VSL_PLAYER_VERSION,
                true
            );
            
            // Localize script with data about the reveals
            wp_localize_script(
                'vsl-reveal-js',
                'vslRevealData',
                array('reveals' => $reveals)
            );
            
            // Enqueue the script
            wp_enqueue_script('vsl-reveal-js');
            
            // Enqueue related styles if needed
            wp_enqueue_style(
                'vsl-reveal-css',
                VSL_PLAYER_URL . 'public/css/vsl-player-reveal.css',
                array(),
                VSL_PLAYER_VERSION
            );
        }
    }
    
    /**
     * VSL player shortcode callback
     * 
     * @param array $atts
     * @param string $content
     * @return string
     */
    public function vsl_player_shortcode($atts, $content = null) {
        // Extract attributes
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'vsl_player');
        
        // Get the VSL Player post
        $post_id = absint($atts['id']);
        
        if (!$post_id || get_post_type($post_id) !== 'vsl_player') {
            return '';
        }
        
        // Get VSL player data from post meta
        $youtube_url = get_post_meta($post_id, '_vsl_youtube_url', true);
        $player_color = get_post_meta($post_id, '_vsl_player_color', true) ?: '#617be5';
        $fake_progress = get_post_meta($post_id, '_vsl_fake_progress', true) === '1';
        $progress_color = get_post_meta($post_id, '_vsl_progress_color', true) ?: '#ff0000';
        
        // Opções para o estilo ao pausar
        $pause_style = get_post_meta($post_id, '_vsl_pause_style', true) ?: 'default';
        $pause_color = get_post_meta($post_id, '_vsl_pause_color', true) ?: '#000000';
        $pause_image_id = get_post_meta($post_id, '_vsl_pause_image', true);
        $hide_pause_button = get_post_meta($post_id, '_vsl_hide_pause_button', true) === '1';
        
        // Verificar se o resume player está ativado
        $enable_resume_player = get_post_meta($post_id, '_vsl_enable_resume_player', true) === '1';
        
        // Configurações de cores do Resume Player
        $resume_overlay_color = get_post_meta($post_id, '_vsl_resume_overlay_color', true) ?: '#000000';
        $resume_button_color = get_post_meta($post_id, '_vsl_resume_button_color', true) ?: '#617be5';
        $resume_button_hover_color = get_post_meta($post_id, '_vsl_resume_button_hover_color', true) ?: '#8950C7';
        
        // Opções de revelar oferta
        $enable_offer_reveal = get_post_meta($post_id, '_vsl_enable_offer_reveal', true) === '1';
        $offer_reveal_class = get_post_meta($post_id, '_vsl_offer_reveal_class', true);
        $offer_reveal_time = get_post_meta($post_id, '_vsl_offer_reveal_time', true) ?: '0';
        $offer_reveal_persist = get_post_meta($post_id, '_vsl_offer_reveal_persist', true) === '1';
        
        // Eventos de conversão
        $conversion_events = get_post_meta($post_id, '_vsl_conversion_events', true);
        $has_conversion_events = !empty($conversion_events) && is_array($conversion_events);
        
        // Extract YouTube video ID from URL
        $video_id = $this->get_youtube_video_id($youtube_url);
        if (!$video_id) {
            return '';
        }
        
        // Generate a unique ID for this player instance
        $player_id = 'vsl-player-' . $post_id;
        
        // Build the output
        $output = '<div id="' . esc_attr($player_id) . '" ';
        $output .= 'class="vsl-player-container" ';
        $output .= 'data-vsl-id="' . esc_attr($post_id) . '" ';
        $output .= 'data-video-id="' . esc_attr($video_id) . '" ';
        $output .= 'data-player-color="' . esc_attr($player_color) . '" ';
        $output .= 'data-fake-progress="' . ($fake_progress ? 'true' : 'false') . '" ';
        $output .= 'data-progress-color="' . esc_attr($progress_color) . '" ';
        
        // Atributos para o estilo ao pausar
        $output .= 'data-pause-style="' . esc_attr($pause_style) . '" ';
        $output .= 'data-pause-color="' . esc_attr($pause_color) . '" ';
        if ($pause_image_id) {
            $output .= 'data-pause-image="' . esc_attr($pause_image_id) . '" ';
        }
        $output .= 'data-hide-pause-button="' . ($hide_pause_button ? 'true' : 'false') . '" ';
        
        // Atributo para o resume player
        $output .= 'data-enable-resume-player="' . ($enable_resume_player ? 'true' : 'false') . '" ';
        $output .= 'data-resume-overlay-color="' . esc_attr($resume_overlay_color) . '" ';
        $output .= 'data-resume-button-color="' . esc_attr($resume_button_color) . '" ';
        $output .= 'data-resume-button-hover-color="' . esc_attr($resume_button_hover_color) . '" ';
        
        // Atributos para revelar oferta
        $output .= 'data-enable-offer-reveal="' . ($enable_offer_reveal ? 'true' : 'false') . '" ';
        if ($enable_offer_reveal) {
            $output .= 'data-offer-reveal-class="' . esc_attr($offer_reveal_class) . '" ';
            $output .= 'data-offer-reveal-time="' . esc_attr($offer_reveal_time) . '" ';
            $output .= 'data-offer-reveal-persist="' . ($offer_reveal_persist ? 'true' : 'false') . '" ';
        }
        
        // Atributos para eventos de conversão
        $output .= 'data-has-conversion-events="' . ($has_conversion_events ? 'true' : 'false') . '" ';
        if ($has_conversion_events) {
            $output .= 'data-conversion-events="' . esc_attr(json_encode($conversion_events)) . '" ';
        }
        
        $output .= '>';
        
        // Create the various overlays for the player
        // Start overlay with play button - NOVA IMPLEMENTAÇÃO PARA RESOLVER O PROBLEMA DE POSICIONAMENTO
        $output .= '<div class="vsl-start-overlay" style="display:flex;align-items:center;justify-content:center;">';
        
        // Agora, em vez de usar uma div para envolver o SVG, colocamos o SVG diretamente com inline styles fixos
        // Isso garante que ele esteja posicionado corretamente desde o primeiro render
        $svg_content = $this->get_player_svg($player_color);
        $output .= $svg_content;
        
        $output .= '</div>';
        
        // Playing overlay with controls
        $output .= '<div class="vsl-playing-overlay" style="display: none;">';
        $output .= '<div class="vsl-controls">';
        $output .= '<button class="vsl-play-pause"></button>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>'; // Close the container
        
        // Enqueue the necessary scripts and styles for this shortcode
        wp_enqueue_style('vsl-player-loading');
        wp_enqueue_script('vsl-player-loading');
        wp_enqueue_style('vsl-player-youtube');
        wp_enqueue_script('vsl-player-youtube');
        
        // Se o fake_progress estiver ativado, carregue os arquivos relacionados
        if ($fake_progress) {
            wp_enqueue_style('vsl-player-progress-bar');
            wp_enqueue_script('vsl-player-progress-bar');
        }
        
        // Se o resume player estiver ativado, carregue os arquivos relacionados
        if ($enable_resume_player) {
            wp_enqueue_style('vsl-player-resume');
            wp_enqueue_script('vsl-player-resume');
        }
        
        // Se o offer reveal estiver ativado, carregue os arquivos relacionados
        if ($enable_offer_reveal) {
            wp_enqueue_style('vsl-player-offer-reveal');
            wp_enqueue_script('vsl-player-offer-reveal');
        }
        
        // Se houver eventos de conversão, carregue os arquivos relacionados
        if ($has_conversion_events) {
            wp_enqueue_style('vsl-player-conversions');
            wp_enqueue_script('vsl-player-conversions');
        }
        
        return $output;
    }
    
    /**
     * Get the SVG player button with custom color
     * 
     * @param string $player_color The player color
     * @return string The SVG content with custom color
     */
    public function get_player_svg($player_color) {
        // Obter o conteúdo do arquivo SVG original
        $svg_path = VSL_PLAYER_DIR . 'assets/player-thumb-youtube.svg';
        
        if (file_exists($svg_path)) {
            $svg_content = file_get_contents($svg_path);
            
            // Adicionar a classe vsl-play-button-svg ao elemento SVG e garantir que o SVG esteja pré-posicionado corretamente
            $svg_content = str_replace('<svg', '<svg class="vsl-play-button-svg" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);-webkit-transform:translate(-50%,-50%);margin:0;padding:0;"', $svg_content);
            
            // Substituir a cor no SVG se necessário
            if ($player_color) {
                // Substituir a cor padrão pela cor personalizada
                // O SVG usa #7440B6 como cor padrão
                $svg_content = str_replace('fill="#7440B6"', 'fill="' . esc_attr($player_color) . '"', $svg_content);
                $svg_content = str_replace('fill="#617be5"', 'fill="' . esc_attr($player_color) . '"', $svg_content);
                // Verificar também se há atributos stroke com a cor
                $svg_content = str_replace('stroke="#7440B6"', 'stroke="' . esc_attr($player_color) . '"', $svg_content);
                $svg_content = str_replace('stroke="#617be5"', 'stroke="' . esc_attr($player_color) . '"', $svg_content);
            }
            
            return $svg_content;
        }
        
        // Fallback para o SVG inline caso o arquivo não seja encontrado
        $svg = '<svg class="vsl-play-button-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">';
        $svg .= '<circle cx="50" cy="50" r="45" fill="' . esc_attr($player_color) . '" />';
        $svg .= '<path d="M40,30 L70,50 L40,70 Z" fill="#ffffff" />';
        $svg .= '</svg>';
        
        return $svg;
    }
    
    /**
     * Get all VSL Player data for use in JavaScript
     * 
     * @return array Array of player configurations
     */
    private function get_vsl_players_data() {
        $players_data = array();
        
        // Query all published vsl_player posts
        $args = array(
            'post_type'      => 'vsl_player',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );
        
        $player_posts = get_posts($args);
        
        foreach ($player_posts as $player_post) {
            // Get YouTube URL
            $youtube_url = get_post_meta($player_post->ID, '_vsl_youtube_url', true);
            $player_color = get_post_meta($player_post->ID, '_vsl_player_color', true) ?: '#617be5';
            $fake_progress = get_post_meta($player_post->ID, '_vsl_fake_progress', true) ?: '0';
            $progress_color = get_post_meta($player_post->ID, '_vsl_progress_color', true) ?: '#ff0000';
            
            // Add player data
            $players_data[] = array(
                'id'             => $player_post->ID,
                'video_id'       => $this->get_youtube_video_id($youtube_url),
                'player_color'   => $player_color,
                'fake_progress'  => $fake_progress === '1',
                'progress_color' => $progress_color
            );
        }
        
        return $players_data;
    }
    
    /**
     * Extract YouTube video ID from URL
     *
     * @param string $url YouTube URL
     * @return string|null Video ID or null if not found
     */
    private function get_youtube_video_id($url) {
        if (empty($url)) {
            return null;
        }
        
        $video_id = null;
        
        // Match standard YouTube URLs
        if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $id_match)) {
            $video_id = $id_match[1];
        } 
        // Match youtu.be URLs
        else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $id_match)) {
            $video_id = $id_match[1];
        }
        // Match embed URLs
        else if (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $id_match)) {
            $video_id = $id_match[1];
        }
        // Match v= param
        else if (preg_match('/v=([^\&\?\/]+)/', $url, $id_match)) {
            $video_id = $id_match[1];
        }
        
        return $video_id;
    }
    
    /**
     * Get all active VSL Reveals for a specific page
     * 
     * @param int $page_id The page ID to check
     * @return array Array of reveal configurations
     */
    private function get_active_reveals_for_page($page_id) {
        $reveals = array();
        
        // Query all published vsl_reveal posts
        $args = array(
            'post_type'      => 'vsl_reveal',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );
        
        $reveal_posts = get_posts($args);
        
        foreach ($reveal_posts as $reveal_post) {
            // Get the pages this reveal is active on
            $active_pages = get_post_meta($reveal_post->ID, '_vsl_reveal_pages', true);
            
            // Skip if no pages are configured or the current page is not in the list
            if (!is_array($active_pages) || !in_array($page_id, $active_pages)) {
                continue;
            }
            
            // This reveal is active for the current page
            $reveals[] = array(
                'id'       => $reveal_post->ID,
                'class'    => get_post_meta($reveal_post->ID, '_vsl_reveal_class', true),
                'time'     => (int) get_post_meta($reveal_post->ID, '_vsl_reveal_time', true),
                'persist'  => get_post_meta($reveal_post->ID, '_vsl_reveal_persist', true) === '1',
            );
        }
        
        return $reveals;
    }
    
    /**
     * Load progress bar assets via AJAX
     */
    public function load_progress_bar_assets() {
        // Verificar nonce
        check_ajax_referer('vsl_player_ajax_nonce', 'nonce');
        
        // Enfileirar scripts e estilos da barra de progresso
        wp_enqueue_script('vsl-player-progress-bar');
        wp_enqueue_style('vsl-player-progress-bar');
        
        wp_send_json_success(array(
            'message' => 'Progress bar assets loaded'
        ));
        
        wp_die();
    }
    
    /**
     * Get image URL via AJAX
     */
    public function get_image_url_ajax() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vsl_player_ajax_nonce')) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        // Verificar ID da imagem
        if (!isset($_POST['image_id']) || empty($_POST['image_id'])) {
            wp_send_json_error('ID da imagem não fornecido');
            return;
        }
        
        $image_id = absint($_POST['image_id']);
        $image_url = wp_get_attachment_url($image_id);
        
        if ($image_url) {
            wp_send_json_success($image_url);
        } else {
            wp_send_json_error('Imagem não encontrada');
        }
    }
    
    /**
     * Add preconnect links for YouTube domains to improve loading time
     */
    public function add_youtube_preconnect() {
        echo '<link rel="preconnect" href="https://www.youtube.com">';
        echo '<link rel="preconnect" href="https://www.youtube-nocookie.com">';
        echo '<link rel="preconnect" href="https://i.ytimg.com">';
    }
}
