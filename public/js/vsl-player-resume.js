/**
 * VSL Player - Recurso de Continuar Assistindo
 * 
 * Armazena o tempo de reprodução do vídeo e permite continuar assistindo de onde parou
 */
(function($) {
    'use strict';
    
    // Variáveis globais
    var resumeOverlays = {};
    var saveInterval = 5000; // Salvar a cada 5 segundos
    var playerIntervals = {};
    var isInitializingPlayer = {};
    
    /**
     * Inicializar o recurso de continuar assistindo para todos os players
     */
    function initializeResumeFeature() {
        // Precisamos executar isso antes que o player do YouTube seja inicializado
        $('.vsl-player-container').each(function() {
            var $container = $(this);
            var containerId = $container.attr('id');
            var vslId = $container.data('vsl-id');
            var enableResumePlayer = $container.data('enable-resume-player') === true || $container.data('enable-resume-player') === 'true';
            
            if (containerId && vslId) {
                if (enableResumePlayer) {
                    // Verificar se existe um tempo salvo
                    var savedTime = getSavedTime(vslId);
                    
                    if (savedTime && savedTime > 0) {
                        // Marcar este player como em processo de inicialização
                        isInitializingPlayer[vslId] = true;
                        
                        // Impedir a inicialização automática do YouTube Player
                        $container.attr('data-resume-pending', 'true');
                        
                        // Criar o overlay de resumo para este player
                        createResumeOverlay($container, containerId, vslId, savedTime);
                    }
                } else {
                    // Resume player desativado, limpar qualquer tempo salvo
                    clearSavedTime(vslId);
                }
            }
        });
        
        // Configurar o evento para iniciar o monitoramento quando o vídeo começar a ser reproduzido
        $(document).on('YT.PlayerState.PLAYING', function(event, player, scriptId) {
            var playerId = 'vsl-player-' + scriptId;
            var $container = $('#' + playerId);
            var vslId = $container.data('vsl-id');
            var enableResumePlayer = $container.data('enable-resume-player') === true || $container.data('enable-resume-player') === 'true';
            
            if (player && vslId && enableResumePlayer) {
                // Iniciar o monitoramento de tempo para este vídeo
                startTimeTracking(player, vslId);
            }
        });
        
        // Salvar o tempo quando o vídeo for pausado
        $(document).on('YT.PlayerState.PAUSED', function(event, player, scriptId) {
            var playerId = 'vsl-player-' + scriptId;
            var $container = $('#' + playerId);
            var vslId = $container.data('vsl-id');
            var enableResumePlayer = $container.data('enable-resume-player') === true || $container.data('enable-resume-player') === 'true';
            
            if (player && vslId && enableResumePlayer) {
                // Salvar o tempo atual
                saveCurrentTime(player, vslId);
                
                // Limpar o intervalo de salvamento
                if (playerIntervals[vslId]) {
                    clearInterval(playerIntervals[vslId]);
                    playerIntervals[vslId] = null;
                }
            }
        });
        
        // Limpar o tempo quando o vídeo terminar
        $(document).on('YT.PlayerState.ENDED', function(event, player, scriptId) {
            var playerId = 'vsl-player-' + scriptId;
            var $container = $('#' + playerId);
            var vslId = $container.data('vsl-id');
            var enableResumePlayer = $container.data('enable-resume-player') === true || $container.data('enable-resume-player') === 'true';
            
            if (vslId && enableResumePlayer) {
                // Limpar o tempo salvo quando o vídeo terminar
                clearSavedTime(vslId);
                
                // Limpar o intervalo de salvamento
                if (playerIntervals[vslId]) {
                    clearInterval(playerIntervals[vslId]);
                    playerIntervals[vslId] = null;
                }
            }
        });
    }
    
    /**
     * Criar o overlay de resumo para um player específico
     */
    function createResumeOverlay($container, containerId, vslId, savedTime) {
        // Obter as cores personalizadas dos atributos de dados
        var resumeOverlayColor = $container.data('resume-overlay-color') || '#000000';
        var resumeButtonColor = $container.data('resume-button-color') || '#617be5';
        var resumeButtonHoverColor = $container.data('resume-button-hover-color') || '#3854c3';
        
        // Criar o elemento do overlay
        var $resumeOverlay = $('<div class="vsl-resume-overlay">' +
            '<div class="vsl-resume-message">' +
                '<h3>Você já começou a assistir esse vídeo</h3>' +
                '<div class="vsl-resume-actions">' +
                    '<button class="vsl-resume-continue">' +
                        '<i class="vsl-resume-icon vsl-resume-continue-icon"></i>' +
                        '<span>Continuar assistindo</span>' +
                        '<small>(' + formatTime(savedTime) + ')</small>' +
                    '</button>' +
                    '<button class="vsl-resume-restart">' +
                        '<i class="vsl-resume-icon vsl-resume-restart-icon"></i>' +
                        '<span>Assistir do início</span>' +
                    '</button>' +
                '</div>' +
            '</div>' +
        '</div>');
        
        // Adicionar estilos personalizados inline
        $resumeOverlay.css('background-color', resumeOverlayColor);
        $resumeOverlay.find('.vsl-resume-continue').css('background-color', resumeButtonColor);
        
        // Adicionar ao container do player
        $container.append($resumeOverlay);
        
        // Armazenar o overlay para referência futura
        resumeOverlays[containerId] = $resumeOverlay;
        
        // Adicionar estilos personalizados para hover com JavaScript
        $resumeOverlay.find('.vsl-resume-continue').hover(
            function() {
                $(this).css('background-color', resumeButtonHoverColor);
            },
            function() {
                $(this).css('background-color', resumeButtonColor);
            }
        );
        
        // Configurar evento para o botão de continuar
        $resumeOverlay.find('.vsl-resume-continue').on('click', function() {
            // Remover o atributo que impede a inicialização automática
            $container.removeAttr('data-resume-pending');
            
            // Acionar o evento userInitiatedPlay para ativar a barra de progresso falsa
            var scriptId = vslId;
            $(document).trigger('vsl.userInitiatedPlay', [scriptId]);
            
            // Inicializar o player do YouTube se ainda não estiver inicializado
            initializeVideoPlayer($container, containerId, vslId, savedTime);
            
            // Ocultar o overlay
            $resumeOverlay.hide();
        });
        
        // Configurar evento para o botão de reiniciar
        $resumeOverlay.find('.vsl-resume-restart').on('click', function() {
            // Limpar o tempo salvo
            clearSavedTime(vslId);
            
            // Remover o atributo que impede a inicialização automática
            $container.removeAttr('data-resume-pending');
            
            // Acionar o evento userInitiatedPlay para ativar a barra de progresso falsa
            var scriptId = vslId;
            $(document).trigger('vsl.userInitiatedPlay', [scriptId]);
            
            // Inicializar o player do YouTube se ainda não estiver inicializado
            initializeVideoPlayer($container, containerId, vslId, 0);
            
            // Ocultar o overlay
            $resumeOverlay.hide();
        });
    }
    
    /**
     * Inicializar o player de vídeo após a escolha do usuário
     */
    function initializeVideoPlayer($container, containerId, vslId, startTime) {
        // Verificar se o player já existe
        var player = window['vslYouTubePlayer_' + vslId];
        
        if (player) {
            // O player já existe, apenas definir o tempo de início e reproduzir
            player.seekTo(startTime);
            player.playVideo();
            player.unMute();
            
            // Disparar evento YT.PlayerState.PLAYING para iniciar a barra de progresso
            var scriptId = vslId;
            $(document).trigger('YT.PlayerState.PLAYING', [player, scriptId]);
            
            // Ocultar o overlay de início e mostrar o overlay de reprodução
            $container.find('.vsl-start-overlay').hide();
            $container.find('.vsl-playing-overlay').show();
        } else {
            // Verificar se há um evento personalizado para inicializar o player
            // que será capturado pelo vsl-player-youtube.js
            $(document).trigger('VSL.InitializePlayer', [$container, vslId, startTime]);
            
            // Adicionar um temporizador de verificação para ver se o player já foi inicializado
            checkPlayerInitialization($container, vslId, startTime);
        }
    }
    
    /**
     * Verificar periodicamente se o player foi inicializado
     */
    function checkPlayerInitialization($container, vslId, startTime) {
        var checkInterval = setInterval(function() {
            var player = window['vslYouTubePlayer_' + vslId];
            
            if (player) {
                clearInterval(checkInterval);
                
                // Dar tempo para o player terminar de inicializar
                setTimeout(function() {
                    player.seekTo(startTime);
                    player.playVideo();
                    player.unMute();
                    
                    // Disparar evento YT.PlayerState.PLAYING para iniciar a barra de progresso
                    var scriptId = vslId;
                    $(document).trigger('YT.PlayerState.PLAYING', [player, scriptId]);
                    
                    // Ocultar o overlay de início e mostrar o overlay de reprodução
                    $container.find('.vsl-start-overlay').hide();
                    $container.find('.vsl-playing-overlay').show();
                }, 500);
            }
        }, 100);
        
        // Limpar o intervalo após 10 segundos para evitar loop infinito
        setTimeout(function() {
            clearInterval(checkInterval);
        }, 10000);
    }
    
    /**
     * Iniciar o monitoramento de tempo para um vídeo
     */
    function startTimeTracking(player, vslId) {
        // Limpar qualquer intervalo existente
        if (playerIntervals[vslId]) {
            clearInterval(playerIntervals[vslId]);
        }
        
        // Configurar novo intervalo de salvamento
        playerIntervals[vslId] = setInterval(function() {
            saveCurrentTime(player, vslId);
        }, saveInterval);
    }
    
    /**
     * Salvar o tempo atual de reprodução
     */
    function saveCurrentTime(player, vslId) {
        if (player) {
            var currentTime = player.getCurrentTime();
            var duration = player.getDuration();
            
            // Apenas salvar se o tempo for maior que 0 e o vídeo não estiver no final
            if (currentTime > 0 && duration > 0 && currentTime < duration - 10) {
                localStorage.setItem('vsl_resume_time_' + vslId, currentTime);
            }
        }
    }
    
    /**
     * Obter o tempo salvo para um vídeo
     */
    function getSavedTime(vslId) {
        var savedTime = localStorage.getItem('vsl_resume_time_' + vslId);
        return savedTime ? parseFloat(savedTime) : 0;
    }
    
    /**
     * Limpar o tempo salvo para um vídeo
     */
    function clearSavedTime(vslId) {
        localStorage.removeItem('vsl_resume_time_' + vslId);
    }
    
    /**
     * Formatar o tempo em formato legível (MM:SS)
     */
    function formatTime(timeInSeconds) {
        var minutes = Math.floor(timeInSeconds / 60);
        var seconds = Math.floor(timeInSeconds % 60);
        
        return (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }
    
    // Inicializar o recurso quando o documento estiver pronto
    // É importante que isso seja executado antes da inicialização do YouTube Player
    $(document).ready(function() {
        // Inicializar imediatamente para marcar os players que têm tempo salvo
        initializeResumeFeature();
    });
    
})(jQuery);
