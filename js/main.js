document.addEventListener("DOMContentLoaded", function () {
    activateMenu();
    registerImagePopups();
    registerFormValidation();
});

// Active Menu Highlighting
// Sets the 'active' class on the nav link matching the current page URL.
function activateMenu() {
    const navLinks = document.querySelectorAll("nav a.nav-link");
    navLinks.forEach(function (link) {
        // Compare just the filename portion
        const linkPage = link.href.split("/").pop().split("?")[0];
        const currentPage = location.href.split("/").pop().split("?")[0];
        if (linkPage === currentPage) {
            link.classList.add("active");
            link.setAttribute("aria-current", "page");
        }
    });
}

// Image Popup on Click
// Clicking a .img-thumbnail image opens a larger popup; clicking again closes it.
function registerImagePopups() {
    const thumbnails = document.getElementsByClassName("img-thumbnail");

    for (let i = 0; i < thumbnails.length; i++) {
        thumbnails[i].addEventListener("click", function () {
            // Remove any existing popup first
            const existingPopup = document.querySelector(".img-popup");
            if (existingPopup) {
                existingPopup.remove();
                return;
            }

            // Create popup element
            const popup = document.createElement("span");
            popup.setAttribute("class", "img-popup");
            popup.setAttribute("role", "dialog");
            popup.setAttribute("aria-label", "Enlarged image view");

            // Get the larger image src (swap _small with _large if applicable)
            let largeSrc = this.getAttribute("src");
            if (largeSrc.includes("_small")) {
                largeSrc = largeSrc.replace("_small", "_large");
            }

            popup.innerHTML = '<img src="' + largeSrc + '" alt="' +
                              this.getAttribute("alt") + ' (enlarged)">';

            // Close popup on click
            popup.addEventListener("click", function () {
                popup.remove();
            });

            // Insert popup into the page
            document.body.appendChild(popup);
        });
    }
}

// Client-side Form Validation Enhancement
// Adds visual feedback for Bootstrap forms.
function registerFormValidation() {
    const forms = document.querySelectorAll("form.needs-validation");

    forms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            // Password confirmation check
            const pwd = form.querySelector('input[name="pwd"]');
            const pwdConfirm = form.querySelector('input[name="pwd_confirm"]');
            if (pwd && pwdConfirm && pwd.value !== pwdConfirm.value) {
                pwdConfirm.setCustomValidity("Passwords do not match.");
            } else if (pwdConfirm) {
                pwdConfirm.setCustomValidity("");
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add("was-validated");
        });
    });
}
