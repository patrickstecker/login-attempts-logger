<?php
/*
Plugin Name: Login Attempts Logger
Description: Logs all login attempts with all available data in the database securely and displays the latest login attempts on a settings page.
Version: 1.1.1
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
        'username' => wp_slash($username),
        'status' => wp_slash($status),
        'ip_address' => wp_slash($ip_address),
        'user_agent' => wp_slash($user_agent),
        'time' => wp_slash($current_time)
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

// Function to display latest login attempts on settings page
function display_login_attempts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'login_attempts';
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY time DESC LIMIT %d", 10));
    ?>
    <div class="wrap">
        <h1>Latest Login Attempts</h1>
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

}
