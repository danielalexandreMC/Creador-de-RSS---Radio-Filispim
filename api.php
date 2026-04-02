<?php
/**
 * ============================================================================
 * Rádio FilispiM — Xestor de RSS
 * Ficheiro: api.php
 * ============================================================================
 *
 * Descrición:
 *   Este ficheiro é a API principal da aplicación. Manexa TODAS as peticións
 *   AJAX que chegan dende o cliente JavaScript (app.js). Funciona como un
 *   enrutador de accións: recibe un parámetro "action" e executa a lógica
 *   correspondente.
 *
 * Métodos soportados:
 *   - GET: para consultas (listar feeds, sesión, exportar, previsualizar XML...)
 *   - POST: para operacións que modifican datos (crear, editar, eliminar...)
 *
 * Formato de resposta:
 *   Todas as respostas son en formato JSON con cabeceira
 *   "Content-Type: application/json; charset=utf-8".
 *   As respostas de éxito inclúen { "success": true, ... }
 *   As respostas de erro inclúen { "success": false, "error": "mensaxe" }
 *
 * Seguridade:
 *   - As accións que modifican datos requiren autenticación (sesión PHP)
 *   - Os contrasinais almacénanse con bcrypt (password_hash/PASSWORD_DEFAULT)
 *   - Utilízase bloqueo de ficheiros (flock) para evitar corrupción de datos
 *   - Toda a saída escápase correctamente para previr inxeccións
 *
 * Almacenamento:
 *   Os datos gárdanse en data/data.json (formato JSON).
 *   Non se usa base de datos, o que facilita a instalación en hosting compartido.
 *
 * @package Rádio FilispiM — Xestor de RSS
 * @version 1.0
 * @license Gratuíto para uso comunitario
 * ============================================================================
 */

// ============================================================================
// INICIO DE SESIÓN E CONFIGURACIÓN INICIAL
// ============================================================================

/** @var bool Iniciar a sesión PHP para xestionar a autenticación do administrador */
session_start();

/** @var string Cabeceira HTTP para indicar que a resposta é JSON en UTF-8 */
header('Content-Type: application/json; charset=utf-8');

// ============================================================================
// RUTAS DOS FICHEIROS DE DATOS
// ============================================================================

/**
 * Directorio base do proxecto.
 * Igual ao directorio onde se atopa este ficheiro api.php.
 * @var string
 */
$baseDir = __DIR__;

/**
 * Ruta ao ficheiro principal de datos (JSON).
 * Contén a información do administrador, feeds, entradas e rexistro de cambios.
 * @var string
 */
$dataFile = $baseDir . '/data/data.json';

/**
 * Ruta ao ficheiro de bloqueo para operacións de lectura/escritura.
 * Úsase con flock() para evitar condicións de carreira cando varios
 * procesos PHP intentan escribir no ficheiro de datos á vez.
 * @var string
 */
$lockFile = $baseDir . '/data/data.lock';

// ============================================================================
// COMPROBACIÓN DE INSTALACIÓN
// ============================================================================

/**
 * Se o ficheiro de datos non existe, significa que a aplicación aínda non
 * foi instalada. Nese caso, devolvemos un erro 503 (Servizo non dispoñible)
 * para que o cliente saiba que debe executar install.php primeiro.
 */
if (!file_exists($dataFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'A aplicación non está instalada. Executa install.php.']);
    exit;
}

// ============================================================================
// FUNCIONS AUXILIARES — LECTURA E ESCRITURA DE DATOS
// ============================================================================

/**
 * Ler datos do ficheiro JSON con bloqueo de ficheiros.
 *
 * Esta función abre o ficheiro de bloqueo, adquire un bloqueo compartido
 * (LOCK_SH — permítese lectura simultánea pero non escritura), le o
 * contido do ficheiro data.json, e libera o bloqueo.
 *
 * Estrutura dos datos devoltos:
 *   [
 *     'admin' => [            // Datos do usuario administrador
 *       'id' => string,       // Identificador único (hex)
 *       'username' => string, // Nome de usuario
 *       'password_hash' => string, // Hash bcrypt do contrasinal
 *       'created_at' => string,    // Data de creación (ISO 8601)
 *     ],
 *     'feeds' => [           // Lista de feeds RSS
 *       [
 *         'id' => string,        // Identificador único do feed
 *         'name' => string,      // Nome visible do feed
 *         'description' => string|null, // Descrición do feed
 *         'slug' => string,      // URL amigable única
 *         'created_at' => string, // Data de creación
 *         'updated_at' => string, // Data de última actualización
 *         'access_count' => int, // Número de accesos ao RSS
 *         'entries' => [        // Lista de entradas (episodios)
 *           [
 *             'id' => string,        // Identificador único
 *             'title' => string,      // Título do episodio
 *             'description' => string|null,
 *             'audio_url' => string, // URL directa ao audio
 *             'audio_type' => string, // MIME type (audio/mpeg, etc.)
 *             'pub_date' => string,  // Data de publicación
 *             'created_at' => string,
 *           ],
 *         ],
 *       ],
 *     ],
 *     'change_logs' => [     // Rexistro de cambios (últimos 500)
 *       [
 *         'id' => string,
 *         'action' => string,    // Tipo de acción (create_feed, add_entry...)
 *         'details' => string,   // Descrición detallada
 *         'feed_id' => string|null, // ID do feed afectado
 *         'created_at' => string,
 *       ],
 *     ],
 *   ]
 *
 * @return array Datos da aplicación ou array baleiro se hai erro
 */
