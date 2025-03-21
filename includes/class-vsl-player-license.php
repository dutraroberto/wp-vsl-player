<?php
/**
 * Handle license validation for VSL Player
 */
class VSL_Player_License {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add AJAX handlers for license validation
        add_action('wp_ajax_vsl_validate_license', array($this, 'ajax_validate_license'));
        
        // Add notices if license is invalid
        add_action('admin_notices', array($this, 'license_notices'));
        
        // Set up cron job for automatic license validation
        add_action('vsl_player_daily_license_check', array($this, 'daily_license_check'));
        
        // Set up cron schedule on plugin activation
        if (!wp_next_scheduled('vsl_player_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'vsl_player_daily_license_check');
        }
        
        // Block access to restricted areas if license is invalid
        add_action('current_screen', array($this, 'restrict_access_if_invalid_license'));
    }

    /**
     * Register settings for the license page
     */
    public function register_settings() {
        register_setting('vsl_player_license_settings', 'vsl_player_license_key');
        register_setting('vsl_player_license_settings', 'vsl_player_license_status');
        register_setting('vsl_player_license_settings', 'vsl_player_license_expiry');
    }

    /**
     * AJAX handler for license validation
     */
    public function ajax_validate_license() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vsl_player_admin_nonce')) {
            wp_send_json_error(array('message' => 'Erro de segurança. Por favor, recarregue a página e tente novamente.'));
        }
        
        // Check for license key
        if (!isset($_POST['license_key']) || empty($_POST['license_key'])) {
            wp_send_json_error(array('message' => 'Por favor, insira uma chave de licença válida.'));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        // Save license key
        update_option('vsl_player_license_key', $license_key);
        
        // Validate license
        $result = $this->validate_license($license_key);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Licença ativada com sucesso! Seu plugin está pronto para uso.',
                'expiry' => isset($result['expiry']) ? $result['expiry'] : ''
            ));
        } else {
            wp_send_json_error(array(
                'message' => isset($result['message']) ? $result['message'] : 'Erro ao validar licença.'
            ));
        }
    }

    /**
     * Scheduled daily license check
     */
    public function daily_license_check() {
        $license_key = get_option('vsl_player_license_key', '');
        
        if (!empty($license_key)) {
            $this->validate_license($license_key, true);
        }
    }

    /**
     * Validate license with remote API
     * 
     * @param string $license_key The license key to validate
     * @param bool $is_background Whether this check is running in the background (default: false)
     * @return array Validation result
     */
    private function validate_license($license_key, $is_background = false) {
        $api_url = 'https://plugins.mundowp.com.br/wp-json/mundowp/v1/validate';
        $domain = $_SERVER['HTTP_HOST'];
        
        $response = wp_remote_post($api_url, array(
            'body' => wp_json_encode(array(
                'license_key' => $license_key,
                'domain' => $domain,
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            // Error in the request
            $error_message = $response->get_error_message();
            
            if ($is_background) {
                // For background checks, default to the previous status if we can't reach the server
                return array(
                    'success' => false,
                    'message' => sprintf(__('Erro ao verificar licença: %s. Por favor, tente novamente mais tarde.', 'vsl-player'), $error_message),
                    'status' => get_option('vsl_player_license_status', 'inactive')
                );
            }
            
            // Update license status as inactive if this was a manual check
            update_option('vsl_player_license_status', 'inactive');
            delete_option('vsl_player_license_expiry');
            
            return array(
                'success' => false,
                'message' => sprintf(__('Erro ao verificar licença: %s. Por favor, tente novamente mais tarde.', 'vsl-player'), $error_message),
                'status' => 'inactive'
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['status']) && $data['status'] === 'success') {
            // License is valid
            update_option('vsl_player_license_key', $license_key);
            update_option('vsl_player_license_status', 'active');
            
            $expiry_date = '';
            if (isset($data['expiration_date'])) {
                // Format expiration date for display
                $expiry_timestamp = strtotime($data['expiration_date']);
                $expiry_date = date_i18n(get_option('date_format'), $expiry_timestamp);
                update_option('vsl_player_license_expiry', $expiry_date);
            }
            
            return array(
                'success' => true,
                'message' => __('Licença ativada com sucesso! Seu plugin está pronto para uso.', 'vsl-player'),
                'status' => 'active',
                'expiry' => $expiry_date
            );
        } else {
            // License is invalid
            update_option('vsl_player_license_status', 'inactive');
            delete_option('vsl_player_license_expiry');
            
            $error_message = isset($data['message']) ? $data['message'] : __('Chave de licença inválida. Por favor, verifique sua chave e tente novamente.', 'vsl-player');
            
            return array(
                'success' => false,
                'message' => $error_message,
                'status' => 'inactive'
            );
        }
    }

    /**
     * Restrict access to certain admin pages if license is invalid
     */
    public function restrict_access_if_invalid_license() {
        // Get current screen
        $screen = get_current_screen();
        
        // Only restrict VSL player pages, not VSL reveal pages
        $restricted_screens = array(
            'vsl_player',           // Add/edit VSL screen
            'edit-vsl_player'        // VSL list screen
        );
        
        // No need to restrict if license is valid
        if ($this->is_license_valid()) {
            return;
        }
        
        // Only restrict access to specific screens
        if (in_array($screen->id, $restricted_screens)) {
            // Display access blocked message and exit
            $this->display_access_blocked_message();
            exit;
        }
    }
    
    /**
     * Display the access blocked message
     */
    private function display_access_blocked_message() {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html__('Acesso Bloqueado', 'vsl-player'); ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: #f0f0f1;
                    margin: 0;
                    padding: 0;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .blocked-container {
                    background: #fff;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
                    max-width: 500px;
                    padding: 40px;
                    text-align: center;
                }
                h2 {
                    color: #d63638;
                    margin-top: 0;
                }
                button {
                    background: #2271b1;
                    border: none;
                    border-radius: 4px;
                    color: #fff;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                    padding: 10px 20px;
                    text-decoration: none;
                    margin-top: 20px;
                }
                button:hover {
                    background: #135e96;
                }
                p {
                    color: #1d2327;
                    font-size: 15px;
                    line-height: 1.5;
                }
            </style>
        </head>
        <body>
            <div class="blocked-container">
                <h2><?php echo esc_html__('Acesso Bloqueado', 'vsl-player'); ?></h2>
                <p><?php echo esc_html__('A criação e edição de VSLs está bloqueada porque sua licença é inválida ou expirou.', 'vsl-player'); ?></p>
                <p><?php echo esc_html__('Por favor, ative sua licença para desbloquear todas as funcionalidades.', 'vsl-player'); ?></p>
                <button onclick="history.back()"><?php echo esc_html__('Voltar', 'vsl-player'); ?></button>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Display admin notices if license is invalid
     */
    public function license_notices() {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only show on our plugin pages or dashboard
        $screen = get_current_screen();
        if (!in_array($screen->id, array('dashboard', 'edit-vsl_player', 'vsl_player', 'vsl_reveal', 'edit-vsl_reveal'))) {
            return;
        }
        
        // Check license status
        $license_status = get_option('vsl_player_license_status', 'inactive');
        
        if ($license_status !== 'active') {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php 
                    echo sprintf(
                        __('O plugin VSL Player Otimizado requer uma licença válida para funcionar. <a href="%s">Clique aqui</a> para ativar sua licença.', 'vsl-player'),
                        admin_url('admin.php?page=vsl-player-license')
                    ); 
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Check if the license is valid
     */
    public function is_license_valid() {
        $license_status = get_option('vsl_player_license_status', 'inactive');
        return ($license_status === 'active');
    }
    
    /**
     * Clean up when plugin is deactivated
     */
    public static function deactivate() {
        // Remove scheduled cron job
        $timestamp = wp_next_scheduled('vsl_player_daily_license_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'vsl_player_daily_license_check');
        }
    }
}
