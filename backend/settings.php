<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

$hcap_api_key_n                     = "hcaptcha_api_key";
$hcap_secret_key_n                  = "hcaptcha_secret_key";
$hcap_theme_n                       = "hcaptcha_theme";
$hcap_size_n                        = "hcaptcha_size";
$hcap_language_n                    = "hcaptcha_language";
$hcap_nf_status_n                   = "hcaptcha_nf_status";
$hcap_cf7_status_n                  = "hcaptcha_cf7_status";
$hcap_lf_status_n                   = "hcaptcha_lf_status";
$hcap_rf_status_n                   = "hcaptcha_rf_status";
$hcap_cmf_status_n                  = "hcaptcha_cmf_status";
$hcap_lpf_status_n                  = "hcaptcha_lpf_status";
$hcap_wc_login_status_n             = "hcaptcha_wc_login_status";
$hcap_wc_reg_status_n               = "hcaptcha_wc_reg_status";
$hcap_wc_lost_pass_status_n         = "hcaptcha_wc_lost_pass_status";
$hcap_wc_checkout_status_n          = "hcaptcha_wc_checkout_status";
$hcap_bp_reg_status_n               = "hcaptcha_bp_reg_status";
$hcap_bp_create_group_status_n      = "hcaptcha_bp_create_group_status";
$hcap_bbp_new_topic_status_n        = "hcaptcha_bbp_new_topic_status";
$hcap_bbp_reply_status_n            = "hcaptcha_bbp_reply_status";
$hcap_wpforo_new_topic_status_n     = "hcaptcha_wpforo_new_topic_status";
$hcap_wpforo_reply_status_n         = "hcaptcha_wpforo_reply_status";
$hcap_mc4wp_status_n                = "hcaptcha_mc4wp_status";
$hcap_jetpack_cf_status_n           = "hcaptcha_jetpack_cf_status";
$hcap_subscribers_status_n          = "hcaptcha_subscribers_status";

