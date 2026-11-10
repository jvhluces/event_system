<?php

session_start();

require_once '../config/db.php';


if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        "Location: user_profile.php"
    );

    exit();
}

$fullname =
    trim($_POST['fullname'] ?? '');

$email =
    trim($_POST['email'] ?? '');

$contact =
    trim($_POST['contact'] ?? '');

$address =
    trim($_POST['address'] ?? '');

if ($fullname === '') {

    header(
        "Location: user_profile.php?edit=1&error=fullname_required"
    );

    exit();
}


if ($email === '') {

    header(
        "Location: user_profile.php?edit=1&error=email_required"
    );

    exit();
}


if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    header(
        "Location: user_profile.php?edit=1&error=invalid_email"
    );

    exit();
}


/* =========================================================
   GET CURRENT PROFILE PICTURE
========================================================= */

$currentStmt = mysqli_prepare(
    $conn,
    "SELECT
        email,
        profile_pic
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$currentStmt) {

    header(
        "Location: user_profile.php?edit=1&error=database"
    );

    exit();
}

mysqli_stmt_bind_param(
    $currentStmt,
    "i",
    $user_id
);

mysqli_stmt_execute(
    $currentStmt
);

$currentResult =
    mysqli_stmt_get_result(
        $currentStmt
    );

$currentUser =
    mysqli_fetch_assoc(
        $currentResult
    );

mysqli_stmt_close(
    $currentStmt
);


if (!$currentUser) {

    header(
        "Location: user_profile.php?edit=1&error=database"
    );

    exit();
}

$currentEmail =
    $currentUser['email'] ?? '';

$currentProfilePic =
    $currentUser['profile_pic'] ?? '';


/* =========================================================
   CHECK EMAIL DUPLICATE
========================================================= */

$emailStmt = mysqli_prepare(
    $conn,
    "SELECT user_id
     FROM users
     WHERE email = ?
     AND user_id != ?
     LIMIT 1"
);

if (!$emailStmt) {

    header(
        "Location: user_profile.php?edit=1&error=database"
    );

    exit();
}

mysqli_stmt_bind_param(
    $emailStmt,
    "si",
    $email,
    $user_id
);

mysqli_stmt_execute(
    $emailStmt
);

$emailResult =
    mysqli_stmt_get_result(
        $emailStmt
    );

$emailExists =
    $emailResult &&
    mysqli_num_rows($emailResult) > 0;

mysqli_stmt_close(
    $emailStmt
);


if ($emailExists) {

    header(
        "Location: user_profile.php?edit=1&error=email_exists"
    );

    exit();
}


/* =========================================================
   DEFAULT:
   KEEP CURRENT PROFILE PICTURE
========================================================= */

$newProfilePic =
    $currentProfilePic;

$newUploadedFile =
    null;

$uploadDir =
    __DIR__ .
    '/../uploads/profiles/';


/* =========================================================
   CREATE UPLOAD DIRECTORY
========================================================= */

if (!is_dir($uploadDir)) {

    if (
        !mkdir(
            $uploadDir,
            0755,
            true
        )
    ) {

        header(
            "Location: user_profile.php?edit=1&error=upload_folder"
        );

        exit();
    }
}


/* =========================================================
   PROFILE PICTURE UPLOAD
========================================================= */

