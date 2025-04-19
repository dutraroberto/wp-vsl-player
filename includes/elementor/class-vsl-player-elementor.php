<?php
/**
 * Elementor integration for WP VSL Player
 *
 * Registers the VSL Player widget in Elementor.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'elementor/widgets/widgets_registered', function() {
    // Only proceed if Elementor is loaded
    if ( ! defined( 'ELEMENTOR_PATH' ) ) {
        return;
    }
    
    // Include the widget class
    require_once VSL_PLAYER_DIR . 'includes/elementor/widgets/class-vsl-player-widget.php';
    
    // Register the widget
    \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new VSL_Player_Widget() );
} );
