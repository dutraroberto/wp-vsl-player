/**
 * Script de coleta de dados de analytics para o VSL Player
 *
 * Gerencia o acompanhamento de eventos do player de vídeo como
 * impressões, plays, progresso, cliques e conclusões.
 *
 * @package    VSL_Player
 * @subpackage VSL_Player/public/js
 * @author     Roberto Dutra
 * @since      1.4.0
 */

(function ($) {
  "use strict";

  // Função de log para debug
  function vslAnalyticsLog(message, data) {
    console.log(
      "%c[VSL Analytics] " + message,
      "background: #e74c3c; color: white; padding: 2px 5px; border-radius: 3px;",
      data || ""
    );
  }

  vslAnalyticsLog("Script de analytics carregado");

  // Vamos rastrear a interação do usuário com o vídeo
  window.VSL_Player_Interaction = {
    userClicked: false,
    firstPlaySent: false,
    resumeActive: false, // Flag para indicar se o recurso de retomada está ativo
  };

  vslAnalyticsLog(
    "VSL_Player_Interaction inicializado",
    window.VSL_Player_Interaction
  );

  // Função para gerar UUID compatível com todos navegadores
  function generateUUID() {
    // Implementação compatível com todos os navegadores
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(
      /[xy]/g,
      function (c) {
        const r = (Math.random() * 16) | 0;
        const v = c === "x" ? r : (r & 0x3) | 0x8;
        return v.toString(16);
      }
    );
  }

  // Gerar ou obter ID do visualizador
  const viewerId = localStorage.getItem("vsl_viewer_id") || generateUUID();
  localStorage.setItem("vsl_viewer_id", viewerId);

  // Sistema de batching de eventos
  const eventQueue = [];
  const BATCH_SIZE = 5;
  const BATCH_INTERVAL = 8000;
  let flushTimer = null;

  /**
   * Flush da fila de eventos para o servidor
   */
  function flushEventQueue() {
    if (eventQueue.length === 0) return;

    if (!window.VSL_ANALYTICS) {
      vslAnalyticsLog("ERRO: VSL_ANALYTICS não está configurado");
      eventQueue.length = 0;
      return;
    }

    const eventsToSend = eventQueue.splice(0, 50);
    const apiUrl =
      window.location.origin + "/wp-json/vsl-analytics/v1/collect-batch";

    vslAnalyticsLog("Enviando batch de eventos", {
      count: eventsToSend.length,
    });

    fetch(apiUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ events: eventsToSend }),
    })
      .then(function (response) {
        if (!response.ok) {
          vslAnalyticsLog("Erro ao enviar batch", { status: response.status });
        }
        return response.json();
      })
      .then(function (data) {
        vslAnalyticsLog("Batch enviado com sucesso", data);
      })
      .catch(function (error) {
        vslAnalyticsLog("Erro de rede ao enviar batch", error);
      });

    if (flushTimer) {
      clearTimeout(flushTimer);
      flushTimer = null;
    }
  }

  /**
   * Agenda próximo flush automático
   */
  function scheduleNextFlush() {
    if (flushTimer) {
      clearTimeout(flushTimer);
    }
    flushTimer = setTimeout(flushEventQueue, BATCH_INTERVAL);
  }

  /**
   * Envia dados de analytics para a fila (batching)
   *
   * @param {string} event - Tipo de evento (impression, play, progress, etc)
   * @param {Object} extra - Dados adicionais para o evento
   * @param {boolean} immediate - Se true, envia imediatamente sem batching
   */
  function sendAnalytics(event, extra = {}, immediate = false) {
    vslAnalyticsLog("Adicionando evento à fila", {
      event: event,
      extra: extra,
    });

    if (!window.VSL_ANALYTICS) {
      vslAnalyticsLog("ERRO: VSL_ANALYTICS não está configurado");
      return;
    }

    const data = {
      event,
      sid: viewerId,
      pid: window.VSL_ANALYTICS.post_id || 0,
      ytid: window.VSL_ANALYTICS.yt_id || "",
      device: window.VSL_ANALYTICS.device || "desktop",
      url: location.href,
      ...extra,
    };

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has("utm_source")) {
      data.utm_source = urlParams.get("utm_source");
    }
    if (urlParams.has("utm_medium")) {
      data.utm_medium = urlParams.get("utm_medium");
    }
    if (urlParams.has("utm_campaign")) {
      data.utm_campaign = urlParams.get("utm_campaign");
    }

    if (immediate) {
      const apiUrl =
        window.location.origin + "/wp-json/vsl-analytics/v1/collect";
      fetch(apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      });
      return;
    }

    eventQueue.push(data);

    if (eventQueue.length >= BATCH_SIZE) {
      flushEventQueue();
    } else {
      scheduleNextFlush();
    }
  }

  window.addEventListener("beforeunload", function () {
    if (eventQueue.length > 0) {
      flushEventQueue();
    }
  });

  /**
   * Inicializa o sistema de analytics quando o DOM estiver pronto
   */
  document.addEventListener("DOMContentLoaded", function () {
    // Se não houver configuração de analytics, saia
    if (!window.VSL_ANALYTICS) {
      return;
    }

    sendAnalytics("impression", {}, true);

    // Vamos adicionar um evento de clique ao botão de play SVG
    // Isso nos permite saber quando o usuário clica para iniciar o vídeo
    const playButtons = document.querySelectorAll(
      ".vsl-start-overlay, .vsl-play-button-svg"
    );
    playButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        window.VSL_Player_Interaction.userClicked = true;
      });
    });

    // Verificar se temos players com retomada pendente
    $(".vsl-player-container").each(function () {
      var resumePending = $(this).attr("data-resume-pending") === "true";
      var containerId = $(this).attr("id");
      vslAnalyticsLog("Verificando se o player está em modo de retomada", {
        containerId: containerId,
        resumePending: resumePending,
        atributo: $(this).attr("data-resume-pending"),
      });

      if (resumePending) {
        window.VSL_Player_Interaction.resumeActive = true;
        vslAnalyticsLog("Player em modo de retomada detectado", {
          containerId: containerId,
          VSL_Player_Interaction: window.VSL_Player_Interaction,
        });
      }
    });

    // Monitorar o player do YouTube
    setupYouTubeTracking();

    // Monitorar cliques em botões CTA
    const ctaButtons = document.querySelectorAll(
      ".vsl-cta-button, .vsl-reveal-offer"
    );

    ctaButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        sendAnalytics("cta_click", { cta: 1 });
      });
    });

    // Monitorar quando o usuário sai da página
    window.addEventListener("beforeunload", function () {
      if (
        window.vslYTPlayer &&
        typeof window.vslYTPlayer.getCurrentTime === "function"
      ) {
        const currentTime = Math.floor(window.vslYTPlayer.getCurrentTime());

        sendAnalytics(
          "exit",
          {
            progress_sec: currentTime,
          },
          true
        );
      }
    });
  });

  /**
   * Configura o monitoramento do player YouTube usando os eventos jQuery
   */
  function setupYouTubeTracking() {
    // Variáveis para controlar o tracking
    let progressInterval = null;
    let lastProgressTime = 0;
    let progressMilestones = [10, 25, 50, 75, 100];
    let sentMilestones = {};

    // Escutar o evento personalizado de ação de retomada
    $(document).on("vsl.resumeAction", function (e, action, timePosition) {
      vslAnalyticsLog("Evento vsl.resumeAction recebido", {
        action: action,
        timePosition: timePosition,
      });
      // Enviar evento analítico para a ação de retomada
      sendAnalytics("resume_action", {
        action: action, // 'continue' ou 'restart'
        progress_sec: Math.floor(timePosition || 0),
      });
    });

    // Detectar quando o player estiver pronto
    $(document).on("YT.PlayerReady", function (event, player, scriptId) {
      if (!player) {
        return;
      }

      // Obter ID do container
      const containerId = player.getIframe().id.replace("-inner", "");
      const $container = $("#" + containerId);

      // Verificar se este container está em modo de retomada
      const isResumePending = $container.attr("data-resume-pending") === "true";
      const vslId = $container.data("vsl-id");
      vslAnalyticsLog("Verificando estado do container", {
        containerId: containerId,
        vslId: vslId,
        isResumePending: isResumePending,
        atributoValor: $container.attr("data-resume-pending"),
      });

      // Verificar overlay de início e adicionar listener
      const $startOverlay = $container.find(".vsl-start-overlay");
      vslAnalyticsLog("Overlay de início encontrado", {
        containerId: containerId,
        overlayEncontrado: $startOverlay.length > 0,
      });
      if ($startOverlay.length) {
        // Só registre o evento de clique se não estiver em modo de retomada
        if (!isResumePending) {
          $startOverlay
            .off("click.analytics")
            .on("click.analytics", function () {
              if (!window.VSL_Player_Interaction.firstPlaySent) {
                window.VSL_Player_Interaction.userClicked = true;
                window.VSL_Player_Interaction.firstPlaySent = true;

                sendAnalytics("play", {
                  progress_sec: Math.floor(player.getCurrentTime() || 0),
                });

                // Iniciar monitoramento de progresso
                startProgressTracking(player);
              }
            });
        }

        // Também monitorar os botões de retomada na overlay de resumo
        const $resumeOverlay = $container.find(".vsl-resume-overlay");
        vslAnalyticsLog("Overlay de resumo encontrado", {
          containerId: containerId,
          overlayEncontrado: $resumeOverlay.length > 0,
        });
        if ($resumeOverlay.length) {
          $resumeOverlay
            .find(".vsl-resume-continue, .vsl-resume-restart")
            .off("click.analytics")
            .on("click.analytics", function () {
              window.VSL_Player_Interaction.userClicked = true;
              window.VSL_Player_Interaction.firstPlaySent = true;

              setTimeout(function () {
                sendAnalytics("play", {
                  progress_sec: Math.floor(player.getCurrentTime() || 0),
                  resume: $(this).hasClass("vsl-resume-continue")
                    ? "continue"
                    : "restart",
                });

                // Iniciar monitoramento de progresso
                startProgressTracking(player);
              }, 1000); // Pequeno delay para garantir que o player foi inicializado
            });
        }
      } else {
      }

      // Detectar estado de reprodução do vídeo
      $(document).on(
        "YT.PlayerState.PLAYING",
        function (e, thisPlayer, thisScriptId) {
          if (thisPlayer === player) {
            // Verificar se o container tem o atributo de retomada pendente
            const $playerContainer = $("#vsl-player-" + thisScriptId);
            const isResumePending =
              $playerContainer.attr("data-resume-pending") === "true";

            vslAnalyticsLog("Estado do player ao receber evento PLAYING", {
              scriptId: thisScriptId,
              playerContainerId: $playerContainer.attr("id"),
              isResumePending: isResumePending,
              atributoValor: $playerContainer.attr("data-resume-pending"),
              userClicked: window.VSL_Player_Interaction.userClicked,
              firstPlaySent: window.VSL_Player_Interaction.firstPlaySent,
              resumeActive: window.VSL_Player_Interaction.resumeActive,
            });

            // Se não estiver em modo de retomada, processe normalmente
            if (!isResumePending) {
              // Se não há overlay mas o usuário interagiu com o player
              if (
                window.VSL_Player_Interaction.userClicked &&
                !window.VSL_Player_Interaction.firstPlaySent
              ) {
                window.VSL_Player_Interaction.firstPlaySent = true;

                sendAnalytics("play", {
                  progress_sec: Math.floor(player.getCurrentTime() || 0),
                });
              }

              // Iniciar monitoramento de progresso se já tiver play
              if (window.VSL_Player_Interaction.firstPlaySent) {
                startProgressTracking(player);
              }
            } else {
              // Em modo de retomada, deixe o player-resume gerenciar o player
              // Não enviaremos analytics automaticamente até que o usuário faça uma escolha
            }
          }
        }
      );

      // Detectar fim do vídeo - usar namespace para evitar conflitos
      $(document).on(
        "YT.PlayerState.ENDED.analytics",
        function (e, thisPlayer, thisScriptId) {
          if (thisPlayer === player) {
            vslAnalyticsLog("Vídeo terminou, registrando evento complete", {
              scriptId: thisScriptId,
            });

            // Registrar evento de conclusão do vídeo
            sendAnalytics(
              "complete",
              {
                progress_sec: Math.floor(player.getDuration() || 0),
                progress_percent: 100,
              },
              true
            );

            // Limpar intervalo de progresso
            stopProgressTracking();

            // IMPORTANTE: Não manipular o player aqui, deixe o overlay de fim cuidar disso
            vslAnalyticsLog("Aguardando overlay de fim exibir");
          }
        }
      );

      // Detectar saída da página
      $(window).on("beforeunload", function () {
        if (player && player.getPlayerState && player.getPlayerState() !== 0) {
          sendAnalytics(
            "exit",
            {
              progress_sec: Math.floor(player.getCurrentTime() || 0),
              progress_percent: Math.round(
                (player.getCurrentTime() / player.getDuration()) * 100
              ),
            },
            true
          );
        }
      });
    });

    // Função para iniciar o monitoramento de progresso
    function startProgressTracking(player) {
      // Evita múltiplos intervalos
      stopProgressTracking();

      // Não iniciar monitoramento se o vídeo estiver em modo de retomada e o usuário ainda não fez uma escolha
      vslAnalyticsLog(
        "Verificando se deve iniciar monitoramento de progresso",
        window.VSL_Player_Interaction
      );
      if (
        window.VSL_Player_Interaction.resumeActive &&
        !window.VSL_Player_Interaction.userClicked
      ) {
        vslAnalyticsLog(
          "Monitoramento não iniciado pois o vídeo está em modo de retomada e o usuário não interagiu"
        );
        return;
      }
      vslAnalyticsLog("Iniciando monitoramento de progresso");

      // Monitorar progresso a cada 2 segundos
      progressInterval = setInterval(function () {
        try {
          if (
            player &&
            typeof player.getCurrentTime === "function" &&
            typeof player.getDuration === "function" &&
            player.getPlayerState &&
            player.getPlayerState() === 1
          ) {
            // Verificar se está tocando

            const currentTime = Math.floor(player.getCurrentTime());
            const duration = Math.floor(player.getDuration());

            if (duration > 0) {
              const percent = Math.round((currentTime / duration) * 100);

              // Log detalhado

              // Envio por marcos percentuais
              progressMilestones.forEach(function (milestone) {
                if (percent >= milestone && !sentMilestones[milestone]) {
                  sentMilestones[milestone] = true;

                  sendAnalytics("progress", {
                    progress_sec: currentTime,
                    progress_percent: milestone,
                  });
                }
              });

              // Envio por tempo (a cada 10s)
              if (currentTime > lastProgressTime + 10) {
                lastProgressTime = currentTime;

                sendAnalytics("progress", {
                  progress_sec: currentTime,
                });
              }
            }
          }
        } catch (e) {}
      }, 2000); // Verificar a cada 2 segundos
    }

    // Função para parar o monitoramento de progresso
    function stopProgressTracking() {
      if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;

        // Resetar para próxima reprodução
        sentMilestones = {};
        lastProgressTime = 0;
      }
    }
  }

  // Expor funções globalmente para uso em outros scripts
  window.vslAnalytics = {
    sendEvent: sendAnalytics,
    log: vslAnalyticsLog,
    debug: function () {
      return {
        interaction: window.VSL_Player_Interaction,
        resumePendingPlayers: $(
          '.vsl-player-container[data-resume-pending="true"]'
        ).length,
        resumeOverlays: $(".vsl-resume-overlay").length,
      };
    },
  };

  // Log de debug final após setup
  vslAnalyticsLog("Setup de analytics concluído");
})(jQuery);
