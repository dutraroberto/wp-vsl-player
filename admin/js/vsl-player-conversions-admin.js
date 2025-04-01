/**
 * VSL Player Conversions Admin
 * 
 * Handles the admin UI for adding/removing conversion events
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Controle do switcher mestre
        $('#vsl-conversions-master-toggle').on('change', function() {
            if ($(this).is(':checked')) {
                $('#vsl-conversion-events-wrapper').removeClass('hidden');
            } else {
                $('#vsl-conversion-events-wrapper').addClass('hidden');
            }
        });
        
        // Add new conversion event
        $('#vsl-add-conversion-event').on('click', function() {
            const eventId = 'event-' + Math.random().toString(36).substr(2, 9);
            const template = `
                <div class="vsl-conversion-event" id="${eventId}">
                    <div class="vsl-conversion-event-header">
                        <h4>${vslConversionsAdmin.i18n.conversionEvent}</h4>
                        <button type="button" class="vsl-remove-event button">${vslConversionsAdmin.i18n.remove}</button>
                    </div>
                    <div class="vsl-conversion-event-content">
                        <div class="vsl-event-field">
                            <label for="${eventId}-name">${vslConversionsAdmin.i18n.eventName}</label>
                            <input type="text" id="${eventId}-name" 
                                   name="vsl_conversion_events[${eventId}][name]" 
                                   class="widefat" placeholder="${vslConversionsAdmin.i18n.eventNamePlaceholder}" required>
                        </div>
                        <div class="vsl-event-field">
                            <label for="${eventId}-time">${vslConversionsAdmin.i18n.eventTime}</label>
                            <input type="number" id="${eventId}-time" 
                                   name="vsl_conversion_events[${eventId}][time]" 
                                   class="small-text" min="0" step="1" value="0" required>
                        </div>
                        <div class="vsl-event-integrations">
                            <h5>${vslConversionsAdmin.i18n.integrations}</h5>
                            <div class="vsl-event-integration-option">
                                <label class="vsl-toggle-switch">
                                    <input type="checkbox" name="vsl_conversion_events[${eventId}][ga]" value="1">
                                    <span class="vsl-toggle-slider"></span>
                                </label>
                                <span class="vsl-toggle-label">${vslConversionsAdmin.i18n.googleAnalytics}</span>
                            </div>
                            <div class="vsl-event-integration-option">
                                <label class="vsl-toggle-switch">
                                    <input type="checkbox" name="vsl_conversion_events[${eventId}][gads]" value="1">
                                    <span class="vsl-toggle-slider"></span>
                                </label>
                                <span class="vsl-toggle-label">${vslConversionsAdmin.i18n.googleAds}</span>
                            </div>
                            <div class="vsl-event-integration-option">
                                <label class="vsl-toggle-switch">
                                    <input type="checkbox" name="vsl_conversion_events[${eventId}][fbpixel]" value="1">
                                    <span class="vsl-toggle-slider"></span>
                                </label>
                                <span class="vsl-toggle-label">${vslConversionsAdmin.i18n.facebookPixel}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#vsl-conversion-events-container').append(template);
        });
        
        // Remove conversion event (delegated to handle dynamically added events)
        $('#vsl-conversion-events-container').on('click', '.vsl-remove-event', function() {
            const $event = $(this).closest('.vsl-conversion-event');
            
            // Animate removal
            $event.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Initialize existing conversion fields
        function initializeConversionFields() {
            // Any special initialization for the conversion fields
            // (e.g., colorpickers, etc.)
        }
        
        // Initialize fields on load
        initializeConversionFields();
    });

})(jQuery);
