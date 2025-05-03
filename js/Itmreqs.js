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

    // Profile Dropdown
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

    // Table pagination and filtering
    const rowsPerPage = 5;
    let currentPage = 1;
    const tableBody = document.querySelector(".item-table tbody");
    const rows = Array.from(tableBody.getElementsByTagName("tr"));
    let filteredRows = [...rows];
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);

    function showPage(page) {
        if (filteredRows.length === 0) {
            tableBody.innerHTML = "<tr><td colspan='7'>No results found</td></tr>";
            document.getElementById("page-number").innerText = "No results";
            document.getElementById("prev-btn").disabled = true;
            document.getElementById("next-btn").disabled = true;
            return;
        }

        rows.forEach(row => (row.style.display = "none"));
        let start = (page - 1) * rowsPerPage;
        let end = start + rowsPerPage;
        filteredRows.slice(start, end).forEach(row => (row.style.display = "table-row"));

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

    function filterTable() {
        const searchQuery = document.getElementById("search-box").value.toLowerCase();
        const startDate = document.getElementById("start-date").value;
        const endDate = document.getElementById("end-date").value;

        filteredRows = rows.filter(row => {
            let rowData = row.innerText.toLowerCase();
            let dateCell = row.children[3].innerText;

            let matchesSearch = rowData.includes(searchQuery);
            let matchesDate = true;

            if (startDate && endDate) {
                matchesDate = dateCell >= startDate && dateCell <= endDate;
            }

            return matchesSearch && matchesDate;
        });

        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        currentPage = 1;
        showPage(currentPage);
    }

    document.getElementById("search-box").addEventListener("input", filterTable);
    document.getElementById("start-date").addEventListener("change", filterTable);
    document.getElementById("end-date").addEventListener("change", filterTable);
    document.getElementById("prev-btn").addEventListener("click", prevPage);
    document.getElementById("next-btn").addEventListener("click", nextPage);

    showPage(currentPage);

    // Request approval/rejection handling
    const approveButtons = document.querySelectorAll(".approve-btn");
    const rejectButtons = document.querySelectorAll(".reject-btn");
    const rejectModal = document.getElementById("rejectModal");
    const rejectionReason = document.getElementById("rejectionReason");
    const errorMessage = document.getElementById("error-message");
    const confirmReject = document.getElementById("confirmReject");
    const cancelReject = document.getElementById("cancelReject");
    let currentRequestId = null;

    // Verify request status with backend
    async function verifyRequestStatus(requestId, expectedStatus) {
        try {
            const response = await fetch(`ItemRequest.php?verify_request=1&request_id=${requestId}`);
            const data = await response.json();
            return data.status === expectedStatus;
        } catch (error) {
            console.error("Verification failed:", error);
            return false;
        }
    }

    // Approve button handler
    approveButtons.forEach(button => {
        button.addEventListener("click", async function() {
            if (!confirm("Are you sure you want to approve this request?")) return;
            
            const requestId = this.closest("tr").dataset.requestId;
            const row = this.closest("tr");
            
            try {
                const response = await fetch("ItemRequest.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `action=approve&request_id=${requestId}`
                });
                
                if (!response.ok) throw new Error("Network response was not ok");
                
                const data = await response.json();
                
                if (data.success) {
                    // Update UI immediately
                    row.dataset.status = "Approved";
                    const statusCell = row.cells[5]; // Status cell (6th column)
                    statusCell.textContent = "Approved";
                    statusCell.style.color = "green";
                    
                    // Remove action buttons
                    const actionCell = row.cells[6]; // Actions cell (7th column)
                    actionCell.innerHTML = "";
                    
                    // Add visual feedback
                    row.style.backgroundColor = "#e6f7e6";
                    
                    // Verify with backend
                    const verified = await verifyRequestStatus(requestId, "Approved");
                    if (!verified) throw new Error("Status verification failed");
                    
                    alert("Request approved successfully!");
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error("Error:", error);
                alert("Error: " + error.message);
                location.reload();
            }
        });
    });

    // Reject button handler
    rejectButtons.forEach(button => {
        button.addEventListener("click", function(e) {
            e.preventDefault();
            currentRequestId = this.closest("tr").dataset.requestId;
            rejectionReason.value = "";
            errorMessage.textContent = "";
            rejectModal.style.display = "flex";
        });
    });

    confirmReject.addEventListener("click", async function() {
        const reason = rejectionReason.value.trim();
        if (!reason) {
            errorMessage.textContent = "Rejection reason is required.";
            return;
        }

        try {
            const response = await fetch("ItemRequest.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `action=reject&request_id=${currentRequestId}&reason=${encodeURIComponent(reason)}`
            });
            
            if (!response.ok) throw new Error("Network response was not ok");
            
            const data = await response.json();
            
            if (data.success) {
                const row = document.querySelector(`tr[data-request-id="${currentRequestId}"]`);
                row.dataset.status = "Rejected";
                const statusCell = row.cells[5]; // Status cell
                statusCell.textContent = "Rejected";
                statusCell.style.color = "red";
                
                // Remove action buttons
                const actionCell = row.cells[6]; // Actions cell
                actionCell.innerHTML = "";
                
                // Add visual feedback
                row.style.backgroundColor = "#ffebeb";
                
                // Verify with backend
                const verified = await verifyRequestStatus(currentRequestId, "Rejected");
                if (!verified) throw new Error("Status verification failed");
                
                alert("Request rejected successfully!");
                rejectModal.style.display = "none";
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error("Error:", error);
            alert("Error: " + error.message);
            location.reload();
        }
    });

    cancelReject.addEventListener("click", function() {
        rejectModal.style.display = "none";
    });

    window.addEventListener("click", function(event) {
        if (event.target === rejectModal) {
            rejectModal.style.display = "none";
        }
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
