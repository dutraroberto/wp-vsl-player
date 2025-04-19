<?php
/**
 * VSL Player Elementor Widget
 *
 * @package WP_VSL_Player
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class VSL_Player_Widget extends Widget_Base {

    public function get_name() {
        return 'vsl_player';
    }

    public function get_title() {
        return __( 'VSL Player', 'wp-vsl-player' );
    }

    public function get_icon() {
        return 'eicon-video-camera';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'wp-vsl-player' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'vsl_id',
            [
                'label' => __( 'Vídeo', 'wp-vsl-player' ),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_vsl_options(),
                'multiple' => false,
                'label_block' => true,
            ]
        );

        $this->end_controls_section();
    }

    private function get_vsl_options() {
        $options = [];
        $posts = get_posts([
            'post_type' => 'vsl_player',
            'numberposts' => -1,
        ]);
        if ( $posts ) {
            foreach ( $posts as $post ) {
                $options[ $post->ID ] = $post->post_title;
            }
        }
        return $options;
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if ( ! empty( $settings['vsl_id'] ) ) {
            echo do_shortcode( '[vsl_player id="' . esc_attr( $settings['vsl_id'] ) . '"]' );
        }
    }
}
