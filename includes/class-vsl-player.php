<?php
/**
 * The main plugin class
 */
class VSL_Player {

    /**
     * Initialize the plugin
     */
    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Admin class
        require_once VSL_PLAYER_DIR . 'admin/class-vsl-player-admin.php';
        
        // Custom Post Types
        require_once VSL_PLAYER_DIR . 'includes/class-vsl-player-cpt.php';
        
        // License handler
        require_once VSL_PLAYER_DIR . 'includes/class-vsl-player-license.php';
    }

    /**
     * Run the plugin
     */
    public function run() {
        // Initialize admin
        $admin = new VSL_Player_Admin();
        
        // Initialize custom post types
        $cpt = new VSL_Player_CPT();
        
        // Initialize license
        $license = new VSL_Player_License();
        
        // Register activation and deactivation hooks
        register_activation_hook(VSL_PLAYER_DIR . 'vsl-player.php', array($this, 'activate'));
        register_deactivation_hook(VSL_PLAYER_DIR . 'vsl-player.php', array($this, 'deactivate'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Actions to perform on activation
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Actions to perform on deactivation
        flush_rewrite_rules();
    }
}
