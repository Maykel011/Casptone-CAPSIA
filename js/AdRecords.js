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

//////////////////////Item Records Management//////////////////////
document.addEventListener("DOMContentLoaded", function manageItemRecords() {
    // DOM elements
    const filterForm = document.getElementById('filter-form');
    const searchBox = document.getElementById('search-box');
    const startDate = document.getElementById('start-date');
    const endDate = document.getElementById('end-date');
    const statusFilter = document.querySelector('select[name="status"]');
    const categoryFilter = document.querySelector('select[name="category"]');
    const clearFiltersBtn = document.getElementById('clear-filters-btn');
    const deleteSelectedBtn = document.querySelector('.delete-selected-btn');
    const selectAllCheckbox = document.querySelector('.select-all');
    const itemCheckboxes = document.querySelectorAll('.select-item');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const pageNumber = document.getElementById('page-number');
    
    // Modal elements
    const deleteModal = document.getElementById('deleteModal');
    const updateModal = document.getElementById('updateModal');
    const createModal = document.getElementById('create-Item-modal');
    const cancelDeleteBtn = document.getElementById('cancelDelete');
    const cancelUpdateBtn = document.getElementById('cancelUpdate');
    const cancelCreateBtn = document.getElementById('cancel-btn');
    const updateForm = document.getElementById('update-form');
    const createForm = document.getElementById('create-item-form');
    const quantityInput = document.getElementById('quantity');
    const updateQuantityInput = document.getElementById('update-quantity');
    const updateAvailabilityInput = document.getElementById('update-availability');
    
    // Variables to track state
    let currentItemId = null;
    let selectedItems = [];
    let currentPage = parseInt(new URLSearchParams(window.location.search).get('page')) || 1;
    let totalPages = parseInt(document.getElementById('page-number').textContent.split(' of ')[1]) || 1;

    // Initialize event listeners
    initEventListeners();

    function initEventListeners() {
        // Filter form submission
        if (filterForm) {
            searchBox.addEventListener('input', debounce(() => filterForm.submit(), 500));
            startDate.addEventListener('change', () => filterForm.submit());
            endDate.addEventListener('change', () => filterForm.submit());
            
            if (statusFilter) {
                statusFilter.addEventListener('change', () => filterForm.submit());
            }
            
            if (categoryFilter) {
                categoryFilter.addEventListener('change', () => filterForm.submit());
            }
        }

        // Clear filters button
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', clearFilters);
        }

        // Checkbox events
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', toggleSelectAll);
        }

        // Item checkboxes
        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedItems);
        });

        // Pagination buttons
        if (prevBtn) {
            prevBtn.addEventListener('click', prevPage);
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', nextPage);
        }

        // Modal buttons
        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', () => deleteModal.style.display = 'none');
        }
        if (cancelUpdateBtn) {
            cancelUpdateBtn.addEventListener('click', () => updateModal.style.display = 'none');
        }
        if (cancelCreateBtn) {
            cancelCreateBtn.addEventListener('click', () => createModal.style.display = 'none');
        }

        // Form submissions
        if (updateForm) {
            updateForm.addEventListener('submit', handleUpdateItem);
        }
        if (createForm) {
            createForm.addEventListener('submit', handleCreateItem);
        }

        // Quantity change listeners for automatic status updates
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                // Set availability equal to quantity when creating new item
                document.getElementById('availability').value = this.value;
                updateStatus(this.value);
            });
        }
        
        if (updateQuantityInput) {
            updateQuantityInput.addEventListener('input', function() {
                // Ensure availability doesn't exceed quantity when updating
                const quantity = parseInt(this.value) || 0;
                const availabilityInput = document.getElementById('update-availability');
                const currentAvailability = parseInt(availabilityInput.value) || 0;
                
                if (currentAvailability > quantity) {
                    availabilityInput.value = quantity;
                }
                availabilityInput.max = quantity;
                updateStatus(availabilityInput.value, true);
            });
        }
        
        if (updateAvailabilityInput) {
            updateAvailabilityInput.addEventListener('input', function() {
                updateStatus(this.value, true);
            });
        }
    }

    // Automatic status update function
    function updateStatus(quantity, isUpdateForm = false) {
        quantity = parseInt(quantity) || 0;
        let statusText = 'Available';
        let statusClass = 'available';
        
        if (quantity === 0) {
            statusText = 'Out of Stock';
            statusClass = 'out-of-stock';
        } else if (quantity <= 5) {
            statusText = 'Low Stock';
            statusClass = 'low-stock';
        }
        
        if (isUpdateForm) {
            document.getElementById('update-status-display').value = statusText;
            document.getElementById('update-status-display').className = statusClass;
            document.getElementById('update-status').value = statusText;
        } else {
            document.getElementById('status-display').value = statusText;
            document.getElementById('status-display').className = statusClass;
            document.getElementById('status').value = statusText;
        }
    }

    // Utility functions
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    function showSuccessMessage(message) {
        const successMessage = document.getElementById('successMessage');
        const successText = document.getElementById('successText');
        
        successText.textContent = message;
        successMessage.style.display = 'flex';
        
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 3000);
    }

    // Filter functions
    function clearFilters() {
        searchBox.value = '';
        startDate.value = '';
        endDate.value = '';
        if (statusFilter) statusFilter.value = '';
        if (categoryFilter) categoryFilter.value = '';
        filterForm.submit();
    }

    function updateSelectedItems() {
        selectedItems = [];
        const allCheckboxes = document.querySelectorAll('.select-item');
        let checkedCount = 0;
        
        allCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selectedItems.push(checkbox.getAttribute('data-item-id'));
                checkedCount++;
            }
        });
        
        deleteSelectedBtn.disabled = selectedItems.length === 0;
        
        // Update select-all checkbox state
        selectAllCheckbox.checked = checkedCount === allCheckboxes.length && allCheckboxes.length > 0;
        selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
    }

 // Replace your current toggleSelectAll and updateSelectAllState functions with these:

