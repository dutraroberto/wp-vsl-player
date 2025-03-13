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
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vsl-player-nonce')) {
            wp_send_json_error(array('message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'vsl-player')));
        }
        
        // Get license key from POST
        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        
        if (empty($license_key)) {
            wp_send_json_error(array('message' => __('Por favor, insira uma chave de licença.', 'vsl-player')));
        }
        
        // Validate license with API
        $result = $this->validate_license($license_key);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Validate license with remote API
     * Note: This is a placeholder function. Actual implementation will happen in a future phase.
     */
    private function validate_license($license_key) {
        // This is a placeholder. In a real implementation, this would call an external API
        // to validate the license key. For now, we'll simulate a successful validation.
        
        // For testing purposes only - consider any license key starting with "VALID" as valid
        $is_valid = (strpos($license_key, 'VALID') === 0);
        
        if ($is_valid) {
            // Save license data
            update_option('vsl_player_license_key', $license_key);
            update_option('vsl_player_license_status', 'active');
            
            // Set expiry date to 1 year from now
            $expiry_date = date('d/m/Y', strtotime('+1 year'));
            update_option('vsl_player_license_expiry', $expiry_date);
            
            return array(
                'success' => true,
                'message' => __('Licença validada com sucesso!', 'vsl-player'),
                'status' => 'active',
                'expiry' => $expiry_date
            );
        } else {
            // Invalid license
            update_option('vsl_player_license_status', 'inactive');
            delete_option('vsl_player_license_expiry');
            
            return array(
                'success' => false,
                'message' => __('Chave de licença inválida. Por favor, verifique e tente novamente.', 'vsl-player'),
                'status' => 'inactive'
            );
        }
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
}
