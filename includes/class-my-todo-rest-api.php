<?php
defined('ABSPATH') || exit;

class MyTodoRestApi {
    private $database;

    public function __construct() {
        $this->database = new MyTodoDatabase();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes() {
        // Register todos routes
        register_rest_route('my-todo/v1', '/todos', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_todos'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_todo'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));

        register_rest_route('my-todo/v1', '/todos/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_todo'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_todo'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_todo'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));

        // Register comments routes
        register_rest_route('my-todo/v1', '/comments', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_comment'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        register_rest_route('my-todo/v1', '/comments/(?P<todo_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_comments'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        // Debug nonce endpoint
        register_rest_route('my-todo/v1', '/debug-nonce', array(
            'methods' => 'GET',
            'callback' => array($this, 'debug_nonce'),
            'permission_callback' => '__return_true'
        ));
    }

    public function check_permissions($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        error_log('check_permissions: Nonce received: ' . ($nonce ?: 'none'));
        error_log('check_permissions: Is user logged in: ' . (is_user_logged_in() ? 'yes' : 'no'));
        error_log('check_permissions: Current user ID: ' . get_current_user_id());

        if (!$nonce) {
            error_log('check_permissions: No nonce provided');
            return new WP_Error('rest_cookie_invalid_nonce', 'No nonce provided', array('status' => 403));
        }

        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            error_log('check_permissions: Nonce verification failed for nonce: ' . $nonce);
            return new WP_Error('rest_cookie_invalid_nonce', 'Cookie check failed', array('status' => 403));
        }

        if (!is_user_logged_in()) {
            error_log('check_permissions: User not logged in');
            return new WP_Error('rest_not_logged_in', 'You must be logged in to access this resource', array('status' => 401));
        }

        return true;
    }

    public function debug_nonce($request) {
        return rest_ensure_response(array('nonce' => wp_create_nonce('wp_rest')));
    }

    public function get_todos($request) {
        global $wpdb;
        $table_todos = $this->database->get_table_todos();

        $status = $request->get_param('status');
        $where_clause = "WHERE user_id = %d";
        $params = array(get_current_user_id());

        if ($status) {
            $where_clause .= " AND status = %s";
            $params[] = $status;
        }

        $sql = "SELECT * FROM $table_todos $where_clause ORDER BY deadline_date ASC, created_date DESC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        error_log('get_todos: Query results: ' . print_r($results, true));

        return rest_ensure_response($results ?? []);
    }

    public function get_todo($request) {
        global $wpdb;
        $table_todos = $this->database->get_table_todos();

        $id = $request['id'];
        $sql = "SELECT * FROM $table_todos WHERE id = %d AND user_id = %d";
        $result = $wpdb->get_row($wpdb->prepare($sql, $id, get_current_user_id()));

        if (!$result) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }

        return rest_ensure_response($result);
    }

    public function create_todo($request) {
        global $wpdb;
        $table_todos = $this->database->get_table_todos();

        $title = sanitize_text_field($request->get_param('title'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $deadline_date = sanitize_text_field($request->get_param('deadline_date'));

        if (empty($title) || empty($deadline_date)) {
            return new WP_Error('missing_fields', 'Title and deadline date are required', array('status' => 400));
        }

        $result = $wpdb->insert(
            $table_todos,
            array(
                'title' => $title,
                'description' => $description,
                'deadline_date' => $deadline_date,
                'user_id' => get_current_user_id()
            ),
            array('%s', '%s', '%s', '%d')
        );

        if ($result === false) {
            return new WP_Error('create_failed', 'Failed to create todo', array('status' => 500));
        }

        return rest_ensure_response(array('id' => $wpdb->insert_id, 'message' => 'Todo created successfully'));
    }

    public function update_todo($request) {
        global $wpdb;
        $table_todos = $this->database->get_table_todos();

        $id = $request['id'];
        $title = sanitize_text_field($request->get_param('title'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $deadline_date = sanitize_text_field($request->get_param('deadline_date'));
        $status = sanitize_text_field($request->get_param('status'));

        if (empty($title) || empty($deadline_date)) {
            return new WP_Error('missing_fields', 'Title and deadline date are required', array('status' => 400));
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_todos WHERE id = %d AND user_id = %d",
            $id, get_current_user_id()
        ));

        if (!$existing) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }

        $result = $wpdb->update(
            $table_todos,
            array(
                'title' => $title,
                'description' => $description,
                'deadline_date' => $deadline_date,
                'status' => $status
            ),
            array('id' => $id, 'user_id' => get_current_user_id()),
            array('%s', '%s', '%s', '%s'),
            array('%d', '%d')
        );

        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update todo', array('status' => 500));
        }

        return rest_ensure_response(array('message' => 'Todo updated successfully'));
    }

    public function delete_todo($request) {
        global $wpdb;
        $table_todos = $this->database->get_table_todos();
        $table_comments = $this->database->get_table_comments();

        $id = $request['id'];

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_todos WHERE id = %d AND user_id = %d",
            $id, get_current_user_id()
        ));

        if (!$existing) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }

        $wpdb->delete($table_comments, array('todo_id' => $id), array('%d'));
        $result = $wpdb->delete(
            $table_todos,
            array('id' => $id, 'user_id' => get_current_user_id()),
            array('%d', '%d')
        );

        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete todo', array('status' => 500));
        }

        return rest_ensure_response(array('message' => 'Todo deleted successfully'));
    }

    public function get_comments($request) {
        global $wpdb;
        $table_todos = $this->database->get_table_todos();
        $table_comments = $this->database->get_table_comments();

        $todo_id = $request['todo_id'];

        $todo_exists = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_todos WHERE id = %d AND user_id = %d",
            $todo_id, get_current_user_id()
        ));

        if (!$todo_exists) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }

        $sql = "SELECT * FROM $table_comments WHERE todo_id = %d ORDER BY comment_date DESC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $todo_id));
        error_log('get_comments: Query results: ' . print_r($results, true));

        return rest_ensure_response($results ?? []);
    }

    public function create_comment($request) {
        global $wpdb;
        $table_todos = $this->database->get_table_todos();
        $table_comments = $this->database->get_table_comments();

        $todo_id = intval($request->get_param('todo_id'));
        $comment = sanitize_textarea_field($request->get_param('comment'));

        if (empty($comment)) {
            return new WP_Error('missing_comment', 'Comment is required', array('status' => 400));
        }

        $todo_exists = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_todos WHERE id = %d AND user_id = %d",
            $todo_id, get_current_user_id()
        ));

        if (!$todo_exists) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }

        $result = $wpdb->insert(
            $table_comments,
            array(
                'todo_id' => $todo_id,
                'comment' => $comment,
                'user_id' => get_current_user_id()
            ),
            array('%d', '%s', '%d')
        );

        if ($result === false) {
            return new WP_Error('create_failed', 'Failed to create comment', array('status' => 500));
        }

        return rest_ensure_response(array('id' => $wpdb->insert_id, 'message' => 'Comment created successfully'));
    }
}
