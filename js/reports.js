/**
 * reports.js - Handles functionality for the Reports page including:
 * - Pagination
 * - Search and filtering
 * - Download options
 * - Checkbox selection
 * - Dropdown menus
 */

document.addEventListener("DOMContentLoaded", function () {
    // ========== GLOBAL VARIABLES ==========
    const rowsPerPage = 10;
    let currentPage = 1;
    let totalPages = 1;
    let filteredRows = [];
    
    // DOM Elements
    const tableBody = document.querySelector(".report-table tbody");
    const globalSearch = document.getElementById('globalSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const unitFilter = document.getElementById('unitFilter');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const pageNumber = document.getElementById('page-number');
    const selectAllCheckbox = document.querySelector(".select-all");
    const itemCheckboxes = document.querySelectorAll(".select-checkbox");
    const downloadLinks = document.querySelectorAll(".download-pdf, .download-xlsx");
    const selectedItemsInput = document.getElementById("selectedItems");
    const resetBtn = document.querySelector('.filter-reset');
    
    // ========== INITIALIZATION ==========
    initializeComponents();
    
    /**
     * Initializes all components of the page
     */
    function initializeComponents() {
        initializeDropdowns();
        initializeCheckboxes();
        initializeTable();
        setupEventListeners();
    }

    // ========== DROPDOWN FUNCTIONALITY ==========
    /**
     * Initializes dropdown menus including:
     * - User profile dropdown
     * - Sidebar dropdown persistence
     */
    function initializeDropdowns() {
        // User profile dropdown
        const userIcon = document.getElementById("userIcon");
        const userDropdown = document.getElementById("userDropdown");

        if (userIcon && userDropdown) {
            userIcon.addEventListener("click", function (event) {
                event.stopPropagation();
                userDropdown.classList.toggle("show");
            });

            document.addEventListener("click", function (event) {
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

            arrow.addEventListener("click", function (event) {
                event.stopPropagation();
                parent.classList.toggle("active");
                savedDropdownState[dropdownText] = parent.classList.contains("active");
                localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
            });
        });
    }

    // ========== CHECKBOX FUNCTIONALITY ==========
    /**
     * Initializes checkbox functionality including:
     * - Select all checkbox
     * - Individual item checkboxes
     */
    function initializeCheckboxes() {
        // Toggle all checkboxes
        window.toggleSelectAll = function(checkbox) {
            itemCheckboxes.forEach(itemCheckbox => {
                itemCheckbox.checked = checkbox.checked;
            });
        };

        // Individual checkbox behavior
        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener("change", function() {
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });

        checkDataAvailability();
    }

    /**
     * Checks if there is data available and shows/hides checkboxes accordingly
     */
    function checkDataAvailability() {
        const rows = tableBody.querySelectorAll("tr");
        let hasData = false;

        rows.forEach(row => {
            if (!row.classList.contains("no-data")) {
                hasData = true;
                const checkbox = row.querySelector(".select-checkbox");
                if (checkbox) checkbox.style.display = "inline-block";
            }
        });

        if (!hasData) {
            tableBody.innerHTML = `
                <tr class="no-data">
                    <td colspan="11" style="text-align:center; padding: 10px;">No data available</td>
                </tr>
            `;
        }
    }

    // ========== TABLE & PAGINATION ==========
    /**
     * Initializes the table and pagination
     */
    function initializeTable() {
        // Create array of all data rows (excluding the no-data row if present)
        filteredRows = Array.from(tableBody.querySelectorAll("tr:not(.no-data)"));
        
        // Initially show all rows
        filteredRows.forEach(row => row.style.display = 'table-row');
        
        updatePagination();
        showPage(currentPage);
    }

    /**
     * Updates pagination controls and information
     */
    function updatePagination() {
        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        pageNumber.textContent = `Page ${currentPage} of ${totalPages}`;
        prevBtn.disabled = (currentPage === 1);
        nextBtn.disabled = (currentPage === totalPages || totalPages === 0);
    }

    /**
     * Shows a specific page of results
     * @param {number} page - The page number to show
     */
    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        // First hide all rows
        filteredRows.forEach(row => {
            row.style.display = "none";
        });

        // Then show only the rows for this page
        for (let i = start; i < end && i < filteredRows.length; i++) {
            if (filteredRows[i]) {
                filteredRows[i].style.display = "table-row";
            }
        }

        updatePagination();
    }

    // ========== FILTER FUNCTIONALITY ==========
    /**
     * Sets up event listeners for filter controls
     */
    function setupEventListeners() {
        globalSearch.addEventListener('input', filterTable);
        categoryFilter.addEventListener('change', filterTable);
        unitFilter.addEventListener('change', filterTable);
        resetBtn.addEventListener('click', resetFilters);
        
        // Pagination controls
        prevBtn.addEventListener('click', prevPage);
        nextBtn.addEventListener('click', nextPage);
        
        // Download links
        downloadLinks.forEach(link => {
            link.addEventListener("click", handleDownloadClick);
        });
    }

    /**
     * Filters the table based on search and filter criteria
     */
    function filterTable() {
        const searchTerm = globalSearch.value.toLowerCase();
        const selectedCategory = categoryFilter.value ? categoryFilter.value.toLowerCase() : '';
        const selectedUnit = unitFilter.value ? unitFilter.value.toLowerCase() : '';

        const allRows = Array.from(tableBody.querySelectorAll('tr:not(.no-data)'));
        filteredRows = [];
        let visibleRows = 0;
        
        allRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            // Correct column indexes (0-based):
            const itemName = cells[3].textContent.toLowerCase(); // Column 3: Item Name
            const description = cells[4].textContent.toLowerCase(); // Column 4: Description
            const category = cells[5].textContent.toLowerCase(); // Column 5: Category
            const unit = cells[9].textContent.toLowerCase(); // Column 9: Unit
            
            const matchesSearch = searchTerm === '' || 
                itemName.includes(searchTerm) || 
                description.includes(searchTerm) ||
                cells[2].textContent.toLowerCase().includes(searchTerm); // Model No
            
            const matchesCategory = selectedCategory === '' || category === selectedCategory;
            const matchesUnit = selectedUnit === '' || unit === selectedUnit;
            
            if (matchesSearch && matchesCategory && matchesUnit) {
                filteredRows.push(row);
                visibleRows++;
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        currentPage = 1;
        updatePagination();
        showPage(currentPage);
        updateNoDataMessage(visibleRows);
    }

    /**
     * Updates the "no data" message based on visible rows
     * @param {number} visibleRows - Count of currently visible rows
     */
    function updateNoDataMessage(visibleRows) {
        const noDataRow = document.querySelector('.no-data');
        if (visibleRows === 0) {
            if (!noDataRow) {
                tableBody.innerHTML += `
                    <tr class="no-data">
                        <td colspan="11" style="text-align:center; padding: 10px;">No matching records found</td>
                    </tr>
                `;
            } else {
                noDataRow.style.display = 'table-row';
            }
        } else if (noDataRow) {
            noDataRow.style.display = 'none';
        }
    }

    /**
     * Resets all filters and shows all data
     */
    function resetFilters() {
        globalSearch.value = '';
        categoryFilter.selectedIndex = 0;
        unitFilter.selectedIndex = 0;
        
        // Reset to show all rows
        filteredRows = Array.from(tableBody.querySelectorAll('tr:not(.no-data)'));
        currentPage = 1;
        
        // Show all rows
        filteredRows.forEach(row => {
            row.style.display = 'table-row';
        });
        
        updatePagination();
        showPage(currentPage);
        updateNoDataMessage(filteredRows.length);
    }

    // ========== PAGINATION CONTROLS ==========
    /**
     * Goes to the previous page
     */
    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    /**
     * Goes to the next page
     */
    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    // ========== DOWNLOAD FUNCTIONALITY ==========
    /**
     * Handles download link clicks
     * @param {Event} event - The click event
     */
    function handleDownloadClick(event) {
        event.preventDefault();
        
        // Get visible rows based on current filters
        const visibleRows = filteredRows.filter(row => row.style.display !== 'none');
        
        // Get item names from visible rows
        const visibleItemNames = visibleRows.map(row => 
            row.querySelector('.select-checkbox').value
        );
        
        // Use selected items if any, otherwise use all visible items
        const selectedCheckboxes = document.querySelectorAll(".select-checkbox:checked");
        const selectedValues = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        const itemsToDownload = selectedValues.length > 0 ? selectedValues : visibleItemNames;
        
        // Submit form with selected items
        selectedItemsInput.value = JSON.stringify(itemsToDownload);
        const form = document.getElementById("downloadForm");
        form.action = this.href;
        form.submit();
    }
});