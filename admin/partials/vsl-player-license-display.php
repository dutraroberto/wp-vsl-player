<?php
/**
 * License management display for VSL Player.
 *
 * @package    VSL_Player
 * @subpackage VSL_Player/admin/partials
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap vsl-player-admin">
    <h1>Licença do VSL Player Otimizado</h1>
    
    <div class="vsl-license-container">
        <h2>Ativar Licença</h2>
        <p>Insira sua chave de licença abaixo para ativar o VSL Player Otimizado e receber atualizações e suporte.</p>
        
        <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="vsl_player_license_key">Chave de Licença</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="vsl_player_license_key" 
                                   name="vsl_player_license_key" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($license_key); ?>" 
                                   placeholder="XXXX-XXXX-XXXX-XXXX"
                                   autocomplete="off">
                            <p class="description">Insira a chave de licença que você recebeu ao comprar o plugin.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <p>
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Validar Licença">
                <?php wp_nonce_field('vsl_player_nonce', 'nonce'); ?>
            </p>
        </form>
        
        <div class="license-info">
            <h3>Informações da Licença</h3>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <?php if ($license_status === 'active'): ?>
                                <span class="license-status status-active">Ativa</span>
                            <?php elseif ($license_status === 'expired'): ?>
                                <span class="license-status status-expired">Expirada</span>
                            <?php else: ?>
                                <span class="license-status status-inactive">Inativa</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <?php if ($license_status === 'active' && !empty($license_expiry)): ?>
                    <tr>
                        <th scope="row">Expira em</th>
                        <td><?php echo esc_html($license_expiry); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="vsl-license-container" style="margin-top: 30px;">
        <h2>Problemas com a licença?</h2>
        <p>Se você estiver enfrentando problemas ao ativar sua licença, entre em contato com nosso suporte:</p>
        <ul>
            <li>Email: <a href="mailto:suporte@mundowp.com.br">suporte@mundowp.com.br</a></li>
            <li>WhatsApp: <a href="https://wa.me/5562992831307" target="_blank" class="whatsapp-link"><span class="dashicons dashicons-whatsapp"></span> (62) 99283-1307</a></li>
        </ul>
    </div>
</div>
