<?php
require_once 'config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($username)) {
        $errors[] = 'A felhasználónév megadása kötelező.';
    } elseif (strpos($username, ' ') !== false) {
        $errors[] = 'A felhasználónévben nem lehet szóköz.';
    }

    if (empty($email)) {
        $errors[] = 'Az e-mail cím megadása kötelező.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Az e-mail cím formátuma helytelen.';
    }

    if (empty($password)) {
        $errors[] = 'A jelszó megadása kötelező.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'A jelszónak legalább 8 karakter hosszúnak kell lennie.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'A jelszónak tartalmaznia kell kisbetűt.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'A jelszónak tartalmaznia kell nagybetűt.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'A jelszónak tartalmaznia kell számjegyet.';
    }

    if ($password !== $password2) {
        $errors[] = 'A két jelszó nem egyezik meg.';
    }

    $users = loadJSON(USERS_FILE);
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            $errors[] = 'Ez a felhasználónév már foglalt.';
            break;
        }
    }

    if (empty($errors)) {
        $newUser = [
            'id' => count($users) > 0 ? max(array_column($users, 'id')) + 1 : 1,
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'is_admin' => false
        ];
        $users[] = $newUser;
        saveJSON(USERS_FILE, $users);

        $success = 'Sikeres regisztráció! Most már bejelentkezhetsz.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Minden mező kitöltése kötelező.';
    } else {
        $users = loadJSON(USERS_FILE);
        $found = false;

        foreach ($users as $user) {
            if ($user['username'] === $username) {
                $found = true;
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    header('Location: index.php');
                    exit;
                } else {
                    $errors[] = 'Hibás jelszó.';
                }
                break;
            }
        }

        if (!$found) {
            $errors[] = 'Nem létezik ilyen felhasználó.';
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés / Regisztráció - Budapesti Közösségi Költségvetés</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Budapesti Közösségi Költségvetés</h1>
            <nav>
                <a href="index.php">Főoldal</a>
                <?php if (isLoggedIn()): ?>
                    <a href="projects-own.php">Saját projektek</a>
                    <a href="submit-project.php">Projekt beküldése</a>
                    <?php if (isAdmin()): ?>
                        <a href="projects-admin.php">Admin</a>
                        <a href="statistics.php">Statisztikák</a>
                    <?php endif; ?>
                    <a href="auth.php?logout=1">Kijelentkezés</a>
                <?php else: ?>
                    <a href="auth.php" class="active">Bejelentkezés</a>
                <?php endif; ?>
            </nav>
        </header>

        <main>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= e($success) ?>
                </div>
            <?php endif; ?>

            <div class="auth-container">
                <div class="auth-box">
                    <h2>Bejelentkezés</h2>
                    <form method="POST" action="auth.php">
                        <div class="form-group">
                            <label for="login-username">Felhasználónév</label>
                            <input type="text" id="login-username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="login-password">Jelszó</label>
                            <input type="password" id="login-password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary">Bejelentkezés</button>
                    </form>
                </div>

                <div class="auth-box">
                    <h2>Regisztráció</h2>
                    <form method="POST" action="auth.php">
                        <div class="form-group">
                            <label for="reg-username">Felhasználónév</label>
                            <input type="text" id="reg-username" name="username" required>
                            <small>Nem tartalmazhat szóközt</small>
                        </div>
                        <div class="form-group">
                            <label for="reg-email">E-mail cím</label>
                            <input type="email" id="reg-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="reg-password">Jelszó</label>
                            <input type="password" id="reg-password" name="password" required>
                            <small>Min. 8 karakter, tartalmaz kisbetűt, nagybetűt és számot</small>
                        </div>
                        <div class="form-group">
                            <label for="reg-password2">Jelszó megerősítése</label>
                            <input type="password" id="reg-password2" name="password2" required>
                        </div>
                        <button type="submit" name="register" class="btn btn-primary">Regisztráció</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
