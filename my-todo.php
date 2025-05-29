<?php
/**
 * Plugin Name: My Todo
 * Description: A comprehensive todo management plugin
 * Version: 1.0.1
 * Author: Chethan S Poojary
 * Text Domain: my-todo
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('MY_TODO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MY_TODO_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required classes
require_once MY_TODO_PLUGIN_PATH . 'includes/class-my-todo-plugin.php';
require_once MY_TODO_PLUGIN_PATH . 'includes/class-my-todo-database.php';
require_once MY_TODO_PLUGIN_PATH . 'includes/class-my-todo-rest-api.php';
require_once MY_TODO_PLUGIN_PATH . 'includes/class-my-todo-admin.php';
require_once MY_TODO_PLUGIN_PATH . 'includes/class-my-todo-frontend.php';
require_once MY_TODO_PLUGIN_PATH . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Initialize the plugin
function mytodo_run_plugin() {
    $plugin = new MyTodoPlugin();
    PucFactory::buildUpdateChecker(
        'https://raw.githubusercontent.com/itscsp/my-todo/main/manifest.json',
        __FILE__,
        'my-todo'
    );
}

add_action('plugins_loaded', 'mytodo_run_plugin');
