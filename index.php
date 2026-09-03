<?php
/**
 * index.php — Front Controller
 * Punto de entrada único de la API.
 *  - Fase 3: enrutamiento por verbo HTTP + ruta.
 *  - Fase 6: configuración de errores de producción y manejadores
 *            globales que convierten cualquier fallo imprevisto en
 *            una respuesta 500 limpia en JSON.
 */

// =========================================================
//  1) CONFIGURACIÓN DE ERRORES (producción)
// =========================================================
// NUNCA mostrar errores en pantalla: un aviso de PHP impreso antes
// del JSON lo corrompería y expondría rutas internas del servidor.
// Se registran en el log del servidor, no se muestran al usuario.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Todas las respuestas de la API son JSON en UTF-8
header('Content-Type: application/json; charset=utf-8');

// =========================================================
//  2) MANEJADOR GLOBAL DE EXCEPCIONES
// =========================================================
// Cualquier excepción NO atrapada (fallo de conexión, error en una
// consulta, etc.) llega aquí. Registramos el detalle real en el log
// para poder depurar, y devolvemos un 500 GENÉRICO al cliente.
set_exception_handler(function (Throwable $e): void {
    error_log('[API-Tareas] ' . $e->getMessage()
        . ' en ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Error interno del servidor'], JSON_UNESCAPED_UNICODE);
});

// =========================================================
//  3) RED DE SEGURIDAD FINAL: ERRORES FATALES
// =========================================================
// Los errores FATALES (ej. llamar a una función inexistente) NO son
// excepciones, así que el manejador anterior no los ve. Este los
// captura al apagarse el script y evita que se filtre un error crudo.
register_shutdown_function(function (): void {
    $error = error_get_last();
    $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if ($error !== null && in_array($error['type'], $fatales, true)) {
        error_log('[API-Tareas][FATAL] ' . $error['message']
            . ' en ' . $error['file'] . ':' . $error['line']);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Error interno del servidor'], JSON_UNESCAPED_UNICODE);
    }
});

// =========================================================
//  4) ENRUTAMIENTO (Fase 3, sin cambios)
// =========================================================
require __DIR__ . '/src/TareaController.php';

// Ruta solicitada, relativa a la carpeta de la app
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($basePath === '/') {
    $basePath = '';
}

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($basePath !== '' && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}

$requestPath = trim($requestPath, '/');
$segments = $requestPath === '' ? [] : explode('/', $requestPath);

$method  = $_SERVER['REQUEST_METHOD'];
$recurso = $segments[0] ?? null;
$id      = $segments[1] ?? null;

if ($recurso !== 'tareas') {
    http_response_code(404);
    echo json_encode(['error' => 'Recurso no encontrado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new TareaController();

switch ($method) {
    case 'GET':
        if ($id === null) {
            $controller->index();
        } else {
            $controller->show($id);
        }
        break;

    case 'POST':
        $controller->store();
        break;

    case 'PUT':
    case 'PATCH':
        if ($id === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe indicar el ID de la tarea a actualizar'], JSON_UNESCAPED_UNICODE);
        } else {
            $controller->update($id);
        }
        break;

    case 'DELETE':
        if ($id === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe indicar el ID de la tarea a eliminar'], JSON_UNESCAPED_UNICODE);
        } else {
            $controller->destroy($id);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método HTTP no permitido'], JSON_UNESCAPED_UNICODE);
        break;
}