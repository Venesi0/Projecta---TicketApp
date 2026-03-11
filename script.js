console.log("script.js chargé sur projects.php");


/* --- Table filters logic --- */
const applyFilters = () => {
    const rows = document.querySelectorAll(".tickets-table tbody tr");
    if (rows.length === 0) return;

    const projectSelect = document.querySelector('#projectSelect');
    const statusSelect = document.querySelector('select[name="status"]');
    const typeSelect = document.querySelector('select[name="type"]');
    const prioritySelect = document.querySelector('select[name="priority"]');

    const projectValue = projectSelect ? projectSelect.value.toLowerCase().trim() : "all";
    const statusValue = statusSelect ? statusSelect.value.toLowerCase().trim() : "status";
    const typeValue = typeSelect ? typeSelect.value.toLowerCase().trim() : "type";
    const priorityValue = prioritySelect ? prioritySelect.value.toLowerCase().trim() : "priority";

    const isProjectDetailsPage = document.querySelector(".project-tickets-section");

    // Filters in user side
    const userProjectFilter = document.querySelector("#projectFilter");
    const userStatusFilter = document.querySelector("#statusFilter");

    rows.forEach(row => {
        if (row.closest(".noFilter")) return;

        const projectCell = row.querySelector(".ticket-info-cell span");
        const statusCell = row.querySelector(".status-tag");
        const typeCell = row.querySelector(".type, .billing-tag");
        const priorityCell = row.querySelector(".badge-priority");

        const projectText = projectCell ? projectCell.textContent.toLowerCase().trim() : "";
        const statusText = statusCell ? statusCell.textContent.toLowerCase().trim() : "";
        const typeText = typeCell ? typeCell.textContent.toLowerCase().trim() : "";
        const priorityText = priorityCell ? priorityCell.textContent.toLowerCase().trim() : "";

        if (userProjectFilter || userStatusFilter) {
            const projectValue = userProjectFilter ? userProjectFilter.value.toLowerCase().trim() : "all projects";
            const statusValue = userStatusFilter ? userStatusFilter.value.toLowerCase().trim() : "all statuses";

            const projectCell = row.children[1];
            const statusCell = row.querySelector(".status-tag");
            const projectText = projectCell ? projectCell.textContent.toLowerCase().trim() : "";
            const statusText = statusCell ? statusCell.textContent.toLowerCase().trim() : "";

            const projectMatch =
                projectValue.includes("all") || projectText === projectValue;
            const statusMatch =
                statusValue.includes("all") || statusText === statusValue;

            if (projectMatch && statusMatch) {
                row.classList.remove("titanic");
            } else {
                row.classList.add("titanic");
            }
            return;
        }

        if (isProjectDetailsPage) {
            const typeMatch = typeValue.includes("type") || typeValue.includes("all") || typeText === typeValue;

            if (typeMatch) {
                row.classList.remove("titanic");
            } else {
                row.classList.add("titanic");
            }
            return;
        }

        const projectMatch = projectValue.includes("all") || projectText.includes(projectValue);
        const statusMatch = (statusValue.includes("status") && statusText !== "archived") || statusValue.includes("all") || statusText === statusValue;
        
        const typeMatch = typeValue.includes("type") || typeValue.includes("all") || typeText === typeValue;
        
        const priorityMatch = priorityValue.includes("priority") || priorityValue.includes("all") || priorityText === priorityValue;

        if (projectMatch && statusMatch && typeMatch && priorityMatch) {
            row.classList.remove("titanic");
        } else {
            row.classList.add("titanic");
        }
    });
};

document.querySelectorAll(".ticket-status").forEach(filter => {
    filter.addEventListener("change", applyFilters);
});

const projectSelector = document.querySelector('#projectSelect');
if (projectSelector) {
    projectSelector.addEventListener("change", applyFilters);
}

const userProjectFilter = document.querySelector("#projectFilter");
const userStatusFilter = document.querySelector("#statusFilter");
if (userProjectFilter) userProjectFilter.addEventListener("change", applyFilters);
if (userStatusFilter) userStatusFilter.addEventListener("change", applyFilters);


/* --- Forms Validations --- */
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d).{6,}$/;

function toggleError(elementId, isValid) {
    const errorElement = document.querySelector(elementId);
    if (!errorElement) return;
    
    if (isValid) {
        errorElement.classList.add('titanic'); 
    } else {
        errorElement.classList.remove('titanic');
    }
}

