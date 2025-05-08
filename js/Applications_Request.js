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
    const rejectModal = document.getElementById("rejectModal");
    const requestListModal = document.getElementById("requestListModal");
    const closeModalButtons = document.querySelectorAll(".close");
    const cancelBtn = document.getElementById("cancelReject");
    const confirmReject = document.getElementById("confirmReject");
    const rejectionReason = document.getElementById("rejectionReason");
    const errorMessage = document.getElementById("error-message");
    const wordCountDisplay = document.getElementById("wordCount");
    const viewListBtn = document.getElementById("view-list-btn");
    const refreshListBtn = document.getElementById("refresh-list-btn");
    let currentRequestId = null;

    // Modal event listeners
    closeModalButtons.forEach(btn => {
        btn.onclick = function() {
            rejectModal.style.display = "none";
            requestListModal.style.display = "none";
            rejectionReason.value = "";
            errorMessage.textContent = "";
        };
    });

    cancelBtn.onclick = function() {
        rejectModal.style.display = "none";
        rejectionReason.value = "";
        errorMessage.textContent = "";
    };

    window.addEventListener("click", function(event) {
        if (event.target === rejectModal || event.target === requestListModal) {
            rejectModal.style.display = "none";
            requestListModal.style.display = "none";
            rejectionReason.value = "";
            errorMessage.textContent = "";
        }
    });

    // View List Button
    viewListBtn.addEventListener("click", function() {
        requestListModal.style.display = "flex";
        loadRequestList();
    });

    // Refresh List Button
    refreshListBtn.addEventListener("click", loadRequestList);

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
    const statusFilter = document.getElementById('status-filter');
    const searchBox = document.getElementById('search-box');

    statusFilter.addEventListener('change', filterData);
    searchBox.addEventListener('input', debounce(filterData, 300));

    function filterData() {
        const query = searchBox.value.toLowerCase();
        const statusValue = statusFilter.value;

        filteredRows = rows.filter(row => {
            let rowText = '';
            // Get all cell text content
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                rowText += cell.textContent.toLowerCase() + ' ';
            });

            let matchesSearch = rowText.includes(query);
            let matchesStatus = statusValue === 'all' || 
                              row.getAttribute('data-status').toLowerCase().includes(statusValue.toLowerCase());

            return matchesSearch && matchesStatus;
        });

        currentPage = 1;
        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        showPage(currentPage);
    }

    // ====================
    // LOAD REQUEST LIST FOR MODAL
    // ====================
    async function loadRequestList() {
        try {
            const response = await fetch("Application_Request.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: "action=get_requests"
            });
            
            const data = await response.json();
            const requestListBody = document.getElementById("request-list-body");
            requestListBody.innerHTML = "";

            if (data.success && data.requests.length > 0) {
                data.requests.forEach(request => {
                    const tr = document.createElement("tr");
                    tr.setAttribute("data-request-id", request.request_id);
                    
                    // Name
                    const nameTd = document.createElement("td");
                    nameTd.textContent = request.username;
                    tr.appendChild(nameTd);
                    
                    // Item Name
                    const itemNameTd = document.createElement("td");
                    itemNameTd.textContent = request.item_name;
                    tr.appendChild(itemNameTd);
                    
                    // Item Type
                    const itemTypeTd = document.createElement("td");
                    itemTypeTd.textContent = request.item_category;
                    tr.appendChild(itemTypeTd);
                    
                    // Quantity
                    const quantityTd = document.createElement("td");
                    quantityTd.textContent = request.quantity;
                    tr.appendChild(quantityTd);
                    
                    // Status
                    const statusTd = document.createElement("td");
                    statusTd.className = `status-cell ${request.status.toLowerCase().replace(' ', '-')}`;
                    
                    const processedTime = request.processed_at ? new Date(request.processed_at).toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    }) : '';
                    
                    // In the same loadRequestList function, update the status display
if (request.status === "For Checking") {
    statusTd.innerHTML = `<span class="status-checking">${request.status}</span>`;
} else if (request.status === "For Releasing") {
    statusTd.innerHTML = `<span class="status-releasing">${request.status}</span>`;
} else if (request.status === "Released") {
    statusTd.innerHTML = `<span class="status-released">${request.status}</span>`;
} else if (request.status === "Return Pending") {
    statusTd.innerHTML = `<span class="status-pending">${request.status}</span>`;
} else {
    statusTd.textContent = request.status;
}
                    
                    if (processedTime) {
                        statusTd.innerHTML += `<span class="processed-time">${processedTime}</span>`;
                    }
                    
                    tr.appendChild(statusTd);
                    
                    // Request Date
                    const requestDateTd = document.createElement("td");
                    requestDateTd.textContent = new Date(request.request_date).toLocaleDateString();
                    tr.appendChild(requestDateTd);
                    
                    // Actions
                    const actionsTd = document.createElement("td");
                    actionsTd.className = "action-cell";
                    
                    // In the loadRequestList function, update the actions section
if (request.status === "For Checking") {
    const processBtn = document.createElement("button");
    processBtn.className = "process-btn";
    processBtn.setAttribute("data-request-id", request.request_id);
    processBtn.innerHTML = '<i class="fas fa-cog"></i> Process';
    actionsTd.appendChild(processBtn);
} else if (request.status === "For Releasing") {
    const releaseBtn = document.createElement("button");
    releaseBtn.className = "release-btn";
    releaseBtn.setAttribute("data-request-id", request.request_id);
    releaseBtn.innerHTML = '<i class="fas fa-check-circle"></i> Release';
    actionsTd.appendChild(releaseBtn);
} else if (request.status === "Released" || request.status === "Return Pending") {
    const returnBtn = document.createElement("button");
    returnBtn.className = "return-btn";
    returnBtn.setAttribute("data-request-id", request.request_id);
    returnBtn.innerHTML = '<i class="fas fa-undo"></i> Return';
    actionsTd.appendChild(returnBtn);
} else {
    actionsTd.textContent = "No action";
}
                    
                    tr.appendChild(actionsTd);
                    requestListBody.appendChild(tr);
                });
            } else {
                const tr = document.createElement("tr");
                tr.innerHTML = "<td colspan='7'>No requests to process</td>";
                requestListBody.appendChild(tr);
            }
        } catch (error) {
            console.error("Error loading request list:", error);
            showNotification("Error loading request list", "error");
        }
    }

    // ====================
    // REQUEST HANDLING (using event delegation)
    // ====================
