<?php
session_start();

/**
 * ============================================================================
 * Rádio FilispiM — Xestor de RSS
 * Ficheiro: index.php
 * ============================================================================
 *
 * Descrición:
 *   Páxina principal da aplicación. É un "shell" HTML baleiro que carga
 *   a interface de usuario construída dinámicamente con JavaScript.
 *
 * Funcionamento:
 *   1. Comproba se a aplicación está instalada (existe data/data.json)
 *   2. Se non está instalada, redirixe a install.php
 *   3. Se está instalada, carga a páxina HTML coa estrutura básica
 *
 * Estrutura HTML:
 *   - <header>: Cabeceira renderizada por JS (logo, nome, botóns)
 *   - <main>: Contido principal renderizado por JS (feeds, filtros)
 *   - <footer>: Pé de páxina renderizado por JS (dereitos de autor)
 *   - Reprodutor de radio: Fixo abaixo, renderizado por JS
 *   - Contedor de diálogos: Modais renderizados por JS
 *   - Contedor de toasts: Notificacións renderizadas por JS
 *
 * Ficheiros que carga:
 *   - css/style.css: Follas de estilo principais
 *   - js/app.js: Toda a lóxica de interface e chamadas á API
 *
 * @package Rádio FilispiM — Xestor de RSS
 * @version 1.0
 * ============================================================================
 */

// ============================================================================
// COMPROBACIÓN DE INSTALACIÓN
// ============================================================================

/**
 * Ruta ao ficheiro de datos.
 * Se non existe, a aplicación non foi instalada aínda.
 * @var string
 */
$dataFile = __DIR__ . '/data/data.json';

if (!file_exists($dataFile)) {
    // Redirixir ao script de instalación
    header('Location: install.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="gl">
<head>
    <!-- Codificación de caracteres UTF-8 -->
    <meta charset="UTF-8">

    <!-- Vista adaptada a todos os dispositivos -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Descrición para buscadores e redes sociais -->
    <meta name="description" content="Rádio FilispiM - Xestor de RSS para a radio libre e comunitaria da Terra de Trasancos. 102.3 FM">

    <!-- Cor do tema do navegador (barra de enderezos en móbil) -->
    <meta name="theme-color" content="#dc2626">

    <!-- Metadatos Open Graph para compartición en redes sociais -->
    <meta property="og:title" content="Rádio FilispiM — Xestor de RSS">
    <meta property="og:description" content="Crea e xestiona feeds RSS para os programas da Rádio FilispiM">
    <meta property="og:type" content="website">

    <!-- Título da páxina -->
    <title>Rádio FilispiM — Xestor de RSS</title>

    <!-- Favicon SVG inline (icona de radio emoji, sen ficheiro externo) -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📻</text></svg>">

    <!-- Follas de estilo principais -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- ============================================================
         Cabeceira da aplicación
         Renderizada dinámicamente por renderHeader() en app.js.
         Contén: logo, nome "Rádio FilispiM", badge "NO AR",
         e botóns de acceso/saír.
         ============================================================ -->
    <header id="app-header" class="header"></header>

    <!-- ============================================================
         Contido principal da páxina
         Renderizado dinámicamente por renderMain() en app.js.
         Contén: lapelas de filtro, tarxetas de feeds, e botóns
         de administración (crear feed, exportar/importar).
         ============================================================ -->
    <main id="app-main" class="main-content"></main>

    <!-- ============================================================
         Pé de páxina
         Renderizado dinámicamente por renderFooter() en app.js.
         Contén: dereitos de autor e información da frecuencia FM.
         ============================================================ -->
    <footer id="app-footer" class="footer"></footer>

    <!-- ============================================================
         Reprodutor de radio en directo
         Renderizado dinámicamente por renderPlayer() en app.js.
         Fixo na parte inferior da pantalla (position: fixed).
         Reproduce o streaming de CUAC FM (filispim.mp3).
         ============================================================ -->
    <div id="radio-player" class="player-fixed"></div>

    <!-- ============================================================
         Contedor de diálogos modais
         Os modais (login, crear feed, etc.) insírense aquí
         dinámicamente por renderDialogs() en app.js.
         ============================================================ -->
    <div id="dialog-container"></div>

    <!-- ============================================================
         Contedor de notificacións toast
         As mensaxes temporais (éxito, erro, información) insírense
         aquí por showToast() en app.js.
         ============================================================ -->
    <div id="toast-container" class="toast-container"></div>

    <!-- ============================================================
         JavaScript principal
         Contén toda a lóxica da interface: renderizado, chamadas á API,
         xestión de diálogos, reprodutor, eventos e inicialización.
         ============================================================ -->
    <script src="js/app.js"></script>

</body>
</html>
