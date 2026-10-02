<?php
/**
 * setup-toolkit.php — Herramienta genérica de despliegue y diagnóstico Laravel
 * ⚠️  Eliminar después de cada sesión de mantenimiento.
 *
 * Coloca este archivo en el directorio público del servidor, al mismo nivel
 * desde donde puedas referenciar la carpeta raíz del proyecto Laravel.
 */

error_reporting(E_ALL);

// ── Protección básica de acceso ─────────────────────────────────────────
define('TOOLKIT_SECRET', 'R9AAbWSZeZRhccQmv45EdcfGPkYmTTxB62i');

if (($_GET['key'] ?? $_POST['key'] ?? '') !== TOOLKIT_SECRET) {
    http_response_code(404);
    exit('Not found');
}

ini_set('display_errors', 1);

// ── CONFIG: editar por proyecto ────────────────────────────────────────────
define('TOOL_NAME', 'Deploy Toolkit');           // Nombre mostrado en el header
define('APP_ROOT',    dirname(__DIR__, 2) . '/sales.dezavoice.com/system'); // Ajusta la ruta al proyecto Laravel
define('PUBLIC_ROOT', __DIR__);

// Subcarpetas del disco "public" que quieras auditar en la sección Storage.
// Déjalo vacío ([]) si no aplica a este proyecto.
$MEDIA_FOLDERS = [
    // 'categorias',
    // 'productos/galeria',
];

// Variables ENV de servicios opcionales que quieras exponer en la sección ENV.
// Formato: 'NOMBRE_ENV' => 'Etiqueta a mostrar'
$EXTRA_SERVICE_ENV_KEYS = [
    // 'MERCADOPAGO_ACCESS_TOKEN' => 'MercadoPago Token',
    // 'STRIPE_SECRET' => 'Stripe Secret',
];
// ─────────────────────────────────────────────────────────────────────────

// ── Cargar Laravel ────────────────────────────────────────────────────────
$laravelOk = false;
$laravelError = null;

try {
    require APP_ROOT . '/vendor/autoload.php';
    $app    = require_once APP_ROOT . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->handle(Illuminate\Http\Request::capture());
    $laravelOk = true;
} catch (Throwable $e) {
    $laravelError = $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine();
}

// ── Helpers ───────────────────────────────────────────────────────────────
function artisan(string $cmd, array $params = []): string {
    Illuminate\Support\Facades\Artisan::call($cmd, $params);
    return trim(Illuminate\Support\Facades\Artisan::output());
}

function ok(string $msg): string  { return "<span class='ok'>✅ {$msg}</span>"; }
function err(string $msg): string { return "<span class='err'>❌ {$msg}</span>"; }
function warn(string $msg): string{ return "<span class='warn'>⚠️  {$msg}</span>"; }
function info(string $msg): string{ return "<span class='info'>ℹ️  {$msg}</span>"; }

