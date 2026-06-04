<?php
if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la terminal.\n");
}

require_once __DIR__ . '/../connection/connect.php';

// validar parámetros
if ($argc < 5) {
    echo "Uso: php crear_admin.php <email> <password> <id_tip_user>\n";
    exit(1);
}

$doc = $argv[1];
$nombre = $argv[2];
$email = $argv[3];
$password = $argv[4];
$id_tipo = $argv[5];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Email inválido.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "La contraseña debe tener mínimo 8 caracteres.\n";
    exit(1);
}

try {
    $db = new Database();
    $pdo = $db->conectar();
    //Verificar si ya existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        echo "El email ya está registrado.\n";
        exit(1);
    }
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $insert = $pdo->prepare("INSERT INTO user (documento, nombre, email, password, id_tip_user) VALUES (?,?,?,?,?)");
    $insert->execute([$doc, $nombre, $email, $hash, $id_tipo]);

    echo "Administrador creado exitosamente.\n";
    echo "Email: $email | ID Tipo: $id_tipo\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}