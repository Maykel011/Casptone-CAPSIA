document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.getElementById("loginForm");

    loginForm.addEventListener("submit", function (event) {
        event.preventDefault(); // Prevent default form submission

        const formData = new FormData(loginForm);
        const loginData = {
            username: formData.get("username"),
            password: formData.get("password"),
        };

        fetch("../php/login.php", { // Ensure the correct path to the PHP file
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(loginData),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    window.location.href = data.redirectUrl; // Redirect on success
                } else {
                    alert(data.message); // Show error message
                }
            })
            .catch((error) => {
                console.error("Error:", error);
                alert("An error occurred. Please try again.");
            });
    });
});
