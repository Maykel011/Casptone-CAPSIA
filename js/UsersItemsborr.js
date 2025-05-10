document.addEventListener("DOMContentLoaded", function () {
    //////////////////////// VARIABLE DECLARATIONS ////////////////////////
    const dropdownArrows = document.querySelectorAll(".arrow-icon");
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");
    const errorModal = document.getElementById("errorModal");
    const modalMessage = document.getElementById("modalMessage");
    const modalCloseBtn = document.querySelector(".custom-modal-close");
    const confirmationModal = document.getElementById("confirmationModal");
    const confirmationMessage = document.getElementById("confirmationMessage");
    const confirmYes = document.getElementById("confirmYes");
    const confirmNo = document.getElementById("confirmNo");
    const requestForm = document.getElementById("requestForm");
    const submitBtn = document.getElementById("submit-btn");
    const addToCartBtn = document.getElementById("add-to-cart-btn");
    const submitAllBtn = document.getElementById("submit-all-btn");
    const clearCartBtn = document.getElementById("clear-cart-btn");
    const cartTableBody = document.getElementById("cart-items");
    const itemCategory = document.getElementById("item-category");
    const itemDropdown = document.getElementById("item-id");
    const dateNeeded = document.getElementById("date_needed");
    const returnDate = document.getElementById("return_date");
    const resetBtn = requestForm.querySelector(".reset-btn");

    let cartItems = JSON.parse(localStorage.getItem('borrowCart')) || [];
    let currentAction = null;
    let currentIndex = null;
    const itemCache = {};

    //////////////////////// INITIALIZATION ////////////////////////
    // Set minimum dates
    const today = new Date().toISOString().split('T')[0];
    dateNeeded.min = today;
    returnDate.min = today;

    // Initialize cart display
    updateCartDisplay();

    // Apply saved dropdown state
    dropdownArrows.forEach(arrow => {
        const parent = arrow.closest(".dropdown");
        const dropdownText = parent.querySelector(".text").innerText;
        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }
    });

    //////////////////////// EVENT LISTENERS ////////////////////////
    
    //////////////////////// SHOW AVAILABILITY ////////////////////////
    itemDropdown.addEventListener("change", function() {
        const itemId = this.value;
        const availableSpan = document.getElementById('available-quantity');
        const quantityInput = document.getElementById('quantity');
        
        // Clear previous values
        availableSpan.textContent = '';
        quantityInput.placeholder = 'Enter quantity';
        quantityInput.removeAttribute('max');
        quantityInput.value = '';
        
        if (!itemId) return;
    
        fetch(`UserItemBorrow.php?item_id=${itemId}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                
                // Update the availability display
                availableSpan.textContent = `${data.available} Available`;
                
                // Set the max attribute and update placeholder
                quantityInput.max = data.available;
                quantityInput.placeholder = `Max: ${data.available}`;
                
                // Add validation to prevent typing more than available
                quantityInput.addEventListener('input', function() {
                    if (parseInt(this.value) > parseInt(this.max)) {
                        this.value = this.max;
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching item availability:', error);
            });
    });
    
    // Update the form reset handler to clear everything
    resetBtn.addEventListener("click", function() {
        itemDropdown.innerHTML = '<option value="" disabled selected>Select an Item</option>';
        document.getElementById('available-quantity').textContent = '';
        const quantityInput = document.getElementById('quantity');
        quantityInput.placeholder = 'Enter quantity';
        quantityInput.removeAttribute('max');
        quantityInput.value = '';
    });

    //////////////////////// DROPDOWN FUNCTIONALITY ////////////////////////
    dropdownArrows.forEach(arrow => {
        arrow.addEventListener("click", function (event) {
            event.stopPropagation();
            const parent = this.closest(".dropdown");
            const dropdownText = parent.querySelector(".text").innerText;
            parent.classList.toggle("active");
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });

    //////////////////////// USER PROFILE DROPDOWN ////////////////////////
    userIcon.addEventListener("click", function (event) {
        event.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });

    //////////////////////// MODAL FUNCTIONALITY ////////////////////////
    modalCloseBtn.addEventListener("click", () => {
        errorModal.style.display = "none";
        confirmationModal.style.display = "none";
    });

    window.addEventListener("click", (event) => {
        if (event.target === errorModal || event.target === confirmationModal) {
            errorModal.style.display = "none";
            confirmationModal.style.display = "none";
        }
    });

    //////////////////////// FORM SUBMISSION HANDLERS ////////////////////////
    submitBtn.addEventListener('click', function() {
        submitRequest(false); // false means not from cart
    });

    addToCartBtn.addEventListener('click', function() {
        handleAddToCart();
    });

    submitAllBtn.addEventListener('click', function() {
        handleSubmitAll();
    });

    clearCartBtn.addEventListener('click', function() {
        if (cartItems.length === 0) {
            showModal('Your cart is already empty.', true);
            return;
        }
        showConfirmation('Are you sure you want to clear your entire cart?', 'clear');
    });

    //////////////////////// CONFIRMATION MODAL HANDLERS ////////////////////////
    confirmYes.addEventListener('click', function() {
        confirmationModal.style.display = "none";
        
        if (currentAction === 'remove') {
            cartItems.splice(currentIndex, 1);
            localStorage.setItem('borrowCart', JSON.stringify(cartItems));
            updateCartDisplay();
            showModal('Item removed from cart.', false);
        } else if (currentAction === 'clear') {
            cartItems = [];
            localStorage.removeItem('borrowCart');
            updateCartDisplay();
            showModal('Cart cleared successfully.', false);
        }
        
        currentAction = null;
        currentIndex = null;
    });

    confirmNo.addEventListener('click', function() {
        confirmationModal.style.display = "none";
        currentAction = null;
        currentIndex = null;
    });

    //////////////////////// DYNAMIC ITEM LOADING ////////////////////////
    itemCategory.addEventListener("change", function () {
        const category = this.value;
        itemDropdown.innerHTML = '<option value="" disabled selected>Select an Item</option>';
        itemDropdown.disabled = true;

        if (category) {
            if (itemCache[category]) {
                populateDropdown(itemCache[category]);
                itemDropdown.disabled = false;
                return;
            }

            fetch(`UserItemBorrow.php?item_category=${encodeURIComponent(category)}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(items => {
                    itemCache[category] = items;
                    if (Array.isArray(items) && items.length > 0) {
                        populateDropdown(items);
                    } else {
                        showNoItemsMessage();
                    }
                })
                .catch(error => {
                    console.error('Error fetching items:', error);
                    showNoItemsMessage("Error loading items. Please try again.");
                })
                .finally(() => {
                    itemDropdown.disabled = false;
                });
        }
    });

    //////////////////////// DATE VALIDATION ////////////////////////
    dateNeeded.addEventListener("change", function() {
        const dateNeededValue = new Date(this.value);
        const returnDateValue = new Date(returnDate.value);
        
        if (returnDateValue && dateNeededValue > returnDateValue) {
            showModal('Return date must be after the date needed.');
            this.value = '';
        }
        
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

    //////////////////////// FORM RESET HANDLING ////////////////////////
    resetBtn.addEventListener("click", function() {
        itemDropdown.innerHTML = '<option value="" disabled selected>Select an Item</option>';
    });

    //////////////////////// HELPER FUNCTIONS ////////////////////////

    //////////////////////// MODAL FUNCTIONS ////////////////////////
    function showModal(message, isError = true) {
        modalMessage.textContent = message;
        modalMessage.className = isError ? "error-message" : "success-message";
        errorModal.style.display = "flex";
    }

    function showConfirmation(message, action, index = null) {
        confirmationMessage.textContent = message;
        currentAction = action;
        currentIndex = index;
        confirmationModal.style.display = "flex";
    }

    //////////////////////// CART FUNCTIONS ////////////////////////
    function updateCartDisplay() {
        cartTableBody.innerHTML = '';
        
        if (cartItems.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="6" style="text-align: center;">Your cart is empty</td>';
            cartTableBody.appendChild(row);
            return;
        }
        
        cartItems.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.item_name}</td>
                <td>${item.item_category}</td>
                <td>${item.quantity}</td>
                <td>${item.date_needed}</td>
                <td>${item.return_date}</td>
                <td>
                    <button class="remove-item-btn" data-index="${index}">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>
                </td>
            `;
            cartTableBody.appendChild(row);
        });
        
        // Add event listeners to remove buttons
        document.querySelectorAll('.remove-item-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                showConfirmation('Are you sure you want to remove this item from your cart?', 'remove', index);
            });
        });
    }

    function addToCart(itemData) {
        const existingItemIndex = cartItems.findIndex(item => 
            item.item_id === itemData.item_id && 
            item.date_needed === itemData.date_needed && 
            item.return_date === itemData.return_date
        );
        
        if (existingItemIndex >= 0) {
            cartItems[existingItemIndex].quantity += itemData.quantity;
        } else {
            cartItems.push({
                item_id: itemData.item_id,
                item_name: itemData.item_name,
                item_category: itemData.item_category,
                quantity: itemData.quantity,
                date_needed: itemData.date_needed,
                return_date: itemData.return_date,
                purpose: itemData.purpose,
                notes: itemData.notes || ''
            });
        }
        
        localStorage.setItem('borrowCart', JSON.stringify(cartItems));
        updateCartDisplay();
        
        document.querySelector('.borrow-cart-container').scrollIntoView({
            behavior: 'smooth'
        });
        
        return true;
    }

    //////////////////////// FORM HANDLING FUNCTIONS ////////////////////////
    function handleAddToCart() {
        const formData = new FormData(requestForm);
        const itemId = formData.get('item_id');
        const itemName = document.querySelector('#item-id option:checked').textContent;
        const itemCategory = formData.get('item_category');
        const quantity = parseInt(formData.get('quantity'));
        const maxQuantity = parseInt(document.getElementById('quantity').max) || 0;
        const dateNeeded = formData.get('date_needed');
        const returnDate = formData.get('return_date');
        const purpose = formData.get('purpose');
        
        if (!itemId || !itemName || !itemCategory || !quantity || !dateNeeded || !returnDate || !purpose) {
            showModal('Please fill all required fields.', true);
            return;
        }
        
        if (quantity <= 0) {
            showModal('Quantity must be greater than 0.', true);
            return;
        }
        
        if (quantity > maxQuantity) {
            showModal(`You cannot request more than ${maxQuantity} items (available quantity).`, true);
            return;
        }
        
        if (new Date(dateNeeded) > new Date(returnDate)) {
            showModal('Return date must be after the date needed.', true);
            return;
        }
        
        const itemData = {
            item_id: itemId,
            item_name: itemName,
            item_category: itemCategory,
            quantity: quantity,
            date_needed: dateNeeded,
            return_date: returnDate,
            purpose: purpose,
            notes: formData.get('notes')
        };
        
        if (addToCart(itemData)) {
            showModal('Item added to cart successfully!', false);
            
            const selectedCategory = requestForm.querySelector('#item-category').value;
            requestForm.reset();
            requestForm.querySelector('#item-category').value = selectedCategory;
            document.getElementById('item-id').innerHTML = '<option value="" disabled selected>Select an Item</option>';
        } else {
            showModal('Failed to add item to cart.', true);
        }
    }

    function submitRequest(fromCart) {
        const formData = new FormData(requestForm);
        const itemId = formData.get('item_id');
        const itemName = document.querySelector('#item-id option:checked').textContent;
        const itemCategory = formData.get('item_category');
        const quantity = parseInt(formData.get('quantity'));
        const maxQuantity = parseInt(document.getElementById('quantity').max) || 0;
        const dateNeeded = formData.get('date_needed');
        const returnDate = formData.get('return_date');
        const purpose = formData.get('purpose');
        
        if (!itemId || !itemName || !itemCategory || !quantity || !dateNeeded || !returnDate || !purpose) {
            showModal('Please fill all required fields.', true);
            return;
        }
        
        if (quantity > maxQuantity) {
            showModal(`You cannot request more than ${maxQuantity} items (available quantity).`, true);
            return;
        }
        
        const btn = fromCart ? submitAllBtn : submitBtn;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        fetch('UserItemBorrow.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                showModal(data.error);
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
            btn.disabled = false;
            btn.textContent = fromCart ? 'Submit All Requests' : 'Submit Request';
        });
    }

    function handleSubmitAll() {
        if (cartItems.length === 0) {
            showModal('Your cart is empty. Please add items before submitting.', true);
            return;
        }
        
        submitAllBtn.disabled = true;
        submitAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        submitCartItems(0);
    }

    function submitCartItems(index) {
        if (index >= cartItems.length) {
            showModal('All items submitted for approval! They will remain in your cart until approved by admin.', false);
            submitAllBtn.disabled = false;
            submitAllBtn.textContent = 'Submit All Requests';
            
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            return;
        }
        
        const item = cartItems[index];
        const formData = new FormData();
        
        for (const key in item) {
            if (item.hasOwnProperty(key)) {
                formData.append(key, item[key]);
            }
        }
        formData.append('from_cart', 'true');
        
        fetch('UserItemBorrow.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                showModal(`Error submitting item "${item.item_name}": ${data.error}`);
                submitAllBtn.disabled = false;
                submitAllBtn.textContent = 'Submit All Requests';
            } else {
                submitCartItems(index + 1);
            }
        })
        .catch(error => {
            showModal(`Error submitting item "${item.item_name}": ${error.message}`);
            submitAllBtn.disabled = false;
            submitAllBtn.textContent = 'Submit All Requests';
        });
    }

    //////////////////////// UTILITY FUNCTIONS ////////////////////////
    function populateDropdown(items) {
        itemDropdown.innerHTML = '<option value="" disabled selected>Select an Item</option>';
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.item_id;
            option.textContent = item.item_name;
            itemDropdown.appendChild(option);
        });
    }

    function showNoItemsMessage(message = "No items available for this category") {
        const option = document.createElement('option');
        option.value = "";
        option.disabled = true;
        option.textContent = message;
        itemDropdown.appendChild(option);
    }
});