function readData(): array {
    global $dataFile, $lockFile;

    // Crear o directorio data/ se non existe (por se acaso)
    $dataDir = dirname($dataFile);
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }

    // Crear o ficheiro de bloqueo se non existe
    if (!file_exists($lockFile)) {
        @touch($lockFile);
    }

    // Abrir o ficheiro de bloqueo en modo creación+lectura
    $fp = fopen($lockFile, 'c+');
    if (!$fp) return ['admin' => null, 'feeds' => [], 'change_logs' => []];

    // Bloqueo compartido: permite múltiples lecturas simultáneas
    flock($fp, LOCK_SH);

    // Ler o contido de data.json (ou cadea baleira se non existe)
    $content = file_exists($dataFile) ? file_get_contents($dataFile) : '';

    // Liberar o bloqueo compartido
    flock($fp, LOCK_UN);
    fclose($fp);

    // Descodificar o JSON
    $data = json_decode($content, true);

    // Se o JSON non é válido, devolver estrutura baleira
    if (!is_array($data)) {
        return ['admin' => null, 'feeds' => [], 'change_logs' => []];
    }

    // Asegurar que as chaves principais existen (compatibilidade cara atrás)
    if (!isset($data['feeds'])) $data['feeds'] = [];
    if (!isset($data['change_logs'])) $data['change_logs'] = [];

    return $data;
}

/**
 * Escribir datos no ficheiro JSON con bloqueo exclusivo.
 *
 * Adquire un bloqueo exclusivo (LOCK_EX — só un escritor á vez) antes de
 * escribir. Isto evita que dous procesos sobreescriban o ficheiro
 * simultaneamente e corrompan os datos.
 *
 * O ficheiro gárdase con formato bonito (JSON_PRETTY_PRINT) e caracteres
 * Unicode sen escapar (JSON_UNESCAPED_UNICODE) para que sexa lexible
 * directamente se se abre cun editor de texto.
 *
 * @param array $data Datos completos da aplicación para gardar
 * @return bool true se se escribiu correctamente, false en caso de erro
 */
function writeData(array $data): bool {
    global $dataFile, $lockFile;

    // Abrir o ficheiro de bloqueo
    $fp = fopen($lockFile, 'c+');
    if (!$fp) return false;

    // Bloqueo exclusivo: bloquea ata que outros procesos liberen o seu bloqueo
    flock($fp, LOCK_EX);

    // Escribir os datos en JSON con formato lexible
    $result = file_put_contents(
        $dataFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX // Bloqueo adicional a nivel de file_put_contents
    );

    // Liberar o bloqueo e pechar
    flock($fp, LOCK_UN);
    fclose($fp);

    return $result !== false;
}

// ============================================================================
// FUNCIONS AUXILIARES — XERACIÓN DE IDS E SLUGS
// ============================================================================

/**
 * Xerar un identificador único aleatorio en formato hexadecimal.
 *
 * Usa random_bytes(12) que xera 12 bytes criptográficamente seguros,
 * convertidos a 24 caracteres hexadecimais. Esto é suficiente para
 * evitar colisións mesmo con miles de rexistros.
 *
 * @return string ID único de 24 caracteres hexadecimais (ex: "a1b2c3d4e5f6...")
 */
function generateId(): string {
    return bin2hex(random_bytes(12));
}

/**
 * Xerar un slug (URL amigable) a partires do nome do feed.
 *
 * O slug é a versión do nome adaptada para usar en URLs:
 *   - Converte a minúsculas
 *   - Elimina acentos e caracteres especiais (usa Intl transliterator cando está dispoñible)
 *   - Substitúe espazos por guións
 *   - Elimina caracteres non alfanuméricos
 *   - Garantiza unicidade engadindo un sufixo numérico se é necesario
 *
 * Exemplos:
 *   "O meu Podcast Galego" → "o-meu-podcast-galego"
 *   "Programa nº 1" → "programa-1"
 *
 * @param string $name Nome do feed para xerar o slug
 * @param array $existingFeeds Lista de feeds existentes para verificar unicidade
 * @return string Slug único e limpo
 */
function generateSlug(string $name, array $existingFeeds = []): string {
    // Converter a minúsculas
    $slug = strtolower($name);

    // Usar o transliterator de PHP (Intl) se está dispoñible para quitar acentos
    if (function_exists('transliterator_transliterate')) {
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $slug);
    }

    // Fallback se o transliterator falla
    if ($slug === false || empty($slug)) {
        $slug = strtolower($name);
    }

    // Eliminar caracteres non alfanuméricos (excepto espazos e guións)
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = trim($slug);

    // Substituír espazos múltiples por un único guión
    $slug = preg_replace('/\s+/', '-', $slug);

    // Eliminar guións múltiples consecutivos
    $slug = preg_replace('/-+/', '-', $slug);

    // Eliminar guións ao inicio e ao final
    $slug = trim($slug, '-');

    // Se despois de todo o slug está baleiro, xerar un aleatorio
    if (empty($slug)) {
        $slug = 'feed-' . generateId();
    }

    // Verificar unicidade contra os slugs existentes
    $existingSlugs = array_column($existingFeeds, 'slug');
    if (in_array($slug, $existingSlugs)) {
        $counter = 1;
        while (in_array($slug . '-' . $counter, $existingSlugs)) {
            $counter++;
        }
        $slug = $slug . '-' . $counter;
    }

    return $slug;
}

