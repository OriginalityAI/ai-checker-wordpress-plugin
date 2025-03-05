<?php
// If this file is called directly, abort.
if (!defined('ABSPATH')) {
   exit;
}

// Vue visibility handling
$vcard_visibility = 'none';
if ($connection !== 'Connected') {
   $vcard_visibility = 'block';
}
?>
<div class="originality-ai--admin-container">
   <?php echo wp_kses_post( OriginalityAIAdminUI::get_logo() ); ?>
   <div id="app"></div>
   <div class="v-card" id="settings-originality-ai" style="display: <?php echo esc_attr( $vcard_visibility ); ?>">
       <!-- Connection Form -->
       <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" autocomplete="off">
           <?php wp_nonce_field( 'originalityai_connection_action', 'originalityai_connection_nonce' ); ?>
           
           <input type="hidden" name="action" value="<?php echo $connection === 'Connected' ? 'originalityai_disconnect' : 'originalityai_connect'; ?>">
           
           <p>
               <label for="connection-status"><?php esc_html_e( 'Connection Status', 'originality-ai'); ?>: 
                   <span style="color: <?php echo esc_attr( ( $connection === 'Disconnected' ) ? 'rgba(var(--v-theme-error))' : 'rgba(var(--v-theme-success))'); ?>; font-weight: bold">
                       <?php echo esc_html( $connection ); ?>
                   </span>
               </label>
           </p>

           <?php if ( $connection !== 'Connected' ): ?>
               <p>
                   <label for="login"><?php esc_html_e( 'Login', 'originality-ai' ); ?></label><br/>
                   <input type="text" id="login" name="login" required="required" autocomplete="none" 
                          placeholder="<?php esc_attr_e('Your Originality.ai login', 'originality-ai'); ?>">
               </p>
               <p>
                   <label for="password"><?php esc_html_e( 'Password', 'originality-ai' ); ?></label><br/>
                   <input type="password" id="password" name="password" required="required" autocomplete="new-password" 
                          placeholder="<?php esc_attr_e('Your Originality.ai password', 'originality-ai'); ?>">
               </p>
               <p>
                   <div class="v-row justify-center">
                       <div class="v-col-sm-12 v-col-md-8 v-col-12 d-flex justify-center font-weight-bold">
                           <span>
                               <a href="https://app.originality.ai/forgot-password?utm_source=wp_plugin" target="_blank" 
                                  class="text-primary cursor-pointer text-decoration-underline">
                                   <?php esc_html_e( 'Forgot Password?', 'originality-ai' ); ?>
                               </a>
                           </span>
                       </div>
                       <div class="v-col-sm-12 v-col-md-8 v-col-12 d-flex justify-center font-weight-bold">
                           <span>
                               <?php esc_html_e( "Don't have an account?", 'originality-ai' ); ?>
                               <a href="https://app.originality.ai/signup?utm_source=wp_plugin" target="_blank" 
                                  class="text-primary cursor-pointer text-decoration-underline">
                                   <?php esc_html_e( 'Sign Up', 'originality-ai' ); ?>
                               </a>
                           </span>
                       </div>
                   </div>
               </p>
           <?php else: ?>
               <p>
                   <label for="login"><?php esc_html_e( 'Login', 'originality-ai' ); ?></label><br/>
                   <input id="login" type="text" value="<?php echo esc_attr( OriginalityAI::get_account_email() ); ?>" readonly="readonly">
               </p>
           <?php endif; ?>

           <?php
           if ( $connection === 'Connected' ) {
               submit_button(
                   __( 'Disconnect', 'originality-ai' ),
                   'primary',
                   'disconnect-button',
                   false,
                   array(
                       'onclick' => 'return confirm("' . esc_js( __('Do you really want to disconnect?', 'originality-ai' ) ) . '");'
                   )
               );
           } else {
               submit_button(
                   __( 'Connect', 'originality-ai' ),
                   'primary', 
                   'connection-button'
               );
           }
           ?>
       </form>

       <!-- Settings Form -->
       <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" autocomplete="off">
           <?php wp_nonce_field( 'originalityai_save_settings_action', 'originalityai_save_settings_nonce' ); ?>
           <input type="hidden" name="action" value="originalityai_save_settings">
           
           <div style="padding-top: 20px">
               <h3><?php esc_html_e( 'Default AI Detection Model', 'originality-ai' ); ?></h3>
               <p><?php esc_html_e( 'Select the AI detection model default to suit your needs.', 'originality-ai' ); ?></p>
               <select name="model_id">
                   <?php
                   $current_model_id = OriginalityAI::get_setting_ai_scan_model();
                   foreach ( OriginalityAIAPI::AI_SCAN_MODELS as $model_id => $model_name ) {
                       $selected = ( $model_id == $current_model_id ) ? 'selected="selected"' : '';
                       echo '<option value="' . esc_attr( $model_id ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $model_name ) . '</option>';
                   }
                   ?>
               </select>
               <?php submit_button( __( 'Save', 'originality-ai' ), 'primary', 'save-button' ); ?>
           </div>
       </form>
   </div>
</div>