function showToast(message, selector) {
    let toast = selector ? document.querySelector(selector) : null;
    if (!toast) {
        toast = document.createElement("div");
        toast.className = "toast toast-success";
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add("show");
    setTimeout(() => {
        toast.classList.remove("show");
    }, 3000);
}


/* --- Login Form Logic --- */
const loginForm = document.querySelector('#loginForm');

if (loginForm) {
    loginForm.addEventListener("submit", function(event) {
        event.preventDefault();

        const email = document.querySelector("#usernameInput").value.trim();
        const password = document.querySelector("#passwordInput").value.trim();

        const isEmailValid = emailRegex.test(email);
        const isPasswordValid = passwordRegex.test(password);

        toggleError('#mailError', isEmailValid);
        toggleError('#passwordError', isPasswordValid);

        if (isEmailValid && isPasswordValid) {
            this.submit();
        } else {
            console.log("Login validation failed");
        }
    });
}


/* --- SignUp Form Logic --- */
const signUpForm = document.querySelector('#signUpForm');

if (signUpForm) {
    signUpForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const name = document.querySelector("#usernameNameInput").value.trim();
        const email = document.querySelector("#usernameInput").value.trim();
        const password = document.querySelector("#passwordInput").value.trim();
        const confirmPassword = document.querySelector("#confirmPasswordInput").value.trim();

        const isNameValid = name.length >= 4;
        const isEmailValid = emailRegex.test(email);
        const isPasswordValid = passwordRegex.test(password);
        const isMatch = (password === confirmPassword && password !== "");

        toggleError('#nameError', isEmailValid);
        toggleError('#mailError', isEmailValid);
        toggleError('#passwordError', isPasswordValid);
        toggleError('#confirmedPasswordError', isMatch);

        if (isEmailValid && isPasswordValid && isMatch && isNameValid) {
            showToast("Account created successfully.");
            this.submit();
        } else {
            console.log("SignUp validation failed");
        }
    });
}

/* --- Forgot Password Form Logic --- */
const forgotForm = document.querySelector('#ForgotPasswordForm');

if (forgotForm) {
    const codeInputs = forgotForm.querySelectorAll('.code-group input');

    codeInputs.forEach((input, index) => {
        input.addEventListener('input', () => {
            if (input.value.length === 1 && index < codeInputs.length - 1) {
                codeInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && input.value === '' && index > 0) {
                codeInputs[index - 1].focus();
            }
        });
    });


    forgotForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const email = document.querySelector("#usernameInput").value.trim();
        
        let enteredCode = "";
        codeInputs.forEach(input => enteredCode += input.value);

        const isEmailValid = emailRegex.test(email);
        const isCodeValid = (enteredCode === "222222");

        toggleError('#mailError', isEmailValid);
        toggleError('#codeError', isCodeValid);

        if (isEmailValid && isCodeValid) {
            this.submit();
        } else {
            console.log("Validation code or Email failed");
        }
    });
}

/* --- Reset Form Logic --- */
const resetForm = document.querySelector('#resetForm');

if (resetForm) {
    resetForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const password = document.querySelector("#newPasswordInput").value.trim();
        const confirmPassword = document.querySelector("#confirmPasswordInput").value.trim();

        const isPasswordValid = passwordRegex.test(password);
        const isMatch = (password === confirmPassword && password !== "");

        toggleError('#passwordError', isPasswordValid);
        toggleError('#confirmedPasswordError', isMatch);

        if (isPasswordValid && isMatch) {
            showToast("Password updated.");
            this.submit();
        } else {
            console.log("Reset passwordd validation failed");
        }
    });
}

/* --- Confirm a project --- */
document.addEventListener('click', function (e) {
    const confirmProjectLink = e.target.closest('.confirm-project-btn');
    if (!confirmProjectLink) return;

    e.preventDefault();

    const projectCard = confirmProjectLink.closest(".project-card");
    if (!projectCard) return;

    const openTickets = confirmProjectLink.dataset.openTickets || "0";

    const openTicketsLabel = document.createElement("span");
    openTicketsLabel.textContent = `${openTickets} Opened tickets`;
    confirmProjectLink.replaceWith(openTicketsLabel);

    const statusTag = projectCard.querySelector(".project-status-tag");
    if (statusTag && statusTag.classList.contains("ready")) {
        statusTag.classList.remove("ready");
        statusTag.textContent = "Active";
    }
});



/* Project Rejection in modal */
const rejectButtons = document.querySelectorAll('.request-item .btn-reject');

rejectButtons.forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        const projectContainer = this.closest('.request-item');

        if (projectContainer) {
        const confirmAction = confirm("Are you sure you want to reject and delete this project ?");
        
        if (confirmAction) {
            projectContainer.remove();
        }
        }
    });
});

/* Project Form Validations fields */
const projectForm = document.querySelector("#createProjectForm");

