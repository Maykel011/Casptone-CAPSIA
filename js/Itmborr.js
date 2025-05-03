document.addEventListener("DOMContentLoaded", function () {
    // ====================
    // DROPDOWN FUNCTIONALITY
    // ====================
    const dropdownArrows = document.querySelectorAll(".arrow-icon");
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

    dropdownArrows.forEach(arrow => {
        const parent = arrow.closest(".dropdown");
        const dropdownText = parent.querySelector(".text").innerText;

        // Apply saved state
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

    // ====================
    // PROFILE DROPDOWN
    // ====================
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon.addEventListener("click", function (event) {
        event.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });

    // ====================
    // MODAL FUNCTIONALITY
    // ====================
    const modal = document.getElementById("rejectModal");
    const closeModal = document.querySelector(".close");
    const cancelBtn = document.getElementById("cancelReject");
    const confirmReject = document.getElementById("confirmReject");
    const rejectionReason = document.getElementById("rejectionReason");
    const errorMessage = document.getElementById("error-message");
    const wordCountDisplay = document.getElementById("wordCount");
    let currentRequestId = null;

    // Modal event listeners
    closeModal.onclick = cancelBtn.onclick = function() {
        modal.style.display = "none";
        rejectionReason.value = "";
        errorMessage.textContent = "";
    };

    window.addEventListener("click", function(event) {
        if (event.target === modal) {
            modal.style.display = "none";
            rejectionReason.value = "";
            errorMessage.textContent = "";
        }
    });

    // Word count functionality for rejection reason
    rejectionReason.addEventListener("input", function() {
        const text = this.value.trim();
        const wordCount = text === "" ? 0 : text.split(/\s+/).length;
        wordCountDisplay.textContent = wordCount;
        
        if (wordCount > 5) {
            errorMessage.textContent = "Reason must be 5 words or less";
        } else {
            errorMessage.textContent = "";
        }
    });

    // ====================
    // TABLE FUNCTIONALITY
    // ====================
    const rowsPerPage = 7;
    let currentPage = 1;
    const tableBody = document.getElementById("item-table-body");
    let rows = Array.from(tableBody.querySelectorAll("tr:not(.no-results)"));
    let filteredRows = [...rows];
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);

    // Initialize status attributes for existing rows
    rows.forEach(row => {
        const statusCell = row.querySelector(".status-cell");
        if (statusCell) {
            row.setAttribute("data-status", statusCell.textContent.trim());
        }
    });

    function showPage(page) {
        // Clear any existing no-results message
        const noResultsRow = tableBody.querySelector(".no-results");
        if (noResultsRow) {
            noResultsRow.remove();
        }

        if (filteredRows.length === 0) {
            // Show no results message
            const noResultsRow = document.createElement("tr");
            noResultsRow.className = "no-results";
            noResultsRow.innerHTML = "<td colspan='11'>No matching requests found</td>";
            tableBody.appendChild(noResultsRow);
            
            document.getElementById("page-number").innerText = "No results";
            document.getElementById("prev-btn").disabled = true;
            document.getElementById("next-btn").disabled = true;
            return;
        }

        // Hide all rows first
        rows.forEach(row => row.style.display = "none");
        
        // Show rows for current page
        const start = (page - 1) * rowsPerPage;
        const end = Math.min(start + rowsPerPage, filteredRows.length);
        
        for (let i = start; i < end; i++) {
            filteredRows[i].style.display = "table-row";
        }

        // Update pagination controls
        document.getElementById("page-number").innerText = `Page ${page} of ${totalPages}`;
        document.getElementById("prev-btn").disabled = page === 1;
        document.getElementById("next-btn").disabled = page === totalPages;
    }

    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    // ====================
    // FILTER FUNCTIONALITY
    // ====================
    const returnedFilter = document.getElementById('returned-filter');
    const customDateContainer = document.getElementById('custom-date-container');
    const customDate = document.getElementById('custom-date');

    returnedFilter.addEventListener('change', function() {
        if (this.value === 'custom') {
            customDateContainer.style.display = 'block';
        } else {
            customDateContainer.style.display = 'none';
            filterData();
        }
    });

    customDate.addEventListener('change', filterData);

    function filterData() {
        const query = document.getElementById("search-box").value.toLowerCase();
        const filterValue = returnedFilter.value;
        let dateFilter = null;

        // Calculate date ranges based on filter
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (filterValue === 'today') {
            dateFilter = today.toISOString().split('T')[0];
        } else if (filterValue === 'this_week') {
            const firstDay = new Date(today);
            firstDay.setDate(firstDay.getDate() - firstDay.getDay());
            dateFilter = firstDay.toISOString().split('T')[0];
        } else if (filterValue === 'this_month') {
            dateFilter = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
        } else if (filterValue === 'custom' && customDate.value) {
            dateFilter = customDate.value;
        }

        filteredRows = rows.filter(row => {
            let rowText = '';
            // Get all cell text content
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                rowText += cell.textContent.toLowerCase() + ' ';
            });

            let matchesSearch = rowText.includes(query);
            
            // Get the processed_at date (assuming it's in the processed-time span)
            let processedAtSpan = row.querySelector('.processed-time');
            let processedDate = processedAtSpan ? processedAtSpan.textContent.trim() : null;
            
            // Filter by processed date if applicable
            if (dateFilter && processedDate) {
                try {
                    const rowDate = new Date(processedDate).toISOString().split('T')[0];
                    
                    if (filterValue === 'today') {
                        return matchesSearch && rowDate === dateFilter;
                    } else if (filterValue === 'this_week' || filterValue === 'this_month') {
                        const filterDate = new Date(dateFilter);
                        const rowDateObj = new Date(rowDate);
                        return matchesSearch && rowDateObj >= filterDate;
                    } else if (filterValue === 'custom') {
                        return matchesSearch && rowDate === dateFilter;
                    }
                } catch (e) {
                    console.error("Error parsing date:", e);
                    return matchesSearch;
                }
            } else if (dateFilter) {
                // If we have a date filter but no processed date, exclude the row
                return false;
            }

            return matchesSearch;
        });

        currentPage = 1;
        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        showPage(currentPage);
    }

    // ====================
    // AUTO REFRESH FUNCTIONALITY
    // ====================
    let refreshInterval = 30000; // 30 seconds
    let refreshTimer;
    
    function startAutoRefresh() {
        refreshTimer = setInterval(refreshTable, refreshInterval);
    }
    
    function stopAutoRefresh() {
        clearInterval(refreshTimer);
    }
    
    async function refreshTable() {
        try {
            const response = await fetch("ItemBorrowed.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: "action=refresh"
            });
            
            const data = await response.json();
            
            if (data.rows && data.rows.length > 0) {
                tableBody.innerHTML = "";
                
                data.rows.forEach(row => {
                    const tr = document.createElement("tr");
                    tr.setAttribute("data-request-id", row.request_id);
                    
                    // Create all table cells
                    const cells = [
                        row.username,
                        row.item_name,
                        row.item_category,
                        row.date_needed,
                        row.return_date,
                        row.quantity,
                        row.purpose,
                        row.notes,
                        "", // Status cell will be handled separately
                        row.request_date,
                        ""  // Action cell will be handled separately
                    ];
                    
                    cells.forEach((cellContent, index) => {
                        const td = document.createElement("td");
                        if (index === 8) { // Status cell
                            td.className = `status-cell ${row.status.toLowerCase()}`;
                            
                            const processedTime = row.processed_at ? new Date(row.processed_at).toLocaleString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            }) : '';
                            
                            if (row.status === "Approved") {
                                td.innerHTML = `<span class="status-approved" title="Approved on ${processedTime}">Approved</span><span class="processed-time">${processedTime}</span>`;
                                tr.setAttribute("data-status", "Approved");
                            } else if (row.status === "Rejected") {
                                td.innerHTML = `<span class="status-rejected" title="Rejected on ${processedTime}">Rejected</span><span class="processed-time">${processedTime}</span>`;
                                if (row.rejection_reason) {
                                    td.innerHTML += `<div class="rejection-reason">${row.rejection_reason}</div>`;
                                }
                                tr.setAttribute("data-status", "Rejected");
                            } else if (row.status === "Returned") {
                                td.innerHTML = `<span class="status-returned" title="Returned on ${processedTime}">Returned</span><span class="processed-time">${processedTime}</span>`;
                                tr.setAttribute("data-status", "Returned");
                            } else {
                                td.innerHTML = `<span title="Pending approval">${row.status}</span>`;
                                tr.setAttribute("data-status", row.status);
                            }
                        } else if (index === 10) { // Action cell
                            td.className = "action-cell";
                            if (row.status === "Pending") {
                                const approveBtn = document.createElement("button");
                                approveBtn.className = "approve-btn";
                                approveBtn.setAttribute("data-request-id", row.request_id);
                                approveBtn.textContent = "Approve";
                                
                                const rejectBtn = document.createElement("button");
                                rejectBtn.className = "reject-btn";
                                rejectBtn.setAttribute("data-request-id", row.request_id);
                                rejectBtn.textContent = "Reject";
                                
                                td.appendChild(approveBtn);
                                td.appendChild(rejectBtn);
                            } else {
                                const span = document.createElement("span");
                                span.className = "processed-label";
                                span.textContent = "Processed";
                                td.appendChild(span);
                            }
                        } else {
                            td.textContent = cellContent;
                        }
                        tr.appendChild(td);
                    });
                    
                    tableBody.appendChild(tr);
                });
                
                // Reinitialize rows and filteredRows
                rows = Array.from(tableBody.querySelectorAll("tr:not(.no-results)"));
                filteredRows = [...rows];
                totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                showPage(currentPage);
            }
        } catch (error) {
            console.error("Error refreshing table:", error);
        }
    }

    // ====================
    // REQUEST HANDLING (using event delegation)
    // ====================
    function handleApprove(event) {
        const requestId = this.getAttribute("data-request-id");
        
        if (confirm("Are you sure you want to approve this request?")) {
            fetch("ItemBorrowed.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `action=approve&request_id=${requestId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshTable();
                    showNotification("Request approved successfully", "success");
                } else {
                    showNotification(data.error || "Error approving request", "error");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showNotification("An error occurred while approving the request", "error");
            });
        }
    }
    
    function handleReject(event) {
        const requestId = this.getAttribute("data-request-id");
        currentRequestId = requestId;
        modal.style.display = "flex";
        rejectionReason.focus();
    }
    
    // Update confirm rejection handler
    confirmReject.addEventListener("click", function() {
        const reason = rejectionReason.value.trim();
        const wordCount = reason === "" ? 0 : reason.split(/\s+/).length;
        
        if (!reason) {
            errorMessage.textContent = "Please provide a reason for rejection.";
            return;
        }
        
        if (wordCount > 5) {
            errorMessage.textContent = "Reason must be 5 words or less";
            return;
        }
        
        fetch("ItemBorrowed.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=reject&request_id=${currentRequestId}&reason=${encodeURIComponent(reason)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                refreshTable();
                modal.style.display = "none";
                rejectionReason.value = "";
                errorMessage.textContent = "";
                showNotification("Request rejected successfully", "success");
            } else {
                showNotification(data.error || "Error rejecting request", "error");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showNotification("An error occurred while rejecting the request", "error");
        });
    });

    function handleReturn(event) {
        const requestId = this.getAttribute("data-request-id");
        
        if (confirm("Are you sure this item has been returned?")) {
            fetch("ItemBorrowed.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `action=return&request_id=${requestId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshTable();
                    showNotification("Item marked as returned", "success");
                } else {
                    showNotification("Error marking item as returned", "error");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showNotification("An error occurred", "error");
            });
        }
    }

    // ====================
    // EVENT LISTENERS
    // ====================
    document.getElementById("search-box").addEventListener("input", debounce(filterData, 300));
    document.getElementById("prev-btn").addEventListener("click", prevPage);
    document.getElementById("next-btn").addEventListener("click", nextPage);

    // Use event delegation for approve/reject buttons
    tableBody.addEventListener("click", function(event) {
        if (event.target.classList.contains("approve-btn")) {
            handleApprove.call(event.target, event);
        } else if (event.target.classList.contains("reject-btn")) {
            handleReject.call(event.target, event);
        } else if (event.target.classList.contains("return-btn")) {
            handleReturn.call(event.target, event);
        }
    });

    // ====================
    // HELPER FUNCTIONS
    // ====================
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    function showNotification(message, type) {
        const notification = document.createElement("div");
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add("fade-out");
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    }

    // ====================
    // INITIALIZATION
    // ====================
    showPage(currentPage);
    startAutoRefresh();
    
    // Stop auto-refresh when page is unloaded
    window.addEventListener("beforeunload", stopAutoRefresh);
});

// Make pagination functions available globally
window.nextPage = function() {
    const event = new Event('click');
    document.getElementById("next-btn").dispatchEvent(event);
};

window.prevPage = function() {
    const event = new Event('click');
    document.getElementById("prev-btn").dispatchEvent(event);
};