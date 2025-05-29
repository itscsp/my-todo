<?php
defined('ABSPATH') || exit;

class MyTodoPlugin {
    private $database;
    private $rest_api;
    private $admin;
    private $frontend;

    public function __construct() {
        $this->database = new MyTodoDatabase();
        $this->rest_api = new MyTodoRestApi();
        $this->admin = new MyTodoAdmin();
        $this->frontend = new MyTodoFrontend();

        add_action('init', array($this, 'init'));
        register_activation_hook(MY_TODO_PLUGIN_PATH . 'my-todo.php', array($this, 'activate'));
        register_deactivation_hook(MY_TODO_PLUGIN_PATH . 'my-todo.php', array($this, 'deactivate'));
    }

    public function init() {
        // Schedule daily event to move overdue tasks
        if (!wp_next_scheduled('my_todo_daily_check')) {
            wp_schedule_event(time(), 'daily', 'my_todo_daily_check');
        }
        add_action('my_todo_daily_check', array($this->database, 'move_overdue_tasks'));
    }

    public function activate() {
        $this->database->create_tables();
    }

    public function deactivate() {
        // wp_clear_scheduled_hook('my_todo_daily_check');
    }
}
