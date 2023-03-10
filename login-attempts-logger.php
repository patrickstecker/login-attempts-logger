<?php
/*
Plugin Name: Login Attempts Logger
Description: Logs all login attempts with all available data in the database securely and displays the latest login attempts on a settings page.
Version: 1.2.0
Author: Patrick Stecker
Author URI: https://patrickstecker.com/
Plugin URI: https://github.com/patrickstecker/login-attempts-logger/
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Function to log login attempts
function log_login_attempt($username, $status, $ip_address, $user_agent) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'login_attempts';
    $current_time = current_time('mysql');
    $data = array(
        'username' => sanitize_text_field($username),
        'status' => sanitize_text_field($status),
        'ip_address' => sanitize_text_field($ip_address),
        'user_agent' => sanitize_text_field($user_agent),
        'time' => sanitize_text_field($current_time)
    );
    $format = array(
        '%s',
        '%s',
        '%s',
        '%s',
        '%s'
    );
    $wpdb->insert($table_name, $data, $format);
}

// Function to create login attempts table
function create_login_attempts_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'login_attempts';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        username varchar(50) NOT NULL,
        status varchar(20) NOT NULL,
        ip_address varchar(100) NOT NULL,
        user_agent text NOT NULL,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Create login attempts table on plugin activation
register_activation_hook(__FILE__, 'create_login_attempts_table');

// Log successful logins
add_action('wp_login', function ($username) {
    $status = 'success';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    log_login_attempt($username, $status, $ip_address, $user_agent);
});

// Log failed logins
add_action('wp_login_failed', function () {
    $status = 'failed';
    $username = isset($_POST['log']) ? sanitize_user($_POST['log']) : '(no username entered)';
    $ip_address = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
    log_login_attempt($username, $status, $ip_address, $user_agent);
});

// Add settings page to display latest login attempts
add_action('admin_menu', function () {
    add_submenu_page(
        'options-general.php',
        'Latest Login Attempts',
        'Login Attempts',
        'manage_options',
        'login-attempts',
        'display_login_attempts'
    );
});

add_action('admin_init', 'login_logger_settings');


function login_logger_settings() {
    add_settings_section('lal_auto_deletion_section', 'Automatic Log Deletion', null, 'login-attempts');

    // setting to turn automatic log deletion on / off
    add_settings_field('lal_delete_after_days_switch', 'Automatic Log Deletion', 'lalDaysSwitchHTML', 'login-attempts', 'lal_auto_deletion_section');
    register_setting('loginattemptsloggerplugin', 'lal_delete_after_days_switch', array('sanitize_callback' => 'sanitize_text_field', 'default' => ''));
    
    // configure after how many days logs should be deleted
    add_settings_field('lal_delete_after_days_days', 'Automatic Log Deletion Days', 'lalDaysDaysHTML', 'login-attempts', 'lal_auto_deletion_section');
    register_setting('loginattemptsloggerplugin', 'lal_delete_after_days_days', array('sanitize_callback' => 'sanitize_lal_delete_after_days_days', 'default' => '30'));
}

function sanitize_lal_delete_after_days_days ($input) {
    if ((!ctype_digit($input)) OR $input < 1) {
        add_settings_error('lal_delete_after_days_days', 'lal_delete_after_days_days_error', 'Please enter a number greater than 0.');
        return get_option('lal_delete_after_days_days', '30');
    }
    return $input;
}

function lalDaysSwitchHTML () { ?>
    <input type="checkbox" name="lal_delete_after_days_switch" value="1" <?php checked(get_option('lal_delete_after_days_switch'), '1') ?>>
    <p class="description">Automatically deletes logs after the specified amount of days in the setting below.</p>
<?php }

function lalDaysDaysHTML () { ?>
    <input type="number" name="lal_delete_after_days_days" value="<?php echo get_option('lal_delete_after_days_days') ?>" >
    <p class="description">Specifies after which amount of days logs should be deleted when the Automatic Log Deletion is turned on.</p>
<?php }

function delete_login_attempts_after_days($days) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'login_attempts';

    // Set the cutoff date for deleting old logs (in days)
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $days + 1 . ' days'));

    // Delete the old logs
    $wpdb->query("DELETE FROM $table_name WHERE time < '$cutoff_date'");
}

function lal_get_auto_log_delete_on_or_off () {
    if (get_option( 'lal_delete_after_days_switch' )) {
        return 'on';
    }
    if (!get_option( 'lal_delete_after_days_switch' )) {
        return 'off';
    }
    return 'UNDEFINED';
}

// Function to display latest login attempts on settings page
function display_login_attempts() {
    // first delete old entries if option enabled
    if (get_option('lal_delete_after_days_switch', '0' )) {
        delete_login_attempts_after_days(get_option('lal_delete_after_days_days', '30' ));
    }

    // now display settings and logs
    global $wpdb;
    $table_name = $wpdb->prefix . 'login_attempts';
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY time DESC LIMIT %d", 10));
    ?>
    <div class="wrap">
        <h1>Latest Login Attempts</h1>
        <form action="options.php" method="Post">
            <?php
                settings_fields('loginattemptsloggerplugin');
                do_settings_sections('login-attempts');
                submit_button();
            ?>
        </form>
        
        <h2>Attempts List</h2>
        <p class="description">Automatic log deletion after <?php echo esc_html(get_option( 'lal_delete_after_days_days' )) ?> day(s) is turned <strong><?php echo esc_html(lal_get_auto_log_delete_on_or_off()) ?></strong></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th>Username</th>
                <th>Status</th>
                <th>IP Address</th>
                <th>User Agent</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $result) : ?>
                <tr>
                    <td><?php echo esc_html($result->username); ?></td>
                    <td><?php echo esc_html($result->status); ?></td>
                    <td><?php echo esc_html($result->ip_address); ?></td>
                    <td><?php echo esc_html($result->user_agent); ?></td>
                    <td><?php echo esc_html($result->time); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
}

// Register a function to be executed when the plugin is deactivated
register_deactivation_hook(__FILE__, 'my_plugin_deactivation');

function my_plugin_deactivation() {
    global $wpdb;

    // Delete the custom database table
    $table_name = $wpdb->prefix . 'login_attempts';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    delete_option('lal_delete_after_days_days');
    delete_option('lal_delete_after_days_switch');
}
