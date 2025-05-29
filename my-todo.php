<?php
/**
 * Plugin Name: My Todo
 * Description: A comprehensive todo management plugin
 * Version: 1.0.0
 * Author: Chethan S Poojary
 * Text Domain: my-todo
 */

defined( 'ABSPATH' ) || exit;


// Define plugin constants
define('MY_TODO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MY_TODO_PLUGIN_PATH', plugin_dir_path(__FILE__));

class MyTodoPlugin
{
    private $table_todos;
    private $table_comments;

    public function __construct()
    {
        global $wpdb;
        $this->table_todos = $wpdb->prefix . 'my_todos';
        $this->table_comments = $wpdb->prefix . 'my_todo_comments';

        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Schedule daily event to move overdue tasks
        if (!wp_next_scheduled('my_todo_daily_check')) {
            wp_schedule_event(time(), 'daily', 'my_todo_daily_check');
        }
        add_action('my_todo_daily_check', array($this, 'move_overdue_tasks'));
    }

    public function activate()
    {
        $this->create_tables();
    }

    public function deactivate()
    {
        // wp_clear_scheduled_hook('my_todo_daily_check');
    }

    private function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create todos table
        $sql_todos = "CREATE TABLE $this->table_todos (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            deadline_date date NOT NULL,
            status enum('pending', 'completed') DEFAULT 'pending',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            user_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY deadline_date (deadline_date)
        ) $charset_collate;";

        // Create comments table
        $sql_comments = "CREATE TABLE $this->table_comments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            todo_id mediumint(9) NOT NULL,
            comment text NOT NULL,
            comment_date datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY todo_id (todo_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_todos);
        dbDelta($sql_comments);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'My Todo',
            'My Todo',
            'manage_options',
            'my-todo',
            array($this, 'admin_page'),
            'dashicons-list-view',
            30
        );
    }

    public function admin_page()
    {
        ?>
        <div class="wrap">
            <h1>My Todo Manager</h1>
            <div id="my-todo-app">
                <div class="my-todo-container">
                    <!-- Add Todo Form -->
                    <div class="my-todo-form-container">
                        <h2>Add New Todo</h2>
                        <form id="add-todo-form">
                            <table class="form-table">
                                <tr>
                                    <th><label for="todo-title">Title *</label></th>
                                    <td><input type="text" id="todo-title" name="title" required class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="todo-description">Description</label></th>
                                    <td><textarea id="todo-description" name="description" rows="4" class="large-text"></textarea></td>
                                </tr>
                                <tr>
                                    <th><label for="todo-deadline">Deadline Date *</label></th>
                                    <td><input type="date" id="todo-deadline" name="deadline_date" required></td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button-primary">Add Todo</button>
                            </p>
                        </form>
                    </div>

                    <!-- Todos List -->
                    <div class="my-todo-list-container">
                        <h2>My Todos</h2>
                        <div class="tablenav top">
                            <div class="alignleft actions">
                                <select id="filter-status">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                </select>
                                <button type="button" class="button" onclick="filterTodos()">Filter</button>
                            </div>
                        </div>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="todos-list">
                                <!-- Todos will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Todo Modal -->
        <div id="edit-todo-modal" class="my-todo-modal" style="display: none;">
            <div class="my-todo-modal-content">
                <span class="my-todo-close">&times;</span>
                <h2>Edit Todo</h2>
                <form id="edit-todo-form">
                    <input type="hidden" id="edit-todo-id" name="id">
                    <table class="form-table">
                        <tr>
                            <th><label for="edit-todo-title">Title *</label></th>
                            <td><input type="text" id="edit-todo-title" name="title" required class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="edit-todo-description">Description</label></th>
                            <td><textarea id="edit-todo-description" name="description" rows="4" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="edit-todo-deadline">Deadline Date *</label></th>
                            <td><input type="date" id="edit-todo-deadline" name="deadline_date" required></td>
                        </tr>
                        <tr>
                            <th><label for="edit-todo-status">Status</label></th>
                            <td>
                                <select id="edit-todo-status" name="status">
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button-primary">Update Todo</button>
                        <button type="button" class="button" onclick="closeEditModal()">Cancel</button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Comments Modal -->
        <div id="comments-modal" class="my-todo-modal" style="display: none;">
            <div class="my-todo-modal-content">
                <span class="my-todo-close">&times;</span>
                <h2>Todo Comments</h2>
                <div id="todo-comments-list"></div>
                <form id="add-comment-form">
                    <input type="hidden" id="comment-todo-id" name="todo_id">
                    <table class="form-table">
                        <tr>
                            <th><label for="new-comment">Add Comment</label></th>
                            <td><textarea id="new-comment" name="comment" rows="3" class="large-text" required></textarea></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button-primary">Add Comment</button>
                        <button type="button" class="button" onclick="closeCommentsModal()">Close</button>
                    </p>
                </form>
            </div>
        </div>

        <style>
        .my-todo-container {
            max-width: 1200px;
            margin: 20px 0;
        }
        .my-todo-form-container {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .my-todo-list-container {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .my-todo-modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .my-todo-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        .my-todo-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 10px;
        }
        .my-todo-close:hover {
            color: black;
        }
        .todo-status-pending {
            color: #d63638;
        }
        .todo-status-completed {
            color: #00a32a;
        }
        .todo-overdue {
            background-color: #fef7f1;
        }
        .comment-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
            margin-bottom: 10px;
        }
        .comment-date {
            font-size: 12px;
            color: #666;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            loadTodos();

            // Add todo form submission
            $('#add-todo-form').on('submit', function(e) {
                e.preventDefault();
                addTodo();
            });

            // Edit todo form submission
            $('#edit-todo-form').on('submit', function(e) {
                e.preventDefault();
                updateTodo();
            });

            // Add comment form submission
            $('#add-comment-form').on('submit', function(e) {
                e.preventDefault();
                addComment();
            });

            // Close modals when clicking the X
            $('.my-todo-close').on('click', function() {
                $(this).closest('.my-todo-modal').hide();
            });

            // Close modals when clicking outside
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('my-todo-modal')) {
                    $('.my-todo-modal').hide();
                }
            });
        });

        function loadTodos() {
            jQuery.ajax({
                url: '<?php echo rest_url('my-todo/v1/todos'); ?>',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(data) {
                    displayTodos(data);
                },
                error: function(xhr, status, error) {
                    alert('Error loading todos: ' + error);
                }
            });
        }

        function displayTodos(todos) {
            var html = '';
            var today = new Date().toISOString().split('T')[0];
            
            todos.forEach(function(todo) {
                var statusClass = 'todo-status-' + todo.status;
                var rowClass = (todo.deadline_date < today && todo.status === 'pending') ? 'todo-overdue' : '';
                
                html += '<tr class="' + rowClass + '">';
                html += '<td><strong>' + todo.title + '</strong></td>';
                html += '<td>' + (todo.description || '') + '</td>';
                html += '<td>' + todo.deadline_date + '</td>';
                html += '<td><span class="' + statusClass + '">' + todo.status.charAt(0).toUpperCase() + todo.status.slice(1) + '</span></td>';
                html += '<td>';
                html += '<button class="button button-small" onclick="editTodo(' + todo.id + ')">Edit</button> ';
                html += '<button class="button button-small" onclick="viewComments(' + todo.id + ')">Comments</button> ';
                html += '<button class="button button-small button-link-delete" onclick="deleteTodo(' + todo.id + ')">Delete</button>';
                html += '</td>';
                html += '</tr>';
            });
            
            jQuery('#todos-list').html(html);
        }

        function addTodo() {
            var formData = {
                title: jQuery('#todo-title').val(),
                description: jQuery('#todo-description').val(),
                deadline_date: jQuery('#todo-deadline').val()
            };

            jQuery.ajax({
                url: '<?php echo rest_url('my-todo/v1/todos'); ?>',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                data: formData,
                success: function(data) {
                    jQuery('#add-todo-form')[0].reset();
                    loadTodos();
                    alert('Todo added successfully!');
                },
                error: function(xhr, status, error) {
                    alert('Error adding todo: ' + error);
                }
            });
        }

        function editTodo(id) {
            jQuery.ajax({
                url: '<?php echo rest_url('my-todo/v1/todos/'); ?>' + id,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(data) {
                    jQuery('#edit-todo-id').val(data.id);
                    jQuery('#edit-todo-title').val(data.title);
                    jQuery('#edit-todo-description').val(data.description);
                    jQuery('#edit-todo-deadline').val(data.deadline_date);
                    jQuery('#edit-todo-status').val(data.status);
                    jQuery('#edit-todo-modal').show();
                },
                error: function(xhr, status, error) {
                    alert('Error loading todo: ' + error);
                }
            });
        }

        function updateTodo() {
            var id = jQuery('#edit-todo-id').val();
            var formData = {
                title: jQuery('#edit-todo-title').val(),
                description: jQuery('#edit-todo-description').val(),
                deadline_date: jQuery('#edit-todo-deadline').val(),
                status: jQuery('#edit-todo-status').val()
            };

            jQuery.ajax({
                url: '<?php echo rest_url('my-todo/v1/todos/'); ?>' + id,
                method: 'PUT',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                data: formData,
                success: function(data) {
                    jQuery('#edit-todo-modal').hide();
                    loadTodos();
                    alert('Todo updated successfully!');
                },
                error: function(xhr, status, error) {
                    alert('Error updating todo: ' + error);
                }
            });
        }

        function deleteTodo(id) {
            if (confirm('Are you sure you want to delete this todo?')) {
                jQuery.ajax({
                    url: '<?php echo rest_url('my-todo/v1/todos/'); ?>' + id,
                    method: 'DELETE',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                    },
                    success: function(data) {
                        loadTodos();
                        alert('Todo deleted successfully!');
                    },
                    error: function(xhr, status, error) {
                        alert('Error deleting todo: ' + error);
                    }
                });
            }
        }

        function viewComments(todoId) {
            jQuery('#comment-todo-id').val(todoId);
            loadComments(todoId);
            jQuery('#comments-modal').show();
        }

        function loadComments(todoId) {
            jQuery.ajax({
                url: '<?php echo rest_url('my-todo/v1/comments/'); ?>' + todoId,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(data) {
                    displayComments(data);
                },
                error: function(xhr, status, error) {
                    alert('Error loading comments: ' + error);
                }
            });
        }

        function displayComments(comments) {
            var html = '';
            if (comments.length === 0) {
                html = '<p>No comments yet.</p>';
            } else {
                comments.forEach(function(comment) {
                    html += '<div class="comment-item">';
                    html += '<div>' + comment.comment + '</div>';
                    html += '<div class="comment-date">' + comment.comment_date + '</div>';
                    html += '</div>';
                });
            }
            jQuery('#todo-comments-list').html(html);
        }

        function addComment() {
            var formData = {
                todo_id: jQuery('#comment-todo-id').val(),
                comment: jQuery('#new-comment').val()
            };

            jQuery.ajax({
                url: '<?php echo rest_url('my-todo/v1/comments'); ?>',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                data: formData,
                success: function(data) {
                    jQuery('#new-comment').val('');
                    loadComments(formData.todo_id);
                    alert('Comment added successfully!');
                },
                error: function(xhr, status, error) {
                    alert('Error adding comment: ' + error);
                }
            });
        }

        function filterTodos() {
            var status = jQuery('#filter-status').val();
            var url = '<?php echo rest_url('my-todo/v1/todos'); ?>';
            if (status) {
                url += '?status=' + status;
            }
            
            jQuery.ajax({
                url: url,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(data) {
                    displayTodos(data);
                },
                error: function(xhr, status, error) {
                    alert('Error filtering todos: ' + error);
                }
            });
        }

        function closeEditModal() {
            jQuery('#edit-todo-modal').hide();
        }

        function closeCommentsModal() {
            jQuery('#comments-modal').hide();
        }
        </script>
        <?php
    }

    public function register_rest_routes()
    {
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
    }

    public function check_permissions()
    {
        return current_user_can('manage_options');
    }

    // Todo CRUD operations
    public function get_todos($request)
    {
        global $wpdb;
        
        $status = $request->get_param('status');
        $where_clause = "WHERE user_id = %d";
        $params = array(get_current_user_id());
        
        if ($status) {
            $where_clause .= " AND status = %s";
            $params[] = $status;
        }
        
        $sql = "SELECT * FROM $this->table_todos $where_clause ORDER BY deadline_date ASC, created_date DESC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        return rest_ensure_response($results);
    }

    public function get_todo($request)
    {
        global $wpdb;
        
        $id = $request['id'];
        $sql = "SELECT * FROM $this->table_todos WHERE id = %d AND user_id = %d";
        $result = $wpdb->get_row($wpdb->prepare($sql, $id, get_current_user_id()));
        
        if (!$result) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }
        
        return rest_ensure_response($result);
    }

    public function create_todo($request)
    {
        global $wpdb;
        
        $title = sanitize_text_field($request->get_param('title'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $deadline_date = sanitize_text_field($request->get_param('deadline_date'));
        
        if (empty($title) || empty($deadline_date)) {
            return new WP_Error('missing_fields', 'Title and deadline date are required', array('status' => 400));
        }
        
        $result = $wpdb->insert(
            $this->table_todos,
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

    public function update_todo($request)
    {
        global $wpdb;
        
        $id = $request['id'];
        $title = sanitize_text_field($request->get_param('title'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $deadline_date = sanitize_text_field($request->get_param('deadline_date'));
        $status = sanitize_text_field($request->get_param('status'));
        
        if (empty($title) || empty($deadline_date)) {
            return new WP_Error('missing_fields', 'Title and deadline date are required', array('status' => 400));
        }
        
        // Check if todo exists and belongs to current user
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $this->table_todos WHERE id = %d AND user_id = %d",
            $id, get_current_user_id()
        ));
        
        if (!$existing) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }
        
        $result = $wpdb->update(
            $this->table_todos,
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

    public function delete_todo($request)
    {
        global $wpdb;
        
        $id = $request['id'];
        
        // Check if todo exists and belongs to current user
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $this->table_todos WHERE id = %d AND user_id = %d",
            $id, get_current_user_id()
        ));
        
        if (!$existing) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }
        
        // Delete associated comments first
        $wpdb->delete($this->table_comments, array('todo_id' => $id), array('%d'));
        
        // Delete todo
        $result = $wpdb->delete(
            $this->table_todos,
            array('id' => $id, 'user_id' => get_current_user_id()),
            array('%d', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete todo', array('status' => 500));
        }
        
        return rest_ensure_response(array('message' => 'Todo deleted successfully'));
    }

    // Comment operations
    public function get_comments($request)
    {
        global $wpdb;
        
        $todo_id = $request['todo_id'];
        
        // Verify todo belongs to current user
        $todo_exists = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $this->table_todos WHERE id = %d AND user_id = %d",
            $todo_id, get_current_user_id()
        ));
        
        if (!$todo_exists) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }
        
        $sql = "SELECT * FROM $this->table_comments WHERE todo_id = %d ORDER BY comment_date DESC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $todo_id));
        
        return rest_ensure_response($results);
    }

    public function create_comment($request)
    {
        global $wpdb;
        
        $todo_id = intval($request->get_param('todo_id'));
        $comment = sanitize_textarea_field($request->get_param('comment'));
        
        if (empty($comment)) {
            return new WP_Error('missing_comment', 'Comment is required', array('status' => 400));
        }
        
        // Verify todo belongs to current user
        $todo_exists = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $this->table_todos WHERE id = %d AND user_id = %d",
            $todo_id, get_current_user_id()
        ));
        
        if (!$todo_exists) {
            return new WP_Error('todo_not_found', 'Todo not found', array('status' => 404));
        }
        
        $result = $wpdb->insert(
            $this->table_comments,
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

    public function move_overdue_tasks()
    {
        global $wpdb;
        
        $today = current_time('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
        
        // Get all overdue pending tasks
        $overdue_todos = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $this->table_todos WHERE deadline_date < %s AND status = 'pending'",
            $today
        ));
        
        foreach ($overdue_todos as $todo) {
            // Add a system comment about the task being moved
            $comment = "Task automatically moved from {$todo->deadline_date} to {$today} due to missed deadline.";
            
            $wpdb->insert(
                $this->table_comments,
                array(
                    'todo_id' => $todo->id,
                    'comment' => $comment,
                    'user_id' => $todo->user_id
                ),
                array('%d', '%s', '%d')
            );
            
            // Update the deadline to today
            $wpdb->update(
                $this->table_todos,
                array('deadline_date' => $today),
                array('id' => $todo->id),
                array('%s'),
                array('%d')
            );
        }
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook != 'toplevel_page_my-todo') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'myTodoAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('my_todo_nonce')
        ));
    }

    public function enqueue_frontend_scripts()
    {
        $plugin_url = plugin_dir_url(__FILE__);
        wp_enqueue_script('jquery');
         wp_enqueue_style('mytodo-style', $plugin_url . 'assets/css/style.css');
        // wp_enqueue_script('mytodo-script', $plugin_url . 'assets/js/script.js', array('jquery'), null, true);
    }
}

