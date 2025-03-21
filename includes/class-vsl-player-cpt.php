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
        
        // Add admin styles for better column layout
        add_action('admin_head', array($this, 'admin_custom_css'));
        
        // Add custom columns to Reveal CPT
        add_filter('manage_vsl_reveal_posts_columns', array($this, 'reveal_custom_columns'));
        add_action('manage_vsl_reveal_posts_custom_column', array($this, 'reveal_custom_column_content'), 10, 2);
        
        // Handle shortcodes
        add_shortcode('vsl_player', array($this, 'vsl_player_shortcode'));
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
        $player_color = get_post_meta($post->ID, '_vsl_player_color', true);
        $fake_progress = get_post_meta($post->ID, '_vsl_fake_progress', true);
        $progress_color = get_post_meta($post->ID, '_vsl_progress_color', true);
        
        // Novas opções para o estilo ao pausar
        $pause_style = get_post_meta($post->ID, '_vsl_pause_style', true);
        $pause_color = get_post_meta($post->ID, '_vsl_pause_color', true);
        $pause_image_id = get_post_meta($post->ID, '_vsl_pause_image', true);
        $hide_pause_button = get_post_meta($post->ID, '_vsl_hide_pause_button', true);
        $enable_resume_player = get_post_meta($post->ID, '_vsl_enable_resume_player', true);
        
        // Opções de cores para o Resume Player
        $resume_overlay_color = get_post_meta($post->ID, '_vsl_resume_overlay_color', true) ?: '#000000';
        $resume_button_color = get_post_meta($post->ID, '_vsl_resume_button_color', true) ?: '#617be5';
        $resume_button_hover_color = get_post_meta($post->ID, '_vsl_resume_button_hover_color', true) ?: '#3854c3';
        
        // Valores padrão
        if (empty($pause_style)) {
            $pause_style = 'default';
        }
        if (empty($pause_color)) {
            $pause_color = '#000000';
        }
        
        // Use default color if not set
        if (empty($player_color)) {
            $player_color = '#617be5';
        }
        
        // Use default progress color if not set
        if (empty($progress_color)) {
            $progress_color = '#ff0000';
        }
        
        // Generate shortcode
        $shortcode = '[vsl_player id="' . $post->ID . '"]';
        ?>
        <div class="vsl-meta-box-container">
            <!-- SEÇÃO: CONFIGURAÇÕES GERAIS -->
            <div class="vsl-settings-section">
                <div class="vsl-section-header">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <h2><?php echo esc_html__('Configurações Gerais', 'vsl-player'); ?></h2>
                </div>
                <div class="vsl-section-content">
                    <!-- URL do YouTube -->
                    <div class="vsl-field-row">
                        <div class="vsl-field-label">
                            <label for="vsl_youtube_url"><?php echo esc_html__('URL do YouTube', 'vsl-player'); ?></label>
                            <p class="description"><?php echo esc_html__('Insira a URL completa do vídeo do YouTube', 'vsl-player'); ?></p>
                        </div>
                        <div class="vsl-field-input">
                            <input type="text" id="vsl_youtube_url" name="vsl_youtube_url" value="<?php echo esc_attr($youtube_url); ?>" 
                                  class="regular-text" placeholder="https://www.youtube.com/watch?v=xyz123">
                        </div>
                    </div>
                    
                    <!-- Estilo ao Pausar -->
                    <div class="vsl-field-row">
                        <div class="vsl-field-label">
                            <label for="vsl_pause_style"><?php echo esc_html__('Estilo ao Pausar', 'vsl-player'); ?></label>
                            <p class="description"><?php echo esc_html__('Escolha o estilo de fundo quando o vídeo está pausado.', 'vsl-player'); ?></p>
                        </div>
                        <div class="vsl-field-input">
                            <select id="vsl_pause_style" name="vsl_pause_style" class="regular-text vsl-conditional-control" data-control-group="vsl-pause-style">
                                <option value="default" <?php selected($pause_style, 'default'); ?>>
                                    <?php echo esc_html__('Padrão (Gradiente)', 'vsl-player'); ?>
                                </option>
                                <option value="solid" <?php selected($pause_style, 'solid'); ?>>
                                    <?php echo esc_html__('Cor Sólida', 'vsl-player'); ?>
                                </option>
                                <option value="image" <?php selected($pause_style, 'image'); ?>>
                                    <?php echo esc_html__('Imagem personalizada', 'vsl-player'); ?>
                                </option>
                            </select>
                            
                            <!-- Cor de Fundo (Mostrado apenas quando o estilo é "solid") -->
                            <div class="vsl-conditional-field vsl-pause-style-solid-field">
                                <label for="vsl_pause_color"><?php echo esc_html__('Cor de Fundo', 'vsl-player'); ?></label>
                                <input type="text" id="vsl_pause_color" name="vsl_pause_color" value="<?php echo esc_attr($pause_color); ?>" 
                                       class="vsl-color-picker" data-default-color="#000000">
                            </div>
                            
                            <!-- Imagem de Fundo (Mostrado apenas quando o estilo é "image") -->
                            <div class="vsl-conditional-field vsl-pause-style-image-field">
                                <label for="vsl_pause_image"><?php echo esc_html__('Imagem de Fundo', 'vsl-player'); ?></label>
                                <input type="hidden" id="vsl_pause_image" name="vsl_pause_image" value="<?php echo esc_attr($pause_image_id); ?>">
                                <button type="button" class="button vsl-upload-media" id="vsl_pause_image_button" data-media-type="image">
                                    <?php echo esc_html__('Selecionar Imagem', 'vsl-player'); ?>
                                </button>
                                <div class="vsl-media-preview-container" id="vsl_pause_image_preview">
                                    <?php 
                                    if (!empty($pause_image_id)) {
                                        echo wp_get_attachment_image($pause_image_id, 'thumbnail');
                                        echo '<button type="button" class="button vsl-remove-media">' . esc_html__('Remover', 'vsl-player') . '</button>';
                                    }
                                    ?>
                                </div>
                                <!-- Ocultar botão do player quando pausado -->
                                <div style="margin-top: 15px;">
                                    <label class="vsl-toggle-switch">
                                        <input type="checkbox" id="vsl_hide_pause_button" name="vsl_hide_pause_button" value="1" <?php checked($hide_pause_button, '1'); ?>>
                                        <span class="vsl-toggle-slider"></span>
                                    </label>
                                    <span class="vsl-toggle-label"><?php echo esc_html__('Ocultar botão do player quando pausado', 'vsl-player'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SEÇÃO: PERSONALIZAÇÃO -->
            <div class="vsl-settings-section">
                <div class="vsl-section-header">
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <h2><?php echo esc_html__('Personalização', 'vsl-player'); ?></h2>
                </div>
                <div class="vsl-section-content">
                    <!-- Cor do Botão de Play -->
                    <div class="vsl-field-row">
                        <div class="vsl-field-label">
                            <label for="vsl_player_color"><?php echo esc_html__('Cor do Botão de Play', 'vsl-player'); ?></label>
                            <p class="description"><?php echo esc_html__('Selecione a cor para o botão de play.', 'vsl-player'); ?></p>
                        </div>
                        <div class="vsl-field-input">
                            <input type="text" id="vsl_player_color" name="vsl_player_color" value="<?php echo esc_attr($player_color); ?>" 
                                   class="vsl-color-picker" data-default-color="#617be5">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SEÇÃO: RESUME PLAYER -->
            <div class="vsl-settings-section">
                <div class="vsl-section-header">
                    <span class="dashicons dashicons-controls-play"></span>
                    <h2><?php echo esc_html__('Resume Player', 'vsl-player'); ?></h2>
                </div>
                <div class="vsl-section-content">
                    <!-- Ativar Resume Player -->
                    <div class="vsl-field-row">
                        <div class="vsl-field-label">
                            <label><?php echo esc_html__('Ativar Resume Player', 'vsl-player'); ?></label>
                            <p class="description"><?php echo esc_html__('Se ativado, o player irá lembrar a posição do vídeo e retomar a partir da última posição.', 'vsl-player'); ?></p>
                        </div>
                        <div class="vsl-field-input">
                            <label class="vsl-toggle-switch">
                                <input type="checkbox" id="vsl_enable_resume_player" name="vsl_enable_resume_player" value="1" <?php checked($enable_resume_player, '1'); ?> class="vsl-conditional-control" data-control-group="vsl-resume-player-options">
                                <span class="vsl-toggle-slider"></span>
                            </label>
                            <span class="vsl-toggle-label"><?php echo esc_html__('Ativar', 'vsl-player'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Opções de cores para o Resume Player (visíveis apenas quando o Resume Player está ativado) -->
                    <div class="vsl-conditional-field vsl-resume-player-options">
                        <div class="vsl-field-row">
                            <div class="vsl-field-label">
                                <label for="vsl_resume_overlay_color"><?php echo esc_html__('Cor de Fundo do Resume Player', 'vsl-player'); ?></label>
                                <p class="description"><?php echo esc_html__('Selecione a cor de fundo para o overlay do Resume Player.', 'vsl-player'); ?></p>
                            </div>
                            <div class="vsl-field-input">
                                <input type="text" id="vsl_resume_overlay_color" name="vsl_resume_overlay_color" value="<?php echo esc_attr($resume_overlay_color); ?>" 
                                       class="vsl-color-picker" data-default-color="#000000">
                            </div>
                        </div>
                        
                        <div class="vsl-field-row">
                            <div class="vsl-field-label">
                                <label for="vsl_resume_button_color"><?php echo esc_html__('Cor do Botão do Resume Player', 'vsl-player'); ?></label>
                                <p class="description"><?php echo esc_html__('Selecione a cor para o botão do Resume Player.', 'vsl-player'); ?></p>
                            </div>
                            <div class="vsl-field-input">
                                <input type="text" id="vsl_resume_button_color" name="vsl_resume_button_color" value="<?php echo esc_attr($resume_button_color); ?>" 
                                       class="vsl-color-picker" data-default-color="#617be5">
                            </div>
                        </div>
                        
                        <div class="vsl-field-row">
                            <div class="vsl-field-label">
                                <label for="vsl_resume_button_hover_color"><?php echo esc_html__('Cor do Botão do Resume Player ao Passar o Mouse', 'vsl-player'); ?></label>
                                <p class="description"><?php echo esc_html__('Selecione a cor para o botão do Resume Player ao passar o mouse.', 'vsl-player'); ?></p>
                            </div>
                            <div class="vsl-field-input">
                                <input type="text" id="vsl_resume_button_hover_color" name="vsl_resume_button_hover_color" value="<?php echo esc_attr($resume_button_hover_color); ?>" 
                                       class="vsl-color-picker" data-default-color="#8950C7">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SEÇÃO: BARRA DE PROGRESSO -->
            <div class="vsl-settings-section">
                <div class="vsl-section-header">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <h2><?php echo esc_html__('Barra de Progresso', 'vsl-player'); ?></h2>
                </div>
                <div class="vsl-section-content">
                    <!-- Barra de Progresso Falsa -->
                    <div class="vsl-field-row">
                        <div class="vsl-field-label">
                            <label><?php echo esc_html__('Barra de Progresso Falsa', 'vsl-player'); ?></label>
                            <p class="description"><?php echo esc_html__('A barra de progresso falsa avança mais rapidamente do que o progresso real do vídeo, dando a impressão de que o vídeo é mais curto.', 'vsl-player'); ?></p>
                        </div>
                        <div class="vsl-field-input">
                            <label class="vsl-toggle-switch">
                                <input type="checkbox" id="vsl_fake_progress" name="vsl_fake_progress" value="1" <?php checked($fake_progress, '1'); ?> 
                                       data-target="vsl-progress-color-field">
                                <span class="vsl-toggle-slider"></span>
                            </label>
                            <span class="vsl-toggle-label"><?php echo esc_html__('Ativar', 'vsl-player'); ?></span>
                            
                            <!-- Cor da Barra de Progresso (Mostrado independentemente do estado do toggle) -->
                            <div class="vsl-progress-color-field" <?php echo ($fake_progress !== '1') ? 'style="display:none;"' : ''; ?>>
                                <label for="vsl_progress_color" style="display:block; margin-top: 15px; margin-bottom: 5px;">
                                    <?php echo esc_html__('Cor da Barra de Progresso', 'vsl-player'); ?>
                                </label>
                                <input type="text" id="vsl_progress_color" name="vsl_progress_color" value="<?php echo esc_attr($progress_color); ?>" 
                                       class="vsl-color-picker" data-default-color="#ff0000">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SEÇÃO: REVELAR OFERTA -->
            <div class="vsl-settings-section">
                <div class="vsl-section-header">
                    <span class="dashicons dashicons-visibility"></span>
                    <h2><?php echo esc_html__('Revelar oferta', 'vsl-player'); ?></h2>
                </div>
                <div class="vsl-section-content">
                    <!-- Ativar Revelação de Ofertas -->
                    <div class="vsl-field-row">
                        <div class="vsl-field-label">
                            <label><?php echo esc_html__('Ativar Revelação de Ofertas', 'vsl-player'); ?></label>
                            <p class="description"><?php echo esc_html__('Se ativado, elementos com a classe CSS definida serão revelados após o tempo especificado de reprodução do vídeo.', 'vsl-player'); ?></p>
                        </div>
                        <div class="vsl-field-input">
                            <?php 
                            $enable_offer_reveal = get_post_meta($post->ID, '_vsl_enable_offer_reveal', true);
                            ?>
                            <label class="vsl-toggle-switch">
                                <input type="checkbox" id="vsl_enable_offer_reveal" name="vsl_enable_offer_reveal" value="1" <?php checked($enable_offer_reveal, '1'); ?> class="vsl-conditional-control" data-control-group="vsl-offer-reveal-options">
                                <span class="vsl-toggle-slider"></span>
                            </label>
                            <span class="vsl-toggle-label"><?php echo esc_html__('Ativar', 'vsl-player'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Opções de Revelação de Ofertas (visíveis apenas quando a funcionalidade está ativada) -->
                    <div class="vsl-conditional-field vsl-offer-reveal-options">
                        <div class="vsl-field-row">
                            <div class="vsl-field-label">
                                <label for="vsl_offer_reveal_class"><?php echo esc_html__('Classe do Elemento', 'vsl-player'); ?></label>
                                <p class="description"><?php echo esc_html__('Insira a classe CSS do elemento que será revelado (ex.: .oferta-especial)', 'vsl-player'); ?></p>
                            </div>
                            <div class="vsl-field-input">
                                <?php 
                                $offer_reveal_class = get_post_meta($post->ID, '_vsl_offer_reveal_class', true);
                                ?>
                                <input type="text" id="vsl_offer_reveal_class" name="vsl_offer_reveal_class" value="<?php echo esc_attr($offer_reveal_class); ?>" 
                                      class="regular-text" placeholder=".oferta-especial">
                            </div>
                        </div>
                        
                        <div class="vsl-field-row">
                            <div class="vsl-field-label">
                                <label for="vsl_offer_reveal_time"><?php echo esc_html__('Tempo até revelar', 'vsl-player'); ?></label>
                                <p class="description"><?php echo esc_html__('Defina o tempo em segundos até que o elemento seja revelado.', 'vsl-player'); ?></p>
                            </div>
                            <div class="vsl-field-input">
                                <?php 
                                $offer_reveal_time = get_post_meta($post->ID, '_vsl_offer_reveal_time', true) ?: '0';
                                ?>
                                <input type="number" id="vsl_offer_reveal_time" name="vsl_offer_reveal_time" value="<?php echo esc_attr($offer_reveal_time); ?>" 
                                       min="0" step="1" class="small-text">
                            </div>
                        </div>
                        
                        <div class="vsl-field-row">
                            <div class="vsl-field-label">
                                <label for="vsl_offer_reveal_pages"><?php echo esc_html__('Selecionar Página', 'vsl-player'); ?></label>
                                <p class="description"><?php echo esc_html__('Selecione as páginas onde a funcionalidade de revelação será ativada.', 'vsl-player'); ?></p>
                            </div>
                            <div class="vsl-field-input">
                                <?php 
                                $selected_pages = get_post_meta($post->ID, '_vsl_offer_reveal_pages', true);
                                if (!is_array($selected_pages)) {
                                    $selected_pages = array();
                                }
                                ?>
                                <select name="vsl_offer_reveal_pages[]" id="vsl_offer_reveal_pages" multiple="multiple" class="vsl-select2-pages" style="width: 100%;">
                                    <?php
                                    $pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'ASC'));
                                    foreach ($pages as $page) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($page->ID),
                                            in_array($page->ID, $selected_pages) ? 'selected="selected"' : '',
                                            esc_html($page->post_title)
                                        );
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="vsl-field-row">
                            <div class="vsl-field-label">
                                <label><?php echo esc_html__('Persistência de Visualização', 'vsl-player'); ?></label>
                                <p class="description"><?php echo esc_html__('Manter elementos revelados visíveis após recarregamento da página.', 'vsl-player'); ?></p>
                            </div>
                            <div class="vsl-field-input">
                                <?php 
                                $offer_reveal_persist = get_post_meta($post->ID, '_vsl_offer_reveal_persist', true);
                                ?>
                                <label class="vsl-toggle-switch">
                                    <input type="checkbox" id="vsl_offer_reveal_persist" name="vsl_offer_reveal_persist" value="1" <?php checked($offer_reveal_persist, '1'); ?>>
                                    <span class="vsl-toggle-slider"></span>
                                </label>
                                <span class="vsl-toggle-label"><?php echo esc_html__('Ativar', 'vsl-player'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SEÇÃO: SHORTCODE -->
            <div class="vsl-settings-section">
                <div class="vsl-section-header">
                    <span class="dashicons dashicons-shortcode"></span>
                    <h2><?php echo esc_html__('Shortcode', 'vsl-player'); ?></h2>
                </div>
                <div class="vsl-section-content">
                    <div class="vsl-field-row">
                        <div class="vsl-field-label">
                            <label><?php echo esc_html__('Código para Inserção', 'vsl-player'); ?></label>
                            <p class="description"><?php echo esc_html__('Copie e cole este shortcode em qualquer página ou post para exibir esta VSL.', 'vsl-player'); ?></p>
                        </div>
                        <div class="vsl-field-input">
                            <div class="vsl-shortcode-container">
                                <input type="text" readonly="readonly" value="<?php echo esc_attr($shortcode); ?>" 
                                       onclick="this.select();">
                                <button type="button" class="button button-primary vsl-copy-shortcode" 
                                        data-shortcode="<?php echo esc_attr($shortcode); ?>">
                                    <?php echo esc_html__('Copiar', 'vsl-player'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
        $selected_pages = get_post_meta($post->ID, '_vsl_reveal_pages', true);
        
        // Ensure selected pages is an array
        if (!is_array($selected_pages)) {
            $selected_pages = array();
        }
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
                <th><label for="vsl_reveal_pages"><?php echo esc_html__('Páginas Ativas', 'vsl-player'); ?></label></th>
                <td>
                    <select name="vsl_reveal_pages[]" id="vsl_reveal_pages" multiple="multiple" class="vsl-select2-pages" style="width: 100%;">
                        <?php
                        $pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'ASC'));
                        foreach ($pages as $page) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($page->ID),
                                in_array($page->ID, $selected_pages) ? 'selected="selected"' : '',
                                esc_html($page->post_title)
                            );
                        }
                        ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Selecione as páginas onde a funcionalidade de ocultação será ativada.', 'vsl-player'); ?></p>
                </td>
            </tr>
        </table>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Initialize Select2 on the pages dropdown
                $('#vsl_reveal_pages').select2({
                    placeholder: "<?php echo esc_js(__('Selecione as páginas...', 'vsl-player')); ?>",
                    allowClear: true,
                    width: '100%',
                    dropdownAutoWidth: true,
                    closeOnSelect: false,
                    language: {
                        noResults: function() {
                            return "<?php echo esc_js(__('Nenhuma página encontrada', 'vsl-player')); ?>";
                        }
                    }
                });
            });
        </script>
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
            
            // Save player color
            if (isset($_POST['vsl_player_color'])) {
                update_post_meta($post_id, '_vsl_player_color', sanitize_text_field($_POST['vsl_player_color']));
            }
            
            // Save fake progress
            $fake_progress = isset($_POST['vsl_fake_progress']) ? '1' : '0';
            update_post_meta($post_id, '_vsl_fake_progress', $fake_progress);
            
            // Save progress color
            if (isset($_POST['vsl_progress_color'])) {
                update_post_meta($post_id, '_vsl_progress_color', sanitize_text_field($_POST['vsl_progress_color']));
            }
            
            // Salvar opções de estilo ao pausar
            if (isset($_POST['vsl_pause_style'])) {
                update_post_meta($post_id, '_vsl_pause_style', sanitize_text_field($_POST['vsl_pause_style']));
            }
            
            if (isset($_POST['vsl_pause_color'])) {
                update_post_meta($post_id, '_vsl_pause_color', sanitize_text_field($_POST['vsl_pause_color']));
            }
            
            if (isset($_POST['vsl_pause_image'])) {
                update_post_meta($post_id, '_vsl_pause_image', sanitize_text_field($_POST['vsl_pause_image']));
            }
            
            // Salvar opção de ocultar botão de play
            $hide_pause_button = isset($_POST['vsl_hide_pause_button']) ? '1' : '0';
            update_post_meta($post_id, '_vsl_hide_pause_button', $hide_pause_button);
            
            // Salvar opção de ativar resume player
            $enable_resume_player = isset($_POST['vsl_enable_resume_player']) ? '1' : '0';
            update_post_meta($post_id, '_vsl_enable_resume_player', $enable_resume_player);
            
            // Salvar opções de cores para o Resume Player
            if (isset($_POST['vsl_resume_overlay_color'])) {
                update_post_meta($post_id, '_vsl_resume_overlay_color', sanitize_text_field($_POST['vsl_resume_overlay_color']));
            }
            
            if (isset($_POST['vsl_resume_button_color'])) {
                update_post_meta($post_id, '_vsl_resume_button_color', sanitize_text_field($_POST['vsl_resume_button_color']));
            }
            
            if (isset($_POST['vsl_resume_button_hover_color'])) {
                update_post_meta($post_id, '_vsl_resume_button_hover_color', sanitize_text_field($_POST['vsl_resume_button_hover_color']));
            }
            
            // Salvar opções de revelar oferta
            $enable_offer_reveal = isset($_POST['vsl_enable_offer_reveal']) ? '1' : '0';
            update_post_meta($post_id, '_vsl_enable_offer_reveal', $enable_offer_reveal);
            
            if (isset($_POST['vsl_offer_reveal_class'])) {
                update_post_meta($post_id, '_vsl_offer_reveal_class', sanitize_text_field($_POST['vsl_offer_reveal_class']));
            }
            
            if (isset($_POST['vsl_offer_reveal_time'])) {
                update_post_meta($post_id, '_vsl_offer_reveal_time', absint($_POST['vsl_offer_reveal_time']));
            }
            
            if (isset($_POST['vsl_offer_reveal_pages'])) {
                update_post_meta($post_id, '_vsl_offer_reveal_pages', array_map('absint', $_POST['vsl_offer_reveal_pages']));
            }
            
            $offer_reveal_persist = isset($_POST['vsl_offer_reveal_persist']) ? '1' : '0';
            update_post_meta($post_id, '_vsl_offer_reveal_persist', $offer_reveal_persist);
            
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
            
            // Save selected pages
            if (isset($_POST['vsl_reveal_pages'])) {
                update_post_meta($post_id, '_vsl_reveal_pages', array_map('absint', $_POST['vsl_reveal_pages']));
            }
        }
    }

    /**
     * Set YouTube thumbnail as featured image
     */
    public function set_youtube_thumbnail($post_id, $youtube_url) {
        // Extract video ID from URL
        preg_match('/(?:v=|be\/)([^&\?\/]+)/', $youtube_url, $matches);
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
        
        // Reorganizar colunas para um layout mais integrado
        $new_columns['cb'] = $columns['cb'];
        $new_columns['integrated_view'] = __('VSL Otimizada', 'vsl-player');
        $new_columns['shortcode'] = __('Shortcode', 'vsl-player');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /**
     * Content for custom columns in VSL Player CPT
     */
    public function vsl_custom_column_content($column, $post_id) {
        switch ($column) {
            case 'integrated_view':
                echo '<div class="vsl-integrated-view">';
                
                // Thumbnail com overlay do título
                if (has_post_thumbnail($post_id)) {
                    echo '<div class="vsl-thumbnail">';
                    echo get_the_post_thumbnail($post_id, array(120, 68));
                    echo '</div>';
                } else {
                    echo '<div class="vsl-thumbnail vsl-no-thumbnail">';
                    echo '<div class="no-thumbnail">' . esc_html__('Sem thumbnail', 'vsl-player') . '</div>';
                    echo '</div>';
                }
                
                // Título
                echo '<div class="vsl-title">';
                echo '<a href="' . get_edit_post_link($post_id) . '" class="row-title">' . esc_html(get_the_title($post_id)) . '</a>';
                echo '</div>';
                
                echo '</div>';
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
        $new_columns['class'] = __('Classe CSS', 'vsl-player');
        $new_columns['time'] = __('Tempo (s)', 'vsl-player');
        $new_columns['pages'] = __('Páginas Ativas', 'vsl-player');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /**
     * Content for custom columns in VSL Reveal CPT
     */
    public function reveal_custom_column_content($column, $post_id) {
        switch ($column) {                
            case 'class':
                $css_class = get_post_meta($post_id, '_vsl_reveal_class', true);
                echo esc_html($css_class);
                break;
                
            case 'time':
                $reveal_time = get_post_meta($post_id, '_vsl_reveal_time', true);
                echo esc_html($reveal_time);
                break;
                
            case 'pages':
                $selected_pages = get_post_meta($post_id, '_vsl_reveal_pages', true);
                if (is_array($selected_pages) && !empty($selected_pages)) {
                    $page_names = array();
                    foreach ($selected_pages as $page_id) {
                        $page_title = get_the_title($page_id);
                        if ($page_title) {
                            $page_names[] = $page_title;
                        }
                    }
                    if (!empty($page_names)) {
                        echo esc_html(implode(', ', array_slice($page_names, 0, 3)));
                        if (count($page_names) > 3) {
                            echo esc_html(' (' . (count($page_names) - 3) . ' mais)');
                        }
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Get YouTube video ID from a URL
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
        if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $matches)) {
            $video_id = $matches[1];
        } 
        // Match youtu.be URLs
        else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $matches)) {
            $video_id = $matches[1];
        }
        // Match embed URLs
        else if (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $matches)) {
            $video_id = $matches[1];
        }
        // Match v= param
        else if (preg_match('/v=([^\&\?\/]+)/', $url, $matches)) {
            $video_id = $matches[1];
        }
        
        return $video_id;
    }
    
    /**
     * Get the SVG player button with custom color
     *
     * @param int $post_id The post ID to get the color from
     * @return string The SVG content with custom color
     */
    private function get_player_svg($post_id) {
        // Get the color from post meta or use default
        $player_color = get_post_meta($post_id, '_vsl_player_color', true);
        if (empty($player_color)) {
            $player_color = '#617be5';
        }
        
        // Path to SVG file
        $svg_path = VSL_PLAYER_DIR . 'assets/player-thumb-youtube.svg';
        
        // Check if file exists
        if (!file_exists($svg_path)) {
            return '';
        }
        
        // Read SVG content
        $svg_content = file_get_contents($svg_path);
        
        // Replace colors
        // O SVG usa #7440B6 como cor padrão
        $svg_content = str_replace('fill="#7440B6"', 'fill="' . esc_attr($player_color) . '"', $svg_content);
        $svg_content = str_replace('fill="#617be5"', 'fill="' . esc_attr($player_color) . '"', $svg_content);
        $svg_content = str_replace('stroke="#7440B6"', 'stroke="' . esc_attr($player_color) . '"', $svg_content);
        $svg_content = str_replace('stroke="#617be5"', 'stroke="' . esc_attr($player_color) . '"', $svg_content);
        
        // Add class to SVG for styling
        $svg_content = str_replace('<svg ', '<svg class="vsl-play-button-svg" ', $svg_content);
        
        return $svg_content;
    }

    /**
     * VSL Player shortcode callback
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function vsl_player_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'vsl_player');
        
        $post_id = absint($atts['id']);
        
        // Check if post exists and is published
        if (empty($post_id) || get_post_status($post_id) !== 'publish' || get_post_type($post_id) !== 'vsl_player') {
            return '';
        }
        
        // Get metadata
        $youtube_url = get_post_meta($post_id, '_vsl_youtube_url', true);
        $player_color = get_post_meta($post_id, '_vsl_player_color', true);
        $fake_progress = get_post_meta($post_id, '_vsl_fake_progress', true);
        $progress_color = get_post_meta($post_id, '_vsl_progress_color', true);
        
        // Novas opções para o estilo ao pausar
        $pause_style = get_post_meta($post_id, '_vsl_pause_style', true);
        $pause_color = get_post_meta($post_id, '_vsl_pause_color', true);
        $pause_image_id = get_post_meta($post_id, '_vsl_pause_image', true);
        $hide_pause_button = get_post_meta($post_id, '_vsl_hide_pause_button', true);
        $enable_resume_player = get_post_meta($post_id, '_vsl_enable_resume_player', true);
        
        // Opções de cores para o Resume Player
        $resume_overlay_color = get_post_meta($post_id, '_vsl_resume_overlay_color', true);
        $resume_button_color = get_post_meta($post_id, '_vsl_resume_button_color', true);
        $resume_button_hover_color = get_post_meta($post_id, '_vsl_resume_button_hover_color', true);
        
        // Normalizar o valor de fake_progress para booleano
        $fake_progress = ($fake_progress === '1' || $fake_progress === true);
        $hide_pause_button = ($hide_pause_button === '1' || $hide_pause_button === true);
        $enable_resume_player = ($enable_resume_player === '1' || $enable_resume_player === true);
        
        // Extract YouTube video ID from URL
        $video_id = $this->get_youtube_video_id($youtube_url);
        
        if (empty($video_id)) {
            return '';
        }
        
        // Generate player ID
        $player_id = 'vsl-player-' . $post_id;
        
        // Build the player container
        $output = '<div id="' . esc_attr($player_id) . '" class="vsl-player-container" data-vsl-id="' . esc_attr($post_id) . '" data-video-id="' . esc_attr($video_id) . '" data-player-color="' . esc_attr($player_color) . '" data-fake-progress="' . ($fake_progress ? 'true' : 'false') . '" data-progress-color="' . esc_attr($progress_color) . '" data-pause-style="' . esc_attr($pause_style) . '" data-pause-color="' . esc_attr($pause_color) . '" data-pause-image="' . esc_attr($pause_image_id) . '" data-hide-pause-button="' . ($hide_pause_button ? 'true' : 'false') . '" data-enable-resume-player="' . ($enable_resume_player ? 'true' : 'false') . '" data-resume-overlay-color="' . esc_attr($resume_overlay_color) . '" data-resume-button-color="' . esc_attr($resume_button_color) . '" data-resume-button-hover-color="' . esc_attr($resume_button_hover_color) . '">';
        
        // Get custom SVG with player color
        $player_svg = $this->get_player_svg($post_id);
        
        // Add overlays
        $output .= '<div class="vsl-start-overlay">';
        if (!empty($player_svg)) {
            $output .= $player_svg; // Use custom SVG
        } else {
            $output .= '<div class="vsl-play-button"></div>'; // Fallback to CSS button
        }
        $output .= '</div>';
        
        $output .= '<div class="vsl-playing-overlay"></div>';
        $output .= '<div class="vsl-pause-overlay">';
        $output .= '<div class="vsl-play-button"></div>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        // Enqueue necessary scripts
        wp_enqueue_script('vsl-player-youtube');
        wp_enqueue_style('vsl-player-youtube');
        
        // Se a barra de progresso falsa estiver ativada, carregue os scripts e estilos
        if ($fake_progress) {
            wp_enqueue_script('vsl-player-progress-bar');
            wp_enqueue_style('vsl-player-progress-bar');
        }
        
        return $output;
    }

    /**
     * Add custom CSS to admin head
     */
    public function admin_custom_css() {
        // Só aplicar os estilos na página de listagem do CPT vsl_player
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'vsl_player' || $screen->base !== 'edit') {
            return;
        }
        ?>
        <style>
            /* Melhorar o alinhamento entre a thumbnail e o título na listagem do CPT */
            .wp-list-table .column-integrated_view {
                width: 50%;
            }
            
            .vsl-integrated-view {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .vsl-thumbnail {
                width: 120px;
                height: 68px;
                overflow: hidden;
                border-radius: 4px;
                flex-shrink: 0;
                background-color: #f0f0f0;
                position: relative;
            }
            
            .vsl-thumbnail img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            
            .vsl-no-thumbnail {
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: #f0f0f0;
                color: #666;
                font-size: 12px;
            }
            
            .vsl-title {
                flex-grow: 1;
            }
            
            .vsl-title .row-title {
                font-weight: 600;
                font-size: 14px;
                display: block;
                margin-bottom: 5px;
            }
            
            /* Estilos para o container de shortcode */
            .shortcode-container {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .shortcode-field {
                flex-grow: 1;
                font-family: monospace;
                padding: 5px 8px;
                background-color: #f7f7f7;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
        </style>
        <?php
    }
}
