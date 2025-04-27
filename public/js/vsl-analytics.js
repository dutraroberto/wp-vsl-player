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


  /**
   * Envia dados de analytics para a API REST
   * 
   * @param {string} event - Tipo de evento (impression, play, progress, etc)
   * @param {Object} extra - Dados adicionais para o evento
   */
  function sendAnalytics(event, extra = {}) {

    
    if (!window.VSL_ANALYTICS || !window.VSL_ANALYTICS.nonce) {

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

    
    wp.apiFetch({
      path: '/vsl-analytics/v1/collect',
      method: 'POST',
      headers: { 'X-WP-Nonce': window.VSL_ANALYTICS.nonce },
      data: data
    }).then(function(response) {

      return response;
    }).catch(function(error) {

    });
  }

  /**
   * Inicializa o sistema de analytics quando o DOM estiver pronto
   */
  document.addEventListener('DOMContentLoaded', function() {
    // Se não houver configuração de analytics, saia
    if (!window.VSL_ANALYTICS) {

      return;
    }
    


    // Registrar impressão imediatamente

    sendAnalytics('impression');

    // Vamos adicionar um evento de clique ao botão de play SVG
    // Isso nos permite saber quando o usuário clica para iniciar o vídeo
    const playButtons = document.querySelectorAll('.vsl-start-overlay, .vsl-play-button-svg');
    playButtons.forEach(function(button) {
      button.addEventListener('click', function() {

        window.VSL_Player_Interaction.userClicked = true;
      });
    });

    // Monitorar o player do YouTube
    setupYouTubeTracking();

    // Monitorar cliques em botões CTA
    const ctaButtons = document.querySelectorAll('.vsl-cta-button, .vsl-reveal-offer');

    
    ctaButtons.forEach(function(button) {
      button.addEventListener('click', function() {

        sendAnalytics('cta_click', { cta: 1 });
      });
    });

    // Monitorar quando o usuário sai da página
    window.addEventListener('beforeunload', function() {

      if (window.vslYTPlayer && typeof window.vslYTPlayer.getCurrentTime === 'function') {
        const currentTime = Math.floor(window.vslYTPlayer.getCurrentTime());

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


    // Variáveis para controlar o tracking
    let progressInterval = null;
    let lastProgressTime = 0;
    let progressMilestones = [10, 25, 50, 75, 100];
    let sentMilestones = {};

    // Detectar quando o player estiver pronto
    $(document).on('YT.PlayerReady', function(event, player, scriptId) {
      if (!player) {

        return;
      }
      

      
      // Obter ID do container
      const containerId = player.getIframe().id.replace('-inner', '');
      const $container = $('#' + containerId);
      

      
      // Verificar overlay de início e adicionar listener
      const $startOverlay = $container.find('.vsl-start-overlay');
      if ($startOverlay.length) {

        
        $startOverlay.off('click.analytics').on('click.analytics', function() {
          if (!window.VSL_Player_Interaction.firstPlaySent) {

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

      }
      
      // Detectar estado de reprodução do vídeo
      $(document).on('YT.PlayerState.PLAYING', function(e, thisPlayer, thisScriptId) {
        if (thisPlayer === player) {

          
          // Se não há overlay mas o usuário interagiu com o player
          if (window.VSL_Player_Interaction.userClicked && !window.VSL_Player_Interaction.firstPlaySent) {

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

              
              // Envio por marcos percentuais
              progressMilestones.forEach(function(milestone) {
                if (percent >= milestone && !sentMilestones[milestone]) {
                  sentMilestones[milestone] = true;

                  
                  sendAnalytics('progress', { 
                    progress_sec: currentTime, 
                    progress_percent: milestone 
                  });
                }
              });
              
              // Envio por tempo (a cada 10s)
              if (currentTime > lastProgressTime + 10) {
                lastProgressTime = currentTime;

                
                sendAnalytics('progress', { 
                  progress_sec: currentTime 
                });
              }
            }
          }
        } catch(e) {

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
