<?php
defined('ABSPATH') || exit;

class MyTodoDatabase
{
    private $table_todos;
    private $table_comments;

    public function __construct()
    {
        global $wpdb;
        $this->table_todos = $wpdb->prefix . 'my_todos';
        $this->table_comments = $wpdb->prefix . 'my_todo_comments';
    }


    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create todos table
        $sql_todos = "CREATE TABLE $this->table_todos (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        title text NOT NULL,
        description longtext,
        status varchar(20) NOT NULL DEFAULT 'pending',
        deadline_date date DEFAULT NULL,
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY deadline_date (deadline_date)
    ) $charset_collate;";

        // Create comments table
        $sql_comments = "CREATE TABLE $this->table_comments (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        todo_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        comment text NOT NULL,
        comment_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY todo_id (todo_id),
        KEY user_id (user_id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_todos);
        dbDelta($sql_comments);
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

    // Getter methods for table names
    public function get_table_todos()
    {
        return $this->table_todos;
    }

    public function get_table_comments()
    {
        return $this->table_comments;
    }
}
