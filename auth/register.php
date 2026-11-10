
<?php
session_start();
require_once '../config/db.php';

$error = '';
$success = '';
$redirectUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $fullname = trim(
        $firstname . ' ' . $middlename . ' ' . $lastname
    );
    $fullname = preg_replace('/\s+/', ' ', $fullname);
    $email = trim($_POST['email'] ?? '');
    $contactInput = trim($_POST['contact'] ?? '');
    $contact = preg_replace('/[^\d+]/', '', $contactInput);
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $organization_name = trim($_POST['organization_name'] ?? '');
    $role = trim($_POST['role'] ?? '');

    $namePattern = "/^[\p{L}]+(?:[ '\-][\p{L}]+)*$/u";
    $organizationPattern = "/^[\p{L}\p{N}][\p{L}\p{N} .,'&()\-\/]*$/u";

    if (
        $firstname === '' ||
        $lastname === '' ||
        $email === '' ||
        $contact === '' ||
        $password === '' ||
        $role === ''
    ) {
        $error = "Please complete all required fields.";
    } elseif (!preg_match('/^\+[1-9]\d{6,14}$/', $contact)) {
        $error = "Please enter a valid phone number with its country code.";
    } elseif (!preg_match($namePattern, $firstname)) {
        $error = "First name must contain letters only. Numbers and invalid characters are not allowed.";
    } elseif (
        $middlename !== '' &&
        !preg_match($namePattern, $middlename)
    ) {
        $error = "Middle name must contain letters only. Numbers and invalid characters are not allowed.";
    } elseif (!preg_match($namePattern, $lastname)) {
        $error = "Last name must contain letters only. Numbers and invalid characters are not allowed.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!in_array($role, ['Audience', 'Organizer'], true)) {
        $error = "Invalid role selected.";
    } elseif (
        $role === 'Organizer' &&
        $organization_name === ''
    ) {
        $error = "Organization name is required for Organizer accounts.";
    } elseif (
        $organization_name !== '' &&
        !preg_match($organizationPattern, $organization_name)
    ) {
        $error = "Please enter a valid organization name.";
    } else {
        $checkEmail = $conn->prepare(
            "SELECT user_id FROM users
             WHERE email = ? OR contact = ?
             LIMIT 1"
        );

        if (!$checkEmail) {
            $error = "Database error. Please try again.";
        } else {
            $checkEmail->bind_param("ss", $email, $contact);
            $checkEmail->execute();
            $checkEmail->store_result();

            if ($checkEmail->num_rows > 0) {
                $error = "Email address or phone number is already registered.";
                $checkEmail->close();
            } else {
                $checkEmail->close();

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO users (
                        firstname,
                        middlename,
                        lastname,
                        fullname,
                        email,
                        contact,
                        address,
                        password,
                        organization_name,
                        role,
                        account_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')"
                );

                if (!$stmt) {
                    $error = "Unable to create account. Please try again.";
                } else {
                    $stmt->bind_param(
                        "ssssssssss",
                        $firstname,
                        $middlename,
                        $lastname,
                        $fullname,
                        $email,
                        $contact,
                        $address,
                        $hashedPassword,
                        $organization_name,
                        $role
                    );

                    if ($stmt->execute()) {
                        $new_id = $stmt->insert_id;
                        $stmt->close();

                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $new_id;
                        $_SESSION['role'] = $role;
                        $_SESSION['organization_name'] = $organization_name;

                        if ($role === 'Audience') {
                            $success = 'Registration successful! Welcome to IPT Event System. Redirecting to your Audience Dashboard...';
                            $redirectUrl = '../users/user.php';
                        } elseif ($role === 'Organizer') {
                            $success = 'Registration successful! Welcome to IPT Event System. Redirecting to your Organizer Dashboard...';
                            $redirectUrl = '../organizer/org_dash.php';
                        }
                    } else {
                        $error = "Registration failed. Please try again.";
                        $stmt->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | IPT Event System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.6.0/build/css/intlTelInput.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/auth/register.css">
</head>
<body>

<div class="register-card">
    <div class="register-header text-center">
        <div class="register-icon">
            <i class="bi bi-person-plus-fill"></i>
        </div>
        <h3>Create Your Account</h3>
        <p>Register to access the IPT Event System</p>
    </div>

    <div class="register-body">

        <?php if ($success !== ''): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>
                    <strong>Registration Successful!</strong><br>
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    <div class="mt-2">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Please wait. Redirecting to your dashboard...
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <div class="section-title">
            <i class="bi bi-person-vcard me-1"></i>
            Account Information
        </div>

        <form method="POST" action="register.php" id="registerForm" novalidate>
            <div class="row g-3">

                <div class="col-12 col-md-4">
                    <label for="firstname" class="form-label">First Name <span class="required">*</span></label>
                    <input type="text" name="firstname" id="firstname" class="form-control"
                        placeholder="Enter your first name"
                        value="<?= htmlspecialchars($_POST['firstname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="given-name" required>
                    <div id="firstnameError" class="invalid-feedback">Enter a valid first name.</div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="middlename" class="form-label">Middle Name</label>
                    <input type="text" name="middlename" id="middlename" class="form-control"
                        placeholder="Enter your middle name (optional)"
                        value="<?= htmlspecialchars($_POST['middlename'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="additional-name">
                    <div id="middlenameError" class="invalid-feedback">Enter a valid middle name.</div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="lastname" class="form-label">Last Name <span class="required">*</span></label>
                    <input type="text" name="lastname" id="lastname" class="form-control"
                        placeholder="Enter your last name"
                        value="<?= htmlspecialchars($_POST['lastname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="family-name" required>
                    <div id="lastnameError" class="invalid-feedback">Enter a valid last name.</div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" id="email" class="form-control"
                        placeholder="Enter your email address"
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="email" required>
                </div>

                <div class="col-12 col-md-4">
                    <label for="contact" class="form-label">Contact Number <span class="required">*</span></label>
                    <input type="tel" name="contact" id="contact" class="form-control"
                        placeholder="Select country and enter phone number"
                        value="<?= htmlspecialchars($_POST['contact'] ?? '+63', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="tel" required>
                    <div id="contactError" class="invalid-feedback">
                        Enter a valid number including its country code.
                    </div>
                    <div id="phonePickerError" class="alert alert-warning d-none mt-2 mb-0">
                        Country selector could not load. Refresh this page before registering.
                    </div>
                    <div class="form-text mt-1">
                        Choose your country; its calling code is added automatically.
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" name="address" id="address" class="form-control"
                        placeholder="Enter your address"
                        value="<?= htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="street-address">
                </div>

                <div class="col-12 col-md-4">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <input type="password" name="password" id="password" class="form-control"
                        placeholder="Minimum 6 characters" autocomplete="new-password" required>
                    <div class="form-text mt-1">Must be at least 6 characters.</div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="role" class="form-label">Account Type <span class="required">*</span></label>
                    <select name="role" id="role" class="form-select" required>
                        <option value="" disabled <?= empty($_POST['role']) ? 'selected' : '' ?>>
                            Select account type
                        </option>
                        <option value="Audience" <?= (($_POST['role'] ?? '') === 'Audience') ? 'selected' : '' ?>>
                            Audience
                        </option>
                        <option value="Organizer" <?= (($_POST['role'] ?? '') === 'Organizer') ? 'selected' : '' ?>>
                            Organizer
                        </option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label for="organization_name" class="form-label">
                        Organization Name
                        <span id="organizationRequired" class="required">*</span>
                    </label>
                    <input type="text" name="organization_name" id="organization_name" class="form-control"
                        placeholder="Enter your organization name"
                        value="<?= htmlspecialchars($_POST['organization_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="organization">
                    <div id="organizationHelp" class="form-text mt-1">
                        Required for Organizer accounts.
                    </div>
                    <div id="organizationError" class="invalid-feedback">
                        Organization name is required for Organizer accounts.
                    </div>
                </div>

            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-register w-100">
                    <i class="bi bi-person-check-fill me-2"></i>
                    Create Account
                </button>
            </div>
        </form>

        <div class="login-area">
            <span class="text-muted">Already have an account?</span>
            <a href="login_user.php" class="login-link ms-1">Login here</a>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.6.0/build/js/intlTelInput.min.js"></script>

<script>
const firstnameInput = document.getElementById('firstname');
const middlenameInput = document.getElementById('middlename');
const lastnameInput = document.getElementById('lastname');
const organizationInput = document.getElementById('organization_name');
const organizationRequired = document.getElementById('organizationRequired');
const organizationHelp = document.getElementById('organizationHelp');
const organizationError = document.getElementById('organizationError');
const roleInput = document.getElementById('role');
const registerForm = document.getElementById('registerForm');
const contactInput = document.getElementById('contact');
const phonePickerError = document.getElementById('phonePickerError');

const redirectUrl = <?= json_encode($redirectUrl) ?>;
const registrationSucceeded = <?= $success !== '' ? 'true' : 'false' ?>;

/*
 * Successful registration:
 * show the success message first, then redirect based on the registered role.
 */
if (registrationSucceeded && redirectUrl !== '') {
    registerForm.style.display = 'none';

    const loginArea = document.querySelector('.login-area');
    if (loginArea) {
        loginArea.style.display = 'none';
    }

    setTimeout(function () {
        window.location.replace(redirectUrl);
    }, 2000);
} else {
    initializeRegistrationForm();
}

function initializeRegistrationForm() {
    if (typeof window.intlTelInput !== 'function') {
        phonePickerError.classList.remove('d-none');
        registerForm.querySelector('[type="submit"]').disabled = true;
        return;
    }

    const phoneInput = window.intlTelInput(contactInput, {
        initialCountry: 'ph',
        nationalMode: false,
        formatOnDisplay: false,
        autoPlaceholder: 'off'
    });

    let currentDialCode = phoneInput.getSelectedCountryData().dialCode;

    if (contactInput.value.trim() === '') {
        contactInput.value = '+' + currentDialCode;
    } else {
        phoneInput.setNumber(contactInput.value);
        currentDialCode = phoneInput.getSelectedCountryData().dialCode;
    }

    contactInput.addEventListener('countrychange', function () {
        const nextDialCode = phoneInput.getSelectedCountryData().dialCode;
        const currentDigits = contactInput.value.replace(/\D/g, '');

        const localDigits = currentDigits.startsWith(currentDialCode)
            ? currentDigits.slice(currentDialCode.length)
            : currentDigits;

        contactInput.value = '+' + nextDialCode + localDigits;
        currentDialCode = nextDialCode;
    });

    function validateName(input, required) {
        const name = input.value.trim();

        if (name === '') {
            input.classList.toggle('is-invalid', required);
            return !required;
        }

        const namePattern = /^[\p{L}]+(?:[ '\-][\p{L}]+)*$/u;
        const valid = namePattern.test(name);

        input.classList.toggle('is-invalid', !valid);
        return valid;
    }

    function validateOrganization() {
        const role = roleInput.value;
        const organization = organizationInput.value.trim();

        if (role === 'Organizer') {
            organizationRequired.style.display = 'inline';
            organizationInput.required = true;
            organizationHelp.textContent = 'Required for Organizer accounts.';

            if (organization === '') {
                organizationInput.classList.add('is-invalid');
                organizationError.textContent =
                    'Organization name is required for Organizer accounts.';
                return false;
            }

            const organizationPattern =
                /^[\p{L}\p{N}][\p{L}\p{N} .,'&()\-\/]*$/u;

            if (!organizationPattern.test(organization)) {
                organizationInput.classList.add('is-invalid');
                organizationError.textContent = 'Please enter a valid organization name.';
                return false;
            }

            organizationInput.classList.remove('is-invalid');
            return true;
        }

        organizationRequired.style.display = 'none';
        organizationInput.required = false;

        organizationHelp.textContent = role === 'Audience'
            ? 'Optional for Audience accounts.'
            : 'Select an account type first.';

        organizationInput.classList.remove('is-invalid');
        return true;
    }

    roleInput.addEventListener('change', validateOrganization);

    firstnameInput.addEventListener('input', function () {
        validateName(firstnameInput, true);
    });

    middlenameInput.addEventListener('input', function () {
        validateName(middlenameInput, false);
    });

    lastnameInput.addEventListener('input', function () {
        validateName(lastnameInput, true);
    });

    organizationInput.addEventListener('input', validateOrganization);

    registerForm.addEventListener('submit', function (event) {
        const firstnameValid = validateName(firstnameInput, true);
        const middlenameValid = validateName(middlenameInput, false);
        const lastnameValid = validateName(lastnameInput, true);
        const organizationValid = validateOrganization();

        const phoneDigits = contactInput.value.replace(/\D/g, '');
        const phoneValid = /^\+[1-9]\d{6,14}$/.test('+' + phoneDigits);
        const contactError = document.getElementById('contactError');

        if (phoneValid) {
            contactInput.value = '+' + phoneDigits;
            contactInput.classList.remove('is-invalid');
            contactError.style.display = 'none';
        } else {
            contactInput.classList.add('is-invalid');
            contactError.style.display = 'block';
        }

        if (
            !firstnameValid ||
            !middlenameValid ||
            !lastnameValid ||
            !organizationValid ||
            !phoneValid
        ) {
            event.preventDefault();

            if (!firstnameValid) {
                firstnameInput.focus();
            } else if (!middlenameValid) {
                middlenameInput.focus();
            } else if (!lastnameValid) {
                lastnameInput.focus();
            } else if (!organizationValid) {
                organizationInput.focus();
            } else {
                contactInput.focus();
            }
        }
    });

    validateOrganization();
}
</script>

</body>
</html>
