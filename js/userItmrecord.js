//////////////////////Profile Dropdown//////////////////////
document.addEventListener("DOMContentLoaded", function () {
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
});

//////////////////////Sidebar Dropdown//////////////////////
document.addEventListener("DOMContentLoaded", function() {
    // Initialize dropdown arrows
    const dropdownArrows = document.querySelectorAll(".arrow-icon");
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

    dropdownArrows.forEach(arrow => {
        const parent = arrow.closest(".dropdown");
        const dropdownText = parent.querySelector(".text").innerText;

        // Apply saved state from localStorage
        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }

        arrow.addEventListener("click", function(event) {
            event.stopPropagation();
            parent.classList.toggle("active");
            
            // Save state to localStorage
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });
});

//////////////////////Filter and Pagination Handling//////////////////////
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("filter-form");
    if (!form) return;

    // Get all filter elements
    const searchBox = document.getElementById("search-box");
    const statusFilter = document.getElementById("status-filter");
    const categoryFilter = document.getElementById("category-filter");
    const pageInput = document.getElementById("page-input");
    const clearFiltersBtn = document.getElementById("clear-filters-btn");

    // Store current filter values
    const currentFilters = {
        search: searchBox ? searchBox.value : '',
        status: statusFilter ? statusFilter.value : '',
        category: categoryFilter ? categoryFilter.value : '',
        page: pageInput ? pageInput.value : 1
    };

    // Function to submit form
    function submitForm() {
        form.submit();
    }

    // Function to handle page changes
    window.changePage = function(page) {
        if (pageInput) {
            pageInput.value = page;
            submitForm();
        }
    };

    // Set up event listeners for filters
    if (searchBox) {
        let searchTimeout;
        searchBox.addEventListener("input", function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                if (pageInput) pageInput.value = 1; // Reset to first page on search
                submitForm();
            }, 500);
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener("change", function() {
            if (pageInput) pageInput.value = 1; // Reset to first page on filter change
            submitForm();
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener("change", function() {
            if (pageInput) pageInput.value = 1; // Reset to first page on filter change
            submitForm();
        });
    }

    // Clear filters button
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener("click", function() {
            // Reset all filter inputs
            if (searchBox) searchBox.value = '';
            if (statusFilter) statusFilter.value = '';
            if (categoryFilter) categoryFilter.value = '';
            if (pageInput) pageInput.value = 1;
            
            // Submit the form to reload with cleared filters
            submitForm();
        });
    }
});

//////////////////////Profile Dropdown and Sidebar (remain the same)//////////////////////
// ... (keep the existing profile dropdown and sidebar code)