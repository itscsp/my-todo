jQuery(document).ready(function($) {
    // Check if myTodoAjax is defined
    if (typeof myTodoAjax === 'undefined' || !myTodoAjax.rest_url || !myTodoAjax.nonce) {
        console.error('myTodoAjax, rest_url, or nonce is not defined. Ensure scripts are enqueued correctly.');
        return;
    }

    // Admin functionality
    if ($('#my-todo-app').length) {
        // Check login status properly
        if (!myTodoAjax.is_logged_in) {
            console.error('User is not logged in. Cannot load todos.');
            return;
        }
        
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
    }
});

function refreshNonce(callback) {
    jQuery.ajax({
        url: myTodoAjax.rest_url + 'my-todo/v1/debug-nonce',
        method: 'GET',
        success: function(data) {
            console.log('refreshNonce response:', data);
            if (data && data.nonce) {
                myTodoAjax.nonce = data.nonce;
                console.log('Nonce refreshed:', myTodoAjax.nonce);
                if (typeof callback === 'function') {
                    callback();
                }
            } else {
                console.error('Failed to refresh nonce:', data);
                alert('Authentication error. Please log in again.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error refreshing nonce:', xhr.responseText);
            alert('Error refreshing authentication. Please log in again.');
        }
    });
}

function loadTodos() {
    jQuery.ajax({
        url: myTodoAjax.rest_url + 'my-todo/v1/todos',
        method: 'GET',
        beforeSend: function(xhr) {
            console.log('Sending nonce:', myTodoAjax.nonce);
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        success: function(data) {
            console.log('loadTodos response:', data);
            if (Array.isArray(data)) {
                displayTodos(data);
            } else {
                console.error('Expected an array of todos, received:', data);
                if (data.code === 'rest_cookie_invalid_nonce') {
                    console.log('Nonce invalid, attempting to refresh...');
                    refreshNonce(function() {
                        loadTodos(); // Retry with new nonce
                    });
                } else {
                    alert('Error: Invalid response from server. Please try again or check the console for details.');
                    displayTodos([]);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading todos:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                console.log('Nonce invalid in error callback, attempting to refresh...');
                refreshNonce(function() {
                    loadTodos(); // Retry with new nonce
                });
            } else {
                alert('Error loading todos: ' + message);
                displayTodos([]);
            }
        }
    });
}

function displayTodos(todos) {
    var html = '';
    var today = new Date().toISOString().split('T')[0];
    
    if (!Array.isArray(todos) || todos.length === 0) {
        html = '<tr><td colspan="5">No todos found.</td></tr>';
    } else {
        todos.forEach(function(todo) {
            var statusClass = 'todo-status-' + (todo.id || 'unknown');
            var rowClass = (todo.deadline_date && todo.deadline_date < today && todo.status === 'pending') ? 'todo-overdue' : '';
            
            html += '<tr class="' + rowClass + '">';
            html += '<td><strong>' + (todo.title || 'Untitled') + '</strong></td>';
            html += '<td>' + (todo.description || '') + '</td>';
            html += '<td>' + (todo.deadline_date || 'No deadline') + '</td>';
            html += '<td><span class="' + statusClass + '">' + (todo.status ? todo.status.charAt(0).toUpperCase() + todo.status.slice(1) : 'Unknown') + '</span></td>';
            html += '<td>';
            html += '<button class="button button-small" onclick="editTodo(' + (todo.id || 0) + ')">Edit</button> ';
            html += '<button class="button button-small" onclick="viewComments(' + (todo.id || 0) + ')">Comments</button> ';
            html += '<button class="button button-small button-link-delete" onclick="deleteTodo(' + (todo.id || 0) + ')">Delete</button>';
            html += '</td>';
            html += '</tr>';
        });
    }
    
    jQuery('#todos-list').html(html);
}

function addTodo() {
    var formData = {
        title: jQuery('#todo-title').val(),
        description: jQuery('#todo-description').val(),
        deadline_date: jQuery('#todo-deadline').val()
    };

    jQuery.ajax({
        url: myTodoAjax.rest_url + 'my-todo/v1/todos',
        method: 'POST',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        data: formData,
        success: function(data) {
            console.log('addTodo response:', data);
            jQuery('#add-todo-form')[0].reset();
            loadTodos();
            alert('Todo added successfully!');
        },
        error: function(xhr, status, error) {
            console.error('Error adding todo:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    addTodo();
                });
            } else {
                alert('Error adding todo: ' + message);
            }
        }
    });
}

function editTodo(id) {
    jQuery.ajax({
        url: myTodoAjax.rest_url + 'my-todo/v1/todos/' + id,
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        success: function(data) {
            console.log('editTodo response:', data);
            jQuery('#edit-todo-id').val(data.id);
            jQuery('#edit-todo-title').val(data.title);
            jQuery('#edit-todo-description').val(data.description);
            jQuery('#edit-todo-deadline').val(data.deadline_date);
            jQuery('#edit-todo-status').val(data.status);
            jQuery('#edit-todo-modal').show();
        },
        error: function(xhr, status, error) {
            console.error('Error loading todo:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    editTodo(id);
                });
            } else {
                alert('Error loading todo: ' + message);
            }
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
        url: myTodoAjax.rest_url + 'my-todo/v1/todos/' + id,
        method: 'PUT',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        data: formData,
        success: function(data) {
            console.log('updateTodo response:', data);
            jQuery('#edit-todo-modal').hide();
            loadTodos();
            alert('Todo updated successfully!');
        },
        error: function(xhr, status, error) {
            console.error('Error updating todo:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    updateTodo();
                });
            } else {
                alert('Error updating todo: ' + message);
            }
        }
    });
}