if(projectForm) {
    projectForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const name = document.querySelector("#projectName").value.trim();
        const hours = document.querySelector("#contractHours").value.trim();
        const descInput = document.querySelector("#projectDesc");
        const clientInput = document.querySelector("#projectClient");

        if (descInput) {
            const desc = descInput.value.trim();
            const isNameValid = name.length >= 6;
            const isDescValid = desc.length >= 30;
            const isHourValid = parseInt(hours) >= 5;

            toggleError('#userProjectNameError', isNameValid);
            toggleError('#userProjectDescError', isDescValid);
            toggleError('#userProjectHoursError', isHourValid);

            if (isNameValid && isDescValid && isHourValid) {
                showToast("Project request sent.");
                this.submit();
            } else {
                console.log("User project form validation failed");
            }
            return;
        }

        const client = clientInput ? clientInput.value.trim() : "";
        const isNameValid = name.length >= 6;
        const isClientValid = client.length >= 4;
        const isHourValid = parseInt(hours) >= 10;

        toggleError('#nameError', isNameValid);
        toggleError('#clientError', isClientValid);
        toggleError('#hoursError', isHourValid);

        if(isNameValid && isClientValid && isHourValid) {
            window.location.hash = "";
            showToast("Project request sent.");
            this.submit();
        } else {
            console.log("Project form validation failed")
        }

    });
}


/* Ticket creation from project Form Validations fields */
const ticketForm = document.querySelector("#ticketForm");

if(ticketForm) {
    ticketForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const title = document.querySelector("#ticketTitle").value;
        const desc = document.querySelector("#ticketDesc").value;
        const hours = document.querySelector("#ticketHours").value;
        const client = document.querySelector("#ticketClient").value;

        const isTitleValid = title.length >= 15;
        const isDescValid = desc.length >= 30;
        const isHourValid = parseInt(hours) >= 3;
        const isClientValid = client.length >= 4;

        toggleError('#titleError', isTitleValid);
        toggleError('#descError', isDescValid);
        toggleError('#hourError', isHourValid);
        toggleError('#clientError', isClientValid);


        if(isTitleValid && isDescValid && isHourValid && isClientValid) {
            window.location.hash = ""; 
            showToast("Ticket created.");
            this.submit();
        } else {
            console.log("Ticket form validation failed")
        }

    });
}

/* --- User Project Ticket Form Validations --- */
const userTicketForm = document.querySelector("#userTicketForm");

if (userTicketForm) {
    userTicketForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const title = document.querySelector("#userTicketTitle").value.trim();
        const desc = document.querySelector("#userTicketDesc").value.trim();
        const hours = document.querySelector("#userTicketHours").value.trim();

        const isTitleValid = title.length >= 6;
        const isDescValid = desc.length >= 50;
        const isHourValid = hours === "" || parseFloat(hours) >= 3;

        toggleError('#userTicketTitleError', isTitleValid);
        toggleError('#userTicketDescError', isDescValid);
        toggleError('#userTicketHoursError', isHourValid);

        if (isTitleValid && isDescValid && isHourValid) {
            window.location.hash = "";
            showToast("Ticket submitted successfully.");
            this.submit();
        } else {
            console.log("User ticket form validation failed");
        }
    });
}

/* --- User Tickets Page Form Validations --- */
const userTicketsForm = document.querySelector("#userTicketsForm");

if (userTicketsForm) {
    userTicketsForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const title = document.querySelector("#userTicketsTitle").value.trim();
        const desc = document.querySelector("#userTicketsDesc").value.trim();
        const hours = document.querySelector("#userTicketsHours").value.trim();

        const isTitleValid = title.length >= 6;
        const isDescValid = desc.length >= 50;
        const isHourValid = hours === "" || parseFloat(hours) >= 3;

        toggleError('#userTicketsTitleError', isTitleValid);
        toggleError('#userTicketsDescError', isDescValid);
        toggleError('#userTicketsHoursError', isHourValid);

        if (isTitleValid && isDescValid && isHourValid) {
            showToast("Ticket submitted successfully.", "#userTicketToast");
            this.submit();
            window.location.hash = "";
        } else {
            console.log("User tickets form validation failed");
        }
    });
}

/* --- Collaborators Form Validations --- */
const addCollabForm = document.querySelector("#addCollabForm");

if (addCollabForm) {
    addCollabForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const name = document.querySelector("#collabName").value.trim();
        const role = document.querySelector("#collabRole").value.trim();
        const email = document.querySelector("#collabEmail").value.trim();

        const nameParts = name.split(" ").filter(part => part.length > 0);
        const isNameValid = nameParts.length >= 2;
        const isRoleValid = role.length >= 4;
        const isEmailValid = emailRegex.test(email);

        toggleError('#collabNameError', isNameValid);
        toggleError('#collabRoleError', isRoleValid);
        toggleError('#collabEmailError', isEmailValid);

        if (isNameValid && isRoleValid && isEmailValid) {
            window.location.hash = ""; 
            showToast("Collaborator added.");
            this.submit();
        } else {
            console.log("Collaborator form validation failed");
        }
    });
}

