<?php
session_start();

$error = null;
$success = null;

function getUsersFilePath() {
    $dataDir = sys_get_temp_dir() . '/quiz-game';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    if (is_dir($dataDir) && is_writable($dataDir)) {
        return $dataDir . '/users.txt';
    }
    return __DIR__ . '/users.txt';
}

function getUsers() {
    $users = [];
    $usersFile = getUsersFilePath();
    if (!is_readable($usersFile)) {
        return $users;
    }
    $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $users[$parts[0]] = $parts[1];
        }
    }
    return $users;
}

function addUser($username, $password) {
    $record = $username . ':' . $password . PHP_EOL;
    $usersFile = getUsersFilePath();
    $file = fopen($usersFile, 'c+');
    if ($file === false) {
        return false;
    }
    $locked = flock($file, LOCK_EX);
    if ($locked) {
        fseek($file, 0, SEEK_END);
        fwrite($file, $record);
        flock($file, LOCK_UN);
    }
    fclose($file);
    return $locked;
}

if (isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($username === '' || $password === '' || $confirm_password === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $users = getUsers();
        if (isset($users[$username])) {
            $error = 'That username is already taken.';
        } else {
            if (addUser($username, $password)) {
                $success = 'Account created successfully. You can now log in.';
            } else {
                $error = 'Unable to create account. Please try again.';
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
    <title>Register</title>
    <link rel="stylesheet" href="st.css">
</head>
<body>
    <header class="quiz-header">
        <h1>Create Your Account</h1>
        <p>Join the quiz and start testing your knowledge!</p>
    </header>

    <div class="container login-container login-container--narrow">
        <h2>Register</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="register" class="btn">Create Account</button>
        </form>
        <p class="register-note">
            Already have an account? <a href="index.php">Log in.</a>
        </p>
    </div>
</body>
</html>
