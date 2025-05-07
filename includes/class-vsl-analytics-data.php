<?php
/**
 * Classe responsável pelo processamento de dados de analytics
 *
 * @package    VSL_Player
 * @subpackage VSL_Player/includes
 * @author     Roberto Dutra
 * @since      1.4.1
 */

class VSL_Analytics_Data {
    
    /**
     * Inicializa a classe e registra os hooks
     */
    public function __construct() {
        // Registrar endpoints AJAX
        add_action('wp_ajax_vsl_get_analytics_data', array($this, 'get_analytics_data'));
    }
    
    /**
     * Busca e processa os dados de analytics para exibição na interface
     */
    public function get_analytics_data() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vsl_analytics_nonce')) {
            wp_send_json_error('Erro de segurança: nonce inválido');
            exit;
        }
        
        // Pegar os filtros
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        // Aplicar valores padrão
        $video_id = isset($filters['video_id']) ? intval($filters['video_id']) : 0;
        $date_start = isset($filters['date_start']) && !empty($filters['date_start']) ? sanitize_text_field($filters['date_start']) : date('Y-m-d', strtotime('-30 days'));
        $date_end = isset($filters['date_end']) && !empty($filters['date_end']) ? sanitize_text_field($filters['date_end']) : date('Y-m-d');
        
        // Garantir que as datas estão no formato correto (YYYY-MM-DD)
        $date_start = date('Y-m-d', strtotime($date_start));
        $date_end = date('Y-m-d', strtotime($date_end . ' +1 day')); // +1 dia para incluir o último dia na consulta
        
        // Buscar dados do banco de dados
        $session_data = $this->get_sessions_data($video_id, $date_start, $date_end);
        
        // Se não houver dados, retornar erro
        if (empty($session_data)) {
            wp_send_json_success(array(
                'sessions' => array(),
                'summary' => array(
                    'total_views' => 0,
                    'avg_watch_time' => '0s',
                    'completion_rate' => 0
                ),
                'retention' => array(
                    'labels' => array(),
                    'values' => array()
                )
            ));
            exit;
        }
        
        // Processar dados de retenção
        $retention_data = $this->calculate_retention_data($session_data, $video_id);
        
        // Calcular métricas de resumo
        $summary = $this->calculate_summary_metrics($session_data, $video_id);
        
        // Preparar resposta
        $response = array(
            'sessions' => $session_data,
            'summary' => $summary,
            'retention' => $retention_data
        );
        
        // Enviar resposta como JSON
        wp_send_json_success($response);
        exit;
    }
    
    /**
     * Busca os dados de sessões do banco de dados
     * 
     * @param int $video_id ID do vídeo para filtrar (0 para todos)
     * @param string $date_start Data inicial no formato Y-m-d
     * @param string $date_end Data final no formato Y-m-d
     * @return array Array de sessões
     */
    private function get_sessions_data($video_id, $date_start, $date_end) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vsl_sessions';
        
        // Construir consulta SQL
        $sql = "SELECT * FROM {$table_name} WHERE first_impression >= %s AND first_impression <= %s";
        $params = array($date_start, $date_end);
        
        // Adicionar filtro de vídeo se especificado
        if ($video_id > 0) {
            $sql .= " AND video_post_id = %d";
            $params[] = $video_id;
        }
        
        // Ordenar por data
        $sql .= " ORDER BY first_impression DESC";
        
        // Preparar e executar a consulta
        $query = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return $results;
    }
    
    /**
     * Calcula as métricas de resumo com base nos dados das sessões
     * 
     * @param array $sessions Dados das sessões
     * @param int $video_id ID do vídeo (usado para buscar a duração)
     * @return array Métricas de resumo
     */
    private function calculate_summary_metrics($sessions, $video_id) {
        $total_views = count($sessions);
        $total_progress = 0;
        $completed = 0;
        
        // Tentar buscar a duração do vídeo se um ID específico foi fornecido
        $video_duration = 0;
        if ($video_id > 0) {
            $video_duration = intval(get_post_meta($video_id, '_vsl_player_video_length', true));
        }
        
        // Calcular métricas
        foreach ($sessions as $session) {
            $total_progress += intval($session['max_progress_sec']);
            
            // Considerar completado se assistiu pelo menos 95% do vídeo ou
            // se a flag 'completed' estiver marcada como 1
            if ($session['completed'] == 1) {
                $completed++;
            } elseif ($video_duration > 0 && intval($session['max_progress_sec']) >= ($video_duration * 0.95)) {
                $completed++;
            }
        }
        
        // Calcular tempo médio de visualização
        $avg_watch_time = $total_views > 0 ? round($total_progress / $total_views) : 0;
        
        // Formatar tempo médio de visualização
        $formatted_time = $this->format_seconds($avg_watch_time);
        
        // Calcular taxa de conclusão
        $completion_rate = $total_views > 0 ? round(($completed / $total_views) * 100) : 0;
        
        return array(
            'total_views' => $total_views,
            'avg_watch_time' => $formatted_time,
            'completion_rate' => $completion_rate
        );
    }
    
    /**
     * Calcula os dados de retenção de audiência
     * 
     * @param array $sessions Dados das sessões
     * @param int $video_id ID do vídeo
     * @return array Dados de retenção formatados para Chart.js
     */
    private function calculate_retention_data($sessions, $video_id) {
        // Determinar a duração máxima do vídeo
        $max_duration = 0;
        
        // Se temos um ID específico, buscar a duração do vídeo
        if ($video_id > 0) {
            $duration = intval(get_post_meta($video_id, '_vsl_player_video_length', true));
            if ($duration > 0) {
                $max_duration = $duration;
            }
        }
        
        // Se não temos duração definida, calcular com base nas sessões
        if ($max_duration == 0) {
            foreach ($sessions as $session) {
                $max_duration = max($max_duration, intval($session['max_progress_sec']));
            }
        }
        
        // Se ainda não temos duração, definir um valor padrão
        if ($max_duration == 0) {
            $max_duration = 300; // 5 minutos como padrão
        }
        
        // Determinar os intervalos para o gráfico de retenção
        // Queremos cerca de 20-30 pontos no gráfico para não ficar muito carregado
        $interval = max(5, ceil($max_duration / 25)); // No mínimo 5 segundos
        
        // Criar pontos de tempo para o gráfico
        $time_points = range(0, $max_duration, $interval);
        
        // Garantir que o último ponto seja a duração máxima
        if (end($time_points) != $max_duration) {
            $time_points[] = $max_duration;
        }
        
        // Contar quantas sessões assistiram até cada ponto
        $retention_counts = array_fill(0, count($time_points), 0);
        $total_sessions = count($sessions);
        
        foreach ($sessions as $session) {
            $progress = intval($session['max_progress_sec']);
            
            foreach ($time_points as $index => $time_point) {
                if ($progress >= $time_point) {
                    $retention_counts[$index]++;
                }
            }
        }
        
        // Calcular percentual de retenção
        $retention_percentages = array();
        if ($total_sessions > 0) {
            foreach ($retention_counts as $count) {
                $retention_percentages[] = round(($count / $total_sessions) * 100, 1);
            }
        }
        
        return array(
            'labels' => $time_points,
            'values' => $retention_percentages
        );
    }
    
    /**
     * Formata segundos em uma string legível (MM:SS ou HH:MM:SS)
     * 
     * @param int $seconds Número de segundos
     * @return string Tempo formatado
     */
    private function format_seconds($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $seconds = $seconds % 60;
            return sprintf('%d:%02d', $minutes, $seconds);
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $seconds = $seconds % 60;
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }
    }
}

// Inicializar a classe
$vsl_analytics_data = new VSL_Analytics_Data();
