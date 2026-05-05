<?php
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        redirect('login.php');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        setFlash('error', 'Por favor complete todos los campos');
        redirect('login.php');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        
        setFlash('success', 'Bienvenido, ' . $user['username']);
        redirect('dashboard.php');
    } else {
        setFlash('error', 'Usuario o contraseña incorrectos');
        redirect('login.php');
    }
}

redirect('../login.php');
