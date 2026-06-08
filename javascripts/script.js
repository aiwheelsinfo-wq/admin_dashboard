document.addEventListener("DOMContentLoaded", function () {
    const toggleIcon = document.getElementById("toggleIcon");
    if (!toggleIcon) {
        console.error("Toggle icon not found!");
        return;
    }
    toggleIcon.addEventListener("click", function () {
        const passwordField = document.getElementById("password");
        console.log("Toggle icon clicked, current type:", passwordField.type);
        if (passwordField.type === "password") {
            passwordField.type = "text";
            this.classList.remove("fa-eye");
            this.classList.add("fa-eye-slash");
            console.log("Switched to text");
        } else {
            passwordField.type = "password";
            this.classList.remove("fa-eye-slash");
            this.classList.add("fa-eye");
            console.log("Switched to password");
        }
    });

    // Handle login form submit
    document.getElementById("loginForm").addEventListener("submit", function (event) {
        event.preventDefault();
        let email = document.getElementById("email").value;
        let password = document.getElementById("password").value;
        let errorMsg = document.getElementById("error-message");
        fetch("auth_adminlogin.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: email, password: password })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    localStorage.setItem("admin_token", "logged_in");
                    window.location.href = "dashboard.php";
                } else {
                    errorMsg.textContent = data.message;
                    errorMsg.style.color = "red";
                }
            })
            .catch(error => console.error("Error:", error));
    });

    // Redirect unauthenticated users
    if (
        window.location.pathname.includes("dashboard.php") &&
        !localStorage.getItem("admin_token")
    ) {
        window.location.href = "adminlogin.php";
    }
});