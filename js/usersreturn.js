document.addEventListener("DOMContentLoaded", function () {
    // Dropdown arrow functionality
    const dropdownArrows = document.querySelectorAll(".arrow-icon");
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

    dropdownArrows.forEach(arrow => {
        let parent = arrow.closest(".dropdown");
        let dropdownText = parent.querySelector(".text").innerText;

        // Apply saved state
        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }

        arrow.addEventListener("click", function (event) {
            event.stopPropagation();
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
        event.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });

    // Table pagination and filtering
    const rowsPerPage = 10;
    let currentPage = 1;
    const tableBody = document.querySelector(".inventory-table tbody");
    const rows = Array.from(tableBody.querySelectorAll("tr"));
    let filteredRows = [...rows];
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);

    function showPage(page) {
        if (filteredRows.length === 0) {
            tableBody.innerHTML = "<tr><td colspan='6'>No results found</td></tr>";
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
            let rowDate = row.cells[3]?.textContent || ""; // Return date column
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

    // Initialize table
    showPage(currentPage);
    window.nextPage = nextPage;
    window.prevPage = prevPage;
    window.searchTable = searchTable;
    window.filterByDate = filterByDate;

    // Condition dropdown handling
    function handleConditionChange(selectElement) {
        const conditionInput = selectElement.nextElementSibling;
        
        if (selectElement.value === "Other") {
            conditionInput.style.display = "block";
            conditionInput.focus();
        } else {
            conditionInput.style.display = "none";
            conditionInput.value = "";
        }
    }

    function updateCondition(inputElement) {
        const selectElement = inputElement.previousElementSibling;
        if (inputElement.value.trim() !== "") {
            selectElement.value = "Other";
        }
    }

    // Attach event listeners to all condition dropdowns
    document.querySelectorAll('.condition-dropdown').forEach(dropdown => {
        dropdown.addEventListener('change', function() {
            handleConditionChange(this);
        });
    });

    // Attach event listeners to all condition inputs
    document.querySelectorAll('.condition-input').forEach(input => {
        input.addEventListener('input', function() {
            updateCondition(this);
        });
    });

    // Close condition inputs when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.matches('.condition-dropdown, .condition-input')) {
            document.querySelectorAll('.condition-input').forEach(input => {
                if (input.value.trim() === "") {
                    input.style.display = "none";
                    const select = input.previousElementSibling;
                    if (select.value === "Other") {
                        select.value = "Good";
                    }
                }
            });
        }
    });

    // Return Item Modal functionality
    const returnButtons = document.querySelectorAll('.return-item-btn');
    const returnModal = document.getElementById('returnModal');
    const closeModal = document.querySelector('#returnModal .close');
    const cancelReturn = document.getElementById('cancelReturn');
    const returnNotes = document.getElementById('return_notes');
    const wordCount = document.getElementById('word-count');
    const notesError = document.getElementById('notes-error');
    const returnForm = document.getElementById('returnForm');

    // Open modal when return button is clicked
    returnButtons.forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const itemId = row.getAttribute('data-item-id');
            const itemName = row.getAttribute('data-item-name');
            const quantity = row.querySelector('td:nth-child(2)').textContent;
            
            // Get condition value (either from dropdown or input)
            const conditionDropdown = row.querySelector('.condition-dropdown');
            const conditionInput = row.querySelector('.condition-input');
            let condition = conditionDropdown.value;
            if (condition === "Other" && conditionInput.value.trim() !== "") {
                condition = conditionInput.value.trim();
            }
            
            // Set form values
            document.getElementById('return_item_id').value = itemId;
            document.getElementById('return_item_name').value = itemName;
            document.getElementById('return_quantity').value = quantity;
            document.getElementById('return_condition').value = condition;
            
            // Reset notes and counter when modal opens
            returnNotes.value = '';
            wordCount.textContent = '0/10 words';
            notesError.style.display = 'none';
            
            returnModal.style.display = 'block';
        });
    });
    
    // Close modal
    closeModal.addEventListener('click', function() {
        returnModal.style.display = 'none';
    });
    
    cancelReturn.addEventListener('click', function() {
        returnModal.style.display = 'none';
    });
    
    // Word count validation
    returnNotes.addEventListener('input', function() {
        const words = this.value.trim().split(/\s+/).filter(word => word.length > 0);
        const wordCountValue = words.length;
        wordCount.textContent = `${wordCountValue}/10 words`;
        
        if (wordCountValue > 10) {
            notesError.style.display = 'block';
            notesError.textContent = 'Please limit your notes to 10 words.';
        } else {
            notesError.style.display = 'none';
        }
    });
    
    // Form submission with validation
returnForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const words = returnNotes.value.trim().split(/\s+/).filter(word => word.length > 0);
    const wordCountValue = words.length;
    
    if (wordCountValue > 10) {
        notesError.style.display = 'block';
        notesError.textContent = 'Please limit your notes to 10 words.';
        return;
    }
    
    try {
        const formData = new FormData(this);
        const response = await fetch('UserItemReturned.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const text = await response.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned an invalid response. Please try again.');
        }
        
        if (!data || typeof data.success === 'undefined') {
            throw new Error('Invalid response format from server');
        }
        
        if (data.success) {
            alert(data.message);
            returnModal.style.display = 'none';
            location.reload();
        } else {
            throw new Error(data.message || 'Request failed');
        }
    } catch (error) {
        console.error('Error:', error);
        alert(error.message);
    }
});
});