// ── Procesar acción POST ─────────────────────────────────────────────────
$result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $laravelOk) {
    $action = $_POST['action'];
    ob_start();

    try {
        switch ($action) {

            // ── ENV ───────────────────────────────────────────────────────
            case 'env':
                echo "<strong>APLICACIÓN</strong>\n";
                echo sprintf("  %-28s %s\n", 'APP_NAME',  config('app.name'));
                echo sprintf("  %-28s %s\n", 'APP_ENV',   config('app.env'));
                echo sprintf("  %-28s %s\n", 'APP_DEBUG', config('app.debug') ? warn('true') : ok('false'));
                echo sprintf("  %-28s %s\n", 'APP_URL',   config('app.url'));
                echo sprintf("  %-28s %s\n", 'APP_KEY',   config('app.key') ? ok('Configurada') : err('NO CONFIGURADA'));

                echo "\n<strong>BASE DE DATOS</strong>\n";
                echo sprintf("  %-28s %s\n", 'DB_CONNECTION', config('database.default'));
                echo sprintf("  %-28s %s\n", 'DB_HOST',       config('database.connections.mysql.host'));
                echo sprintf("  %-28s %s\n", 'DB_PORT',       config('database.connections.mysql.port'));
                echo sprintf("  %-28s %s\n", 'DB_DATABASE',   config('database.connections.mysql.database'));
                echo sprintf("  %-28s %s\n", 'DB_USERNAME',   config('database.connections.mysql.username'));
                $pass = config('database.connections.mysql.password');
                echo sprintf("  %-28s %s\n", 'DB_PASSWORD',   $pass ? ok('Configurada (' . strlen($pass) . ' chars)') : err('vacía'));

                echo "\n<strong>STORAGE</strong>\n";
                echo sprintf("  %-28s %s\n", 'FILESYSTEM_DISK',        config('filesystems.default'));
                echo sprintf("  %-28s %s\n", 'FILESYSTEM_PUBLIC_ROOT', config('filesystems.disks.public.root'));
                echo sprintf("  %-28s %s\n", 'FILESYSTEM_PUBLIC_URL',  config('filesystems.disks.public.url'));

                if (!empty($EXTRA_SERVICE_ENV_KEYS)) {
                    echo "\n<strong>SERVICIOS</strong>\n";
                    foreach ($EXTRA_SERVICE_ENV_KEYS as $envKey => $label) {
                        $value = env($envKey);
                        echo sprintf("  %-28s %s\n", $label, $value ? ok('Configurado') : err('NO configurado'));
                    }
                }

                echo "\n<strong>MAIL</strong>\n";
                echo sprintf("  %-28s %s\n", 'MAIL_MAILER', config('mail.default'));
                echo sprintf("  %-28s %s\n", 'MAIL_HOST',   config('mail.mailers.smtp.host') ?? 'N/A');
                echo sprintf("  %-28s %s\n", 'MAIL_FROM',   config('mail.from.address') ?? 'N/A');
                break;

            // ── TEST DB ───────────────────────────────────────────────────
            case 'db':
                try {
                    $pdo    = Illuminate\Support\Facades\DB::connection()->getPdo();
                    $dbname = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
                    echo ok("Conexión exitosa → {$dbname}") . "\n\n";

                    $tables = Illuminate\Support\Facades\DB::select('SHOW TABLES');
                    $count  = count($tables);
                    echo ok("{$count} tablas encontradas") . "\n";

                    $names = array_map(fn($t) => array_values((array)$t)[0], $tables);
                    sort($names);
                    foreach ($names as $name) {
                        echo "  · {$name}\n";
                    }
                } catch (Throwable $e) {
                    echo err('No se pudo conectar') . "\n";
                    echo "  Mensaje: " . $e->getMessage() . "\n";
                    echo "\n" . warn('Verifica DB_HOST, DB_DATABASE, DB_USERNAME y DB_PASSWORD en tu .env') . "\n";
                }
                break;

            // ── MIGRAR ────────────────────────────────────────────────────
            case 'migrate':
                try {
                    echo info('Ejecutando: php artisan migrate --force') . "\n\n";
                    $out = artisan('migrate', ['--force' => true]);
                    echo ok('migrate completado') . "\n{$out}\n";
                } catch (Throwable $e) {
                    echo err('migrate falló') . "\n" . $e->getMessage() . "\n";
                    echo info('Revisa el estado de migraciones antes de reintentar') . "\n";
                }
                break;

            // ── ESTADO DE MIGRACIONES ────────────────────────────────────
            case 'migrate_status':
                try {
                    $out = artisan('migrate:status');
                    echo ok('migrate:status') . "\n{$out}\n";
                } catch (Throwable $e) {
                    echo err('migrate:status falló') . "\n" . $e->getMessage() . "\n";
                }
                break;

            // ── CLEAR CACHE ───────────────────────────────────────────────
            case 'clear':
                $cmds = ['config:clear', 'route:clear', 'view:clear', 'cache:clear', 'event:clear'];
                foreach ($cmds as $cmd) {
                    try {
                        $out = artisan($cmd);
                        echo ok($cmd) . ($out ? " → {$out}" : '') . "\n";
                    } catch (Throwable $e) {
                        echo err($cmd) . " → " . $e->getMessage() . "\n";
                    }
                }

                echo "\n<strong>Limpieza manual de bootstrap/cache</strong>\n";
                $cacheDir = APP_ROOT . '/bootstrap/cache';
                $files = glob($cacheDir . '/*.php') ?: [];
                if (empty($files)) {
                    echo info('bootstrap/cache ya estaba vacío') . "\n";
                } else {
                    foreach ($files as $f) {
                        @unlink($f) ? print(ok(basename($f) . ' eliminado') . "\n") : print(warn('No se pudo eliminar ' . basename($f)) . "\n");
                    }
                }

                echo "\n<strong>Limpieza manual de storage/framework/views</strong>\n";
                $viewsDir = APP_ROOT . '/storage/framework/views';
                $views = glob($viewsDir . '/*.php') ?: [];
                echo count($views) > 0
                    ? ok(count($views) . ' archivos de vistas compiladas eliminados') . "\n"
                    : info('Sin vistas compiladas') . "\n";
                foreach ($views as $v) { @unlink($v); }
                break;

            // ── CLEAR VIEWS (solo vistas, más agresivo) ─────────────────────
            case 'clear_views':
                echo info('Ejecutando: php artisan view:clear') . "\n\n";
                try {
                    $out = artisan('view:clear');
                    echo ok('view:clear') . ($out ? " → {$out}" : '') . "\n";
                } catch (Throwable $e) {
                    echo err('view:clear falló') . " → " . $e->getMessage() . "\n";
                }

                echo "\n<strong>Borrado manual de storage/framework/views</strong>\n";
                $viewsDir = APP_ROOT . '/storage/framework/views';
                $views = glob($viewsDir . '/*.php') ?: [];
                if (empty($views)) {
                    echo info('No había archivos compilados que borrar') . "\n";
                } else {
                    $deleted = 0;
                    $failed = 0;
                    foreach ($views as $v) {
                        if (@unlink($v)) {
                            $deleted++;
                        } else {
                            $failed++;
                            echo warn('No se pudo eliminar ' . basename($v)) . "\n";
                        }
                    }
                    echo ok("{$deleted} archivo(s) de vistas compiladas eliminados") . "\n";
                    if ($failed > 0) {
                        echo err("{$failed} archivo(s) no se pudieron eliminar (revisa permisos de escritura en storage/framework/views)") . "\n";
                    }
                }

                echo "\n<strong>Reset de OPcache</strong>\n";
                if (function_exists('opcache_reset')) {
                    $resetOk = @opcache_reset();
                    echo $resetOk
                        ? ok('opcache_reset() ejecutado correctamente') . "\n"
                        : warn('opcache_reset() devolvió false (puede estar deshabilitado o restringido)') . "\n";
                } else {
                    echo info('opcache_reset() no está disponible en este entorno PHP') . "\n";
                }

                echo "\n" . info('Si el error persiste después de esto, confirma que el archivo .blade.php en el servidor realmente tiene el contenido esperado (puede que el deploy no haya subido el cambio).') . "\n";
                break;

            // ── OPTIMIZE ──────────────────────────────────────────────────
            case 'optimize':
                try {
                    $out = artisan('optimize');
                    echo ok('optimize completado') . "\n{$out}\n";
                } catch (Throwable $e) {
                    echo err('optimize falló') . "\n" . $e->getMessage() . "\n";
                    echo info('Intenta correr clear primero') . "\n";
                }
                break;

            // ── STORAGE INFO ─────────────────────────────────────────────
            case 'storage':
                $diskRoot = config('filesystems.disks.public.root');
                $diskUrl  = config('filesystems.disks.public.url');

                echo "<strong>Configuración del disco public</strong>\n";
                echo sprintf("  %-20s %s\n", 'root (física):', $diskRoot);
                echo sprintf("  %-20s %s\n", 'url (pública):', $diskUrl);

                echo "\n<strong>¿Existe la carpeta root?</strong>\n";
                if (is_dir($diskRoot)) {
                    echo ok($diskRoot . ' existe') . "\n";
                    echo sprintf("  %-20s %s\n", 'Permisos:', substr(sprintf('%o', fileperms($diskRoot)), -4));
                    echo sprintf("  %-20s %s\n", 'Escribible:', is_writable($diskRoot) ? ok('Sí') : err('No'));
                } else {
                    echo err($diskRoot . ' NO existe') . "\n";
                    echo info('Crear la carpeta manualmente vía FTP o cPanel') . "\n";
                }

                if (!empty($MEDIA_FOLDERS)) {
                    echo "\n<strong>Subcarpetas de media</strong>\n";
                    foreach ($MEDIA_FOLDERS as $folder) {
                        $path = $diskRoot . '/' . $folder;
                        if (is_dir($path)) {
                            $files = count(array_diff(scandir($path), ['.', '..']));
                            echo ok($folder) . " ({$files} archivos)\n";
                        } else {
                            echo warn($folder . ' no existe aún') . "\n";
                        }
                    }
                }

                echo "\n<strong>Test de escritura</strong>\n";
                $testFile = '_test_write_' . time() . '.txt';
                try {
                    Illuminate\Support\Facades\Storage::disk('public')->put($testFile, 'test-' . date('c'));
                    echo ok("Storage::disk('public')->put() funciona") . "\n";
                    $publicPath = $diskRoot . '/' . $testFile;
                    echo is_file($publicPath)
                        ? ok('Archivo accesible en ruta física') . "\n"
                        : warn('Archivo no encontrado en ruta física (¿ruta incorrecta?)') . "\n";
                    Illuminate\Support\Facades\Storage::disk('public')->delete($testFile);
                    echo info('Archivo de prueba eliminado') . "\n";
                } catch (Throwable $e) {
                    echo err('Error de escritura: ' . $e->getMessage()) . "\n";
                }

                echo "\n<strong>URL de ejemplo</strong>\n";
                echo "  " . $diskUrl . "/ejemplo.webp\n";
                break;

            // ── VERIFICAR ARCHIVO API.PHP EN SERVIDOR ───────────────────────
            case 'check_api_file':
                $apiPath = APP_ROOT . '/routes/api.php';
                echo ok('Ruta: ' . $apiPath) . "\n\n";
                if (file_exists($apiPath)) {
                    echo ok('El archivo existe') . "\n";
                    echo "\n<strong>Contenido:</strong>\n";
                    echo htmlspecialchars(file_get_contents($apiPath)) . "\n";
                } else {
                    echo err('El archivo NO existe en el servidor') . "\n";
                }
                break;

            // ── RUTAS ─────────────────────────────────────────────────────
            // ── RETELL: CONFIGURACIÓN ─────────────────────────────────────
            case 'retell':
                $cfg   = config('services.retell');
                $key   = (string) config('services.retell.api_key');
                $agent = (string) config('services.retell.web_test_agent_id');

                echo sprintf("  %-36s %s\n", "bloque 'retell' en services.php", is_array($cfg) ? ok('existe') : err('NO existe: falta en config/services.php'));
                echo sprintf("  %-36s %s\n", 'api_key (config)', $key !== '' ? ok('configurada, ' . strlen($key) . ' caracteres') : err('vacía'));
                echo sprintf("  %-36s %s\n", 'web_test_agent_id (config)', $agent !== '' ? ok(substr($agent, 0, 12) . '…') : err('vacío'));
                echo sprintf("  %-36s %s\n", 'agent_version', config('services.retell.web_test_agent_version') ?: info('sin definir'));
                echo sprintf("  %-36s %s\n", 'max_minutes', config('services.retell.web_test_max_minutes') ?? info('sin definir'));
                echo sprintf("  %-36s %s\n", 'APP_URL', config('app.url'));
                echo sprintf("  %-36s %s\n", 'URL del webhook', rtrim((string) config('app.url'), '/') . '/api/v1/webhooks/retell');
                echo sprintf("  %-36s %s\n", 'config cacheada', app()->configurationIsCached() ? warn('sí: los cambios del .env no se leen hasta limpiar caché') : ok('no'));

                echo "\n<strong>Archivo .env en el servidor</strong>\n";
                $envPath = APP_ROOT . '/.env';
                $envText = is_file($envPath) ? (string) file_get_contents($envPath) : '';
                echo sprintf("  %-36s %s\n", 'lectura de .env', $envText !== '' ? ok('existe') : err('no se pudo leer ' . $envPath));
                foreach (['RETELL_API_KEY', 'RETELL_WEB_TEST_AGENT_ID', 'RETELL_WEB_TEST_AGENT_VERSION', 'RETELL_WEB_TEST_MAX_MINUTES'] as $name) {
                    $found = preg_match('/^\s*' . $name . '\s*=\s*(\S.*)$/m', $envText);
                    echo sprintf("  %-36s %s\n", $name, $found ? ok('presente en .env') : err('no está en .env'));
                }
                break;

            // ── RETELL: PROBAR LLAVE Y AGENTE ─────────────────────────────
            case 'retell_test':
                try {
                    $data = app(App\Services\Retell\RetellApi::class)->createWebCall([
                        'agent_id' => config('services.retell.web_test_agent_id'),
                    ]);
                    echo ok('Retell aceptó la llave y el agente') . "\n";
                    echo "  call_id: " . ($data['call_id'] ?? '?') . "\n";
                    echo info('La llamada no se conecta; su token caduca en 30 segundos.') . "\n";
                } catch (Throwable $e) {
                    echo err('Retell respondió con error') . "\n  " . $e->getMessage() . "\n";
                    echo info('401 = llave inválida · 422 = agente inexistente o de otra cuenta') . "\n";
                }
                break;

            // ── WEB PUSH: ESTADO ──────────────────────────────────────────
            case 'webpush':
                $cfg = config('services.webpush');

                echo sprintf("  %-34s %s\n", 'minishlink/web-push', class_exists(Minishlink\WebPush\WebPush::class) ? ok('instalada') : err('NO instalada: sube vendor/ tras composer require'));

                foreach (['openssl' => true, 'curl' => true, 'mbstring' => true, 'gmp' => false, 'bcmath' => false] as $ext => $required) {
                    echo sprintf("  %-34s %s\n", "extensión {$ext}", extension_loaded($ext)
                        ? ok('activa')
                        : ($required ? err('FALTA') : warn('no activa (opcional, solo rendimiento)')));
                }

                echo sprintf("  %-34s %s\n", 'VAPID public key', filled($cfg['public_key'] ?? null) ? ok('configurada') : err('vacía'));
                echo sprintf("  %-34s %s\n", 'VAPID private key', filled($cfg['private_key'] ?? null) ? ok('configurada') : err('vacía'));
                echo sprintf("  %-34s %s\n", 'VAPID subject', $cfg['subject'] ?? err('sin definir'));
                echo sprintf("  %-34s %s\n", 'dispositivos registrados', Illuminate\Support\Facades\DB::table('device_tokens')->whereNull('deleted_at')->count());
                break;

            // ── WEB PUSH: GENERAR CLAVES VAPID ────────────────────────────
            case 'vapid_generate':
                if (! class_exists(Minishlink\WebPush\VAPID::class)) {
                    echo err('La librería minishlink/web-push no está instalada.') . "\n";
                    break;
                }

                $keys = Minishlink\WebPush\VAPID::createVapidKeys();
                echo ok('Claves generadas. Cópialas a tu .env y limpia caché.') . "\n\n";
                echo "VAPID_PUBLIC_KEY={$keys['publicKey']}\n";
                echo "VAPID_PRIVATE_KEY={$keys['privateKey']}\n\n";
                echo warn('Si ya había claves, cambiarlas invalida las suscripciones: cada usuario debe volver a activar sus notificaciones.') . "\n";
                echo warn('La clave privada quedó en pantalla: elimina el toolkit al terminar.') . "\n";
                break;

            case 'routes':
                $routes = collect(Illuminate\Support\Facades\Route::getRoutes())
                    ->map(function ($route) {
                        return [
                            'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
                            'uri'    => $route->uri(),
                            'name'   => $route->getName() ?? '',
                            'action' => $route->getActionName(),
                        ];
                    })
                    ->sortBy('uri')
                    ->values();

                echo ok($routes->count() . ' rutas registradas') . "\n\n";

                foreach ($routes as $r) {
                    echo sprintf(
                        "%-10s %-45s %-25s %s\n",
                        $r['method'],
                        $r['uri'],
                        $r['name'],
                        $r['action']
                    );
                }
                break;

            default:
                echo warn('Acción desconocida') . "\n";
        }
    } catch (Throwable $e) {
        echo err('Error inesperado') . "\n";
        echo $e->getMessage() . "\n";
        echo "En " . $e->getFile() . ':' . $e->getLine() . "\n";
    }

    $result = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(TOOL_NAME) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; background: #0f0f0f; color: #d4d4d4; min-height: 100vh; padding: 2rem; }
        .wrap { max-width: 860px; margin: 0 auto; }

        header { border-bottom: 1px solid #333; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        header h1 { font-family: Georgia, serif; font-size: 1.6rem; color: #9fb4c9; font-weight: normal; letter-spacing: .05em; }
        header p  { font-size: .75rem; color: #666; margin-top: .4rem; }

        .warning { background: #2a1a1a; border: 1px solid #a04c4c; border-radius: 4px; padding: .75rem 1rem; margin-bottom: 2rem; font-size: .8rem; color: #e08a8a; }

        .status { background: #1a1a1a; border: 1px solid #333; border-radius: 4px; padding: .75rem 1rem; margin-bottom: 2rem; font-size: .8rem; }
        .status .ok  { color: #6dbf67; }
        .status .err { color: #e06c6c; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .75rem; margin-bottom: 2rem; }

        form.action-form { display: contents; }

        button {
            width: 100%;
            background: #1e1e1e;
            border: 1px solid #444;
            color: #9fb4c9;
            padding: .75rem 1rem;
            font-family: 'Courier New', monospace;
            font-size: .8rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            cursor: pointer;
            border-radius: 4px;
            transition: background .2s, border-color .2s;
            text-align: left;
        }
        button:hover { background: #2a2a2a; border-color: #9fb4c9; }
        button .icon { display: block; font-size: 1.2rem; margin-bottom: .35rem; }
        button .label { display: block; }
        button .desc { display: block; font-size: .65rem; color: #666; margin-top: .2rem; text-transform: none; letter-spacing: 0; }

        button.danger { color: #e0a06c; }
        button.danger:hover { border-color: #e0a06c; }

        .output-wrap { background: #111; border: 1px solid #333; border-radius: 4px; padding: 1.25rem 1.5rem; }
        .output-wrap h2 { font-size: .8rem; color: #888; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 1rem; border-bottom: 1px solid #222; padding-bottom: .5rem; }
        .output-wrap pre { font-size: .8rem; line-height: 1.7; white-space: pre-wrap; word-break: break-all; }

        .ok   { color: #6dbf67; }
        .err  { color: #e06c6c; }
        .warn { color: #e0b96c; }
        .info { color: #6cb4e0; }

        footer { margin-top: 3rem; padding-top: 1rem; border-top: 1px solid #222; font-size: .7rem; color: #444; }
    </style>
</head>
<body>
<div class="wrap">

    <header>
        <h1><?= htmlspecialchars(TOOL_NAME) ?></h1>
        <p>Herramienta de despliegue y diagnóstico · <?= date('Y-m-d H:i:s') ?> · PHP <?= PHP_VERSION ?></p>
    </header>

    <div class="warning">
        ⚠️ &nbsp;Este archivo debe eliminarse después de cada sesión de mantenimiento. No dejes este archivo en producción.
    </div>

    <div class="status">
        Laravel:
        <?php if ($laravelOk): ?>
            <span class="ok">✅ cargado correctamente</span> · <?= config('app.name') ?> · <?= config('app.env') ?>
        <?php else: ?>
            <span class="err">❌ error al cargar: <?= htmlspecialchars($laravelError) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($laravelOk): ?>
    <div class="grid">

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="env">
                <span class="icon">🔍</span>
                <span class="label">Variables ENV</span>
                <span class="desc">App, DB, Storage, Servicios</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="db">
                <span class="icon">🗄️</span>
                <span class="label">Test Base de Datos</span>
                <span class="desc">Conexión + listado de tablas</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="migrate_status">
                <span class="icon">📋</span>
                <span class="label">Estado Migraciones</span>
                <span class="desc">php artisan migrate:status</span>
            </button>
        </form>

        <form class="action-form" method="POST" onsubmit="return confirm('¿Ejecutar migraciones pendientes en producción?');">
            <button class="danger" type="submit" name="action" value="migrate">
                <span class="icon">🚀</span>
                <span class="label">Migrar</span>
                <span class="desc">php artisan migrate --force</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="clear">
                <span class="icon">🧹</span>
                <span class="label">Limpiar Caché</span>
                <span class="desc">Config, rutas, vistas, eventos</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="clear_views">
                <span class="icon">🗑️</span>
                <span class="label">Limpiar Views</span>
                <span class="desc">view:clear + borrado manual + OPcache reset</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="optimize">
                <span class="icon">⚡</span>
                <span class="label">Optimize</span>
                <span class="desc">php artisan optimize</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="storage">
                <span class="icon">📁</span>
                <span class="label">Storage</span>
                <span class="desc">Rutas, permisos, test escritura</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="routes">
                <span class="icon">🛣️</span>
                <span class="label">Rutas</span>
                <span class="desc">Todas las rutas registradas (web + api)</span>
            </button>
        </form>
        
        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="check_api_file">
                <span class="icon">📄</span>
                <span class="label">Ver api.php</span>
                <span class="desc">Contenido real en servidor</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="retell">
                <span class="icon">📞</span>
                <span class="label">Retell: configuración</span>
                <span class="desc">Variables, caché y URL del webhook</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="retell_test">
                <span class="icon">🔑</span>
                <span class="label">Retell: probar llave</span>
                <span class="desc">Crea una llamada de prueba (no se conecta)</span>
            </button>
        </form>

        <form class="action-form" method="POST">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="webpush">
                <span class="icon">🔔</span>
                <span class="label">Web Push: estado</span>
                <span class="desc">Librería, extensiones y claves VAPID</span>
            </button>
        </form>

        <form class="action-form" method="POST" onsubmit="return confirm('Esto genera claves nuevas. ¿Continuar?');">
    <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
            <button type="submit" name="action" value="vapid_generate">
                <span class="icon">🗝️</span>
                <span class="label">Web Push: claves VAPID</span>
                <span class="desc">Genera un par nuevo para el .env</span>
            </button>
        </form>

    </div>
    <?php endif; ?>

    <?php if ($result): ?>
    <div class="output-wrap">
        <h2>Resultado</h2>
        <pre><?= $result ?></pre>
    </div>
    <?php endif; ?>

    <footer>
        APP_ROOT: <?= APP_ROOT ?> &nbsp;·&nbsp;
        PUBLIC_ROOT: <?= PUBLIC_ROOT ?> &nbsp;·&nbsp;
        Memory: <?= round(memory_get_usage()/1024/1024, 1) ?> MB
    </footer>

</div>
</body>
</html>