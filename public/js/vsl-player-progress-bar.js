/**
 * VSL Player Otimizado - Barra de Progresso Falsa
 * 
 * Cria uma barra de progresso que avança de forma não-linear, dando a impressão
 * de que o vídeo é mais curto do que realmente é.
 */
(function($) {
    'use strict';

    // Variáveis globais
    var progressBars = {};
    var progressIntervals = {};
    var videoDurations = {};
    var userInitiatedPlay = {}; // Rastreia se o play foi iniciado pelo usuário
    
    // Quando o DOM estiver pronto
    $(document).ready(function() {
        // Inicializa quando o player do YouTube estiver pronto
        $(document).on('YT.PlayerState.PLAYING', function(e, player, scriptId) {
            if (!progressBars[scriptId]) {
                initProgressBar(player, scriptId);
            } else if (userInitiatedPlay[scriptId]) {
                // Mostra a barra apenas se o play foi iniciado pelo usuário
                progressBars[scriptId].container.show();
                resumeProgressBar(scriptId);
            }
        });
        
        // Pausa a animação quando o vídeo for pausado
        $(document).on('YT.PlayerState.PAUSED', function(e, player, scriptId) {
            pauseProgressBar(scriptId);
            // Oculta a barra quando o vídeo for pausado
            if (progressBars[scriptId]) {
                progressBars[scriptId].container.hide();
            }
        });
        
        // Retoma a animação quando o vídeo for reproduzido
        $(document).on('YT.PlayerState.PLAYING', function(e, player, scriptId) {
            if (userInitiatedPlay[scriptId]) {
                resumeProgressBar(scriptId);
            }
        });
        
        // Reinicia a barra quando o vídeo for reiniciado
        $(document).on('YT.PlayerState.ENDED', function(e, player, scriptId) {
            resetProgressBar(scriptId);
            // Oculta a barra quando o vídeo terminar
            if (progressBars[scriptId]) {
                progressBars[scriptId].container.hide();
            }
        });
        
        // Detecta cliques nos overlays de play/pause para identificar interação do usuário
        $(document).on('click', '.vsl-start-overlay, .vsl-pause-overlay', function() {
            var $container = $(this).closest('.vsl-player-container');
            var scriptId = $container.attr('id').replace('vsl-player-', '');
            userInitiatedPlay[scriptId] = true;
            resumeProgressBar(scriptId);
        });
        
        // Captura o evento personalizado quando o usuário inicia manualmente a reprodução
        $(document).on('vsl.userInitiatedPlay', function(e, scriptId) {
            userInitiatedPlay[scriptId] = true;
            
            // Se a barra de progresso já foi inicializada, exiba-a
            if (progressBars[scriptId]) {
                progressBars[scriptId].container.show();
                resumeProgressBar(scriptId);
            }
        });
    });

    /**
     * Inicializa a barra de progresso
     */
    function initProgressBar(player, scriptId) {
        var $container = $('#vsl-player-' + scriptId);
        
        // Cria os elementos da barra de progresso se ainda não existirem
        if ($container.find('.vsl-progress-container').length === 0) {
            $container.append('<div class="vsl-progress-container"><div class="vsl-progress-bar"></div></div>');
        }
        
        progressBars[scriptId] = {
            container: $container.find('.vsl-progress-container'),
            bar: $container.find('.vsl-progress-bar'),
            startTime: new Date().getTime(),
            currentWidth: 0
        };
        
        // Aplica a cor personalizada da barra de progresso, se disponível
        if (typeof VSLProgressBar !== 'undefined' && VSLProgressBar.progress_color) {
            progressBars[scriptId].bar.css('background-color', VSLProgressBar.progress_color);
        }
        
        // Exibe a barra de progresso apenas se o play foi iniciado pelo usuário
        if (userInitiatedPlay[scriptId]) {
            progressBars[scriptId].container.show();
        } else {
            progressBars[scriptId].container.hide();
        }
        
        // Obtém a duração do vídeo
        if (player && player.getDuration) {
            videoDurations[scriptId] = player.getDuration();
        } else {
            // Valor padrão se não conseguir obter a duração real
            videoDurations[scriptId] = 300; // 5 minutos
        }
        
        // Inicia o intervalo para atualizar a barra
        startProgressInterval(player, scriptId);
    }

    /**
     * Inicia o intervalo para atualizar a barra de progresso
     */
    function startProgressInterval(player, scriptId) {
        if (progressIntervals[scriptId]) {
            clearInterval(progressIntervals[scriptId]);
        }
        
        progressIntervals[scriptId] = setInterval(function() {
            updateProgressBar(player, scriptId);
        }, 100); // Atualiza a cada 100ms para uma animação suave
    }

    /**
     * Atualiza a barra de progresso com base no tempo atual do vídeo
     */
    function updateProgressBar(player, scriptId) {
        if (!player || !progressBars[scriptId]) return;
        
        var currentTime = player.getCurrentTime();
        var duration = videoDurations[scriptId];
        var realProgress = currentTime / duration;
        
        // Calcula o progresso falso usando uma função de easing não-linear
        var fakeProgress = calculateFakeProgress(realProgress);
        
        // Atualiza a largura da barra de progresso
        progressBars[scriptId].bar.css('width', (fakeProgress * 100) + '%');
        progressBars[scriptId].currentWidth = fakeProgress * 100;
    }

    /**
     * Calcula o progresso falso com base no progresso real
     * Usa uma função não-linear para criar a ilusão de progresso mais rápido no início
     */
    function calculateFakeProgress(realProgress) {
        // Primeiros 10% do tempo -> avança até 30% da barra
        if (realProgress < 0.1) {
            return easeOutQuad(realProgress * 10) * 0.3;
        }
        // De 10% a 30% do tempo -> avança de 30% a 50% da barra
        else if (realProgress < 0.3) {
            var adjustedProgress = (realProgress - 0.1) / 0.2; // Normaliza para 0-1
            return 0.3 + (easeOutQuad(adjustedProgress) * 0.2);
        }
        // De 30% a 50% do tempo -> avança de 50% a 65% da barra
        else if (realProgress < 0.5) {
            var adjustedProgress = (realProgress - 0.3) / 0.2; // Normaliza para 0-1
            return 0.5 + (easeOutQuad(adjustedProgress) * 0.15);
        }
        // De 50% a 70% do tempo -> avança de 65% a 80% da barra
        else if (realProgress < 0.7) {
            var adjustedProgress = (realProgress - 0.5) / 0.2; // Normaliza para 0-1
            return 0.65 + (easeOutQuad(adjustedProgress) * 0.15);
        }
        // Últimos 30% do tempo -> avança de 80% a 100% da barra
        else {
            var adjustedProgress = (realProgress - 0.7) / 0.3; // Normaliza para 0-1
            return 0.8 + (easeInOutQuad(adjustedProgress) * 0.2);
        }
    }

    /**
     * Função de easing: easeOutQuad
     * Desacelera gradualmente
     */
    function easeOutQuad(t) {
        return t * (2 - t);
    }

    /**
     * Função de easing: easeInOutQuad
     * Acelera no início e desacelera no final
     */
    function easeInOutQuad(t) {
        return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
    }

    /**
     * Pausa a animação da barra de progresso
     */
    function pauseProgressBar(scriptId) {
        if (progressBars[scriptId]) {
            progressBars[scriptId].container.addClass('vsl-paused');
            
            if (progressIntervals[scriptId]) {
                clearInterval(progressIntervals[scriptId]);
                progressIntervals[scriptId] = null;
            }
        }
    }

    /**
     * Retoma a animação da barra de progresso
     */
    function resumeProgressBar(scriptId) {
        if (progressBars[scriptId]) {
            progressBars[scriptId].container.removeClass('vsl-paused');
            
            var player = window['vslYouTubePlayer_' + scriptId];
            if (player) {
                startProgressInterval(player, scriptId);
            }
        }
    }

    /**
     * Reinicia a barra de progresso
     */
    function resetProgressBar(scriptId) {
        if (progressBars[scriptId]) {
            progressBars[scriptId].bar.css('width', '0%');
            progressBars[scriptId].currentWidth = 0;
            
            if (progressIntervals[scriptId]) {
                clearInterval(progressIntervals[scriptId]);
                progressIntervals[scriptId] = null;
            }
        }
    }

})(jQuery);
