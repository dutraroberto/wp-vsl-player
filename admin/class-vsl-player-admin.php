<?php
/**
 * The admin-specific functionality of the plugin.
 */
class VSL_Player_Admin {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register the menu for the plugin.
     */
    public function add_admin_menu() {
        // Main menu - now pointing to VSLs list
        add_menu_page(
            __('VSL Otimizado', 'vsl-player'),
            __('VSL Otimizado', 'vsl-player'),
            'manage_options',
            'edit.php?post_type=vsl_player',
            null,
            'dashicons-video-alt3',
            30
        );
        
        // Submenu for VSLs (already added via main menu)
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('VSLs Otimizadas', 'vsl-player'),
            __('VSLs Otimizadas', 'vsl-player'),
            'manage_options',
            'edit.php?post_type=vsl_player',
            null
        );
        
        // Submenu for Reveal 
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('Ocultar Sessões', 'vsl-player'),
            __('Ocultar Sessões', 'vsl-player'),
            'manage_options',
            'edit.php?post_type=vsl_reveal',
            null
        );
        
        // Submenu for license management
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('Licença', 'vsl-player'),
            __('Licença', 'vsl-player'),
            'manage_options',
            'vsl-player-license',
            array($this, 'display_license_page')
        );
        
        // Submenu for support
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('Suporte', 'vsl-player'),
            __('Suporte', 'vsl-player'),
            'manage_options',
            'vsl-player-support',
            array($this, 'display_support_page')
        );
        
        // Submenu for analytics
        add_submenu_page(
            'edit.php?post_type=vsl_player',
            __('Analytics', 'vsl-player'),
            __('Analytics', 'vsl-player'),
            'manage_options',
            'vsl-player-analytics',
            array($this, 'display_analytics_page')
        );
    }

    /**
     * Enqueue scripts and styles for the admin area.
     */
    public function enqueue_scripts($hook) {
        // Only enqueue on our plugin pages
        $screen = get_current_screen();
        
        // Debug - registrar informações da tela 
        error_log('VSL Player - Screen ID: ' . $screen->id . ', Hook: ' . $hook);
        
        // Check if we're on one of our plugin pages
        $vsl_admin_pages = array(
            'toplevel_page_edit',
            'vsl-player_page_vsl-player-license',
            'vsl_player_page_vsl-player-license',
            'vsl_player_page_vsl-player-support',
            'vsl_player_page_vsl-player-analytics',
            'edit-vsl_player',
            'vsl_player',
            'edit-vsl_reveal',
            'vsl_reveal'
        );
        
        // Always load scripts on vsl-player-license page
        if (isset($_GET['page']) && $_GET['page'] == 'vsl-player-license') {
            // continuamos com o enfileiramento normal
        }
        // Skip if not on our pages
        else if (!in_array($screen->id, $vsl_admin_pages) && strpos($hook, 'vsl-player') === false) {
            return;
        }
        
        // Carregar scripts e estilos específicos para a página de analytics
        if (isset($_GET['page']) && $_GET['page'] == 'vsl-player-analytics') {
            // jQuery UI para datepicker
            wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css');
            wp_enqueue_script('jquery-ui-datepicker');
            
            // Chart.js
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(),
                '3.9.1',
                true
            );
            
            // CSS específico para analytics
            wp_enqueue_style(
                'vsl-player-analytics-styles',
                VSL_PLAYER_URL . 'admin/css/vsl-player-analytics.css',
                array('vsl-player-admin-enhanced'),
                VSL_PLAYER_VERSION,
                'all'
            );
            
            // JS específico para analytics
            wp_enqueue_script(
                'vsl-player-analytics',
                VSL_PLAYER_URL . 'admin/js/vsl-player-analytics.js',
                array('jquery', 'chartjs', 'jquery-ui-datepicker'),
                VSL_PLAYER_VERSION,
                true
            );
            
            // Localize script para analytics
            wp_localize_script(
                'vsl-player-analytics',
                'vslAnalytics',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('vsl_analytics_nonce'),
                    'i18n' => array(
                        'noData' => __('Nenhum dado disponível para exibição', 'vsl-player'),
                        'loading' => __('Carregando...', 'vsl-player')
                    )
                )
            );
        }
        
        // Admin CSS (original)
        wp_enqueue_style(
            'vsl-player-admin', 
            VSL_PLAYER_URL . 'admin/css/vsl-player-admin.css', 
            array(), 
            VSL_PLAYER_VERSION, 
            'all'
        );
        
        // Enhanced Admin CSS
        wp_enqueue_style(
            'vsl-player-admin-enhanced', 
            VSL_PLAYER_URL . 'admin/css/vsl-player-admin-enhanced.css', 
            array('vsl-player-admin'), 
            VSL_PLAYER_VERSION, 
            'all'
        );
        
        // Admin JS (original)
        wp_enqueue_script(
            'vsl-player-admin',
            VSL_PLAYER_URL . 'admin/js/vsl-player-admin.js',
            array('jquery'),
            VSL_PLAYER_VERSION,
            true
        );
        
        // Enhanced Admin JS
        wp_enqueue_script(
            'vsl-player-admin-enhanced',
            VSL_PLAYER_URL . 'admin/js/vsl-player-admin-enhanced.js',
            array('jquery', 'wp-color-picker'),
            VSL_PLAYER_VERSION,
            true
        );
        
        // WordPress Media Upload
        wp_enqueue_media();
        
        // WordPress Color Picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Conversions Admin CSS and JS (only for VSL Player editing)
        if ($screen->id === 'vsl_player') {
            wp_enqueue_style(
                'vsl-player-conversions-admin',
                VSL_PLAYER_URL . 'admin/css/vsl-player-conversions-admin.css',
                array('vsl-player-admin-enhanced'),
                VSL_PLAYER_VERSION,
                'all'
            );
            
            wp_enqueue_script(
                'vsl-player-conversions-admin',
                VSL_PLAYER_URL . 'admin/js/vsl-player-conversions-admin.js',
                array('jquery', 'vsl-player-admin-enhanced'),
                VSL_PLAYER_VERSION,
                true
            );
            
            // Smart Hooks Admin CSS
            wp_enqueue_style(
                'vsl-player-smart-hooks-admin',
                VSL_PLAYER_URL . 'admin/css/vsl-player-smart-hooks-admin.css',
                array('vsl-player-admin-enhanced'),
                VSL_PLAYER_VERSION,
                'all'
            );
            
            // Smart Hooks Admin JS
            wp_enqueue_script(
                'vsl-player-smart-hooks-admin',
                VSL_PLAYER_URL . 'admin/js/vsl-player-smart-hooks-admin.js',
                array('jquery', 'vsl-player-admin-enhanced'),
                VSL_PLAYER_VERSION,
                true
            );
            
            // Localize script with translations for the smart hooks admin
            wp_localize_script(
                'vsl-player-smart-hooks-admin',
                'vslSmartHooksAdmin',
                array(
                    'i18n' => array(
                        'smartHook' => __('Gancho Inteligente', 'vsl-player'),
                        'remove' => __('Remover', 'vsl-player'),
                        'hookName' => __('Nome do Gancho', 'vsl-player'),
                        'hookNamePlaceholder' => __('Ex: 30s para oferta', 'vsl-player'),
                        'hookImage' => __('Imagem do Gancho', 'vsl-player'),
                        'selectImage' => __('Selecionar Imagem', 'vsl-player'),
                        'imageDescription' => __('Imagem PNG 16:9 com o gatilho', 'vsl-player'),
                        'startTime' => __('Tempo de início (segundos)', 'vsl-player'),
                        'endTime' => __('Tempo de fim (segundos)', 'vsl-player'),
                        'selectHookImage' => __('Selecione a imagem do gancho', 'vsl-player'),
                        'useThisImage' => __('Usar esta imagem', 'vsl-player')
                    )
                )
            );
            
            // Localize script with translations for the conversions admin
            wp_localize_script(
                'vsl-player-conversions-admin',
                'vslConversionsAdmin',
                array(
                    'i18n' => array(
                        'conversionEvent' => __('Evento de Conversão', 'vsl-player'),
                        'remove' => __('Remover', 'vsl-player'),
                        'eventName' => __('Nome do Evento', 'vsl-player'),
                        'eventNamePlaceholder' => __('Ex: View_Oferta', 'vsl-player'),
                        'eventTime' => __('Tempo (segundos)', 'vsl-player'),
                        'integrations' => __('Integrações', 'vsl-player'),
                        'googleAnalytics' => __('Google Analytics (GA4)', 'vsl-player'),
                        'googleAds' => __('Google Ads (Conversões)', 'vsl-player'),
                        'facebookPixel' => __('Facebook Pixel', 'vsl-player')
                    )
                )
            );
        }
        
        // Select2 para o CPT vsl_reveal
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            array(),
            '4.1.0'
        );
        
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );
        
        // Localize script for AJAX
        wp_localize_script(
            'vsl-player-admin-enhanced',
            'vsl_player_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vsl_player_admin_nonce'),
                'strings' => array(
                    'choose_video' => __('Escolher Vídeo', 'vsl-player'),
                    'choose_image' => __('Escolher Imagem', 'vsl-player'),
                    'use_this' => __('Usar este', 'vsl-player'),
                    'remove' => __('Remover', 'vsl-player'),
                    'copied' => __('Copiado!', 'vsl-player'),
                )
            )
        );

        // Localize script for AJAX for vsl-player-admin.js
        wp_localize_script(
            'vsl-player-admin',
            'vsl_player_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vsl_player_admin_nonce')
            )
        );
    }

    /**
     * Display the license page content.
     */
    public function display_license_page() {
        ?>
        <div class="wrap vsl-player-admin">
            <h1><?php echo esc_html__('VSL Player Otimizado - Licença', 'vsl-player'); ?></h1>
            
            <div class="vsl-license-container">
                <form method="post" id="vsl-license-form">
                    <?php 
                    // Adicionar nonce manualmente
                    wp_nonce_field('vsl_player_admin_nonce', 'vsl_license_nonce');
                    
                    // Get license data
                    $license_key = get_option('vsl_player_license_key', '');
                    $license_status = get_option('vsl_player_license_status', 'inactive');
                    $license_expiry = get_option('vsl_player_license_expiry', '');
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Chave de Licença', 'vsl-player'); ?></th>
                            <td>
                                <input type="text" id="vsl_player_license_key" name="vsl_player_license_key" 
                                       value="<?php echo esc_attr($license_key); ?>" class="regular-text" 
                                       placeholder="XXXXX-XXXXX-XXXXX-XXXXX">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Status da Licença', 'vsl-player'); ?></th>
                            <td>
                                <?php
                                $status_class = 'inactive';
                                $status_label = __('Inativa', 'vsl-player');
                                
                                if ($license_status === 'active') {
                                    $status_class = 'active';
                                    $status_label = __('Ativa', 'vsl-player');
                                } elseif ($license_status === 'expired') {
                                    $status_class = 'expired';
                                    $status_label = __('Expirada', 'vsl-player');
                                }
                                ?>
                                <span class="license-status status-<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($license_expiry) && $license_status === 'active') : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Expira em', 'vsl-player'); ?></th>
                            <td>
                                <?php echo esc_html($license_expiry); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <?php submit_button(__('Salvar e Validar Licença', 'vsl-player'), 'primary', 'validate_license_btn'); ?>
                </form>
                
                <div class="license-info">
                    <p><?php echo esc_html__('O VSL Player Otimizado requer uma licença válida para funcionar. A licença permite que você utilize o plugin em um único domínio.', 'vsl-player'); ?></p>
                    <p>
                        <?php 
                        echo sprintf(
                            esc_html__('Para adquirir uma licença ou renovar a existente, visite nosso site: %s', 'vsl-player'),
                            '<a href="https://mundowp.com.br/plugins/vsl-player" target="_blank">mundowp.com.br/plugins/vsl-player</a>'
                        ); 
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Garantir que este script seja executado apenas na página de licença
            if (window.location.href.indexOf('vsl-player-license') > -1) {
                console.log('Script de validação de licença carregado');
                
                // Capturar o envio do formulário diretamente
                $('#vsl-license-form').on('submit', function(e) {
                    e.preventDefault();
                    console.log('Formulário enviado!');
                    
                    var licenseKey = $('#vsl_player_license_key').val();
                    
                    if (!licenseKey) {
                        alert('Por favor, insira uma chave de licença.');
                        return;
                    }
                    
                    // Mostrar estado de carregamento
                    var submitButton = $(this).find('input[type="submit"]');
                    var originalText = submitButton.val();
                    submitButton.val('Validando...').prop('disabled', true);
                    
                    // Enviar requisição AJAX
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'vsl_validate_license',
                            license_key: licenseKey,
                            nonce: '<?php echo wp_create_nonce('vsl_player_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            console.log('Resposta da validação:', response);
                            
                            // Restaurar estado do botão
                            submitButton.val(originalText).prop('disabled', false);
                            
                            if (response.success) {
                                // Atualizar status da licença visualmente
                                $('.license-status')
                                    .removeClass('status-inactive status-expired')
                                    .addClass('status-active')
                                    .text('Ativa');
                                    
                                // Adicionar data de expiração se fornecida
                                if (response.data && response.data.expiry) {
                                    // Verificar se a linha de expiração existe
                                    var expiryRow = $('th:contains("Expira em")').parent();
                                    if (expiryRow.length) {
                                        expiryRow.find('td').text(response.data.expiry);
                                    } else {
                                        // Criar nova linha para expiração
                                        var newRow = $('<tr><th scope="row">Expira em</th><td>' + response.data.expiry + '</td></tr>');
                                        $('.form-table').append(newRow);
                                    }
                                }
                                
                                // Mostrar mensagem de sucesso
                                alert('Licença ativada com sucesso!');
                            } else {
                                // Atualizar status da licença visualmente
                                $('.license-status')
                                    .removeClass('status-active status-expired')
                                    .addClass('status-inactive')
                                    .text('Inativa');
                                
                                // Remover linha de expiração se existir
                                $('th:contains("Expira em")').parent().remove();
                                
                                // Mostrar mensagem de erro
                                alert('Erro ao validar licença. Por favor, verifique a chave e tente novamente.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro na requisição AJAX:', error);
                            console.log(xhr.responseText);
                            
                            // Restaurar estado do botão
                            submitButton.val(originalText).prop('disabled', false);
                            
                            // Mostrar mensagem de erro
                            alert('Erro na requisição: ' + error + '. Por favor, tente novamente mais tarde.');
                        }
                    });
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Display the support page content.
     */
    public function display_support_page() {
        ?>
        <div class="wrap vsl-player-admin">
            <h1><?php echo esc_html__('VSL Player Otimizado - Suporte', 'vsl-player'); ?></h1>
            
            <div class="vsl-support-container">
                <div class="support-section">
                    <h2><?php echo esc_html__('Ajuda', 'vsl-player'); ?></h2>
                    <p>
                        <?php 
                        echo sprintf(
                            esc_html__('Precisa de ajuda? Fale conosco pelo WhatsApp: %s', 'vsl-player'),
                            '<a href="https://mundowp.com.br/whatsapp" target="_blank" class="whatsapp-link"><span class="dashicons dashicons-whatsapp"></span> Suporte</a>'
                        ); 
                        ?>
                    </p>
                </div>
                
                <div class="license-section">
                    <h2><?php echo esc_html__('Aquisição de Licença', 'vsl-player'); ?></h2>
                    <p>
                        <?php 
                        echo sprintf(
                            esc_html__('Quer adquirir uma licença? Visite nosso site: %s', 'vsl-player'),
                            '<a href="https://mundowp.com.br/plugins/vsl-player" target="_blank">mundowp.com.br/plugins/vsl-player</a>'
                        ); 
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display the analytics page content.
     */
    public function display_analytics_page() {
        // Obter todos os vídeos (VSL Players) para o filtro
        $vsl_players = get_posts([
            'post_type' => 'vsl_player',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        ?>
        <div class="wrap vsl-player-admin">
            <h1><?php echo esc_html__('VSL Player Otimizado - Analytics', 'vsl-player'); ?></h1>
            
            <div class="vsl-analytics-container">
                <!-- Filtros -->
                <div class="vsl-analytics-filters">
                    <div class="vsl-analytics-filter-group">
                        <label for="video_filter"><?php echo esc_html__('Selecione o Vídeo:', 'vsl-player'); ?></label>
                        <select id="video_filter" name="video_filter" required>
                            <option value="" disabled selected><?php echo esc_html__('Escolha um vídeo...', 'vsl-player'); ?></option>
                            <?php foreach ($vsl_players as $player): ?>
                                <option value="<?php echo esc_attr($player->ID); ?>">
                                    <?php echo esc_html($player->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="filter-description"><?php echo esc_html__('Por favor, selecione um vídeo para visualizar suas métricas.', 'vsl-player'); ?></p>
                    </div>
                    
                    <div class="vsl-analytics-filter-group">
                        <label for="date_start"><?php echo esc_html__('Data Inicial:', 'vsl-player'); ?></label>
                        <input type="text" id="date_start" name="date_start" class="vsl-datepicker" placeholder="AAAA-MM-DD">
                    </div>
                    
                    <div class="vsl-analytics-filter-group">
                        <label for="date_end"><?php echo esc_html__('Data Final:', 'vsl-player'); ?></label>
                        <input type="text" id="date_end" name="date_end" class="vsl-datepicker" placeholder="AAAA-MM-DD">
                    </div>
                    
                    <button type="button" id="apply_filters" class="apply-filters-button">
                        <span class="dashicons dashicons-filter"></span> 
                        <?php echo esc_html__('Aplicar Filtros', 'vsl-player'); ?>
                    </button>
                </div>
                
                <!-- Indicador de carregamento -->
                <div class="vsl-analytics-loading">
                    <span class="spinner is-active"></span>
                    <p><?php echo esc_html__('Carregando dados...', 'vsl-player'); ?></p>
                </div>
                
                <!-- Cards de resumo -->
                <div class="vsl-analytics-summary">
                    <div class="vsl-analytics-card">
                        <h3><?php echo esc_html__('Total de Visualizações', 'vsl-player'); ?></h3>
                        <div class="value" id="total_views">0</div>
                    </div>
                    
                    <div class="vsl-analytics-card">
                        <h3><?php echo esc_html__('Tempo Médio de Visualização', 'vsl-player'); ?></h3>
                        <div class="value" id="avg_watch_time">0s</div>
                    </div>
                    
                    <div class="vsl-analytics-card">
                        <h3><?php echo esc_html__('Taxa de Conclusão', 'vsl-player'); ?></h3>
                        <div class="value" id="completion_rate">0%</div>
                    </div>
                    
                    <div class="vsl-analytics-card">
                        <h3><?php echo esc_html__('Total de Cliques CTA', 'vsl-player'); ?></h3>
                        <div class="value" id="total_cta_clicks">0</div>
                    </div>
                    
                    <div class="vsl-analytics-card">
                        <h3><?php echo esc_html__('Taxa de Cliques no Player', 'vsl-player'); ?></h3>
                        <div class="value" id="play_rate">0%</div>
                    </div>
                </div>
                
                <!-- Área de gráficos -->
                <div class="vsl-analytics-charts">
                    <!-- Gráfico de retenção -->
                    <h3 class="section-title"><?php echo esc_html__('Retenção de Audiência', 'vsl-player'); ?></h3>
                    <div class="chart-container">
                        <canvas id="retention-chart"></canvas>
                    </div>
                    
                    <!-- Gráficos de dispositivos e origens -->
                    <div class="charts-row">
                        <div class="chart-column">
                            <h3 class="section-title"><?php echo esc_html__('Dispositivos Utilizados', 'vsl-player'); ?></h3>
                            <div class="chart-pie-container">
                                <canvas id="devices-chart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-column">
                            <div class="referrers-options">
                                <h3 class="section-title"><?php echo esc_html__('Origens das Visualizações (Top 10)', 'vsl-player'); ?></h3>
                                <div class="url-grouping-toggle">
                                    <label for="group_urls"><?php echo esc_html__('Agrupar URLs sem parâmetros', 'vsl-player'); ?></label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="group_urls" name="group_urls">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="referrers-container">
                                <table class="referrers-table" id="referrers-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo esc_html__('URL', 'vsl-player'); ?></th>
                                            <th class="count-column"><?php echo esc_html__('Visualizações', 'vsl-player'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="2"><?php echo esc_html__('Carregando dados...', 'vsl-player'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Análise de Campanhas UTM -->
                    <h3 class="section-title"><?php echo esc_html__('Campanhas de Marketing (UTM)', 'vsl-player'); ?></h3>
                    
                    <div class="utm-filters">
                        <div class="utm-filter-group">
                            <label for="utm_source_filter"><?php echo esc_html__('Fonte (utm_source)', 'vsl-player'); ?></label>
                            <select id="utm_source_filter" name="utm_source_filter">
                                <option value=""><?php echo esc_html__('Todas as fontes', 'vsl-player'); ?></option>
                                <!-- Opções serão preenchidas via JavaScript -->
                            </select>
                        </div>
                        
                        <div class="utm-filter-group">
                            <label for="utm_campaign_filter"><?php echo esc_html__('Campanha (utm_campaign)', 'vsl-player'); ?></label>
                            <select id="utm_campaign_filter" name="utm_campaign_filter">
                                <option value=""><?php echo esc_html__('Todas as campanhas', 'vsl-player'); ?></option>
                                <!-- Opções serão preenchidas via JavaScript -->
                            </select>
                        </div>
                        
                        <button type="button" id="apply_utm_filters" class="apply-utm-filters">
                            <span class="dashicons dashicons-filter"></span> 
                            <?php echo esc_html__('Aplicar Filtros UTM', 'vsl-player'); ?>
                        </button>
                    </div>
                    
                    <div class="utm-table-container">
                        <table class="utm-campaigns-table" id="utm-campaigns-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Fonte (utm_source)', 'vsl-player'); ?></th>
                                    <th><?php echo esc_html__('Mídia (utm_medium)', 'vsl-player'); ?></th>
                                    <th><?php echo esc_html__('Campanha (utm_campaign)', 'vsl-player'); ?></th>
                                    <th class="num-column"><?php echo esc_html__('Sessões', 'vsl-player'); ?></th>
                                    <th class="num-column"><?php echo esc_html__('Taxa de Clique', 'vsl-player'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5"><?php echo esc_html__('Carregando dados...', 'vsl-player'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Mensagem de nenhum dado -->
                <div class="vsl-no-data" style="display: none;">
                    <p><?php echo esc_html__('Nenhum dado disponível para os filtros selecionados.', 'vsl-player'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
}
