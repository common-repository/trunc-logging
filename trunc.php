<?php
/**
 * Plugin Name: Trunc Logging
 * Plugin URI: http://trunc.org/wordpress/
 * Description: The Trunc Logging Plugin provides an audit log (syslog) and visibility to what is happening inside WordPress.
 * Author: Trunc
 * Version: 1.0.6
 * Author URI: https://trunc.org
 * License: GPLv3
 * Requires PHP: 7.0
*/


/**
 * @package   Trunc Logging
 * @author    Daniel Cid   <dcid@noc.org>
 * @copyright Since 2024 Trunc.
 * @license   GPLv3
 * @link      https://trunc.org/wordpress/
 */


/* Starting - only continue if from WP */
if(!function_exists('add_action') || !function_exists('wp_die'))
{
    exit(0);
}


define('TRUNCLOGGING', 'trunc_logging');
define('TRUNCLOGGING_VERSION', '1.0.6');


defined( 'TRUNCLOGGING_DIR' ) || define('TRUNCLOGGING_DIR', dirname(dirname(plugin_dir_path(__FILE__))) . '/uploads/logging/');
defined( 'TRUNCLOGGING_FILE' ) || define('TRUNCLOGGING_FILE', dirname(dirname(plugin_dir_path(__FILE__))) . '/uploads/logging/trunc_logging.php');


add_action('admin_init', 'trunc_logging_reg_settings');
add_action('admin_menu', 'trunc_logging_menu');

/* Hooks for the logging calls */
add_action('wp_login', 'trunc_logging_hook_wp_login', 20);
add_action('wp_login_failed', 'trunc_logging_hook_wp_login_failed', 20);
add_action('user_register', 'trunc_logging_hook_user_register', 20);
add_action('profile_update', 'trunc_logging_hook_profile_update', 20, 2);
add_action('switch_theme', 'trunc_logging_hook_switch_theme', 20);
add_action('retrieve_password', 'trunc_logging_hook_retrieve_password', 20);
add_action('xmlrpc_publish_post', 'trunc_logging_hook_xmlrpc_publish_post', 20);
add_action('publish_phone', 'trunc_logging_hook_publish_phone', 20);
add_action('publish_post', 'trunc_logging_hook_publish_post', 20);
add_action('publish_page', 'trunc_logging_hook_publish_page', 20);
add_action('private_to_published', 'trunc_logging_hook_private_to_published', 20);
//add_action('login_form_resetpass', 'trunc_logging_hook_login_form_resetpass', 20);
add_action('delete_user', 'trunc_logging_hook_delete_user', 20);
add_action('delete_post', 'trunc_logging_hook_delete_post', 20);
add_action('wp_trash_post', 'trunc_logging_hook_trash_post', 20);
add_action('create_category', 'trunc_logging_hook_create_category', 20);
add_action('activated_plugin', 'trunc_logging_hook_activated_plugin', 20);
add_action('deactivated_plugin', 'trunc_logging_hook_deactivated_plugin', 20);
add_action('admin_init', 'trunc_logging_hook_admin_init', 20);




