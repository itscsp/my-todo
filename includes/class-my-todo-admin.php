<?php
defined('ABSPATH') || exit;

class MyTodoAdmin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
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
                <span class="my-todo-close">×</span>
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
                <span class="my-todo-close">×</span>
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
<?php
    }


    public function enqueue_admin_scripts($hook)
    {
        if ($hook != 'toplevel_page_my-todo') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_style('mytodo-style', MY_TODO_PLUGIN_URL . 'assets/css/style.css');
        wp_enqueue_script('mytodo-admin-script', MY_TODO_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MY_TODO_VERSION, true);

        wp_localize_script('mytodo-admin-script', 'myTodoAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
            'rest_url' => esc_url_raw(rest_url()),
            'is_logged_in' => is_user_logged_in()
        ));
    }
}