const editCollabForm = document.querySelector("#editCollabForm");

if (editCollabForm) {
    editCollabForm.addEventListener("submit", function(e) {
        //e.preventDefault();

        const role = document.querySelector("#editRole").value.trim();
        const isRoleValid = role.length >= 4;

        toggleError('#collabEditRoleError', isRoleValid);

        if (isRoleValid) {
            window.location.hash = ""; 
            showToast("Collaborator updated.");
            this.submit();
        } else {
            console.log("Edit collaborator validation failed");
        }
    });
}

/* --- Admin Client Form Validations --- */
const createClientForm = document.querySelector("#createClientForm");

if (createClientForm) {
    createClientForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const name = document.querySelector("#clientName").value.trim();
        const status = document.querySelector("#projectStatus").value.trim();
        const date = document.querySelector("#clientDate").value.trim();

        const isNameValid = name.length >= 4;
        const isStatusValid = status !== "";
        const isDateValid = date !== "";

        toggleError('#clientNameError', isNameValid);
        toggleError('#clientStatusError', isStatusValid);
        toggleError('#clientDateError', isDateValid);

        if (isNameValid && isStatusValid && isDateValid) {
            showToast("Client created.");
            this.submit();
        } else {
            console.log("Client form validation failed");
        }
    });
}

const editClientForm = document.querySelector("#editClientForm");

if (editClientForm) {
    editClientForm.addEventListener("submit", function(e) {
        //e.preventDefault();

        const name = document.querySelector("#clientNameEdit").value.trim();
        const status = document.querySelector("#projectStatusEdit").value.trim();
        const date = document.querySelector("#clientDateEdit").value.trim();

        const isNameValid = name.length >= 4;
        const isStatusValid = status !== "";
        const isDateValid = date !== "";

        toggleError('#clientNameEditError', isNameValid);
        toggleError('#clientStatusEditError', isStatusValid);
        toggleError('#clientDateEditError', isDateValid);

        if (isNameValid && isStatusValid && isDateValid) {
            showToast("Client updated.");
            this.submit();
        } else {
            console.log("Edit client form validation failed");
        }
    });
}

/* --- Admin Settings Password Validation --- */
const adminPasswordForm = document.querySelector("#adminPasswordForm");

if (adminPasswordForm) {
    adminPasswordForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const currentPassword = document.querySelector("#currentPassword").value.trim();
        const newPassword = document.querySelector("#newPassword").value.trim();
        const confirmPassword = document.querySelector("#confirmPassword").value.trim();

        const isCurrentValid = currentPassword.length > 0;
        const isNewValid = passwordRegex.test(newPassword);
        const isMatch = newPassword === confirmPassword && newPassword !== "";

        toggleError('#currentPasswordError', isCurrentValid);
        toggleError('#newPasswordError', isNewValid);
        toggleError('#confirmPasswordError', isMatch);

        if (isCurrentValid && isNewValid && isMatch) {
            showToast("Password updated.");
            this.submit();
        } else {
            console.log("Admin password validation failed");
        }
    });
}

/* --- Admin Settings Preferences Toast --- */
const adminNotifForm = document.querySelector("#adminNotifForm");
if (adminNotifForm) {
    adminNotifForm.addEventListener("submit", function(e) {
        e.preventDefault();
        showToast("Preferences saved.");
    });
}

/* --- Admin Profile Validation --- */
const adminProfileForm = document.querySelector("#adminProfileForm");
if (adminProfileForm) {
    adminProfileForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const name = document.querySelector("#adminName").value.trim();
        const email = document.querySelector("#adminEmail").value.trim();
        const phone = document.querySelector("#adminPhone").value.trim();

        const nameParts = name.split(" ").filter(part => part.length > 0);
        const isNameValid = nameParts.length >= 2;
        const isEmailValid = emailRegex.test(email);
        const isPhoneValid = phone.length >= 10;

        toggleError('#adminNameError', isNameValid);
        toggleError('#adminEmailError', isEmailValid);
        toggleError('#adminPhoneError', isPhoneValid);

        if (isNameValid && isEmailValid && isPhoneValid) {
            showToast("Profile updated.");
            this.submit();
        } else {
            console.log("Admin profile validation failed");
        }
    });
}

/* --- Admin Tickets: Update + Archive Toasts --- */
const adminTicketUpdateForms = document.querySelectorAll(".admin-ticket-update-form");
if (adminTicketUpdateForms.length > 0) {
    adminTicketUpdateForms.forEach(form => {
        form.addEventListener("submit", function(e) {
            const action = e.submitter && e.submitter.value ? e.submitter.value : "update";
            showToast(action === "archive" ? "Ticket archived." : "Ticket updated.");
            window.location.hash = "";
        });
    });
}