if (
    isset($_FILES['profile_pic']) &&
    $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE
) {

    $file =
        $_FILES['profile_pic'];


    /* ---------------------------------------------
       UPLOAD ERROR
    --------------------------------------------- */

    if (
        $file['error'] !==
        UPLOAD_ERR_OK
    ) {

        header(
            "Location: user_profile.php?edit=1&error=upload_failed"
        );

        exit();
    }


    /* ---------------------------------------------
       FILE SIZE
    --------------------------------------------- */

    if (
        $file['size'] >
        5 * 1024 * 1024
    ) {

        header(
            "Location: user_profile.php?edit=1&error=file_too_large"
        );

        exit();
    }


    /* ---------------------------------------------
       CHECK IMAGE
    --------------------------------------------- */

    $imageInfo =
        @getimagesize(
            $file['tmp_name']
        );


    if ($imageInfo === false) {

        header(
            "Location: user_profile.php?edit=1&error=invalid_image"
        );

        exit();
    }


    /* ---------------------------------------------
       ALLOWED MIME TYPES
    --------------------------------------------- */

    $allowedMimeTypes = [

        'image/jpeg' =>
            'jpg',

        'image/png' =>
            'png',

        'image/gif' =>
            'gif',

        'image/webp' =>
            'webp'

    ];


    $mimeType =
        $imageInfo['mime']
        ?? '';


    if (
        !isset(
            $allowedMimeTypes[
                $mimeType
            ]
        )
    ) {

        header(
            "Location: user_profile.php?edit=1&error=invalid_image_type"
        );

        exit();
    }


    $extension =
        $allowedMimeTypes[
            $mimeType
        ];



    try {

        $randomPart =
            bin2hex(
                random_bytes(12)
            );

    } catch (Exception $e) {

        $randomPart =
            uniqid(
                '',
                true
            );
    }


    $newFileName =
        'profile_' .
        $user_id .
        '_' .
        $randomPart .
        '.' .
        $extension;


    $destination =
        $uploadDir .
        $newFileName;


    /* ---------------------------------------------
       MOVE FILE
    --------------------------------------------- */

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {

        header(
            "Location: user_profile.php?edit=1&error=upload_failed"
        );

        exit();
    }


    /*
        Database stores:

        uploads/profiles/profile_1_xxxxx.jpg

        NOT:

        ../uploads/...

        NOT:

        users/uploads/...
    */

    $newProfilePic =
        'uploads/profiles/' .
        $newFileName;


    /*
        Remember the newly uploaded file.

        We will delete it if the
        database update fails.
    */

    $newUploadedFile =
        $destination;
}


/* =========================================================
   UPDATE DATABASE
========================================================= */

$update = mysqli_prepare(
    $conn,
    "UPDATE users
     SET
        fullname = ?,
        email = ?,
        contact = ?,
        address = ?,
        profile_pic = ?
     WHERE user_id = ?"
);


if (!$update) {

    /*
        DB prepare failed.
        Delete newly uploaded file
        because it is not saved in DB.
    */

    if (
        $newUploadedFile &&
        is_file($newUploadedFile)
    ) {

        @unlink(
            $newUploadedFile
        );
    }


    header(
        "Location: user_profile.php?edit=1&error=database"
    );

    exit();
}


mysqli_stmt_bind_param(
    $update,
    "sssssi",
    $fullname,
    $email,
    $contact,
    $address,
    $newProfilePic,
    $user_id
);


$success =
    mysqli_stmt_execute(
        $update
    );


mysqli_stmt_close(
    $update
);


/* =========================================================
   DATABASE UPDATE FAILED
========================================================= */

if (!$success) {

    /*
        Delete new uploaded file
        because DB update failed.
    */

    if (
        $newUploadedFile &&
        is_file($newUploadedFile)
    ) {

        @unlink(
            $newUploadedFile
        );
    }


    header(
        "Location: user_profile.php?edit=1&error=update_failed"
    );

    exit();
}


/* =========================================================
   DELETE OLD PROFILE PICTURE
   ONLY AFTER DB SUCCESS
========================================================= */

if (
    $newUploadedFile &&
    !empty($currentProfilePic)
) {

    /*
        Ignore external URLs.
    */

    if (
        !preg_match(
            '/^https?:\/\//i',
            $currentProfilePic
        )
    ) {

        $oldPath =
            str_replace(
                '\\',
                '/',
                $currentProfilePic
            );

        $oldPath =
            ltrim(
                $oldPath,
                '/'
            );


        /*
            Remove ../ if an old version
            of the database stored it.
        */

        while (
            strpos(
                $oldPath,
                '../'
            ) === 0
        ) {

            $oldPath =
                substr(
                    $oldPath,
                    3
                );
        }


        /*
            Only allow deletion
            from uploads/profiles/
        */

        if (
            strpos(
                $oldPath,
                'uploads/profiles/'
            ) === 0
        ) {

            $oldFile =
                __DIR__ .
                '/../' .
                $oldPath;


            /*
                Security check:
                old file must actually
                be inside uploads/profiles.
            */

            $realOldFile =
                realpath(
                    $oldFile
                );

            $realUploadDir =
                realpath(
                    $uploadDir
                );


            if (
                $realOldFile !== false &&
                $realUploadDir !== false &&
                strpos(
                    $realOldFile,
                    $realUploadDir . DIRECTORY_SEPARATOR
                ) === 0 &&
                is_file($realOldFile)
            ) {

                @unlink(
                    $realOldFile
                );

            }

        }

    }

}


/* =========================================================
   SUCCESS
========================================================= */

header(
    "Location: user_profile.php?success=updated"
);

exit();

?>