function deleteTodo(id) {
    if (confirm('Are you sure you want to delete this todo?')) {
        jQuery.ajax({
            url: myTodoAjax.rest_url + 'my-todo/v1/todos/' + id,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
            },
            success: function(data) {
                console.log('deleteTodo response:', data);
                loadTodos();
                alert('Todo deleted successfully!');
            },
            error: function(xhr, status, error) {
                console.error('Error deleting todo:', xhr.responseText);
                var message = xhr.responseJSON?.message || error;
                if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                    refreshNonce(function() {
                        deleteTodo(id);
                    });
                } else {
                    alert('Error deleting todo: ' + message);
                }
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
        url: myTodoAjax.rest_url + 'my-todo/v1/comments/' + todoId,
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        success: function(data) {
            console.log('loadComments response:', data);
            if (Array.isArray(data)) {
                displayComments(data);
            } else {
                console.error('Expected an array of comments, received:', data);
                if (data.code === 'rest_cookie_invalid_nonce') {
                    refreshNonce(function() {
                        loadComments(todoId);
                    });
                } else {
                    displayComments([]);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading comments:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    loadComments(todoId);
                });
            } else {
                alert('Error loading comments: ' + message);
                displayComments([]);
            }
        }
    });
}

function displayComments(comments) {
    var html = '';
    if (!Array.isArray(comments) || comments.length === 0) {
        html = '<p>No comments yet.</p>';
    } else {
        comments.forEach(function(comment) {
            html += '<div class="comment-item">';
            html += '<div>' + (comment.comment || 'No comment text') + '</div>';
            html += '<div class="comment-date">' + (comment.comment_date || 'Unknown date') + '</div>';
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
        url: myTodoAjax.rest_url + 'my-todo/v1/comments',
        method: 'POST',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        data: formData,
        success: function(data) {
            console.log('addComment response:', data);
            jQuery('#new-comment').val('');
            loadComments(formData.todo_id);
            alert('Comment added successfully!');
        },
        error: function(xhr, status, error) {
            console.error('Error adding comment:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    addComment();
                });
            } else {
                alert('Error adding comment: ' + message);
            }
        }
    });
}

function filterTodos() {
    var status = jQuery('#filter-status').val();
    var url = myTodoAjax.rest_url + 'my-todo/v1/todos';
    if (status) {
        url += '?status=' + status;
    }
    
    jQuery.ajax({
        url: url,
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        success: function(data) {
            console.log('filterTodos response:', data);
            if (Array.isArray(data)) {
                displayTodos(data);
            } else {
                console.error('Expected an array of todos, received:', data);
                if (data.code === 'rest_cookie_invalid_nonce') {
                    refreshNonce(function() {
                        filterTodos();
                    });
                } else {
                    alert('Error: Invalid response from server. Please try again or check the console for details.');
                    displayTodos([]);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error filtering todos:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    filterTodos();
                });
            } else {
                alert('Error filtering todos: ' + message);
                displayTodos([]);
            }
        }
    });
}

function closeEditModal() {
    jQuery('#edit-todo-modal').hide();
}

function closeCommentsModal() {
    jQuery('#comments-modal').hide();
}