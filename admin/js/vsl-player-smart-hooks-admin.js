/**
 * VSL Player Smart Hooks Admin
 * 
 * Handles the admin UI for adding/removing smart hooks
 */
jQuery(document).ready(function($) {
    'use strict';

    // Controle do switcher mestre
    $('#vsl-smart-hooks-master-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#vsl-smart-hooks-wrapper').removeClass('hidden');
        } else {
            $('#vsl-smart-hooks-wrapper').addClass('hidden');
        }
    });
    
    // Add new smart hook
    $('#vsl-add-smart-hook').on('click', function() {
        const hookId = 'hook-' + Math.random().toString(36).substr(2, 9);
        const template = `
            <div class="vsl-smart-hook" id="${hookId}">
                <div class="vsl-smart-hook-header">
                    <h4>${vslSmartHooksAdmin.i18n.smartHook}</h4>
                    <button type="button" class="vsl-remove-hook button">${vslSmartHooksAdmin.i18n.remove}</button>
                </div>
                <div class="vsl-smart-hook-content">
                    <div class="vsl-hook-field">
                        <label for="${hookId}-name">${vslSmartHooksAdmin.i18n.hookName}</label>
                        <input type="text" id="${hookId}-name" 
                               name="vsl_smart_hooks[${hookId}][name]" 
                               class="widefat" placeholder="${vslSmartHooksAdmin.i18n.hookNamePlaceholder}" required>
                    </div>
                    <div class="vsl-hook-field">
                        <label for="${hookId}-image">${vslSmartHooksAdmin.i18n.hookImage}</label>
                        <input type="hidden" id="${hookId}-image" 
                               name="vsl_smart_hooks[${hookId}][image]" value="">
                        <button type="button" class="button vsl-upload-hook-image" 
                                data-target="${hookId}-image"
                                data-preview="${hookId}-image-preview">
                            ${vslSmartHooksAdmin.i18n.selectImage}
                        </button>
                        <p class="description">${vslSmartHooksAdmin.i18n.imageDescription}</p>
                        <div class="vsl-hook-image-preview" id="${hookId}-image-preview"></div>
                    </div>
                    <div class="vsl-hook-field-group">
                        <div class="vsl-hook-field">
                            <label for="${hookId}-start">${vslSmartHooksAdmin.i18n.startTime}</label>
                            <input type="number" id="${hookId}-start" 
                                   name="vsl_smart_hooks[${hookId}][start]" 
                                   class="small-text" min="0" step="1" value="0" required>
                        </div>
                        <div class="vsl-hook-field">
                            <label for="${hookId}-end">${vslSmartHooksAdmin.i18n.endTime}</label>
                            <input type="number" id="${hookId}-end" 
                                   name="vsl_smart_hooks[${hookId}][end]" 
                                   class="small-text" min="0" step="1" value="0" required>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#vsl-smart-hooks-container').append(template);
    });
    
    // Remove smart hook (delegated to handle dynamically added hooks)
    $('#vsl-smart-hooks-container').on('click', '.vsl-remove-hook', function() {
        const $hook = $(this).closest('.vsl-smart-hook');
        
        // Animate removal
        $hook.fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    // Handle image upload buttons (delegated for dynamic content)
    $('#vsl-smart-hooks-container').on('click', '.vsl-upload-hook-image', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const targetId = button.data('target');
        const previewId = button.data('preview');
        
        // Create the media frame
        const mediaFrame = wp.media({
            title: vslSmartHooksAdmin.i18n.selectHookImage,
            button: {
                text: vslSmartHooksAdmin.i18n.useThisImage
            },
            multiple: false
        });
        
        // When an image is selected, run a callback
        mediaFrame.on('select', function() {
            // Get the attachment from the modal frame
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            
            // Set the value of the hidden input field
            $('#' + targetId).val(attachment.id);
            
            // Update the preview
            const previewHtml = `
                <img src="${attachment.url}" alt="${attachment.title}" />
                <button type="button" class="button vsl-remove-hook-image" 
                        data-target="${targetId}" 
                        data-preview="${previewId}">
                    ${vslSmartHooksAdmin.i18n.remove}
                </button>
            `;
            
            $('#' + previewId).html(previewHtml);
        });
        
        // Open the modal
        mediaFrame.open();
    });
    
    // Remove hook image
    $('#vsl-smart-hooks-container').on('click', '.vsl-remove-hook-image', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const targetId = button.data('target');
        const previewId = button.data('preview');
        
        // Clear the input value
        $('#' + targetId).val('');
        
        // Clear the preview
        $('#' + previewId).empty();
    });
});
