<?php
/**
 * Plugin Name: VSL Player Otimizado
 * Plugin URI: https://mundowp.com.br/plugins/vsl-player
 * Description: Plugin WordPress que integra a API oficial do YouTube para criar um player otimizado para VSLs (Video Sales Letters).
 * Version: 1.0.0
 * Author: MundoWP
 * Author URI: https://mundowp.com.br
 * Text Domain: vsl-player
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
