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

(function() {
  'use strict';
  
  console.log('[VSL Analytics] Script carregado!');

  // Vamos rastrear a interação do usuário com o vídeo
  window.VSL_Player_Interaction = {
    userClicked: false,
    firstPlaySent: false
  };

  // Função para gerar UUID compatível com todos navegadores
  function generateUUID() {
    // Implementação compatível com todos os navegadores
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }
  
  // Gerar ou obter ID do visualizador
  const viewerId = localStorage.getItem('vsl_viewer_id') || generateUUID();
  localStorage.setItem('vsl_viewer_id', viewerId);
  console.log('[VSL Analytics] ID do visualizador:', viewerId);

  /**
   * Envia dados de analytics para a API REST
   * 
   * @param {string} event - Tipo de evento (impression, play, progress, etc)
   * @param {Object} extra - Dados adicionais para o evento
   */
  function sendAnalytics(event, extra = {}) {
    console.log(`[VSL Analytics] Evento disparado: ${event}`, extra);
    
    if (!window.VSL_ANALYTICS || !window.VSL_ANALYTICS.nonce) {
      console.error('[VSL Analytics] Configuração incompleta:', window.VSL_ANALYTICS);
      return;
    }

    // Prepare os dados do evento
    const data = {
      event,
      sid: viewerId,
      pid: window.VSL_ANALYTICS.post_id || 0,
      ytid: window.VSL_ANALYTICS.yt_id || '',
      device: window.VSL_ANALYTICS.device || 'desktop',
      url: location.href,
      ...extra
    };

    // Adicionar parâmetros UTM se disponíveis
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('utm_source')) {
      data.utm_source = urlParams.get('utm_source');
    }
    if (urlParams.has('utm_medium')) {
      data.utm_medium = urlParams.get('utm_medium');
    }
    if (urlParams.has('utm_campaign')) {
      data.utm_campaign = urlParams.get('utm_campaign');
    }

    // Enviar para a API REST
    console.log('[VSL Analytics] Enviando dados para API:', data);
    
    wp.apiFetch({
      path: '/vsl-analytics/v1/collect',
      method: 'POST',
      headers: { 'X-WP-Nonce': window.VSL_ANALYTICS.nonce },
      data: data
    }).then(function(response) {
      console.log('[VSL Analytics] Evento registrado com sucesso:', event);
      return response;
    }).catch(function(error) {
      console.error('[VSL Analytics] Erro ao enviar evento:', error);
    });
  }

  /**
   * Inicializa o sistema de analytics quando o DOM estiver pronto
   */
  document.addEventListener('DOMContentLoaded', function() {
    // Se não houver configuração de analytics, saia
    if (!window.VSL_ANALYTICS) {
      console.error('[VSL Analytics] VSL_ANALYTICS não está disponível no objeto window');
      return;
    }
    
    console.log('[VSL Analytics] Configuração carregada:', window.VSL_ANALYTICS);

    // Registrar impressão imediatamente
    console.log('[VSL Analytics] Registrando impressão inicial do vídeo');
    sendAnalytics('impression');

    // Vamos adicionar um evento de clique ao botão de play SVG
    // Isso nos permite saber quando o usuário clica para iniciar o vídeo
    const playButtons = document.querySelectorAll('.vsl-start-overlay, .vsl-play-button-svg');
    playButtons.forEach(function(button) {
      button.addEventListener('click', function() {
        console.log('[VSL Analytics] Clique no botão de play detectado');
        window.VSL_Player_Interaction.userClicked = true;
      });
    });

    // Monitorar o player do YouTube
    setupYouTubeTracking();

    // Monitorar cliques em botões CTA
    const ctaButtons = document.querySelectorAll('.vsl-cta-button, .vsl-reveal-offer');
    console.log('[VSL Analytics] Botões CTA encontrados:', ctaButtons.length);
    
    ctaButtons.forEach(function(button) {
      button.addEventListener('click', function() {
        console.log('[VSL Analytics] Clique em CTA detectado');
        sendAnalytics('cta_click', { cta: 1 });
      });
    });

    // Monitorar quando o usuário sai da página
    window.addEventListener('beforeunload', function() {
      console.log('[VSL Analytics] Usuário saindo da página');
      if (window.vslYTPlayer && typeof window.vslYTPlayer.getCurrentTime === 'function') {
        const currentTime = Math.floor(window.vslYTPlayer.getCurrentTime());
        console.log('[VSL Analytics] Enviando evento de saída com tempo:', currentTime);
        sendAnalytics('exit', {
          progress_sec: currentTime
        });
      }
    });
  });

  /**
   * Configura o monitoramento do player YouTube
   */
  function setupYouTubeTracking() {
    console.log('[VSL Analytics] Configurando tracking do YouTube Player');

        // Variáveis para controlar o tracking
    let progressInterval = null;
    let lastProgressTime = 0;
    let lastPercent = 0;
    let progressMilestones = [10, 25, 50, 75, 100];
    let sentMilestones = {};
    let progressPollingActive = false;

    // Função que monitora a variável global do player
    function checkForYTPlayer() {
      // Se o player já está disponível
      if (window.vslYTPlayer && typeof window.vslYTPlayer.addEventListener === 'function') {
        console.log('[VSL Analytics] YouTube Player encontrado, adicionando listener');
        
        // Adicione o listener para mudanças de estado
        window.vslYTPlayer.addEventListener('onStateChange', function(state) {
          console.log('[VSL Analytics] Estado do player alterado:', state.data);
          
          // Verifica se é o estado PLAYING (1)
          if (state.data === YT.PlayerState.PLAYING) {
            // Só registra o play se o usuário clicou no botão
            if (window.VSL_Player_Interaction.userClicked && !window.VSL_Player_Interaction.firstPlaySent) {
              console.log('[VSL Analytics] Primeiro play pelo usuário detectado, enviando evento');
              window.VSL_Player_Interaction.firstPlaySent = true;
              sendAnalytics('play');
            }
            // Iniciar monitoramento de progresso SEMPRE que o vídeo tocar
            if (!progressPollingActive) {
              progressPollingActive = true;
              console.log('[VSL Analytics] Iniciando monitoramento de progresso via polling.');
              progressInterval = setInterval(function() {
                try {
                  if (window.vslYTPlayer && typeof window.vslYTPlayer.getCurrentTime === 'function' && typeof window.vslYTPlayer.getDuration === 'function') {
                    const currentTime = Math.floor(window.vslYTPlayer.getCurrentTime());
                    const duration = Math.floor(window.vslYTPlayer.getDuration());
                    if (duration > 0) {
                      const percent = Math.round((currentTime / duration) * 100);
                      // Log detalhado
                      console.log(`[VSL Analytics] (Polling) Progresso atual: ${currentTime}s de ${duration}s (${percent}%)`);
                      // Envio por marcos percentuais
                      progressMilestones.forEach(function(milestone) {
                        if (percent >= milestone && !sentMilestones[milestone]) {
                          sentMilestones[milestone] = true;
                          console.log(`[VSL Analytics] (Polling) Enviando progresso: ${milestone}% (${currentTime}s)`);
                          sendAnalytics('progress', { progress_sec: currentTime, progress_percent: milestone });
                        }
                      });
                      // Envio por tempo (a cada 10s)
                      if (currentTime > lastProgressTime + 10) {
                        lastProgressTime = currentTime;
                        console.log('[VSL Analytics] (Polling) Enviando progresso (tempo):', currentTime);
                        sendAnalytics('progress', { progress_sec: currentTime });
                      }
                    } else {
                      console.log('[VSL Analytics] (Polling) Duração do vídeo ainda não disponível.');
                    }
                  } else {
                    console.log('[VSL Analytics] (Polling) Player YouTube ou métodos não disponíveis.');
                  }
                } catch(e) {
                  console.error('[VSL Analytics] (Polling) Erro no monitoramento de progresso:', e);
                }
              }, 1000); // Polling a cada 1 segundo
            }
          } else if (state.data === YT.PlayerState.ENDED) {
            // Vídeo completo
            console.log('[VSL Analytics] Vídeo completado');
            if (window.vslYTPlayer && typeof window.vslYTPlayer.getDuration === 'function') {
              sendAnalytics('complete', { 
                progress_sec: Math.floor(window.vslYTPlayer.getDuration()),
                progress_percent: 100
              });
            }
            
            // Limpar intervalo de progresso
            if (progressInterval) {
              clearInterval(progressInterval);
              progressInterval = null;
            }
            progressPollingActive = false;
            // Resetar milestones para próxima reprodução
            sentMilestones = {};
            lastProgressTime = 0;
          }
        });

        // Removemos o intervalo de verificação após encontrar o player
        clearInterval(checkInterval);
      }
    }

    // Verificar a cada 500ms se o player está disponível
    const checkInterval = setInterval(checkForYTPlayer, 500);
  }

  // Expor funções globalmente para uso em outros scripts
  window.vslAnalytics = {
    sendEvent: sendAnalytics
  };
})();
