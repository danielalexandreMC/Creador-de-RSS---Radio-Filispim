<?php
/**
 * ============================================================================
 * Rádio FilispiM — Xestor de RSS
 * Ficheiro: install.php
 * ============================================================================
 *
 * Descrición:
 *   Script de instalación da aplicación. Permite crear o usuario
 *   administrador e inicializar o ficheiro de datos.
 *
 * Fluxo de instalación:
 *   1. Comprobar se a aplicación xa está instalada
 *   2. Verificar os requisitos do servidor (PHP, extensións, permisos)
 *   3. Mostrar o formulario de creación de usuario
 *   4. Validar os datos do formulario
 *   5. Crear o directorio de datos (data/) se non existe
 *   6. Crear o ficheiro .htaccess para protexer o directorio de datos
 *   7. Crear o ficheiro data.json coa estrutura inicial
 *   8. Mostrar a mensaxe de éxito
 *
 * Medidas de seguranza:
 *   - O contrasinal gárdase con bcrypt (PASSWORD_DEFAULT)
 *   - O directorio data/ protéxese con .htaccess (Deny from all)
 *   - Após a instalación, recoméndase eliminar este ficheiro
 *   - O formulario ten validación no lado do servidor (PHP) e no cliente (HTML5)
 *
 * Requisitos do servidor:
 *   - PHP 7.4 ou superior
 *   - Extensión password_hash (bcrypt) dispoñible
 *   - Extensión JSON dispoñible
 *   - Permiso de escritura no directorio actual (para crear data/)
 *
 * @package Rádio FilispiM — Xestor de RSS
 * @version 1.0
 * ============================================================================
 */

// ============================================================================
// RUTAS DE FICHEIROS
// ============================================================================

/**
 * Directorio onde se gardarán os datos da aplicación.
 * @var string
 */
$dataDir = __DIR__ . '/data';

/**
 * Ficheiro principal de datos (JSON).
 * @var string
 */
$dataFile = $dataDir . '/data.json';

/**
 * Ficheiro .htaccess para protexer o directorio de datos.
 * @var string
 */
$htaccessFile = $dataDir . '/.htaccess';

// ============================================================================
// VARIABLES DE ESTADO
// ============================================================================

/** @var string Mensaxe de erro para mostrar no formulario */
$error = '';

/** @var bool true se a instalación se completou correctamente */
$success = '';

/** @var bool true se a aplicación xa estaba instalada */
$installed = file_exists($dataFile);

// ============================================================================
// PROCESAMENTO DO FORMULARIO DE INSTALACIÓN
// ============================================================================

/**
 * Se se envía o formulario por POST e a aplicación non está instalada,
 * procesar a creación do usuario administrador.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    // Recoller datos do formulario
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // --------------------------------------------------------
    // Validacións dos campos do formulario
    // --------------------------------------------------------

    if (empty($username)) {
        $error = 'O nome de usuario é obrigatorio.';
    } elseif (strlen($username) < 3) {
        $error = 'O nome de usuario debe ter al menos 3 caracteres.';
    } elseif (empty($password)) {
        $error = 'O contrasinal é obrigatorio.';
    } elseif (strlen($password) < 6) {
        $error = 'O contrasinal debe ter al menos 6 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Os contrasinais non coinciden.';
    } else {
        // --------------------------------------------------------
        // Crear o directorio de datos se non existe
        // --------------------------------------------------------
        if (!is_dir($dataDir)) {
            if (!mkdir($dataDir, 0755, true)) {
                $error = 'Non se puido crear o directorio de datos. Verifica os permisos.';
            }
        }

        // --------------------------------------------------------
        // Crear o .htaccess para protexer o directorio de datos
        // --------------------------------------------------------
        if (empty($error)) {
            $htaccessContent = "# Denegar acceso directo aos ficheiros de datos\n";
            $htaccessContent .= "Deny from all\n";
            $htaccessContent .= "# Permitir só acceso desde o servidor local\n";
            $htaccessContent .= "<FilesMatch \"^\\.\">\n";
            $htaccessContent .= "    Order allow,deny\n";
            $htaccessContent .= "    Deny from all\n";
            $htaccessContent .= "</FilesMatch>\n";

            // Só crear .htaccess se non existe xa (non sobreescribir)
            if (!file_exists($htaccessFile)) {
                file_put_contents($htaccessFile, $htaccessContent);
            }

            // --------------------------------------------------------
            // Crear o ficheiro de datos inicial con estrutura baleira
            // --------------------------------------------------------
            $initialData = [
                'admin' => [
                    'id' => bin2hex(random_bytes(12)),   // ID único aleatorio
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),  // Hash bcrypt
                    'created_at' => date('Y-m-d\TH:i:s\Z'),
                ],
                'feeds' => [],          // Lista de feeds (baleira ao inicio)
                'change_logs' => [],    // Rexistro de cambios (baleiro ao inicio)
            ];

            $json = json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($dataFile, $json)) {
                // Instalación exitosa
                $success = true;
                $installed = true;
            } else {
                $error = 'Non se puido escribir o ficheiro de datos. Verifica que PHP ten permisos de escritura no directorio "data/".';
            }
        }
    }
}

// ============================================================================
// COMPROBACIÓN DE REQUISITOS DO SERVIDOR
// ============================================================================

/**
 * Lista de requisitos necesarios para que a aplicación funcione.
 * Cada elemento é un array co formato: [nome, cumprido, valor_para_mostrar].
 * @var array
 */
