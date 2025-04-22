/**
 * VSL Player Smart Hooks
 * 
 * Implements the Smart Hook feature to display overlay images at specific times in the video
 */
(function($) {
    'use strict';

    // Store hooks by player ID
    var playerHooks = {};
    var activeHooks = {};
    var checkInterval = 1000; // Check every 1000ms

    /**
     * Initialize Smart Hooks for a player
     * @param {Object} player - YouTube player instance
     * @param {String} playerId - ID of the player container
     */
    function initSmartHooks(player, playerId) {
        console.log('[VSL Smart Hooks] Inicializando Smart Hooks para o player', playerId);
        
        const $container = $('#' + playerId);
        
        // Verificar se o recurso está ativado
        const isEnabled = $container.data('smart-hooks-enabled') === true;
        console.log('[VSL Smart Hooks] Smart Hooks está ativado?', isEnabled);
        
        if (!isEnabled) {
            console.log('[VSL Smart Hooks] Smart Hooks está desativado para este player. Encerrando inicialização.');
            return;
        }

        try {
            // Extrair os hooks dos atributos data
            let hooks = $container.data('smart-hooks');
            console.log('[VSL Smart Hooks] Dados de hooks obtidos:', hooks);
            
            // Se os hooks ainda estiverem em formato string (JSON), parse eles
            if (typeof hooks === 'string') {
                try {
                    hooks = JSON.parse(hooks);
                    console.log('[VSL Smart Hooks] Hooks parseados de string JSON:', hooks);
                } catch (e) {
                    console.error('[VSL Smart Hooks] Erro ao fazer parse dos hooks:', e);
                    return;
                }
            }
            
            // Verificar se há hooks válidos
            if (!hooks || !hooks.length) {
                console.log('[VSL Smart Hooks] Nenhum hook configurado para este player.');
                return;
            }

            // Armazenar hooks para este player
            playerHooks[playerId] = hooks;
            activeHooks[playerId] = {};
            
            // Criar container para as imagens dos hooks
            if ($('#' + playerId + ' .vsl-smart-hooks-container').length === 0) {
                console.log('[VSL Smart Hooks] Criando container para os hooks.');
                $container.append('<div class="vsl-smart-hooks-container"></div>');
            } else {
                console.log('[VSL Smart Hooks] Container para hooks já existe.');
            }
            
            // Debug: informações detalhadas de cada hook
            console.log(`[VSL Smart Hooks] ${hooks.length} hooks configurados:`);
            hooks.forEach((hook, index) => {
                console.log(`[VSL Smart Hooks] Hook #${index}:`);
                console.log(`  - Nome: ${hook.name}`);
                console.log(`  - Imagem: ${hook.image}`);
                console.log(`  - Tempo início: ${hook.start}s`);
                console.log(`  - Tempo fim: ${hook.end}s`);
                
                // Pré-carregando a imagem para evitar problemas de carregamento tardio
                if (hook.image) {
                    const preloadImg = new Image();
                    preloadImg.src = hook.image;
                    preloadImg.onload = function() {
                        console.log(`[VSL Smart Hooks] Imagem pré-carregada com sucesso: ${hook.image}`);
                    };
                    preloadImg.onerror = function() {
                        console.error(`[VSL Smart Hooks] ERRO ao pré-carregar imagem: ${hook.image}`);
                    };
                }
            });
            
            // Iniciar verificação do tempo do vídeo
            const intervalId = setInterval(function() {
                checkHookTimes(player, playerId);
            }, checkInterval);
            
            // Armazenar o ID do intervalo para poder limpar depois se necessário
            $container.data('smart-hooks-interval', intervalId);
            
            console.log('[VSL Smart Hooks] Monitoramento de tempo iniciado com intervalo de', checkInterval, 'ms');
        } catch (error) {
            console.error('[VSL Smart Hooks] Erro ao inicializar hooks:', error);
        }
    }

    /**
     * Check the current video time and display/hide hooks accordingly
     * @param {Object} player - YouTube player instance
     * @param {String} playerId - ID of the player container
     */
    function checkHookTimes(player, playerId) {
        if (!player || !playerHooks[playerId]) {
            return;
        }

        try {
            // Obter o tempo atual do vídeo
            const currentTime = player.getCurrentTime();
            
            // Verificar o estado do player (só processar hooks se estiver reproduzindo)
            const playerState = player.getPlayerState();
            if (playerState !== 1) { // 1 = playing
                return; // Não processar hooks se o vídeo não estiver reproduzindo
            }
            
            const hooks = playerHooks[playerId];
            const $container = $('#' + playerId + ' .vsl-smart-hooks-container');

            // Verificar cada hook para ver se deve ser exibido ou ocultado
            hooks.forEach(function(hook, index) {
                const hookId = `hook-${playerId}-${index}`;
                
                // Este hook deve estar ativo?
                const start = parseFloat(hook.start);
                const end = parseFloat(hook.end);
                const shouldBeActive = currentTime >= start && currentTime <= end;
                const isActive = activeHooks[playerId][hookId];
                
                if (shouldBeActive && !isActive) {
                    // Ativar hook - mostrar a imagem
                    showHook(hookId, hook, $container);
                    activeHooks[playerId][hookId] = true;
                    console.log(`[VSL Smart Hooks] Mostrando hook "${hook.name}" em ${currentTime.toFixed(2)}s (intervalo: ${start}s - ${end}s)`);
                } else if (!shouldBeActive && isActive) {
                    // Desativar hook - ocultar a imagem
                    hideHook(hookId, $container);
                    activeHooks[playerId][hookId] = false;
                    console.log(`[VSL Smart Hooks] Ocultando hook "${hook.name}" em ${currentTime.toFixed(2)}s (fora do intervalo: ${start}s - ${end}s)`);
                }
            });
        } catch (error) {
            console.error('[VSL Smart Hooks] Erro ao verificar tempos dos hooks:', error);
        }
    }

    /**
     * Show a hook image with fade-in effect
     * @param {String} hookId - Unique ID for this hook instance
     * @param {Object} hook - Hook configuration object
     * @param {jQuery} $container - Container element
     */
    function showHook(hookId, hook, $container) {
        // Verificar se o elemento já existe
        if ($('#' + hookId).length === 0) {
            console.log(`[VSL Smart Hooks] Criando elemento para o hook "${hook.name}"`);
            console.log(`[VSL Smart Hooks] URL da imagem: ${hook.image}`);
            
            // Criar elemento para o hook
            const $hook = $('<div>', {
                id: hookId,
                class: 'vsl-smart-hook',
                css: {
                    'opacity': 0
                }
            });
            
            // Adicionar a imagem como elemento filho para melhor controle
            const $img = $('<img>', {
                src: hook.image,
                alt: hook.name || 'Smart Hook',
                class: 'vsl-smart-hook-image'
            });
            
            // Log de depuração quando a imagem carregar ou falhar
            $img.on('load', function() {
                console.log(`[VSL Smart Hooks] Imagem carregada com sucesso para hook "${hook.name}"`);
            });
            
            $img.on('error', function() {
                console.error(`[VSL Smart Hooks] ERRO ao carregar imagem para hook "${hook.name}". URL: ${hook.image}`);
            });
            
            $hook.append($img);
            $container.append($hook);
        }
        
        // Fazer fade in do hook
        $('#' + hookId).animate({
            opacity: 1
        }, 400);
    }

    /**
     * Hide a hook image with fade-out effect
     * @param {String} hookId - Unique ID for this hook instance
     * @param {jQuery} $container - Container element
     */
    function hideHook(hookId, $container) {
        const $hook = $('#' + hookId);
        
        if ($hook.length) {
            $hook.animate({
                opacity: 0
            }, 400, function() {
                // Remover depois do fade out
                $hook.remove();
            });
        }
    }

    // Add to global VSLPlayer namespace
    if (typeof window.VSLPlayer === 'undefined') {
        window.VSLPlayer = {};
    }
    
    window.VSLPlayer.SmartHooks = {
        init: initSmartHooks
    };

})(jQuery);
