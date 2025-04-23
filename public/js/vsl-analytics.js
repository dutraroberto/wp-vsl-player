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

(function($) {
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
   * Configura o monitoramento do player YouTube usando os eventos jQuery
   */
  function setupYouTubeTracking() {
    console.log('[VSL Analytics] Configurando tracking do YouTube Player via eventos jQuery');

    // Variáveis para controlar o tracking
    let progressInterval = null;
    let lastProgressTime = 0;
    let progressMilestones = [10, 25, 50, 75, 100];
    let sentMilestones = {};

    // Detectar quando o player estiver pronto
    $(document).on('YT.PlayerReady', function(event, player, scriptId) {
      if (!player) {
        console.log('[VSL Analytics] Player não disponível no evento YT.PlayerReady');
        return;
      }
      
      console.log('[VSL Analytics] Player detectado via evento YT.PlayerReady:', player);
      
      // Obter ID do container
      const containerId = player.getIframe().id.replace('-inner', '');
      const $container = $('#' + containerId);
      
      console.log('[VSL Analytics] Container do player:', containerId);
      
      // Verificar overlay de início e adicionar listener
      const $startOverlay = $container.find('.vsl-start-overlay');
      if ($startOverlay.length) {
        console.log('[VSL Analytics] Overlay de início encontrado, adicionando listener');
        
        $startOverlay.off('click.analytics').on('click.analytics', function() {
          if (!window.VSL_Player_Interaction.firstPlaySent) {
            console.log('[VSL Analytics] Clique no overlay detectado, enviando evento play');
            window.VSL_Player_Interaction.userClicked = true;
            window.VSL_Player_Interaction.firstPlaySent = true;
            
            sendAnalytics('play', {
              progress_sec: Math.floor(player.getCurrentTime() || 0)
            });
            
            // Iniciar monitoramento de progresso
            startProgressTracking(player);
          }
        });
      } else {
        console.log('[VSL Analytics] Overlay de início não encontrado');
      }
      
      // Detectar estado de reprodução do vídeo
      $(document).on('YT.PlayerState.PLAYING', function(e, thisPlayer, thisScriptId) {
        if (thisPlayer === player) {
          console.log('[VSL Analytics] Vídeo está sendo reproduzido');
          
          // Se não há overlay mas o usuário interagiu com o player
          if (window.VSL_Player_Interaction.userClicked && !window.VSL_Player_Interaction.firstPlaySent) {
            console.log('[VSL Analytics] Primeiro play pelo usuário detectado via evento');
            window.VSL_Player_Interaction.firstPlaySent = true;
            
            sendAnalytics('play', {
              progress_sec: Math.floor(player.getCurrentTime() || 0)
            });
          }
          
          // Iniciar monitoramento de progresso se já tiver play
          if (window.VSL_Player_Interaction.firstPlaySent) {
            startProgressTracking(player);
          }
        }
      });
      
      // Detectar fim do vídeo
      $(document).on('YT.PlayerState.ENDED', function(e, thisPlayer, thisScriptId) {
        if (thisPlayer === player) {
          console.log('[VSL Analytics] Vídeo completado (via evento)');
          
          sendAnalytics('complete', { 
            progress_sec: Math.floor(player.getDuration() || 0),
            progress_percent: 100
          });
          
          // Limpar intervalo de progresso
          stopProgressTracking();
        }
      });
      
      // Detectar saída da página
      $(window).on('beforeunload', function() {
        if (player && player.getPlayerState && player.getPlayerState() !== 0) {
          console.log('[VSL Analytics] Usuário saindo da página');
          
          sendAnalytics('exit', {
            progress_sec: Math.floor(player.getCurrentTime() || 0),
            progress_percent: Math.round((player.getCurrentTime() / player.getDuration()) * 100)
          });
        }
      });
    });
    
    // Função para iniciar o monitoramento de progresso
    function startProgressTracking(player) {
      // Evita múltiplos intervalos
      stopProgressTracking();
      
      console.log('[VSL Analytics] Iniciando monitoramento de progresso');
      
      // Monitorar progresso a cada 2 segundos
      progressInterval = setInterval(function() {
        try {
          if (player && typeof player.getCurrentTime === 'function' && 
              typeof player.getDuration === 'function' && 
              player.getPlayerState && player.getPlayerState() === 1) { // Verificar se está tocando
            
            const currentTime = Math.floor(player.getCurrentTime());
            const duration = Math.floor(player.getDuration());
            
            if (duration > 0) {
              const percent = Math.round((currentTime / duration) * 100);
              
              // Log detalhado
              console.log(`[VSL Analytics] Progresso atual: ${currentTime}s de ${duration}s (${percent}%)`);
              
              // Envio por marcos percentuais
              progressMilestones.forEach(function(milestone) {
                if (percent >= milestone && !sentMilestones[milestone]) {
                  sentMilestones[milestone] = true;
                  console.log(`[VSL Analytics] Enviando progresso: ${milestone}% (${currentTime}s)`);
                  
                  sendAnalytics('progress', { 
                    progress_sec: currentTime, 
                    progress_percent: milestone 
                  });
                }
              });
              
              // Envio por tempo (a cada 10s)
              if (currentTime > lastProgressTime + 10) {
                lastProgressTime = currentTime;
                console.log('[VSL Analytics] Enviando progresso (tempo):', currentTime);
                
                sendAnalytics('progress', { 
                  progress_sec: currentTime 
                });
              }
            }
          }
        } catch(e) {
          console.error('[VSL Analytics] Erro no monitoramento de progresso:', e);
        }
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
    sendEvent: sendAnalytics
  };
})(jQuery);
