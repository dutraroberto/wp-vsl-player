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
    var checkInterval = 250; // Check every 250ms

    /**
     * Initialize Smart Hooks for a player
     * @param {Object} player - YouTube player instance
     * @param {String} playerId - ID of the player container
     */
    function initSmartHooks(player, playerId) {
        const $container = $('#' + playerId);
        const isEnabled = $container.data('smart-hooks-enabled') === true;
        
        if (!isEnabled) {
            return;
        }

        try {
            const hooks = $container.data('smart-hooks');
            
            if (!hooks || !hooks.length) {
                return;
            }

            // Store hooks for this player
            playerHooks[playerId] = hooks;
            activeHooks[playerId] = {};
            
            // Create container for hook images
            $container.append('<div class="vsl-smart-hooks-container"></div>');
            
            // Debug log
            console.log(`[VSL Smart Hooks] Initialized hooks for player ${playerId}`);
            console.log(`[VSL Smart Hooks] ${hooks.length} hooks configured`);
            hooks.forEach(hook => {
                console.log(`[VSL Smart Hooks] Hook: ${hook.name}, Start: ${hook.start}s, End: ${hook.end}s`);
            });
            
            // Start checking the video time
            setInterval(function() {
                checkHookTimes(player, playerId);
            }, checkInterval);
        } catch (error) {
            console.error('[VSL Smart Hooks] Error initializing hooks:', error);
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
            const currentTime = player.getCurrentTime();
            const hooks = playerHooks[playerId];
            const $container = $('#' + playerId + ' .vsl-smart-hooks-container');

            // Check each hook to see if it should be displayed or hidden
            hooks.forEach(function(hook, index) {
                const hookId = `hook-${playerId}-${index}`;
                
                // Should this hook be active?
                const shouldBeActive = currentTime >= hook.start && currentTime <= hook.end;
                const isActive = activeHooks[playerId][hookId];
                
                if (shouldBeActive && !isActive) {
                    // Activate hook - show the image
                    showHook(hookId, hook, $container);
                    activeHooks[playerId][hookId] = true;
                    console.log(`[VSL Smart Hooks] Showing hook: ${hook.name} at ${currentTime}s`);
                } else if (!shouldBeActive && isActive) {
                    // Deactivate hook - hide the image
                    hideHook(hookId, $container);
                    activeHooks[playerId][hookId] = false;
                    console.log(`[VSL Smart Hooks] Hiding hook: ${hook.name} at ${currentTime}s`);
                }
            });
        } catch (error) {
            console.error('[VSL Smart Hooks] Error checking hook times:', error);
        }
    }

    /**
     * Show a hook image with fade-in effect
     * @param {String} hookId - Unique ID for this hook instance
     * @param {Object} hook - Hook configuration object
     * @param {jQuery} $container - Container element
     */
    function showHook(hookId, hook, $container) {
        // Create hook element if it doesn't exist
        if ($('#' + hookId).length === 0) {
            const $hook = $('<div>', {
                id: hookId,
                class: 'vsl-smart-hook',
                css: {
                    'background-image': `url(${hook.image})`,
                    'opacity': 0
                }
            });
            
            $container.append($hook);
        }
        
        // Fade in the hook
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
