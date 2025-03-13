<?php
/**
 * The admin-specific functionality of the plugin.
 */
class VSL_Player_Admin {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register the menu for the plugin.
     */
    public function add_admin_menu() {
        // Main menu - now pointing to VSLs list
        add_menu_page(
            __('VSL Otimizado', 'vsl-player'),
            __('VSL Otimizado', 'vsl-player'),
            'manage_options',
            'edit.php?post_type=vsl_player',
            null,
            'dashicons-video-alt3',
            30
        );
        
        // Submenu for VSLs (already added via main menu)
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('VSLs Otimizadas', 'vsl-player'),
            __('VSLs Otimizadas', 'vsl-player'),
            'manage_options',
            'edit.php?post_type=vsl_player',
            null
        );
        
        // Submenu for Reveal 
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('Ocultar Sessões', 'vsl-player'),
            __('Ocultar Sessões', 'vsl-player'),
            'manage_options',
            'edit.php?post_type=vsl_reveal',
            null
        );
        
        // Submenu for license management
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('Licença', 'vsl-player'),
            __('Licença', 'vsl-player'),
            'manage_options',
            'vsl-player-license',
            array($this, 'display_license_page')
        );
        
        // Submenu for support
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('Suporte', 'vsl-player'),
            __('Suporte', 'vsl-player'),
            'manage_options',
            'vsl-player-support',
            array($this, 'display_support_page')
        );
    }

    /**
     * Enqueue scripts and styles for the admin area.
     */
    public function enqueue_scripts($hook) {
        // Check if we're on one of our plugin pages
        $vsl_admin_pages = array(
            'toplevel_page_edit',
            'vsl_player_page_vsl-player-license',
            'vsl_player_page_vsl-player-support',
            'edit-vsl_player',
            'vsl_player',
            'edit-vsl_reveal',
            'vsl_reveal'
        );
        
        // Only enqueue on our plugin pages
        $screen = get_current_screen();
        if (!in_array($screen->id, $vsl_admin_pages) && strpos($hook, 'vsl-player') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'vsl-player-admin', 
            VSL_PLAYER_URL . 'admin/css/vsl-player-admin.css', 
            array(), 
            VSL_PLAYER_VERSION, 
            'all'
        );
        
        // Admin JS
        wp_enqueue_script(
            'vsl-player-admin', 
            VSL_PLAYER_URL . 'admin/js/vsl-player-admin.js', 
            array('jquery'), 
            VSL_PLAYER_VERSION, 
            false
        );
        
        // Enqueue WordPress media scripts
        wp_enqueue_media();
        
        // Localize script for AJAX
        wp_localize_script('vsl-player-admin', 'vsl_player_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vsl-player-nonce'),
        ));
    }

    /**
     * Display the license page content.
     */
    public function display_license_page() {
        ?>
        <div class="wrap vsl-player-admin">
            <h1><?php echo esc_html__('VSL Player Otimizado - Licença', 'vsl-player'); ?></h1>
            
            <div class="vsl-license-container">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('vsl_player_license_settings');
                    do_settings_sections('vsl_player_license_settings');
                    
                    // Get license data
                    $license_key = get_option('vsl_player_license_key', '');
                    $license_status = get_option('vsl_player_license_status', 'inactive');
                    $license_expiry = get_option('vsl_player_license_expiry', '');
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Chave de Licença', 'vsl-player'); ?></th>
                            <td>
                                <input type="text" id="vsl_player_license_key" name="vsl_player_license_key" 
                                       value="<?php echo esc_attr($license_key); ?>" class="regular-text" 
                                       placeholder="XXXXX-XXXXX-XXXXX-XXXXX">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Status da Licença', 'vsl-player'); ?></th>
                            <td>
                                <?php
                                $status_class = 'inactive';
                                $status_label = __('Inativa', 'vsl-player');
                                
                                if ($license_status === 'active') {
                                    $status_class = 'active';
                                    $status_label = __('Ativa', 'vsl-player');
                                } elseif ($license_status === 'expired') {
                                    $status_class = 'expired';
                                    $status_label = __('Expirada', 'vsl-player');
                                }
                                ?>
                                <span class="license-status status-<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($license_expiry) && $license_status === 'active') : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Expira em', 'vsl-player'); ?></th>
                            <td>
                                <?php echo esc_html($license_expiry); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <?php submit_button(__('Salvar e Validar Licença', 'vsl-player')); ?>
                </form>
                
                <div class="license-info">
                    <p><?php echo esc_html__('O VSL Player Otimizado requer uma licença válida para funcionar. A licença permite que você utilize o plugin em um único domínio.', 'vsl-player'); ?></p>
                    <p>
                        <?php 
                        echo sprintf(
                            esc_html__('Para adquirir uma licença ou renovar a existente, visite nosso site: %s', 'vsl-player'),
                            '<a href="https://mundowp.com.br/plugins/vsl-player" target="_blank">mundowp.com.br/plugins/vsl-player</a>'
                        ); 
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display the support page content.
     */
    public function display_support_page() {
        ?>
        <div class="wrap vsl-player-admin">
            <h1><?php echo esc_html__('VSL Player Otimizado - Suporte', 'vsl-player'); ?></h1>
            
            <div class="vsl-support-container">
                <div class="support-section">
                    <h2><?php echo esc_html__('Ajuda', 'vsl-player'); ?></h2>
                    <p>
                        <?php 
                        echo sprintf(
                            esc_html__('Precisa de ajuda? Fale conosco pelo WhatsApp: %s', 'vsl-player'),
                            '<a href="https://mundowp.com.br/whatsapp" target="_blank" class="whatsapp-link"><span class="dashicons dashicons-whatsapp"></span> Suporte</a>'
                        ); 
                        ?>
                    </p>
                </div>
                
                <div class="license-section">
                    <h2><?php echo esc_html__('Aquisição de Licença', 'vsl-player'); ?></h2>
                    <p>
                        <?php 
                        echo sprintf(
                            esc_html__('Quer adquirir uma licença? Visite nosso site: %s', 'vsl-player'),
                            '<a href="https://mundowp.com.br/plugins/vsl-player" target="_blank">mundowp.com.br/plugins/vsl-player</a>'
                        ); 
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}