new MyTodoPlugin();

require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;


// Initialize the plugin
function mytodo_run_plugin() {
    
    PucFactory::buildUpdateChecker(
        'https://raw.githubusercontent.com/itscsp/my-todo/main/manifest.json',
        __FILE__,
        'my-todo'
    );
}

add_action( 'plugins_loaded', 'mytodo_run_plugin' );

// Shortcode for frontend display
function my_todo_shortcode($atts)
{
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


    <script>
    jQuery(document).ready(function($) {
        loadFrontendTodos();

        // Add todo form submission
        $('#frontend-add-todo-form').on('submit', function(e) {
            e.preventDefault();
            addFrontendTodo();
        });
    });

    function loadFrontendTodos() {
        jQuery.ajax({
            url: '<?php echo rest_url('my-todo/v1/todos'); ?>',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(data) {
                displayFrontendTodos(data);
            },
            error: function(xhr, status, error) {
                console.log('Error loading todos: ' + error);
            }
        });
    }

    function displayFrontendTodos(todos) {
        var html = '';
        var today = new Date().toISOString().split('T')[0];
        
        if (todos.length === 0) {
            html = '<p>No todos found. Add your first todo above!</p>';
        } else {
            todos.forEach(function(todo) {
                var isOverdue = (todo.deadline_date < today && todo.status === 'pending');
                var itemClass = 'todo-item';
                if (isOverdue) itemClass += ' overdue';
                if (todo.status === 'completed') itemClass += ' completed';
                
                html += '<div class="' + itemClass + '" data-todo-id="' + todo.id + '">';
                html += '<div class="todo-title">' + todo.title + '</div>';
                html += '<div class="todo-meta">Deadline: ' + todo.deadline_date + ' | Status: ' + todo.status + '</div>';
                if (todo.description) {
                    html += '<div class="todo-description">' + todo.description + '</div>';
                }
                html += '<div class="todo-actions">';
                if (todo.status === 'pending') {
                    html += '<button onclick="markCompleted(' + todo.id + ')">Mark Complete</button>';
                }
                html += '<button onclick="toggleComments(' + todo.id + ')">Toggle Comments</button>';
                html += '<button onclick="deleteFrontendTodo(' + todo.id + ')">Delete</button>';
                html += '</div>';
                html += '<div class="comment-section" id="comments-' + todo.id + '" style="display: none;">';
                html += '<div class="comments-list" id="comments-list-' + todo.id + '"></div>';
                html += '<div class="add-comment-form">';
                html += '<textarea placeholder="Add a comment..." id="comment-text-' + todo.id + '"></textarea>';
                html += '<button onclick="addFrontendComment(' + todo.id + ')">Add Comment</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
        }
        
        jQuery('#frontend-todos-list').html(html);
    }

    function addFrontendTodo() {
        var formData = {
            title: jQuery('#frontend-todo-title').val(),
            description: jQuery('#frontend-todo-description').val(),
            deadline_date: jQuery('#frontend-todo-deadline').val()
        };

        jQuery.ajax({
            url: '<?php echo rest_url('my-todo/v1/todos'); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            data: formData,
            success: function(data) {
                jQuery('#frontend-add-todo-form')[0].reset();
                loadFrontendTodos();
                alert('Todo added successfully!');
            },
            error: function(xhr, status, error) {
                alert('Error adding todo: ' + error);
            }
        });
    }

    function markCompleted(id) {
        jQuery.ajax({
            url: '<?php echo rest_url('my-todo/v1/todos/'); ?>' + id,
            method: 'PUT',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            data: { status: 'completed' },
            success: function(data) {
                loadFrontendTodos();
                alert('Todo marked as completed!');
            },
            error: function(xhr, status, error) {
                alert('Error updating todo: ' + error);
            }
        });
    }

    function deleteFrontendTodo(id) {
        if (confirm('Are you sure you want to delete this todo?')) {
            jQuery.ajax({
                url: '<?php echo rest_url('my-todo/v1/todos/'); ?>' + id,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(data) {
                    loadFrontendTodos();
                    alert('Todo deleted successfully!');
                },
                error: function(xhr, status, error) {
                    alert('Error deleting todo: ' + error);
                }
            });
        }
    }

    function toggleComments(todoId) {
        var commentsDiv = jQuery('#comments-' + todoId);
        if (commentsDiv.is(':visible')) {
            commentsDiv.hide();
        } else {
            loadFrontendComments(todoId);
            commentsDiv.show();
        }
    }

    function loadFrontendComments(todoId) {
        jQuery.ajax({
            url: '<?php echo rest_url('my-todo/v1/comments/'); ?>' + todoId,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(data) {
                displayFrontendComments(todoId, data);
            },
            error: function(xhr, status, error) {
                console.log('Error loading comments: ' + error);
            }
        });
    }

    function displayFrontendComments(todoId, comments) {
        var html = '';
        if (comments.length === 0) {
            html = '<p>No comments yet.</p>';
        } else {
            comments.forEach(function(comment) {
                html += '<div class="comment-item">';
                html += '<div>' + comment.comment + '</div>';
                html += '<div class="comment-date">' + comment.comment_date + '</div>';
                html += '</div>';
            });
        }
        jQuery('#comments-list-' + todoId).html(html);
    }

    function addFrontendComment(todoId) {
        var comment = jQuery('#comment-text-' + todoId).val();
        if (!comment.trim()) {
            alert('Please enter a comment');
            return;
        }

        jQuery.ajax({
            url: '<?php echo rest_url('my-todo/v1/comments'); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            data: {
                todo_id: todoId,
                comment: comment
            },
            success: function(data) {
                jQuery('#comment-text-' + todoId).val('');
                loadFrontendComments(todoId);
            },
            error: function(xhr, status, error) {
                alert('Error adding comment: ' + error);
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('my_todo', 'my_todo_shortcode');





