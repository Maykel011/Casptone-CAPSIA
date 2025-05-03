////////////////////////// dropdown//////////////////////////
document.addEventListener("DOMContentLoaded", function() {
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
});

////////////////////////// profile Dropdown Funtionality//////////////////////////
document.addEventListener("DOMContentLoaded", function() {
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon.addEventListener("click", function(event) {
        event.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function(event) {
        if (!userIcon.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });
});

////////////////////////// filter Functionality//////////////////////////
document.addEventListener("DOMContentLoaded", function() {
    const rowsPerPage = 10;
    let currentPage = 1;
    const tableBody = document.getElementById("item-table-body");
    const rows = Array.from(tableBody.querySelectorAll("tr:not(.no-results)"));
    let filteredRows = [...rows];
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    const deleteSelectedBtn = document.querySelector('.delete-selected-btn');
    const searchBox = document.getElementById("search-box");
    const startDate = document.getElementById("start-date");
    const endDate = document.getElementById("end-date");

    showPage(currentPage);

    searchBox.addEventListener("input", debounce(filterTable, 300));
    startDate.addEventListener("change", filterTable);
    endDate.addEventListener("change", filterTable);

    function filterTable() {
        const searchTerm = searchBox.value.toLowerCase();
        const startDateVal = startDate.value;
        const endDateVal = endDate.value;

        filteredRows = rows.filter(row => {
            const itemName = row.cells[1].textContent.toLowerCase();
            const dateCell = row.cells[7].textContent;
            
            const matchesSearch = searchTerm === "" || itemName.includes(searchTerm);
            
            let matchesDate = true;
            if (startDateVal && endDateVal) {
                matchesDate = dateCell >= startDateVal && dateCell <= endDateVal;
            }
            
            return matchesSearch && matchesDate;
        });

        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        currentPage = 1;
        showPage(currentPage);
    }

    function showPage(page) {
        const noResultsRow = tableBody.querySelector(".no-results");
        if (noResultsRow) {
            noResultsRow.remove();
        }

        if (filteredRows.length === 0) {
            const noResultsRow = document.createElement("tr");
            noResultsRow.className = "no-results";
            noResultsRow.innerHTML = "<td colspan='13'>No matching items found</td>";
            tableBody.appendChild(noResultsRow);
            
            document.getElementById("page-number").innerText = "No results";
            document.getElementById("prev-btn").disabled = true;
            document.getElementById("next-btn").disabled = true;
            return;
        }

        rows.forEach(row => row.style.display = "none");
        
        const start = (page - 1) * rowsPerPage;
        const end = Math.min(start + rowsPerPage, filteredRows.length);
        
        for (let i = start; i < end; i++) {
            filteredRows[i].style.display = "table-row";
        }

        document.getElementById("page-number").innerText = `Page ${page} of ${totalPages}`;
        document.getElementById("prev-btn").disabled = page === 1;
        document.getElementById("next-btn").disabled = page === totalPages;

        updateDeleteButtonState();
    }

    window.nextPage = function() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    };

    window.prevPage = function() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    };

    function updateDeleteButtonState() {
        const hasSelectedItems = document.querySelectorAll('.select-item:checked').length > 0;
        deleteSelectedBtn.disabled = !hasSelectedItems;
        
        if (deleteSelectedBtn.disabled) {
            deleteSelectedBtn.style.opacity = '0.6';
            deleteSelectedBtn.style.cursor = 'not-allowed';
        } else {
            deleteSelectedBtn.style.opacity = '1';
            deleteSelectedBtn.style.cursor = 'pointer';
        }
    }

    updateDeleteButtonState();

    window.toggleSelectAll = function(checkbox) {
        const checkboxes = document.querySelectorAll(".select-item");
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        updateDeleteButtonState();
    };

    window.deleteSelected = function() {
        const selected = Array.from(document.querySelectorAll(".select-item:checked"));
        if (selected.length === 0) return;
        
        if (confirm(`Are you sure you want to delete ${selected.length} selected items?`)) {
            const deletePromises = selected.map(checkbox => {
                const row = checkbox.closest("tr");
                const itemId = row.dataset.itemId;
                
                return fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `delete-item=1&item_id=${itemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.remove();
                        showAlert('success', 'Item deleted successfully');
                    } else {
                        showAlert('error', data.message || 'Failed to delete item');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while deleting the item');
                });
            });

            Promise.all(deletePromises).then(() => {
                updateDeleteButtonState();
            });
        }
    };

    tableBody.addEventListener('change', function(e) {
        if (e.target.classList.contains('select-item')) {
            const allChecked = document.querySelectorAll('.select-item:checked').length === 
                              document.querySelectorAll('.select-item').length;
            selectAllCheckbox.checked = allChecked;
            updateDeleteButtonState();
        }
    });
});

////////////////////////// modal functionality//////////////////////////
document.addEventListener("DOMContentLoaded", function() {
    const deleteModal = document.getElementById("deleteModal");
    const confirmDelete = document.getElementById("confirmDelete");
    const cancelDelete = document.getElementById("cancelDelete");
    let currentRow = null;

    window.openDeleteModal = function(button) {
        deleteModal.style.display = "block";
        currentRow = button.closest("tr");
    };

    confirmDelete.addEventListener("click", function() {
        if (currentRow) {
            const itemId = currentRow.dataset.itemId;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `delete-item=1&item_id=${itemId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentRow.remove();
                    showAlert('success', 'Item deleted successfully');
                } else {
                    showAlert('error', data.message || 'Failed to delete item');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'An error occurred while deleting the item');
            });
        }
        deleteModal.style.display = "none";
    });

    cancelDelete.addEventListener("click", function() {
        deleteModal.style.display = "none";
    });

    const createModal = document.getElementById("create-Item-modal");
    const updateModal = document.getElementById("updateModal");
    const cancelCreate = document.getElementById("cancel-btn");
    const cancelUpdate = document.getElementById("cancelUpdate");

    window.openCreateModal = function() {
        createModal.style.display = "block";
    };

    cancelCreate.addEventListener("click", function() {
        createModal.style.display = "none";
    });

    window.openUpdateModal = function(button) {
        const row = button.closest("tr");
        
        document.getElementById("update-item-id").value = row.dataset.itemId;
        document.getElementById("update-item-name").value = row.cells[1].textContent;
        document.getElementById("update-description").value = row.cells[2].textContent;
        document.getElementById("update-quantity").value = row.cells[3].textContent;
        document.getElementById("update-unit").value = row.cells[4].textContent;
        document.getElementById("update-status").value = row.cells[5].textContent;
        document.getElementById("update-model-no").value = row.cells[9].textContent;
        document.getElementById("update-item-category").value = row.cells[10].textContent;
        document.getElementById("update-item-location").value = row.cells[11].textContent;
        
        updateModal.style.display = "block";
    };

    cancelUpdate.addEventListener("click", function() {
        updateModal.style.display = "none";
    });

    window.addEventListener("click", function(event) {
        if (event.target === deleteModal) deleteModal.style.display = "none";
        if (event.target === createModal) createModal.style.display = "none";
        if (event.target === updateModal) updateModal.style.display = "none";
    });
});

