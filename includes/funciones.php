<?php

function registrarAsistencia(PDO $pdo, string $documento, string $pin): array
{
    try {
        $stmt = $pdo->prepare("SELECT documento FROM user_ WHERE documento = ? AND pin = ? AND estado = 1 LIMIT 1");
        $stmt->execute([$documento, $pin]);
        if (!$stmt->fetch()) {
            return ['ok' => false, 'tipo' => 'error', 'mensaje' => '❌ Documento o PIN incorrecto.'];
        }

        $stmt = $pdo->prepare("SELECT id_asistencia, fecha_hora_ent FROM asistencias WHERE documento = ? AND DATE(fecha_hora_ent) = CURDATE() AND fecha_hora_sal IS NULL LIMIT 1");
        $stmt->execute([$documento]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            $stmt = $pdo->prepare("INSERT INTO asistencias (documento, fecha_hora_ent) VALUES (?, NOW())");
            $stmt->execute([$documento]);
            return ['ok' => true, 'tipo' => 'entrada', 'mensaje' => '✅ Entrada registrada a las ' . date('H:i:s')];
        }

        $stmt = $pdo->prepare("SELECT id_asistencia FROM asistencias WHERE documento = ? AND DATE(fecha_hora_ent) = CURDATE() AND fecha_hora_sal IS NOT NULL LIMIT 1");
        $stmt->execute([$documento]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'tipo' => 'ya_salio', 'mensaje' => 'ℹ️ Ya registraste entrada y salida el día de hoy.'];
        }

        $stmt = $pdo->prepare("UPDATE asistencias SET fecha_hora_sal = NOW(), cantidad_horas = ROUND(TIMESTAMPDIFF(MINUTE, fecha_hora_ent, NOW()) / 60, 2) WHERE id_asistencia = ?");
        $stmt->execute([$registro['id_asistencia']]);

        $stmt = $pdo->prepare("SELECT cantidad_horas FROM asistencias WHERE id_asistencia = ?");
        $stmt->execute([$registro['id_asistencia']]);
        $horas = $stmt->fetchColumn();

        return ['ok' => true, 'tipo' => 'salida', 'mensaje' => "✅ Salida registrada. Horas trabajadas hoy: {$horas} h"];

    } catch (PDOException $e) {
        error_log('Error registrarAsistencia: ' . $e->getMessage());
        return ['ok' => false, 'tipo' => 'error', 'mensaje' => '❌ Error interno al registrar asistencia.'];
    }
}

function calcularHorasTrabajadas(string $entrada, string $salida): float
{
    $dt1 = new DateTime($entrada);
    $dt2 = new DateTime($salida);
    $diff = $dt1->diff($dt2);
    $minutos = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
    return round($minutos / 60, 2);
}

function formatearHoras(float $horas): string
{
    $h   = (int) $horas;
    $min = (int) round(($horas - $h) * 60);
    return "{$h} h {$min} min";
}