// Modern Notification System
function showChurchNotification(message, type = 'success') {
    const notification = document.getElementById('churchNotification');
    const title = document.getElementById('notificationTitle');
    const msg = document.getElementById('notificationMessage');
    const icon = notification.querySelector('.notification-icon i');
    const iconContainer = notification.querySelector('.notification-icon');
    
    // Set content and style based on type
    if (type === 'success') {
        title.textContent = 'Success';
        icon.className = 'fas fa-check-circle';
        iconContainer.className = 'notification-icon success';
        notification.className = 'notification-modal success';
    } else if (type === 'error') {
        title.textContent = 'Error';
        icon.className = 'fas fa-exclamation-circle';
        iconContainer.className = 'notification-icon error';
        notification.className = 'notification-modal error';
    } else {
        title.textContent = 'Info';
        icon.className = 'fas fa-info-circle';
        iconContainer.className = 'notification-icon info';
        notification.className = 'notification-modal';
    }
    
    msg.textContent = message;
    notification.style.display = 'block';
    
    // Auto-close after 5 seconds
    setTimeout(() => {
        closeChurchNotification();
    }, 5000);
    
    // Close button event
    notification.querySelector('.notification-close').onclick = closeChurchNotification;
    notification.querySelector('.notification-btn').onclick = closeChurchNotification;
}

function closeChurchNotification() {
    const notification = document.getElementById('churchNotification');
    notification.style.animation = 'slideOut 0.5s forwards';
    setTimeout(() => {
        notification.style.display = 'none';
        notification.style.animation = 'slideIn 0.5s forwards';
    }, 500);
}

// Modern Confirmation Dialog
function showChurchConfirmation(message) {
    return new Promise((resolve) => {
        const confirmation = document.getElementById('churchConfirmation');
        const msg = document.getElementById('confirmationMessage');
        
        msg.textContent = message;
        confirmation.style.display = 'flex';
        
        document.getElementById('confirmAction').onclick = function() {
            confirmation.style.display = 'none';
            resolve(true);
        };
        
        document.getElementById('cancelAction').onclick = function() {
            confirmation.style.display = 'none';
            resolve(false);
        };
    });
}

