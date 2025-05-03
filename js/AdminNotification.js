document.addEventListener("DOMContentLoaded", function () {
    const dropdownArrows = document.querySelectorAll(".arrow-icon");

    // Retrieve dropdown state from localStorage
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

    dropdownArrows.forEach(arrow => {
        let parent = arrow.closest(".dropdown");
        let dropdownText = parent.querySelector(".text").innerText;

        // Apply saved state
        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }

        arrow.addEventListener("click", function (event) {
            event.stopPropagation(); // Prevent triggering the parent link
            
            let parent = this.closest(".dropdown");
            parent.classList.toggle("active");

            // Save the state in localStorage
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });
});

// Profile Dropdown
document.addEventListener("DOMContentLoaded", function () {
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon.addEventListener("click", function (event) {
        event.stopPropagation(); // Prevent closing when clicking inside
        userDropdown.classList.toggle("show");
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });
});

// AJAX Helper Function
        async function makeRequest(url, method = 'POST', data = {}) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            };

            const response = await fetch(url, {
                method: method,
                headers: headers,
                body: method !== 'GET' ? JSON.stringify(data) : null
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.json();
        }

        // Notification Actions
        async function handleNotificationAction(button, endpoint, notificationId = null) {
            const buttonText = button.querySelector('.button-text');
            const loader = button.querySelector('.loading');
            
            try {
                button.disabled = true;
                buttonText.style.display = 'none';
                loader.style.display = 'inline-block';

                const data = notificationId ? { notificationId } : {};
                const response = await makeRequest(endpoint, 'POST', data);

                if (response.success) {
                    // Handle UI update
                    if (endpoint.includes('delete')) {
                        button.closest('.notification-item').remove();
                    } else if (endpoint.includes('read')) {
                        button.closest('.notification-item').classList.add('read');
                        button.remove();
                    }
                } else {
                    alert(response.message || 'Action failed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            } finally {
                button.disabled = false;
                buttonText.style.display = 'inline-block';
                loader.style.display = 'none';
            }
        }

        // Event Handlers
        function handleMarkRead(button) {
            const notificationId = button.closest('.notification-item').dataset.notificationId;
            handleNotificationAction(button, 'api/mark_read.php', notificationId);
        }

        function handleDeleteNotification(button) {
            const notificationId = button.closest('.notification-item').dataset.notificationId;
            if (confirm('Are you sure you want to delete this notification?')) {
                handleNotificationAction(button, 'api/delete_notification.php', notificationId);
            }
        }

        function handleMarkAllRead() {
            if (confirm('Mark all notifications as read?')) {
                const button = document.querySelector('.mark-all-read');
                handleNotificationAction(button, 'api/mark_all_read.php');
            }
        }

        function handleDeleteAll() {
            if (confirm('Permanently delete all notifications?')) {
                const button = document.querySelector('.delete-all');
                handleNotificationAction(button, 'api/delete_all_notifications.php');
            }
        }

        async function handleApproveRequest(button) {
            const requestId = button.closest('.notification-item').dataset.requestId;
            if (confirm('Approve this request?')) {
                handleNotificationAction(button, 'api/approve_request.php', requestId);
            }
        }

        async function handleRejectRequest(button) {
            const requestId = button.closest('.notification-item').dataset.requestId;
            if (confirm('Reject this request?')) {
                handleNotificationAction(button, 'api/reject_request.php', requestId);
            }
        }

        async function handleApproveBorrowRequest(button) {
            const borrowId = button.closest('.notification-item').dataset.borrowId;
            if (confirm('Approve this borrow request?')) {
                handleNotificationAction(button, 'api/approve_borrow_request.php', borrowId);
            }
        }

        async function handleRejectBorrowRequest(button) {
            const borrowId = button.closest('.notification-item').dataset.borrowId;
            if (confirm('Reject this borrow request?')) {
                handleNotificationAction(button, 'api/reject_borrow_request.php', borrowId);
            }
        }

        async function handleApproveReturnRequest(button) {
            const returnId = button.closest('.notification-item').dataset.returnId;
            if (confirm('Approve this return request?')) {
                handleNotificationAction(button, 'api/approve_return_request.php', returnId);
            }
        }

        async function handleRejectReturnRequest(button) {
            const returnId = button.closest('.notification-item').dataset.returnId;
            if (confirm('Reject this return request?')) {
                handleNotificationAction(button, 'api/reject_return_request.php', returnId);
            }
        }