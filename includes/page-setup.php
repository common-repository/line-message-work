<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( !isset($this->options['channel_access_token']) || $this->options['channel_access_token'] === ''){
?>
    <div class="notice notice-error  is-dismissible">
        <p><?php _e( 'channel_access_token is required!', SIG_LINE_WORK_PLUGIN_NAME ); ?></p>
    </div>
<?php
}

?>

<?php
if( !isset($this->options['channel_secret']) || $this->options['channel_secret'] === ''){
?>
    <div class="notice notice-error  is-dismissible">
        <p><?php _e( 'channel_secret is required!', SIG_LINE_WORK_PLUGIN_NAME ); ?></p>
    </div>
<?php
}

?>

<div class="wrap">
    <h2><?php _e('LINE Official Account Setting',SIG_LINE_WORK_PLUGIN_NAME)?></h2>
    <form method="post" action="options.php">
        <?php settings_fields('line-work-option'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Channel Access Token:',SIG_LINE_WORK_PLUGIN_NAME)?></th>
                <td>
                    <input type="text" class="regular-text" name="<?php echo SIG_LINE_WORK_OPTIONS?>[channel_access_token]" value="<?php if(isset($this->options['channel_access_token'])) echo esc_attr( $this->options['channel_access_token'] ) ?>">
                    <?php
                        if($this->token_status['code']==200):
                            _e('Access token valid',SIG_LINE_WORK_PLUGIN_NAME);
                    ?>
                    <?php
                        else:
                            _e('Invalid access token',SIG_LINE_WORK_PLUGIN_NAME);
                        endif;
                    ?>
                </td>
		</tr>
            <tr valign="top">

                <th scope="row"><?php _e('Channel Secret:',SIG_LINE_WORK_PLUGIN_NAME)?></th>
                <td>
                    <input type="text" class="regular-text" name="<?php echo SIG_LINE_WORK_OPTIONS?>[channel_secret]" value="<?php if(isset($this->options['channel_secret'])) echo esc_attr( $this->options['channel_secret'] ) ?>">
                    <?php
                        if($this->token_status['code']==200):
                            _e('channel secret valid',SIG_LINE_WORK_PLUGIN_NAME);
                    ?>
                    <?php
                        else:
                            _e('Invalid channel_secret',SIG_LINE_WORK_PLUGIN_NAME);
                        endif;
                    ?>
                </td>
            </tr>
 
        </table>

        <?php submit_button(); ?>
    </form>

    <br><hr><br>

    </form>
</div>

