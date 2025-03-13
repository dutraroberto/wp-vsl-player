<?php
/**
 * Custom Post Types for VSL Player
 */
class VSL_Player_CPT {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Register custom post types
        add_action('init', array($this, 'register_custom_post_types'));
        
        // Register meta boxes
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        
        // Save meta box data
        add_action('save_post', array($this, 'save_meta_box_data'));
        
        // Add custom columns to VSL CPT
        add_filter('manage_vsl_player_posts_columns', array($this, 'vsl_custom_columns'));
        add_action('manage_vsl_player_posts_custom_column', array($this, 'vsl_custom_column_content'), 10, 2);
        
        // Add custom columns to Reveal CPT
        add_filter('manage_vsl_reveal_posts_columns', array($this, 'reveal_custom_columns'));
        add_action('manage_vsl_reveal_posts_custom_column', array($this, 'reveal_custom_column_content'), 10, 2);
        
        // Handle shortcodes
        add_shortcode('vsl_player', array($this, 'vsl_player_shortcode'));
        add_shortcode('vsl_reveal', array($this, 'vsl_reveal_shortcode'));
    }

    /**
     * Register custom post types
     */
    public function register_custom_post_types() {
        // Register VSL Player CPT
        register_post_type('vsl_player', array(
            'labels' => array(
                'name'               => __('VSLs Otimizadas', 'vsl-player'),
                'singular_name'      => __('VSL Otimizada', 'vsl-player'),
                'menu_name'          => __('VSLs Otimizadas', 'vsl-player'),
                'add_new'            => __('Adicionar Nova', 'vsl-player'),
                'add_new_item'       => __('Adicionar Nova VSL', 'vsl-player'),
                'edit_item'          => __('Editar VSL', 'vsl-player'),
                'new_item'           => __('Nova VSL', 'vsl-player'),
                'view_item'          => __('Ver VSL', 'vsl-player'),
                'search_items'       => __('Buscar VSLs', 'vsl-player'),
                'not_found'          => __('Nenhuma VSL encontrada', 'vsl-player'),
                'not_found_in_trash' => __('Nenhuma VSL encontrada na lixeira', 'vsl-player'),
            ),
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // We'll add this to our custom menu
            'supports'            => array('title', 'thumbnail'),
            'has_archive'         => false,
            'rewrite'             => array('slug' => 'vsl'),
            'menu_icon'           => 'dashicons-video-alt2',
            'show_in_rest'        => false,
        ));
        
        // Register VSL Reveal CPT
        register_post_type('vsl_reveal', array(
            'labels' => array(
                'name'               => __('Revelação de Conteúdo', 'vsl-player'),
                'singular_name'      => __('Revelação de Conteúdo', 'vsl-player'),
                'menu_name'          => __('Ocultar Sessões', 'vsl-player'),
                'add_new'            => __('Adicionar Nova', 'vsl-player'),
                'add_new_item'       => __('Adicionar Nova Revelação', 'vsl-player'),
                'edit_item'          => __('Editar Revelação', 'vsl-player'),
                'new_item'           => __('Nova Revelação', 'vsl-player'),
                'view_item'          => __('Ver Revelação', 'vsl-player'),
                'search_items'       => __('Buscar Revelações', 'vsl-player'),
                'not_found'          => __('Nenhuma revelação encontrada', 'vsl-player'),
                'not_found_in_trash' => __('Nenhuma revelação encontrada na lixeira', 'vsl-player'),
            ),
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // We'll add this to our custom menu
            'supports'            => array('title'),
            'has_archive'         => false,
            'rewrite'             => array('slug' => 'reveal'),
            'menu_icon'           => 'dashicons-visibility',
            'show_in_rest'        => false,
        ));
    }

    /**
     * Register meta boxes
     */
    public function register_meta_boxes() {
        // Meta box for VSL Player
        add_meta_box(
            'vsl_player_meta',
            __('Configurações do VSL', 'vsl-player'),
            array($this, 'vsl_player_meta_callback'),
            'vsl_player',
            'normal',
            'high'
        );
        
        // Meta box for Reveal Content
        add_meta_box(
            'vsl_reveal_meta',
            __('Configurações de Revelação', 'vsl-player'),
            array($this, 'vsl_reveal_meta_callback'),
            'vsl_reveal',
            'normal',
            'high'
        );
    }

    /**
     * Callback for VSL Player meta box
     */
    public function vsl_player_meta_callback($post) {
        // Add nonce for security
        wp_nonce_field('vsl_player_meta_save', 'vsl_player_meta_nonce');
        
        // Get saved values
        $youtube_url = get_post_meta($post->ID, '_vsl_youtube_url', true);
        $fallback_video_id = get_post_meta($post->ID, '_vsl_fallback_video', true);
        
        // Generate shortcode
        $shortcode = '[vsl_player id="' . $post->ID . '"]';
        ?>
        <table class="form-table">
            <tr>
                <th><label for="vsl_youtube_url"><?php echo esc_html__('URL do YouTube', 'vsl-player'); ?></label></th>
                <td>
                    <input type="text" id="vsl_youtube_url" name="vsl_youtube_url" value="<?php echo esc_attr($youtube_url); ?>" class="large-text" placeholder="https://www.youtube.com/watch?v=xyz123">
                    <p class="description"><?php echo esc_html__('Insira a URL completa do vídeo do YouTube', 'vsl-player'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="vsl_fallback_video"><?php echo esc_html__('Vídeo de Fallback para Mobile', 'vsl-player'); ?></label></th>
                <td>
                    <div class="media-upload-container">
                        <input type="hidden" id="vsl_fallback_video" name="vsl_fallback_video" value="<?php echo esc_attr($fallback_video_id); ?>">
                        <button type="button" class="button vsl-upload-media" id="vsl_fallback_video_button"><?php echo esc_html__('Selecionar Vídeo', 'vsl-player'); ?></button>
                        <div class="media-preview" id="vsl_fallback_video_preview">
                            <?php 
                            if (!empty($fallback_video_id)) {
                                echo wp_get_attachment_image($fallback_video_id, 'thumbnail');
                                echo '<button type="button" class="button vsl-remove-media">' . esc_html__('Remover', 'vsl-player') . '</button>';
                            }
                            ?>
                        </div>
                        <p class="description"><?php echo esc_html__('Selecione um vídeo MP4 para uso em dispositivos móveis onde o autoplay do YouTube não funciona', 'vsl-player'); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('Shortcode', 'vsl-player'); ?></label></th>
                <td>
                    <div class="shortcode-container">
                        <input type="text" readonly="readonly" value="<?php echo esc_attr($shortcode); ?>" class="large-text" onclick="this.select();">
                        <button type="button" class="button vsl-copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>"><?php echo esc_html__('Copiar', 'vsl-player'); ?></button>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Callback for Reveal Content meta box
     */
    public function vsl_reveal_meta_callback($post) {
        // Add nonce for security
        wp_nonce_field('vsl_reveal_meta_save', 'vsl_reveal_meta_nonce');
        
        // Get saved values
        $css_class = get_post_meta($post->ID, '_vsl_reveal_class', true);
        $reveal_time = get_post_meta($post->ID, '_vsl_reveal_time', true);
        $reveal_persist = get_post_meta($post->ID, '_vsl_reveal_persist', true);
        
        // Generate shortcode
        $shortcode = '[vsl_reveal id="' . $post->ID . '"]';
        ?>
        <table class="form-table">
            <tr>
                <th><label for="vsl_reveal_class"><?php echo esc_html__('Classe do Elemento', 'vsl-player'); ?></label></th>
                <td>
                    <input type="text" id="vsl_reveal_class" name="vsl_reveal_class" value="<?php echo esc_attr($css_class); ?>" class="large-text" placeholder=".call-to-action">
                    <p class="description"><?php echo esc_html__('Insira a classe CSS do elemento que será revelado (ex.: .call-to-action)', 'vsl-player'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="vsl_reveal_time"><?php echo esc_html__('Tempo em Segundos', 'vsl-player'); ?></label></th>
                <td>
                    <input type="number" id="vsl_reveal_time" name="vsl_reveal_time" value="<?php echo esc_attr($reveal_time); ?>" min="0" step="1" class="small-text">
                    <p class="description"><?php echo esc_html__('Defina o tempo em segundos até que o conteúdo seja exibido', 'vsl-player'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="vsl_reveal_persist"><?php echo esc_html__('Persistência de Visualização', 'vsl-player'); ?></label></th>
                <td>
                    <input type="checkbox" id="vsl_reveal_persist" name="vsl_reveal_persist" value="1" <?php checked($reveal_persist, '1'); ?>>
                    <label for="vsl_reveal_persist"><?php echo esc_html__('Manter elementos revelados visíveis após recarregamento da página', 'vsl-player'); ?></label>
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('Shortcode', 'vsl-player'); ?></label></th>
                <td>
                    <div class="shortcode-container">
                        <input type="text" readonly="readonly" value="<?php echo esc_attr($shortcode); ?>" class="large-text" onclick="this.select();">
                        <button type="button" class="button vsl-copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>"><?php echo esc_html__('Copiar', 'vsl-player'); ?></button>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_box_data($post_id) {
        // Check if we're saving VSL Player
        if (isset($_POST['vsl_player_meta_nonce'])) {
            // Verify nonce
            if (!wp_verify_nonce($_POST['vsl_player_meta_nonce'], 'vsl_player_meta_save')) {
                return;
            }
            
            // Check user permissions
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
            
            // Save YouTube URL
            if (isset($_POST['vsl_youtube_url'])) {
                update_post_meta($post_id, '_vsl_youtube_url', sanitize_text_field($_POST['vsl_youtube_url']));
            }
            
            // Save fallback video
            if (isset($_POST['vsl_fallback_video'])) {
                update_post_meta($post_id, '_vsl_fallback_video', sanitize_text_field($_POST['vsl_fallback_video']));
            }
            
            // Generate and save a thumbnail from YouTube if URL is set
            if (!empty($_POST['vsl_youtube_url'])) {
                $this->set_youtube_thumbnail($post_id, $_POST['vsl_youtube_url']);
            }
        }
        
        // Check if we're saving VSL Reveal
        if (isset($_POST['vsl_reveal_meta_nonce'])) {
            // Verify nonce
            if (!wp_verify_nonce($_POST['vsl_reveal_meta_nonce'], 'vsl_reveal_meta_save')) {
                return;
            }
            
            // Check user permissions
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
            
            // Save CSS class
            if (isset($_POST['vsl_reveal_class'])) {
                update_post_meta($post_id, '_vsl_reveal_class', sanitize_text_field($_POST['vsl_reveal_class']));
            }
            
            // Save reveal time
            if (isset($_POST['vsl_reveal_time'])) {
                update_post_meta($post_id, '_vsl_reveal_time', absint($_POST['vsl_reveal_time']));
            }
            
            // Save persistence
            $reveal_persist = isset($_POST['vsl_reveal_persist']) ? '1' : '0';
            update_post_meta($post_id, '_vsl_reveal_persist', $reveal_persist);
        }
    }

    /**
     * Set YouTube thumbnail as featured image
     */
    public function set_youtube_thumbnail($post_id, $youtube_url) {
        // Extract video ID from URL
        preg_match('/[\\?\\&]v=([^\\?\\&]+)/', $youtube_url, $matches);
        if (isset($matches[1])) {
            $video_id = $matches[1];
            
            // YouTube thumbnail URL
            $thumbnail_url = 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg';
            
            // Only proceed if we don't already have a thumbnail
            if (!has_post_thumbnail($post_id)) {
                // Get WP upload directory
                $upload_dir = wp_upload_dir();
                
                // Generate a unique filename
                $filename = 'youtube-thumb-' . $video_id . '.jpg';
                $file_path = $upload_dir['path'] . '/' . $filename;
                
                // Download the image
                $image_data = file_get_contents($thumbnail_url);
                if ($image_data !== false) {
                    file_put_contents($file_path, $image_data);
                    
                    // Check the type of file
                    $filetype = wp_check_filetype($filename, null);
                    
                    // Prepare attachment data
                    $attachment = array(
                        'post_mime_type' => $filetype['type'],
                        'post_title'     => sanitize_file_name($filename),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );
                    
                    // Insert the attachment
                    $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
                    
                    // Generate metadata for the attachment
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    
                    // Set as featured image
                    set_post_thumbnail($post_id, $attach_id);
                }
            }
        }
    }

    /**
     * Custom columns for VSL Player CPT
     */
    public function vsl_custom_columns($columns) {
        $new_columns = array();
        
        // Add thumbnail column after checkbox
        $new_columns['cb'] = $columns['cb'];
        $new_columns['thumbnail'] = __('Pré-visualização', 'vsl-player');
        
        // Add the rest of the columns
        $new_columns['title'] = $columns['title'];
        $new_columns['shortcode'] = __('Shortcode', 'vsl-player');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /**
     * Content for custom columns in VSL Player CPT
     */
    public function vsl_custom_column_content($column, $post_id) {
        switch ($column) {
            case 'thumbnail':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(100, 56));
                } else {
                    echo '<div class="no-thumbnail">' . esc_html__('Sem thumbnail', 'vsl-player') . '</div>';
                }
                break;
                
            case 'shortcode':
                $shortcode = '[vsl_player id="' . $post_id . '"]';
                echo '<div class="shortcode-container">';
                echo '<input type="text" readonly="readonly" value="' . esc_attr($shortcode) . '" class="shortcode-field" onclick="this.select();">';
                echo '<button type="button" class="button vsl-copy-shortcode" data-shortcode="' . esc_attr($shortcode) . '">' . esc_html__('Copiar', 'vsl-player') . '</button>';
                echo '</div>';
                break;
        }
    }

    /**
     * Custom columns for VSL Reveal CPT
     */
    public function reveal_custom_columns($columns) {
        $new_columns = array();
        
        // Keep checkbox
        $new_columns['cb'] = $columns['cb'];
        
        // Add the rest of the columns
        $new_columns['title'] = $columns['title'];
        $new_columns['shortcode'] = __('Shortcode', 'vsl-player');
        $new_columns['class'] = __('Classe CSS', 'vsl-player');
        $new_columns['time'] = __('Tempo (s)', 'vsl-player');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /**
     * Content for custom columns in VSL Reveal CPT
     */
    public function reveal_custom_column_content($column, $post_id) {
        switch ($column) {
            case 'shortcode':
                $shortcode = '[vsl_reveal id="' . $post_id . '"]';
                echo '<div class="shortcode-container">';
                echo '<input type="text" readonly="readonly" value="' . esc_attr($shortcode) . '" class="shortcode-field" onclick="this.select();">';
                echo '<button type="button" class="button vsl-copy-shortcode" data-shortcode="' . esc_attr($shortcode) . '">' . esc_html__('Copiar', 'vsl-player') . '</button>';
                echo '</div>';
                break;
                
            case 'class':
                $css_class = get_post_meta($post_id, '_vsl_reveal_class', true);
                echo esc_html($css_class);
                break;
                
            case 'time':
                $reveal_time = get_post_meta($post_id, '_vsl_reveal_time', true);
                echo esc_html($reveal_time);
                break;
        }
    }

    /**
     * VSL Player shortcode callback
     */
    public function vsl_player_shortcode($atts) {
        // Extract attributes
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'vsl_player');
        
        $post_id = absint($atts['id']);
        
        // Check if post exists and is published
        if (empty($post_id) || get_post_status($post_id) !== 'publish' || get_post_type($post_id) !== 'vsl_player') {
            return '<p>' . esc_html__('Vídeo não encontrado', 'vsl-player') . '</p>';
        }
        
        // Get metadata
        $youtube_url = get_post_meta($post_id, '_vsl_youtube_url', true);
        $fallback_id = get_post_meta($post_id, '_vsl_fallback_video', true);
        
        // Placeholder for player output
        // Actual player implementation will be added in a future phase
        $output = '<div class="vsl-player-container" data-vsl-id="' . esc_attr($post_id) . '">';
        $output .= '<div class="vsl-player-placeholder">';
        $output .= '<p>' . esc_html__('VSL Player será implementado em uma fase futura', 'vsl-player') . '</p>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }

    /**
     * VSL Reveal shortcode callback
     */
    public function vsl_reveal_shortcode($atts) {
        // Extract attributes
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'vsl_reveal');
        
        $post_id = absint($atts['id']);
        
        // Check if post exists and is published
        if (empty($post_id) || get_post_status($post_id) !== 'publish' || get_post_type($post_id) !== 'vsl_reveal') {
            return '';
        }
        
        // Get metadata
        $css_class = get_post_meta($post_id, '_vsl_reveal_class', true);
        $reveal_time = get_post_meta($post_id, '_vsl_reveal_time', true);
        $reveal_persist = get_post_meta($post_id, '_vsl_reveal_persist', true);
        
        // Placeholder for reveal output
        // Actual reveal functionality will be added in a future phase
        $output = '<div class="vsl-reveal-controller" 
                      data-reveal-id="' . esc_attr($post_id) . '" 
                      data-reveal-class="' . esc_attr($css_class) . '" 
                      data-reveal-time="' . esc_attr($reveal_time) . '" 
                      data-reveal-persist="' . esc_attr($reveal_persist) . '">
                  </div>';
        
        return $output;
    }
}