if( isset( $_POST['hcaptcha_settings_nonce'] ) && wp_verify_nonce( $_POST['hcaptcha_settings_nonce'], 'hcaptcha_settings' ) && isset($_POST["submit"]) ){
    if(isset($_POST[$hcap_api_key_n])){
        $hcap_api_key           = $_POST[$hcap_api_key_n];
        update_option($hcap_api_key_n, $hcap_api_key);
    }

    if(isset($_POST[$hcap_secret_key_n])){
        $hcap_secret_key           = $_POST[$hcap_secret_key_n];
        update_option($hcap_secret_key_n, $hcap_secret_key);
    }

    if(isset($_POST[$hcap_theme_n])){
        $hcap_theme           = $_POST[$hcap_theme_n];
        update_option($hcap_theme_n, $hcap_theme);
    }

    if(isset($_POST[$hcap_size_n])){
        $hcap_size           = $_POST[$hcap_size_n];
        update_option($hcap_size_n, $hcap_size);
    }

    if(isset($_POST[$hcap_language_n])){
        $hcap_language           = $_POST[$hcap_language_n];
        update_option($hcap_language_n, $hcap_language);
    }

    if(isset($_POST[$hcap_nf_status_n])){
        $hcap_nf_status         = $_POST[$hcap_nf_status_n];
        update_option($hcap_nf_status_n, $hcap_nf_status);
    } else {
        $hcap_nf_status         = "off";
        update_option($hcap_nf_status_n, $hcap_nf_status);
    }

    if(isset($_POST[$hcap_cf7_status_n])){
        $hcap_cf7_status         = $_POST[$hcap_cf7_status_n];
        update_option($hcap_cf7_status_n, $hcap_cf7_status);
    } else {
        $hcap_cf7_status         = "off";
        update_option($hcap_cf7_status_n, $hcap_cf7_status);
    }

    if(isset($_POST[$hcap_lf_status_n])){
        $hcap_lf_status         = $_POST[$hcap_lf_status_n];
        update_option($hcap_lf_status_n, $hcap_lf_status);
    } else {
        $hcap_lf_status         = "off";
        update_option($hcap_lf_status_n, $hcap_lf_status);
    }

    if(isset($_POST[$hcap_rf_status_n])){
        $hcap_rf_status         = $_POST[$hcap_rf_status_n];
        update_option($hcap_rf_status_n, $hcap_rf_status);
    } else {
        $hcap_rf_status         = "off";
        update_option($hcap_rf_status_n, $hcap_rf_status);
    }

    if(isset($_POST[$hcap_cmf_status_n])){
        $hcap_cmf_status         = $_POST[$hcap_cmf_status_n];
        update_option($hcap_cmf_status_n, $hcap_cmf_status);
    } else {
        $hcap_cmf_status         = "off";
        update_option($hcap_cmf_status_n, $hcap_cmf_status);
    }

    if(isset($_POST[$hcap_lpf_status_n])){
        $hcap_lpf_status         = $_POST[$hcap_lpf_status_n];
        update_option($hcap_lpf_status_n, $hcap_lpf_status);
    } else {
        $hcap_lpf_status         = "off";
        update_option($hcap_lpf_status_n, $hcap_lpf_status);
    }

    if(isset($_POST[$hcap_wc_login_status_n])){
        $hcap_wc_login_status         = $_POST[$hcap_wc_login_status_n];
        update_option($hcap_wc_login_status_n, $hcap_wc_login_status);
    } else {
        $hcap_wc_login_status         = "off";
        update_option($hcap_wc_login_status_n, $hcap_wc_login_status);
    }

    if(isset($_POST[$hcap_wc_reg_status_n])){
        $hcap_wc_reg_status         = $_POST[$hcap_wc_reg_status_n];
        update_option($hcap_wc_reg_status_n, $hcap_wc_reg_status);
    } else {
        $hcap_wc_reg_status         = "off";
        update_option($hcap_wc_reg_status_n, $hcap_wc_reg_status);
    }

    if(isset($_POST[$hcap_wc_lost_pass_status_n])){
        $hcap_wc_lost_pass_status         = $_POST[$hcap_wc_lost_pass_status_n];
        update_option($hcap_wc_lost_pass_status_n, $hcap_wc_lost_pass_status);
    } else {
        $hcap_wc_lost_pass_status         = "off";
        update_option($hcap_wc_lost_pass_status_n, $hcap_wc_lost_pass_status);
    }

    if(isset($_POST[$hcap_wc_checkout_status_n])){
        $hcap_wc_checkout_status         = $_POST[$hcap_wc_checkout_status_n];
        update_option($hcap_wc_checkout_status_n, $hcap_wc_checkout_status);
    } else {
        $hcap_wc_checkout_status         = "off";
        update_option($hcap_wc_checkout_status_n, $hcap_wc_checkout_status);
    }

    if(isset($_POST[$hcap_bp_reg_status_n])){
        $hcap_bp_reg_status         = $_POST[$hcap_bp_reg_status_n];
        update_option($hcap_bp_reg_status_n, $hcap_bp_reg_status);
    } else {
        $hcap_bp_reg_status         = "off";
        update_option($hcap_bp_reg_status_n, $hcap_bp_reg_status);
    }

    if(isset($_POST[$hcap_bp_create_group_status_n])){
        $hcap_bp_create_group_status         = $_POST[$hcap_bp_create_group_status_n];
        update_option($hcap_bp_create_group_status_n, $hcap_bp_create_group_status);
    } else {
        $hcap_bp_create_group_status         = "off";
        update_option($hcap_bp_create_group_status_n, $hcap_bp_create_group_status);
    }

    if(isset($_POST[$hcap_bbp_new_topic_status_n])){
        $hcap_bbp_new_topic_status         = $_POST[$hcap_bbp_new_topic_status_n];
        update_option($hcap_bbp_new_topic_status_n, $hcap_bbp_new_topic_status);
    } else {
        $hcap_bbp_new_topic_status         = "off";
        update_option($hcap_bbp_new_topic_status_n, $hcap_bbp_new_topic_status);
    }

    if(isset($_POST[$hcap_bbp_reply_status_n])){
        $hcap_bbp_reply_status         = $_POST[$hcap_bbp_reply_status_n];
        update_option($hcap_bbp_reply_status_n, $hcap_bbp_reply_status);
    } else {
        $hcap_bbp_reply_status         = "off";
        update_option($hcap_bbp_reply_status_n, $hcap_bbp_reply_status);
    }

    if(isset($_POST[$hcap_wpforo_new_topic_status_n])){
        $hcap_wpforo_new_topic_status         = $_POST[$hcap_wpforo_new_topic_status_n];
        update_option($hcap_wpforo_new_topic_status_n, $hcap_wpforo_new_topic_status);
    } else {
        $hcap_wpforo_new_topic_status         = "off";
        update_option($hcap_wpforo_new_topic_status_n, $hcap_wpforo_new_topic_status);
    }

    if(isset($_POST[$hcap_wpforo_reply_status_n])){
        $hcap_wpforo_reply_status = $_POST[$hcap_wpforo_reply_status_n];
        update_option($hcap_wpforo_reply_status_n, $hcap_wpforo_reply_status);
    } else {
        $hcap_wpforo_reply_status         = "off";
        update_option($hcap_wpforo_reply_status_n, $hcap_wpforo_reply_status);
    }

    if(isset($_POST[$hcap_mc4wp_status_n])){
        $hcap_mc4wp_status         = $_POST[$hcap_mc4wp_status_n];
        update_option($hcap_mc4wp_status_n, $hcap_mc4wp_status);
    } else {
        $hcap_mc4wp_status         = "off";
        update_option($hcap_mc4wp_status_n, $hcap_mc4wp_status);
    }

    if(isset($_POST[$hcap_jetpack_cf_status_n])){
        $hcap_jetpack_cf_status = $_POST[$hcap_jetpack_cf_status_n];
        update_option($hcap_jetpack_cf_status_n, $hcap_jetpack_cf_status);
    } else {
        $hcap_jetpack_cf_status         = "off";
        update_option($hcap_jetpack_cf_status_n, $hcap_jetpack_cf_status);
    }

    if(isset($_POST[$hcap_subscribers_status_n])){
        $hcap_subscribers_status         = $_POST[$hcap_subscribers_status_n];
        update_option($hcap_subscribers_status_n, $hcap_subscribers_status);
    } else {
        $hcap_subscribers_status         = "off";
        update_option($hcap_subscribers_status_n, $hcap_subscribers_status);
    }

    echo '<div id="message" class="updated fade"><p>Settings Updated</p></div>';
} else {
    $hcap_api_key                       = get_option($hcap_api_key_n);
    $hcap_secret_key                    = get_option($hcap_secret_key_n);
    $hcap_nf_status                     = get_option($hcap_nf_status_n);
    $hcap_theme                         = get_option($hcap_theme_n);
    $hcap_size                          = get_option($hcap_size_n);
    $hcap_language                      = get_option($hcap_language_n);
    $hcap_cf7_status                    = get_option($hcap_cf7_status_n);
    $hcap_lf_status                     = get_option($hcap_lf_status_n);
    $hcap_rf_status                     = get_option($hcap_rf_status_n);
    $hcap_cmf_status                    = get_option($hcap_cmf_status_n);
    $hcap_lpf_status                    = get_option($hcap_lpf_status_n);
    $hcap_wc_login_status               = get_option($hcap_wc_login_status_n);
    $hcap_wc_reg_status                 = get_option($hcap_wc_reg_status_n);
    $hcap_wc_lost_pass_status           = get_option($hcap_wc_lost_pass_status_n);
    $hcap_wc_checkout_status            = get_option($hcap_wc_checkout_status_n);
    $hcap_bp_reg_status                 = get_option($hcap_bp_reg_status_n);
    $hcap_bp_create_group_status        = get_option($hcap_bp_create_group_status_n);
    $hcap_bbp_new_topic_status          = get_option($hcap_bbp_new_topic_status_n);
    $hcap_bbp_reply_status              = get_option($hcap_bbp_reply_status_n);
    $hcap_wpforo_new_topic_status       = get_option($hcap_wpforo_new_topic_status_n);
    $hcap_wpforo_reply_status           = get_option($hcap_wpforo_reply_status_n);
    $hcap_mc4wp_status                  = get_option($hcap_mc4wp_status_n);
    $hcap_jetpack_cf_status             = get_option($hcap_jetpack_cf_status_n);
    $hcap_subscribers_status            = get_option($hcap_subscribers_status_n);
}

