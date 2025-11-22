/**
 * VSL Player Conversions
 *
 * Handles tracking of conversion events triggered at specific points in VSL videos
 */
(function ($) {
  "use strict";

  // Store tracked events to avoid duplicate firing
  const trackedEvents = {};

  // Store the time polling interval instances
  const timePollingIntervals = {};

  // Initialize conversion tracking on all VSL players
  const initConversionTracking = function () {
    console.log(
      "[VSL Player Conversions] Iniciando rastreamento de conversões..."
    );

    // Verificar se as bibliotecas necessárias estão disponíveis
    checkTrackingLibraries();

    $(".vsl-player-container").each(function () {
      const $container = $(this);
      const vslId = $container.data("vsl-id");
      const containerId = $container.attr("id");

      console.log(
        `[VSL Player Conversions] Analisando player #${containerId} (VSL ID: ${vslId})`
      );

      // Verifique se há eventos de conversão
      const hasConversionEvents =
        $container.data("has-conversion-events") === true;
      console.log(
        `[VSL Player Conversions] Player #${containerId} - has-conversion-events:`,
        hasConversionEvents
      );

      if (!hasConversionEvents) {
        console.log(
          `[VSL Player Conversions] Player #${containerId} não tem eventos de conversão configurados.`
        );
        return;
      }

      // Obtenha os eventos de conversão do atributo de dados
      let conversionEvents;
      try {
        // Se os dados já estiverem desserializados como objeto
        conversionEvents = $container.data("conversion-events");
        console.log(
          `[VSL Player Conversions] Player #${containerId} - Dados brutos:`,
          conversionEvents
        );
        console.log(
          `[VSL Player Conversions] Player #${containerId} - Tipo de dados:`,
          typeof conversionEvents
        );

        // Se for string, parse para objeto (pode acontecer devido à serialização)
        if (typeof conversionEvents === "string") {
          console.log(
            `[VSL Player Conversions] Player #${containerId} - Fazendo parse de string JSON...`
          );
          conversionEvents = JSON.parse(conversionEvents);
        }

        // Converti para array se for objeto
        if (
          conversionEvents &&
          typeof conversionEvents === "object" &&
          !Array.isArray(conversionEvents)
        ) {
          console.log(
            `[VSL Player Conversions] Player #${containerId} - Convertendo objeto para array...`
          );
          conversionEvents = Object.keys(conversionEvents).map((key) => {
            return {
              id: key,
              ...conversionEvents[key],
            };
          });
        }

        console.log(
          `[VSL Player Conversions] Player #${containerId} - Eventos processados:`,
          conversionEvents
        );
      } catch (e) {
        console.error(
          `[VSL Player Conversions] Player #${containerId} - ERRO ao processar eventos:`,
          e
        );
        return;
      }

      if (!conversionEvents || !conversionEvents.length) {
        console.warn(
          `[VSL Player Conversions] Player #${containerId} - Nenhum evento válido encontrado.`
        );
        return;
      }

      console.log(
        `[VSL Player Conversions] Player #${containerId} - ${conversionEvents.length} evento(s) de conversão encontrado(s)`
      );

      // Inicialize cada evento no objeto de eventos rastreados
      conversionEvents.forEach(function (event) {
        const eventKey = `${vslId}_${event.id}`;
        trackedEvents[eventKey] = false;
        console.log(
          `[VSL Player Conversions] Player #${containerId} - Evento registrado: "${event.name}" aos ${event.time}s (ID: ${event.id})`
        );
        console.log(
          `[VSL Player Conversions] Player #${containerId} - Integrações ativas:`,
          {
            ga: event.ga === "1",
            gads: event.gads === "1",
            fbpixel: event.fbpixel === "1",
          }
        );
      });

      // Aguardar o player estar pronto antes de configurar o polling
      console.log(
        `[VSL Player Conversions] Player #${containerId} - Aguardando player estar pronto...`
      );

      // Escutar o evento de player pronto do YouTube
      $(document).one("YT.PlayerReady", function (event, player, scriptId) {
        // Verificar se é o player correto
        const playerContainerId = player.getIframe().id.replace("-inner", "");
        if (playerContainerId === containerId) {
          console.log(
            `[VSL Player Conversions] Player #${containerId} - Player pronto! Iniciando polling...`
          );
          setupTimePolling($container, containerId, vslId, conversionEvents);
        }
      });

      // Listen for messages from the YouTube iframe (for backwards compatibility)
      window.addEventListener("message", function (event) {
        let data;

        // Parse the data if it's a string
        if (typeof event.data === "string") {
          try {
            data = JSON.parse(event.data);
          } catch (error) {
            return;
          }
        } else {
          data = event.data;
        }

        // Get the current time if available
        const currentTime = data.info?.currentTime;
        const videoId = data.info?.videoData?.video_id;

        // Skip if no time info or if the video ID doesn't match
        if (
          typeof currentTime !== "number" ||
          videoId !== $container.data("video-id")
        ) {
          return;
        }

        // Check all conversion events
        checkConversionEvents(vslId, currentTime, conversionEvents);
      });
    });
  };

  // Verificar se as bibliotecas de rastreamento estão disponíveis
  const checkTrackingLibraries = function () {
    if (typeof gtag !== "function") {
      console.warn(
        "[VSL Player] Google Analytics/Ads não detectado. Por favor, adicione o código de rastreamento do Google Analytics ou Google Ads no cabeçalho do site para habilitar o rastreamento de conversões."
      );
    }

    if (typeof fbq !== "function") {
      console.warn(
        "[VSL Player] Facebook Pixel não detectado. Por favor, adicione o código do Facebook Pixel no cabeçalho do site para habilitar o rastreamento de conversões para o Facebook."
      );
    }
  };

  // Setup active polling for video time - similar à função do offerReveal
  const setupTimePolling = function (
    $container,
    containerId,
    vslId,
    conversionEvents
  ) {
    if (!containerId) {
      console.error(
        `[VSL Player Conversions] ERRO: containerId não fornecido!`
      );
      return;
    }

    // Clear any existing interval
    if (timePollingIntervals[containerId]) {
      clearInterval(timePollingIntervals[containerId]);
    }

    console.log(
      `[VSL Player Conversions] Player #${containerId} - Polling iniciado (verificação a cada 500ms)`
    );

    // Poll every 500ms to check video time
    timePollingIntervals[containerId] = setInterval(function () {
      // Verifique se todos os eventos já foram rastreados
      const allEventsTracked = conversionEvents.every(function (event) {
        const eventKey = `${vslId}_${event.id}`;
        return trackedEvents[eventKey] === true;
      });

      // Se todos os eventos forem rastreados, limpe o intervalo
      if (allEventsTracked) {
        clearInterval(timePollingIntervals[containerId]);
        return;
      }

      // Access the player through window.vslPlayers global object
      if (window.vslPlayers && window.vslPlayers[containerId]) {
        try {
          const player = window.vslPlayers[containerId];
          const currentTime = player.getCurrentTime();

          if (typeof currentTime === "number") {
            // Check all conversion events
            checkConversionEvents(vslId, currentTime, conversionEvents);
          }
        } catch (e) {
          console.error(
            `[VSL Player Conversions] Player #${containerId} - ERRO ao obter tempo do vídeo:`,
            e
          );
        }
      } else {
        // Log apenas uma vez quando o player não está disponível
        if (!window.vslPlayersWarningShown) {
          console.warn(
            `[VSL Player Conversions] Player #${containerId} - Aguardando inicialização do player...`
          );
          window.vslPlayersWarningShown = true;
        }
      }
    }, 500);
  };

  // Check and trigger conversion events based on current time
  const checkConversionEvents = function (
    vslId,
    currentTime,
    conversionEvents
  ) {
    conversionEvents.forEach(function (event) {
      const eventKey = `${vslId}_${event.id}`;

      // Skip if already tracked
      if (trackedEvents[eventKey] === true) {
        return;
      }

      // Verifique se o evento deve ser disparado
      const eventTime = parseInt(event.time, 10) || 0;
      if (currentTime >= eventTime) {
        console.log(
          `[VSL Player Conversions] ⚡ DISPARANDO EVENTO: "${event.name}" (${currentTime}s >= ${eventTime}s)`
        );

        // Marque o evento como rastreado
        trackedEvents[eventKey] = true;

        // Dispare o evento de conversão
        triggerConversionEvent(event, vslId, currentTime);
      }
    });
  };

  // Trigger the actual conversion event to analytics platforms
  const triggerConversionEvent = function (event, vslId, currentTime) {
    console.log(
      `[VSL Player Conversions] 🎯 Processando evento: "${event.name}"`
    );
    console.log(`[VSL Player Conversions] Dados do evento:`, {
      name: event.name,
      time: event.time,
      id: event.id,
      vslId: vslId,
      currentTime: currentTime,
    });

    // Certifique-se de que os valores sejam strings para comparação
    const gaEnabled = String(event.ga) === "1";
    const gadsEnabled = String(event.gads) === "1";
    const fbpixelEnabled = String(event.fbpixel) === "1";

    console.log(`[VSL Player Conversions] Integrações ativas:`, {
      ga: gaEnabled,
      gads: gadsEnabled,
      fbpixel: fbpixelEnabled,
    });

    // Google Analytics (GA4)
    if (gaEnabled) {
      if (typeof gtag === "function") {
        console.log(
          `[VSL Player Conversions] ✅ Enviando para Google Analytics (GA4)...`
        );
        // Send event to GA4
        gtag("event", event.name, {
          event_category: "VSL Player",
          event_label: `Timestamp: ${event.time}s`,
          vsl_id: vslId,
        });
        console.log(
          `[VSL Player Conversions] ✅ Evento enviado para GA4 com sucesso!`
        );
      } else {
        console.warn(
          `[VSL Player Conversions] ⚠️ Google Analytics está ativado para este evento, mas a função gtag não está disponível no site.`
        );
      }
    }

    // Google Ads
    if (gadsEnabled) {
      if (typeof gtag === "function") {
        console.log(`[VSL Player Conversions] ✅ Enviando para Google Ads...`);
        // Send conversion to Google Ads
        gtag("event", "conversion", {
          send_to: "AW-CONVERSION_ID/" + event.name,
        });
        console.log(
          `[VSL Player Conversions] ✅ Conversão enviada para Google Ads!`
        );
        console.warn(
          `[VSL Player Conversions] ⚠️ IMPORTANTE: Substitua 'AW-CONVERSION_ID' pelo seu ID real de conversão do Google Ads!`
        );
      } else {
        console.warn(
          `[VSL Player Conversions] ⚠️ Google Ads está ativado para este evento, mas a função gtag não está disponível no site.`
        );
      }
    }

    // Facebook Pixel
    if (fbpixelEnabled) {
      if (typeof fbq === "function") {
        console.log(
          `[VSL Player Conversions] ✅ Enviando para Facebook Pixel...`
        );
        // Send event to Facebook Pixel
        fbq("track", event.name);
        console.log(
          `[VSL Player Conversions] ✅ Evento enviado para Facebook Pixel com sucesso!`
        );
      } else {
        console.warn(
          `[VSL Player Conversions] ⚠️ Facebook Pixel está ativado para este evento, mas a função fbq não está disponível no site.`
        );
      }
    }

    // Trigger a custom event for third-party integrations
    console.log(
      `[VSL Player Conversions] 📡 Disparando evento jQuery customizado 'vsl_player_conversion'`
    );
    $(document).trigger("vsl_player_conversion", [event]);

    console.log(
      `[VSL Player Conversions] ✅ Evento "${event.name}" processado completamente!`
    );
  };

  // Initialize when document is ready
  $(document).ready(function () {
    initConversionTracking();
  });
})(jQuery);
