/**
 * VSL Player Conversions
 * 
 * Handles tracking of conversion events triggered at specific points in VSL videos
 */
(function($) {
    'use strict';

    // Store tracked events to avoid duplicate firing
    const trackedEvents = {};
    
    // Store the time polling interval instances
    const timePollingIntervals = {};
    
    // Initialize conversion tracking on all VSL players
    const initConversionTracking = function() {
        // Verificar se as bibliotecas necessárias estão disponíveis
        checkTrackingLibraries();
        
        $('.vsl-player-container').each(function() {
            const $container = $(this);
            const vslId = $container.data('vsl-id');
            const containerId = $container.attr('id');
            
            // Verifique se há eventos de conversão
            const hasConversionEvents = $container.data('has-conversion-events') === true;
            if (!hasConversionEvents) {
                return;
            }
            
            // Obtenha os eventos de conversão do atributo de dados
            let conversionEvents;
            try {
                // Se os dados já estiverem desserializados como objeto
                conversionEvents = $container.data('conversion-events');
                
                // Se for string, parse para objeto (pode acontecer devido à serialização)
                if (typeof conversionEvents === 'string') {
                    conversionEvents = JSON.parse(conversionEvents);
                }
                
                // Converti para array se for objeto
                if (conversionEvents && typeof conversionEvents === 'object' && !Array.isArray(conversionEvents)) {
                    conversionEvents = Object.keys(conversionEvents).map(key => {
                        return {
                            id: key,
                            ...conversionEvents[key]
                        };
                    });
                }
            } catch (e) {
                return;
            }
            
            if (!conversionEvents || !conversionEvents.length) {
                return;
            }
            
            // Inicialize cada evento no objeto de eventos rastreados
            conversionEvents.forEach(function(event) {
                const eventKey = `${vslId}_${event.id}`;
                trackedEvents[eventKey] = false;
            });
            
            // Configure o monitoramento do tempo do vídeo
            setupTimePolling($container, containerId, vslId, conversionEvents);
            
            // Listen for messages from the YouTube iframe (for backwards compatibility)
            window.addEventListener('message', function(event) {
                let data;
                
                // Parse the data if it's a string
                if (typeof event.data === 'string') {
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
                if (typeof currentTime !== 'number' || videoId !== $container.data('video-id')) {
                    return;
                }
                
                // Check all conversion events
                checkConversionEvents(vslId, currentTime, conversionEvents);
            });
        });
    };
    
    // Verificar se as bibliotecas de rastreamento estão disponíveis
    const checkTrackingLibraries = function() {
        if (typeof gtag !== 'function') {
            console.warn('[VSL Player] Google Analytics/Ads não detectado. Por favor, adicione o código de rastreamento do Google Analytics ou Google Ads no cabeçalho do site para habilitar o rastreamento de conversões.');
        }
        
        if (typeof fbq !== 'function') {
            console.warn('[VSL Player] Facebook Pixel não detectado. Por favor, adicione o código do Facebook Pixel no cabeçalho do site para habilitar o rastreamento de conversões para o Facebook.');
        }
    };
    
    // Setup active polling for video time - similar à função do offerReveal
    const setupTimePolling = function($container, containerId, vslId, conversionEvents) {
        if (!containerId) {
            return;
        }
        
        // Clear any existing interval
        if (timePollingIntervals[containerId]) {
            clearInterval(timePollingIntervals[containerId]);
        }
        
        // Poll every 500ms to check video time
        timePollingIntervals[containerId] = setInterval(function() {
            // Verifique se todos os eventos já foram rastreados
            const allEventsTracked = conversionEvents.every(function(event) {
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
                    
                    if (typeof currentTime === 'number') {
                        // Check all conversion events
                        checkConversionEvents(vslId, currentTime, conversionEvents);
                    }
                } catch (e) {
                    // Silenciando erro
                }
            }
        }, 500);
    };
    
    // Check and trigger conversion events based on current time
    const checkConversionEvents = function(vslId, currentTime, conversionEvents) {
        conversionEvents.forEach(function(event) {
            const eventKey = `${vslId}_${event.id}`;
            
            // Skip if already tracked
            if (trackedEvents[eventKey] === true) {
                return;
            }
            
            // Verifique se o evento deve ser disparado
            const eventTime = parseInt(event.time, 10) || 0;
            if (currentTime >= eventTime) {
                // Marque o evento como rastreado
                trackedEvents[eventKey] = true;
                
                // Dispare o evento de conversão
                triggerConversionEvent(event);
            }
        });
    };
    
    // Trigger the actual conversion event to analytics platforms
    const triggerConversionEvent = function(event) {
        // Certifique-se de que os valores sejam strings para comparação
        const gaEnabled = String(event.ga) === '1';
        const gadsEnabled = String(event.gads) === '1';
        const fbpixelEnabled = String(event.fbpixel) === '1';
        
        // Google Analytics (GA4)
        if (gaEnabled) {
            if (typeof gtag === 'function') {
                // Send event to GA4
                gtag('event', event.name, {
                    'event_category': 'VSL Player',
                    'event_label': `Timestamp: ${event.time}s`
                });
            } else {
                console.warn(`[VSL Player] Google Analytics está ativado para este evento, mas a função gtag não está disponível no site.`);
            }
        }
        
        // Google Ads
        if (gadsEnabled) {
            if (typeof gtag === 'function') {
                // Send conversion to Google Ads
                gtag('event', 'conversion', {
                    'send_to': 'AW-CONVERSION_ID/' + event.name
                });
            } else {
                console.warn(`[VSL Player] Google Ads está ativado para este evento, mas a função gtag não está disponível no site.`);
            }
        }
        
        // Facebook Pixel
        if (fbpixelEnabled) {
            if (typeof fbq === 'function') {
                // Send event to Facebook Pixel
                fbq('track', event.name);
            } else {
                console.warn(`[VSL Player] Facebook Pixel está ativado para este evento, mas a função fbq não está disponível no site.`);
            }
        }
        
        // Trigger a custom event for third-party integrations
        $(document).trigger('vsl_player_conversion', [event]);
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        initConversionTracking();
    });

})(jQuery);
