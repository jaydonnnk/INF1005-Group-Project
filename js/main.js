/**
 * main.js — Custom JavaScript
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Handles: active nav highlighting, image popups,
 * AJAX game loading on booking form, client-side form validation.
 */

document.addEventListener("DOMContentLoaded", function () {
    activateMenu();
    registerImagePopups();
    registerFormValidation();
    registerBookingGameLoader();
});

/**
 * Highlight the nav link matching the current page URL.
 * @returns {void}
 */
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

/**
 * Register click-to-enlarge popups on all .img-thumbnail elements.
 * Supports keyboard dismissal (Escape) and returns focus to the thumbnail.
 * @returns {void}
 */
function registerImagePopups() {
    const thumbnails = document.getElementsByClassName("img-thumbnail");

    for (const thumbnail of thumbnails) {
        thumbnail.addEventListener("click", function () {
            // Remove any existing popup first
            const existingPopup = document.querySelector(".img-popup");
            if (existingPopup) {
                existingPopup.remove();
                return;
            }

            // Create popup element
            const popup = document.createElement("div");
            popup.setAttribute("class", "img-popup");
            popup.setAttribute("role", "dialog");
            popup.setAttribute("aria-label", "Enlarged image view");

            // Get the larger image src (swap _small with _large if applicable)
            let largeSrc = this.getAttribute("src");
            if (largeSrc.includes("_small")) {
                largeSrc = largeSrc.replace("_small", "_large");
            }

            const img = document.createElement("img");
            img.src = largeSrc;
            img.alt = this.getAttribute("alt") + " (enlarged)";
            popup.appendChild(img);

            // Close popup on click
            popup.addEventListener("click", function () {
                popup.remove();
                document.removeEventListener("keydown", handleEscape);
            });

            // Close popup on Escape key
            const button = this;
            function handleEscape(e) {
                if (e.key === "Escape") {
                    popup.remove();
                    document.removeEventListener("keydown", handleEscape);
                    button.focus();
                }
            }
            document.addEventListener("keydown", handleEscape);

            // Insert popup into the page
            popup.setAttribute("tabindex", "-1");
            document.body.appendChild(popup);
            popup.focus();
        });
    }
}

/**
 * Load available games via AJAX when both date and time slot are selected.
 * Populates the game dropdown and handles pre-selection from URL params.
 * @returns {void}
 */
function registerBookingGameLoader() {
    const dateInput = document.getElementById("booking_date");
    const slotSelect = document.getElementById("time_slot");
    const gameSelect = document.getElementById("game_id");

    // Only run on pages with all three elements (booking form)
    if (!dateInput || !slotSelect || !gameSelect) return;

    // Skip if this is the edit form (game_id is not disabled)
    if (!gameSelect.disabled) return;

    // Check if a game was pre-selected from the URL (e.g. from games.php "Book" button)
    const bookingForm = document.querySelector('form[data-preselect-game]');
    const preselectGameId = bookingForm ? (bookingForm.dataset.preselectGame || '') : '';

    function loadAvailableGames() {
        const date = dateInput.value;
        const slot = slotSelect.value;

        if (!date || !slot) {
            gameSelect.disabled = true;
            gameSelect.innerHTML = '<option value="">Select date and time first</option>';
            return;
        }

        gameSelect.disabled = true;
        gameSelect.innerHTML = '<option value="">Loading games...</option>';

        fetch("process/get_available_games.php?booking_date=" + encodeURIComponent(date) + "&time_slot=" + encodeURIComponent(slot))
            .then(function (response) {
                if (!response.ok) throw new Error("Server error");
                return response.json();
            })
            .then(function (games) {
                gameSelect.innerHTML = '<option value="">-- Select a game --</option>';

                games.forEach(function (game) {
                    const option = document.createElement("option");
                    option.value = game.game_id;
                    const copies = Number.parseInt(game.available_copies, 10);

                    if (copies > 0) {
                        option.textContent = game.title + " (" + copies + " available)";
                    } else {
                        option.textContent = game.title + " (0 available \u2014 fully booked)";
                        option.disabled = true;
                    }

                    gameSelect.appendChild(option);
                });

                // Pre-select game if passed via URL
                if (preselectGameId) {
                    const targetOption = gameSelect.querySelector('option[value="' + preselectGameId + '"]');
                    if (targetOption && !targetOption.disabled) {
                        gameSelect.value = preselectGameId;
                    } else if (targetOption?.disabled) {
                        // Game exists but is fully booked for this slot
                        const helpText = document.getElementById("game_help");
                        if (helpText) {
                            helpText.textContent = "The game you selected is fully booked for this slot. Please choose another or try a different time.";
                            helpText.classList.add("text-danger");
                        }
                    }
                }

                gameSelect.disabled = false;
            })
            .catch(function () {
                gameSelect.innerHTML = '<option value="">Could not load games</option>';
                gameSelect.disabled = true;
            });
    }

    dateInput.addEventListener("change", loadAvailableGames);
    slotSelect.addEventListener("change", loadAvailableGames);
}

/**
 * Add Bootstrap validation feedback and password-match checking on form submit.
 * @returns {void}
 */
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
