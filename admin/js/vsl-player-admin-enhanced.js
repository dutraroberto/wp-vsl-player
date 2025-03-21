/**
 * VSL Player Otimizado - Enhanced Admin Scripts
 */
(function($) {
    'use strict';

    // Document ready
    $(function() {
        // Initialize enhanced media uploader
        initEnhancedMediaUploader();
        
        // Initialize toggle switches
        initToggleSwitches();
        
        // Initialize conditional fields
        initConditionalFields();
        
        // Initialize shortcode copy
        initShortcodeCopy();
        
        // Initialize color picker
        initColorPicker();
        
        // Initialize Select2
        if ($.fn.select2) {
            initSelect2();
        }
        
        // Initialize notice dismiss functionality
        initNoticeDismiss();
        
        // Initialize license validation functionality
        initLicenseValidation();
        
        // Handler específico para o botão "Remover" do vídeo de fallback
        $('#vsl_fallback_video_remove').on('click', function(e) {
            e.preventDefault();
            $('#vsl_fallback_video').val('');
            $('#vsl_fallback_video_preview').empty();
        });
        
        // Trigger change events for selects to ensure proper initial state
        setTimeout(function() {
            $('select.vsl-conditional-control').trigger('change');
        }, 100);
    });

    /**
     * Initialize enhanced media uploader
     */
    function initEnhancedMediaUploader() {
        // Armazenar os frames por ID de botão
        var mediaFrames = {};
        
        // Limpar eventos anteriores e recriar
        $('.vsl-upload-media').off('click');
        
        // Open media uploader on button click
        $('.vsl-upload-media').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var mediaType = button.data('media-type') || 'image';
            var buttonId = button.attr('id');
            var inputId = buttonId.replace('_button', '');
            var inputField = $('#' + inputId);
            var previewContainer = $('#' + inputId + '_preview');
            
            // Se já existir um frame para este botão, destrua-o
            if (mediaFrames[buttonId]) {
                delete mediaFrames[buttonId];
            }
            
            // Usar a implementação mais simples e confiável da API de mídia do WordPress
            var frame = wp.media.frames[buttonId] = wp.media({
                title: mediaType === 'video' ? 'Selecionar Vídeo' : 'Selecionar Imagem',
                library: { type: mediaType },
                button: { text: 'Usar este arquivo' },
                multiple: false
            });
            
            // Armazenar referência
            mediaFrames[buttonId] = frame;
            
            // Quando um arquivo é selecionado
            frame.on('select', function() {
                // Obter os detalhes do arquivo selecionado
                var attachment = frame.state().get('selection').first().toJSON();
                
                // Atualizar campo oculto com o ID do anexo
                inputField.val(attachment.id);
                
                // Limpar o container de prévia
                previewContainer.empty();
                
                // Criar visualização com base no tipo de mídia
                if (mediaType === 'video') {
                    // Criar a visualização do vídeo
                    var videoPreview = $('<div class="vsl-video-preview-wrapper vsl-video-preview-simple"></div>');
                    var videoInfo = $('<div class="vsl-video-info"></div>');
                    
                    // Nome do arquivo com ícone
                    var fileNameWrapper = $('<div class="vsl-video-filename-wrapper"></div>');
                    fileNameWrapper.append('<span class="dashicons dashicons-video-alt3"></span>');
                    fileNameWrapper.append('<span class="vsl-video-filename">' + attachment.filename + '</span>');
                    videoInfo.append(fileNameWrapper);
                    
                    // Detalhes do arquivo
                    var detailsText = attachment.subtype ? attachment.subtype.toUpperCase() : 'VIDEO';
                    
                    // Adicionar tamanho do arquivo se disponível
                    if (attachment.filesizeInBytes) {
                        var fileSizeInMB = Math.round(attachment.filesizeInBytes / 1024 / 1024 * 10) / 10;
                        detailsText += ' · ' + fileSizeInMB + ' MB';
                    }
                    
                    // Adicionar duração se disponível
                    if (attachment.meta && attachment.meta.length_formatted) {
                        detailsText += ' · ' + attachment.meta.length_formatted;
                    }
                    
                    videoInfo.append('<div class="vsl-video-details">' + detailsText + '</div>');
                    videoPreview.append(videoInfo);
                    previewContainer.append(videoPreview);
                } else {
                    // Exibir imagem
                    previewContainer.append('<img src="' + attachment.url + '" />');
                }
                
                // Adicionar botão remover
                var removeButton = $('<button type="button" class="button vsl-remove-media" data-input-id="' + inputId + '">Remover</button>');
                previewContainer.append(removeButton);
            });
            
            // Abrir o seletor de mídia
            frame.open();
            
            return false;
        });
        
        // Botão para remover mídia
        $(document).off('click', '.vsl-remove-media').on('click', '.vsl-remove-media', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var inputId = button.data('input-id');
            
            if (inputId) {
                // Limpar entrada e visualização
                $('#' + inputId).val('');
                $('#' + inputId + '_preview').empty();
            } else {
                // Fallback para método antigo
                var container = button.parent();
                var inputField = $('#' + container.attr('id').replace('_preview', ''));
                inputField.val('');
                container.empty();
            }
            
            return false;
        });
    }

    /**
     * Initialize toggle switches
     */
    function initToggleSwitches() {
        $('.vsl-toggle-switch input').on('change', function() {
            var toggleSwitch = $(this);
            var targetField = toggleSwitch.data('target');
            
            if (targetField) {
                if (toggleSwitch.is(':checked')) {
                    $('.' + targetField).slideDown(200);
                } else {
                    $('.' + targetField).slideUp(200);
                }
            }
        });
    }

    /**
     * Initialize conditional fields based on select values
     */
    function initConditionalFields() {
        // Para campos checkbox controlando exibições condicionais
        $('.vsl-conditional-control[type="checkbox"]').on('change', function() {
            var checkbox = $(this);
            var group = checkbox.data('control-group');
            
            if (group) {
                // Mostrar ou ocultar com base no estado do checkbox
                if (checkbox.is(':checked')) {
                    $('.' + group).slideDown(200);
                } else {
                    $('.' + group).slideUp(200);
                }
            }
        }).trigger('change'); // Disparar no carregamento da página
        
        // Para campos select controlando exibições condicionais
        $('select.vsl-conditional-control').on('change', function() {
            var select = $(this);
            var value = select.val();
            var group = select.data('control-group');
            
            if (group) {
                // Ocultar TODOS os campos no grupo de controle
                $('.' + group + '-default-field, .' + group + '-solid-field, .' + group + '-image-field, .' + group + '-continue-field').hide();
                
                // Limpar qualquer conteúdo ao alternar para uma opção diferente
                if (value !== 'image') {
                    // Se mudar para uma opção diferente de "image", resetar o campo de imagem
                    var imageField = $('#vsl_pause_image');
                    var previewContainer = $('#vsl_pause_image_preview');
                    
                    // Apenas limpar se estiver mudando DE "image" PARA outra opção
                    if (imageField.val() && select.data('previous-value') === 'image') {
                        imageField.val('');
                        previewContainer.html('');
                    }
                }
                
                // Mostrar apenas o campo com o valor correspondente
                $('.' + group + '-' + value + '-field').fadeIn(200);
                
                // Armazenar o valor atual para comparação futura
                select.data('previous-value', value);
            }
        });
        
        // Garantir que o estado inicial seja definido corretamente
        $('select.vsl-conditional-control').each(function() {
            var select = $(this);
            var value = select.val();
            var group = select.data('control-group');
            
            if (group) {
                // Ocultar todos os campos no carregamento
                $('.' + group + '-default-field, .' + group + '-solid-field, .' + group + '-image-field, .' + group + '-continue-field').hide();
                
                // Mostrar apenas o campo com o valor correspondente
                $('.' + group + '-' + value + '-field').show();
                
                // Definir o valor anterior
                select.data('previous-value', value);
            }
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
            
            // Mostrar mensagem de sucesso
            var successMessage = $('<span class="vsl-copy-success">Copiado!</span>');
            $(this).after(successMessage);
            
            // Remover mensagem após 2 segundos
            setTimeout(function() {
                successMessage.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 2000);
        });
    }
    
    /**
     * Initialize WordPress Color Picker
     */
    function initColorPicker() {
        if ($.fn.wpColorPicker) {
            $('.vsl-color-picker').wpColorPicker({
                change: function(event, ui) {
                    // Optional: trigger change event for any dependent elements
                }
            });
        }
    }
    
    /**
     * Initialize Select2
     */
    function initSelect2() {
        $('.vsl-select2').select2({
            width: '100%',
            dropdownAutoWidth: true,
            placeholder: function() {
                return $(this).data('placeholder');
            },
            allowClear: true
        });
    }

    /**
     * Initialize notice dismiss functionality
     */
    function initNoticeDismiss() {
        $('.vsl-notice-dismiss').on('click', function(e) {
            e.preventDefault();
            
            var notice = $(this).closest('.vsl-admin-notice');
            var noticeId = notice.data('notice-id');
            
            $.ajax({
                url: vsl_player_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vsl_dismiss_notice',
                    notice_id: noticeId,
                    nonce: vsl_player_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        notice.slideUp(200, function() {
                            $(this).remove();
                        });
                    }
                }
            });
        });
    }
    
    /**
     * Initialize license validation functionality
     */
    function initLicenseValidation() {
        $('#vsl-license-activate').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var licenseKey = $('#vsl_license_key').val();
            var resultContainer = $('#vsl-license-result');
            
            if (!licenseKey) {
                showAdminNotice('Por favor, insira uma chave de licença.', 'error');
                return;
            }
            
            // Desabilitar botão durante o processamento
            button.prop('disabled', true).text('Verificando...');
            resultContainer.html('');
            
            $.ajax({
                url: vsl_player_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vsl_activate_license',
                    license_key: licenseKey,
                    nonce: vsl_player_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showAdminNotice(response.data.message, 'success');
                        
                        // Atualizar status da licença se necessário
                        if (response.data.license_status) {
                            $('#vsl-license-status').text(response.data.license_status);
                        }
                        
                        // Reload se solicitado
                        if (response.data.reload) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }
                    } else {
                        showAdminNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    showAdminNotice('Ocorreu um erro ao tentar ativar a licença. Por favor, tente novamente.', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Ativar Licença');
                }
            });
        });
        
        $('#vsl-license-deactivate').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var resultContainer = $('#vsl-license-result');
            
            // Desabilitar botão durante o processamento
            button.prop('disabled', true).text('Desativando...');
            resultContainer.html('');
            
            $.ajax({
                url: vsl_player_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vsl_deactivate_license',
                    nonce: vsl_player_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showAdminNotice(response.data.message, 'success');
                        
                        // Atualizar status da licença se necessário
                        if (response.data.license_status) {
                            $('#vsl-license-status').text(response.data.license_status);
                        }
                        
                        // Reload se solicitado
                        if (response.data.reload) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }
                    } else {
                        showAdminNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    showAdminNotice('Ocorreu um erro ao tentar desativar a licença. Por favor, tente novamente.', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Desativar Licença');
                }
            });
        });
    }
    
    /**
     * Show WordPress-style admin notice
     */
    function showAdminNotice(message, type) {
        var noticeClass = 'notice notice-' + (type || 'info');
        var notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
        
        if ($('#vsl-license-result').length) {
            $('#vsl-license-result').html(notice);
        } else {
            var wpHeaderEnd = $('.wp-header-end');
            if (wpHeaderEnd.length) {
                wpHeaderEnd.after(notice);
            } else {
                $('.wrap h1, .wrap h2').first().after(notice);
            }
        }
    }
    
    /**
     * Helper function to get query parameters from URL
     */
    function getQueryParam(param) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }
})(jQuery);
