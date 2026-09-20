<?php

// Un warning/notice de PHP nunca debe filtrarse en el cuerpo de la respuesta
// (rompe el contrato JSON de la API y puede exponer rutas internas del
// servidor). Se loguean en el servidor en vez de mostrarse.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../src/Tenant.php';
require __DIR__ . '/../src/Controllers/CampoController.php';
require __DIR__ . '/../src/Controllers/LoteController.php';
require __DIR__ . '/../src/Controllers/InsumoController.php';
require __DIR__ . '/../src/Controllers/MarcaController.php';
require __DIR__ . '/../src/Controllers/CategoriaController.php';
require __DIR__ . '/../src/Controllers/MovimientoInsumoController.php';

// Carga el .env desde el arranque (no recién en la primera conexión a la
// base) porque el header CORS de acá abajo lo necesita ya mismo.
Database::inicializarEnv();

// React corre en un origen distinto (puerto/dominio propio) al de esta API,
// tanto en desarrollo como en producción, asi que el navegador exige estos
// headers para permitir que el frontend lea la respuesta. FRONTEND_URL puede
// listar varios orígenes separados por coma (ej. dominio propio + la URL
// default de Vercel) porque Access-Control-Allow-Origin solo acepta un
// valor a la vez: hay que reflejar el que matchee, no concatenarlos.
$origenesPermitidos = array_map('trim', explode(',', Database::obtenerVariable('FRONTEND_URL', 'http://localhost:5173')));
$origenSolicitado = $_SERVER['HTTP_ORIGIN'] ?? '';
$origenPermitido = in_array($origenSolicitado, $origenesPermitidos, true) ? $origenSolicitado : $origenesPermitidos[0];
header("Access-Control-Allow-Origin: {$origenPermitido}");
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Tenant-Id');
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
$query = fn (string $clave) => isset($_GET[$clave]) && $_GET[$clave] !== '' ? (int) $_GET[$clave] : null;

try {
    if ($ruta === '/' && $metodo === 'GET') {
        responder([
            'status' => 'ok',
            'mensaje' => 'API funcionando correctamente',
            'timestamp' => date('c'),
        ]);
        return;
    }

    // Toda ruta de acá en adelante pertenece al módulo de Stock e Insumos y
    // exige un tenant válido (ver src/Tenant.php: solución temporal hasta
    // que exista login real).
    $tenantId = Tenant::resolverDesdeHeader($_SERVER['HTTP_X_TENANT_ID'] ?? null);

    if ($ruta === '/campos' && $metodo === 'GET') {
        responder(CampoController::listar($tenantId));
        return;
    }

    if ($ruta === '/campos' && $metodo === 'POST') {
        responderResultado(CampoController::crear($tenantId, $cuerpo()), 201);
        return;
    }

    if ($ruta === '/lotes' && $metodo === 'GET') {
        responder(LoteController::listar($tenantId, $query('campo_id')));
        return;
    }

    if ($ruta === '/lotes' && $metodo === 'POST') {
        responderResultado(LoteController::crear($tenantId, $cuerpo()), 201);
        return;
    }

    if ($ruta === '/lotes/estimar-insumo' && $metodo === 'POST') {
        responderResultado(LoteController::estimarInsumo($cuerpo()));
        return;
    }

    if ($ruta === '/insumos' && $metodo === 'GET') {
        responder(InsumoController::listar($tenantId));
        return;
    }

    if ($ruta === '/insumos' && $metodo === 'POST') {
        responderResultado(InsumoController::crear($tenantId, $cuerpo()), 201);
        return;
    }

    if ($ruta === '/insumos/stock' && $metodo === 'GET') {
        responder(InsumoController::stock($tenantId));
        return;
    }

    if ($ruta === '/marcas' && $metodo === 'GET') {
        responder(MarcaController::listar($tenantId));
        return;
    }

    if ($ruta === '/marcas' && $metodo === 'POST') {
        responderResultado(MarcaController::crear($tenantId, $cuerpo()), 201);
        return;
    }

    if ($ruta === '/categorias' && $metodo === 'GET') {
        responder(CategoriaController::listar($tenantId));
        return;
    }

    if ($ruta === '/categorias' && $metodo === 'POST') {
        responderResultado(CategoriaController::crear($tenantId, $cuerpo()), 201);
        return;
    }

    if ($ruta === '/movimientos' && $metodo === 'GET') {
        responder(MovimientoInsumoController::listar($tenantId, $query('insumo_id'), $query('lote_id')));
        return;
    }

    if ($ruta === '/movimientos' && $metodo === 'POST') {
        responderResultado(MovimientoInsumoController::registrar($tenantId, $cuerpo()), 201);
        return;
    }

    responderError('Ruta no encontrada', 404);
} catch (DomainException $e) {
    responderError($e->getMessage(), 400);
} catch (Throwable $e) {
    responderError('Error interno del servidor', 500);
}
