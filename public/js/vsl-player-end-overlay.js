/**
 * VSL Player - Overlay de Fim de Vídeo
 * 
 * Exibe um overlay quando o vídeo chega ao fim e permite reiniciar a reprodução
 */
(function($) {
    'use strict';
    
    // Flag global para rastrear se já adicionamos os overlays
    var overlaysAdded = false;
    
    // Armazenar referências a todos os overlays de fim criados
    var allEndOverlays = {};
    
    /**
     * Criar e adicionar o overlay de fim de vídeo a todos os players
     */
    function setupEndOverlay() {
        
        // Se já adicionamos os overlays, não fazer novamente
        if (overlaysAdded) {
            return;
        }
        
        // Adicionar o overlay a cada container de player
        $('.vsl-player-container').each(function() {
            var $container = $(this);
            var containerId = $container.attr('id');
            
            if (containerId) {

                
                // Obter a cor personalizada para o botão
                var buttonColor = $container.data('player-color') || '#617be5';
                var buttonHoverColor = adjustColor(buttonColor, -20); // Escurece a cor em 20%
                
                // Criar o elemento HTML do overlay
                var $endOverlay = $(
                    '<div class="vsl-end-overlay">' +
                        '<div class="vsl-end-message">' +
                            '<h3>Este vídeo chegou ao fim</h3>' +
                        '</div>' +
                        '<button class="vsl-watch-again-button">' +
                            '<span class="vsl-replay-icon"></span>' +
                            'Assistir novamente' +
                        '</button>' +
                    '</div>'
                );
                
                // Aplicar cor personalizada ao botão
                $endOverlay.find('.vsl-watch-again-button').css('background-color', buttonColor);
                
                // Adicionar hover effect com JavaScript
                $endOverlay.find('.vsl-watch-again-button').hover(
                    function() {
                        $(this).css('background-color', buttonHoverColor);
                    },
                    function() {
                        $(this).css('background-color', buttonColor);
                    }
                );
                
                // Adicionar ao container do player
                $container.append($endOverlay);
                
                // Armazenar a referência ao overlay para uso futuro
                allEndOverlays[containerId] = $endOverlay;
                
                // Configurar o evento de clique no botão "Assistir novamente"
                $endOverlay.find('.vsl-watch-again-button').on('click', function() {
                    
                    // Identificar o player associado
                    var vslId = $container.data('vsl-id');
                    var player = window['vslYouTubePlayer_' + vslId];
                    
                    if (player) {

                        // Primeiro remover o overlay completamente e DEPOIS reiniciar o vídeo
                        hideEndOverlay(containerId);
                        
                        // Pequeno delay para garantir que o overlay foi removido antes de iniciar o vídeo
                        setTimeout(function() {
                            // Reiniciar o vídeo
                            player.seekTo(0);
                            player.playVideo();
                            
                            // Se houverem analytics disponíveis, registrar o evento
                            if (window.vslAnalytics && typeof window.vslAnalytics.sendEvent === 'function') {
                                window.vslAnalytics.sendEvent('replay', {
                                    action: 'watch_again'
                                });
                            }
                        }, 50);
                    }
                });
            }
        });
        
        // Configurar os listeners para o evento de fim de vídeo
        setupEventListeners();
        
        // Marcar que os overlays foram adicionados
        overlaysAdded = true;
    }
    
    /**
     * Configurar listeners para o estado do player
     */
    function setupEventListeners() {
        
        // Remover quaisquer listeners antigos para evitar duplicação
        $(document).off('YT.PlayerState.ENDED.endOverlay');
        
        // Configurar listeners para o estado do player
        $(document).on('YT.PlayerState.ENDED.endOverlay', function(event, player, scriptId) {
            if (!player) {
                return;
            }
            
            // Identificar o container
            var playerId = 'vsl-player-' + scriptId;
            var $container = $('#' + playerId);
            
            if ($container.length) {

                
                // Verificar se o overlay existe
                var $overlay = $container.find('.vsl-end-overlay');
                
                if ($overlay.length) {

                    
                    // Remover qualquer classe de ocultação que possa existir
                    $overlay.removeClass('vsl-hidden vsl-force-hidden');
                    
                    // Mostrar o overlay de fim - forçando display flex e depois animando
                    $overlay.css({
                        'display': 'flex',
                        'opacity': '0',
                        'visibility': 'visible',
                        'z-index': '100',
                        'pointer-events': 'auto'
                    }).animate({
                        'opacity': '1'
                    }, 300);
                    
                    // Esconder outros overlays se estiverem visíveis
                    $container.find('.vsl-start-overlay, .vsl-playing-overlay').hide();
                } else {
                    // Tentar adicionar o overlay novamente
                    setupEndOverlay();
                }
            }
        });
    }
    
    /**
     * Ajustar a cor (clarear ou escurecer)
     * @param {string} color - Cor em formato hex
     * @param {number} amount - Valor para ajustar (-100 a 100)
     * @return {string} Nova cor em formato hex
     */
    function adjustColor(color, amount) {
        return '#' + color.replace(/^#/, '').replace(/../g, function(hex) {
            var c = Math.min(Math.max(0, parseInt(hex, 16) + amount), 255).toString(16);
            return ('0' + c).substr(-2);
        });
    }
    
    /**
     * Função para ocultar o overlay de fim
     * @param {string} containerId - ID do container do player
     */
    function hideEndOverlay(containerId) {
        try {
            // Verificar e remover qualquer overlay existente
            $('#' + containerId).find('.vsl-end-overlay').each(function() {
                var $overlay = $(this);

                
                // Esconder imediatamente
                $overlay.hide();
                
                // Aplicar todos os estilos possíveis para esconder
                $overlay.css({
                    'display': 'none',
                    'opacity': '0',
                    'visibility': 'hidden',
                    'z-index': '-9999',
                    'pointer-events': 'none',
                    'position': 'absolute',
                    'left': '-9999px',
                    'top': '-9999px',
                    'width': '0',
                    'height': '0'
                });
                
                // Remover completamente do DOM
                $overlay.remove();
            });
            
            // Limpar a referência armazenada
            if (allEndOverlays[containerId]) {
                allEndOverlays[containerId] = null;
                delete allEndOverlays[containerId];
            }
            
            // Verificar novamente após um pequeno atraso
            setTimeout(function() {
                if ($('#' + containerId).find('.vsl-end-overlay').length > 0) {
                    $('#' + containerId).find('.vsl-end-overlay').remove();
                }
            }, 100);
        } catch (e) {
            // Ignora erro ao tentar remover overlay
        }
    }
    
    // Inicializar quando o script é carregado
    setupEndOverlay();
    
    // Também inicializar quando o documento estiver pronto (dupla segurança)
    $(document).ready(function() {
        // Verificar se já adicionamos os overlays
        if (!overlaysAdded) {
            setupEndOverlay();
        }
    });
    
    // Expor funções importantes globalmente
    window.vslEndOverlay = {
        hide: hideEndOverlay,
        setup: setupEndOverlay
    };
})(jQuery);
