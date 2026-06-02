<?php
/**
 * funciones.php
 * Funciones reutilizables del sistema:
 *  - Registro de asistencia (entrada / salida)
 *  - Cálculo de horas trabajadas
 *  - Validaciones de empleados
 *  - Reporte de asistencias
 * 
 * Uso: require_once __DIR__ . '/../includes/funciones.php';
 */


// ══════════════════════════════════════════════════
//  ASISTENCIA
// ══════════════════════════════════════════════════

/**
 * Registra entrada o salida de un empleado según el estado del día.
 * - Si no hay registro de hoy → INSERT (entrada).
 * - Si existe registro sin salida → UPDATE (salida + horas trabajadas).
 *
 * @param PDO    $pdo        Conexión activa
 * @param string $documento  Documento del empleado
 * @return array ['ok' => bool, 'mensaje' => string, 'tipo' => 'entrada'|'salida'|'ya_salio']
 */
function registrarAsistencia(PDO $pdo, string $documento): array
{
    try {
        // Buscar registro abierto de HOY para este empleado
        $sql = "SELECT a.id_asistencia, a.fecha_entrada
                FROM asistencias a
                INNER JOIN user u ON a.id_empleado = u.documento
                WHERE u.documento = ?
                  AND DATE(a.fecha_entrada) = CURDATE()
                  AND a.fecha_salida IS NULL
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$documento]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            // ── ENTRADA ──
            $sql = "INSERT INTO asistencias (id_empleado, fecha_entrada)
                    VALUES (?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$documento]);

            return [
                'ok'      => true,
                'tipo'    => 'entrada',
                'mensaje' => '✅ Entrada registrada a las ' . date('H:i:s')
            ];
        }

        // Verificar si ya marcó salida hoy
        $sqlSalida = "SELECT id_asistencia FROM asistencias
                      WHERE id_empleado = ?
                        AND DATE(fecha_entrada) = CURDATE()
                        AND fecha_salida IS NOT NULL
                      LIMIT 1";
        $stmtS = $pdo->prepare($sqlSalida);
        $stmtS->execute([$documento]);
        if ($stmtS->fetch()) {
            return [
                'ok'      => false,
                'tipo'    => 'ya_salio',
                'mensaje' => 'ℹ️ Ya registraste entrada y salida el día de hoy.'
            ];
        }

        // ── SALIDA ──
        $sql = "UPDATE asistencias
                SET fecha_salida      = NOW(),
                    horas_trabajadas  = ROUND(TIMESTAMPDIFF(MINUTE, fecha_entrada, NOW()) / 60, 2)
                WHERE id_asistencia = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$registro['id_asistencia']]);

        // Obtener las horas calculadas para el mensaje
        $sqlHoras = "SELECT horas_trabajadas FROM asistencias WHERE id_asistencia = ?";
        $stmtH = $pdo->prepare($sqlHoras);
        $stmtH->execute([$registro['id_asistencia']]);
        $horas = $stmtH->fetchColumn();

        return [
            'ok'      => true,
            'tipo'    => 'salida',
            'mensaje' => "✅ Salida registrada. Horas trabajadas hoy: {$horas} h"
        ];

    } catch (PDOException $e) {
        error_log('Error registrarAsistencia: ' . $e->getMessage());
        return [
            'ok'      => false,
            'tipo'    => 'error',
            'mensaje' => '❌ Error interno al registrar asistencia.'
        ];
    }
}

/**
 * Calcula horas trabajadas entre dos fechas/horas (sin tocar la BD).
 * Útil para mostrar totales en reportes.
 *
 * @param string $entrada  Formato: 'Y-m-d H:i:s'
 * @param string $salida   Formato: 'Y-m-d H:i:s'
 * @return float  Horas con 2 decimales (ej: 7.5)
 */
function calcularHorasTrabajadas(string $entrada, string $salida): float
{
    $dt1 = new DateTime($entrada);
    $dt2 = new DateTime($salida);
    $diff = $dt1->diff($dt2);

    $minutos = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
    return round($minutos / 60, 2);
}

/**
 * Formatea horas decimales a texto legible.
 * Ej: 7.75 → "7 h 45 min"
 *
 * @param float $horas
 * @return string
 */
function formatearHoras(float $horas): string
{
    $h   = (int) $horas;
    $min = (int) round(($horas - $h) * 60);
    return "{$h} h {$min} min";
}


// ══════════════════════════════════════════════════
//  VALIDACIONES DE EMPLEADO
// ══════════════════════════════════════════════════

/**
 * Verifica que un empleado exista y esté activo.
 * Devuelve los datos del empleado o false.
 *
 * @param PDO    $pdo
 * @param string $documento
 * @return array|false
 */
function verificarEmpleado(PDO $pdo, string $documento): array|false
{
    $sql = "SELECT u.documento, u.nombre, u.email, t.tip_user
            FROM user u
            INNER JOIN TYPE_USER t ON u.id_tip_user = t.id_tip_user
            WHERE u.documento = ?
              AND u.estado = 1
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$documento]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Valida el formato de un documento (solo números, entre 6 y 12 dígitos).
 *
 * @param string $documento
 * @return bool
 */
function validarDocumento(string $documento): bool
{
    return (bool) preg_match('/^\d{6,12}$/', $documento);
}

/**
 * Sanitiza y limpia una cadena de texto para uso seguro en HTML.
 *
 * @param string $valor
 * @return string
 */
function limpiar(string $valor): string
{
    return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
}


// ══════════════════════════════════════════════════
//  REPORTES
// ══════════════════════════════════════════════════