// ============================================================================
// FUNCIONS AUXILIARES — DETECCIÓN DE AUDIO E REXISTRO DE CAMBIOS
// ============================================================================

/**
 * Detectar o tipo MIME do audio pola extensión da URL.
 *
 * Analiza a extensión do ficheiro na URL para determinar o tipo MIME
 * correcto, necesario para a etiqueta <enclosure> do RSS. Se non se
 * pode determinar, asume MP3 como valor por defecto.
 *
 * Tipos soportados:
 *   - .ogg → audio/ogg
 *   - .wav → audio/wav
 *   - .flac → audio/flac
 *   - .m4a → audio/mp4
 *   - .mp3 (ou calquera outro) → audio/mpeg
 *
 * @param string $url URL do ficheiro de audio
 * @return string Tipo MIME do audio
 */
function detectAudioType(string $url): string {
    $lower = strtolower($url);
    // Comprobar cada extensión coa rexresión que acepta ? como separador de query
    if (preg_match('/\.ogg(\?|$)/i', $url)) return 'audio/ogg';
    if (preg_match('/\.wav(\?|$)/i', $url)) return 'audio/wav';
    if (preg_match('/\.flac(\?|$)/i', $url)) return 'audio/flac';
    if (preg_match('/\.m4a(\?|$)/i', $url)) return 'audio/mp4';
    if (preg_match('/\.mp3(\?|$)/i', $url)) return 'audio/mpeg';
    // Por defecto, asumir MP3 (o formato máis común en podcasts)
    return 'audio/mpeg';
}

/**
 * Engadir unha entrada ao historial de cambios (changelog).
 *
 * Cada acción que modifica datos (crear feed, engadir entrada, etc.)
 * rexístrase aquí. O rexistro é limitado aos últimos 500 cambios para
 * evitar que o ficheiro de datos creza indefinidamente.
 *
 * @param array &$data Referencia ao array de datos da aplicación
 * @param string $action Tipo de acción (ex: 'create_feed', 'add_entry', 'edit_entry')
 * @param string $details Descrición humana da acción realizada
 * @param string|null $feedId ID do feed afectado, ou null se é xeral
 * @return void
 */
function addChangeLog(array &$data, string $action, string $details, ?string $feedId = null): void {
    $data['change_logs'][] = [
        'id' => generateId(),
        'action' => $action,
        'details' => $details,
        'feed_id' => $feedId,
        'created_at' => date('Y-m-d\TH:i:s\Z'),
    ];

    // Manter só os últimos 500 cambios para controlar o tamaño do ficheiro
    if (count($data['change_logs']) > 500) {
        $data['change_logs'] = array_slice($data['change_logs'], -500);
    }
}

// ============================================================================
// FUNCIONS AUXILIARES — AUTENTICACIÓN E RESPOSTAS
// ============================================================================

/**
 * Verificar se o usuario actual está autenticado como administrador.
 *
 * Comproba se existe a variable de sesión 'filispim_admin_id', que se
 * establece ao iniciar sesión correctamente. Non verifica o contrasinal
 * de novo (a sesión xa é válida).
 *
 * @return bool true se o usuario está autenticado, false en caso contrario
 */
function isAuthenticated(): bool {
    return isset($_SESSION['filispim_admin_id']);
}

/**
 * Enviar unha resposta JSON de éxito ao cliente.
 *
 * @param mixed $data Datos a enviar (array, string, etc.)
 * @param int $code Código HTTP de resposta (por defecto 200)
 * @return void (termina a execución con exit)
 */
