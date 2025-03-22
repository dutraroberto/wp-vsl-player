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
        $('.vsl-player-container').each(function() {
            const $container = $(this);
            const vslId = $container.data('vsl-id');
            const containerId = $container.attr('id');
            const conversionEvents = $container.data('conversion-events');
            
            if (!conversionEvents || !Array.isArray(conversionEvents) || !conversionEvents.length) {
                return;
            }
            
            // Add each event to the tracked events object with initial state
            conversionEvents.forEach(function(event) {
                trackedEvents[`${vslId}_${event.id}`] = false;
            });
            
            // Setup active polling for video time
            setupTimePolling($container, containerId, vslId, conversionEvents);
            
            // Also listen for messages from the YouTube iframe (for backwards compatibility)
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
    
    // Setup active polling for video time
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
            // Check if all events are already tracked
            const allEventsTracked = conversionEvents.every(function(event) {
                return trackedEvents[`${vslId}_${event.id}`];
            });
            
            // If all events are tracked, clear the interval
            if (allEventsTracked) {
                clearInterval(timePollingIntervals[containerId]);
                return;
            }
            
            // Access the player through window.vslPlayers global object
            if (window.vslPlayers && window.vslPlayers[containerId]) {
                try {
                    const player = window.vslPlayers[containerId];
                    const currentTime = player.getCurrentTime();
                    
                    // Check all conversion events
                    checkConversionEvents(vslId, currentTime, conversionEvents);
                } catch (e) {
                    console.error('[VSL Player] Error accessing player:', e);
                }
            }
        }, 500);
    };
    
    // Check and trigger conversion events based on current time
    const checkConversionEvents = function(vslId, currentTime, conversionEvents) {
        conversionEvents.forEach(function(event) {
            const eventKey = `${vslId}_${event.id}`;
            
            // Skip if already tracked
            if (trackedEvents[eventKey]) {
                return;
            }
            
            // Check if we should trigger the event
            if (currentTime >= event.time) {
                // Mark event as tracked
                trackedEvents[eventKey] = true;
                
                // Trigger conversion event
                triggerConversionEvent(event);
            }
        });
    };
    
    // Trigger the actual conversion event to analytics platforms
    const triggerConversionEvent = function(event) {
        console.log(`[VSL Player] Conversion event triggered: ${event.name} at ${event.time} seconds`);
        
        // Google Analytics (GA4)
        if (event.ga && typeof gtag === 'function') {
            // Send event to GA4
            gtag('event', event.name, {
                'event_category': 'VSL Player',
                'event_label': `Timestamp: ${event.time}s`
            });
        }
        
        // Google Ads
        if (event.gads && typeof gtag === 'function') {
            // Send conversion to Google Ads
            gtag('event', 'conversion', {
                'send_to': 'AW-CONVERSION_ID/' + event.name
            });
        }
        
        // Facebook Pixel
        if (event.fbpixel && typeof fbq === 'function') {
            // Send event to Facebook Pixel
            fbq('track', event.name);
        }
        
        // Trigger a custom event for third-party integrations
        $(document).trigger('vsl_player_conversion', [event]);
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        initConversionTracking();
    });

})(jQuery);
