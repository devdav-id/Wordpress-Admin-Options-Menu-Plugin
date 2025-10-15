<?php

/**
 * WordPress GitHub Plugin Updater
 * 
 * 1. Register custom headers (GitHub Plugin URI, GitHub Plugin Folder)
 * 2. Initialize updater class and read GitHub config
 * 3. Check GitHub API for newer versions
 * 4. Add WordPress update notifications
 * 5. Handle subfolder extraction during updates
 * 6. Force update checks and debug info
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * STEP 1: Register Custom Headers from main plugin file
 * ===================================================
 */
add_filter('extra_plugin_headers', function ($headers) {
	$headers['GitHub Plugin URI'] = 'GitHub Plugin URI';
	$headers['GitHub Plugin Folder'] = 'GitHub Plugin Folder';
	return $headers;
});

/**
 * STEP 2: Initialize Updater Class
 * ================================
 * Main class that reads GitHub config in plugin header and sets up WordPress hooks
 */
if (!class_exists('WordPress_GitHub_Updater')) {

	class WordPress_GitHub_Updater
	{

		private $plugin_file;
		private $plugin_slug;
		private $plugin_data;
		private $github_repo;
		private $github_folder;
		private $version;

		public function __construct($plugin_file)
		{
			$this->plugin_file = $plugin_file;
			$this->plugin_slug = plugin_basename($plugin_file);
			
			// Get plugin data including custom headers
			if (!function_exists('get_plugin_data')) {
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			}
			$this->plugin_data = get_plugin_data($plugin_file);

			// Read GitHub config from plugin headers
			$this->github_repo = $this->plugin_data['GitHub Plugin URI'];
			$this->github_folder = $this->plugin_data['GitHub Plugin Folder'];
			$this->version = $this->plugin_data['Version'];

			// set up wordpress hooks
			if ($this->github_repo) {
				add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
				add_filter('upgrader_source_selection', array($this, 'fix_source_folder'), 10, 4);
				add_action('admin_init', array($this, 'show_update_notification'));
			}
		}
		/**
		 * STEP 3: Check GitHub for Updates
		 * ===============================
		 * Compare local vs GitHub version and add to WordPress update system
		 */
		public function check_for_update($transient)
		{
			error_log("WordPress GitHub Plugin Updater: check_for_update called");
			error_log("WordPress GitHub Plugin Updater: Plugin = " . $this->plugin_slug);
			error_log("WordPress GitHub Plugin Updater: Version = " . $this->version);

			if (empty($transient->checked)) {
				return $transient;
			}

			$remote_version = $this->get_remote_version();
			error_log("WordPress GitHub Plugin Updater: Remote version = " . $remote_version);

			if (version_compare($this->version, $remote_version, '<')) {
				
				$update_data = array(
					'slug' => dirname($this->plugin_slug),
					'plugin' => $this->plugin_slug,
					'new_version' => $remote_version,
					'url' => 'https://github.com/' . $this->github_repo,
					'package' => $this->get_download_url(),
					'tested' => get_bloginfo('version'),
					'requires_php' => '7.4',
					'compatibility' => array()
				);

				$transient->response[$this->plugin_slug] = (object) $update_data;

				// Also add to checked array to ensure WordPress sees it
				if (!isset($transient->checked)) {
					$transient->checked = array();
				}
				$transient->checked[$this->plugin_slug] = $this->version;

			} else {
				// Remove from updates if no update needed
				unset($transient->response[$this->plugin_slug]);
			}

			return $transient;
		}

		/**
		 * STEP 4: Show Update Notifications
		 * ================================
		 */
		public function show_update_notification()
		{
			$update_plugins = get_site_transient('update_plugins');

			if (!empty($update_plugins) && isset($update_plugins->response[$this->plugin_slug])) {
				add_action('admin_notices', array($this, 'admin_notice_update'));
			}
		}

		// displays on wordpress backend
		public function admin_notice_update()
		{
			if (get_current_screen()->id === 'plugins') {
				$update_plugins = get_site_transient('update_plugins');
				if (isset($update_plugins->response[$this->plugin_slug])) {
					$update_info = $update_plugins->response[$this->plugin_slug];
					echo '<div class="notice notice-warning">';
					echo '<p><strong>Plugin Update Available:</strong> Version ' . $update_info->new_version . ' is available for ' . $this->plugin_data['Name'] . '</p>';
					echo '</div>';
				}
			}
		}

		private function get_remote_version()
		{
			// If no GitHub folder specified, look in root
			$file_path = $this->github_folder ? $this->github_folder . '/' : '';
			$file_path .= basename($this->plugin_file);
			
			$request = wp_remote_get('https://api.github.com/repos/' . $this->github_repo . '/contents/' . $file_path);

			if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
				$body = wp_remote_retrieve_body($request);
				$data = json_decode($body, true);

				if (isset($data['content'])) {
					$content = base64_decode($data['content']);

					if (preg_match('/Version:\s*(.+)/i', $content, $matches)) {
						return trim($matches[1]);
					}
				}
			}

			return $this->version;
		}

		private function get_download_url()
		{
			return 'https://github.com/' . $this->github_repo . '/archive/refs/heads/main.zip';
		}

		/**
		 * STEP 5: Fix Subfolder Extraction
		 * ================================
		 */
		public function fix_source_folder($source, $remote_source, $upgrader, $hook_extra)
		{
			if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_slug) {

				// Get repository name from GitHub URI
				$repo_parts = explode('/', $this->github_repo);
				$repo_name = end($repo_parts);
				
				// GitHub creates folder with repo-name-branch format
				$expected_folder = $repo_name . '-main';
				
				if ($this->github_folder) {
					// If subfolder specified, navigate to it
					$corrected_source = $remote_source . '/' . $expected_folder . '/' . $this->github_folder . '/';
				} else {
					// If no subfolder, use root of repository
					$corrected_source = $remote_source . '/' . $expected_folder . '/';
				}

				if (is_dir($corrected_source)) {
					return $corrected_source;
				} else {
					// Try to find the actual folder structure
					if (is_dir($remote_source)) {
						$dirs = scandir($remote_source);
						foreach ($dirs as $dir) {
							if ($dir !== '.' && $dir !== '..' && is_dir($remote_source . '/' . $dir)) {
								
								if ($this->github_folder) {
									// Check if this directory contains our plugin folder
									$potential_plugin_path = $remote_source . '/' . $dir . '/' . $this->github_folder . '/';
									if (is_dir($potential_plugin_path)) {
										return $potential_plugin_path;
									}
								} else {
									// Check if this directory contains the main plugin file
									$potential_plugin_path = $remote_source . '/' . $dir . '/';
									if (file_exists($potential_plugin_path . basename($this->plugin_file))) {
										return $potential_plugin_path;
									}
								}
							}
						}
					}
				}
			}

			return $source;
		}
	}
}

