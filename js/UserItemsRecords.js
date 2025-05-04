/**
 * User Item Records JavaScript
 * Handles all client-side functionality for the User Item Records page
 */

class UserItemRecords {
    constructor() {
      this.initElements();
      this.initEventListeners();
      this.restoreDropdownState();
    }
  
    // Initialize DOM elements
    initElements() {
      // Profile dropdown elements
      this.userIcon = document.getElementById('userIcon');
      this.userDropdown = document.getElementById('userDropdown');
  
      // Sidebar dropdown elements
      this.dropdownArrows = document.querySelectorAll('.arrow-icon');
  
      // Filter and pagination elements
      this.filterForm = document.getElementById('filter-form');
      this.searchBox = document.getElementById('search-box');
      this.statusFilter = document.getElementById('status-filter');
      this.categoryFilter = document.getElementById('category-filter');
      this.pageInput = document.getElementById('page-input');
      this.clearFiltersBtn = document.getElementById('clear-filters-btn');
    }
  
    // Initialize all event listeners
    initEventListeners() {
      // Profile dropdown
      if (this.userIcon && this.userDropdown) {
        this.userIcon.addEventListener('click', (e) => this.toggleProfileDropdown(e));
        document.addEventListener('click', (e) => this.closeProfileDropdown(e));
      }
  
      // Sidebar dropdowns
      this.dropdownArrows.forEach(arrow => {
        arrow.addEventListener('click', (e) => this.toggleSidebarDropdown(e));
      });
  
      // Filter and pagination
      if (this.filterForm) {
        // Search with debounce
        if (this.searchBox) {
          this.searchBox.addEventListener('input', () => this.handleSearch());
        }
  
        // Filter changes
        if (this.statusFilter) {
          this.statusFilter.addEventListener('change', () => this.handleFilterChange());
        }
  
        if (this.categoryFilter) {
          this.categoryFilter.addEventListener('change', () => this.handleFilterChange());
        }
  
        // Clear filters
        if (this.clearFiltersBtn) {
          this.clearFiltersBtn.addEventListener('click', () => this.clearFilters());
        }
      }
  
      // Expose changePage to global scope
      window.changePage = (page) => this.changePage(page);
    }
  
    // Profile dropdown methods
    toggleProfileDropdown(e) {
      e.stopPropagation();
      this.userDropdown.classList.toggle('show');
    }
  
    closeProfileDropdown(e) {
      if (!this.userIcon.contains(e.target)) {
        this.userDropdown.classList.remove('show');
      }
    }
  
    // Sidebar dropdown methods
    toggleSidebarDropdown(e) {
      e.stopPropagation();
      const parent = e.currentTarget.closest('.dropdown');
      parent.classList.toggle('active');
      this.saveDropdownState(parent);
    }
  
    saveDropdownState(dropdown) {
      const dropdownText = dropdown.querySelector('.text').innerText;
      const dropdownState = JSON.parse(localStorage.getItem('dropdownState')) || {};
      dropdownState[dropdownText] = dropdown.classList.contains('active');
      localStorage.setItem('dropdownState', JSON.stringify(dropdownState));
    }
  
    restoreDropdownState() {
      const savedState = JSON.parse(localStorage.getItem('dropdownState')) || {};
      this.dropdownArrows.forEach(arrow => {
        const parent = arrow.closest('.dropdown');
        const dropdownText = parent.querySelector('.text').innerText;
        if (savedState[dropdownText]) {
          parent.classList.add('active');
        }
      });
    }
  
    // Filter and pagination methods
    handleSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.pageInput.value = 1;
        this.submitForm();
      }, 500);
    }
  
    handleFilterChange() {
      this.pageInput.value = 1;
      this.submitForm();
    }
  
    clearFilters() {
      if (this.searchBox) this.searchBox.value = '';
      if (this.statusFilter) this.statusFilter.value = '';
      if (this.categoryFilter) this.categoryFilter.value = '';
      if (this.pageInput) this.pageInput.value = 1;
      this.submitForm();
    }
  
    changePage(page) {
      this.pageInput.value = page;
      this.submitForm();
    }
  
    submitForm() {
      try {
        this.filterForm.submit();
      } catch (error) {
        console.error('Error submitting form:', error);
        // Fallback to manual form submission if needed
        const formData = new FormData(this.filterForm);
        const params = new URLSearchParams(formData);
        window.location.href = `${this.filterForm.action}?${params.toString()}`;
      }
    }
  }
  
  // Initialize the application when DOM is fully loaded
  document.addEventListener('DOMContentLoaded', () => {
    new UserItemRecords();
  });