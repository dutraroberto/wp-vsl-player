/**
 * VSL Player Offer Reveal Functionality
 * 
 * This script handles revealing elements on the page based on the video's current time.
 */
(function($) {
    'use strict';

    // Store revealed elements to avoid revealing them again
    const revealedElements = {};
    
    // Store the time polling interval instances
    const timePollingIntervals = {};
    
    // Track if elements were previously shown (for persistence)
    const checkPersistentElements = function(revealClass, vslId) {
        const storageKey = `vsl_revealed_${vslId}_${revealClass.replace(/\W/g, '_')}`;
        return localStorage.getItem(storageKey) === 'true';
    };

    // Mark elements as revealed for persistence
    const markElementsAsRevealed = function(revealClass, vslId) {
        const storageKey = `vsl_revealed_${vslId}_${revealClass.replace(/\W/g, '_')}`;
        localStorage.setItem(storageKey, 'true');
    };

    // Function to reveal elements
    const revealElements = function(revealClass, persist, vslId) {
        // Skip if already revealed
        if (revealedElements[`${vslId}_${revealClass}`]) {
            return;
        }

        const elementSelector = revealClass.trim();
        
        if (!elementSelector) {
            return;
        }

        const elements = document.querySelectorAll(elementSelector);
        
        if (elements.length === 0) {
            return;
        }

        // Mark these elements as revealed
        revealedElements[`${vslId}_${revealClass}`] = true;
        
        elements.forEach(function(element) {
            // Add 'ativo' class for transition
            element.classList.add('ativo');
            
            // Wait for a short time then set opacity to 1
            setTimeout(function() {
                element.style.opacity = '1';
            }, 200);
        });

        // If persistence is enabled, save to localStorage
        if (persist) {
            markElementsAsRevealed(revealClass, vslId);
        }
    };

    // Initialize offer reveal on all VSL players
    const initOfferReveal = function() {
        $('.vsl-player-container').each(function() {
            const $container = $(this);
            const enableOfferReveal = $container.data('enable-offer-reveal') === true;
            
            if (!enableOfferReveal) {
                return;
            }
            
            const vslId = $container.data('vsl-id');
            const revealClass = $container.data('offer-reveal-class');
            const revealTime = parseInt($container.data('offer-reveal-time'), 10) || 0;
            const persist = $container.data('offer-reveal-persist') === true;
            
            if (!revealClass) {
                return;
            }
            
            // Check if elements should be revealed immediately due to persistence
            if (persist && checkPersistentElements(revealClass, vslId)) {
                revealElements(revealClass, false, vslId);
            }
            
            // Add the necessary CSS to hide elements until revealed
            const styleId = `vsl-offer-reveal-style-${vslId}`;
            
            // Only add the style if it doesn't already exist
            if (!document.getElementById(styleId)) {
                const style = document.createElement('style');
                style.id = styleId;
                style.textContent = `
                    ${revealClass} {
                        display: none;
                        opacity: 0;
                        transition: 1s;
                    }
                    
                    ${revealClass}.ativo {
                        display: flex;
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Setup active polling for video time
            setupTimePolling($container, vslId, revealClass, revealTime, persist);
            
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
                
                // Check if we should reveal elements
                if (currentTime >= revealTime) {
                    revealElements(revealClass, persist, vslId);
                }
            });
        });
    };
    
    // Setup active polling for video time
    const setupTimePolling = function($container, vslId, revealClass, revealTime, persist) {
        const containerId = $container.attr('id');
        
        if (!containerId) {
            return;
        }
        
        // Clear any existing interval
        if (timePollingIntervals[containerId]) {
            clearInterval(timePollingIntervals[containerId]);
        }
        
        // Poll every 500ms to check video time
        timePollingIntervals[containerId] = setInterval(function() {
            // Skip if already revealed
            if (revealedElements[`${vslId}_${revealClass}`]) {
                clearInterval(timePollingIntervals[containerId]);
                return;
            }
            
            // Access the player through window.vslPlayers global object
            if (window.vslPlayers && window.vslPlayers[containerId]) {
                try {
                    const player = window.vslPlayers[containerId];
                    const currentTime = player.getCurrentTime();
                    
                    // Check if we should reveal elements
                    if (currentTime >= revealTime) {
                        revealElements(revealClass, persist, vslId);
                        clearInterval(timePollingIntervals[containerId]);
                    }
                } catch (e) {
                    console.error('[VSL Player] Error accessing player:', e);
                }
            }
        }, 500);
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        initOfferReveal();
    });

})(jQuery);
