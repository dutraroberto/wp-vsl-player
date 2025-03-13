/**
 * VSL Player Otimizado - Admin Scripts
 */
(function($) {
    'use strict';

    // Document ready
    $(function() {
        // Initialize media uploader
        initMediaUploader();
        
        // Initialize shortcode copy
        initShortcodeCopy();
        
        // Initialize license validation
        initLicenseValidation();
    });

    /**
     * Initialize WordPress media uploader
     */
    function initMediaUploader() {
        var mediaUploader;
        
        // Open media uploader on button click
        $('.vsl-upload-media').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var inputField = $('#' + button.attr('id').replace('_button', ''));
            var previewContainer = $('#' + inputField.attr('id') + '_preview');
            
            // If the uploader object has already been created, reopen the dialog
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            // Create media uploader
            mediaUploader = wp.media({
                title: 'Selecionar Mídia',
                button: {
                    text: 'Usar este arquivo'
                },
                library: {
                    type: 'video' // Limit selection to video files
                },
                multiple: false
            });
            
            // When a file is selected, grab the URL and set it as the input value
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                inputField.val(attachment.id);
                
                // Update preview
                previewContainer.html('');
                if (attachment.type === 'video') {
                    var img = $('<img>').attr('src', attachment.image.src);
                    previewContainer.append(img);
                    
                    var removeButton = $('<button>').attr({
                        'type': 'button',
                        'class': 'button vsl-remove-media'
                    }).text('Remover');
                    previewContainer.append(removeButton);
                }
            });
            
            // Open the uploader dialog
            mediaUploader.open();
        });
        
        // Remove media on button click
        $(document).on('click', '.vsl-remove-media', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var previewContainer = button.parent();
            var inputField = $('#' + previewContainer.attr('id').replace('_preview', ''));
            
            // Clear input and preview
            inputField.val('');
            previewContainer.html('');
        });
    }

    /**
     * Initialize shortcode copy functionality
     */
    function initShortcodeCopy() {
        $('.vsl-copy-shortcode').on('click', function(e) {
            e.preventDefault();
            
            var shortcode = $(this).data('shortcode');
            var tempInput = $('<input>');
            
            $('body').append(tempInput);
            tempInput.val(shortcode).select();
            document.execCommand('copy');
            tempInput.remove();
            
            // Show success message
            var originalText = $(this).text();
            $(this).text('Copiado!');
            
            // Reset button text after 2 seconds
            setTimeout(function(button, text) {
                button.text(text);
            }, 2000, $(this), originalText);
        });
    }

    /**
     * Initialize license validation
     */
    function initLicenseValidation() {
        // Handle license form submission via AJAX
        var licenseForm = $('form').has('input#vsl_player_license_key');
        
        if (licenseForm.length) {
            licenseForm.on('submit', function(e) {
                e.preventDefault();
                
                var licenseKey = $('#vsl_player_license_key').val();
                
                if (!licenseKey) {
                    alert('Por favor, insira uma chave de licença.');
                    return;
                }
                
                // Show loading state
                var submitButton = licenseForm.find('input[type="submit"]');
                var originalText = submitButton.val();
                submitButton.val('Validando...').prop('disabled', true);
                
                // Send AJAX request
                $.ajax({
                    url: vsl_player_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vsl_validate_license',
                        license_key: licenseKey,
                        nonce: vsl_player_params.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update UI with success
                            $('.license-status')
                                .removeClass('status-inactive status-expired')
                                .addClass('status-active')
                                .text('Ativa');
                                
                            // Add expiry date if provided
                            if (response.data.expiry) {
                                // Check if expiry row exists
                                var expiryRow = $('th:contains("Expira em")').parent();
                                if (expiryRow.length) {
                                    expiryRow.find('td').text(response.data.expiry);
                                } else {
                                    // Create new row for expiry
                                    var newRow = $('<tr><th scope="row">Expira em</th><td>' + response.data.expiry + '</td></tr>');
                                    $('.form-table').append(newRow);
                                }
                            }
                            
                            alert(response.data.message);
                        } else {
                            // Update UI with failure
                            $('.license-status')
                                .removeClass('status-active status-expired')
                                .addClass('status-inactive')
                                .text('Inativa');
                                
                            // Remove expiry row if it exists
                            $('th:contains("Expira em")').parent().remove();
                            
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('Ocorreu um erro ao validar a licença. Por favor, tente novamente mais tarde.');
                    },
                    complete: function() {
                        // Restore button state
                        submitButton.val(originalText).prop('disabled', false);
                    }
                });
            });
        }
    }

})(jQuery);
