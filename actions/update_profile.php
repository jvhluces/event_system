<?php

session_start();

require_once '../config/db.php';

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] != 'Organizer'
) {
    header("Location: login_user.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

$uploadDir = __DIR__ . '/uploads/profiles/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$userQuery = mysqli_query(
    $conn,
    "SELECT *
     FROM users
     WHERE user_id = $user_id
     LIMIT 1"
);

if (!$userQuery || mysqli_num_rows($userQuery) == 0) {
    session_destroy();
    header("Location: login_user.php");
    exit();
}

$user = mysqli_fetch_assoc($userQuery);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstname = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $fullname = trim(
        $firstname . ' ' .
        ($middlename !== '' ? $middlename . ' ' : '') .
        $lastname
    );
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');

    $errors = [];
    $namePattern = "/^[\p{L}]+(?:[ '\-][\p{L}]+)*$/u";


    if ($firstname === '') {
        $errors[] = "First name is required.";
    } elseif (!preg_match($namePattern, $firstname)) {
        $errors[] = "First name must contain letters only.";
    }

    if ($middlename !== '' && !preg_match($namePattern, $middlename)) {
        $errors[] = "Middle name must contain letters only.";
    }

    if ($lastname === '') {
        $errors[] = "Last name is required.";
    } elseif (!preg_match($namePattern, $lastname)) {
        $errors[] = "Last name must contain letters only.";
    }

    if ($email === '') {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }


    if (empty($errors)) {

        $emailSafe = mysqli_real_escape_string(
            $conn,
            $email
        );

        $emailCheck = mysqli_query(
            $conn,
            "SELECT user_id
             FROM users
             WHERE email = '$emailSafe'
             AND user_id != $user_id
             LIMIT 1"
        );

        if ($emailCheck && mysqli_num_rows($emailCheck) > 0) {
            $errors[] = "That email is already being used.";
        }
    }
    $profilePic = $user['profile_pic'] ?? '';

    if (
        isset($_FILES['profile_pic']) &&
        $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {

            $errors[] = "There was a problem uploading the image.";

        } else {

            $file = $_FILES['profile_pic'];

            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'gif',
                'webp'
            ];

            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );

            if (!in_array(
                $extension,
                $allowedExtensions,
                true
            )) {

                $errors[] =
                    "Only JPG, JPEG, PNG, GIF, and WEBP images are allowed.";

            } elseif ($file['size'] > 5 * 1024 * 1024) {

                $errors[] =
                    "Profile image must not exceed 5MB.";

            } else {

                $imageInfo = @getimagesize(
                    $file['tmp_name']
                );

                if ($imageInfo === false) {

                    $errors[] =
                        "The uploaded file is not a valid image.";

                } else {

                    $newFileName =
                        'profile_' .
                        $user_id .
                        '_' .
                        time() .
                        '_' .
                        bin2hex(random_bytes(4)) .
                        '.' .
                        $extension;

                    $destination =
                        $uploadDir .
                        $newFileName;

                    if (
                        move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )
                    ) {

                        if (
                            !empty($profilePic) &&
                            strpos(
                                $profilePic,
                                'uploads/profiles/'
                            ) === 0
                        ) {

                            $oldFile =
                                __DIR__ .
                                '/' .
                                $profilePic;

                            if (
                                file_exists($oldFile)
                            ) {
                                @unlink($oldFile);
                            }
                        }


                        $profilePic =
                            'uploads/profiles/' .
                            $newFileName;

                    } else {

                        $errors[] =
                            "Unable to save the uploaded image.";
                    }
                }
            }
        }
    }


    if (empty($errors)) {

        $fullnameSafe =
            mysqli_real_escape_string(
                $conn,
                $fullname
            );

        $firstnameSafe = mysqli_real_escape_string($conn, $firstname);
        $middlenameSafe = mysqli_real_escape_string($conn, $middlename);
        $lastnameSafe = mysqli_real_escape_string($conn, $lastname);

        $emailSafe =
            mysqli_real_escape_string(
                $conn,
                $email
            );

        $contactSafe =
            mysqli_real_escape_string(
                $conn,
                $contact
            );

        $profileSafe =
            mysqli_real_escape_string(
                $conn,
                $profilePic
            );


        $sql = "
            UPDATE users
            SET
                firstname = '$firstnameSafe',
                middlename = '$middlenameSafe',
                lastname = '$lastnameSafe',
                fullname = '$fullnameSafe',
                email = '$emailSafe',
                contact = '$contactSafe',
                profile_pic = '$profileSafe'
            WHERE user_id = $user_id
        ";


        if (mysqli_query($conn, $sql)) {

            $_SESSION['fullname'] = $fullname;

            header(
                "Location: ../organizer/org_dash.php?profile_updated=1"
            );

            exit();

        } else {

            $errors[] =
                "Database error: " .
                mysqli_error($conn);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Edit Profile - EventMS</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
>

<link rel="stylesheet" href="../assets/css/actions/update_profile.css">

</head>

<body>

<div class="profile-wrapper">

    <div class="profile-card">

        <div class="text-center mb-4">

            <?php if (!empty($user['profile_pic']) &&
                      file_exists(__DIR__ . '/' . $user['profile_pic'])): ?>

                <img
                    src="<?= htmlspecialchars($user['profile_pic']); ?>"
                    class="profile-preview"
                    id="previewImage"
                    alt="Profile"
                >

            <?php else: ?>

                <div
                    class="default-avatar"
                    id="defaultAvatar"
                >
                    <i class="bi bi-person-fill"></i>
                </div>

                <img
                    id="previewImage"
                    class="profile-preview d-none"
                    alt="Profile Preview"
                >

            <?php endif; ?>

        </div>


        <h2 class="text-center mb-4">
            Edit Profile
        </h2>


        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <ul class="mb-0">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error); ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <div class="mb-4">

                <label class="form-label fw-bold">

                    <i class="bi bi-image"></i>

                    Profile Picture

                </label>

                <input
                    type="file"
                    name="profile_pic"
                    id="profilePic"
                    class="form-control"
                    accept="image/*"
                >

                <small class="text-muted">
                    JPG, JPEG, PNG, GIF, or WEBP — maximum 5MB.
                </small>

            </div>

            <div class="row">

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">First Name</label>
                    <input
                        type="text"
                        name="firstname"
                        class="form-control"
                        value="<?= htmlspecialchars($user['firstname'] ?? ''); ?>"
                        autocomplete="given-name"
                        required
                    >
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Middle Name</label>
                    <input
                        type="text"
                        name="middlename"
                        class="form-control"
                        value="<?= htmlspecialchars($user['middlename'] ?? ''); ?>"
                        autocomplete="additional-name"
                    >
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Last Name</label>
                    <input
                        type="text"
                        name="lastname"
                        class="form-control"
                        value="<?= htmlspecialchars($user['lastname'] ?? ''); ?>"
                        autocomplete="family-name"
                        required
                    >
                </div>

            </div>

            <div class="mb-3">

                <label class="form-label fw-bold">
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $user['email'] ?? ''
                    ); ?>"
                    required
                >

            </div>


            <div class="mb-4">

                <label class="form-label fw-bold">
                    Contact Number
                </label>

                <input
                    type="text"
                    name="contact_number"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $user['contact_number'] ?? ''
                    ); ?>"
                >

            </div>


            <div class="d-flex gap-2">

                <a
                    href="../organizer/org_dash.php#profile"
                    class="btn btn-secondary w-50"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-primary w-50"
                >
                    <i class="bi bi-check-circle"></i>
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<script>

const profilePic =
    document.getElementById("profilePic");

const previewImage =
    document.getElementById("previewImage");

const defaultAvatar =
    document.getElementById("defaultAvatar");


profilePic.addEventListener(
    "change",
    function() {

        const file =
            this.files[0];

        if (!file) {
            return;
        }

        const reader =
            new FileReader();

        reader.onload =
            function(e) {

                previewImage.src =
                    e.target.result;

                previewImage.classList.remove(
                    "d-none"
                );

                if (defaultAvatar) {

                    defaultAvatar.style.display =
                        "none";

                }

            };

        reader.readAsDataURL(file);

    }
);

</script>

</body>

</html>