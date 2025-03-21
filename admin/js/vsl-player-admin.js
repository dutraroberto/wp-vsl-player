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
        
        // Initialize notice dismiss
        initNoticeDismiss();
        
        // Initialize color picker
        initColorPicker();
        
        // Initialize Select2
        initSelect2();
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
        // Get current admin page
        var adminPage = getQueryParam('page');
        
        // Handle license form submission via AJAX
        if (adminPage === 'vsl-player-license') {
            var licenseForm = $('.vsl-license-container form');
            
            if (licenseForm.length) {
                licenseForm.on('submit', function(e) {
                    e.preventDefault();
                    
                    var licenseKey = $('#vsl_player_license_key').val();
                    
                    if (!licenseKey) {
                        // Show inline error message
                        showAdminNotice('Por favor, insira uma chave de licença.', 'error');
                        return;
                    }
                    
                    // Show loading state
                    var submitButton = licenseForm.find('input[type="submit"]');
                    var originalText = submitButton.val();
                    submitButton.val('Validando...').prop('disabled', true);
                    
                    // Remove any existing notices
                    $('.notice').remove();
                    
                    // Show loading message
                    showAdminNotice('Validando licença...', 'info');
                    
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
                            // Remove loading notice
                            $('.notice-info').remove();
                            
                            // Reset button state
                            submitButton.val(originalText).prop('disabled', false);
                            
                            if (response.success) {
                                // Update license status visually
                                $('.license-status')
                                    .removeClass('status-inactive status-expired')
                                    .addClass('status-active')
                                    .text('Ativa');
                                    
                                // Add expiry date if provided
                                if (response.data && response.data.expiry) {
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
                                
                                // Show success message
                                showAdminNotice(response.data && response.data.message ? response.data.message : 'Licença ativada com sucesso! Seu plugin está pronto para uso.', 'success');
                            } else {
                                // Update license status visually
                                $('.license-status')
                                    .removeClass('status-active status-expired')
                                    .addClass('status-inactive')
                                    .text('Inativa');
                                
                                // Remove expiry row if it exists
                                $('th:contains("Expira em")').parent().remove();
                                
                                // Show error message
                                showAdminNotice(response.data && response.data.message ? response.data.message : 'Erro ao validar licença. Por favor, tente novamente.', 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            // Remove loading notice
                            $('.notice-info').remove();
                            
                            // Reset button state
                            submitButton.val(originalText).prop('disabled', false);
                            
                            // Show error message
                            showAdminNotice('Erro na requisição: ' + error, 'error');
                        }
                    });
                });
            }
        }
    }
    
    /**
     * Initialize WordPress Color Picker
     */
    function initColorPicker() {
        // Initialize color picker on all color-picker fields
        if ($.fn.wpColorPicker) {
            $('.color-picker').wpColorPicker({
                // Define default options
                defaultColor: '#617be5',
                change: function(event, ui) {
                    // Optional: Do something when color changes
                },
                clear: function() {
                    // Optional: Do something when color is cleared
                },
                hide: true,
                palettes: true
            });
        }
    }

    /**
     * Initialize Select2
     */
    function initSelect2() {
        // Initialize Select2 on all select2 fields
        if ($.fn.select2) {
            $('.vsl-select2-pages').select2({
                placeholder: "Selecione as páginas...",
                allowClear: true,
                width: '100%',
                dropdownAutoWidth: true,
                closeOnSelect: false
            });
        }
    }

    /**
     * Show WordPress-style admin notice
     */
    function showAdminNotice(message, type) {
        // Convert type to WordPress notice class
        var noticeClass = 'notice-' + (type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'));
        
        // Create notice HTML
        var noticeHtml = '<div class="notice ' + noticeClass + ' is-dismissible">' +
                         '<p>' + (type === 'error' ? '❌ Erro: ' : (type === 'success' ? '✅ ' : '⏳ ')) + message + '</p>' +
                         '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dispensar este aviso.</span></button>' +
                         '</div>';
                         
        // Insert notice at the top of the page
        if ($('.wrap > h1').length) {
            $(noticeHtml).insertAfter('.wrap > h1');
        } else {
            $('.wrap').prepend(noticeHtml);
        }
    }
    
    /**
     * Initialize notice dismiss functionality
     */
    function initNoticeDismiss() {
        $(document).on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Helper function to get query parameters from URL
     */
    function getQueryParam(param) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

})(jQuery);
