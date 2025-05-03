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
    const errorModal = document.getElementById("errorModal");
    const modalMessage = document.getElementById("modalMessage");
    const modalCloseBtn = document.querySelector(".custom-modal-close");

    function showModal(message, isError = true) {
        modalMessage.textContent = message;
        modalMessage.className = isError ? "error-message" : "success-message";
        errorModal.style.display = "flex";
    }

    modalCloseBtn.addEventListener("click", () => {
        errorModal.style.display = "none";
    });

    window.addEventListener("click", (event) => {
        if (event.target === errorModal) {
            errorModal.style.display = "none";
        }
    });

    // ====================
    // FORM SUBMISSION
    // ====================
    const requestForm = document.getElementById("requestForm");
    
    requestForm.addEventListener("submit", function(event) {
        event.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector(".submit-btn");
        
        // Disable submit button during processing
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        fetch('UserItemBorrow.php', {
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
            if (data.error) {
                showModal(data.error);
                // If it's a duplicate request error, reset the item selection
                if (data.error.includes('already have an active request')) {
                    document.getElementById('item-id').value = '';
                }
            } else if (data.success) {
                showModal('Request submitted successfully!', false);
                setTimeout(() => {
                    window.location.href = 'UserTransaction.php?success=1';
                }, 1500);
            }
        })
        .catch(error => {
            showModal('Error submitting request: ' + error.message);
            console.error('Error:', error);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Request';
        });
    });

    // ====================
    // DYNAMIC ITEM LOADING
    // ====================
    const itemCache = {};
    const itemCategory = document.getElementById("item-category");
    const itemDropdown = document.getElementById("item-id");

    itemCategory.addEventListener("change", function () {
        const category = this.value;
        
        // Clear existing options
        itemDropdown.innerHTML = '<option value="" disabled selected>Select an Item</option>';
        itemDropdown.disabled = true;

        if (category) {
            // Check cache first
            if (itemCache[category]) {
                populateDropdown(itemCache[category]);
                itemDropdown.disabled = false;
                return;
            }

            fetch(`UserItemBorrow.php?item_category=${encodeURIComponent(category)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(items => {
                    // Cache the results
                    itemCache[category] = items;
                    
                    if (Array.isArray(items) && items.length > 0) {
                        populateDropdown(items);
                    } else {
                        const option = document.createElement('option');
                        option.value = "";
                        option.disabled = true;
                        option.textContent = "No items available for this category";
                        itemDropdown.appendChild(option);
                    }
                })
                .catch(error => {
                    console.error('Error fetching items:', error);
                    const option = document.createElement('option');
                    option.value = "";
                    option.disabled = true;
                    option.textContent = "Error loading items. Please try again.";
                    itemDropdown.appendChild(option);
                })
                .finally(() => {
                    itemDropdown.disabled = false;
                });
        }
    });

    function populateDropdown(items) {
        itemDropdown.innerHTML = '<option value="" disabled selected>Select an Item</option>';
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.item_id;
            option.textContent = item.item_name;
            itemDropdown.appendChild(option);
        });
    }

    // ====================
    // DATE VALIDATION
    // ====================
    const dateNeeded = document.getElementById("date_needed");
    const returnDate = document.getElementById("return_date");

    dateNeeded.addEventListener("change", function() {
        const dateNeededValue = new Date(this.value);
        const returnDateValue = new Date(returnDate.value);
        
        if (returnDateValue && dateNeededValue > returnDateValue) {
            showModal('Return date must be after the date needed.');
            this.value = '';
        }
        
        // Set minimum return date
        if (this.value) {
            returnDate.min = this.value;
        }
    });

    returnDate.addEventListener("change", function() {
        const returnDateValue = new Date(this.value);
        const dateNeededValue = new Date(dateNeeded.value);
        
        if (dateNeededValue && returnDateValue < dateNeededValue) {
            showModal('Return date must be after the date needed.');
            this.value = '';
        }
    });

    // ====================
    // FORM RESET HANDLING
    // ====================
    const resetBtn = requestForm.querySelector(".reset-btn");
    resetBtn.addEventListener("click", function() {
        itemDropdown.innerHTML = '<option value="" disabled selected>Select an Item</option>';
    });

    // ====================
    // INITIALIZE CURRENT DATE
    // ====================
    const today = new Date().toISOString().split('T')[0];
    dateNeeded.min = today;
    returnDate.min = today;
});