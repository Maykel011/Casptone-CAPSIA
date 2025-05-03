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


// Search & Filter
document.addEventListener("DOMContentLoaded", function () {
    const rowsPerPage = 10;
    let currentPage = 1;
    const tableBody = document.getElementById("item-table-body");
    const rows = Array.from(tableBody.getElementsByTagName("tr"));
    let filteredRows = [...rows]; // Stores search and date filter results
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);

    function showPage(page) {
        if (filteredRows.length === 0) {
            tableBody.innerHTML = "<tr><td colspan='11'>No results found</td></tr>";
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

    function searchTable() {
        const query = document.getElementById("search-input").value.toLowerCase();
        filterData(query);
    }

    function filterByDate() {
        filterData(document.getElementById("search-input").value.toLowerCase());
    }

    function filterData(query) {
        const startDate = document.getElementById("start-date").value;
        const endDate = document.getElementById("end-date").value;

        filteredRows = rows.filter(row => {
            let rowText = row.textContent.toLowerCase();
            let rowDate = row.cells[2]?.textContent || ""; // Adjust index if needed
            let matchesSearch = rowText.includes(query);

            if (startDate || endDate) {
                let rowTimestamp = new Date(rowDate).getTime();
                let startTimestamp = startDate ? new Date(startDate).getTime() : -Infinity;
                let endTimestamp = endDate ? new Date(endDate).getTime() : Infinity;

                return matchesSearch && rowTimestamp >= startTimestamp && rowTimestamp <= endTimestamp;
            }

            return matchesSearch;
        });

        currentPage = 1;
        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        showPage(currentPage);
    }

    showPage(currentPage);
    window.nextPage = nextPage;
    window.prevPage = prevPage;
    window.searchTable = searchTable;
    window.filterByDate = filterByDate;
});


//for reject button
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("rejectModal");
    const closeModal = document.querySelector(".close");
    const cancelBtn = document.getElementById("cancelReject");
    const confirmReject = document.getElementById("confirmReject");
    const rejectionReason = document.getElementById("rejectionReason");
    const errorMessage = document.getElementById("error-message"); // Error message element
    let currentRow = null; // Store the row where the button was clicked

    // Handle Approve button click
    document.querySelectorAll(".approve-btn").forEach(button => {
        button.addEventListener("click", function () {
            currentRow = this.closest("tr"); // Get the current row
            if (currentRow) {
                currentRow.cells[6].innerText = "Approved";
                currentRow.cells[6].style.color = "green"; // Change color for visibility
            }
        });
    });

    // Open modal when Reject button is clicked
    document.querySelectorAll(".reject-btn").forEach(button => {
        button.addEventListener("click", function () {
            currentRow = this.closest("tr"); // Get the current row
            modal.style.display = "flex"; // Show modal
        });
    });

    // Close modal when clicking "X" or Cancel
    closeModal.onclick = cancelBtn.onclick = function () {
        modal.style.display = "none";
        rejectionReason.value = ""; // Clear input
        errorMessage.textContent = ""; // Clear error message
    };

    // Confirm rejection (Require reason)
    confirmReject.addEventListener("click", function () {
        if (rejectionReason.value.trim() === "") {
            errorMessage.textContent = "Please provide a reason for rejection.";
            errorMessage.style.color = "red"; // Make it visually clear
            return; // Stop function if no input
        }

        errorMessage.textContent = ""; // Clear error if input is valid

        // Update status in the table
        if (currentRow) {
            currentRow.cells[6].innerText = "Rejected";
            currentRow.cells[6].style.color = "red"; // Change color for visibility
        }

        // Close modal
        modal.style.display = "none";
        rejectionReason.value = ""; // Clear input
    });

    // Close modal if clicking outside the modal
    window.addEventListener("click", function (event) {
        if (event.target === modal) {
            modal.style.display = "none";
            rejectionReason.value = ""; // Clear input
            errorMessage.textContent = ""; // Clear error message
        }
    });
});

 // Improved handleAction function
function handleAction(button, action) {
    const row = button.closest('tr');
    const returnId = row.dataset.returnId;
    
    if (action === 'approve') {
        if (confirm('Are you sure you want to approve this return?')) {
            processAction(returnId, 'approve');
        }
    } else if (action === 'reject') {
        const modal = document.getElementById('rejectModal');
        modal.style.display = 'block';
        modal.dataset.returnId = returnId;
        
        document.getElementById('confirmReject').onclick = function() {
            const reason = document.getElementById('rejectionReason').value;
            if (!reason.trim()) {
                document.getElementById('error-message').textContent = 'Please enter a reason';
                return;
            }
            processAction(returnId, 'reject', reason);
            modal.style.display = 'none';
            document.getElementById('rejectionReason').value = '';
        };
    }
}

// Improved processAction function
function processAction(returnId, action, notes = '') {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: action,
            return_id: returnId,
            notes: notes
        })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload(); // Refresh to show updated status
        } else {
            throw new Error(data.message || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

        // Close modal
        document.querySelector('.close').onclick = function() {
            document.getElementById('rejectModal').style.display = 'none';
        };

        document.getElementById('cancelReject').onclick = function() {
            document.getElementById('rejectModal').style.display = 'none';
        };
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
