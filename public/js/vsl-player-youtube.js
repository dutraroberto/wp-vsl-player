/**
 * VSL Player Otimizado - YouTube Player Functionality
 */
(function($) {
    'use strict';

    // Store player instances
    var players = {};
    
    // Make players accessible globally for other scripts
    window.vslPlayers = players;
    
    // Initialize when the document is ready
    $(document).ready(function() {
        if (typeof vslPlayerData === 'undefined') {
            return;
        }

        // Solução para garantir que o botão SVG esteja centralizado desde o início
        // Pre-carregar a imagem SVG para evitar o "flash" de posicionamento
        var svgPath = vslPlayerData.plugin_url + 'assets/player-thumb-youtube.svg';
        var img = new Image();
        img.onload = function() {
            // Imagem carregada, agora é seguro mostrar
            $('.vsl-play-button-svg').css({
                'opacity': '1',
                'visibility': 'visible'
            });
        };
        img.src = svgPath;

        // Aplicar posicionamento forçado ao botão SVG
        $('.vsl-play-button-svg').css({
            'position': 'absolute',
            'top': '50%',
            'left': '50%',
            'transform': 'translate(-50%, -50%)',
            '-webkit-transform': 'translate(-50%, -50%)',
            '-moz-transform': 'translate(-50%, -50%)',
            '-ms-transform': 'translate(-50%, -50%)',
            'margin': '0',
            'padding': '0',
            'opacity': '0', // Inicialmente oculto até ser carregado
            'visibility': 'hidden'
        });

        // Create player containers and overlays
        $('.vsl-player-container').each(function() {
            var $container = $(this);
            var containerId = $container.attr('id');
            var postId = $container.data('vsl-id');
            
            if (!containerId || !postId) {
                return;
            }
            
            // Find video ID for this player
            var videoId = null;
            var playerData = null;
            
            for (var i = 0; i < vslPlayerData.players.length; i++) {
                if (vslPlayerData.players[i].id == postId) {
                    videoId = vslPlayerData.players[i].video_id;
                    playerData = vslPlayerData.players[i];
                    break;
                }
            }
            
            if (!videoId) {
                return;
            }
            
            // Create player overlays
            createPlayerOverlays($container, containerId);
            
            // Se a barra de progresso falsa estiver ativada, carregue os scripts e estilos necessários
            if (playerData && playerData.fake_progress === true) {
                loadProgressBarAssets();
            }
        });
        
        // Load YouTube API if not already loaded
        if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
            var tag = document.createElement('script');
            tag.src = "https://www.youtube.com/iframe_api";
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            
            window.onYouTubeIframeAPIReady = initializeYouTubePlayers;
        } else {
            initializeYouTubePlayers();
        }
    });
    
    /**
     * Carregar os assets da barra de progresso falsa
     */
    function loadProgressBarAssets() {
        // Este código assume que os scripts e estilos já foram registrados na parte PHP do plugin
        if (typeof wp !== 'undefined' && wp.ajax) {
            // Estamos no ambiente WordPress, podemos usar wp.ajax para carregar os scripts
            wp.ajax.post('vsl_load_progress_bar_assets', {
                nonce: vslPlayerData.nonce
            });
        } else {
            // Alternativa: apenas garantimos que o script e o CSS sejam carregados
            if (!$('script[src*="vsl-player-progress-bar.js"]').length) {
                $.getScript(vslPlayerData.plugin_url + 'public/js/vsl-player-progress-bar.js');
            }
            if (!$('link[href*="vsl-player-progress-bar.css"]').length) {
                $('<link>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: vslPlayerData.plugin_url + 'public/css/vsl-player-progress-bar.css'
                }).appendTo('head');
            }
        }
        
        // Criar objeto global com as configurações da barra de progresso
        window.VSLProgressBar = {
            progress_color: '#ff0000' // Cor padrão
        };
        
        // Atualizar com a cor de progresso personalizada se disponível
        if (vslPlayerData && vslPlayerData.players) {
            // Procurar por um player com progresso falso habilitado
            for (var i = 0; i < vslPlayerData.players.length; i++) {
                if (vslPlayerData.players[i].fake_progress) {
                    window.VSLProgressBar.progress_color = vslPlayerData.players[i].progress_color || '#ff0000';
                    break;
                }
            }
        }
    }
    
    /**
     * Create player overlays for start, playing and pause states
     */
    function createPlayerOverlays($container, containerId) {
        // Start overlay should already contain our custom SVG from PHP
        // Only create elements if they don't exist
        if ($container.find('.vsl-playing-overlay').length === 0) {
            $container.append('<div class="vsl-playing-overlay"></div>');
        }
        
        if ($container.find('.vsl-pause-overlay').length === 0) {
            var $pauseOverlay = $('<div class="vsl-pause-overlay"></div>');
            
            // Get player color 
            var playerColor = $container.data('player-color') || '#617be5';
            
            // For pause overlay, we'll use the CSS button for simplicity
            $pauseOverlay.append('<div class="vsl-play-button"></div>');
            $container.append($pauseOverlay);
        }
        
        // Set up overlay click events
        setupOverlayEvents($container, containerId);
        
        // Configurar o estilo personalizado do overlay de pausa
        setupPauseOverlayStyle($container, $container.find('.vsl-pause-overlay'));
    }
    
    /**
     * Configurar o estilo personalizado do overlay de pausa com base nos atributos de dados
     */
    function setupPauseOverlayStyle($container, $pauseOverlay) {
        var pauseStyle = $container.data('pause-style') || 'default';
        var pauseColor = $container.data('pause-color') || '#000000';
        var pauseImageId = $container.data('pause-image');
        var hidePauseButton = $container.data('hide-pause-button') === true || $container.data('hide-pause-button') === 'true';
        
        // Remover estilos anteriores
        $pauseOverlay.css({
            'background': '',
            'background-image': '',
            'background-color': '',
            'background-size': ''
        });
        
        // Aplicar o estilo com base na seleção
        switch (pauseStyle) {
            case 'solid':
                // Cor sólida
                $pauseOverlay.css({
                    'background': pauseColor,
                    'background-color': pauseColor
                });
                break;
                
            case 'continue':
                // Estilo "Continuar Assistindo" - usando o SVG como background
                var svgUrl = vslPlayerData.plugin_url + 'assets/template-continuar-assistindo.svg';
                
                // Pré-carregar o SVG
                var img = new Image();
                img.onload = function() {
                    $pauseOverlay.css({
                        'background-image': 'url(' + svgUrl + ')',
                        'background-size': 'cover',
                        'background-position': 'center'
                    });
                };
                img.src = svgUrl;
                
                // Verificar se deve ocultar o botão de play
                if (hidePauseButton) {
                    $pauseOverlay.find('.vsl-play-button').hide();
                } else {
                    $pauseOverlay.find('.vsl-play-button').show();
                }
                break;
                
            case 'image':
                // Imagem de fundo
                if (pauseImageId) {
                    // Pre-carregue a imagem imediatamente para evitar atrasos
                    if (!$pauseOverlay.data('image-preloaded')) {
                        // Obter a URL da imagem via AJAX se necessário
                        getImageUrl(pauseImageId, function(imageUrl) {
                            if (imageUrl) {
                                // Pré-carregar a imagem
                                var img = new Image();
                                img.onload = function() {
                                    // Quando a imagem terminar de carregar, aplicá-la ao overlay
                                    $pauseOverlay.css({
                                        'background-image': 'url(' + imageUrl + ')',
                                        'background-size': 'cover',
                                        'background-position': 'center'
                                    });
                                    // Marcar como pré-carregada
                                    $pauseOverlay.data('image-preloaded', true);
                                };
                                img.src = imageUrl;
                            }
                        });
                    }
                    
                    // Verificar se deve ocultar o botão de play
                    if (hidePauseButton) {
                        $pauseOverlay.find('.vsl-play-button').hide();
                    } else {
                        $pauseOverlay.find('.vsl-play-button').show();
                    }
                }
                break;
                
            default:
                // Estilo padrão (gradiente)
                $pauseOverlay.css({
                    'background': 'linear-gradient(to bottom, rgba(0, 0, 0, 0.4), #000000 60%)'
                });
                break;
        }
        
        // Sempre mostrar o botão para estilos que não são imagens ou continue
        if (pauseStyle !== 'image' && pauseStyle !== 'continue') {
            $pauseOverlay.find('.vsl-play-button').show();
        }
    }
    
    /**
     * Obter a URL da imagem a partir do ID
     */
    function getImageUrl(imageId, callback) {
        // Se estamos no ambiente WordPress, usamos a API REST para obter a URL da imagem
        if (typeof wp !== 'undefined' && wp.ajax && vslPlayerData.rest_url) {
            $.ajax({
                url: vslPlayerData.rest_url + 'wp/v2/media/' + imageId,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', vslPlayerData.nonce);
                },
                success: function(response) {
                    if (response && response.source_url) {
                        callback(response.source_url);
                    } else {
                        callback(null);
                    }
                },
                error: function() {
                    callback(null);
                }
            });
        } else if (vslPlayerData && vslPlayerData.plugin_url) {
            // Alternativa: fazemos uma chamada específica para obter a URL
            $.ajax({
                url: vslPlayerData.ajax_url,
                method: 'POST',
                data: {
                    action: 'vsl_get_image_url',
                    image_id: imageId,
                    nonce: vslPlayerData.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        callback(response.data);
                    } else {
                        callback(null);
                    }
                },
                error: function() {
                    callback(null);
                }
            });
        } else {
            callback(null);
        }
    }
    
    /**
     * Set up click events for player overlays
     */
    function setupOverlayEvents($container, containerId) {
        var $startOverlay = $container.find('.vsl-start-overlay');
        var $playingOverlay = $container.find('.vsl-playing-overlay');
        var $pauseOverlay = $container.find('.vsl-pause-overlay');
        
        // Pré-configura o overlay de pausa imediatamente (não esperar o clique)
        setupPauseOverlayStyle($container, $pauseOverlay);
        
        $startOverlay.on('click', function() {
            var player = players[containerId];
            if (player) {
                player.unMute();
                player.seekTo(0);
                player.playVideo();
                
                // Solução mais robusta para ocultar o overlay inicial (compatível com Safari iOS)
                $startOverlay.css({
                    'opacity': '0',
                    'visibility': 'hidden',
                    'display': 'none',
                    'pointer-events': 'none',
                    'z-index': '-1'  // Mover para trás de outros elementos
                });
                
                // Forçar repaint para Safari iOS
                setTimeout(function() {
                    $startOverlay.find('.vsl-play-button-svg').css('display', 'none');
                    $playingOverlay.show();
                }, 10);
                
                // Make the player's script ID available globally
                var scriptId = containerId.replace('vsl-player-', '');
                window['vslYouTubePlayer_' + scriptId] = player;
                
                // Trigger an event for the progress bar
                $(document).trigger('YT.PlayerState.PLAYING', [player, scriptId]);
                
                // Definir explicitamente userInitiatedPlay como true quando o usuário clicar
                $(document).trigger('vsl.userInitiatedPlay', [scriptId]);
            }
        });
        
        $playingOverlay.on('click', function() {
            var player = players[containerId];
            if (player) {
                player.pauseVideo();
                
                // Não precisamos reconfigurar o estilo aqui, pois já foi pré-configurado
                // e isso estava causando o atraso
                
                $pauseOverlay.css({
                    'display': 'block',
                    'opacity': '1',
                    'pointer-events': 'auto',
                    'transition': '0s'
                });
                
                // Trigger an event for the progress bar
                var scriptId = containerId.replace('vsl-player-', '');
                $(document).trigger('YT.PlayerState.PAUSED', [player, scriptId]);
            }
        });
        
        $pauseOverlay.on('click', function() {
            var player = players[containerId];
            if (player) {
                player.playVideo();
                $pauseOverlay.css({
                    'opacity': '0',
                    'pointer-events': 'none',
                    'transition': '0.5s 0.3s'
                });
                
                // Trigger an event for the progress bar
                var scriptId = containerId.replace('vsl-player-', '');
                $(document).trigger('YT.PlayerState.PLAYING', [player, scriptId]);
            }
        });
    }
    
    /**
     * Initialize all YouTube players on the page
     */
    function initializeYouTubePlayers() {
        $('.vsl-player-container').each(function() {
            var $container = $(this);
            var containerId = $container.attr('id');
            var postId = $container.data('vsl-id');
            
            if (!containerId || !postId) {
                return;
            }
            
            // Verificar se o player está aguardando a escolha do recurso "Continuar assistindo"
            if ($container.attr('data-resume-pending') === 'true') {
                return;
            }
            
            // Get YouTube video ID from player data
            var videoId = null;
            for (var i = 0; i < vslPlayerData.players.length; i++) {
                if (vslPlayerData.players[i].id == postId) {
                    videoId = vslPlayerData.players[i].video_id;
                    break;
                }
            }
            
            if (!videoId) {
                return;
            }
            
            createYouTubePlayer(containerId, videoId);
        });
        
        // Ouvir o evento de inicialização do player a partir do Resume Player
        $(document).on('VSL.InitializePlayer', function(event, $container, vslId, startTime) {
            console.log('VSL Player: Received initialization request from Resume Player for video ' + vslId + ' with start time ' + startTime);
            
            // Obter o containerId
            var containerId = $container.attr('id');
            
            // Obter o vídeo ID
            var videoId = null;
            for (var i = 0; i < vslPlayerData.players.length; i++) {
                if (vslPlayerData.players[i].id == vslId) {
                    videoId = vslPlayerData.players[i].video_id;
                    break;
                }
            }
            
            if (!videoId) {
                return;
            }
            
            // Inicializar o player do YouTube
            createYouTubePlayer(containerId, videoId, startTime);
        });
    }
    
    /**
     * Create a YouTube player instance
     */
    function createYouTubePlayer(containerId, videoId, startTime) {
        var $container = $('#' + containerId);
        
        if (!$container.length) {
            return;
        }
        
        // Adicionar classe de loading para esconder o player até estar pronto
        $container.addClass('vsl-player-loading');
        
        // Create inner container for YouTube iframe
        var innerPlayerId = containerId + '-inner';
        if ($('#' + innerPlayerId).length === 0) {
            $container.prepend('<div id="' + innerPlayerId + '"></div>');
        }
        
        // Armazenar o tempo inicial, se fornecido
        if (startTime !== undefined) {
            $container.data('start-time', startTime);
        }
        
        try {
            players[containerId] = new YT.Player(innerPlayerId, {
                height: '100%',
                width: '100%',
                videoId: videoId,
                playerVars: {
                    'autoplay': 1,
                    'controls': 0,
                    'mute': 1,
                    'enablejsapi': 1,
                    'rel': 0,
                    'showinfo': 0,
                    'modestbranding': 1,
                    'loop': 1,
                    'playlist': videoId // Required for looping when using player parameters
                },
                events: {
                    'onReady': onPlayerReady,
                    'onStateChange': onPlayerStateChange,
                    'onError': onPlayerError
                }
            });
        } catch (e) {
            console.error('VSL Player: Error creating YouTube player - ', e);
        }
    }
    
    /**
     * YouTube player onReady event handler
     */
    function onPlayerReady(event) {
        var player = event.target;
        var iframe = player.getIframe();
        var containerId = iframe.id.replace('-inner', '');
        var $container = $('#' + containerId);
        var vslId = $container.data('vsl-id');
        var scriptId = iframe.id.replace('vsl-youtube-', '').replace('-inner', '');
        
        // Verificar se existe um tempo inicial definido pelo Resume Player
        var startTime = $container.data('start-time');
        if (startTime !== undefined && startTime > 0) {
            player.seekTo(startTime);
            player.unMute();
        } else {
            // Set initial volume
            player.setVolume(100);
            
            // Mute player initially (autoplay requirement)
            player.mute();
        }
        
        // Start video
        player.playVideo();
        
        // Disparar um evento personalizado para notificar que o player está pronto
        $(document).trigger('YT.PlayerReady', [player, scriptId]);
        
        // Armazenar o player em uma variável global para facilitar o acesso
        window['vslYouTubePlayer_' + vslId] = player;
    }
    
    /**
     * YouTube player onStateChange event handler
     */
    function onPlayerStateChange(event) {
        var state = event.data;
        var player = event.target;
        var iframe = player.getIframe();
        var containerId = iframe.id.replace('-inner', '');
        var scriptId = containerId.replace('vsl-player-', '');
        
        if (state === YT.PlayerState.ENDED) {
            // Loop the video from the beginning
            event.target.seekTo(0);
            
            // Trigger an event for the progress bar
            $(document).trigger('YT.PlayerState.ENDED', [player, scriptId]);
        } else if (state === YT.PlayerState.PLAYING) {
            // Trigger an event for the progress bar
            $(document).trigger('YT.PlayerState.PLAYING', [player, scriptId]);
        } else if (state === YT.PlayerState.PAUSED) {
            // Trigger an event for the progress bar
            $(document).trigger('YT.PlayerState.PAUSED', [player, scriptId]);
        }
    }
    
    /**
     * YouTube player onError event handler
     */
    function onPlayerError(event) {
        console.error('VSL Player: Player error - ' + event.data);
    }
    
})(jQuery);