/**
 * STEP 6: Force Update Check Handler
 * =================================
 * Manual update trigger via URL parameter: ?force_github_check=1
 */
add_action('load-plugins.php', function () {
	if (current_user_can('update_plugins')) {
		if (isset($_GET['force_github_check'])) {
			delete_site_transient('update_plugins');
		}
	}
});

/**
 * STEP 7: Debug Admin Notice
 * =========================
 * Show updater status and GitHub version comparison
 */
add_action('admin_notices', function () {
	if (current_user_can('manage_options')) {
		$current_page = basename($_SERVER['PHP_SELF']);

		// Only show on plugins page
		if ($current_page == 'plugins.php') {
			
			// Get the main plugin file (assumes this updater is in includes/ folder)
			$plugin_file = dirname(dirname(__FILE__)) . '/' . basename(dirname(dirname(__FILE__))) . '.php';
			
			// If that doesn't exist, try common plugin file names
			if (!file_exists($plugin_file)) {
				$possible_files = array(
					dirname(dirname(__FILE__)) . '/wp-plugin-template.php',
					dirname(dirname(__FILE__)) . '/index.php',
					dirname(dirname(__FILE__)) . '/main.php'
				);
				
				foreach ($possible_files as $file) {
					if (file_exists($file)) {
						$plugin_file = $file;
						break;
					}
				}
			}

			if (file_exists($plugin_file)) {
				// Get plugin data
				if (!function_exists('get_plugin_data')) {
					require_once(ABSPATH . 'wp-admin/includes/plugin.php');
				}
				$plugin_data = get_plugin_data($plugin_file);
				$current_version = $plugin_data['Version'];
				$github_repo = $plugin_data['GitHub Plugin URI'];
				$github_folder = $plugin_data['GitHub Plugin Folder'];

				// Get GitHub version for comparison
				$github_version = 'Unable to fetch';
				if ($github_repo) {
					$file_path = $github_folder ? $github_folder . '/' : '';
					$file_path .= basename($plugin_file);
					
					$request = wp_remote_get('https://api.github.com/repos/' . $github_repo . '/contents/' . $file_path);
					if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
						$body = wp_remote_retrieve_body($request);
						$data = json_decode($body, true);
						if (isset($data['content'])) {
							$content = base64_decode($data['content']);
							if (preg_match('/Version:\s*(.+)/i', $content, $matches)) {
								$github_version = trim($matches[1]);
							}
						}
					}
				}

				// display on wordpress backend
				echo '<div class="notice notice-info is-dismissible">';
				echo '<p><strong>WordPress GitHub Plugin Updater Debug:</strong><br>';
				echo 'GitHub Repo: ' . ($github_repo ? $github_repo : 'NOT FOUND') . '<br>';
				echo 'GitHub Plugin Folder: ' . ($github_folder ? $github_folder : 'ROOT') . '<br>';
				echo 'Plugin Slug: ' . plugin_basename($plugin_file) . '<br>';
				echo '---------------<br>';
				echo 'GitHub Plugin Version: ' . $github_version . '<br>';
				echo 'WordPress Plugin Version: ' . $current_version . '<br>';
				echo '<a href="plugins.php?force_github_check=1" class="button">Force Update Check</a></p>';
				echo '</div>';
			}

			// Force update check for debugging
			if (isset($_GET['force_github_check'])) {
				echo '<div class="notice notice-warning">';
				echo '<p><strong>Forcing GitHub Update Check...</strong></p>';
				echo '</div>';

				delete_site_transient('update_plugins');
				wp_redirect(admin_url('plugins.php'));
				exit;
			}
		}
	}
});
