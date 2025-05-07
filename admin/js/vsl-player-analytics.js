/**
 * VSL Player Analytics - JavaScript para gráficos e interatividade
 */
(function($) {
    'use strict';

    // Chart.js configurações globais
    Chart.defaults.color = '#666';
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
    
    // Variáveis para armazenar os gráficos
    let retentionChart = null;
    let devicesChart = null;
    
    // Inicialização
    $(document).ready(function() {
        // Inicializar datepicker
        $('.vsl-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            maxDate: 0
        });
        
        // Definir data inicial como 30 dias atrás por padrão
        if ($('#date_start').val() === '') {
            const defaultStart = new Date();
            defaultStart.setDate(defaultStart.getDate() - 30);
            $('#date_start').datepicker('setDate', defaultStart);
        }
        
        // Definir data final como hoje por padrão
        if ($('#date_end').val() === '') {
            $('#date_end').datepicker('setDate', new Date());
        }
        
        // Botão de aplicar filtros principais
        $('#apply_filters').on('click', function() {
            loadAnalyticsData();
        });
        
        // Toggle de agrupamento de URLs
        $('#group_urls').on('change', function() {
            // Recarregar dados quando o toggle mudar
            loadAnalyticsData();
        });
        
        // Botão de aplicar filtros UTM
        $('#apply_utm_filters').on('click', function() {
            loadAnalyticsData();
        });
        
        // Carregar dados iniciais
        loadAnalyticsData();
    });
    
    /**
     * Carrega os dados de analytics do servidor
     */
    function loadAnalyticsData() {
        // Obter o valor atual do toggle explicitamente como booleano
        // O .prop('checked') retorna true ou false para checkboxes
        const groupUrls = $('#group_urls').prop('checked') === true ? true : false;
        
        console.log('Estado atual do toggle:', groupUrls); // Para debug
        
        const filters = {
            video_id: $('#video_filter').val(),
            date_start: $('#date_start').val(),
            date_end: $('#date_end').val(),
            // Novas opções
            group_urls: groupUrls, // Garantir que seja boolean true/false
            utm_source: $('#utm_source_filter').val(),
            utm_campaign: $('#utm_campaign_filter').val()
        };
        
        // Mostrar indicador de carregamento
        $('.vsl-analytics-loading').show();
        $('.vsl-analytics-charts').hide();
        $('.vsl-no-data').hide();
        
        // Fazer requisição AJAX
        $.ajax({
            url: vslAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'vsl_get_analytics_data',
                nonce: vslAnalytics.nonce,
                filters: filters
            },
            success: function(response) {
                $('.vsl-analytics-loading').hide();
                
                if (response.success && response.data) {
                    updateAnalyticsView(response.data);
                } else {
                    showNoData();
                }
            },
            error: function() {
                $('.vsl-analytics-loading').hide();
                showNoData();
            }
        });
    }
    
    /**
     * Atualiza a visualização com os dados de analytics
     */
    function updateAnalyticsView(data) {
        if (!data.sessions || data.sessions.length === 0) {
            showNoData();
            return;
        }
        
        // Mostrar área de gráficos
        $('.vsl-analytics-charts').show();
        
        // Atualizar cards de resumo
        updateSummaryCards(data);
        
        // Renderizar gráfico de retenção
        renderRetentionChart(data.retention);
        
        // Renderizar gráfico de dispositivos
        renderDevicesChart(data.devices);
        
        // Atualizar estado do toggle de agrupamento de URLs
        // Comparar explicitamente com true para garantir um valor booleano correto
        const isGrouped = data.filters.group_urls === true;
        console.log('Valor retornado do servidor para group_urls:', data.filters.group_urls, 'Convertido para:', isGrouped);
        $('#group_urls').prop('checked', isGrouped);
        
        // Preencher tabela de origens
        populateReferrersTable(data.referrers, data.filters.group_urls);
        
        // Atualizar filtros de UTM
        updateUtmFilters(data.utm_data);
        
        // Preencher tabela de campanhas UTM
        populateUtmCampaignsTable(data.utm_data.campaigns);
    }
    
    /**
     * Atualiza os cards de resumo com métricas-chave
     */
    function updateSummaryCards(data) {
        $('#total_views').text(data.summary.total_views || 0);
        $('#avg_watch_time').text(data.summary.avg_watch_time || '0s');
        $('#completion_rate').text((data.summary.completion_rate || 0) + '%');
        $('#total_cta_clicks').text(data.cta_clicks || 0);
        $('#play_rate').text((data.play_rate || 0) + '%');
    }
    
    /**
     * Renderiza o gráfico de retenção de audiência
     */
    function renderRetentionChart(retentionData) {
        const ctx = document.getElementById('retention-chart').getContext('2d');
        
        // Destruir gráfico anterior se existir
        if (retentionChart) {
            retentionChart.destroy();
        }
        
        // Criar novo gráfico
        retentionChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: retentionData.labels,
                datasets: [{
                    label: 'Retenção de Audiência (%)',
                    data: retentionData.values,
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    borderColor: 'rgba(0, 115, 170, 1)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: 'rgba(0, 115, 170, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    title: {
                        display: true,
                        text: 'Retenção de Audiência ao Longo do Vídeo',
                        font: {
                            size: 16
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        title: {
                            display: true,
                            text: 'Percentual de Retenção'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Tempo no Vídeo (segundos)'
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Exibe mensagem quando não há dados disponíveis
     */
    function showNoData() {
        $('.vsl-analytics-charts').hide();
        $('.vsl-no-data').show();
    }
    
    /**
     * Renderiza o gráfico de dispositivos utilizados
     */
    function renderDevicesChart(devicesData) {
        const ctx = document.getElementById('devices-chart').getContext('2d');
        
        // Destruir gráfico anterior se existir
        if (devicesChart) {
            devicesChart.destroy();
        }
        
        // Verificar se há dados
        if (!devicesData || !devicesData.labels || devicesData.labels.length === 0) {
            return;
        }
        
        // Formatar labels para exibição mais amigável
        const formattedLabels = devicesData.labels.map(label => {
            // Capitalizar primeira letra e substituir 'unknown' por 'Desconhecido'
            if (label.toLowerCase() === 'unknown') {
                return 'Desconhecido';
            }
            return label.charAt(0).toUpperCase() + label.slice(1);
        });
        
        // Criar novo gráfico
        devicesChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: formattedLabels,
                datasets: [{
                    data: devicesData.data,
                    backgroundColor: devicesData.colors,
                    borderColor: '#ffffff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            boxWidth: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Preenche a tabela de origens de visualizações
     */
    function populateReferrersTable(referrersData, isGrouped) {
        const $table = $('#referrers-table tbody');
        $table.empty();
        
        // Verificar se há dados
        if (!referrersData || referrersData.length === 0) {
            $table.append(`<tr><td colspan="2">${vslAnalytics.i18n.noData}</td></tr>`);
            return;
        }
        
        // Preencher a tabela com os dados
        $.each(referrersData, function(index, item) {
            // Formatar URL para exibição
            let displayUrl = item.url;
            if (displayUrl === 'unknown' || displayUrl === '-') {
                displayUrl = 'Desconhecido';
            } else if (displayUrl.length > 60) {
                // Truncar URLs muito longas
                displayUrl = displayUrl.substring(0, 57) + '...';
            }
            
            // Adicionar indicador de URL agrupada quando aplicável
            const urlTitle = isGrouped && displayUrl !== 'Desconhecido' ? 'URL agrupada (sem parâmetros)' : item.url;
            
            $table.append(`
                <tr>
                    <td title="${urlTitle}">${displayUrl}</td>
                    <td class="count-column">${item.count}</td>
                </tr>
            `);
        });
    }
    
    /**
     * Atualiza os dropdowns de filtros UTM com as opções disponíveis
     */
    function updateUtmFilters(utmData) {
        if (!utmData) return;
        
        // Atualizar dropdown de utm_source
        const $sourceFilter = $('#utm_source_filter');
        const currentSourceValue = $sourceFilter.val(); // Preservar seleção atual
        $sourceFilter.find('option:not(:first)').remove(); // Remover todas as opções exceto a primeira
        
        // Adicionar novas opções
        $.each(utmData.utm_sources, function(index, source) {
            if (source === '') return; // Pular a opção vazia (já existe)
            $sourceFilter.append(`<option value="${source}">${source}</option>`);
        });
        
        // Restaurar a seleção anterior, se existir
        if (currentSourceValue) {
            $sourceFilter.val(currentSourceValue);
        }
        
        // Atualizar dropdown de utm_campaign
        const $campaignFilter = $('#utm_campaign_filter');
        const currentCampaignValue = $campaignFilter.val(); // Preservar seleção atual
        $campaignFilter.find('option:not(:first)').remove(); // Remover todas as opções exceto a primeira
        
        // Adicionar novas opções
        $.each(utmData.utm_campaigns, function(index, campaign) {
            if (campaign === '') return; // Pular a opção vazia (já existe)
            $campaignFilter.append(`<option value="${campaign}">${campaign}</option>`);
        });
        
        // Restaurar a seleção anterior, se existir
        if (currentCampaignValue) {
            $campaignFilter.val(currentCampaignValue);
        }
    }
    
    /**
     * Preenche a tabela de campanhas UTM
     */
    function populateUtmCampaignsTable(campaignsData) {
        const $table = $('#utm-campaigns-table tbody');
        $table.empty();
        
        // Verificar se há dados
        if (!campaignsData || campaignsData.length === 0) {
            $table.append(`<tr><td colspan="5">${vslAnalytics.i18n.noData}</td></tr>`);
            return;
        }
        
        // Preencher a tabela com os dados
        $.each(campaignsData, function(index, campaign) {
            // Formatar para exibição
            const source = campaign.utm_source === '-' ? 'Não definido' : campaign.utm_source;
            const medium = campaign.utm_medium === '-' ? 'Não definido' : campaign.utm_medium;
            const campaignName = campaign.utm_campaign === '-' ? 'Não definido' : campaign.utm_campaign;
            
            $table.append(`
                <tr>
                    <td>${source}</td>
                    <td>${medium}</td>
                    <td>${campaignName}</td>
                    <td class="num-column">${campaign.sessions}</td>
                    <td class="num-column">${campaign.click_rate}%</td>
                </tr>
            `);
        });
    }
    
})(jQuery);