function jsonResponse($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Enviar unha resposta JSON de erro ao cliente.
 *
 * @param string $message Mensaxe de erro descritiva (en galego)
 * @param int $code Código HTTP de erro (por defecto 400)
 * @return void (termina a execución con exit)
 */
function errorResponse(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// DETERMINACIÓN DA ACCIÓN SOLICITADA
// ============================================================================

/**
 * A acción determina que operación realizar. Pode vir por GET (query string)
 * ou por POST (no corpo da petición en formato JSON ou form-data).
 * @var string
 */
$action = $_GET['action'] ?? '';

// Se a petición é POST, extraer a acción do corpo da petición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tentar descodificar como JSON primeiro (o cliente envía JSON)
    $input = json_decode(file_get_contents('php://input'), true);
    // Se non é JSON válido, tentar con datos de formulario
    if (!$input) $input = $_POST;
    $action = $input['action'] ?? '';
}

// ============================================================================
// ENROUTADOR DE ACCIÓNS DA API
// ============================================================================

switch ($action) {

    // ========================================================================
    // SECCIÓN: SESIÓN — Xestión da autenticación do administrador
    // ========================================================================

    /**
     * Acción: session
     * Método: GET
     * Descrición: Comproba se o usuario actual ten unha sesión activa.
     * Parámetros: ningún
     * Resposta: { success: true/false, authenticated: true/false, username: "..." }
     * Uso: O cliente chama esta función ao cargar a páxina para saber
     *      se debe mostrar os controles de administración.
     */
    case 'session':
        if (isAuthenticated()) {
            $adminId = $_SESSION['filispim_admin_id'];
            $username = $_SESSION['filispim_admin_username'] ?? 'admin';
            jsonResponse([
                'success' => true,
                'authenticated' => true,
                'username' => $username,
                'user' => ['id' => $adminId, 'username' => $username]
            ]);
        } else {
            jsonResponse(['success' => false, 'authenticated' => false]);
        }
        break;

    /**
     * Acción: login
     * Método: POST
     * Descrición: Inicia sesión coas credenciais proporcionadas.
     * Parámetros:
     *   - username (string, obrigatorio): Nome de usuario do administrador
     *   - password (string, obrigatorio): Contrasinal en texto plano
     * Resposta: { success: true, username: "..." }
     * Errores: 401 se as credenciais son incorrectas
     * Nota de seguranza: O contrasinal verifica con password_verify() contra
     *       o hash bcrypt almacenado. Non se almacena nunca o contrasinal en claro.
     */
    case 'login':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        // Validación: ambos campos obrigatorios
        if (empty($username) || empty($password)) {
            errorResponse('Requírese usuario e contrasinal');
        }

        $data = readData();

        // Verificar que existe un administrador configurado
        if (!$data['admin']) {
            errorResponse('Credenciais incorrectas', 401);
        }

        // Verificar que o nome de usuario coincide (comparación exacta)
        if ($data['admin']['username'] !== $username) {
            errorResponse('Credenciais incorrectas', 401);
        }

        // Verificar o contrasinal con bcrypt (tempo constante para previr timing attacks)
        if (!password_verify($password, $data['admin']['password_hash'])) {
            errorResponse('Credenciais incorrectas', 401);
        }

        // Establecer as variables de sesión
        $_SESSION['filispim_admin_id'] = $data['admin']['id'];
        $_SESSION['filispim_admin_username'] = $data['admin']['username'];
        $_SESSION['filispim_last_activity'] = time();

        jsonResponse([
            'success' => true,
            'username' => $data['admin']['username'],
            'user' => ['id' => $data['admin']['id'], 'username' => $data['admin']['username']]
        ]);
        break;

    /**
     * Acción: logout
     * Método: POST
     * Descrición: Pecha a sesión do administrador.
     * Parámetros: ningún
     * Resposta: { success: true }
     * Seguridade: Destruye todas as variables de sesión.
     */
    case 'logout':
        session_unset();
        session_destroy();
        jsonResponse(['success' => true]);
        break;

    /**
     * Acción: reset_password
     * Método: POST
     * Descrición: Cambia o contrasinal do administrador autenticado.
     * Parámetros:
     *   - currentPassword (string, obrigatorio): Contrasinal actual
     *   - newPassword (string, obrigatorio, mín. 6 caracteres): Novo contrasinal
     * Resposta: { success: true, message: "..." }
     * Requiere: Sesión activa de administrador
     */
    case 'reset_password':
        if (!isAuthenticated()) {
            errorResponse('Non autorizado', 401);
        }

        $currentPassword = $input['currentPassword'] ?? '';
        $newPassword = $input['newPassword'] ?? '';

        // Validacións
        if (empty($currentPassword) || empty($newPassword)) {
            errorResponse('Ambos contrasinais son obrigatorios');
        }
        if (strlen($newPassword) < 6) {
            errorResponse('O novo contrasinal debe ter al menos 6 caracteres');
        }

        $data = readData();

        // Verificar o contrasinal actual
        if (!password_verify($currentPassword, $data['admin']['password_hash'])) {
            errorResponse('O contrasinal actual é incorrecto');
        }

        // Xerar novo hash e gardar
        $data['admin']['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        writeData($data);

        jsonResponse(['success' => true, 'message' => 'Contrasinal actualizado correctamente']);
        break;

    // ========================================================================
    // SECCIÓN: FEEDS — Xestión de feeds RSS (crear, editar, eliminar, listar)
    // ========================================================================

    /**
     * Acción: feeds
     * Método: GET
     * Descrición: Obtén a lista de feeds con filtrado temporal opcional.
     * Parámetros:
     *   - filter (string, opcional): Filtro temporal.
     *     Valores: "7" (últimos 7 días), "15" (+15 días), "30" (+30 días), "all" (todos)
     *     Por defecto: "7"
     * Resposta: { success: true, feeds: [...] }
     * Orde: Os feeds ordénanse por updated_at descendente (máis recentes primeiro).
     */
    case 'feeds':
        $filter = $_GET['filter'] ?? '7';
        $data = readData();
        $feeds = $data['feeds'] ?? [];

        // Aplicar filtro temporal se non é "all"
        if ($filter !== 'all') {
            $days = intval($filter);
            $cutoff = strtotime("-{$days} days");
            // Filtrar feeds actualizados dentro do período indicado
            $feeds = array_filter($feeds, function ($feed) use ($cutoff) {
                return strtotime($feed['updated_at']) >= $cutoff;
            });
            $feeds = array_values($feeds); // Reindexar o array
        }

        // Ordenar por data de actualización descendente (máis recente primeiro)
        usort($feeds, function ($a, $b) {
            return strtotime($b['updated_at']) - strtotime($a['updated_at']);
        });

        jsonResponse(['success' => true, 'feeds' => array_values($feeds)]);
        break;

    /**
     * Acción: create_feed
     * Método: POST
     * Descrición: Crea un novo feed RSS.
     * Parámetros:
     *   - name (string, obrigatorio): Nome do feed (programa/podcast)
     *   - description (string, opcional): Descrición do contido
     * Resposta: { success: true, id, name, description, slug, ... }
     * Código: 201 (Created) en caso de éxito
     * Requiere: Sesión activa de administrador
     * Nota: O slug xerase automáticamente a partires do nome.
     */
    case 'create_feed':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');

        // Validación: o nome é obrigatorio
        if (empty($name)) {
            errorResponse('O nome do feed é obrigatorio');
        }

        $data = readData();

        // Xerar un slug único baseado no nome
        $slug = generateSlug($name, $data['feeds']);

        // Crear a estrutura do novo feed
        $feed = [
            'id' => generateId(),
            'name' => $name,
            'description' => $description ?: null,
            'slug' => $slug,
            'created_at' => date('Y-m-d\TH:i:s\Z'),
            'updated_at' => date('Y-m-d\TH:i:s\Z'),
            'access_count' => 0,
            'entries' => [],
        ];

        // Engadir o feed á lista de feeds
        $data['feeds'][] = $feed;

        // Rexistrar a creación no historial de cambios
        addChangeLog($data, 'create_feed', 'Feed "' . $name . '" creado', $feed['id']);

        // Gardar os datos no ficheiro JSON
        writeData($data);

        // Responder co feed creado e código 201
        $feed['success'] = true;
        jsonResponse($feed, 201);
        break;

    /**
     * Acción: edit_feed
     * Método: POST
     * Descrición: Edita os datos dun feed existente (nome e descrición).
     * Parámetros:
     *   - id (string, obrigatorio): ID do feed a editar
     *   - name (string, obrigatorio): Novo nome do feed
     *   - description (string, opcional): Nova descrición
     * Resposta: { success: true, message: "Feed actualizado" }
     * Errores: 404 se o feed non existe
     * Requiere: Sesión activa de administrador
     */
    case 'edit_feed':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $id = $input['id'] ?? '';
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');

        if (empty($id) || empty($name)) {
            errorResponse('O ID e o nome son obrigatorios');
        }

        $data = readData();
        $found = false;

        // Buscar o feed polo ID e actualizar os campos
        foreach ($data['feeds'] as &$feed) {
            if ($feed['id'] === $id) {
                $oldName = $feed['name'];
                $feed['name'] = $name;
                $feed['description'] = $description ?: null;
                $feed['updated_at'] = date('Y-m-d\TH:i:s\Z');
                $found = true;
                addChangeLog($data, 'edit_feed', 'Feed "' . $oldName . '" editado', $id);
                break;
            }
        }
        unset($feed); // Romper a referencia (boa práctica en PHP)

        if (!$found) {
            errorResponse('Feed non atopado', 404);
        }

        writeData($data);
        jsonResponse(['success' => true, 'message' => 'Feed actualizado']);
        break;

    /**
     * Acción: delete_feed
     * Método: POST
     * Descrición: Elimina un feed e todas as súas entradas permanentemente.
     * Parámetros:
     *   - id (string, obrigatorio): ID do feed a eliminar
     * Resposta: { success: true, message: "Feed eliminado" }
     * Errores: 404 se o feed non existe
     * Requiere: Sesión activa de administrador
     * Aviso: Esta acción non se pode desfacer. Elimínase todo o feed e
     *         tódalas súas entradas.
     */
    case 'delete_feed':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $id = $input['id'] ?? '';
        if (empty($id)) errorResponse('O ID é obrigatorio');

        $data = readData();
        $found = false;
        $feedName = '';

        // Buscar o feed e eliminalo do array
        foreach ($data['feeds'] as $i => $feed) {
            if ($feed['id'] === $id) {
                $feedName = $feed['name'];
                array_splice($data['feeds'], $i, 1);
                $found = true;
                // Rexistrar no changelog cantas entradas tiña
                addChangeLog($data, 'delete_feed', 'Feed "' . $feedName . '" eliminado (' . count($feed['entries']) . ' entradas)', null);
                break;
            }
        }

        if (!$found) errorResponse('Feed non atopado', 404);

        writeData($data);
        jsonResponse(['success' => true, 'message' => 'Feed eliminado']);
        break;

    // ========================================================================
    // SECCIÓN: ENTRADAS — Xestión de episodios dentro dos feeds
    // ========================================================================

    /**
     * Acción: add_entry
     * Método: POST
     * Descrición: Engade unha nova entrada (episodio) a un feed.
     * Parámetros:
     *   - feedId (string, obrigatorio): ID do feed onde engadir
     *   - title (string, obrigatorio): Título do episodio
     *   - description (string, opcional): Descrición do episodio
     *   - audioUrl (string, obrigatorio): URL directa ao ficheiro de audio
     * Resposta: { success: true, message: "Entrada engadida" }
     * Errores: 400 se faltan datos, 404 se o feed non existe
     * Requiere: Sesión activa de administrador
     * Nota: A nova entrada engádese ao principio da lista (máis recente primeiro).
     *       O tipo MIME do audio detectase automaticamente pola extensión da URL.
     */
    case 'add_entry':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $feedId = $input['feedId'] ?? '';
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $audioUrl = trim($input['audioUrl'] ?? '');

        // Validacións
        if (empty($feedId) || empty($title) || empty($audioUrl)) {
            errorResponse('O título e a URL do audio son obrigatorios');
        }

        // Validar que a URL do audio é unha URL válida
        if (!filter_var($audioUrl, FILTER_VALIDATE_URL)) {
            errorResponse('A URL do audio non é válida');
        }

        $data = readData();
        $found = false;
        $feedName = '';

        // Buscar o feed e engadir a entrada
        foreach ($data['feeds'] as &$feed) {
            if ($feed['id'] === $feedId) {
                $feedName = $feed['name'];
                $entry = [
                    'id' => generateId(),
                    'title' => $title,
                    'description' => $description ?: null,
                    'audio_url' => $audioUrl,
                    'audio_type' => detectAudioType($audioUrl), // Detectar MIME automaticamente
                    'pub_date' => date('Y-m-d\TH:i:s\Z'),
                    'created_at' => date('Y-m-d\TH:i:s\Z'),
                ];
                // Engadir ao principio (máis recente primeiro)
                array_unshift($feed['entries'], $entry);
                $feed['updated_at'] = date('Y-m-d\TH:i:s\Z');
                $found = true;
                addChangeLog($data, 'add_entry', 'Entrada "' . $title . '" engadida ao feed "' . $feedName . '"', $feedId);
                break;
            }
        }
        unset($feed);

        if (!$found) errorResponse('Feed non atopado', 404);

        writeData($data);
        jsonResponse(['success' => true, 'message' => 'Entrada engadida']);
        break;

    /**
     * Acción: delete_entry
     * Método: POST
     * Descrición: Elimina unha entrada dun feed.
     * Parámetros:
     *   - feedId (string, obrigatorio): ID do feed
     *   - entryId (string, obrigatorio): ID da entrada a eliminar
     * Resposta: { success: true, message: "Entrada eliminada" }
     * Errores: 404 se non se atopan
     * Requiere: Sesión activa de administrador
     */
    case 'delete_entry':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $feedId = $input['feedId'] ?? '';
        $entryId = $input['entryId'] ?? '';

        if (empty($feedId) || empty($entryId)) {
            errorResponse('Os IDs son obrigatorios');
        }

        $data = readData();
        $found = false;
        $entryTitle = '';

        // Buscar a entrada dentro do feed e eliminala
        foreach ($data['feeds'] as &$feed) {
            if ($feed['id'] === $feedId) {
                foreach ($feed['entries'] as $i => $entry) {
                    if ($entry['id'] === $entryId) {
                        $entryTitle = $entry['title'];
                        array_splice($feed['entries'], $i, 1);
                        $feed['updated_at'] = date('Y-m-d\TH:i:s\Z');
                        $found = true;
                        addChangeLog($data, 'delete_entry', 'Entrada "' . $entryTitle . '" eliminada do feed "' . $feed['name'] . '"', $feedId);
                        break;
                    }
                }
                break;
            }
        }
        unset($feed);

        if (!$found) errorResponse('Entrada non atopada', 404);

        writeData($data);
        jsonResponse(['success' => true, 'message' => 'Entrada eliminada']);
        break;

    /**
     * Acción: edit_entry
     * Método: POST
     * Descrición: Edita unha entrada existente (título, descrición e/ou URL de audio).
     * Parámetros:
     *   - feedId (string, obrigatorio): ID do feed
     *   - entryId (string, obrigatorio): ID da entrada a editar
     *   - title (string, obrigatorio): Novo título do episodio
     *   - description (string, opcional): Nova descrición
     *   - audioUrl (string, opcional): Nova URL de audio (se se deixa baleiro, mantén a actual)
     * Resposta: { success: true, message: "Entrada actualizada" }
     * Errores: 404 se non se atopa, 400 se a URL non é válida
     * Requiere: Sesión activa de administrador
     * Nota: Se a URL de audio se proporciona, o tipo MIME redetectase automaticamente.
     */
    case 'edit_entry':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $feedId = $input['feedId'] ?? '';
        $entryId = $input['entryId'] ?? '';
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $audioUrl = trim($input['audioUrl'] ?? '');

        // Validacións
        if (empty($feedId) || empty($entryId) || empty($title)) {
            errorResponse('O ID e o título son obrigatorios');
        }

        // Validar a URL só se se proporcionou (non é obrigatorio cambiar)
        if (!empty($audioUrl) && !filter_var($audioUrl, FILTER_VALIDATE_URL)) {
            errorResponse('A URL do audio non é válida');
        }

        $data = readData();
        $found = false;

        // Buscar o feed e a entrada, e actualizar os campos
        foreach ($data['feeds'] as &$feed) {
            if ($feed['id'] === $feedId) {
                foreach ($feed['entries'] as &$entry) {
                    if ($entry['id'] === $entryId) {
                        $oldTitle = $entry['title'];
                        $entry['title'] = $title;
                        $entry['description'] = $description ?: null;
                        // Actualizar a URL de audio só se se proporcionou unha nova
                        if (!empty($audioUrl)) {
                            $entry['audio_url'] = $audioUrl;
                            $entry['audio_type'] = detectAudioType($audioUrl); // Redetectar tipo MIME
                        }
                        $feed['updated_at'] = date('Y-m-d\TH:i:s\Z');
                        $found = true;
                        addChangeLog($data, 'edit_entry', 'Entrada "' . $oldTitle . '" editada no feed "' . $feed['name'] . '"', $feedId);
                        break;
                    }
                }
                unset($entry); // Romper a referencia interna
                break;
            }
        }
        unset($feed); // Romper a referencia externa

        if (!$found) errorResponse('Entrada non atopada', 404);

        writeData($data);
        jsonResponse(['success' => true, 'message' => 'Entrada actualizada']);
        break;

    // ========================================================================
    // SECCIÓN: VISTA PREVIA XML — Xeración e previsualización do RSS
    // ========================================================================

    /**
     * Acción: xml_preview
     * Método: GET
     * Descrición: Xera o XML RSS dun feed e devolveo como cadea de texto
     *            para a súa previsualización na interface.
     * Parámetros:
     *   - feedId (string, obrigatorio): ID do feed
     * Resposta: { success: true, xml: "<?xml ...>" }
     * Errores: 404 se o feed non existe
     * Requiere: Sesión activa de administrador
     */
    case 'xml_preview':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $feedId = $_GET['feedId'] ?? '';
        if (empty($feedId)) errorResponse('O ID do feed é obrigatorio');

        $data = readData();
        $xml = '';

        // Buscar o feed e xerar o XML
        foreach ($data['feeds'] as $feed) {
            if ($feed['id'] === $feedId) {
                $xml = generateRSSXml($feed);
                break;
            }
        }

        if (empty($xml)) errorResponse('Feed non atopado', 404);

        jsonResponse(['success' => true, 'xml' => $xml]);
        break;

    // ========================================================================
    // SECCIÓN: HISTORIAL DE CAMBIOS — Rexistro de auditoría
    // ========================================================================

    /**
     * Acción: changelog
     * Método: GET
     * Descrición: Obtén o rexistro de cambios (historial de accións).
     * Parámetros:
     *   - feedId (string, opcional): Se se proporciona, filtra só os cambios
     *     dese feed. Se se omite, devolve todos os cambios.
     * Resposta: { success: true, changelog: [...] }
     * Límite: Móstranse como máximo 100 cambios, ordenados por data descendente.
     * Requiere: Sesión activa de administrador
     */
    case 'changelog':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $feedId = $_GET['feedId'] ?? '';
        $data = readData();
        $logs = $data['change_logs'] ?? [];

        // Filtrar por feed se se especificou
        if (!empty($feedId)) {
            $logs = array_filter($logs, function ($log) use ($feedId) {
                return $log['feed_id'] === $feedId;
            });
            $logs = array_values($logs);
        }

        // Ordenar por data de creación descendente (máis recente primeiro)
        usort($logs, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Limitar a 100 resultados para non sobrecargar a interface
        $logs = array_slice($logs, 0, 100);

        jsonResponse(['success' => true, 'changelog' => array_values($logs)]);
        break;

    // ========================================================================
    // SECCIÓN: EXPORTACIÓN — Copia de seguranza dos datos
    // ========================================================================

    /**
     * Acción: export
     * Método: GET
     * Descrición: Exporta todos os datos da aplicación en formato JSON.
     *            NON inclúe o contrasinal do administrador por seguranza.
     * Parámetros: ningún
     * Resposta: { success: true, data: { exported_at, app, feeds, change_logs } }
     * Requiere: Sesión activa de administrador
     * Nota de seguranza: O hash do contrasinal NON se exporta. Ao importar,
     *       mantiñense as credenciais existentes no servidor de destino.
     */
    case 'export':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $data = readData();

        // Preparar datos para exportación (SEN o contrasinal do admin por seguranza)
        $exportData = [
            'exported_at' => date('Y-m-d\TH:i:s\Z'),
            'app' => 'Rádio FilispiM RSS',
            'feeds' => $data['feeds'] ?? [],
            'change_logs' => $data['change_logs'] ?? [],
        ];
        jsonResponse(['success' => true, 'data' => $exportData]);
        break;

    // ========================================================================
    // SECCIÓN: IMPORTACIÓN — Restauración de datos desde copia de seguranza
    // ========================================================================

    /**
     * Acción: import
     * Método: POST
     * Descrición: Importa datos dun ficheiro JSON de copia de seguranza.
     * Parámetros:
     *   - data (array/object, obrigatorio): Datos a importar coa mesma estrutura
     *     que a exportación (debe conter un array "feeds").
     * Resposta: { success: true, feeds: N, entries: N, message: "..." }
     * Comportamento:
     *   - Se un feed xa existe (coincide o slug), as entradas novas engádense
     *     ás existentes (non se duplican).
     *   - Se un feed non existe, créase como novo.
     *   - As credenciais do administrador NON se sobreescriben nunca.
     * Requiere: Sesión activa de administrador
     */
    case 'import':
        if (!isAuthenticated()) errorResponse('Non autorizado', 401);

        $importData = $input['data'] ?? ($input ?? null);
        if (!is_array($importData)) {
            errorResponse('Os datos de importación non son válidos');
        }

        $data = readData();
        $importedFeeds = 0;
        $importedEntries = 0;

        // Procesar cada feed do ficheiro de importación
        if (isset($importData['feeds']) && is_array($importData['feeds'])) {
            foreach ($importData['feeds'] as $feed) {
                if (empty($feed['name'])) continue;

                // Determinar o slug do feed a importar
                $existingIndex = null;
                $slug = $feed['slug'] ?? generateSlug($feed['name'], $data['feeds']);

                // Verificar se xa existe un feed co mesmo slug
                foreach ($data['feeds'] as $i => $existing) {
                    if ($existing['slug'] === $slug) {
                        $existingIndex = $i;
                        break;
                    }
                }

                // Separar as entradas do feed (para procesalas separadamente)
                $newEntries = $feed['entries'] ?? [];
                unset($feed['entries']);

                if ($existingIndex !== null) {
                    // Feed existente: engadir só as entradas que non existan (por ID)
                    $existingEntryIds = array_column($data['feeds'][$existingIndex]['entries'], 'id');
                    foreach ($newEntries as $entry) {
                        if (!in_array($entry['id'], $existingEntryIds)) {
                            $data['feeds'][$existingIndex]['entries'][] = $entry;
                            $importedEntries++;
                        }
                    }
                    $data['feeds'][$existingIndex]['updated_at'] = date('Y-m-d\TH:i:s\Z');
                } else {
                    // Feed novo: crear con novo ID e engadir todas as súas entradas
                    $newFeed = [
                        'id' => generateId(),
                        'name' => $feed['name'],
                        'description' => $feed['description'] ?? null,
                        'slug' => $slug,
                        'created_at' => date('Y-m-d\TH:i:s\Z'),
                        'updated_at' => date('Y-m-d\TH:i:s\Z'),
                        'access_count' => 0,
                        'entries' => $newEntries,
                    ];
                    $data['feeds'][] = $newFeed;
                    $importedFeeds++;
                    $importedEntries += count($newEntries);
                }
            }
        }

        // Rexistrar a importación no historial de cambios
        addChangeLog($data, 'import', "Importación: $importedFeeds feeds, $importedEntries entradas", null);
        writeData($data);

        jsonResponse([
            'success' => true,
            'message' => "Importados $importedFeeds feeds novos e $importedEntries entradas",
            'feeds' => $importedFeeds,
            'entries' => $importedEntries,
            'imported_feeds' => $importedFeeds,
            'imported_entries' => $importedEntries,
        ]);
        break;

    // ========================================================================
    // ACCIÓN POR DEFECTO — Se non se recoñece a acción solicitada
    // ========================================================================
    default:
        errorResponse('Acción non recoñecida', 400);
        break;
}

// ============================================================================
// FUNCION DE XERACIÓN DE XML RSS 2.0
// ============================================================================

/**
 * Xera unha cadea XML RSS 2.0 válido a partires dos datos dun feed.
 *
 * O XML xerado cumpre a especificación RSS 2.0 e inclúe:
 *   - Namespace Atom para a auto-referencia (<atom:link>)
 *   - Etiqueta <language> configurada como "gl" (galego)
 *   - Etiqueta <lastBuildDate> coa data de última actualización
 *   - Tags <enclosure> con tipo MIME para cada entrada (necesario para podcasts)
 *   - Tags <guid> únicos para cada entrada
 *
 * @param array $feed Datos completos do feed (con entradas incluídas)
 * @return string XML RSS 2.0 como cadea de texto
 */
function generateRSSXml(array $feed): string {
    // Calcular a URL base do servidor (funciona en calquera subdirectorio)
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $scriptDir;
    $rssUrl = $baseUrl . '/rss.php?feed=' . urlencode($feed['slug']);

    // Construír o XML de forma segura (escapando todos os valores)
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    $xml .= '  <channel>' . "\n";
    $xml .= '    <title>' . xmlEscape($feed['name']) . "</title>\n";
    $xml .= '    <description>' . xmlEscape($feed['description'] ?? '') . "</description>\n";
    $xml .= '    <link>' . xmlEscape($baseUrl) . "</link>\n";
    $xml .= '    <atom:link href="' . xmlEscape($rssUrl) . '" rel="self" type="application/rss+xml"/>' . "\n";
    $xml .= '    <language>gl</language>' . "\n";
    $xml .= '    <lastBuildDate>' . date('r', strtotime($feed['updated_at'])) . "</lastBuildDate>\n";

    // Ordenar as entradas por data de publicación descendente
    $entries = $feed['entries'] ?? [];
    usort($entries, function ($a, $b) {
        return strtotime($b['pub_date']) - strtotime($a['pub_date']);
    });

    // Xerar un <item> por cada entrada/episodio
    foreach ($entries as $entry) {
        $xml .= '    <item>' . "\n";
        $xml .= '      <title>' . xmlEscape($entry['title']) . "</title>\n";
        $xml .= '      <description>' . xmlEscape($entry['description'] ?? '') . "</description>\n";
        // A etiqueta enclosure é esencial para podcasts (indica o ficheiro de audio)
        $xml .= '      <enclosure url="' . xmlEscape($entry['audio_url']) . '" length="0" type="' . xmlEscape($entry['audio_type'] ?? 'audio/mpeg') . '"/>' . "\n";
        $xml .= '      <pubDate>' . date('r', strtotime($entry['pub_date'])) . "</pubDate>\n";
        $xml .= '      <guid>' . xmlEscape($entry['id']) . "</guid>\n";
        $xml .= '    </item>' . "\n";
    }

    $xml .= '  </channel>' . "\n";
    $xml .= '</rss>';

    return $xml;
}

// ============================================================================
// FUNCION DE ESCAPE PARA XML
// ============================================================================

/**
 * Escapa caracteres especiais para incluír de forma segura en XML.
 *
 * Converte os caracteres: & → &amp;  < → &lt;  > → &gt;  " → &quot;  ' → &#039;
 * Utiliza as banderas ENT_XML1 e ENT_QUOTES para un escape completo.
 *
 * @param string $str Texto a escapar
 * @return string Texto escapado seguro para XML
 */
function xmlEscape(string $str): string {
    return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