/**
 * Obtiene el reporte de asistencias con JOIN a empleados.
 * Permite filtrar por fecha de inicio y fin.
 *
 * @param PDO         $pdo
 * @param string|null $fechaInicio  'Y-m-d'  (opcional)
 * @param string|null $fechaFin     'Y-m-d'  (opcional)
 * @return array
 */
function obtenerReporteAsistencias(PDO $pdo, ?string $fechaInicio = null, ?string $fechaFin = null): array
{
    $sql = "SELECT
                a.id_asistencia,
                u.documento,
                u.nombre,
                a.fecha_entrada,
                a.fecha_salida,
                a.horas_trabajadas
            FROM asistencias a
            INNER JOIN user u ON a.id_empleado = u.documento
            WHERE 1=1";

    $params = [];

    if ($fechaInicio) {
        $sql .= " AND DATE(a.fecha_entrada) >= ?";
        $params[] = $fechaInicio;
    }
    if ($fechaFin) {
        $sql .= " AND DATE(a.fecha_entrada) <= ?";
        $params[] = $fechaFin;
    }

    $sql .= " ORDER BY a.fecha_entrada DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Suma el total de horas trabajadas de un arreglo de asistencias.
 *
 * @param array $asistencias  Resultado de obtenerReporteAsistencias()
 * @return float
 */
function totalHorasReporte(array $asistencias): float
{
    return round(array_sum(array_column($asistencias, 'horas_trabajadas')), 2);
}
// ══════════════════════════════════════════════════
//  GESTIÓN DE EMPLEADOS
// ══════════════════════════════════════════════════

/**
 * Crea un nuevo empleado en la base de datos.
 *
 * @param PDO    $pdo
 * @param array  $datos  Claves: documento, nombre, email, id_tip_user, password
 * @return array ['ok' => bool, 'mensaje' => string]
 */
function crearEmpleado(PDO $pdo, array $datos): array
{
    try {
        // Verificar que el documento no exista ya
        $sqlCheck = "SELECT documento FROM user WHERE documento = ? LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$datos['documento']]);
        if ($stmtCheck->fetch()) {
            return [
                'ok'      => false,
                'mensaje' => '⚠️ Ya existe un empleado con ese documento.'
            ];
        }

        $sql = "INSERT INTO user (documento, nombre, email, id_tip_user, password, estado)
                VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['documento'],
            trim($datos['nombre']),
            trim($datos['email']),
            $datos['id_tip_user'],
            password_hash($datos['password'], PASSWORD_BCRYPT)
        ]);

        return [
            'ok'      => true,
            'mensaje' => '✅ Empleado creado correctamente.'
        ];

    } catch (PDOException $e) {
        error_log('Error crearEmpleado: ' . $e->getMessage());
        return [
            'ok'      => false,
            'mensaje' => '❌ Error interno al crear el empleado.'
        ];
    }
}

/**
 * Edita los datos de un empleado existente.
 * Solo actualiza los campos que vengan en $datos (nombre, email, id_tip_user, estado).
 * Si se incluye 'password', la re-hashea y la actualiza también.
 *
 * @param PDO    $pdo
 * @param string $documento  Documento del empleado a editar
 * @param array  $datos      Campos a actualizar (nombre, email, id_tip_user, estado, password)
 * @return array ['ok' => bool, 'mensaje' => string]
 */
function editarEmpleado(PDO $pdo, string $documento, array $datos): array
{
    try {
        // Verificar que el empleado exista
        $sqlCheck = "SELECT documento FROM user WHERE documento = ? LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$documento]);
        if (!$stmtCheck->fetch()) {
            return [
                'ok'      => false,
                'mensaje' => '⚠️ No se encontró ningún empleado con ese documento.'
            ];
        }

        $campos  = [];
        $valores = [];

        $permitidos = ['nombre', 'email', 'id_tip_user', 'estado'];
        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $campos[]  = "{$campo} = ?";
                $valores[] = is_string($datos[$campo]) ? trim($datos[$campo]) : $datos[$campo];
            }
        }

        // Contraseña opcional
        if (!empty($datos['password'])) {
            $campos[]  = "password = ?";
            $valores[] = password_hash($datos['password'], PASSWORD_BCRYPT);
        }

        if (empty($campos)) {
            return [
                'ok'      => false,
                'mensaje' => '⚠️ No se enviaron campos válidos para actualizar.'
            ];
        }

        $valores[] = $documento; // Para el WHERE
        $sql = "UPDATE user SET " . implode(', ', $campos) . " WHERE documento = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($valores);

        return [
            'ok'      => true,
            'mensaje' => '✅ Empleado actualizado correctamente.'
        ];

    } catch (PDOException $e) {
        error_log('Error editarEmpleado: ' . $e->getMessage());
        return [
            'ok'      => false,
            'mensaje' => '❌ Error interno al editar el empleado.'
        ];
    }
}

/**
 * Busca un empleado por su documento.
 * Devuelve sus datos básicos junto al nombre del tipo de usuario.
 *
 * @param PDO    $pdo
 * @param string $documento
 * @return array|null  Array con los datos del empleado, o null si no existe.
 */
function buscarEmpleadoPorDocumento(PDO $pdo, string $documento): ?array
{
    try {
        $sql = "SELECT
                    u.documento,
                    u.nombre,
                    u.email,
                    u.estado,
                    t.tip_user
                FROM user u
                INNER JOIN TYPE_USER t ON u.id_tip_user = t.id_tip_user
                WHERE u.documento = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$documento]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $empleado ?: null;

    } catch (PDOException $e) {
        error_log('Error buscarEmpleadoPorDocumento: ' . $e->getMessage());
        return null;
    }
}