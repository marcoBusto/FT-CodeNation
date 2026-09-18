<?php

// Un warning/notice de PHP nunca debe filtrarse en el cuerpo de la respuesta
// (rompe el contrato JSON de la API y puede exponer rutas internas del
// servidor). Se loguean en el servidor en vez de mostrarse.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../config/Database.php';
// A medida que se creen controllers, requerirlos acá:
// require __DIR__ . '/../src/Controllers/EjemploController.php';

// React corre en un origen distinto (puerto/dominio propio) al de esta API,
// tanto en desarrollo como en producción, asi que el navegador exige estos
// headers para permitir que el frontend lea la respuesta.
$origenPermitido = getenv('FRONTEND_URL') ?: 'http://localhost:5173';
header("Access-Control-Allow-Origin: {$origenPermitido}");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'OPTIONS') {
    http_response_code(204);
    return;
}

// Formato de respuesta estándar para toda la API: el frontend siempre revisa
// "error" primero — si es null, el contenido útil está en "data", sin
// importar qué endpoint sea.
function responder($data, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode(['data' => $data, 'error' => null], JSON_UNESCAPED_UNICODE);
}

function responderError(string $mensaje, int $codigo, array $detalles = []): void
{
    http_response_code($codigo);
    echo json_encode([
        'data' => null,
        'error' => $detalles ? ['mensaje' => $mensaje, 'detalles' => $detalles] : ['mensaje' => $mensaje],
    ], JSON_UNESCAPED_UNICODE);
}

// Los controladores de alta/edición devuelven ['errores' => [...]] cuando la
// validación falla, o los datos guardados cuando todo sale bien. Esta función
// traduce eso al formato de respuesta estándar, para no repetir el mismo
// if/else en cada ruta que crea o edita algo.
function responderResultado(array $resultado, int $codigoExito = 200): void
{
    if (isset($resultado['errores'])) {
        responderError('Datos inválidos', 422, $resultado['errores']);
    } else {
        responder($resultado, $codigoExito);
    }
}

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$cuerpo = fn () => json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if ($ruta === '/' && $metodo === 'GET') {
        responder([
            'status' => 'ok',
            'mensaje' => 'API funcionando correctamente',
            'timestamp' => date('c'),
        ]);
        return;
    }

    // Patrón para nuevas rutas:
    //
    // if ($ruta === '/recurso' && $metodo === 'GET') {
    //     responder(RecursoController::listar());
    //     return;
    // }
    //
    // if ($ruta === '/recurso' && $metodo === 'POST') {
    //     responderResultado(RecursoController::crear($cuerpo()), 201);
    //     return;
    // }
    //
    // if (preg_match('#^/recurso/(\d+)$#', $ruta, $coincidencia) && $metodo === 'PUT') {
    //     responderResultado(RecursoController::actualizar((int) $coincidencia[1], $cuerpo()));
    //     return;
    // }

    responderError('Ruta no encontrada', 404);
} catch (Throwable $e) {
    responderError('Error interno del servidor', 500);
}
