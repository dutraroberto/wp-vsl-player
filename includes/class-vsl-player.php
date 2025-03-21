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
        
        // Public-facing functionality
        require_once VSL_PLAYER_DIR . 'public/class-vsl-player-public.php';
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
        
        // Initialize public-facing functionality
        $public = new VSL_Player_Public();
        
        // Register activation and deactivation hooks
        register_activation_hook(VSL_PLAYER_DIR . 'vsl-player.php', array($this, 'activate'));
        register_deactivation_hook(VSL_PLAYER_DIR . 'vsl-player.php', array($this, 'deactivate'));
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {

        // Add shortcode for VSL Player
        add_shortcode('vsl_player', array($this, 'vsl_player_shortcode'));
        
        // Add hooks for ajax handlers
        add_action('wp_ajax_vsl_load_progress_bar_assets', array($this, 'load_progress_bar_assets'));
        add_action('wp_ajax_nopriv_vsl_load_progress_bar_assets', array($this, 'load_progress_bar_assets'));
        
        // Add ajax handler for getting image URL
        add_action('wp_ajax_vsl_get_image_url', array($this, 'get_image_url_ajax'));
        add_action('wp_ajax_nopriv_vsl_get_image_url', array($this, 'get_image_url_ajax'));

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
