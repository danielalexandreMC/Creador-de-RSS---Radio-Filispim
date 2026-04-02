<?php
/**
 * ============================================================================
 * Rádio FilispiM — Xestor de RSS
 * Ficheiro: rss.php
 * ============================================================================
 *
 * Descrición:
 *   Endpoint público que xera e serve feeds RSS 2.0 en formato XML.
 *   Este é o ficheiro ao que acceden os lectores de podcasts e
 *   agregadores RSS para obter os episodios de cada programa.
 *
 * URL de acceso:
 *   rss.php?feed=slug-do-feed
 *
 *   Exemplo: rss.php?feed=o-meu-podcast-galego
 *
 * Fluxo de funcionamento:
 *   1. Verificar que a aplicación está instalada
 *   2. Verificar que se proporcionou un parámetro "feed"
 *   3. Buscar o feed polo slug nos datos
 *   4. Incrementar o contador de accesos (con bloqueo de escritura)
 *   5. Xerar o XML RSS 2.0 con todas as entradas
 *   6. Enviar o XML con cabeceiras HTTP axeitadas
 *
 * Cabeceiras HTTP:
 *   - Content-Type: application/rss+xml; charset=utf-8
 *   - Cache-Control: no-cache (para que os lectores sempre obteñan datos frescos)
 *   - Pragma: no-cache
 *   - Expires: 0
 *
 * Formato RSS:
 *   RSS 2.0 con namespace Atom para auto-referencia.
 *   Cada entrada inclúe etiqueta <enclosure> coa URL e tipo MIME do audio.
 *
 * @package Rádio FilispiM — Xestor de RSS
 * @version 1.0
 * ============================================================================
 */

// ============================================================================
// RUTAS DE FICHEIROS
// ============================================================================

/**
 * Ruta ao ficheiro de datos principal.
 * @var string
 */
$dataFile = __DIR__ . '/data/data.json';

// ============================================================================
// 1. COMPROBACIÓN DE INSTALACIÓN
// ============================================================================

/**
 * Se o ficheiro de datos non existe, a aplicación non está instalada.
 * Devolvemos un XML RSS con mensaxe de erro.
 */
if (!file_exists($dataFile)) {
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Rádio FilispiM — Xestor de RSS</title>
    <description>A aplicación aínda non está configurada.</description>
  </channel>
</rss>';
    exit;
}

// ============================================================================
// 2. VALIDACIÓN DO PARÁMETRO "feed"
// ============================================================================

/**
 * O slug do feed é obrigatorio. Se non se proporciona, devolvemos erro 400.
 * @var string
 */
$slug = $_GET['feed'] ?? '';

if (empty($slug)) {
    header('Content-Type: application/rss+xml; charset=utf-8');
    http_response_code(400);
    echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Erro</title>
    <description>Non se especificou ningún feed. Usa ?feed=slug-do-feed</description>
  </channel>
</rss>';
    exit;
}

// ============================================================================
// 3. LECTURA DOS DATOS (con bloqueo compartido)
// ============================================================================

/**
 * Ler os datos do ficheiro JSON usando bloqueo de ficheiros
 * para evitar lecturas durante escrituras simultáneas.
 */
$lockFile = __DIR__ . '/data/data.lock';
$fp = fopen($lockFile, 'c+');
flock($fp, LOCK_SH);                                  // Bloqueo compartido (lectura)
$content = file_get_contents($dataFile);
flock($fp, LOCK_UN);                                  // Liberar bloqueo
fclose($fp);

// Descodificar o JSON
$data = json_decode($content, true);

// Verificar que os datos son válidos
if (!is_array($data) || !isset($data['feeds'])) {
    header('Content-Type: application/rss+xml; charset=utf-8');
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Erro</title>
    <description>Erro ao ler os datos.</description>
  </channel>
</rss>';
    exit;
}

// ============================================================================
// 4. BUSCA DO FEED PELO SLUG
// ============================================================================

/**
 * Buscar o feed solicitado na lista de feeds.
 * O slug é o identificador único amigable da URL.
 */
$foundFeed = null;
$feedIndex = null;

foreach ($data['feeds'] as $i => $feed) {
    if ($feed['slug'] === $slug) {
        $foundFeed = $feed;
        $feedIndex = $i;
        break;
    }
}

// Se non se atopou o feed, devolver erro 404
if (!$foundFeed) {
    header('Content-Type: application/rss+xml; charset=utf-8');
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Feed non atopado</title>
    <description>Non existe ningún feed co identificador "' . htmlspecialchars($slug, ENT_XML1, 'UTF-8') . '".</description>
  </channel>
</rss>';
    exit;
}

