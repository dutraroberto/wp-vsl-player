/**
 * VSL Player Analytics - JavaScript para gráficos e interatividade
 */
(function ($) {
  "use strict";

  // Chart.js configurações globais
  Chart.defaults.color = "#666";
  Chart.defaults.font.family =
    '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

  // Variáveis para armazenar os gráficos
  let retentionChart = null;
  let devicesChart = null;

  // Armazenar dados de retenção globalmente para recalcular pontos
  let globalRetentionData = null;
  let selectedIntervalSeconds = 10; // Valor padrão do intervalo em segundos

  // Inicialização
  $(document).ready(function () {
    // Seletor visual de vídeos
    $(".vsl-video-card").on("click", function () {
      const videoId = $(this).data("video-id");

      // Remover seleção anterior
      $(".vsl-video-card").removeClass("selected");

      // Adicionar seleção ao card clicado
      $(this).addClass("selected");

      // Atualizar campo hidden
      $("#video_filter").val(videoId);

      // Aplicar filtros automaticamente
      loadAnalyticsData();
    });

    // Inicializar datepicker com design melhorado e formato brasileiro
    $(".vsl-datepicker").datepicker({
      dateFormat: "dd/mm/yy", // Formato brasileiro dd/mm/aaaa
      maxDate: 0,
      showOtherMonths: true,
      selectOtherMonths: true,
      changeMonth: true,
      changeYear: true,
      showAnim: "fadeIn",
      dayNamesMin: ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"],
      monthNamesShort: [
        "Jan",
        "Fev",
        "Mar",
        "Abr",
        "Mai",
        "Jun",
        "Jul",
        "Ago",
        "Set",
        "Out",
        "Nov",
        "Dez",
      ],
      firstDay: 0,
    });

    // Inicializar o controle deslizante de intervalo
    $("#interval-slider").on("input", function () {
      const value = $(this).val();
      $("#interval-value").text(value);
      selectedIntervalSeconds = parseInt(value);
    });

    // Botão para aplicar o novo intervalo
    $("#apply-interval").on("click", function () {
      console.log(
        "Botão Aplicar clicado, intervalo selecionado:",
        selectedIntervalSeconds
      );
      if (globalRetentionData) {
        console.log("Aplicando novo intervalo aos dados globais");
        renderRetentionChart(globalRetentionData, selectedIntervalSeconds);
      } else {
        console.log("Erro: Nenhum dado de retenção disponível para recalcular");
      }
    });

    // Definir data inicial como 30 dias atrás por padrão
    if ($("#date_start").val() === "") {
      const defaultStart = new Date();
      defaultStart.setDate(defaultStart.getDate() - 30);
      $("#date_start").datepicker("setDate", defaultStart);
    }

    // Definir data final como hoje por padrão
    if ($("#date_end").val() === "") {
      $("#date_end").datepicker("setDate", new Date());
    }

    // Função para atualizar os campos de data com base no período selecionado
    $("#date_range").on("change", function () {
      const today = new Date();
      let startDate = new Date();
      let endDate = new Date();

      // Configura as datas com base na opção selecionada
      switch ($(this).val()) {
        case "today": // Hoje
          startDate = new Date(today);
          endDate = new Date(today);
          break;

        case "yesterday": // Ontem
          startDate = new Date(today);
          startDate.setDate(startDate.getDate() - 1);
          endDate = new Date(startDate);
          break;

        case "last7days": // Últimos 7 dias
          startDate.setDate(startDate.getDate() - 6);
          break;

        case "last14days": // Últimos 14 dias
          startDate.setDate(startDate.getDate() - 13);
          break;

        case "last28days": // Últimos 28 dias
          startDate.setDate(startDate.getDate() - 27);
          break;

        case "thisMonth": // Este mês
          startDate = new Date(today.getFullYear(), today.getMonth(), 1);
          break;

        case "lastMonth": // Mês passado
          startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
          endDate = new Date(today.getFullYear(), today.getMonth(), 0);
          break;

        case "last90days": // Últimos 90 dias
          startDate.setDate(startDate.getDate() - 89);
          break;

        case "custom": // Personalizado - não altera as datas, mas exibe os campos
          $(".date-input-group").slideDown(200);
          return;
      }

      // Atualiza os campos de data
      $("#date_start").datepicker("setDate", startDate);
      $("#date_end").datepicker("setDate", endDate);

      // Se qualquer opção exceto 'custom' for selecionada, oculta os campos de data
      if ($(this).val() !== "custom") {
        $(".date-input-group").slideUp(200);
      } else {
        $(".date-input-group").slideDown(200);
      }
    });

    // Definir "Personalizado" como opção padrão do seletor de período
    $("#date_range").val("custom");

    // Garantir que os campos de data sejam exibidos inicialmente, já que "Personalizado" é o padrão
    $(".date-input-group").show();

    // Botão de aplicar filtros principais
    $("#apply_filters").on("click", function () {
      loadAnalyticsData();
    });

    // Toggle de agrupamento de URLs
    $("#group_urls").on("change", function () {
      // Recarregar dados quando o toggle mudar
      loadAnalyticsData();
    });

    // Botão de aplicar filtros UTM
    $("#apply_utm_filters").on("click", function () {
      loadAnalyticsData();
    });

    // Exibir mensagem inicial solicitando seleção de vídeo
    $(".vsl-analytics-loading").hide();
    $(".vsl-analytics-charts").hide();
    $(".vsl-no-data")
      .show()
      .html(
        "<p>Por favor, selecione um vídeo e clique em Aplicar Filtros para visualizar as métricas.</p>"
      );
  });

  /**
   * Carrega os dados de analytics do servidor
   */
  function loadAnalyticsData() {
    // Verificar se um vídeo foi selecionado
    const videoId = $("#video_filter").val();
    if (!videoId) {
      // Exibir mensagem solicitando a seleção de um vídeo
      $(".vsl-analytics-loading").hide();
      $(".vsl-analytics-charts").hide();
      $(".vsl-no-data")
        .show()
        .html(
          "<p>Por favor, selecione um vídeo para visualizar suas métricas.</p>"
        );
      return;
    }

    // Obter o valor atual do toggle explicitamente como booleano
    // O .prop('checked') retorna true ou false para checkboxes
    const groupUrls = $("#group_urls").prop("checked");

    console.log("Estado atual do toggle:", groupUrls); // Para debug

    // Converter datas do formato brasileiro para o formato usado no servidor (yyyy-mm-dd)
    let dateStart = $("#date_start").val();
    let dateEnd = $("#date_end").val();

    // Função para converter data do formato dd/mm/yyyy para yyyy-mm-dd
    function convertDateFormat(dateString) {
      if (!dateString) return "";
      const parts = dateString.split("/");
      if (parts.length !== 3) return dateString;
      return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }

    // Preparar dados do filtro
    const filters = {
      video_id: videoId,
      date_start: convertDateFormat(dateStart),
      date_end: convertDateFormat(dateEnd),
      group_urls: groupUrls,
      utm_source: $("#utm_source_filter").val(),
      utm_campaign: $("#utm_campaign_filter").val(),
    };

    // Mostrar indicador de carregamento
    $(".vsl-analytics-loading").show();
    $(".vsl-analytics-charts").hide();
    $(".vsl-no-data").hide();

    // Fazer requisição AJAX
    $.ajax({
      url: vslAnalytics.ajax_url,
      type: "POST",
      data: {
        action: "vsl_get_analytics_data",
        nonce: vslAnalytics.nonce,
        filters: filters,
      },
      success: function (response) {
        $(".vsl-analytics-loading").hide();

        if (response.success && response.data) {
          updateAnalyticsView(response.data);
        } else {
          showNoData();
        }
      },
      error: function () {
        $(".vsl-analytics-loading").hide();
        showNoData();
      },
    });
  }

  /**
   * Atualiza a visualização com os dados de analytics
   */
  function updateAnalyticsView(data) {
    console.log("Dados recebidos do servidor:", data);

    if (!data.sessions || data.sessions.length === 0) {
      // Zerar todas as métricas quando não há dados
      resetAllMetrics();
      showNoData();
      return;
    }

    // Mostrar área de gráficos
    $(".vsl-analytics-charts").show();

    // Atualizar cards de resumo
    updateSummaryCards(data);

    // Renderizar gráfico de retenção
    renderRetentionChart(data.retention);

    // Renderizar gráfico de dispositivos
    renderDevicesChart(data.devices);

    // Atualizar estado do toggle de agrupamento de URLs
    // Comparar explicitamente com true para garantir um valor booleano correto
    const isGrouped = data.filters.group_urls === true;
    console.log(
      "Valor retornado do servidor para group_urls:",
      data.filters.group_urls,
      "Convertido para:",
      isGrouped
    );
    $("#group_urls").prop("checked", isGrouped);

    // Preencher tabela de origens
    populateReferrersTable(data.referrers, data.filters.group_urls);

    // Atualizar filtros de UTM
    updateUtmFilters(data.utm_data);

    // Preencher tabela de campanhas UTM
    populateUtmCampaignsTable(data.utm_data.campaigns);
  }

  /**
   * Reseta todas as métricas para zero quando não há dados
   */
  function resetAllMetrics() {
    // Resetar cards de resumo
    $("#total_views").text("0");
    $("#iframe_views").text("0");
    $("#avg_watch_time").text("0s");
    $("#completion_rate").text("0%");
    $("#total_cta_clicks").text("0");
    $("#play_rate").text("0%");

    // Limpar gráficos
    if (retentionChart) {
      retentionChart.destroy();
      retentionChart = null;
    }

    if (devicesChart) {
      devicesChart.destroy();
      devicesChart = null;
    }

    // Limpar tabelas
    $("#referrers-table tbody").empty();
    $("#utm-campaigns-table tbody").empty();

    // Esconder informações de duração do vídeo
    $("#video-duration-info").hide();
  }

  /**
   * Atualiza os cards de resumo com métricas-chave
   */
  function updateSummaryCards(data) {
    $("#total_views").text(data.summary.total_views || 0);
    $("#avg_watch_time").text(data.summary.avg_watch_time || "0s");
    $("#completion_rate").text((data.summary.completion_rate || 0) + "%");
    $("#total_cta_clicks").text(data.cta_clicks || 0);
    $("#play_rate").text((data.play_rate || 0) + "%");
    $("#iframe_views").text(data.iframe_views || 0);
  }

  /**
   * Renderiza o gráfico de retenção de audiência usando a duração real do vídeo
   * @param {Object} retentionData - Dados de retenção do vídeo
   * @param {Number} intervalSeconds - Intervalo em segundos entre os pontos do gráfico (opcional)
   */
  function renderRetentionChart(retentionData, intervalSeconds = null) {
    // Verificar se temos dados válidos
    if (!retentionData || !retentionData.data || !retentionData.timePoints) {
      console.error("Dados de retenção inválidos:", retentionData);
      return;
    }

    const ctx = document.getElementById("retention-chart").getContext("2d");

    // Salvar dados globalmente para recalcular com diferentes intervalos
    globalRetentionData = retentionData;

    console.log("Salvando dados para uso global:", retentionData);
    console.log(
      "Intervalo selecionado:",
      intervalSeconds || selectedIntervalSeconds
    );

    // Destruir gráfico anterior se existir
    if (retentionChart) {
      retentionChart.destroy();
    }

    // Adicionar informações sobre a duração do vídeo
    if (retentionData.formattedDuration) {
      $("#video-duration-info").html(
        `<strong>Duração do vídeo:</strong> ${retentionData.formattedDuration}`
      );
      $("#video-duration-info").show();
    } else {
      $("#video-duration-info").hide();
    }

    // Se o intervalo não foi especificado, usar o valor do slider
    if (intervalSeconds === null) {
      intervalSeconds = selectedIntervalSeconds;
    }

    // Calcular intervalo baseado na duração do vídeo e no valor do controle deslizante
    const videoDuration = retentionData.videoDuration || 0;

    console.log(
      "Recalculando pontos para gráfico com intervalo de " +
        intervalSeconds +
        " segundos"
    );
    console.log("Duração total do vídeo: " + videoDuration + " segundos");

    // Criar pontos de tempo para o gráfico com o novo intervalo
    const timePoints = [];
    const percentageData = [];
    const viewerCountsData = [];
    const formattedLabels = [];

    // Gerar pontos baseados no intervalo selecionado
    for (let time = 0; time <= videoDuration; time += intervalSeconds) {
      timePoints.push(time);

      // Encontrar o índice mais próximo nos dados originais
      const nearestIndex = findNearestTimeIndex(retentionData.timePoints, time);

      // Usar os valores do ponto mais próximo
      percentageData.push(retentionData.data[nearestIndex]);
      viewerCountsData.push(retentionData.viewerCounts[nearestIndex]);

      // Formatar a label para o tempo
      const minutes = Math.floor(time / 60);
      const seconds = time % 60;
      formattedLabels.push(
        `${minutes}:${seconds < 10 ? "0" + seconds : seconds}`
      );
    }

    // Garantir que o último ponto seja a duração máxima
    if (
      timePoints.length > 0 &&
      timePoints[timePoints.length - 1] !== videoDuration
    ) {
      const time = videoDuration;
      timePoints.push(time);

      const nearestIndex = findNearestTimeIndex(retentionData.timePoints, time);
      percentageData.push(retentionData.data[nearestIndex]);
      viewerCountsData.push(retentionData.viewerCounts[nearestIndex]);

      const minutes = Math.floor(time / 60);
      const seconds = time % 60;
      formattedLabels.push(
        `${minutes}:${seconds < 10 ? "0" + seconds : seconds}`
      );
    }

    console.log("Pontos calculados:", {
      totalPoints: timePoints.length,
      labels: formattedLabels,
      percentageData: percentageData,
    });

    // Criar gráfico com os novos pontos calculados
    createRetentionChart(
      ctx,
      formattedLabels,
      percentageData,
      viewerCountsData
    );
  }

  /**
   * Função auxiliar para encontrar o índice do tempo mais próximo
   */
  function findNearestTimeIndex(timePoints, targetTime) {
    let nearestIndex = 0;
    let minDiff = Number.MAX_VALUE;

    for (let i = 0; i < timePoints.length; i++) {
      const diff = Math.abs(timePoints[i] - targetTime);
      if (diff < minDiff) {
        minDiff = diff;
        nearestIndex = i;
      }
    }

    return nearestIndex;
  }

  /**
   * Cria o gráfico de retenção com os dados fornecidos
   * @param {CanvasRenderingContext2D} ctx - Contexto do canvas
   * @param {Array} labels - Labels formatadas para os pontos de tempo
   * @param {Array} percentageData - Dados percentuais de retenção
   * @param {Array} viewerCountsData - Número absoluto de visualizadores
   */
  function createRetentionChart(ctx, labels, percentageData, viewerCountsData) {
    console.log("Criando gráfico de retenção com:", {
      labels: labels,
      percentageData: percentageData,
      viewerCountsData: viewerCountsData,
    });

    // Criar novo gráfico
    retentionChart = new Chart(ctx, {
      type: "line",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Retenção de Audiência (%)",
            data: percentageData,
            backgroundColor: "rgba(0, 115, 170, 0.1)",
            borderColor: "rgba(0, 115, 170, 1)",
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: "rgba(0, 115, 170, 1)",
            pointBorderColor: "#fff",
            pointBorderWidth: 1,
            tension: 0.3,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            mode: "index",
            intersect: false,
            callbacks: {
              label: function (context) {
                const pointIndex = context.dataIndex;
                const percentage = context.parsed.y;
                const viewerCount = viewerCountsData
                  ? viewerCountsData[pointIndex]
                  : null;
                const timeLabel = labels[pointIndex];

                if (viewerCount !== null) {
                  return `${percentage}% de retenção (${viewerCount} espectadores) em ${timeLabel}`;
                } else {
                  return `${percentage}% de retenção em ${timeLabel}`;
                }
              },
            },
          },
          title: {
            display: true,
            text: "Retenção de Audiência ao Longo do Vídeo",
            font: {
              size: 16,
            },
          },
        },
        scales: {
          y: {
            min: 0,
            max: 100,
            ticks: {
              callback: function (value) {
                return value + "%";
              },
            },
            title: {
              display: true,
              text: "Percentual de Retenção",
            },
          },
          x: {
            title: {
              display: true,
              text: "Tempo no Vídeo (segundos)",
            },
          },
        },
      },
    });
  }

  /**
   * Exibe mensagem quando não há dados disponíveis
   */
  function showNoData() {
    $(".vsl-analytics-charts").hide();
    $(".vsl-no-data").show();
  }

  /**
   * Renderiza o gráfico de dispositivos utilizados
   */
  function renderDevicesChart(devicesData) {
    const ctx = document.getElementById("devices-chart").getContext("2d");

    // Destruir gráfico anterior se existir
    if (devicesChart) {
      devicesChart.destroy();
    }

    // Verificar se há dados
    if (
      !devicesData ||
      !devicesData.labels ||
      devicesData.labels.length === 0
    ) {
      return;
    }

    // Formatar labels para exibição mais amigável
    const formattedLabels = devicesData.labels.map((label) => {
      // Capitalizar primeira letra e substituir 'unknown' por 'Desconhecido'
      if (label.toLowerCase() === "unknown") {
        return "Desconhecido";
      }
      return label.charAt(0).toUpperCase() + label.slice(1);
    });

    // Criar novo gráfico
    devicesChart = new Chart(ctx, {
      type: "pie",
      data: {
        labels: formattedLabels,
        datasets: [
          {
            data: devicesData.data,
            backgroundColor: devicesData.colors,
            borderColor: "#ffffff",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "right",
            labels: {
              padding: 20,
              boxWidth: 15,
              font: {
                size: 12,
              },
            },
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const value = context.raw;
                const total = context.dataset.data.reduce(
                  (acc, val) => acc + val,
                  0
                );
                const percentage = Math.round((value / total) * 100);
                return `${context.label}: ${value} (${percentage}%)`;
              },
            },
          },
        },
      },
    });
  }

  /**
   * Preenche a tabela de origens de visualizações
   */
  function populateReferrersTable(referrersData, isGrouped) {
    const $table = $("#referrers-table tbody");
    $table.empty();

    // Verificar se há dados
    if (!referrersData || referrersData.length === 0) {
      $table.append(
        `<tr><td colspan="2">${vslAnalytics.i18n.noData}</td></tr>`
      );
      return;
    }

    // Preencher a tabela com os dados
    $.each(referrersData, function (index, item) {
      // Formatar URL para exibição
      let displayUrl = item.url;
      if (displayUrl === "unknown" || displayUrl === "-") {
        displayUrl = "Desconhecido";
      } else if (displayUrl.length > 60) {
        // Truncar URLs muito longas
        displayUrl = displayUrl.substring(0, 57) + "...";
      }

      // Adicionar indicador de URL agrupada quando aplicável
      const urlTitle =
        isGrouped && displayUrl !== "Desconhecido"
          ? "URL agrupada (sem parâmetros)"
          : item.url;

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
    const $sourceFilter = $("#utm_source_filter");
    const currentSourceValue = $sourceFilter.val(); // Preservar seleção atual
    $sourceFilter.find("option:not(:first)").remove(); // Remover todas as opções exceto a primeira

    // Adicionar novas opções
    $.each(utmData.utm_sources, function (index, source) {
      if (source === "") return; // Pular a opção vazia (já existe)
      $sourceFilter.append(`<option value="${source}">${source}</option>`);
    });

    // Restaurar a seleção anterior, se existir
    if (currentSourceValue) {
      $sourceFilter.val(currentSourceValue);
    }

    // Atualizar dropdown de utm_campaign
    const $campaignFilter = $("#utm_campaign_filter");
    const currentCampaignValue = $campaignFilter.val(); // Preservar seleção atual
    $campaignFilter.find("option:not(:first)").remove(); // Remover todas as opções exceto a primeira

    // Adicionar novas opções
    $.each(utmData.utm_campaigns, function (index, campaign) {
      if (campaign === "") return; // Pular a opção vazia (já existe)
      $campaignFilter.append(
        `<option value="${campaign}">${campaign}</option>`
      );
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
    const $table = $("#utm-campaigns-table tbody");
    $table.empty();

    // Verificar se há dados
    if (!campaignsData || campaignsData.length === 0) {
      $table.append(
        `<tr><td colspan="5">${vslAnalytics.i18n.noData}</td></tr>`
      );
      return;
    }

    // Preencher a tabela com os dados
    $.each(campaignsData, function (index, campaign) {
      // Formatar para exibição
      const source =
        campaign.utm_source === "-" ? "Não definido" : campaign.utm_source;
      const medium =
        campaign.utm_medium === "-" ? "Não definido" : campaign.utm_medium;
      const campaignName =
        campaign.utm_campaign === "-" ? "Não definido" : campaign.utm_campaign;

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