// Updated handler functions with modern UI
async function handleApprove(event) {
    const requestId = this.getAttribute("data-request-id");
    const confirmed = await showChurchConfirmation("Are you sure you want to approve this request? It will be moved to 'For Checking' status.");
    
    if (confirmed) {
        fetch("Application_Request.php", {
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
                showChurchNotification("Request approved and moved to For Checking", "success");
            } else {
                showChurchNotification(data.error || "Error approving request", "error");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showChurchNotification("An error occurred while approving the request", "error");
        });
    }
}

async function handleProcess(event) {
    const requestId = this.getAttribute("data-request-id");
    const confirmed = await showChurchConfirmation("Are you sure you want to process this item? It will be moved to 'For Releasing' status.");
    
    if (confirmed) {
        fetch("Application_Request.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=process&request_id=${requestId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadRequestList();
                refreshTable();
                showChurchNotification("Request processed and moved to For Releasing", "success");
            } else {
                showChurchNotification("Error processing request", "error");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showChurchNotification("An error occurred while processing the request", "error");
        });
    }
}

async function handleRelease(event) {
    const requestId = this.getAttribute("data-request-id");
    const confirmed = await showChurchConfirmation("Are you sure you want to release this item? It will be marked as 'Released'.");
    
    if (confirmed) {
        fetch("Application_Request.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=release&request_id=${requestId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadRequestList();
                refreshTable();
                showChurchNotification("Item released successfully", "success");
            } else {
                showChurchNotification("Error releasing item", "error");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showChurchNotification("An error occurred while releasing the item", "error");
        });
    }
}

async function handleReturn(event) {
    const requestId = this.getAttribute("data-request-id");
    const confirmed = await showChurchConfirmation("Are you sure you want to confirm this return?");
    
    if (confirmed) {
        fetch("Application_Request.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=return&request_id=${requestId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadRequestList();
                refreshTable();
                showChurchNotification("Return confirmed successfully", "success");
            } else {
                showChurchNotification(data.error || "Error confirming return", "error");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showChurchNotification("An error occurred while confirming return", "error");
        });
    }
}

function handleReject(event) {
    const requestId = this.getAttribute("data-request-id");
    currentRequestId = requestId;
    rejectModal.style.display = "flex";
    rejectionReason.focus();
}

