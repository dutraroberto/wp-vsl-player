/**
 * VSL Player Otimizado - Revelar Oferta Functionality
 */
(function($) {
    'use strict';

    // Store revealed elements to avoid duplicating operations
    var revealedElements = {};
    
    // Initialize when the document is ready
    $(document).ready(function() {
        // Find all VSL players with reveal offer enabled
        $('.vsl-player-container').each(function() {
            var $container = $(this);
            var enableRevealOffer = $container.data('enable-reveal-offer') === true || $container.data('enable-reveal-offer') === 'true';
            
            if (enableRevealOffer) {
                setupRevealOffer($container);
            }
        });
    });
    
    /**
     * Setup reveal offer functionality for a specific player
     */
    function setupRevealOffer($container) {
        var revealClass = $container.data('reveal-class');
        var revealTime = parseInt($container.data('reveal-time'), 10) || 3;
        var videoId = $container.data('video-id');
        
        // Skip if no valid class to reveal
        if (!revealClass) {
            return;
        }
        
        // Aplicar estilos iniciais aos elementos alvo
        // Não adicionamos a classe "esconder", usamos diretamente a classe do usuário
        $(revealClass).css({
            'display': 'none',
            'opacity': '0',
            'transition': 'opacity 1s ease'
        });
        
        // Listen for messages from the YouTube player
        window.addEventListener('message', function(event) {
            let data;
            
            // Parse data from the event
            if (typeof event.data === 'string') {
                try {
                    data = JSON.parse(event.data);
                } catch (error) {
                    return;
                }
            } else {
                data = event.data;
            }
            
            // Check if this message is for our video
            if (data.id && data.id === videoId) {
                // Get current time from the player
                let currentTime = data.info?.currentTime;
                
                // If current time exceeds reveal time, show the elements
                if (currentTime > revealTime) {
                    revealElements(revealClass);
                }
            }
        });
    }
    
    /**
     * Reveal elements with the specified class
     */
    function revealElements(selector) {
        // Skip if already revealed
        if (revealedElements[selector]) {
            return;
        }
        
        // Mark as revealed to avoid duplicate operations
        revealedElements[selector] = true;
        
        // Get all elements with the specified class
        let elements = document.querySelectorAll(selector);
        
        // Revelar os elementos
        for (let i = 0; i < elements.length; i++) {
            // Primeiro exibimos o elemento
            elements[i].style.display = 'flex';
            
            // Depois de um pequeno delay, ajustamos a opacidade para criar uma transição suave
            setTimeout(function() {
                elements[i].style.opacity = '1';
            }, 200);
        }
    }
    
})(jQuery);