$requirements = [];
$requirements[] = ['PHP 7.4+', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION];
$requirements[] = ['password_hash (bcrypt)', function_exists('password_hash'), function_exists('password_hash') ? 'Dispoñible' : 'Non dispoñible'];
$requirements[] = ['JSON', function_exists('json_encode'), function_exists('json_encode') ? 'Dispoñible' : 'Non dispoñible'];
$requirements[] = ['Escribir en data/', is_writable(__DIR__) || is_writable($dataDir), is_writable($dataDir) ? 'Si' : 'Non'];

// Verificar se todos os requisitos se cumpren
$allRequirementsMet = true;
foreach ($requirements as $req) {
    if (!$req[1]) $allRequirementsMet = false;
}
?>
<!DOCTYPE html>
<html lang="gl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rádio FilispiM — Instalación</title>
    <!-- Estilos inline para a páxina de instalación (non depende de style.css) -->
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #0a0a0a;
            color: #fafafa;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .install-container {
            width: 100%;
            max-width: 480px;
            background: #18181b;
            border: 1px solid #3f3f46;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .logo {
            width: 56px; height: 56px;
            background: #dc2626;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 20px rgba(220,38,38,0.5);
            font-size: 1.5rem;
        }
        h1 { text-align: center; font-size: 1.25rem; margin-bottom: 0.25rem; }
        .subtitle { text-align: center; color: #a1a1aa; font-size: 0.875rem; margin-bottom: 2rem; }

        .req-list { list-style: none; margin-bottom: 1.5rem; }
        .req-list li {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.5rem 0; border-bottom: 1px solid #27272a; font-size: 0.875rem;
        }
        .req-list li:last-child { border-bottom: none; }
        .req-ok { color: #22c55e; }
        .req-fail { color: #ef4444; font-weight: 600; }

        form { display: flex; flex-direction: column; gap: 1rem; }
        label { font-size: 0.875rem; color: #a1a1aa; display: block; margin-bottom: 0.25rem; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 0.625rem 0.75rem;
            background: #27272a; border: 1px solid #3f3f46;
            border-radius: 0.5rem; color: #fafafa; font-size: 0.875rem;
            outline: none; transition: border-color 0.2s;
        }
        input:focus { border-color: #dc2626; }
        input::placeholder { color: #52525b; }

        button[type="submit"] {
            width: 100%; padding: 0.75rem;
            background: #dc2626; color: white; border: none;
            border-radius: 0.5rem; font-size: 0.9375rem; font-weight: 600;
            cursor: pointer; transition: background 0.2s;
            box-shadow: 0 0 15px rgba(220,38,38,0.4);
        }
        button:hover { background: #ef4444; }

        .error {
            background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);
            color: #fca5a5; padding: 0.75rem; border-radius: 0.5rem;
            font-size: 0.875rem; margin-bottom: 1rem;
        }
        .success-box {
            background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);
            color: #86efac; padding: 1rem; border-radius: 0.5rem;
            font-size: 0.875rem; text-align: center;
        }
        .success-box a {
            color: #dc2626; text-decoration: none; font-weight: 600;
            display: inline-block; margin-top: 0.5rem;
        }
        .success-box a:hover { text-decoration: underline; }
        .warning { color: #fbbf24; font-size: 0.75rem; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <div class="install-container">
        <!-- Logo da Rádio FilispiM -->
        <div class="logo">📻</div>
        <h1>Rádio FilispiM</h1>
        <p class="subtitle">Instalación do Xestor de RSS</p>

        <?php if ($success): ?>
            <!-- ============================================================
                 Mensaxe de instalación completada con éxito
                 Recoméndase eliminar este ficheiro (install.php) por seguranza.
                 ============================================================ -->
            <div class="success-box">
                ✅ Instalación completada correctamente!
                <p style="margin-top:0.5rem; color:#a1a1aa;">
                    O usuario administrador foi creado.
                    Podes eliminar este ficheiro (install.php) por seguranza.
                </p>
                <a href="index.php">Ir ao Xestor de RSS →</a>
            </div>

        <?php elseif ($installed): ?>
            <!-- ============================================================
                 A aplicación xa está instalada — redirixir á páxina principal
                 ============================================================ -->
            <div class="success-box">
                ✅ A aplicación xa está instalada.
                <br><br>
                <a href="index.php">Ir ao Xestor de RSS →</a>
            </div>

        <?php else: ?>

            <!-- Mensaxe de erro (se hai) -->
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- ============================================================
                 Comprobación de requisitos do servidor
                 Mostra unha lista co estado de cada requisito
                 ============================================================ -->
            <h3 style="font-size:0.875rem; margin-bottom:0.75rem; color:#a1a1aa;">Requisitos do servidor:</h3>
            <ul class="req-list">
                <?php foreach ($requirements as $req): ?>
                    <li>
                        <span><?php echo htmlspecialchars($req[0]); ?></span>
                        <span class="<?php echo $req[1] ? 'req-ok' : 'req-fail'; ?>">
                            <?php echo $req[1] ? '✓ ' . $req[2] : '✗ Non dispoñible'; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($allRequirementsMet): ?>
                <!-- ============================================================
                     Formulario de creación do usuario administrador
                     Só se mostra se todos os requisitos se cumpren
                     ============================================================ -->
                <form method="POST" action="install.php">
                    <div>
                        <label for="username">Nome de usuario</label>
                        <input type="text" id="username" name="username"
                               placeholder="admin" value="admin" required
                               autocomplete="username">
                    </div>
                    <div>
                        <label for="password">Contrasinal</label>
                        <input type="password" id="password" name="password"
                               placeholder="Mínimo 6 caracteres" required minlength="6"
                               autocomplete="new-password">
                        <p class="warning">Usa un contrasinal forte e seguro.</p>
                    </div>
                    <div>
                        <label for="password2">Confirmar contrasinal</label>
                        <input type="password" id="password2" name="password2"
                               placeholder="Repite o contrasinal" required minlength="6"
                               autocomplete="new-password">
                    </div>
                    <button type="submit">Crear usuario e instalar</button>
                </form>
            <?php else: ?>
                <!-- Erro: algúns requisitos non se cumpren -->
                <div class="error">
                    Algun dos requisitos do servidor non se cumpren. Contacta co teu provedor de hosting.
                </div>
            <?php endif; ?>

            <!-- Créditos -->
            <p style="text-align:center; margin-top:1.5rem; font-size:0.75rem; color:#52525b;">
                Rádio FilispiM — A rádio libre e comunitaria da Terra de Trasancos
            </p>

        <?php endif; ?>
    </div>
</body>
</html>
