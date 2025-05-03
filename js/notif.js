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

//design for content
document.addEventListener("DOMContentLoaded", function() {
    const chartConfigs = [
        { id: "chart1", type: "bar", data: [3, 6, 4, 8, 5] },
        { id: "chart2", type: "doughnut", data: [2, 3, 4] },
        { id: "chart3", type: "line", data: [1, 4, 2, 5, 3] },
        { id: "chart4", type: "bar", data: [5, 2, 3, 4, 6], horizontal: true }
    ];
    
    chartConfigs.forEach(config => {
        new Chart(document.getElementById(config.id).getContext('2d'), {
            type: config.type,
            data: {
                labels: ["A", "B", "C", "D", "E"],
                datasets: [{
                    data: config.data,
                    backgroundColor: "rgba(255, 255, 255, 0.2)",
                    borderColor: "white",
                    borderWidth: 1,
                    fill: false,
                    pointRadius: 0,
                    tension: 0.4
                }]
            },
            options: {
                indexAxis: config.horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { display: false }, y: { display: false } },
                plugins: { legend: { display: false } }
            }
        });
    });
    
    new Chart(document.getElementById("mainChart").getContext("2d"), {
        type: "bar",
        data: {
            labels: ["Users", "Approved Requests", "Pending Request", "Total Items"],
            datasets: [{
                label: "Overview",
                data: [150, 300, 200, 500],
                backgroundColor: ["#f7971e", "#ff416c", "#00b09b", "#6a11cb"],
                borderColor: "white",
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } }
        }
    });
});

// recent items
document.addEventListener("DOMContentLoaded", function() {
    const chartConfigs = [
        { id: "chart1", type: "bar", data: [3, 6, 4, 8, 5], color: ["#6a11cb", "#2575fc"] },
        { id: "chart2", type: "doughnut", data: [2, 3, 4], color: ["#00b09b", "#96c93d"] },
        { id: "chart3", type: "line", data: [1, 4, 2, 5, 3], color: ["#ff416c", "#ff4b2b"] },
        { id: "chart4", type: "bar", data: [5, 2, 3, 4, 6], color: ["#f7971e", "#ffd200"], horizontal: true }
    ];
    
    chartConfigs.forEach(config => {
        new Chart(document.getElementById(config.id).getContext('2d'), {
            type: config.type,
            data: {
                labels: ["A", "B", "C", "D", "E"],
                datasets: [{
                    data: config.data,
                    backgroundColor: config.color,
                    borderColor: "white",
                    borderWidth: 1,
                    fill: false,
                    pointRadius: 0,
                    tension: 0.4
                }]
            },
            options: {
                indexAxis: config.horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { display: false }, y: { display: false } },
                plugins: { legend: { display: false } }
            }
        });
    });
});

// action buttons

document.addEventListener("DOMContentLoaded", function () {
    function openModal(id, name = '', description = '', model = '', expiration = '', brand = '', quantity = '') {
        let modal = document.getElementById(id);

        if (!modal) {
            console.error("Modal not found:", id);
            return;
        }

        modal.style.display = 'flex';

        // If it's the view modal, update details
        if (id === 'viewModal') {
            document.getElementById('modalItemName').innerText = name;
            document.getElementById('modalDescription').innerText = description;
            document.getElementById('modalModel').innerText = model;
            document.getElementById('modalExpiration').innerText = expiration;
            document.getElementById('modalBrand').innerText = brand;
            document.getElementById('modalQuantity').innerText = quantity;
        }
    }

    // Open the View Details modal
    document.querySelectorAll(".view").forEach(button => {
        button.addEventListener("click", function () {
            const row = this.closest("tr");
            openModal('viewModal',
                row.cells[0].innerText,
                row.cells[1].innerText,
                row.cells[2].innerText,
                row.cells[3].innerText,
                row.cells[4].innerText,
                row.cells[5].innerText
            );
        });
    });

    // Open the Approve modal
    document.querySelectorAll(".approve").forEach(button => {
        button.addEventListener("click", function () {
            openModal('approveModal');
        });
    });

    // Open the Reject modal
    document.querySelectorAll(".reject").forEach(button => {
        button.addEventListener("click", function () {
            openModal('rejectModal');
        });
    });

    // Close modals when clicking X button
    document.querySelectorAll(".close").forEach(button => {
        button.addEventListener("click", function () {
            this.closest(".modal").style.display = "none";
        });
    });

    // Close modals when clicking outside the modal content
    document.querySelectorAll(".modal").forEach(modal => {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    });
});


 // AJAX Helper Function
        async function makeRequest(url, method = 'POST', data = {}) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            };

            const response = await fetch(url, {
                method: method,
                headers: headers,
                body: method !== 'GET' ? JSON.stringify(data) : null
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.json();
        }

        // Notification Actions
        async function handleNotificationAction(button, endpoint, notificationId = null) {
            const buttonText = button.querySelector('.button-text');
            const loader = button.querySelector('.loading');
            
            try {
                button.disabled = true;
                buttonText.style.display = 'none';
                loader.style.display = 'inline-block';

                const data = notificationId ? { notificationId } : {};
                const response = await makeRequest(endpoint, 'POST', data);

                if (response.success) {
                    // Handle UI update
                    if (endpoint.includes('delete')) {
                        button.closest('.notification-item').remove();
                    } else if (endpoint.includes('read')) {
                        button.closest('.notification-item').classList.add('read');
                        button.remove();
                    }
                } else {
                    alert(response.message || 'Action failed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            } finally {
                button.disabled = false;
                buttonText.style.display = 'inline-block';
                loader.style.display = 'none';
            }
        }

       