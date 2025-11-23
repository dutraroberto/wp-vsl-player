<?php
/**
 * Plugin Name: WP VSL Player
 * Plugin URI: https://mundowp.com.br/plugins/wp-vsl-player/
 * Description: Crie facilmente player otimizados para Vendas!
 * Version: 1.5.0
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
define('VSL_PLAYER_VERSION', '1.5.0');
define('VSL_PLAYER_DIR', plugin_dir_path(__FILE__));
define('VSL_PLAYER_URL', plugin_dir_url(__FILE__));

// Import classes for plugin update checker
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

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

/**
 * Plugin Update Checker 
 * Implementa sistema de atualização automática do plugin através de servidor personalizado
 */
$update_checker_path = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
if (file_exists($update_checker_path)) {
    require $update_checker_path;
    
    $updateChecker = PucFactory::buildUpdateChecker(
        'https://plugins.mundowp.com.br/wp-vsl-player/info.json', // URL do arquivo JSON com informações de atualização
        __FILE__, // Caminho para o arquivo principal do plugin
        'wp-vsl-player' // Slug único do plugin
    );
    
    // Adiciona filtro para mostrar mais detalhes no modal de atualização do WordPress
    add_filter('puc_view_details_link_position-wp-vsl-player', function() {
        return 'before';
    });
    
    // Adiciona um hook para verificar por atualizações quando o usuário acessa a página de plugins
    add_action('load-plugins.php', function() use ($updateChecker) {
        if (!wp_doing_ajax()) {
            $updateChecker->checkForUpdates();
        }
    });
} else {
    // Adiciona um aviso no painel administrativo se o arquivo não for encontrado
    add_action('admin_notices', function() {
        echo '<div class="notice notice-warning"><p>';
        echo 'O sistema de atualização automática do plugin WP-VSL-Player não está disponível. ';
        echo 'A pasta <code>plugin-update-checker</code> está faltando.';
        echo '</p></div>';
    });
}