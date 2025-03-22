<?php
/**
 * Plugin Name: WP VSL Player
 * Plugin URI: https://mundowp.com.br/plugins/wp-vsl-player/
 * Description: Crie facilmente player otimizados para Vendas!
 * Version: 1.2.0
 * Author: Roberto Dutra
 * Author URI: https://mundowp.com.br
 * Text Domain: wp-vsl-player
 * Domain Path: /languages
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('VSL_PLAYER_VERSION', '1.0.0');
define('VSL_PLAYER_DIR', plugin_dir_path(__FILE__));
define('VSL_PLAYER_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once VSL_PLAYER_DIR . 'includes/class-vsl-player.php';

// Initialize the plugin
function run_vsl_player() {
    $plugin = new VSL_Player();
    $plugin->run();
}
run_vsl_player();

// Register deactivation hook to clean up cron
register_deactivation_hook(__FILE__, array('VSL_Player_License', 'deactivate'));