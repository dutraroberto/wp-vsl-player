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
        
        // Opções de visualização e filtros
        // Converter explicitamente para booleano e garantir que seja true ou false
        $group_urls = false;
        if (isset($filters['group_urls'])) {
            // Tratar diferentes tipos de entrada como "true"
            $group_urls_value = $filters['group_urls'];
            if ($group_urls_value === 'true' || $group_urls_value === '1' || $group_urls_value === 1 || $group_urls_value === true) {
                $group_urls = true;
            }
        }
        $utm_source_filter = isset($filters['utm_source']) ? sanitize_text_field($filters['utm_source']) : '';
        $utm_campaign_filter = isset($filters['utm_campaign']) ? sanitize_text_field($filters['utm_campaign']) : '';
        
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
        
        // Processar dados de dispositivos
        $devices_data = $this->calculate_devices_data($session_data);
        
        // Processar dados de origens de visualização (com opção de agrupar URLs)
        $referrers_data = $this->calculate_referrers_data($session_data, $group_urls);
        
        // Calcular total de cliques em CTA
        $cta_clicks = $this->calculate_cta_clicks($session_data);
        
        // Calcular taxa de cliques no player
        $play_rate = $this->calculate_play_rate($session_data);
        
        // Processar dados de campanhas UTM
        $utm_data = $this->calculate_utm_campaigns($session_data, $utm_source_filter, $utm_campaign_filter);
        
        // Preparar resposta
        $response = array(
            'sessions' => $session_data,
            'summary' => $summary,
            'retention' => $retention_data,
            'devices' => $devices_data,
            'referrers' => $referrers_data,
            'cta_clicks' => $cta_clicks,
            'play_rate' => $play_rate,
            'utm_data' => $utm_data,
            'filters' => array(
                'group_urls' => $group_urls,
                'utm_source' => $utm_source_filter,
                'utm_campaign' => $utm_campaign_filter
            )
        );
        
        // Enviar resposta como JSON
        wp_send_json_success($response);
        exit;
    }
    
    /**
     * Busca os dados de sessões do banco de dados com informações de duração do vídeo
     * 
     * @param int $video_id ID do vídeo para filtrar (0 para todos)
     * @param string $date_start Data inicial no formato Y-m-d
     * @param string $date_end Data final no formato Y-m-d
     * @return array Array de sessões com duração do vídeo
     */
    private function get_sessions_data($video_id, $date_start, $date_end) {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'vsl_sessions';
        $videos_table = $wpdb->prefix . 'vsl_videos';
        
        // Construir consulta SQL com LEFT JOIN para obter a duração do vídeo
        $sql = "SELECT s.*, v.video_duration_sec 
                FROM {$sessions_table} s 
                LEFT JOIN {$videos_table} v ON s.video_post_id = v.video_post_id 
                WHERE s.first_impression >= %s AND s.first_impression <= %s";
        $params = array($date_start, $date_end);
        
        // Adicionar filtro de vídeo se especificado
        if ($video_id > 0) {
            $sql .= " AND s.video_post_id = %d";
            $params[] = $video_id;
        }
        
        // Ordenar por data
        $sql .= " ORDER BY s.first_impression DESC";
        
        // Preparar e executar a consulta
        $query = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return $results;
    }
    
    /**
     * Calcula as métricas de resumo com base nos dados das sessões
     * 
     * @param array $sessions Dados das sessões (incluindo video_duration_sec do JOIN)
     * @param int $video_id ID do vídeo
     * @return array Métricas de resumo
     */
    private function calculate_summary_metrics($sessions, $video_id) {
        $total_views = count($sessions);
        $total_progress = 0;
        $completed = 0;
        
        // Usar a duração do vídeo da primeira sessão (todas devem ter o mesmo valor)
        $video_duration = 0;
        if (!empty($sessions) && isset($sessions[0]['video_duration_sec'])) {
            $video_duration = intval($sessions[0]['video_duration_sec']);
        }
        
        // Se não encontrou na tabela de vídeos, tentar buscar dos metadados legados
        if ($video_duration <= 0 && $video_id > 0) {
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
        
        // Calcular taxa de conclusão baseada na duração real do vídeo
        $completion_rate = $total_views > 0 ? round(($completed / $total_views) * 100) : 0;
        
        return array(
            'total_views' => $total_views,
            'avg_watch_time' => $formatted_time,
            'completion_rate' => $completion_rate,
            'video_duration' => $video_duration, // Adicionando a duração para uso no frontend
            'formatted_duration' => $this->format_seconds($video_duration)
        );
    }
    
    /**
     * Calcula os dados de retenção de audiência usando a duração real do vídeo
     * 
     * @param array $sessions Dados das sessões com duração do vídeo
     * @param int $video_id ID do vídeo
     * @return array Dados de retenção formatados para Chart.js
     */
    private function calculate_retention_data($sessions, $video_id) {
        // Determinar a duração máxima do vídeo
        $max_duration = 0;
        
        // Obter duração da tabela wp_vsl_videos (deve estar no resultado do JOIN)
        if (!empty($sessions) && isset($sessions[0]['video_duration_sec'])) {
            $max_duration = intval($sessions[0]['video_duration_sec']);
        }
        
        // Se não encontrou na tabela de vídeos, tentar buscar dos metadados legados
        if ($max_duration <= 0 && $video_id > 0) {
            $max_duration = intval(get_post_meta($video_id, '_vsl_player_video_length', true));
        }
        
        // Se ainda não temos duração definida, calcular com base no progresso das sessões
        if ($max_duration == 0) {
            foreach ($sessions as $session) {
                $max_duration = max($max_duration, intval($session['max_progress_sec']));
            }
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
        
        // Calcular percentuais de retenção
        $retention_percentages = array();
        
        if ($total_sessions > 0) {
            foreach ($retention_counts as $count) {
                $retention_percentages[] = round(($count / $total_sessions) * 100);
            }
        }
        
        // Formatar pontos de tempo como minutos:segundos para exibição
        $formatted_time_points = array();
        foreach ($time_points as $seconds) {
            $formatted_time_points[] = $this->format_seconds($seconds);
        }
        
        return array(
            'labels' => $formatted_time_points,
            'data' => $retention_percentages,
            'timePoints' => $time_points, // Pontos de tempo brutos em segundos
            'videoDuration' => $max_duration, // Duração total do vídeo
            'formattedDuration' => $this->format_seconds($max_duration) // Duração formatada
        );
    }
    
    /**
     * Calcula os dados de dispositivos para exibição em gráfico de pizza
     * 
     * @param array $sessions Dados das sessões
     * @return array Dados de dispositivos formatados para Chart.js
     */
    private function calculate_devices_data($sessions) {
        $devices = array();
        
        // Contar ocorrências de cada tipo de dispositivo
        foreach ($sessions as $session) {
            $device_type = !empty($session['device_type']) ? $session['device_type'] : 'unknown';
            
            if (!isset($devices[$device_type])) {
                $devices[$device_type] = 0;
            }
            
            $devices[$device_type]++;
        }
        
        // Obter cores para o gráfico
        $colors = $this->get_chart_colors(count($devices));
        
        // Formatar dados para Chart.js
        $labels = array_keys($devices);
        $data = array_values($devices);
        
        return array(
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors
        );
    }
    
    /**
     * Calcula os dados de origens de visualização para exibição em tabela
     * 
     * @param array $sessions Dados das sessões
     * @param bool $group_urls Se verdadeiro, agrupa URLs sem parâmetros
     * @return array Dados de origens formatados
     */
    private function calculate_referrers_data($sessions, $group_urls = false) {
        $referrers = array();
        
        // Contar ocorrências de cada URL de origem
        foreach ($sessions as $session) {
            $page_url = !empty($session['page_url']) ? $session['page_url'] : 'unknown';
            
            // Se a opção de agrupar URLs está ativada, limpar os parâmetros
            if ($group_urls && $page_url !== 'unknown') {
                $clean_url = parse_url($page_url, PHP_URL_PATH);
                // Se não conseguiu obter o caminho, usar a URL original
                if (empty($clean_url)) {
                    $clean_url = $page_url;
                }
                $page_url = $clean_url;
            }
            
            if (!isset($referrers[$page_url])) {
                $referrers[$page_url] = 0;
            }
            
            $referrers[$page_url]++;
        }
        
        // Ordenar por contagem (maior para menor)
        arsort($referrers);
        
        // Limitar para os top 10
        $referrers = array_slice($referrers, 0, 10, true);
        
        // Formatar para exibição
        $formatted_referrers = array();
        foreach ($referrers as $url => $count) {
            $formatted_referrers[] = array(
                'url' => $url,
                'count' => $count,
                'is_clean' => $group_urls
            );
        }
        
        return $formatted_referrers;
    }
    
    /**
     * Calcula o total de cliques em CTA
     * 
     * @param array $sessions Dados das sessões
     * @return int Total de cliques em CTA
     */
    private function calculate_cta_clicks($sessions) {
        $total_clicks = 0;
        
        foreach ($sessions as $session) {
            $total_clicks += intval($session['cta_clicks']);
        }
        
        return $total_clicks;
    }
    
    /**
     * Calcula a taxa de cliques no player
     * 
     * @param array $sessions Dados das sessões
     * @return float Taxa de cliques (porcentagem)
     */
    private function calculate_play_rate($sessions) {
        $total_sessions = count($sessions);
        $sessions_with_play = 0;
        
        foreach ($sessions as $session) {
            if (!empty($session['first_play'])) {
                $sessions_with_play++;
            }
        }
        
        $play_rate = $total_sessions > 0 ? round(($sessions_with_play / $total_sessions) * 100, 1) : 0;
        
        return $play_rate;
    }
    
    /**
     * Analisa os dados de campanhas UTM e gera relatório
     * 
     * @param array $sessions Dados das sessões
     * @param string $utm_source_filter Filtro opcional para utm_source
     * @param string $utm_campaign_filter Filtro opcional para utm_campaign
     * @return array Dados de campanhas formatados
     */
    private function calculate_utm_campaigns($sessions, $utm_source_filter = '', $utm_campaign_filter = '') {
        $campaigns = array();
        $utm_sources = array('');  // Inclui uma opção vazia para "Todos"
        $utm_campaigns = array(''); // Inclui uma opção vazia para "Todos"
        
        // Primeiro passo: identificar todas as fontes e campanhas disponíveis e agrupar dados
        foreach ($sessions as $session) {
            $utm_source = !empty($session['utm_source']) ? $session['utm_source'] : '-';
            $utm_medium = !empty($session['utm_medium']) ? $session['utm_medium'] : '-';
            $utm_campaign = !empty($session['utm_campaign']) ? $session['utm_campaign'] : '-';
            
            // Adicionar à lista de filtros disponíveis se ainda não estiver lá
            if (!in_array($utm_source, $utm_sources) && $utm_source !== '-') {
                $utm_sources[] = $utm_source;
            }
            
            if (!in_array($utm_campaign, $utm_campaigns) && $utm_campaign !== '-') {
                $utm_campaigns[] = $utm_campaign;
            }
            
            // Criar chave única para esta combinação de UTMs
            $key = $utm_source . '|' . $utm_medium . '|' . $utm_campaign;
            
            // Aplicar filtros se especificados
            if ((!empty($utm_source_filter) && $utm_source !== $utm_source_filter) ||
                (!empty($utm_campaign_filter) && $utm_campaign !== $utm_campaign_filter)) {
                continue; // Pular esta sessão se não corresponder aos filtros
            }
            
            // Inicializar ou incrementar contador para esta combinação
            if (!isset($campaigns[$key])) {
                $campaigns[$key] = array(
                    'utm_source' => $utm_source,
                    'utm_medium' => $utm_medium,
                    'utm_campaign' => $utm_campaign,
                    'sessions' => 0,
                    'plays' => 0 // Para calcular taxa de clique
                );
            }
            
            $campaigns[$key]['sessions']++;
            
            // Contar quantas sessões tiveram um play
            if (!empty($session['first_play'])) {
                $campaigns[$key]['plays']++;
            }
        }
        
        // Calcular a taxa de clique e outros dados para cada campanha
        foreach ($campaigns as $key => &$campaign) {
            $campaign['click_rate'] = $campaign['sessions'] > 0 ? 
                round(($campaign['plays'] / $campaign['sessions']) * 100, 1) : 0;
        }
        
        // Ordenar por número de sessões (maior para menor)
        uasort($campaigns, function($a, $b) {
            return $b['sessions'] - $a['sessions'];
        });
        
        // Formatar para exibição
        $formatted_campaigns = array_values($campaigns);
        
        return array(
            'campaigns' => $formatted_campaigns,
            'utm_sources' => $utm_sources,
            'utm_campaigns' => $utm_campaigns
        );
    }
    
    /**
     * Retorna um conjunto de cores para uso em gráficos
     * 
     * @param int $count Número de cores necessárias
     * @return array Array de cores em formato hexadecimal
     */
    private function get_chart_colors($count) {
        $base_colors = array(
            '#0073aa', // Azul WordPress
            '#d54e21', // Laranja WordPress
            '#37c871', // Verde
            '#f2a700', // Amarelo
            '#e14d43', // Vermelho
            '#826eb4', // Roxo
            '#00b9eb', // Azul claro
            '#f78b53', // Laranja claro
            '#7ad03a', // Verde claro
            '#ffba00', // Amarelo escuro
        );
        
        // Se precisamos de mais cores do que temos no array base
        if ($count > count($base_colors)) {
            // Repetir as cores básicas
            $extended_colors = array();
            for ($i = 0; $i < $count; $i++) {
                $extended_colors[] = $base_colors[$i % count($base_colors)];
            }
            return $extended_colors;
        }
        
        // Caso contrário, retornar apenas as cores necessárias
        return array_slice($base_colors, 0, $count);
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
