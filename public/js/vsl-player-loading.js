/**
 * VSL Player - Script para controlar a tela de carregamento
 */
(function($) {
    'use strict';
    
    // Variável para rastrear o estado do carregamento
    var playersReady = {};
    
    // Função para executar assim que o DOM estiver pronto
    function initLoadingScreen() {
        console.log('VSL Loading: Inicializando telas de carregamento');
        
        // Aplicar tela de carregamento a cada container do player
        $('.vsl-player-container').each(function() {
            var $container = $(this);
            var postId = $container.data('vsl-id');
            
            if (postId) {
                // Criar e adicionar a overlay de carregamento se ainda não existir
                if ($container.find('.vsl-loading-overlay').length === 0) {
                    var $loadingOverlay = $('<div class="vsl-loading-overlay">' +
                        '<div class="vsl-loader"></div>' +
                        '</div>');
                    
                    // Aplicar a cor personalizada ao spinner
                    var playerColor = $container.data('player-color') || '#617be5';
                    $loadingOverlay.find('.vsl-loader').css('border-bottom-color', playerColor);
                    
                    // Adicionar ao container
                    $container.append($loadingOverlay);
                    console.log('VSL Loading: Tela de carregamento adicionada para player ID: ' + postId);
                }
            }
        });
    }
    
    // Registrar evento assim que o DOM estiver pronto
    $(document).ready(function() {
        // Inicializar a tela de carregamento
        initLoadingScreen();
        
        // Ouvir o evento do YouTube API carregada
        window.onYouTubeIframeAPIReady = function() {
            console.log('VSL Loading: YouTube API carregada');
        };
        
        // Quando o player do YouTube estiver pronto
        $(document).on('YT.PlayerReady', function(e, player, scriptId) {
            console.log('VSL Loading: Player pronto! ID: ' + scriptId);
            // Marcar este player como pronto
            playersReady[scriptId] = true;
            
            // Esconder a tela de carregamento com um pequeno atraso para garantir que o vídeo comece
            setTimeout(function() {
                hideLoadingScreen(scriptId);
            }, 700);
        });
        
        // Também ouvir os eventos de estado do player
        $(document).on('YT.PlayerState.PLAYING', function(e, player, scriptId) {
            console.log('VSL Loading: Player está reproduzindo: ' + scriptId);
            // Se o player começou a reproduzir, podemos esconder a tela de carregamento
            if (!playersReady[scriptId]) {
                playersReady[scriptId] = true;
                hideLoadingScreen(scriptId);
            }
        });
        
        // Se os players não carregarem em 8 segundos, escondemos as telas de carregamento
        setTimeout(function() {
            console.log('VSL Loading: Timeout de carregamento atingido. Escondendo todas as telas de carregamento.');
            hideAllLoadingScreens();
        }, 8000);
    });
    
    // Para garantir que o script funcione mesmo se for carregado tardiamente
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        console.log('VSL Loading: Documento já carregado, inicializando imediatamente');
        initLoadingScreen();
    }
    
    /**
     * Esconder a tela de carregamento para um player específico
     */
    function hideLoadingScreen(scriptId) {
        // Verificar se este player já está marcado como pronto
        if (!playersReady[scriptId]) {
            console.log('VSL Loading: Tentativa de esconder a tela de carregamento para um player que não está pronto: ' + scriptId);
            return;
        }
        
        console.log('VSL Loading: Escondendo tela de carregamento para player: ' + scriptId);
        
        // Adiciona a classe player-ready ao container do player
        var $playerContainer = $('#vsl-player-' + scriptId);
        if ($playerContainer.length) {
            $playerContainer.addClass('vsl-player-ready');
            console.log('VSL Loading: Container do player marcado como pronto: vsl-player-' + scriptId);
        } else {
            console.log('VSL Loading: Container do player não encontrado: vsl-player-' + scriptId);
        }
    }
    
    /**
     * Esconder todas as telas de carregamento
     */
    function hideAllLoadingScreens() {
        console.log('VSL Loading: Escondendo todas as telas de carregamento...');
        
        // Adicionar a classe vsl-player-ready a todos os containers
        $('.vsl-player-container').addClass('vsl-player-ready');
    }
    
    // Exportar funções para uso global (útil para debugging)
    window.vslPlayerLoading = {
        hideLoadingScreen: hideLoadingScreen,
        hideAllLoadingScreens: hideAllLoadingScreens,
        playersReady: playersReady
    };
    
})(jQuery);