?>
<div class="wrap">
<div id="poststuff">
    <div id="post-body">
        <div class="tnc-pdf-column-left">
            <div class="postbox">
                <fieldset>
                    <h3>hCaptcha Settings</h3>
                    <div class="inside">
					 <h3>In order to use <a href="https://hCaptcha.com/?r=wp">hCaptcha </a> please register <a href="https://hCaptcha.com/?r=wp">here</a> to get your site key and secret key.</h3>
                        <form method="post" action="">
                            <strong>hCaptcha Site Key</strong><br><br />
                            <input type="text" name="<?php echo $hcap_api_key_n; ?>" size="50" value="<?php echo $hcap_api_key; ?>" /><br><br />

                            <strong>hCaptcha Secret Key</strong><br><br />
                            <input type="password" name="<?php echo $hcap_secret_key_n; ?>" size="50" value="<?php echo $hcap_secret_key; ?>" /><br><br />
                            
                            <strong>hCaptcha Theme</strong><br><br />
                            <select name="<?php echo $hcap_theme_n; ?>" id="<?php echo $hcap_theme_n; ?>">
                                <option value="light" <?php if($hcap_theme == "light"){echo 'selected';} ?>>Light</option>
                                <option value="dark" <?php if($hcap_theme == "dark"){echo 'selected';} ?>>Dark</option>
                            </select><br><br />

                            <strong>hCaptcha Size</strong><br><br />
                            <select name="<?php echo $hcap_size_n; ?>" id="<?php echo $hcap_size_n; ?>">
                                <option value="normal" <?php if($hcap_size == "normal"){echo 'selected';} ?>>Normal</option>
                                <option value="compact" <?php if($hcap_size == "compact"){echo 'selected';} ?>>Compact</option>
                            </select><br><br />

                            <strong>Override Language Detection (optional)</strong><br><br />
                            <input type="text" name="<?php echo $hcap_language_n; ?>" size="50" value="<?php echo $hcap_language; ?>" /><br>
                            Info on <a href="https://hcaptcha.com/docs/languages" target="_blank">language codes</a><br><br />

                            <!-- Features -->
                            <strong>Enable/Disable Features</strong><br /><br />
                            <input type="checkbox" name="<?php echo $hcap_nf_status_n; ?>" <?php if($hcap_nf_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable Ninja Forms Addon</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_cf7_status_n; ?>" <?php if($hcap_cf7_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable Contact Form 7 Addon</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_lf_status_n; ?>" <?php if($hcap_lf_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on Login Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_rf_status_n; ?>" <?php if($hcap_rf_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on Register Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_cmf_status_n; ?>" <?php if($hcap_cmf_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on Comment Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_lpf_status_n; ?>" <?php if($hcap_lpf_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on Lost Password Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_wc_login_status_n; ?>" <?php if($hcap_wc_login_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on WooCommerce Login Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_wc_reg_status_n; ?>" <?php if($hcap_wc_reg_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on WooCommerce Registration Form</span><br /><br />

                            <input type="checkbox" name="<?php echo $hcap_wc_lost_pass_status_n; ?>" <?php if($hcap_wc_lost_pass_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on WooCommerce Lost Password Form</span><br /><br />

                            <input type="checkbox" name="<?php echo $hcap_wc_checkout_status_n; ?>" <?php if($hcap_wc_checkout_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on WooCommerce Checkout Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_bp_reg_status_n; ?>" <?php if($hcap_bp_reg_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on Buddypress Registration Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_bp_create_group_status_n; ?>" <?php if($hcap_bp_create_group_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on BuddyPress Create Group Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_bbp_new_topic_status_n; ?>" <?php if($hcap_bbp_new_topic_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on bbpress new topic Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_bbp_reply_status_n; ?>" <?php if($hcap_bbp_reply_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on bbpress reply Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_wpforo_new_topic_status_n; ?>" <?php if($hcap_wpforo_new_topic_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on WPForo new topic Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_wpforo_reply_status_n; ?>" <?php if($hcap_wpforo_reply_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on WPForo Reply Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_mc4wp_status_n; ?>" <?php if($hcap_mc4wp_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on Mailchimp for WP Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_jetpack_cf_status_n; ?>" <?php if($hcap_jetpack_cf_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on Jetpack Contact Form</span><br /><br />                             
                            <input type="checkbox" name="<?php echo $hcap_subscribers_status_n; ?>" <?php if($hcap_subscribers_status == "on"){echo "checked='checked'";} else { echo "";} ?>/> &nbsp;<span>Enable hCaptcha on Subscribers Form</span><br /><br />                             
                        
                            <p><input type="submit" value="Save hCaptcha Settings" class="button button-primary" name="submit" /></p>
                            <?php
                                wp_nonce_field( 'hcaptcha_settings', 'hcaptcha_settings_nonce' );
                            ?>
                        </form>
                    </div>
                </fieldset>
            </div>
        </div> <!-- tnc-column-left  -->
        </div> <!-- postbody -->
    </div><!--poststuff-->
</div><!--/.wrap-->
<style type="text/css">
    a{
        text-decoration: none;
    }
    #poststuff h3{
        border-bottom: 1px solid #f4f4f4;
        padding: 0;
        margin: 10px 0 20px 10px;
        padding-bottom: 15px;
    }
</style>