// ====================
// REFRESH TABLE FUNCTION
// ====================
async function refreshTable() {
    try {
        const response = await fetch("Application_Request.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "action=refresh"
        });
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Check if data is valid
        if (!data || !Array.isArray(data.rows)) {
            throw new Error("Invalid data received from server");
        }
        
        // Get or create table body element
        const tableBody = document.querySelector("#requestsTable tbody") || 
                          document.createElement("tbody");
        if (!document.querySelector("#requestsTable tbody")) {
            document.querySelector("#requestsTable").appendChild(tableBody);
        }
        
        // Clear existing rows
        tableBody.innerHTML = "";
        
        // Handle empty data case
        if (data.rows.length === 0) {
            const tr = document.createElement("tr");
            tr.className = "no-results";
            const td = document.createElement("td");
            td.colSpan = 11;
            td.textContent = "No requests found";
            tr.appendChild(td);
            tableBody.appendChild(tr);
            return;
        }
        
        // Process each row
        data.rows.forEach(row => {
            const tr = document.createElement("tr");
            tr.setAttribute("data-request-id", row.request_id);
            
            // Create cells
            const cells = [
                row.username || '',
                row.item_name || '',
                row.item_category || '',
                row.date_needed || '',
                row.return_date || '',
                row.quantity || '',
                row.purpose || '',
                row.notes || '',
                "", // Status cell
                row.request_date || '',
                ""  // Action cell
            ];
            
            cells.forEach((cellContent, index) => {
                const td = document.createElement("td");
                
                if (index === 8) { // Status cell
                    const status = row.status || 'Pending';
                    const statusClass = status.toLowerCase().replace(' ', '-');
                    td.className = `status-cell ${statusClass}`;
                    
                    const processedTime = row.processed_at ? new Date(row.processed_at).toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    }) : '';
                    
                    // Status handling
                    if (status === "Approved") {
                        td.innerHTML = `<span class="status-approved" title="Approved on ${processedTime}">Approved</span><span class="processed-time">${processedTime}</span>`;
                    } else if (status === "Rejected") {
                        td.innerHTML = `<span class="status-rejected" title="Rejected on ${processedTime}">Rejected</span><span class="processed-time">${processedTime}</span>`;
                        if (row.rejection_reason) {
                            td.innerHTML += `<div class="rejection-reason">${row.rejection_reason}</div>`;
                        }
                    } else if (status === "Returned") {
                        td.innerHTML = `<span class="status-returned" title="Returned on ${processedTime}">Returned</span><span class="processed-time">${processedTime}</span>`;
                    } else if (status === "For Checking") {
                        td.innerHTML = `<span class="status-checking" title="For Checking since ${processedTime}">For Checking</span><span class="processed-time">${processedTime}</span>`;
                    } else if (status === "For Releasing") {
                        td.innerHTML = `<span class="status-releasing" title="For Releasing since ${processedTime}">For Releasing</span><span class="processed-time">${processedTime}</span>`;
                    } else if (status === "Return Pending") {
                        td.innerHTML = `<span class="status-pending" title="Return Pending since ${processedTime}">Return Pending</span><span class="processed-time">${processedTime}</span>`;
                    } else if (status === "Released") {
                        td.innerHTML = `<span class="status-released" title="Released on ${processedTime}">Released</span><span class="processed-time">${processedTime}</span>`;
                    } else {
                        td.innerHTML = `<span title="Pending approval">${status}</span>`;
                    }
                    
                    tr.setAttribute("data-status", status);
                    
                } else if (index === 10) { // Action cell
                    td.className = "action-cell";
                    const status = row.status || 'Pending';
                    
                    if (status === "Pending") {
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
                    } else if (status === "Released") {
                        const returnBtn = document.createElement("button");
                        returnBtn.className = "return-btn";
                        returnBtn.setAttribute("data-request-id", row.request_id);
                        returnBtn.textContent = "Return";
                        td.appendChild(returnBtn);
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
        
        // Initialize pagination if those functions exist
        if (typeof initPagination === 'function') {
            initPagination();
        }
        
    } catch (error) {
        console.error("Error refreshing table:", error);
        if (typeof showNotification === 'function') {
            showNotification("Error refreshing table data", "error");
        } else {
            alert("Error refreshing table data: " + error.message);
        }
    }
}

    // ====================
    // EVENT LISTENERS
    // ====================
    document.getElementById("prev-btn").addEventListener("click", prevPage);
    document.getElementById("next-btn").addEventListener("click", nextPage);

    // Use event delegation for all buttons
    tableBody.addEventListener("click", function(event) {
        if (event.target.classList.contains("approve-btn")) {
            handleApprove.call(event.target, event);
        } else if (event.target.classList.contains("reject-btn")) {
            handleReject.call(event.target, event);
        } else if (event.target.classList.contains("return-btn")) {
            handleReturn.call(event.target, event);
        }
    });

    // Event delegation for modal table buttons
    document.getElementById("request-list-body").addEventListener("click", function(event) {
        if (event.target.classList.contains("process-btn") || 
            (event.target.parentElement && event.target.parentElement.classList.contains("process-btn"))) {
            const btn = event.target.classList.contains("process-btn") ? event.target : event.target.parentElement;
            handleProcess.call(btn, event);
        } else if (event.target.classList.contains("release-btn") || 
                 (event.target.parentElement && event.target.parentElement.classList.contains("release-btn"))) {
            const btn = event.target.classList.contains("release-btn") ? event.target : event.target.parentElement;
            handleRelease.call(btn, event);
        } else if (event.target.classList.contains("return-btn") || 
                 (event.target.parentElement && event.target.parentElement.classList.contains("return-btn"))) {
            const btn = event.target.classList.contains("return-btn") ? event.target : event.target.parentElement;
            handleReturn.call(btn, event);
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
    filterData();
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