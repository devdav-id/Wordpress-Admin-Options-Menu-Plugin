# Wordpress-Admin-Options-Menu-Plugin
A WordPress plugin that adds a DDID Tools page to the admin menu.

## Description
This plugin initializes a backend admin page accessible through the WordPress admin menu. It creates a menu item named "DDID Tools" that navigates to its own option page.

## Installation
1. Upload the plugin files to the `/wp-content/plugins/ddid-tools` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to "DDID Tools" in the admin menu to access the tools page.

## Features
- Adds "DDID Tools" menu item to WordPress admin menu
- Dedicated option page with placeholder content
- Clean, modern admin interface design
- Easy to extend with additional functionality
- **Automatic GitHub Updates**: Plugin automatically checks for updates from this GitHub repository and allows one-click updates from the WordPress admin

## Usage
After activation, you'll find "DDID Tools" in your WordPress admin menu. Click on it to access the tools page.

## Development
The plugin consists of two main files:

### `ddid-tools.php`
The main plugin file containing:
- Plugin headers and metadata
- Admin menu registration
- Page rendering with placeholder content
- Inline styling for the admin page
- GitHub updater initialization

### `wordpress-github-updater.php`
The GitHub updater class that:
- Checks for new releases on GitHub via the GitHub API
- Integrates with WordPress's native plugin update system
- Displays update notifications in the WordPress admin
- Enables one-click plugin updates directly from GitHub releases

## How Updates Work
When you push a new release to this GitHub repository:
1. Tag the release with a version number (e.g., `v1.0.1` or `1.0.1`)
2. The plugin will automatically detect the new version
3. WordPress will show an update notification in the admin dashboard
4. Users can click "Update Now" to install the latest version directly from GitHub

**Note**: Make sure to update the version number in the plugin header and the `DDID_TOOLS_VERSION` constant when creating new releases.

## License
GPL2 
