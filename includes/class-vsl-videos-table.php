<?php
/**
 * Classe para gerenciar a tabela wp_vsl_videos
 *
 * Responsável por inserir e atualizar informações do vídeo na tabela wp_vsl_videos
 *
 * @package    VSL_Player
 * @subpackage VSL_Player/includes
 * @author     Roberto Dutra
 * @since      1.4.0
 */

class VSL_Videos_Table {

    /**
     * Construtor da classe
     */
    public function __construct() {
        // Usar wp_insert_post que é acionado para todos os posts novos ou atualizados
        add_action('wp_insert_post', array($this, 'process_vsl_post'), 10, 3);
        
        // Garantir que todos os vídeos existentes sejam adicionados na tabela
        add_action('admin_init', array($this, 'populate_existing_videos'));
    }

    /**
     * Processa um post do tipo vsl_player quando ele é inserido ou atualizado
     * Usa o hook wp_insert_post que é mais confiável para capturar a criação e atualização de posts
     *
     * @param int     $post_id     ID do post
     * @param WP_Post $post        Objeto do post
     * @param bool    $update      True se for uma atualização, false se for uma criação
     */
    public function process_vsl_post($post_id, $post, $update) {
        // Verificar se é do tipo vsl_player
        if (get_post_type($post_id) !== 'vsl_player') {
            return;
        }
        
        // Verificar se estamos em um autosave ou revisão
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Verificar se estamos em um save automatizado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verificar se o post está publicado
        if ($post->post_status !== 'publish') {
            return;
        }

        // Verificar permissões de edição
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Obter a URL do YouTube salva nos metadados
        $youtube_url = get_post_meta($post_id, '_vsl_youtube_url', true);
        if (empty($youtube_url)) {
            return;
        }

        // Extrair o ID do vídeo do YouTube da URL
        $youtube_video_id = $this->extract_youtube_id($youtube_url);
        if (empty($youtube_video_id)) {
            return;
        }

        // Inserir ou atualizar o registro na tabela
        $this->save_video_data($post_id, $youtube_video_id);
    }

    /**
     * Extrai o ID do vídeo a partir da URL do YouTube
     *
     * @param string $url URL do YouTube
     * @return string|null ID do vídeo ou null se não for encontrado
     */
    private function extract_youtube_id($url) {
        $pattern = 
            '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Salva os dados do vídeo na tabela wp_vsl_videos
     *
     * @param int    $post_id         ID do post
     * @param string $youtube_video_id ID do vídeo do YouTube
     */
    private function save_video_data($post_id, $youtube_video_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vsl_videos';
        
        // Verificar se já existe um registro para este post_id
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT video_post_id FROM $table_name WHERE video_post_id = %d",
            $post_id
        ));
        
        if ($existing) {
            // Atualizar registro existente
            $wpdb->update(
                $table_name,
                array(
                    'youtube_video_id' => $youtube_video_id,
                    // A duração será atualizada posteriormente
                ),
                array('video_post_id' => $post_id),
                array('%s'),
                array('%d')
            );
        } else {
            // Inserir novo registro
            $wpdb->insert(
                $table_name,
                array(
                    'video_post_id' => $post_id,
                    'youtube_video_id' => $youtube_video_id,
                    'video_duration_sec' => 0, // Valor padrão, será atualizado posteriormente
                ),
                array('%d', '%s', '%d')
            );
        }
    }

    /**
     * Obtém os dados de um vídeo da tabela wp_vsl_videos
     *
     * @param int $post_id ID do post
     * @return object|null Objeto com os dados do vídeo ou null se não encontrado
     */
    public function get_video_data($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vsl_videos';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE video_post_id = %d",
            $post_id
        ));
    }

    /**
     * Atualiza a duração de um vídeo na tabela wp_vsl_videos
     *
     * @param int $post_id ID do post
     * @param int $duration Duração do vídeo em segundos
     * @return bool True se atualizado com sucesso, false caso contrário
     */
    public function update_video_duration($post_id, $duration) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vsl_videos';
        
        $result = $wpdb->update(
            $table_name,
            array('video_duration_sec' => $duration),
            array('video_post_id' => $post_id),
            array('%d'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Popula a tabela wp_vsl_videos com todos os vídeos existentes
     * 
     * Esta função é executada no admin_init e verifica quais posts do tipo
     * 'vsl_player' ainda não estão na tabela wp_vsl_videos
     */
    public function populate_existing_videos() {
        // Usar transient para evitar execução a cada carregamento de página
        if (get_transient('vsl_videos_populated')) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'vsl_videos';
        
        // Verificar se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return;
        }
        
        // Buscar todos os IDs de posts do tipo 'vsl_player'
        $vsl_posts = get_posts(array(
            'post_type' => 'vsl_player',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ));
        
        if (empty($vsl_posts)) {
            // Definir transient para evitar verificações freqüentes
            set_transient('vsl_videos_populated', true, DAY_IN_SECONDS);
            return;
        }
        
        // Obter IDs que já estão na tabela
        $existing_ids = $wpdb->get_col("SELECT video_post_id FROM $table_name");
        
        $count = 0;
        foreach ($vsl_posts as $post_id) {
            // Pular se já existe na tabela
            if (in_array($post_id, $existing_ids)) {
                continue;
            }
            
            // Obter URL do YouTube
            $youtube_url = get_post_meta($post_id, '_vsl_youtube_url', true);
            if (empty($youtube_url)) {
                continue;
            }
            
            // Extrair ID do vídeo do YouTube
            $youtube_video_id = $this->extract_youtube_id($youtube_url);
            if (empty($youtube_video_id)) {
                continue;
            }
            
            // Salvar na tabela
            $this->save_video_data($post_id, $youtube_video_id);
            $count++;
        }
        
        // Definir transient para evitar verificações freqüentes
        // Se houver atualizações, definir para um período mais curto para verificar novamente em breve
        if ($count > 0) {
            set_transient('vsl_videos_populated', true, HOUR_IN_SECONDS);
        } else {
            set_transient('vsl_videos_populated', true, DAY_IN_SECONDS);
        }
    }
}
