<?php
/**
 * Plugin Name: DDID Tools
 * Plugin URI: https://github.com/devdav-id/Wordpress-Admin-Options-Menu-Plugin
 * Description: A WordPress plugin that adds a DDID Tools page to the admin menu.
 * Version: 1.0.1
 * Author: devdav-id
 * Author URI: https://github.com/devdav-id
 * License: GPL2
 * Text Domain: ddid-tools
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin version
define('DDID_TOOLS_VERSION', '1.0.0');

// Include GitHub updater
require_once plugin_dir_path(__FILE__) . 'wordpress-github-updater.php';

// Initialize GitHub updater
if (is_admin()) {
    new WordPress_GitHub_Updater(
        __FILE__,
        'devdav-id',
        'Wordpress-Admin-Options-Menu-Plugin'
    );
}

/**
 * Register the admin menu
 */
function ddid_tools_add_admin_menu() {
    add_menu_page(
        'DDID Tools',                    // Page title
        'DDID Tools',                    // Menu title
        'manage_options',                // Capability required
        'ddid-tools',                    // Menu slug
        'ddid_tools_render_page',        // Callback function
        'dashicons-admin-tools',         // Icon
        80                               // Position
    );
}
add_action('admin_menu', 'ddid_tools_add_admin_menu');

/**
 * Render the admin page
 */
function ddid_tools_render_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="ddid-tools-container">
            <div class="ddid-tools-header">
                <h2>Welcome to DDID Tools</h2>
                <p>This is your site tools administration page.</p>
            </div>
            
            <div class="ddid-tools-content">
                <div class="ddid-tools-card">
                    <h3>Getting Started</h3>
                    <p>This page is currently under development. More tools and options will be added soon.</p>
                </div>
                
                <div class="ddid-tools-card">
                    <h3>Plugin Information</h3>
                    <ul>
                        <li><strong>Version:</strong> <?php echo esc_html(DDID_TOOLS_VERSION); ?></li>
                        <li><strong>Status:</strong> Active</li>
                        <li><strong>Purpose:</strong> Site administration tools</li>
                    </ul>
                </div>
                
                <div class="ddid-tools-card">
                    <h3>Placeholder Content</h3>
                    <p>This section demonstrates the admin page layout. Content and functionality can be added here as needed.</p>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .ddid-tools-container {
            margin-top: 20px;
        }
        
        .ddid-tools-header {
            background: #fff;
            padding: 20px;
            border-left: 4px solid #2271b1;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .ddid-tools-header h2 {
            margin-top: 0;
            color: #1d2327;
        }
        
        .ddid-tools-header p {
            margin-bottom: 0;
            color: #646970;
        }
        
        .ddid-tools-content {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }
        
        .ddid-tools-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .ddid-tools-card h3 {
            margin-top: 0;
            color: #1d2327;
            border-bottom: 2px solid #f0f0f1;
            padding-bottom: 10px;
        }
        
        .ddid-tools-card ul {
            list-style: none;
            padding-left: 0;
        }
        
        .ddid-tools-card ul li {
            padding: 5px 0;
            color: #3c434a;
        }
        
        .ddid-tools-card p {
            color: #646970;
            line-height: 1.6;
        }
    </style>
    <?php
}
