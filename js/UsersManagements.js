document.addEventListener("DOMContentLoaded", function () {
    // Dropdown State Management
    const dropdownArrows = document.querySelectorAll(".arrow-icon");
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

    dropdownArrows.forEach(arrow => {
        const parent = arrow.closest(".dropdown");
        const dropdownText = parent.querySelector(".text").innerText;

        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }

        arrow.addEventListener("click", function (event) {
            event.stopPropagation();
            parent.classList.toggle("active");
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });

    // Modal Initialization
    function initializeModal(modalId, openBtnId, closeBtnId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        const openBtn = document.getElementById(openBtnId);
        const closeBtn = document.getElementById(closeBtnId);

        modal.style.display = "none";

        if (openBtn) openBtn.addEventListener("click", () => modal.style.display = "block");
        if (closeBtn) closeBtn.addEventListener("click", () => modal.style.display = "none");

        window.addEventListener("click", event => {
            if (event.target === modal) modal.style.display = "none";
        });
    }

    initializeModal("create-account-modal", "create-account-btn", "cancel-btn");
    initializeModal("deactivate-account-modal", null, "deactivate-cancel-btn");

    // Global variables
    let users = [];
    let currentPage = 1;
    const rowsPerPage = 10;

    // Main Functions
    function updateTable() {
        const tbody = document.getElementById("user-table-body");
        tbody.innerHTML = "";

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const paginatedUsers = users.slice(start, end);

        paginatedUsers.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.username}</td>
                <td>${user.email}</td>
                <td>${user.role}</td>
                <td>${user.dateCreated}</td>
                <td>${user.ministry}</td>
                <td>${user.status}</td>
                <td>
                    <button class="delete-btn" data-user-id="${user.user_id}">Delete</button>
                    <button class="deactivate-btn" data-user-id="${user.user_id}">Deactivate</button>
                </td>
            `;
            tbody.appendChild(row);
        });

        updatePagination();
    }

    document.getElementById('deactivate-duration').addEventListener('change', function() {
        document.getElementById('custom-duration-container').style.display = 
            this.value === 'custom' ? 'block' : 'none';
    });

    function openDeactivateModal(userId) {
        const currentUserRole = document.getElementById('current-user-role')?.value;
        if (currentUserRole && currentUserRole !== 'Administrator') {
            alert('Only administrators can deactivate users.');
            return;
        }
        const user = users.find(u => parseInt(u.user_id) === parseInt(userId));
        if (!user) {
            alert('User not found');
            return;
        }

        // Prevent deactivating other admins
        if (user.role === 'Administrator') {
            alert('You cannot deactivate another administrator.');
            return;
        }
        document.getElementById('deactivate-user-id').value = userId;
        document.getElementById('deactivate-account-modal').style.display = 'block';
    }

    function updatePagination() {
        document.getElementById("page-number").innerText = `Page ${currentPage}`;
        document.getElementById("prev-btn").disabled = currentPage === 1;
        document.getElementById("next-btn").disabled = currentPage >= Math.ceil(users.length / rowsPerPage);
    }

function fetchUsers() {
    showLoading(true);
    fetch('UserManagement.php?fetchUsers=true')
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (Array.isArray(data)) {
                users = data.map(user => ({
                    ...user,
                    status: user.status === '1' ? 'Active' : 
                           user.status === '0' ? 'Inactive' : 
                           user.status // Keep existing status if not 1 or 0
                }));
                updateTable();
            } else if (data.error) {
                throw new Error(data.error);
            }
        })
        .catch(error => {
            console.error('Error fetching users:', error);
            alert('Error: ' + error.message);
        })
        .finally(() => showLoading(false));
}

    // Fixed Account Creation
    document.getElementById("account-form").addEventListener("submit", async function(event) {
        event.preventDefault();

        const username = document.getElementById("username").value.trim();
        const email = document.getElementById("email").value.trim();
        const password = document.getElementById("password").value;
        const ministry = document.getElementById("ministry").value;
        const role = document.getElementById("role").value;

        // Validate inputs
        if (!username || !email || !password || ministry === "Choose") {
            alert("Please fill in all required fields");
            return;
        }

        // Check administrator limit
        if (role === "Administrator") {
            const adminCount = users.filter(user => user.role === "Administrator").length;
            if (adminCount >= 5) {
                alert("Maximum of 5 administrators already reached");
                return;
            }
        }

        // Confirmation dialog
        if (!confirm(`Create new ${role.toLowerCase()} account for ${username}?`)) {
            return;
        }

        try {
            showLoading(true);

            const formData = new FormData();
            formData.append('action', 'CREATE');
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('ministry', ministry);
            formData.append('role', role);
            formData.append('status', 'Active');

            const response = await fetch('UserManagement.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();

            if (data.success) {
                // Add new user to the beginning of the array
                users.unshift({
                    user_id: data.user_id,
                    username: username,
                    email: email,
                    role: role,
                    dateCreated: new Date().toLocaleDateString(),
                    ministry: ministry,
                    status: 'Active'
                });

                // Reset form and close modal
                document.getElementById("account-form").reset();
                document.getElementById("create-account-modal").style.display = "none";

                // Update UI
                currentPage = 1; // Reset to first page
                updateTable();
                alert(`Account for ${username} created successfully!`);
            } else {
                throw new Error(data.error || 'Account creation failed');
            }
        } catch (error) {
            console.error('Error:', error);
            alert(`Error: ${error.message}`);
        } finally {
            showLoading(false);
        }
    });

    // User Actions
    function updateStatus(userId, status) {
        if (!confirm(`Change user status to ${status}?`)) {
            // Reset the select to its previous value
            const select = document.querySelector(`select[onchange="updateStatus(${userId}, this.value)"]`);
            const user = users.find(u => u.user_id === userId);
            if (user && select) select.value = user.status;
            return;
        }

        showLoading(true);
        fetch('UserManagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=UPDATE_STATUS&user_id=${userId}&status=${status}`
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const userIndex = users.findIndex(u => u.user_id === userId);
                if (userIndex !== -1) {
                    users[userIndex].status = status;
                }
                alert('Status updated successfully.');
            } else {
                throw new Error(data.error || 'Failed to update status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        })
        .finally(() => showLoading(false));
    }

    function deleteUser(userId) {
        // Convert userId to number for comparison
        const userIdNum = parseInt(userId);
        const user = users.find(u => parseInt(u.user_id) === userIdNum);
        
        if (!user) {
            console.error('User not found with ID:', userId);
            console.log('Current users array:', users); // Debug log
            return;
        }
    
        // Prevent deleting other admins
        if (user.role === 'Administrator') {
            alert('You cannot delete another administrator.');
            return;
        }
    
        if (!confirm(`Permanently delete user ${user.username}?\n\nThis cannot be undone!`)) {
            return;
        }
    
        showLoading(true);
        const formData = new FormData();
        formData.append('action', 'DELETE');
        formData.append('user_id', userIdNum); // Use the numeric ID
    
        fetch('UserManagement.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Remove user from the array using the numeric ID
                users = users.filter(u => parseInt(u.user_id) !== userIdNum);
                updateTable();
                alert(`User ${user.username} deleted successfully!`);
            } else {
                throw new Error(data.error || 'Failed to delete user');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        })
        .finally(() => showLoading(false));
    }
    
    document.getElementById('deactivate-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const userId = document.getElementById('deactivate-user-id').value;
        const duration = document.getElementById('deactivate-duration').value;
        const customDuration = document.getElementById('custom-duration').value;
        const user = users.find(u => u.user_id == userId);
    
        if (!user) {
            alert('User not found');
            return;
        }
    
        const durationText = duration === 'custom' ? 
            `${customDuration} days` : 
            `${duration} day${duration === '1' ? '' : 's'}`;
    
        if (!confirm(`Deactivate user ${user.username} for ${durationText}?`)) {
            return;
        }
    
        const deactivationDuration = duration === 'custom' ? customDuration : duration;
    
        showLoading(true);
        fetch('UserManagement.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: `action=DEACTIVATE&user_id=${userId}&duration=${deactivationDuration}`
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error("Server didn't return JSON");
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update the user's status in the local array
                const userIndex = users.findIndex(u => u.user_id == userId);
                if (userIndex !== -1) {
                    users[userIndex].status = 'Deactivated';
                }
                alert(`User ${user.username} deactivated successfully`);
                document.getElementById('deactivate-account-modal').style.display = 'none';
                updateTable(); // Refresh the table display
            } else {
                throw new Error(data.error || 'Failed to deactivate user');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        })
        .finally(() => showLoading(false));
    });

    // Pagination
    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            updateTable();
        }
    }

    function nextPage() {
        if (currentPage < Math.ceil(users.length / rowsPerPage)) {
            currentPage++;
            updateTable();
        }
    }

    // Search and Filter
    function filterTable() {
        const query = (document.getElementById("search-box")?.value || "").toLowerCase();
        const startDate = document.getElementById("start-date")?.value ? new Date(document.getElementById("start-date").value) : null;
        const endDate = document.getElementById("end-date")?.value ? new Date(document.getElementById("end-date").value) : null;

        document.querySelectorAll(".user-table tbody tr").forEach(row => {
            const text = row.textContent.toLowerCase();
            const dateCell = row.cells[3]?.textContent.trim();
            const rowDate = dateCell ? parseDate(dateCell) : null;

            const matchesSearch = text.includes(query);
            const matchesDate = (!rowDate || (!startDate && !endDate) || 
                ((!startDate || rowDate >= startDate) && (!endDate || rowDate <= endDate)));

            row.style.display = matchesSearch && matchesDate ? "table-row" : "none";
        });
    }

    document.getElementById("search-box")?.addEventListener("input", filterTable);
    document.getElementById("start-date")?.addEventListener("change", filterTable);
    document.getElementById("end-date")?.addEventListener("change", filterTable);

    // Utility Functions
    function parseDate(dateStr) {
        const formats = [
            { regex: /^\d{4}-\d{2}-\d{2}$/, parse: parts => new Date(parts[0], parts[1] - 1, parts[2]) },
            { regex: /^\d{2}\/\d{2}\/\d{4}$/, parse: parts => new Date(parts[2], parts[0] - 1, parts[1]) },
            { regex: /^\d{2}-\d{2}-\d{4}$/, parse: parts => new Date(parts[2], parts[1] - 1, parts[0]) }
        ];

        for (const { regex, parse } of formats) {
            if (regex.test(dateStr)) {
                return parse(dateStr.split(/[-\/]/));
            }
        }
        return null;
    }

    function showLoading(show) {
        const loader = document.getElementById('loading-overlay') || createLoader();
        loader.style.display = show ? 'flex' : 'none';
    }

    function createLoader() {
        const loader = document.createElement('div');
        loader.id = 'loading-overlay';
        loader.style.position = 'fixed';
        loader.style.top = '0';
        loader.style.left = '0';
        loader.style.width = '100%';
        loader.style.height = '100%';
        loader.style.backgroundColor = 'rgba(0,0,0,0.5)';
        loader.style.display = 'none';
        loader.style.justifyContent = 'center';
        loader.style.alignItems = 'center';
        loader.style.zIndex = '1000';

        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        spinner.style.border = '5px solid #f3f3f3';
        spinner.style.borderTop = '5px solid #3498db';
        spinner.style.borderRadius = '50%';
        spinner.style.width = '50px';
        spinner.style.height = '50px';
        spinner.style.animation = 'spin 1s linear infinite';

        loader.appendChild(spinner);
        document.body.appendChild(loader);

        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);

        return loader;
    }

    // Profile Dropdown
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon?.addEventListener("click", event => {
        event.stopPropagation();
        userDropdown?.classList.toggle("show");
    });

    document.addEventListener("click", event => {
        if (!userIcon?.contains(event.target) && !userDropdown?.contains(event.target)) {
            userDropdown?.classList.remove("show");
        }
    });

    // Event delegation for table button clicks
    document.getElementById("user-table-body")?.addEventListener("click", function(event) {
        const deleteBtn = event.target.closest(".delete-btn");
        const deactivateBtn = event.target.closest(".deactivate-btn");
        
        if (deleteBtn) {
            const userId = deleteBtn.getAttribute("data-user-id");
            deleteUser(parseInt(userId));
        }
        
        if (deactivateBtn) {
            const userId = deactivateBtn.getAttribute("data-user-id");
            openDeactivateModal(parseInt(userId));
        }
    });

    // Pagination button event listeners
    document.getElementById("prev-btn")?.addEventListener("click", prevPage);
    document.getElementById("next-btn")?.addEventListener("click", nextPage);

    // Initial Load
    fetchUsers();

    function checkReactivations() {
        fetch('UserManagement.php?checkReactivations=true')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success && data.reactivated_users > 0) {
                    alert(`${data.reactivated_users} user(s) reactivated.`);
                    fetchUsers(); // Refresh the user list
                }
            })
            .catch(error => console.error('Error checking reactivations:', error));
    }

    // Periodically check for reactivations every 5 minutes
    setInterval(checkReactivations, 300000);
});