////////////////////////// form submissions//////////////////////////
document.addEventListener("DOMContentLoaded", function() {
    const createItemForm = document.getElementById('create-item-form');
    if (createItemForm) {
        createItemForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateCreateForm()) {
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            
            const formData = new FormData(this);
            formData.append('create-item', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    createModal.style.display = 'none';
                    window.location.reload();
                } else {
                    showAlert('error', data.message || 'Failed to create item');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'An error occurred while creating the item.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }

    const updateForm = document.getElementById('update-form');
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateUpdateForm()) {
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            const formData = new FormData();
            formData.append('update-item', '1');
            formData.append('item_id', document.getElementById('update-item-id').value);
            formData.append('item_name', document.getElementById('update-item-name').value);
            formData.append('description', document.getElementById('update-description').value);
            formData.append('quantity', document.getElementById('update-quantity').value);
            formData.append('unit', document.getElementById('update-unit').value);
            formData.append('status', document.getElementById('update-status').value);
            formData.append('model_no', document.getElementById('update-model-no').value);
            formData.append('item_category', document.getElementById('update-item-category').value);
            formData.append('item_location', document.getElementById('update-item-location').value);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    updateModal.style.display = 'none';
                    window.location.reload();
                } else {
                    showAlert('error', data.message || 'Failed to update item');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'An error occurred while updating the item.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
});

////////////////////////// form validation//////////////////////////
function validateCreateForm() {
    const form = document.getElementById('create-item-form');
    const requiredFields = [
        'item_name', 'quantity', 'status', 
        'unit', 'item_category'
    ];
    
    let isValid = true;
    
    form.querySelectorAll('.error-highlight').forEach(el => {
        el.classList.remove('error-highlight');
    });
    
    requiredFields.forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (!field.value.trim()) {
            field.classList.add('error-highlight');
            isValid = false;
        }
    });
    
    const quantityField = form.querySelector('[name="quantity"]');
    if (quantityField.value <= 0 || isNaN(quantityField.value)) {
        quantityField.classList.add('error-highlight');
        isValid = false;
    }
    
    if (!isValid) {
        showAlert('error', 'Please fill in all required fields with valid values');
    }
    
    return isValid;
}

function validateUpdateForm() {
    const form = document.getElementById('update-form');
    const requiredFields = [
        'item_name', 'quantity', 'status', 
        'unit', 'item_category'
    ];
    
    let isValid = true;
    
    form.querySelectorAll('.error-highlight').forEach(el => {
        el.classList.remove('error-highlight');
    });
    
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(`update-${fieldName}`);
        if (!field.value.trim()) {
            field.classList.add('error-highlight');
            isValid = false;
        }
    });
    
    const quantityField = document.getElementById('update-quantity');
    if (quantityField.value <= 0 || isNaN(quantityField.value)) {
        quantityField.classList.add('error-highlight');
        isValid = false;
    }
    
    if (!isValid) {
        showAlert('error', 'Please fill in all required fields with valid values');
    }
    
    return isValid;
}

////////////////////////// helper functions//////////////////////////
function showAlert(type, message) {
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${type}`;
    alertDiv.textContent = message;
    
    document.querySelector('.main-content').prepend(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}