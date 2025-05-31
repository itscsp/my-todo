jQuery(document).ready(function($) {
    // Check if myTodoAjax is defined
    if (typeof myTodoAjax === 'undefined' || !myTodoAjax.rest_url || !myTodoAjax.nonce) {
        console.error('myTodoAjax, rest_url, or nonce is not defined. Ensure scripts are enqueued correctly.');
        return;
    }

    // Frontend functionality
    if ($('#my-todo-frontend').length) {
        if (!myTodoAjax.is_logged_in) {
            console.error('User is not logged in. Cannot load todos.');
            $('#frontend-todos-list').html('<p>Please log in to view your todos.</p>');
            return;
        }
        loadFrontendTodos();

        // Add todo form submission
        $('#frontend-add-todo-form').on('submit', function(e) {
            e.preventDefault();
            addFrontendTodo();
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

function loadFrontendTodos() {
    jQuery.ajax({
        url: myTodoAjax.rest_url + 'my-todo/v1/todos',
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        success: function(data) {
            console.log('loadFrontendTodos response:', data);
            if (Array.isArray(data)) {
                displayFrontendTodos(data);
            } else {
                console.error('Expected an array of todos, received:', data);
                // Add better error handling for HTML responses
                try {
                    if (typeof data === 'string' && data.includes('<html')) {
                        console.error('Received HTML instead of JSON. You might be logged out.');
                        jQuery('#frontend-todos-list').html('<p>Authentication error. Please refresh the page and try again.</p>');
                        return;
                    }
                } catch (e) {}
                
                if (data.code === 'rest_cookie_invalid_nonce') {
                    refreshNonce(function() {
                        loadFrontendTodos();
                    });
                } else {
                    alert('Error: Invalid response from server. Please try again or check the console for details.');
                    displayFrontendTodos([]);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading todos:', xhr.responseText);
            console.error('Status code:', xhr.status);
            
            // Improved error handling for HTML responses
            var responseText = xhr.responseText || '';
            if (responseText.indexOf('<!DOCTYPE html') !== -1 || responseText.indexOf('<html') !== -1) {
                console.error('Received HTML response instead of JSON. Authentication may have failed.');
                jQuery('#frontend-todos-list').html('<p>Authentication error. Please refresh the page and try again.</p>');
                return;
            }
            
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    loadFrontendTodos();
                });
            } else {
                alert('Error loading todos: ' + message);
                displayFrontendTodos([]);
            }
        }
    });
}

function displayFrontendTodos(todos) {
    var html = '';
    var today = new Date().toISOString().split('T')[0];
    
    if (!Array.isArray(todos) || todos.length === 0) {
        html = '<p>No todos found. Add your first todo above!</p>';
    } else {
        todos.forEach(function(todo) {
            var isOverdue = (todo.deadline_date && todo.deadline_date < today && todo.status === 'pending');
            var itemClass = 'todo-item';
            if (isOverdue) itemClass += ' overdue';
            if (todo.status === 'completed') itemClass += ' completed';
            
            html += '<div class="' + itemClass + '" data-todo-id="' + (todo.id || 0) + '">';
            html += '<div class="todo-title">' + (todo.title || 'Untitled') + '</div>';
            html += '<div class="todo-meta">Deadline: ' + (todo.deadline_date || 'No deadline') + ' | Status: ' + (todo.status || 'Unknown') + '</div>';
            if (todo.description) {
                html += '<div class="todo-description">' + todo.description + '</div>';
            }
            html += '<div class="todo-actions">';
            if (todo.status === 'pending') {
                html += '<button onclick="markCompleted(' + (todo.id || 0) + ')">Mark Complete</button>';
            }
            html += '<button onclick="toggleComments(' + (todo.id || 0) + ')">Toggle Comments</button>';
            html += '<button onclick="deleteFrontendTodo(' + (todo.id || 0) + ')">Delete</button>';
            html += '</div>';
            html += '<div class="comment-section" id="comments-' + (todo.id || 0) + '" style="display: none;">';
            html += '<div class="comments-list" id="comments-list-' + (todo.id || 0) + '"></div>';
            html += '<div class="add-comment-form">';
            html += '<textarea placeholder="Add a comment..." id="comment-text-' + (todo.id || 0) + '"></textarea>';
            html += '<button onclick="addFrontendComment(' + (todo.id || 0) + ')">Add Comment</button>';
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
        url: myTodoAjax.rest_url + 'my-todo/v1/todos',
        method: 'POST',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        data: formData,
        success: function(data) {
            console.log('addFrontendTodo response:', data);
            jQuery('#frontend-add-todo-form')[0].reset();
            loadFrontendTodos();
            alert('Todo added successfully!');
        },
        error: function(xhr, status, error) {
            console.error('Error adding todo:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    addFrontendTodo();
                });
            } else {
                alert('Error adding todo: ' + message);
            }
        }
    });
}

function markCompleted(id) {
    // First fetch the todo to get its current data
    jQuery.ajax({
        url: myTodoAjax.rest_url + 'my-todo/v1/todos/' + id,
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        success: function(todo) {
            // Now update with all the required fields plus the new status
            jQuery.ajax({
                url: myTodoAjax.rest_url + 'my-todo/v1/todos/' + id,
                method: 'PUT',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
                },
                data: {
                    title: todo.title,
                    description: todo.description,
                    deadline_date: todo.deadline_date,
                    status: 'completed'
                },
                success: function(data) {
                    console.log('markCompleted response:', data);
                    loadFrontendTodos();
                    alert('Todo marked as completed!');
                },
                error: function(xhr, status, error) {
                    console.error('Error updating todo:', xhr.responseText);
                    var message = xhr.responseJSON?.message || error;
                    if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                        refreshNonce(function() {
                            markCompleted(id);
                        });
                    } else {
                        alert('Error updating todo: ' + message);
                    }
                }
            });
        },
        error: function(xhr, status, error) {
            console.error('Error fetching todo:', xhr.responseText);
            alert('Error marking todo as completed: Could not fetch todo data');
        }
    });
}
function deleteFrontendTodo(id) {
    if (confirm('Are you sure you want to delete this todo?')) {
        jQuery.ajax({
            url: myTodoAjax.rest_url + 'my-todo/v1/todos/' + id,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
            },
            success: function(data) {
                console.log('deleteFrontendTodo response:', data);
                loadFrontendTodos();
                alert('Todo deleted successfully!');
            },
            error: function(xhr, status, error) {
                console.error('Error deleting todo:', xhr.responseText);
                var message = xhr.responseJSON?.message || error;
                if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                    refreshNonce(function() {
                        deleteFrontendTodo(id);
                    });
                } else {
                    alert('Error deleting todo: ' + message);
                }
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
        url: myTodoAjax.rest_url + 'my-todo/v1/comments/' + todoId,
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        success: function(data) {
            console.log('loadFrontendComments response:', data);
            if (Array.isArray(data)) {
                displayFrontendComments(todoId, data);
            } else {
                console.error('Expected an array of comments, received:', data);
                if (data.code === 'rest_cookie_invalid_nonce') {
                    refreshNonce(function() {
                        loadFrontendComments(todoId);
                    });
                } else {
                    displayFrontendComments(todoId, []);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading comments:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    loadFrontendComments(todoId);
                });
            } else {
                alert('Error loading comments: ' + message);
                displayFrontendComments(todoId, []);
            }
        }
    });
}

function displayFrontendComments(todoId, comments) {
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
    jQuery('#comments-list-' + todoId).html(html);
}

function addFrontendComment(todoId) {
    var comment = jQuery('#comment-text-' + todoId).val();
    if (!comment.trim()) {
        alert('Please enter a comment');
        return;
    }

    jQuery.ajax({
        url: myTodoAjax.rest_url + 'my-todo/v1/comments',
        method: 'POST',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', myTodoAjax.nonce);
        },
        data: {
            todo_id: todoId,
            comment: comment
        },
        success: function(data) {
            console.log('addFrontendComment response:', data);
            jQuery('#comment-text-' + todoId).val('');
            loadFrontendComments(todoId);
            alert('Comment added successfully!');
        },
        error: function(xhr, status, error) {
            console.error('Error adding comment:', xhr.responseText);
            var message = xhr.responseJSON?.message || error;
            if (xhr.responseJSON?.code === 'rest_cookie_invalid_nonce') {
                refreshNonce(function() {
                    addFrontendComment(todoId);
                });
            } else {
                alert('Error adding comment: ' + message);
            }
        }
    });
}