function verificarEmpleado(PDO $pdo, string $documento): array|false
{
    $stmt = $pdo->prepare("SELECT u.documento, u.nombre_completo, t.nombre_tipo, a.nombre_area FROM user_ u LEFT JOIN tipo_usuario t ON u.id_tipo = t.id_tipo LEFT JOIN area a ON u.id_area = a.id_area WHERE u.documento = ? AND u.estado = 1 LIMIT 1");
    $stmt->execute([$documento]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function validarDocumento(string $documento): bool
{
    return (bool) preg_match('/^\d{6,12}$/', $documento);
}

function limpiar(string $valor): string
{
    return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
}

function obtenerReporteAsistencias(PDO $pdo, ?string $fechaInicio = null, ?string $fechaFin = null, ?string $documento = null): array
{
    $sql = "SELECT a.id_asistencia, u.documento, u.nombre_completo, ar.nombre_area,
                   a.fecha_hora_ent, a.fecha_hora_sal, a.cantidad_horas
            FROM asistencias a
            INNER JOIN user_ u ON a.documento = u.documento
            LEFT JOIN area ar ON u.id_area = ar.id_area
            WHERE 1=1";
    $params = [];

    if ($fechaInicio) { $sql .= " AND DATE(a.fecha_hora_ent) >= ?"; $params[] = $fechaInicio; }
    if ($fechaFin)    { $sql .= " AND DATE(a.fecha_hora_sal) <= ?"; $params[] = $fechaFin; }
    if ($documento)   { $sql .= " AND u.documento = ?";             $params[] = $documento; }

    $sql .= " ORDER BY a.fecha_hora_ent DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function totalHorasReporte(array $asistencias): float
{
    return round(array_sum(array_column($asistencias, 'cantidad_horas')), 2);
}

function exportarReporteCsv(array $asistencias): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Documento', 'Nombre', 'Area', 'Entrada', 'Salida', 'Horas']);
    foreach ($asistencias as $f) {
        fputcsv($out, [
            $f['id_asistencia'], $f['documento'], $f['nombre_completo'],
            $f['nombre_area'] ?? 'Sin area', $f['fecha_hora_ent'],
            $f['fecha_hora_sal'] ?? '-', number_format((float) $f['cantidad_horas'], 2),
        ]);
    }
    fclose($out);
}

function crearEmpleado(PDO $pdo, array $datos): array
{
    $stmt = $pdo->prepare("SELECT documento FROM user_ WHERE documento = ?");
    $stmt->execute([$datos['documento']]);
    if ($stmt->fetch()) return ['ok' => false, 'mensaje' => 'Ya existe un empleado con ese documento.'];

    try {
        $stmt = $pdo->prepare("INSERT INTO user_ (documento, nombre_completo, pin, password, id_tipo, id_area, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$datos['documento'], $datos['nombre_completo'], $datos['pin'], $datos['password'], $datos['id_tipo'], $datos['id_area'], $datos['estado']]);
        return ['ok' => true, 'mensaje' => 'Empleado creado correctamente.'];
    } catch (PDOException $e) {
        error_log('crearEmpleado: ' . $e->getMessage());
        return ['ok' => false, 'mensaje' => 'Error al crear el empleado.'];
    }
}

function editarEmpleado(PDO $pdo, string $documento, string $nombre, int $id_tipo, int $id_area, int $estado): array
{
    try {
        $stmt = $pdo->prepare("UPDATE user_ SET nombre_completo = ?, id_tipo = ?, id_area = ?, estado = ? WHERE documento = ?");
        $stmt->execute([$nombre, $id_tipo, $id_area, $estado, $documento]);
        return ['ok' => true, 'mensaje' => 'Empleado actualizado correctamente.'];
    } catch (PDOException $e) {
        error_log('editarEmpleado: ' . $e->getMessage());
        return ['ok' => false, 'mensaje' => 'Error al actualizar el empleado.'];
    }
}

function buscarEmpleadoPorDocumento(PDO $pdo, string $documento): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT u.documento, u.nombre_completo, u.id_tipo, u.id_area, t.nombre_tipo, a.nombre_area, u.estado FROM user_ u LEFT JOIN tipo_usuario t ON u.id_tipo = t.id_tipo LEFT JOIN area a ON u.id_area = a.id_area WHERE u.documento = ?");
        $stmt->execute([$documento]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log('buscarEmpleadoPorDocumento: ' . $e->getMessage());
        return null;
    }
}

function obtenertodoslostipos(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT * FROM tipo_usuario ORDER BY id_tipo ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenertodaslasareas(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT * FROM area ORDER BY id_area ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerEmpleados(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT u.documento, u.nombre_completo, u.fecha_creacion, u.estado, u.id_tipo, u.id_area, t.nombre_tipo, a.nombre_area FROM user_ u LEFT JOIN tipo_usuario t ON u.id_tipo = t.id_tipo LEFT JOIN area a ON u.id_area = a.id_area ORDER BY u.nombre_completo ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function eliminarEmpleado(PDO $pdo, string $documento): array
{
    try {
        $stmt = $pdo->prepare("DELETE FROM user_ WHERE documento = ?");
        $stmt->execute([$documento]);
        return ['ok' => true, 'mensaje' => 'Empleado eliminado correctamente.'];
    } catch (PDOException $e) {
        error_log('eliminarEmpleado: ' . $e->getMessage());
        return ['ok' => false, 'mensaje' => 'Error al eliminar el empleado.'];
    }
}