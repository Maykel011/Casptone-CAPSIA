document.addEventListener("DOMContentLoaded", function () {
    // Dropdown arrows functionality
    const dropdownArrows = document.querySelectorAll(".arrow-icon");
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

    dropdownArrows.forEach(arrow => {
        let parent = arrow.closest(".dropdown");
        let dropdownText = parent.querySelector(".text").innerText;

        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }

        arrow.addEventListener("click", function (event) {
            event.stopPropagation();
            let parent = this.closest(".dropdown");
            parent.classList.toggle("active");
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });

    // Profile Dropdown
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

    // Charts initialization
    const chartConfigs = [
        { id: "chart1", type: "bar", data: [3, 6, 4, 8, 5] },
        { id: "chart2", type: "doughnut", data: [2, 3, 4] },
        { id: "chart3", type: "line", data: [1, 4, 2, 5, 3] },
        { id: "chart4", type: "bar", data: [5, 2, 3, 4, 6], horizontal: true }
    ];
    
    chartConfigs.forEach(config => {
        const ctx = document.getElementById(config.id);
        if (ctx) {
            new Chart(ctx.getContext('2d'), {
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
        }
    });

    // Main Chart
    const mainCtx = document.getElementById("mainChart");
    if (mainCtx) {
        new Chart(mainCtx.getContext("2d"), {
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
    }

    // Modal functionality
    function openModal(id, name = '', description = '', model = '', category = '', status = '', quantity = '') {
        let modal = document.getElementById(id);
        if (!modal) {
            console.error("Modal not found:", id);
            return;
        }

        modal.style.display = 'flex';

        if (id === 'viewModal') {
            document.getElementById('modalItemName').innerText = name;
            document.getElementById('modalDescription').innerText = description;
            document.getElementById('modalModel').innerText = model;
            document.getElementById('modalExpiration').innerText = category;
            document.getElementById('modalBrand').innerText = status;
            document.getElementById('modalQuantity').innerText = quantity;
        }
    }

    // View button handlers
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

    // Approve/Reject buttons
    document.querySelectorAll(".approve").forEach(button => {
        button.addEventListener("click", function () {
            openModal('approveModal');
        });
    });

    document.querySelectorAll(".reject").forEach(button => {
        button.addEventListener("click", function () {
            openModal('rejectModal');
        });
    });

    // Close modals
    document.querySelectorAll(".close").forEach(button => {
        button.addEventListener("click", function () {
            this.closest(".modal").style.display = "none";
        });
    });

    document.querySelectorAll(".modal").forEach(modal => {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    });
});

    