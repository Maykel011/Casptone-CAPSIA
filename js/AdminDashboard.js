document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.createElement('button');
    menuToggle.className = 'menu-toggle';
    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    document.querySelector('.left-side').prepend(menuToggle);
    
    menuToggle.addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });
    
    // User dropdown toggle
    const userIcon = document.getElementById('userIcon');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userIcon && userDropdown) {
        userIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            userDropdown.classList.remove('show');
        });
    }
    
    // Dropdown functionality
    const dropdownBtns = document.querySelectorAll('.dropdown-btn');
    dropdownBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.closest('.dropdown');
            dropdown.classList.toggle('active');
            
            // Close other dropdowns
            document.querySelectorAll('.dropdown').forEach(item => {
                if (item !== dropdown) {
                    item.classList.remove('active');
                }
            });
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown').forEach(item => {
                item.classList.remove('active');
            });
        }
    });
    
    // Responsive table scrolling
    const tables = document.querySelectorAll('.table-container');
    tables.forEach(table => {
        if (table.scrollWidth > table.clientWidth) {
            table.classList.add('scrollable');
        }
    });
    
    // Window resize handler
    window.addEventListener('resize', function() {
        document.querySelectorAll('.table-container').forEach(table => {
            if (table.scrollWidth > table.clientWidth) {
                table.classList.add('scrollable');
            } else {
                table.classList.remove('scrollable');
            }
        });
    });
});

