<?php
/**
 * WordPress GitHub Updater
 * 
 * This class enables automatic updates for WordPress plugins hosted on GitHub.
 * It checks for new releases and integrates with WordPress's native update system.
 * 
 * @package DDID_Tools
 * @version 1.0.2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class WordPress_GitHub_Updater {
    
    /**
     * GitHub username
     * @var string
     */
    private $username;
    
    /**
     * GitHub repository name
     * @var string
     */
    private $repository;
    
    /**
     * Plugin slug
     * @var string
     */
    private $plugin_slug;
    
    /**
     * Plugin file path (relative to plugins directory)
     * @var string
     */
    private $plugin_file;
    
    /**
     * Current plugin version
     * @var string
     */
    private $current_version;
    
    /**
     * GitHub API response cache
     * @var object
     */
    private $github_response;
    
    /**
     * Access token for private repositories (optional)
     * @var string
     */
    private $access_token;
    
    /**
     * Constructor
     * 
     * @param string $plugin_file Plugin file path
     * @param string $github_username GitHub username
     * @param string $github_repo GitHub repository name
     * @param string $access_token GitHub access token (optional, for private repos)
     */
    public function __construct($plugin_file, $github_username, $github_repo, $access_token = '') {
        $this->plugin_file = plugin_basename($plugin_file);
        $this->username = $github_username;
        $this->repository = $github_repo;
        $this->plugin_slug = dirname($this->plugin_file);
        $this->access_token = $access_token;
        
        // Get current version from plugin data
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data($plugin_file);
        $this->current_version = $plugin_data['Version'];
        
        // Hook into WordPress
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'rename_github_folder'), 10, 4);
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
    }
    
    /**
     * Get information from GitHub API
     * 
     * @return object|bool GitHub release data or false on failure
     */
    private function get_github_data() {
        // Return cached response if available
        if (!empty($this->github_response)) {
            return $this->github_response;
        }
        
        // Build API URL for latest release
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->username,
            $this->repository
        );
        
        // Set up request arguments
        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
            'timeout' => 15,
        );
        
        // Add authorization header if access token is provided
        if (!empty($this->access_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }
        
        // Make API request
        $response = wp_remote_get($api_url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (empty($data)) {
            return false;
        }
        
        // Cache the response
        $this->github_response = $data;
        
        return $data;
    }
    
    /**
     * Check for plugin updates
     * 
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        // Check if transient has checked property
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get GitHub data
        $github_data = $this->get_github_data();
        
        if ($github_data === false) {
            return $transient;
        }
        
        // Get version from tag name (remove 'v' prefix if present)
        $github_version = $github_data->tag_name;
        if (strpos($github_version, 'v') === 0) {
            $github_version = substr($github_version, 1);
        }
        
        // Compare versions
        if (version_compare($this->current_version, $github_version, '<')) {
            // Get download URL (zipball)
            $download_url = isset($github_data->zipball_url) ? $github_data->zipball_url : '';
            
            // If access token is provided, add it to download URL
            if (!empty($this->access_token) && !empty($download_url)) {
                $download_url = add_query_arg('access_token', $this->access_token, $download_url);
            }
            
            // Build update object
            $plugin_update = new stdClass();
            $plugin_update->slug = $this->plugin_slug;
            $plugin_update->new_version = $github_version;
            $plugin_update->url = isset($github_data->html_url) ? $github_data->html_url : '';
            $plugin_update->package = $download_url;
            
            // Add to transient
            $transient->response[$this->plugin_file] = $plugin_update;
        }
        
        return $transient;
    }
    
    /**
     * Provide plugin information for update screen
     * 
     * @param false|object|array $result The result object or array
     * @param string $action The type of information being requested
     * @param object $args Plugin API arguments
     * @return object Plugin information
     */
    public function plugin_info($result, $action, $args) {
        // Check if this is our plugin
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        // Get GitHub data
        $github_data = $this->get_github_data();
        
        if ($github_data === false) {
            return $result;
        }
        
        // Get version from tag name
        $github_version = $github_data->tag_name;
        if (strpos($github_version, 'v') === 0) {
            $github_version = substr($github_version, 1);
        }
        
        // Build plugin info object
        $plugin_info = new stdClass();
        $plugin_info->name = isset($github_data->name) ? $github_data->name : $this->repository;
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = $github_version;
        $plugin_info->author = '<a href="https://github.com/' . $this->username . '">' . $this->username . '</a>';
        $plugin_info->homepage = 'https://github.com/' . $this->username . '/' . $this->repository;
        $plugin_info->requires = '5.0';
        $plugin_info->tested = get_bloginfo('version');
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = isset($github_data->published_at) ? $github_data->published_at : '';
        $plugin_info->sections = array(
            'description' => isset($github_data->body) ? $github_data->body : 'No description available.',
            'changelog' => isset($github_data->body) ? '<pre>' . $github_data->body . '</pre>' : 'No changelog available.',
        );
        $plugin_info->download_link = isset($github_data->zipball_url) ? $github_data->zipball_url : '';
        
        // Add access token to download link if provided
        if (!empty($this->access_token) && !empty($plugin_info->download_link)) {
            $plugin_info->download_link = add_query_arg('access_token', $this->access_token, $plugin_info->download_link);
        }
        
        return $plugin_info;
    }
    
    /**
     * Rename the extracted GitHub folder to match plugin slug
     * 
     * @param string $source File source location
     * @param string $remote_source Remote file source location
     * @param WP_Upgrader $upgrader WP_Upgrader instance
     * @param array $hook_extra Extra arguments passed to hooked filters
     * @return string Modified source location
     */
    public function rename_github_folder($source, $remote_source, $upgrader, $hook_extra = array()) {
        global $wp_filesystem;
        
        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_file) {
            return $source;
        }
        
        // Get the plugin folder name
        $plugin_folder = $this->plugin_slug;
        
        // Build the new source path
        $new_source = trailingslashit($remote_source) . trailingslashit($plugin_folder);
        
        // Rename the folder
        $wp_filesystem->move($source, $new_source);
        
        return $new_source;
    }
    
    /**
     * Perform actions after plugin update
     * 
     * @param bool $response Installation response
     * @param array $hook_extra Extra arguments passed to hooked filters
     * @param array $result Installation result
     * @return array Installation result
     */
    public function after_update($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        // Get the plugin destination
        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        // Reactivate plugin if it was active
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_file) {
            if (is_plugin_active($this->plugin_file)) {
                activate_plugin($this->plugin_file);
            }
        }
        
        return $result;
    }
}
