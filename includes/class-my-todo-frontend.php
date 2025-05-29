<?php
defined('ABSPATH') || exit;

class MyTodoFrontend {
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('my_todo', array($this, 'my_todo_shortcode'));
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('mytodo-style', MY_TODO_PLUGIN_URL . 'assets/css/style.css');
        wp_enqueue_script('mytodo-script', MY_TODO_PLUGIN_URL . 'assets/js/script.js', array('jquery'), null, true);
        wp_localize_script('mytodo-script', 'myTodoAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('my_todo_nonce'),
            'rest_url' => esc_url_raw(rest_url()) // Ensure rest_url is passed
        ));
    }

    public function my_todo_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'show_completed' => 'false'
        ), $atts);

        if (!is_user_logged_in()) {
            return '<p>Please log in to view your todos.</p>';
        }

        ob_start();
        ?>
        <div id="my-todo-frontend">
            <div class="my-todo-frontend-container">
                <h3>My Todos</h3>
                
                <!-- Add Todo Form -->
                <div class="my-todo-add-form">
                    <h4>Add New Todo</h4>
                    <form id="frontend-add-todo-form">
                        <div class="form-row">
                            <label for="frontend-todo-title">Title:</label>
                            <input type="text" id="frontend-todo-title" name="title" required>
                        </div>
                        <div class="form-row">
                            <label for="frontend-todo-description">Description:</label>
                            <textarea id="frontend-todo-description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-row">
                            <label for="frontend-todo-deadline">Deadline:</label>
                            <input type="date" id="frontend-todo-deadline" name="deadline_date" required>
                        </div>
                        <button type="submit">Add Todo</button>
                    </form>
                </div>

                <!-- Todos List -->
                <div class="my-todo-list">
                    <h4>Your Todos</h4>
                    <div id="frontend-todos-list">
                        <!-- Todos will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