// ============================================================================
// 5. INCREMENTO DO CONTADOR DE ACCESOS (con bloqueo exclusivo)
// ============================================================================

/**
 * Cada vez que un lector de RSS ou podcast accede a este endpoint,
 * incrementamos o contador de accesos do feed.
 * Usamos bloqueo exclusivo para evitar condicións de carreira.
 */
$fp2 = fopen($lockFile, 'c+');
flock($fp2, LOCK_EX);                                 // Bloqueo exclusivo (escritura)
$content = file_get_contents($dataFile);
$data = json_decode($content, true);
if (isset($data['feeds'][$feedIndex])) {
    // Incrementar o contador (ou inicializar a 0 se non existe)
    $data['feeds'][$feedIndex]['access_count'] = ($data['feeds'][$feedIndex]['access_count'] ?? 0) + 1;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
flock($fp2, LOCK_UN);                                 // Liberar bloqueo
fclose($fp2);

// ============================================================================
// 6. XERACIÓN DO XML RSS 2.0
// ============================================================================

/**
 * Construír a URL base do servidor para as referencias no XML.
 * Funciona correctamente en calquera subdirectorio.
 */
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $scriptDir;
$rssUrl = $baseUrl . '/rss.php?feed=' . urlencode($foundFeed['slug']);

/**
 * Función de escape para XML.
 * Converte caracteres especiais para que sexan seguros dentro de etiquetas XML.
 * @param string $str Texto a escapar
 * @return string Texto escapado
 */
function xmlEscape(string $str): string {
    return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// --- Iniciar a construción do XML ---

// Declaración XML
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

// Elemento raíz RSS 2.0 con namespace Atom
$xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";

// Canle principal
$xml .= '  <channel>' . "\n";

// Información do canle
$xml .= '    <title>' . xmlEscape($foundFeed['name']) . "</title>\n";
$xml .= '    <description>' . xmlEscape($foundFeed['description'] ?? '') . "</description>\n";
$xml .= '    <link>' . xmlEscape($baseUrl) . "</link>\n";

// Auto-referencia Atom (requiren moitos lectores de RSS)
$xml .= '    <atom:link href="' . xmlEscape($rssUrl) . '" rel="self" type="application/rss+xml"/>' . "\n";

// Idioma do feed: galego (gl)
$xml .= '    <language>gl</language>' . "\n";

// Data de última actualización (formato RFC 2822)
$xml .= '    <lastBuildDate>' . date('r', strtotime($foundFeed['updated_at'])) . "</lastBuildDate>\n";

// ============================================================================
// 7. XERACIÓN DAS ENTRADAS (EPISODIOS)
// ============================================================================

/**
 * Ordenar as entradas por data de publicación descendente
 * (episodio máis recente primeiro).
 */
$entries = $foundFeed['entries'] ?? [];
usort($entries, function ($a, $b) {
    return strtotime($b['pub_date']) - strtotime($a['pub_date']);
});

// Xerar un <item> por cada entrada
foreach ($entries as $entry) {
    $xml .= '    <item>' . "\n";

    // Título do episodio
    $xml .= '      <title>' . xmlEscape($entry['title']) . "</title>\n";

    // Descrición do episodio
    $xml .= '      <description>' . xmlEscape($entry['description'] ?? '') . "</description>\n";

    // Ficheiro de audio (enclosure) — esencial para podcasts
    // O tipo MIME detectouse automaticamente ao crear a entrada
    $xml .= '      <enclosure url="' . xmlEscape($entry['audio_url']) . '" length="0" type="' . xmlEscape($entry['audio_type'] ?? 'audio/mpeg') . '"/>' . "\n";

    // Data de publicación (formato RFC 2822)
    $xml .= '      <pubDate>' . date('r', strtotime($entry['pub_date'])) . "</pubDate>\n";

    // Identificador único do episodio (GUID)
    $xml .= '      <guid>' . xmlEscape($entry['id']) . "</guid>\n";

    $xml .= '    </item>' . "\n";
}

// Pechar o canle e o RSS
$xml .= '  </channel>' . "\n";
$xml .= '</rss>';

// ============================================================================
// 8. ENVIO DO XML AO CLIENTE
// ============================================================================

/**
 * Enviar o XML con cabeceiras HTTP axeitadas:
 * - Content-Type: application/rss+xml (para que os lectores o recoñezan)
 * - Cache-Control: no-cache (para que sempre se obtengan datos frescos)
 * - Pragma e Expires: compatibilidade con HTTP/1.0
 */
header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo $xml;
