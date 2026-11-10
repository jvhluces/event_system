<?php

session_start();
include 'db.php';

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: login_user.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

$userQuery = mysqli_query(
    $conn,
    "SELECT fullname, email, contact, address, username, profile_pic
     FROM users
     WHERE user_id = $user_id
     LIMIT 1"
);

if (!$userQuery || mysqli_num_rows($userQuery) === 0) {
    die("User account not found.");
}

$user = mysqli_fetch_assoc($userQuery);

$fullname   = $user['fullname'] ?? '';
$email      = $user['email'] ?? '';
$contact    = $user['contact'] ?? '';
$address    = $user['address'] ?? '';
$username   = $user['username'] ?? '';
$profilePic = $user['profile_pic'] ?? '';

if (!empty($profilePic)) {

    $avatar = ltrim($profilePic, '/');

} else {

    $avatar =
        'https://ui-avatars.com/api/?name=' .
        urlencode($fullname ?: 'User') .
        '&background=0d6efd&color=fff';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile - EventMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<link rel="stylesheet" href="../assets/css/actions/edit_user_profile.css">

</head>

<body>


<div class="edit-page">

    <div class="edit-card">
        <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" class="edit-profile-image" id="profilePreview" alt="Profile Picture"  onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullname ?: 'User'); ?>&background=0d6efd&color=fff';">
        <h1 class="edit-title">
            Edit Profile
        </h1>
        <form action="save_user_profile.php" method="POST" enctype="multipart/form-data">

            <div class="mb-4">

                <label class="form-label">

                    <i class="bi bi-image"></i>

                    Profile Picture

                </label>

                <input
                    type="file"
                    name="profile_pic"
                    id="profilePic"
                    class="form-control"
                    accept=".jpg,.jpeg,.png,.gif,.webp"
                >

                <div class="form-text">

                    JPG, JPEG, PNG, GIF, or WEBP — maximum 5MB.

                </div>

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Full Name

                </label>

                <input
                    type="text"
                    name="fullname"
                    class="form-control"
                    value="<?= htmlspecialchars($fullname); ?>"
                    required
                >

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Email

                </label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    value="<?= htmlspecialchars($email); ?>"
                    required
                >

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Contact Number

                </label>

                <input
                    type="text"
                    name="contact"
                    class="form-control"
                    value="<?= htmlspecialchars($contact); ?>"
                >

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Address

                </label>

                <textarea
                    name="address"
                    class="form-control"
                    rows="3"
                ><?= htmlspecialchars($address); ?></textarea>

            </div>

            <div class="form-buttons">

                <a
                    href="user_profile.php"
                    class="btn btn-secondary cancel-btn"
                >

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn btn-primary save-btn"
                >

                    <i class="bi bi-check-circle"></i>

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>


<script>

const profileInput =
    document.getElementById('profilePic');

const profilePreview =
    document.getElementById('profilePreview');


profileInput.addEventListener(
    'change',
    function () {

        const file = this.files[0];

        if (!file) {
            return;
        }


        /* CHECK SIZE */

        if (file.size > 5 * 1024 * 1024) {

            alert(
                'Image is too large. Maximum size is 5MB.'
            );

            this.value = '';

            return;
        }


        const allowed = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!allowed.includes(file.type)) {

            alert(
                'Please select JPG, JPEG, PNG, GIF, or WEBP.'
            );

            this.value = '';

            return;
        }
        const reader = new FileReader();

        reader.onload = function (e) {

            profilePreview.src =
                e.target.result;

        };

        reader.readAsDataURL(file);

    }
);

</script>

</body>
</html>