function toggleSelectAll(checkbox) {
    const isChecked = checkbox.checked;
    const allCheckboxes = document.querySelectorAll('.select-item');
    
    allCheckboxes.forEach(item => {
        item.checked = isChecked;
    });

    // Update the delete button state
    updateSelectedItems();
}

function updateSelectAllState() {
    const allCheckboxes = document.querySelectorAll('.select-item');
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkedCount = document.querySelectorAll('.select-item:checked').length;
    const totalCount = allCheckboxes.length;

    // Update the select all checkbox state
    if (checkedCount === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else if (checkedCount === totalCount) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = true;
    }

    // Update the delete button state
    updateSelectedItems();
}

function updateSelectedItems() {
    selectedItems = [];
    const allCheckboxes = document.querySelectorAll('.select-item');
    const deleteSelectedBtn = document.querySelector('.delete-selected-btn');
    
    allCheckboxes.forEach(checkbox => {
        if (checkbox.checked) {
            selectedItems.push(checkbox.getAttribute('data-item-id'));
        }
    });
    
    // Enable/disable delete button based on selection
    deleteSelectedBtn.disabled = selectedItems.length === 0;
}
    
    
    // Delete functions
    function deleteSelected() {
        if (selectedItems.length === 0) {
            alert('Please select at least one item to delete');
            return;
        }

        if (confirm(`Are you sure you want to delete ${selectedItems.length} selected item(s)?`)) {
            const formData = new FormData();
            formData.append('delete-multiple-items', '1');
            
            selectedItems.forEach(id => {
                formData.append('item_ids[]', id);
            });
            
            fetch('ItemRecords.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSuccessMessage(data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.message || 'Failed to delete items');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
        }
    }

    // Update functions
    function openUpdateModal(itemId) {
        currentItemId = itemId;
        
        fetch(`ItemRecords.php?item_id=${itemId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(item => {
                document.getElementById('update-item-id').value = item.item_id;
                document.getElementById('update-item-name').value = item.item_name;
                document.getElementById('update-description').value = item.description || '';
                document.getElementById('update-quantity').value = item.quantity;
                document.getElementById('update-availability').value = item.availability;
                document.getElementById('update-model-no').value = item.model_no;
                document.getElementById('update-unit').value = item.unit;
                document.getElementById('update-item-category').value = item.item_category;
                document.getElementById('update-item-location').value = item.item_location || '';
                document.getElementById('update-expiration').value = item.expiration || '';
                
                // Set max availability to quantity
                document.getElementById('update-availability').max = item.quantity;
                
                // Set initial status based on availability
                updateStatus(item.availability, true);
                
                updateModal.style.display = 'block';
            })
            .catch(error => {
                console.error('Error fetching item data:', error);
                alert('Failed to load item data');
            });
    }

    function handleUpdateItem(e) {
        e.preventDefault();
        
        // Ensure status is updated before submission
        const availability = document.getElementById('update-availability').value;
        updateStatus(availability, true);
        
        const formData = new FormData(updateForm);
        formData.append('update-item', '1');
        
        fetch('ItemRecords.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage(data.message);
                updateModal.style.display = 'none';
                setTimeout(() => location.reload(), 1000);
            } else {
                alert(data.message || 'Failed to update item');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the item');
        });
    }

    // Create functions
    function openCreateModal() {
        createForm.reset();
        // Reset status to default
        document.getElementById('status-display').value = 'Available';
        document.getElementById('status-display').className = 'available';
        document.getElementById('status').value = 'Available';
        
        // Add hidden availability field to form if it doesn't exist
        if (!document.getElementById('availability')) {
            const availabilityInput = document.createElement('input');
            availabilityInput.type = 'hidden';
            availabilityInput.id = 'availability';
            availabilityInput.name = 'availability';
            createForm.appendChild(availabilityInput);
        }
        
        createModal.style.display = 'block';
    }

    function handleCreateItem(e) {
        e.preventDefault();
        
        // Set availability equal to quantity when creating new item
        const quantity = document.getElementById('quantity').value;
        document.getElementById('availability').value = quantity;
        
        // Update status based on quantity
        updateStatus(quantity);
        
        const formData = new FormData(createForm);
        
        fetch('ItemRecords.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage(data.message);
                createModal.style.display = 'none';
                setTimeout(() => location.reload(), 1000);
            } else {
                alert(data.message || 'Failed to create item');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while creating the item');
        });
    }

    // Pagination functions
    function prevPage() {
        if (currentPage > 1) {
            navigateToPage(currentPage - 1);
        }
    }

    function nextPage() {
        if (currentPage < totalPages) {
            navigateToPage(currentPage + 1);
        }
    }

    function navigateToPage(page) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        window.location.href = url.toString();
    }

    // Global functions (called from HTML onclick)
    window.deleteSelected = deleteSelected;
    window.openCreateModal = openCreateModal;
    window.openUpdateModal = openUpdateModal;
    window.openDeleteModal = function(itemId) {
        currentItemId = itemId;
        deleteModal.style.display = 'block';
    };
    window.toggleSelectAll = toggleSelectAll;
    window.prevPage = prevPage;
    window.nextPage = nextPage;
});