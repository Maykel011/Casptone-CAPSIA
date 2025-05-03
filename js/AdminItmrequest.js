document.addEventListener('DOMContentLoaded', function() {
    // ========== GLOBAL VARIABLES ==========
    const rowsPerPage = 10;
    let currentPage = 1;
    let totalPages = 1;
    let filteredRows = [];
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let currentRequestId = null;

    // ========== INITIALIZATION ==========
    initializeComponents();

    function initializeComponents() {
        initializeDropdowns();
        initializeTable();
        setupFilterControls();
        setupActionHandlers();
        setupModalHandlers();
    }

    // ========== DROPDOWN FUNCTIONALITY ==========
    function initializeDropdowns() {
        // User dropdown
        const userIcon = document.getElementById("userIcon");
        const userDropdown = document.getElementById("userDropdown");

        if (userIcon && userDropdown) {
            userIcon.addEventListener("click", function(event) {
                event.stopPropagation();
                userDropdown.classList.toggle("show");
            });

            document.addEventListener("click", function(event) {
                if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
                    userDropdown.classList.remove("show");
                }
            });
        }

        // Sidebar dropdowns with persistent state
        const dropdownArrows = document.querySelectorAll(".arrow-icon");
        const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

        dropdownArrows.forEach(arrow => {
            const parent = arrow.closest(".dropdown");
            const dropdownText = parent.querySelector(".text").innerText;

            if (savedDropdownState[dropdownText]) {
                parent.classList.add("active");
            }

            arrow.addEventListener("click", function(event) {
                event.stopPropagation();
                parent.classList.toggle("active");
                savedDropdownState[dropdownText] = parent.classList.contains("active");
                localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
            });
        });
    }

    // ========== TABLE & PAGINATION ==========
    function initializeTable() {
        const tableBody = document.querySelector(".item-table tbody");
        const rows = Array.from(tableBody.querySelectorAll("tr:not(.no-results)"));
        filteredRows = rows;
        
        updatePagination();
        showPage(currentPage);
    }

    function updatePagination() {
        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        document.querySelector(".page-info").textContent = `Page ${currentPage} of ${totalPages}`;
        
        // Disable/enable pagination buttons
        const prevBtn = document.querySelector(".page-link:nth-child(1)");
        const nextBtn = document.querySelector(".page-link:nth-child(3)");
        
        if (prevBtn) prevBtn.disabled = currentPage === 1;
        if (nextBtn) nextBtn.disabled = currentPage === totalPages;
    }

    function showPage(page) {
        if (filteredRows.length === 0) {
            document.querySelector(".item-table tbody").innerHTML = 
                "<tr><td colspan='10' class='no-results'>No results found</td></tr>";
            return;
        }

        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        // Hide all rows first
        document.querySelectorAll(".item-table tbody tr").forEach(row => {
            row.style.display = "none";
        });

        // Show rows for current page
        filteredRows.slice(start, end).forEach(row => {
            row.style.display = "table-row";
        });

        updatePagination();
    }

    // ========== FILTER FUNCTIONALITY ==========
    function setupFilterControls() {
        document.getElementById("search-box").addEventListener("input", filterTable);
        document.getElementById("start-date").addEventListener("change", filterTable);
        document.getElementById("end-date").addEventListener("change", filterTable);
    }

    function filterTable() {
        const searchQuery = document.getElementById("search-box").value.toLowerCase();
        const startDate = document.getElementById("start-date").value;
        const endDate = document.getElementById("end-date").value;

        const rows = document.querySelectorAll(".item-table tbody tr:not(.no-results)");
        filteredRows = [];
        
        rows.forEach(row => {
            const rowData = row.innerText.toLowerCase();
            const dateCell = row.cells[7].textContent;

            const matchesSearch = searchQuery === "" || rowData.includes(searchQuery);
            const matchesDate = (startDate === "" || endDate === "") || 
                               (dateCell >= startDate && dateCell <= endDate);

            if (matchesSearch && matchesDate) {
                filteredRows.push(row);
            } else {
                row.style.display = "none";
            }
        });

        currentPage = 1;
        updatePagination();
        showPage(currentPage);
    }

    // ========== REQUEST ACTION HANDLERS ==========
    function setupActionHandlers() {
        // Approve button handler
        document.querySelectorAll('.approve-btn').forEach(btn => {
            btn.addEventListener('click', handleApproveClick);
        });

        // Reject button handler
        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', handleRejectClick);
        });
    }

    function handleApproveClick(e) {
        const btn = e.target;
        if (btn.disabled) return;
        
        currentRequestId = btn.closest('tr').dataset.requestId;
        const itemName = btn.closest('tr').querySelector('td:nth-child(3)').textContent;
        
        document.getElementById('approveModalRequestId').textContent = currentRequestId;
        document.getElementById('approveModalItemName').textContent = itemName;
        document.getElementById('approveModal').style.display = 'block';
    }

    function handleRejectClick(e) {
        const btn = e.target;
        if (btn.disabled) return;
        
        currentRequestId = btn.closest('tr').dataset.requestId;
        const itemName = btn.closest('tr').querySelector('td:nth-child(3)').textContent;
        
        document.getElementById('modalRequestId').textContent = currentRequestId;
        document.getElementById('modalItemName').textContent = itemName;
        document.getElementById('rejectModal').style.display = 'block';
    }

    function processRequestAction(action, requestId, reason = '') {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('request_id', requestId);
        formData.append('csrf_token', csrfToken);
        
        if (action === 'reject') {
            formData.append('reason', reason);
        }

        return fetch('ItemRequest.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
    }

    function updateRequestUI(row, action, itemNo = null) {
        const statusCell = row.querySelector('.status-cell');
        const approveBtn = row.querySelector('.approve-btn');
        const rejectBtn = row.querySelector('.reject-btn');
        
        if (action === 'approved') {
            statusCell.textContent = 'Approved';
            statusCell.className = 'status-cell approved';
            row.style.backgroundColor = "#e6f7e6";
            if (itemNo) {
                // Optional: Update with new item number if needed
            }
        } else if (action === 'rejected') {
            statusCell.textContent = 'Rejected';
            statusCell.className = 'status-cell rejected';
            row.style.backgroundColor = "#ffebeb";
        }
        
        approveBtn.disabled = true;
        rejectBtn.disabled = true;
        approveBtn.classList.add('disabled');
        rejectBtn.classList.add('disabled');
    }

    // ========== MODAL HANDLERS ==========
    function setupModalHandlers() {
        // Confirm approve handler
        document.getElementById('confirmApprove').addEventListener('click', handleConfirmApprove);
        
        // Confirm reject handler
        document.getElementById('confirmReject').addEventListener('click', handleConfirmReject);
        
        // Modal close handlers
        document.querySelectorAll('.close, .cancel-btn').forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });
        
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeAllModals();
            }
        });
    }

    function handleConfirmApprove() {
        processRequestAction('approve', currentRequestId)
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-request-id="${currentRequestId}"]`);
                    updateRequestUI(row, 'approved', data.item_no);
                    closeAllModals();
                    alert('Request approved successfully!');
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while approving the request.');
            });
    }

    function handleConfirmReject() {
        const reason = document.getElementById('rejectionReason').value.trim();
        const errorMsg = document.getElementById('error-message');
        
        if (!reason) {
            errorMsg.textContent = 'Please provide a reason for rejection.';
            return;
        }
        
        errorMsg.textContent = '';
        
        processRequestAction('reject', currentRequestId, reason)
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-request-id="${currentRequestId}"]`);
                    updateRequestUI(row, 'rejected');
                    closeAllModals();
                    alert('Request rejected successfully!');
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while rejecting the request.');
            });
    }

    function closeAllModals() {
        document.getElementById('approveModal').style.display = 'none';
        document.getElementById('rejectModal').style.display = 'none';
        document.getElementById('rejectionReason').value = '';
        document.getElementById('error-message').textContent = '';
    }

    // ========== GLOBAL EVENT HANDLERS ==========
    window.prevPage = function() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    };

    window.nextPage = function() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    };
});