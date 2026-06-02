<?php
session_start();

// 🔗 Conexión y funciones
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funciones.php';

// ️ Conexión a BD
$db = new Database();
$pdo = $db->conectar();
if (!$pdo) {
    die('<div class="alert alert-danger text-center mt-5"> Error de conexión a la base de datos</div>');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Control de Asistencia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


<div class="container-fluid mt-3">
        <div class="text-end">
            <a href="admin/login.php" class="btn btn-primary">
                Iniciar Sesión Admin
            </a>
        </div>
</div>

<div class="row">
        <div class="text-center">
            <h1 class="fw-bold">
                Bienvenido al control de asistencia
            </h1>
        </div>
</div>

<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-5">

            <div class="card shadow">

                <div class="card-header text-center">
                    <h3>Registro de Asistencia</h3>
                </div>

                <div class="card-body">

                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label">
                                Documento
                            </label>

                            <input
                                type="number"
                                name="documento"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                PIN
                            </label>

                            <input
                                type="password"
                                name="pin"
                                class="form-control"
                                required>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-success w-100">
                            Registrar Asistencia
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>