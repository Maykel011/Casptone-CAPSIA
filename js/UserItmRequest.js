document.addEventListener("DOMContentLoaded", function() {
    // Form validation
    const form = document.getElementById('requestForm');
    const submitBtn = document.getElementById('submitBtn');
    const spinner = document.getElementById('submitSpinner');
    
    // Character counter for notes
    const notesTextarea = document.getElementById('notes');
    const charCount = document.querySelector('.char-count');
    
    notesTextarea.addEventListener('input', function() {
        const remaining = 1000 - this.value.length;
        charCount.textContent = `${this.value.length}/1000 characters`;
        charCount.style.color = remaining < 50 ? 'red' : '#666';
    });
    
    // Client-side validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('.error-message').forEach(el => {
            el.textContent = '';
        });
        
        // Validate item name
        const itemName = document.getElementById('item-name');
        if (!itemName.value.trim()) {
            document.getElementById('item-name-error').textContent = 'Item name is required';
            isValid = false;
        }
        
        // Validate category
        const itemCategory = document.getElementById('item-category');
        if (!itemCategory.value) {
            document.getElementById('item-category-error').textContent = 'Category is required';
            isValid = false;
        }
        
        // Validate quantity
        const quantity = document.getElementById('quantity');
        if (!quantity.value || quantity.value < 1 || quantity.value > 1000) {
            document.getElementById('quantity-error').textContent = 'Quantity must be between 1 and 1000';
            isValid = false;
        }
        
        // Validate unit
        const itemUnit = document.getElementById('item-unit');
        if (!itemUnit.value) {
            document.getElementById('item-unit-error').textContent = 'Unit is required';
            isValid = false;
        }
        
        // Validate purpose
        const purpose = document.getElementById('purpose');
        if (!purpose.value.trim()) {
            document.getElementById('purpose-error').textContent = 'Purpose is required';
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            return;
        }
        
        // Show loading spinner
        submitBtn.disabled = true;
        document.querySelector('.btn-text').textContent = 'Processing...';
        spinner.classList.remove('hidden');
    });
    
    // Dropdown functionality
    document.querySelectorAll('.dropdown-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const dropdownContent = button.nextElementSibling;
            dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
        });
    });
    

});

document.addEventListener("DOMContentLoaded", function () {
    // Handle dropdown state
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

            // Save state in localStorage
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });

    // Handle user profile dropdown
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon.addEventListener("click", function (event) {
        event.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });

});
document.addEventListener("DOMContentLoaded", function() {
    // Form validation
    const form = document.getElementById('requestForm');
    const submitBtn = document.getElementById('submitBtn');
    const spinner = document.getElementById('submitSpinner');
    
    // Character counter for notes
    const notesTextarea = document.getElementById('notes');
    const charCount = document.querySelector('.char-count');
    
    notesTextarea.addEventListener('input', function() {
        const remaining = 1000 - this.value.length;
        charCount.textContent = `${this.value.length}/1000 characters`;
        charCount.style.color = remaining < 50 ? 'red' : '#666';
    });
    
    // Client-side validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('.error-message').forEach(el => {
            el.textContent = '';
        });
        
        // Validate item name
        const itemName = document.getElementById('item-name');
        if (!itemName.value.trim()) {
            document.getElementById('item-name-error').textContent = 'Item name is required';
            isValid = false;
        }
        
        // Validate category
        const itemCategory = document.getElementById('item-category');
        if (!itemCategory.value) {
            document.getElementById('item-category-error').textContent = 'Category is required';
            isValid = false;
        }
        
        // Validate quantity
        const quantity = document.getElementById('quantity');
        if (!quantity.value || quantity.value < 1 || quantity.value > 1000) {
            document.getElementById('quantity-error').textContent = 'Quantity must be between 1 and 1000';
            isValid = false;
        }
        
        // Validate unit
        const itemUnit = document.getElementById('item-unit');
        if (!itemUnit.value) {
            document.getElementById('item-unit-error').textContent = 'Unit is required';
            isValid = false;
        }
        
        // Validate purpose
        const purpose = document.getElementById('purpose');
        if (!purpose.value.trim()) {
            document.getElementById('purpose-error').textContent = 'Purpose is required';
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            return;
        }
        
        // Show loading spinner
        submitBtn.disabled = true;
        document.querySelector('.btn-text').textContent = 'Processing...';
        spinner.classList.remove('hidden');
    });
    
    // Dropdown functionality
    document.querySelectorAll('.dropdown-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const dropdownContent = button.nextElementSibling;
            dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
        });
    });
    
    // Profile dropdown
    const userIcon = document.getElementById('userIcon');
    const userDropdown = document.getElementById('userDropdown');
    
    userIcon.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
        userDropdown.style.display = 'none';
    });
});