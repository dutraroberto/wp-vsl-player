/**
 * VSL Player Otimizado - Content Reveal Functionality
 */
(function($) {
    'use strict';

    // Track page visit start time
    var pageStartTime = new Date().getTime();
    
    // Store revealed elements to avoid duplicating operations
    var revealedElements = {};
    
    // Check if localStorage is available
    var storageAvailable = (function() {
        try {
            var storage = window.localStorage;
            var x = '__storage_test__';
            storage.setItem(x, x);
            storage.removeItem(x);
            return true;
        } catch(e) {
            return false;
        }
    })();
    
    // Initialize reveal functionality
    function initializeReveal() {
        // Process all reveal configurations
        if (typeof vslRevealData !== 'undefined' && vslRevealData.reveals && vslRevealData.reveals.length > 0) {
            // First hide all elements that should be initially hidden
            vslRevealData.reveals.forEach(function(reveal) {
                var selector = reveal.class;
                
                // Skip if no valid selector
                if (!selector) return;
                
                // Check if we should keep elements revealed (from localStorage)
                if (storageAvailable && reveal.persist) {
                    var storageKey = 'vsl_reveal_' + reveal.id;
                    if (localStorage.getItem(storageKey) === 'revealed') {
                        // This element should remain visible
                        $(selector).addClass('vsl-revealed').show();
                        revealedElements[selector] = true;
                        return;
                    }
                }
                
                // Hide the elements initially
                $(selector).hide();
                
                // Set up the timer for revealing
                startRevealTimer(reveal);
            });
        }
    }
    
    // Start a timer to reveal content
    function startRevealTimer(reveal) {
        var intervalId = setInterval(function() {
            // Calculate how many seconds the user has been on the page
            var currentTime = new Date().getTime();
            var secondsOnPage = Math.floor((currentTime - pageStartTime) / 1000);
            
            // If the user has been on the page long enough, reveal the content
            if (secondsOnPage >= reveal.time) {
                revealContent(reveal);
                clearInterval(intervalId); // Stop the interval once revealed
            }
        }, 1000); // Check every second
    }
    
    // Reveal content based on configuration
    function revealContent(reveal) {
        var selector = reveal.class;
        
        // Skip if already revealed
        if (revealedElements[selector]) return;
        
        // Show the elements with a fade effect
        $(selector).fadeIn(1000).addClass('vsl-revealed');
        
        // Mark as revealed
        revealedElements[selector] = true;
        
        // Store state in localStorage if persistence is enabled
        if (storageAvailable && reveal.persist) {
            var storageKey = 'vsl_reveal_' + reveal.id;
            localStorage.setItem(storageKey, 'revealed');
        }
    }
    
    // Handle visibility changes (tab switching, etc.)
    function handleVisibilityChange() {
        if (!document.hidden) {
            // Page is now visible again
            
            // Check if any reveals should happen when coming back to the page
            if (typeof vslRevealData !== 'undefined' && vslRevealData.reveals) {
                vslRevealData.reveals.forEach(function(reveal) {
                    var selector = reveal.class;
                    
                    // Skip if already revealed or no valid selector
                    if (revealedElements[selector] || !selector) return;
                    
                    // Calculate how many seconds the user has been on the page
                    var currentTime = new Date().getTime();
                    var secondsOnPage = Math.floor((currentTime - pageStartTime) / 1000);
                    
                    // If the user has been on the page long enough, reveal the content
                    if (secondsOnPage >= reveal.time) {
                        revealContent(reveal);
                    }
                });
            }
        }
    }
    
    // Initialize when the document is ready
    $(document).ready(function() {
        // Initialize the reveal functionality
        initializeReveal();
        
        // Add visibility change handler
        document.addEventListener('visibilitychange', handleVisibilityChange, false);
    });
    
})(jQuery);