function trunc_logging_get_visitor_ip()
{
    $newip = false;
    if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
    {
        if(filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
        {
            $newip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        }
    }

    if(!isset($_SERVER['REMOTE_ADDR'])) 
    {
        $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
    }

    if($newip !== false)
    {
        return($newip . "/" . $_SERVER['REMOTE_ADDR']);
    }

    return($_SERVER['REMOTE_ADDR']);
}


function trunc_logging_get_userinfo($username)
{
    $user_info = get_user_by( 'login', $username );
    if($user_info === false)
    {
        return(false);
    }
    $user_return = array('display_name' => $username, 'avatar' => null);
    if($user_info instanceof WP_User && isset($user_info->user_login)) 
    {
        $user_return['display_name'] = $user_info->display_name;
        $user_return['avatar'] = get_avatar( $user_info->ID, 20 );
        $user_return['role'] = $user_info->roles[0];
        
    }
    return($user_return);

}


function trunc_logging_store_msg($message, $severity = "NOTICE", $bypass_username = null)
{
    $visitor_ip = trunc_logging_get_visitor_ip();
    $current_user = wp_get_current_user();
    $current_time = date( 'Y-m-d H:i:s' );
    $username = "system_event";

    if($current_user instanceof WP_User && isset($current_user->user_login) && !empty($current_user->user_login))
    {
        if( $current_user->user_login != $current_user->display_name ){
            $username = sprintf( '%s(%s)', $current_user->user_login , str_replace(" ", "_", $current_user->display_name));
        } else {
            $username = sprintf( '%s', $current_user->user_login);
        }
    }
    else if($bypass_username != null)
    {
        $username = $bypass_username;
    }

    $final_log = sprintf('%s WPLogging: %s: %s: %s: %s: %s',
        $current_time,
        trim(str_replace(array("https://", "http://"), array("", ""), get_site_url())),
        $severity,
        $visitor_ip,
        $username,
        $message
    );

    if(!is_file(TRUNCLOGGING_FILE))
    {
        if(!is_dir(TRUNCLOGGING_DIR))
        {
            mkdir(TRUNCLOGGING_DIR);
        }
        touch(TRUNCLOGGING_DIR . "/index.html");
        file_put_contents(TRUNCLOGGING_FILE, "<?php exit(0);\n");
        file_put_contents(TRUNCLOGGING_FILE, sprintf('%s WPLogging: %s: %s: %s: %s: %s', $current_time, trim(str_replace(array("https://", "http://"), array("", ""), get_site_url())), "WARNING", $visitor_ip, $username, "Trunc Plugin Log removed. Re-creating it.\n"), FILE_APPEND);
    }
    file_put_contents(TRUNCLOGGING_FILE, $final_log."\n", FILE_APPEND);

    $current_logging_url = get_option('remote_logging_trunc');
    if($current_logging_url !== false)
    {
        $postargs = array('location' => trim(str_replace(array("https://", "http://"), array("", ""), get_site_url())) , 'source' => "WordPress_Plugin", "log" => $final_log);

        $response = wp_remote_post($current_logging_url, array('body' => $postargs ) );
    }

}


function trunc_logging_hook_create_category( $id=0 )
{
    $title = ( is_int($id) ? get_cat_name($id) : 'Unknown' );

    $message = 'WordPress Category created #'.$id.' ('.$title.')';
    trunc_logging_store_msg($message);
}

function trunc_logging_hook_delete_post($id = 0)
{
    $data = ( is_int($id) ? get_post($id) : FALSE );
    $title = 'no_title';

    if($data)
    {
        $title = htmlspecialchars($data->post_title);
    }

    trunc_logging_store_msg('WordPress Post deleted #'.$id.' ('.$title.')');
}

function trunc_logging_hook_trash_post($id = 0)
{
    $data = ( is_int($id) ? get_post($id) : FALSE );
    $title = 'no_title';

    if($data)
    {
        $title = htmlspecialchars($data->post_title);
    }

    trunc_logging_store_msg('WordPress Post trashed #'.$id.' ('.$title.')');
}


function trunc_logging_hook_delete_user( $id=0 )
{
    $data = ( is_int($id) ? get_userdata($id) : FALSE );
    $username = ( $data ? $data->display_name : 'invalid_user_name' );
    $loginname = ( $data ? $data->user_login : 'invalid_login_name' );

    trunc_logging_store_msg('User account deleted: '.$loginname.' #'.$id. ', display_name: ' . $username, 'ADMINISTRATION');
}

function trunc_logging_hook_private_to_published( $id=0 )
{
    $data = ( is_int($id) ? get_post($id) : FALSE );

    if( $data ){
        $title = $data->post_title;
        $p_type = ucwords($data->post_type);
    } else {
        $title = 'title_not_set';
        $p_type = 'Publication';
    }

    $message = 'WordPress ' . $p_type.' changed from private to public #'.$id.' ('.$title.')';
    trunc_logging_store_msg($message);
}

function trunc_logging_hook_publish( $id=0 )
{
    $data = (is_int($id) ? get_post($id) : FALSE );

    if($data)
    {
        $title = $data->post_title;
        $p_type = ucwords($data->post_type);
        $action = ( $data->post_date == $data->post_modified ? 'created' : 'updated' );
    } 
    else 
    {
        $title = 'title_not_set';
        $p_type = 'Post';
        $action = 'published';
    }

    $message = 'WordPress ' . $p_type.' was '.$action.' #'.$id.' ('.$title.')';
    trunc_logging_store_msg($message);
}

function trunc_logging_hook_publish_page( $id=0 )
{
    trunc_logging_hook_publish($id);
}

function trunc_logging_hook_publish_post( $id=0 )
{
    trunc_logging_hook_publish($id);
}

function trunc_logging_hook_publish_phone( $id=0 )
{
    trunc_logging_hook_publish($id);
}

function trunc_logging_hook_xmlrpc_publish_post( $id=0 )
{
    trunc_logging_hook_publish($id);
}

function trunc_logging_hook_retrieve_password( $username = 'user_not_set' )
{
    trunc_logging_store_msg('Password recovery attempt for username: '.$username);
}

function trunc_logging_hook_switch_theme( $theme_name = 'theme_name_not_set' )
{
    $message = 'WordPress Theme switched to: '.$theme_name;
    trunc_logging_store_msg($message, 'ADMINISTRATION');
}

function trunc_logging_hook_user_register( $id=0 )
{
    $data = ( is_int($id) ? get_userdata($id) : FALSE );
    $username = ( $data ? $data->display_name : 'Unknown' );
    $loginname = ( $data ? $data->user_login : 'unknown' );

    $message = 'New user account registered: '.$loginname.' #'.$id.' ('.$username.')';
    trunc_logging_store_msg($message, 'ADMINISTRATION');
}

function trunc_logging_hook_profile_update( $id=0, $orig_profile_data = false )
{
    $data = ( is_int($id) ? get_userdata($id) : FALSE );
    if(!$data || !$orig_profile_data)
    {
        return;
    }
 
    $userroles = implode(',', $data->roles);
    $username = $data->display_name;
    $useremail = $data->user_email;
    $userlogin = $data->user_login;

    $orig_userroles = implode(',', $orig_profile_data->roles);
    $orig_username = $orig_profile_data->display_name;
    $orig_useremail = $orig_profile_data->user_email;
    $orig_userlogin = $orig_profile_data->user_login;

    $emailmsg = "";
    if($useremail !== $orig_useremail)
    {
        $emailmsg = " New email: $useremail, Old email: $orig_useremail;";
    }

    $rolemsg = "";
    if($userroles !== $orig_userroles)
    {
        $rolemsg = " New roles: $userroles, Old roles: $orig_userroles;";
    }

    if(strlen($rolemsg) > 2 || strlen($emailmsg) > 2)
    {
        $message = 'Account profile updated: '.$userlogin.' #'.$id.' ('.$username.'):'.$emailmsg.''.$rolemsg;
        trunc_logging_store_msg($message, 'ADMINISTRATION');
    }
}


function trunc_logging_hook_wp_login($username = 'not_set')
{
    $message = 'User authentication success: '.$username;

    $userinfo = trunc_logging_get_userinfo($username);
    if($userinfo !== false)
    {
        $message = 'User authentication success. Username: '.$username. ', display_name:'.$userinfo['display_name'];
    }
    trunc_logging_store_msg($message, 'LOGIN', $username);
}
function trunc_logging_hook_wp_login_failed($username = 'not_set')
{
    $userinfo = trunc_logging_get_userinfo($username);
    if($userinfo === false)
    {
        $message = 'User authentication failed for non-existent user: '.$username;
    }
    else
    {
        $message = 'User authentication failed. Username: '.$username. ', display_name:'.$userinfo['display_name'];
    }
    trunc_logging_store_msg($message, 'WARNING', $username);
}


function trunc_logging_hook_deactivated_plugin($plugin_name = 'plugin_not_set', $network_activation = '')
{
    $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_name;
    if(!is_file($plugin_file))
    {
        return(0);
    }

    $pinfo = get_plugin_data($plugin_file);
    $pname = "plugin_name_not_set";
    $pversion = "v1.0";
    if(!empty($pinfo['Name']))
    {
        $pname = $pinfo['Name'];
    }
    if(!empty($pinfo['Version']))
    {
        $pversion = $pinfo['Version'];
    }

    $message = 'WordPress plugin deactivated. Plugin: '.htmlspecialchars($plugin_name). ', name:'. htmlspecialchars($pname). ' ('.$pversion.')';
    trunc_logging_store_msg($message, 'ADMINISTRATION');
}


function trunc_logging_hook_activated_plugin($plugin_name = 'plugin_not_set', $network_activation = '')
{
    $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_name;
    if(!is_file($plugin_file))
    {
        return(0);
    }

    $pinfo = get_plugin_data($plugin_file);
    $pname = "plugin_name_not_set";
    $pversion = "v1.0";
    if (!empty($pinfo['Name']))
    {
        $pname = $pinfo['Name'];
    }
    if (!empty($pinfo['Version']))
    {
        $pversion = $pinfo['Version'];
    }

    $message = 'WordPress plugin activated. Plugin: '.htmlspecialchars($plugin_name). ', name:'. htmlspecialchars($pname). ' ('.$pversion.')';
    trunc_logging_store_msg($message, 'ADMINISTRATION');
}
    
function trunc_logging_hook_admin_init()
{
}


function trunc_logging_reg_settings()
{
    //register_setting( 'trunc_logging_settings', 'remote_logging_trunc');
}

function trunc_logging_menu()
{
    // Add main menu link.
    add_menu_page(
        'Trunc Logging',
        'Trunc Logging',
        'administrator',
        'trunc_logging_settings',
        'trunc_logging_settings_display',
        'dashicons-admin-network'
    );
}


function trunc_logging_settings_display()
{
    $error_msg = false;
    $success_msg = false;
    $current_logging_url = get_option('remote_logging_trunc');
    if(!current_user_can('manage_options'))
    {   
        wp_die(__('Access denied.'));
    }

    $nonce = wp_create_nonce('truncupdate');
    if(isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['remote_logging_trunc']))
    {
        if(!isset( $_POST['trunc_nonce'] ) || !wp_verify_nonce($_POST['trunc_nonce'], 'truncupdate'))
        {
            wp_die(__('Invalid nonce. Please refresh the page and try again.'));
        }
        else
        {
            $_POST['remote_logging_trunc'] = trim($_POST['remote_logging_trunc']);
            if(preg_match('/^https:\/\/log-receiver[0-9][0-9]*-[a-z-]*\.trunc.org\/log_ingestion\/\?web_logging=[a-zA-Z0-9]{10,100}$/', $_POST['remote_logging_trunc']))
            {
                $response = wp_remote_post($_POST['remote_logging_trunc']."&testonly");
                if(isset($response['body']) && strpos($response['body'], '"Valid API Key') !== false)
                {
                    $currentval = get_option('remote_logging_trunc');
                    if($currentval)
                    {
                        if($currentval === $_POST['remote_logging_trunc'])
                        {
                            $error_msg = "No change made. Keeping the original remote logging URL.";
                        }
                        else if(update_option('remote_logging_trunc', $_POST['remote_logging_trunc']))
                        {
                            $success_msg = "Remote logging enabled. You should start seeing logs soon on your Trunc dashboard.";
                            $current_logging_url = $_POST['remote_logging_trunc'];
                        }
                        else
                        {
                            $error_msg = "Unable to save settings to the database. Internal error.";
                        }
                    }
                    else
                    {
                        add_option('remote_logging_trunc', $_POST['remote_logging_trunc']);
                        $success_msg = "Remote logging enabled. You should start seeing logs soon on your Trunc dashboard.";
                        $current_logging_url = $_POST['remote_logging_trunc'];
                    }
                }
                else
                {
                    $error_msg = "Invalid remote Trunc logging URL. Unable to verify that the API key is valid. Please go <a href='https://my.trunc.org/dashboard?page=logging&subpage=settings'>here</a> to get the correct one.";
                }
            }
            else
            {
                $error_msg = "Invalid remote Trunc logging URL. Please go <a href='https://my.trunc.org/dashboard?page=logging&subpage=settings'>here</a> to get the correct one.";
            }
        }
    }
//   delete_option('remote_logging_trunc');

    ?>
    <div class="wrap">
    <?php if($success_msg !== false) { ?>
        <div class="updated notice">
        <p><?php echo $success_msg; ?></p>
        </div>
    <?php } ?>
    <?php if($error_msg !== false) { ?>
        <div class="error notice">
        <p><?php echo $error_msg; ?></p>
        </div>
    <?php } ?>

    <h2>Trunc WordPress Logging</h2>
    <hr />
    <p>The Trunc WordPress Logging Plugin brings visibility to what is happening inside WordPress. It creates, stores and keeps track of all the internal activity on your site. It includes successfu; logins, logouts, failed logins, activated plugins, posts modified, and much more. </p>


    <div class="card">
    <form method="post">

    <?php 
    settings_fields( 'trunc_logging_settings' ); 
    do_settings_sections( 'trunc_logging_settings' ); 
    ?>
    <h3>Remote Logging at Trunc</h3>
    <?php if($current_logging_url === false) { ?>
        <p>In addition to the local logging, this plugin can also send your logs remotely to <a href="https://trunc.org">Trunc</a> for long term analysis, alerting storage and correlation.</p>
    <?php } else { ?>
        <p>Enabled. Forwarding your logs to Trunc.org</p>
    <?php } ?>
    <input type="text" name="remote_logging_trunc" value="<?php echo esc_attr( get_option('remote_logging_trunc') ); ?>" placeholder="Trunc URL" />
    <input type="hidden" name="trunc_nonce" value="<?php echo $nonce;?>" />
    <?php submit_button(); ?>
    </form>
    </div>

    <br /><br />
    <h1>WordPress Activity Logging</h1>
    <hr />
    <p class="description">Watch your logs in real time here.</p>

    <?php
    require_once(dirname(__FILE__) . "/classes/trunc_logging_view_logs.php");
    $myListTable = new Trunc_Logging_Table();
    $myListTable->prepare_items(); 

    ?>
    <form method="post">
    <input type="hidden" name="page" value="trunc_logging_settings_search" />
    <?php
        $myListTable->search_box('search', 'search_id');
    ?>
    </form>
    <style type="text/css">
    .wp-list-table .column-date { width: 10%; }
    .wp-list-table .column-user { width: 10%; }
    .wp-list-table .column-ip_address { width: 10%; }
    .wp-list-table .column-log { width: 50%; }
    </style>
    <?php
    $myListTable->display(); 
    ?>

    </div>
    <?php
}
