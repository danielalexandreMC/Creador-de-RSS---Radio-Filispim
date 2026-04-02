/**
 * ============================================================================
 * Rádio FilispiM — Xestor de RSS
 * Ficheiro: js/app.js
 * ============================================================================
 *
 * Descrición:
 *   Aplicación cliente JavaScript que constrúe toda a interface de usuario
 *   de forma dinámica. É unha SPA (Single Page Application) sen framework:
 *   usa JavaScript vanilla para renderizado, xestión de estado e chamadas á API.
 *
 * Toda a interface está en galego (gl).
 * Deseño visual inspirado na web de ideia.gal/rfm.
 *
 * Arquitectura:
 *   - Estado centralizado no obxecto "App"
 *   - Renderizado imperativo (actualización manual do DOM)
 *   - Delegación de eventos (un listener no document para todos os clics)
 *   - Chamadas á API asíncronas con fetch()
 *
 * Seccións do código:
 *   1. Estado da aplicación
 *   2. Utilidades (escape HTML, formato de data, portapapeis, ruta base)
 *   3. Notificacións toast
 *   4. Chamadas á API
 *   5. Xestión de diálogos
 *   6. Iconos SVG
 *   7. Renderizado (cabeceira, contido, tarxetas, entradas, pé, reprodutor, diálogos)
 *   8. Renderizado de diálogos (login, crear/editar feed, engadir/editar entrada, etc.)
 *   9. Funcións do reprodutor de radio
 *   10. Listener de eventos (delegación)
 *   11. Xestores de accións (handlers dos formularios e botóns)
 *   12. Inicialización
 *
 * @package Rádio FilispiM — Xestor de RSS
 * @version 1.0
 * ============================================================================
 */

// ============================================================================
// 1. ESTADO DA APLICACIÓN
// ============================================================================
// O estado global almacena toda a información necesaria para renderizar a UI.
// Cando cambia o estado, chámase ás funcións de renderizado para actualizar
// o DOM. Este patrón é simple pero efectivo para aplicacións de este tamaño.

/**
 * Estado global da aplicación.
 * @type {Object}
 * @property {boolean} isAdmin - true se o usuario actual é administrador
 * @property {string} username - Nome do usuario administrador
 * @property {string} filter - Filtro temporal activo ('7', '15', '30', 'all')
 * @property {Array} feeds - Lista de feeds RSS cargados dende a API
 * @property {Object|null} currentFeed - Feed seleccionado actualmente (para diálogos)
 * @property {boolean} loading - true mentres se cargan os feeds
 * @property {Object} dialogs - Mapa de diálogos abertos
 * @property {string} xmlContent - Contido XML do feed previsualizado
 * @property {Array} changelogData - Datos do rexistro de cambios
 */
const App = {
  isAdmin: false,
  username: '',
  filter: '7',
  feeds: [],
  currentFeed: null,
  loading: false,
  dialogs: {},
  xmlContent: '',
  changelogData: [],
};

// ============================================================================
// Reprodutor de audio — streaming en directo de CUAC FM
// ============================================================================
// O audio non se carga ata que o usuario preme "Reproducir" (preload=none).
// Comeza silenciado para evitar reprodución automática non desexada.
// ============================================================================

/** @type {HTMLAudioElement} Elemento de audio para o streaming */
const audio = new Audio('https://streaming.cuacfm.org/filispim.mp3');
audio.preload = 'none';   // Non pre cargar o audio
audio.muted = true;       // Comezar silenciado

// ============================================================================
// 2. UTILIDADES
// ============================================================================

/**
 * Escapar caracteres HTML especiais para previr inxeccións XSS.
 * Converte: & → &amp;  < → &lt;  > → &gt;  " → &quot;  ' → &#039;
 *
 * @param {*} str - Valor a escapar (só procesa cadeas)
 * @returns {string} Cadea escapada segura para inserir no HTML
 */
function escapeHtml(str) {
  if (typeof str !== 'string') return '';
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return str.replace(/[&<>"']/g, (ch) => map[ch]);
}

/**
 * Formatear unha data ISO 8601 a formato lexible en galego.
 * Converte "2024-01-15T10:30:00Z" en "15/01/2024, 10:30".
 *
 * @param {string} dateStr - Data en formato ISO 8601 ou calquera formato recoñecido por Date
 * @returns {string} Data formateada en DD/MM/AAAA, HH:MM ou a cadea orixinal se non se pode analizar
 */
function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  return day + '/' + month + '/' + year + ', ' + hours + ':' + minutes;
}

/**
 * Copiar un texto ao portapapeis do usuario.
 * Intenta usar a API moderna (navigator.clipboard) primeiro.
 * Se non está dispoñible (HTTP, navegadores antigos), usa o método
 * de respaldo con textarea oculto e execCommand('copy').
 *
 * @param {string} text - Texto a copiar ao portapapeis
 */
async function copyToClipboard(text) {
  try {
    // Método moderno (requere HTTPS ou localhost)
    await navigator.clipboard.writeText(text);
    showToast('Enlace copiado!');
  } catch {
    // Método de respaldo para HTTP ou navegadores antigos
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('Enlace copiado!');
  }
}

// ============================================================================
// Cálculo da ruta base da aplicación
// ============================================================================
// Detecta automaticamente o subdirectorio no que está instalada a app.
// Por exemplo, se a URL é https://exemplo.com/rfm/rss/index.php,
// APP_BASE será "/rfm/rss". Isto permite que os enlaces a api.php,
// rss.php e css/ funcionen correctamente en calquera subdirectorio.
// ============================================================================

/** @type {string} Ruta base do proxecto (sen barra final) */
var APP_BASE = window.location.pathname.replace(/\/[^/]*\.php.*$/, '').replace(/\/$/, '') || '';

/**
 * Construír a URL completa dun feed RSS.
 *
 * @param {string} slug - Slug do feed
 * @returns {string} URL completa (ex: "https://exemplo.com/rss.php?feed=meu-podcast")
 */
function getRssUrl(slug) {
  return window.location.origin + APP_BASE + '/rss.php?feed=' + encodeURIComponent(slug);
}

// ============================================================================
// 3. NOTIFICACIÓNS TOAST
// ============================================================================
// Mensaxes temporais flotantes que aparecen debaixo do reprodutor de radio.
// Tipos: 'success' (verde), 'error' (vermello), 'info' (azul).
// Duración: 3 segundos con animación de entrada/saída.
// ============================================================================

/**
 * Mostrar unha notificación toast.
 *
 * @param {string} message - Mensaxe a mostrar (en galego)
 * @param {string} [type='success'] - Tipo de notificación: 'success', 'error' ou 'info'
 */
function showToast(message, type) {
  type = type || 'success';
  const container = document.getElementById('toast-container');
  if (!container) return;

  // Cores e iconas para cada tipo
  const colors = { success: 'toast-success', error: 'toast-error', info: 'toast-info' };
  const icons = { success: '&#10003;', error: '&#10007;', info: '&#8505;' };

  // Crear o elemento do toast
  const toast = document.createElement('div');
  toast.className = 'toast ' + (colors[type] || colors.success);
  toast.innerHTML = '<span class="toast-icon">' + (icons[type] || icons.success) + '</span>' +
    '<span class="toast-msg">' + escapeHtml(message) + '</span>';
  container.appendChild(toast);

  // Animar a entrada no seguinte frame (para que a animación CSS funcione)
  requestAnimationFrame(function() {
    toast.classList.add('toast-visible');
  });

  // Eliminar o toast despois de 3 segundos
  setTimeout(function() {
    toast.classList.remove('toast-visible');
    toast.addEventListener('transitionend', function() { toast.remove(); }, { once: true });
    // Seguro: eliminar despois de 400ms se o evento non se disparou
    setTimeout(function() { if (toast.parentNode) toast.remove(); }, 400);
  }, 3000);
}

// ============================================================================
// 4. CHAMADAS Á API
// ============================================================================
// Funcións asíncronas para comunicarse co servidor (api.php).
// Usan a API fetch() nativa do navegador.
// Todas as respostas se esperan en formato JSON.
// ============================================================================

/**
 * Realizar unha petición GET á API.
 *
 * @param {string} action - Nome da acción (ex: 'feeds', 'session', 'changelog')
 * @param {Object} [params={}] - Parámetros adicionais da query string
 * @returns {Promise<Object>} Resposta da API en formato JSON
 * @throws {Error} Se a resposta non é exitosa (status != 200)
 */
async function apiGet(action, params) {
  params = params || {};
  const url = new URL('api.php', window.location.origin + APP_BASE + '/');
  url.searchParams.set('action', action);
  for (const key in params) {
    if (params.hasOwnProperty(key)) {
      url.searchParams.set(key, params[key]);
    }
  }
  const response = await fetch(url.toString());
  if (!response.ok) throw new Error('Erro ' + response.status + ': ' + response.statusText);
  return response.json();
}

/**
 * Realizar unha petición POST á API.
 * Envía os datos en formato JSON no corpo da petición.
 *
 * @param {string} action - Nome da acción (ex: 'login', 'create_feed', 'add_entry')
 * @param {Object} [data={}] - Datos a enviar no corpo (sen incluir 'action', que se engade auto)
 * @returns {Promise<Object>} Resposta da API en formato JSON
 * @throws {Error} Se a resposta non é exitosa
 */
async function apiPost(action, data) {
  data = data || {};
  const response = await fetch(APP_BASE + '/api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({ action: action }, data))
  });
  if (!response.ok) throw new Error('Erro ' + response.status + ': ' + response.statusText);
  return response.json();
}

/**
 * Comprobar se hai unha sesión activa de administrador.
 * Actualiza o estado global (App.isAdmin, App.username).
 */
async function checkSession() {
  try {
    const result = await apiGet('session');
    if (result.success) {
      App.isAdmin = true;
      App.username = result.username || '';
    } else {
      App.isAdmin = false;
      App.username = '';
    }
  } catch (e) {
    App.isAdmin = false;
    App.username = '';
  }
}

/**
 * Iniciar sesión coas credenciais proporcionadas.
 *
 * @param {string} username - Nome de usuario
 * @param {string} password - Contrasinal
 * @throws {Error} Se as credenciais son incorrectas
 */
async function login(username, password) {
  const result = await apiPost('login', { username: username, password: password });
  if (result.success) {
    App.isAdmin = true;
    App.username = username;
    showToast('Sesión iniciada correctamente');
  } else {
    throw new Error(result.error || 'Credenciais incorrectas');
  }
}

/**
 * Pechar a sesión do administrador.
 */
async function logout() {
  const result = await apiPost('logout');
  App.isAdmin = false;
  App.username = '';
  showToast('Sesión pechada');
}

/**
 * Obtén a lista de feeds dende a API, aplicando o filtro temporal.
 * Actualiza o estado e re-renderiza a interfaz.
 *
 * @param {string} filter - Filtro temporal: '7', '15', '30' ou 'all'
 */
async function fetchFeeds(filter) {
  App.loading = true;
  renderMain();

  try {
    const result = await apiGet('feeds', { filter: filter });
    if (result.success) {
      App.feeds = result.feeds || [];
    } else {
      throw new Error(result.error || 'Non se puideron cargar os feeds');
    }
  } catch (err) {
    showToast(err.message, 'error');
    App.feeds = [];
  } finally {
    App.loading = false;
    renderMain();
  }
}

/**
 * Crear un novo feed RSS.
 *
 * @param {string} name - Nome do feed
 * @param {string} description - Descrición do feed
 * @throws {Error} Se hai erro na API
 */
async function createFeed(name, description) {
  const result = await apiPost('create_feed', { name: name, description: description });
  if (result.success) {
    showToast('Feed creado correctamente');
  } else {
    throw new Error(result.error || 'Non se puido crear o feed');
  }
}

/**
 * Editar un feed existente.
 *
 * @param {string} id - ID do feed
 * @param {string} name - Novo nome
 * @param {string} description - Nova descrición
 * @throws {Error} Se hai erro na API
 */
async function editFeed(id, name, description) {
  const result = await apiPost('edit_feed', { id: id, name: name, description: description });
  if (result.success) {
    showToast('Feed actualizado correctamente');
  } else {
    throw new Error(result.error || 'Non se puido actualizar o feed');
  }
}

/**
 * Eliminar un feed.
 *
 * @param {string} id - ID do feed a eliminar
 * @throws {Error} Se hai erro na API
 */
async function deleteFeed(id) {
  const result = await apiPost('delete_feed', { id: id });
  if (result.success) {
    showToast('Feed eliminado');
  } else {
    throw new Error(result.error || 'Non se puido eliminar o feed');
  }
}

/**
 * Engadir unha nova entrada (episodio) a un feed.
 *
 * @param {string} feedId - ID do feed
 * @param {string} title - Título do episodio
 * @param {string} description - Descrición do episodio
 * @param {string} audioUrl - URL directa ao ficheiro de audio
 * @throws {Error} Se hai erro na API
 */
async function addEntry(feedId, title, description, audioUrl) {
  const result = await apiPost('add_entry', { feedId: feedId, title: title, description: description, audioUrl: audioUrl });
  if (result.success) {
    showToast('Entrada engadida correctamente');
  } else {
    throw new Error(result.error || 'Non se puido engadir a entrada');
  }
}

/**
 * Eliminar unha entrada dun feed.
 *
 * @param {string} feedId - ID do feed
 * @param {string} entryId - ID da entrada a eliminar
 * @throws {Error} Se hai erro na API
 */
async function deleteEntry(feedId, entryId) {
  const result = await apiPost('delete_entry', { feedId: feedId, entryId: entryId });
  if (result.success) {
    showToast('Entrada eliminada');
  } else {
    throw new Error(result.error || 'Non se puido eliminar a entrada');
  }
}

/**
 * Editar unha entrada existente (título, descrición, URL de audio).
 *
 * @param {string} feedId - ID do feed
 * @param {string} entryId - ID da entrada
 * @param {string} title - Novo título
 * @param {string} description - Nova descrición
 * @param {string} audioUrl - Nova URL de audio (baleiro para manter a actual)
 * @throws {Error} Se hai erro na API
 */
async function editEntry(feedId, entryId, title, description, audioUrl) {
  const result = await apiPost('edit_entry', { feedId: feedId, entryId: entryId, title: title, description: description, audioUrl: audioUrl });
  if (result.success) {
    showToast('Entrada actualizada correctamente');
  } else {
    throw new Error(result.error || 'Non se puido actualizar a entrada');
  }
}

/**
 * Obtén a previsualización XML dun feed para mostrar nun modal.
 *
 * @param {string} feedId - ID do feed
 * @throws {Error} Se hai erro na API
 */
async function getXmlPreview(feedId) {
  const result = await apiGet('xml_preview', { feedId: feedId });
  if (result.success) {
    App.xmlContent = result.xml || '';
  } else {
    throw new Error(result.error || 'Non se puido xerar a previsualización XML');
  }
}

/**
 * Obtén o rexistro de cambios dun feed.
 *
 * @param {string} feedId - ID do feed (opcional, se se omite mostra todos)
 * @throws {Error} Se hai erro na API
 */
async function getChangelog(feedId) {
  const result = await apiGet('changelog', { feedId: feedId });
  if (result.success) {
    App.changelogData = result.changelog || [];
  } else {
    throw new Error(result.error || 'Non se puido cargar o rexistro de cambios');
  }
}

/**
 * Exportar todos os datos da aplicación como ficheiro JSON descargable.
 * Crea un Blob, xera un enlace temporal e inicia a descarga.
 */
async function exportData() {
  try {
    const result = await apiGet('export');
    if (result.success) {
      // Crear un Blob cos datos JSON formatados
      const blob = new Blob([JSON.stringify(result.data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'radiofilispim-rss-backup-' + new Date().toISOString().slice(0, 10) + '.json';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);    // Liberar memoria
      showToast('Datos exportados correctamente');
    } else {
      throw new Error(result.error || 'Non se puideron exportar os datos');
    }
  } catch (err) {
    showToast(err.message, 'error');
  }
}

/**
 * Importar datos dun ficheiro JSON de copia de seguranza.
 *
 * @param {Object} jsonStr - Datos JSON a importar (contén array "feeds")
 * @throws {Error} Se hai erro na API
 */
async function importData(jsonStr) {
  const result = await apiPost('import', { data: jsonStr });
  if (result.success) {
    showToast('Datos importados: ' + (result.feeds || 0) + ' feeds, ' + (result.entries || 0) + ' entradas');
  } else {
    throw new Error(result.error || 'Non se puideron importar os datos');
  }
}

// ============================================================================
// 5. XESTIÓN DE DIÁLOGOS
// ============================================================================
// Sistema simple de xestión de modais. Só un diálogo aberto á vez.
// Ao abrir un novo, péchanse todos os anteriores.
// ============================================================================

/**
 * Abrir un diálogo modal.
 * Pecha calquera diálogo aberto, establece o novo no estado,
 * renderízao e engade a clase 'dialog-open' ao body para bloquear o scroll.
 *
 * @param {string} name - Nome do diálogo (ex: 'login', 'createFeed', 'editEntry')
 * @param {Object} [data] - Datos opcionais para o diálogo (feed, entrada, etc.)
 */
function openDialog(name, data) {
  closeDialogs();
  App.dialogs[name] = true;
  if (data) App.currentFeed = data;
  renderDialogs();
  document.body.classList.add('dialog-open');

  // Foco automático no primeiro campo do formulario
  requestAnimationFrame(function() {
    const input = document.querySelector('.modal-overlay.active .modal-body input, .modal-overlay.active .modal-body textarea');
    if (input) input.focus();
  });
}

/**
 * Pechar todos os diálogos abertos.
 * Limpa o estado de diálogos, o feed seleccionado e o contedor HTML.
 */
function closeDialogs() {
  App.dialogs = {};
  App.currentFeed = null;
  const container = document.getElementById('dialog-container');
  if (container) container.innerHTML = '';
  document.body.classList.remove('dialog-open');
}

// ============================================================================
// 6. ICONOS SVG
// ============================================================================
// Definicións de iconos SVG inline para evitar dependencias externas.
// Usados nos botóns e elementos da interface.
// ============================================================================

/** @type {Object<string, string>} Mapa de iconos SVG por nome */
const ICONS = {
  play: '<svg class="icon-play" viewBox="0 0 24 24" fill="currentColor"><polygon points="6,3 20,12 6,21"/></svg>',
  pause: '<svg class="icon-pause" viewBox="0 0 24 24" fill="currentColor"><rect x="5" y="3" width="4" height="18"/><rect x="15" y="3" width="4" height="18"/></svg>',
  volume: '<svg class="volume-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>',
  volumeMuted: '<svg class="volume-icon-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>',
  trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
  pencil: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  link: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
  code: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
  download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  upload: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
  lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  logout: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
  settings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  exportImport: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>',
};

// ============================================================================
// 7. RENDERIZADO — Funcións que xeran o HTML da interface
// ============================================================================

/**
 * Renderiza a cabeceira da aplicación.
 * Mostra: logo, nome "Rádio FilispiM", subtitulo "Xestor de RSS",
 * badge "NO AR", e botóns de acceso/saír segundo o estado de autenticación.
 */
function renderHeader() {
  const header = document.getElementById('app-header');
  if (!header) return;

  // HTML diferente se o usuario está autenticado ou non
  var adminHtml;
  if (App.isAdmin) {
    // Usuario autenticado: mostrar nome e botón "Saír"
    adminHtml =
      '<div class="header-user">' +
        '<span class="header-username" title="Administrador">' + ICONS.settings + ' ' + escapeHtml(App.username) + '</span>' +
        '<button class="btn btn-cancel btn-sm" data-action="logout" title="Pechar sesión">' +
          ICONS.logout + ' Saír' +
        '</button>' +
      '</div>';
  } else {
    // Usuario non autenticado: mostrar botón "Acceder"
    adminHtml =
      '<button class="btn btn-cancel btn-sm" data-action="login" title="Acceder á administración">' +
        ICONS.lock + ' Acceder' +
      '</button>';
  }

  // Montar a cabeceira completa
  header.innerHTML =
    '<div class="header-content">' +
      '<div class="header-left">' +
        '<div class="header-logo">&#127911;</div>' +
        '<div class="header-info">' +
          '<h1 class="header-title">Rádio FilispiM</h1>' +
          '<p class="header-slogan">Xestor de RSS</p>' +
        '</div>' +
      '</div>' +
      '<div class="header-right">' +
        '<div class="live-badge"><span class="live-dot"></span>NO AR</div>' +
        adminHtml +
      '</div>' +
    '</div>';
}

/**
 * Renderiza o contido principal da páxina.
 * Inclúe: lapelas de filtro, botóns de acción (admin), e
 * as tarxetas de feeds ou estados de carga/baleiro.
 */
function renderMain() {
  var main = document.getElementById('app-main');
  if (!main) return;

  // Definir as lapelas de filtro temporal
  var filters = [
    { value: '7', label: 'Últimos 7 días' },
    { value: '15', label: '+15 días' },
    { value: '30', label: '+30 días' },
    { value: 'all', label: 'Todos' }
  ];

  // Xerar HTML das lapelas
  var filterTabs = '';
  for (var i = 0; i < filters.length; i++) {
    var f = filters[i];
    filterTabs += '<button class="filter-tab' + (App.filter === f.value ? ' active' : '') + '" data-filter="' + f.value + '">' + f.label + '</button>';
  }

  // Contido principal: cargando, baleiro ou lista de feeds
  var contentHtml = '';

  if (App.loading) {
    // Estado de carga: spinner animado
    contentHtml =
      '<div class="loading-container">' +
        '<div class="spinner"></div>' +
        '<p class="loading-text">Cargando feeds...</p>' +
      '</div>';
  } else if (App.feeds.length === 0) {
    // Estado baleiro: sen feeds creados
    contentHtml =
      '<div class="empty-state">' +
        '<div class="empty-icon">&#128246;</div>' +
        '<p class="empty-text">Non hai feeds RSS creados ainda</p>' +
      '</div>';
  } else {
    // Lista de tarxetas de feeds
    var cardsHtml = '';
    for (var j = 0; j < App.feeds.length; j++) {
      cardsHtml += renderFeedCard(App.feeds[j]);
    }
    contentHtml = '<div class="feeds-grid">' + cardsHtml + '</div>';
  }

  // Botóns de acción só visibles para o administrador
  var createBtnHtml = '';
  if (App.isAdmin) {
    createBtnHtml =
      '<div class="feeds-actions">' +
        '<button class="btn btn-primary" data-action="create-feed">' + ICONS.plus + ' Crear novo feed</button>' +
        '<button class="btn btn-cancel btn-sm" data-action="export-import" title="Exportar/Importar datos">' + ICONS.exportImport + ' Datos</button>' +
      '</div>';
  }

  // Montar todo o contido principal
  main.innerHTML =
    '<div class="filter-section">' +
      '<div class="filter-tabs">' + filterTabs + '</div>' +
    '</div>' +
    createBtnHtml +
    contentHtml;
}

/**
 * Renderiza unha tarxeta de feed completa.
 * Inclúe: cabeceira con acordeón, corpo con descrición/meta,
 * primeira entrada visible, e entradas colapsables adicionais.
 *
 * @param {Object} feed - Datos do feed (id, name, description, slug, entries, etc.)
 * @returns {string} HTML completo da tarxeta
 */
function renderFeedCard(feed) {
  var entryCount = feed.entries ? feed.entries.length : (feed.entry_count || 0);
  var lastUpdated = feed.updated_at ? formatDate(feed.updated_at) : 'Sen actualizacións';
  var accessCount = feed.access_count || 0;
  var hasEntries = feed.entries && feed.entries.length > 0;

  // --- Botóns de acción na cabeceira ---
  var actionBtns = '';
  if (App.isAdmin) {
    // Botóns só para o administrador
    actionBtns += '<button class="action-btn delete-btn" data-action="delete-feed" data-feed-id="' + feed.id + '" title="Eliminar feed">' + ICONS.trash + '</button>';
    actionBtns += '<button class="action-btn edit-btn" data-action="edit-feed" data-feed-id="' + feed.id + '" title="Editar feed">' + ICONS.pencil + '</button>';
    actionBtns += '<button class="action-btn add-btn" data-action="add-entry" data-feed-id="' + feed.id + '" title="Engadir entrada">' + ICONS.plus + '</button>';
    actionBtns += '<button class="action-btn changelog-btn" data-action="changelog" data-feed-id="' + feed.id + '" title="Rexistro de cambios">' + ICONS.calendar + '</button>';
  }
  // Botóns visibles para todos os usuarios
  actionBtns += '<button class="copy-btn" data-action="copy-rss" data-slug="' + escapeHtml(feed.slug) + '" title="Copiar enlace RSS">' + ICONS.link + ' RSS</button>';
  actionBtns += '<button class="xml-btn" data-action="xml-preview" data-feed-id="' + feed.id + '" title="Ver XML">' + ICONS.code + ' XML</button>';

  // --- Cabeceira da tarxeta (con chevron para acordeón) ---
  var html =
    '<div class="card" data-feed-id="' + feed.id + '" data-slug="' + escapeHtml(feed.slug) + '">' +
      '<div class="card-header card-header-toggle" data-action="toggle-accordion">' +
        '<div style="display:flex;align-items:center;gap:0.375rem;flex:1;min-width:0;">' +
          '<svg class="card-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>' +
          '<h2 class="card-title"><span class="card-icon">&#127911;</span>' + escapeHtml(feed.name) + '</h2>' +
        '</div>' +
        '<span class="card-entry-count">' + entryCount + ' ' + (entryCount === 1 ? 'entrada' : 'entradas') + '</span>' +
      '</div>' +
      '<div class="card-header-actions">' + actionBtns + '</div>';

  // --- Corpo da tarxeta (descrición e metadatos) ---
  html += '<div class="card-body">';
  if (feed.description) {
    html += '<p class="feed-description">' + escapeHtml(feed.description) + '</p>';
  }
  html += '<div class="feed-meta">' +
    '<span>&#128337; Actualizado: ' + lastUpdated + '</span>' +
    '<span>&#128065; Accesos: ' + accessCount + '</span>' +
  '</div>';
  html += '</div>';

  // --- Primeira entrada (sempre visible) ---
  if (hasEntries) {
    var lastEntry = feed.entries[0];
    html += '<div class="feed-entries">' +
      renderEntry(lastEntry, feed.id) +
    '</div>';
  }

  // --- Entradas colapsables (acordeón) — ata 4 adicionais ---
  if (hasEntries && entryCount > 1) {
    html += '<div class="feed-entries-collapsed">';
    var accordionEntries = feed.entries.slice(1, 5);  // Entradas 2ª a 5ª
    for (var i = 0; i < accordionEntries.length; i++) {
      html += '<div class="feed-entries">' + renderEntry(accordionEntries[i], feed.id) + '</div>';
    }
    // Se hai máis de 5 entradas, mostrar botón "Ver todas"
    if (entryCount > 4) {
      html += '<button class="see-all-btn" data-action="see-all-entries" data-feed-id="' + feed.id + '">Ver todas as ' + entryCount + ' entradas &#8594;</button>';
    }
    html += '</div>';
  }

  html += '</div>';
  return html;
}

/**
 * Renderiza unha entrada (episodio) individual.
 * Mostra: título, descrición, data e botóns de editar/eliminar (só admin).
 *
 * @param {Object} entry - Datos da entrada (id, title, description, pub_date, audio_url)
 * @param {string} feedId - ID do feed pai (necesario para os botóns de acción)
 * @returns {string} HTML da entrada
 */
function renderEntry(entry, feedId) {
  return '<div class="feed-entry" data-entry-id="' + escapeHtml(String(entry.id)) + '">' +
    '<div class="entry-info">' +
      '<span class="entry-title">' + escapeHtml(entry.title) + '</span>' +
      (entry.description ? '<span class="entry-desc">' + escapeHtml(entry.description) + '</span>' : '') +
    '</div>' +
    '<div class="entry-meta">' +
      (entry.pub_date || entry.created_at ? '<span class="entry-date">' + formatDate(entry.pub_date || entry.created_at) + '</span>' : '') +
      (App.isAdmin ? '<button class="entry-edit-btn" data-action="edit-entry" data-feed-id="' + feedId + '" data-entry-id="' + entry.id + '" title="Editar entrada">' + ICONS.pencil + '</button>' : '') +
      (App.isAdmin ? '<button class="entry-delete-btn" data-action="delete-entry" data-feed-id="' + feedId + '" data-entry-id="' + entry.id + '" title="Eliminar entrada">' + ICONS.trash + '</button>' : '') +
    '</div>' +
  '</div>';
}

/**
 * Renderiza o pé de páxina.
 * Mostra: dereitos de autor e información da frecuencia FM.
 */
function renderFooter() {
  var footer = document.getElementById('app-footer');
  if (!footer) return;

  footer.innerHTML =
    '<div class="footer-content">' +
      '<span class="footer-text">&copy; ' + new Date().getFullYear() + ' Rádio FilispiM — Radio comunitaria gratuíta</span>' +
      '<span class="footer-text">102.3 FM — Terra de Trasancos</span>' +
    '</div>';
}

/**
 * Renderiza o reprodutor de radio fixo na parte inferior.
 * Mostra: botón play/pause, nome do programa, estado en directo,
 * e control de volume con slider.
 */
function renderPlayer() {
  var player = document.getElementById('radio-player');
  if (!player) return;

  var isPlaying = !audio.paused && !audio.ended;

  player.innerHTML =
    '<div class="player-card">' +
      '<div class="player-content">' +
        '<div class="player-top">' +
          '<button class="play-button' + (isPlaying ? ' playing' : '') + '" data-action="toggle-play" title="Reproducir/Deter">' +
            ICONS.play + ICONS.pause +
          '</button>' +
          '<div class="player-info">' +
            '<div class="program-title"><span class="program-name">Rádio FilispiM en directo</span></div>' +
            '<div class="program-host"><span id="player-status"' + (isPlaying ? ' class="live"' : '') + '>' + (isPlaying ? '&#9679; En directo' : 'Preme &#9654; para escoitar') + '</span></div>' +
          '</div>' +
          '<div class="volume-control">' +
            '<button class="volume-button' + (audio.muted ? ' muted' : '') + '" data-action="toggle-mute" title="Silenciar">' +
              ICONS.volume + ICONS.volumeMuted +
            '</button>' +
            '<input type="range" class="volume-slider" id="volumeSlider" min="0" max="100" value="' + (audio.muted ? 0 : Math.round(audio.volume * 100)) + '" title="Volume">' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<p class="fm-info">Também no <strong>102.3 FM</strong> da Terra de Trasancos</p>' +
    '</div>';
}

/**
 * Renderiza o diálogo modal activo.
 * Segundo o nome do diálogo activo, chamá a función renderizadora correspondente.
 */
function renderDialogs() {
  var container = document.getElementById('dialog-container');
  if (!container) return;

  // Buscar o diálogo activo
  var dialogNames = Object.keys(App.dialogs);
  var activeDialog = null;
  for (var i = 0; i < dialogNames.length; i++) {
    if (App.dialogs[dialogNames[i]]) {
      activeDialog = dialogNames[i];
      break;
    }
  }

  if (!activeDialog) {
    container.innerHTML = '';
    return;
  }

  // Selecionar a función renderizadora correspondente
  var html = '';

  switch (activeDialog) {
    case 'login': html = renderLoginDialog(); break;
    case 'createFeed': html = renderCreateFeedDialog(); break;
    case 'editFeed': html = renderEditFeedDialog(); break;
    case 'addEntry': html = renderAddEntryDialog(); break;
    case 'editEntry': html = renderEditEntryDialog(); break;
    case 'deleteFeed': html = renderDeleteConfirmDialog(); break;
    case 'xmlPreview': html = renderXmlPreviewDialog(); break;
    case 'changelog': html = renderChangelogDialog(); break;
    case 'exportImport': html = renderExportImportDialog(); break;
    case 'seeAllEntries': html = renderSeeAllEntriesDialog(); break;
    default: html = '';
  }

  container.innerHTML = html;
}

// ============================================================================
// 8. RENDERIZADO DE DIÁLOGOS MODAIS
// ============================================================================
// Cada función devolve o HTML completo dun modal específico.
// Os modais inclúen: overlay, caixa, cabeceira con pechar, corpo con formulario
// ou contido, e botóns de acción.
// ============================================================================

/** Diálogo de inicio de sesión */
function renderLoginDialog() {
  return '<div class="modal-overlay active">' +
    '<div class="modal">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Acceder á administración</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<form class="modal-body" id="form-login">' +
        '<div class="form-group">' +
          '<label class="form-label">Usuario</label>' +
          '<input class="form-input" type="text" name="username" placeholder="Nome de usuario" required autocomplete="username">' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">Contrasinal</label>' +
          '<input class="form-input" type="password" name="password" placeholder="Contrasinal" required autocomplete="current-password">' +
        '</div>' +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Cancelar</button>' +
          '<button type="submit" class="btn btn-primary">Acceder</button>' +
        '</div>' +
      '</form>' +
    '</div>' +
  '</div>';
}

/** Diálogo para crear un novo feed */
function renderCreateFeedDialog() {
  return '<div class="modal-overlay active">' +
    '<div class="modal">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Crear novo feed</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<form class="modal-body" id="form-create-feed">' +
        '<div class="form-group">' +
          '<label class="form-label">Nome do feed *</label>' +
          '<input class="form-input" type="text" name="name" placeholder="Nome do programa ou podcast" required>' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">Descrición</label>' +
          '<textarea class="form-textarea" name="description" placeholder="Descrición breve do contido do feed" rows="3"></textarea>' +
        '</div>' +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Cancelar</button>' +
          '<button type="submit" class="btn btn-primary">Crear feed</button>' +
        '</div>' +
      '</form>' +
    '</div>' +
  '</div>';
}

/** Diálogo para editar un feed existente (preenchido cos datos actuais) */
function renderEditFeedDialog() {
  var feed = App.currentFeed;
  var name = feed ? escapeHtml(feed.name) : '';
  var desc = feed ? escapeHtml(feed.description || '') : '';

  return '<div class="modal-overlay active">' +
    '<div class="modal">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Editar feed</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<form class="modal-body" id="form-edit-feed">' +
        '<div class="form-group">' +
          '<label class="form-label">Nome do feed *</label>' +
          '<input class="form-input" type="text" name="name" value="' + name + '" placeholder="Nome do programa ou podcast" required>' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">Descrición</label>' +
          '<textarea class="form-textarea" name="description" placeholder="Descrición breve do contido do feed" rows="3">' + desc + '</textarea>' +
        '</div>' +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Cancelar</button>' +
          '<button type="submit" class="btn btn-primary">Gardar</button>' +
        '</div>' +
      '</form>' +
    '</div>' +
  '</div>';
}

/** Diálogo para engadir unha nova entrada (episodio) a un feed */
function renderAddEntryDialog() {
  var feedName = App.currentFeed ? escapeHtml(App.currentFeed.name) : '';

  return '<div class="modal-overlay active">' +
    '<div class="modal modal-lg">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Engadir entrada a: ' + feedName + '</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<form class="modal-body" id="form-add-entry">' +
        '<div class="form-group">' +
          '<label class="form-label">Título *</label>' +
          '<input class="form-input" type="text" name="title" placeholder="Título do episodio" required>' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">Descrición</label>' +
          '<textarea class="form-textarea" name="description" placeholder="Descrición do episodio" rows="3"></textarea>' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">URL do audio (MP3/OGG) *</label>' +
          '<input class="form-input" type="url" name="audioUrl" placeholder="https://exemplo.com/audio.mp3" required>' +
          '<span class="form-hint">Enlace directo ao ficheiro de audio</span>' +
        '</div>' +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Cancelar</button>' +
          '<button type="submit" class="btn btn-primary">Engadir entrada</button>' +
        '</div>' +
      '</form>' +
    '</div>' +
  '</div>';
}

/** Diálogo para editar unha entrada existente (preenchido cos datos actuais) */
function renderEditEntryDialog() {
  var data = App.currentFeed;
  if (!data || !data.entry) return '';

  var entry = data.entry;
  var feedId = data.feedId;
  var title = escapeHtml(entry.title);
  var desc = escapeHtml(entry.description || '');
  var audioUrl = escapeHtml(entry.audio_url || '');

  return '<div class="modal-overlay active">' +
    '<div class="modal modal-lg">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Editar entrada</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<form class="modal-body" id="form-edit-entry">' +
        '<input type="hidden" name="feedId" value="' + feedId + '">' +
        '<input type="hidden" name="entryId" value="' + entry.id + '">' +
        '<div class="form-group">' +
          '<label class="form-label">Título *</label>' +
          '<input class="form-input" type="text" name="title" value="' + title + '" placeholder="Título do episodio" required>' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">Descrición</label>' +
          '<textarea class="form-textarea" name="description" placeholder="Descrición do episodio" rows="3">' + desc + '</textarea>' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">URL do audio (MP3/OGG)</label>' +
          '<input class="form-input" type="url" name="audioUrl" value="' + audioUrl + '" placeholder="https://exemplo.com/audio.mp3">' +
          '<span class="form-hint">Deixa baleiro para manter a URL actual</span>' +
        '</div>' +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Cancelar</button>' +
          '<button type="submit" class="btn btn-primary">Gardar cambios</button>' +
        '</div>' +
      '</form>' +
    '</div>' +
  '</div>';
}

/** Diálogo de confirmación para eliminar un feed (con advertencia de entradas) */
function renderDeleteConfirmDialog() {
  var feed = App.currentFeed;
  if (!feed) return '';

  var feedName = escapeHtml(feed.name);
  var entryCount = feed.entries ? feed.entries.length : (feed.entry_count || 0);
  var warning = '';
  if (entryCount > 0) {
    warning = '<p class="dialog-warning">&#9888; Este feed contén <strong>' + entryCount + '</strong> ' +
      (entryCount === 1 ? 'entrada' : 'entradas') + ' que se eliminarán permanentemente.</p>';
  }

  return '<div class="modal-overlay active">' +
    '<div class="modal">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Eliminar feed</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<div class="modal-body">' +
        '<p class="dialog-message">Seguro que queres eliminar o feed <strong>"' + feedName + '"</strong>?</p>' +
        '<p class="dialog-message-secondary">Esta acción non se pode desfacer.</p>' +
        warning +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Cancelar</button>' +
          '<button type="button" class="btn btn-danger" data-action="confirm-delete">Eliminar</button>' +
        '</div>' +
      '</div>' +
    '</div>' +
  '</div>';
}

/** Diálogo de previsualización XML (código RSS en bloque monoespazado) */
function renderXmlPreviewDialog() {
  var escapedXml = escapeHtml(App.xmlContent || 'Cargando...');

  return '<div class="modal-overlay active">' +
    '<div class="modal modal-xl">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Previsualización XML</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<div class="modal-body">' +
        '<pre class="xml-preview">' + escapedXml + '</pre>' +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Pechar</button>' +
          '<button type="button" class="btn btn-primary" data-action="copy-xml">' + ICONS.link + ' Copiar XML</button>' +
        '</div>' +
      '</div>' +
    '</div>' +
  '</div>';
}

/** Diálogo do rexistro de cambios (changelog) dun feed */
function renderChangelogDialog() {
  var feedName = App.currentFeed ? escapeHtml(App.currentFeed.name) : '';

  var changesHtml = '';
  if (App.changelogData.length === 0) {
    changesHtml =
      '<div class="changelog-empty">' +
        '<div class="changelog-empty-icon">&#128203;</div>' +
        '<p>Non hai cambios rexistrados para este feed.</p>' +
      '</div>';
  } else {
    // Iconas e etiquetas para cada tipo de acción
    var actionIcons = {
      created: '&#10133;', edited: '&#9998;', deleted: '&#128465;',
      entry_added: '&#127911;', entry_deleted: '&#128308;'
    };
    var actionLabels = {
      created: 'Feed creado', edited: 'Feed editado', deleted: 'Feed eliminado',
      entry_added: 'Entrada engadida', entry_deleted: 'Entrada eliminada'
    };

    // Xerar a lista de cambios
    for (var i = 0; i < App.changelogData.length; i++) {
      var c = App.changelogData[i];
      var icon = actionIcons[c.action] || '&#8505;';
      var label = actionLabels[c.action] || c.action || 'Acción descoñecida';
      var details = c.details ? '<span class="changelog-details">' + escapeHtml(c.details) + '</span>' : '';
      var date = c.created_at ? formatDate(c.created_at) : '';

      changesHtml +=
        '<div class="changelog-item">' +
          '<div class="changelog-icon">' + icon + '</div>' +
          '<div class="changelog-content">' +
            '<span class="changelog-action">' + label + '</span>' +
            details +
          '</div>' +
          '<span class="changelog-date">' + date + '</span>' +
        '</div>';
    }
  }

  return '<div class="modal-overlay active">' +
    '<div class="modal modal-lg">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Rexistro de cambios — ' + feedName + '</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<div class="modal-body">' +
        '<div class="changelog-list">' + changesHtml + '</div>' +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Pechar</button>' +
        '</div>' +
      '</div>' +
    '</div>' +
  '</div>';
}

/** Diálogo de exportación/importación de datos */
function renderExportImportDialog() {
  return '<div class="modal-overlay active">' +
    '<div class="modal">' +
      '<div class="modal-header">' +
        '<h2 class="modal-title">Exportar / Importar datos</h2>' +
        '<button class="modal-close" data-action="close-dialog">&times;</button>' +
      '</div>' +
      '<div class="modal-body">' +
        '<div class="export-import-section">' +
          '<h3 class="section-title">Exportar datos</h3>' +
          '<p class="section-description">Descarga unha copia de seguranza de todos os feeds e entradas en formato JSON.</p>' +
          '<button class="btn btn-primary" data-action="export">' + ICONS.download + ' Exportar datos</button>' +
        '</div>' +
        '<hr class="dialog-divider">' +
        '<div class="export-import-section">' +
          '<h3 class="section-title">Importar datos</h3>' +
          '<p class="section-description">Carga un ficheiro JSON de copia de seguranza para restaurar os datos. <strong>Isto sobreescribirá os datos existentes.</strong></p>' +
          '<div class="import-upload">' +
            '<label class="btn btn-cancel import-label" for="import-file">' + ICONS.upload + ' Seleccionar ficheiro JSON</label>' +
            '<input type="file" id="import-file" accept=".json" class="import-input-hidden">' +
            '<span class="import-file-name" id="import-file-name">Ningún ficheiro seleccionado</span>' +
          '</div>' +
          '<button class="btn btn-danger" id="btn-import-data" disabled>' + ICONS.upload + ' Importar datos</button>' +
        '</div>' +
        '<div class="form-actions">' +
          '<button type="button" class="btn btn-cancel" data-action="close-dialog">Pechar</button>' +
        '</div>' +
      '</div>' +
    '</div>' +
  '</div>';
}

/** Diálogo modal para ver TODAS as entradas dun feed (cando hai moitas) */
function renderSeeAllEntriesDialog() {
  var feed = App.currentFeed;
  if (!feed) return '';
  var feedName = escapeHtml(feed.name);
  var entries = feed.entries || [];
  var entriesHtml = '';
  if (entries.length === 0) {
    entriesHtml = '<div class="changelog-empty"><div class="changelog-empty-icon">&#127911;</div><p>Non hai entradas neste feed.</p></div>';
  } else {
    for (var i = 0; i < entries.length; i++) {
      entriesHtml += renderEntry(entries[i], feed.id);
    }
  }
  return '<div class="modal-overlay active"><div class="modal modal-lg"><div class="modal-header"><h2 class="modal-title">Todas as entradas — ' + feedName + '</h2><button class="modal-close" data-action="close-dialog">&times;</button></div><div class="modal-body"><div style="max-height:500px;overflow-y:auto;">' + entriesHtml + '</div><div class="form-actions"><button type="button" class="btn btn-cancel" data-action="close-dialog">Pechar</button></div></div></div></div>';
}

// ============================================================================
// 9. FUNCIONS DO REPRODUTOR DE RADIO
// ============================================================================

/**
 * Alternar reprodución/deter do streaming de radio.
 * Se o audio está en silencio co volume a 0, actívao a 80%.
 */
function togglePlay() {
  if (audio.paused) {
    // Se está silenciado, activar o audio
    if (audio.muted && audio.volume === 0) {
      audio.volume = 0.8;
      audio.muted = false;
    } else if (audio.muted) {
      audio.muted = false;
    }
    audio.play().catch(function() {
      showToast('Non se puido iniciar a reprodución', 'error');
    });
  } else {
    audio.pause();
  }
  updatePlayerUI();
}

/**
 * Manexa o cambio de volume desde o slider.
 *
 * @param {Event} e - Evento de cambio do input range
 */
function handleVolumeChange(e) {
  var val = parseInt(e.target.value, 10);
  audio.volume = val / 100;
  if (val > 0) audio.muted = false;
  else audio.muted = true;
  updatePlayerUI();
}

/** Alternar silencio do reprodutor */
function toggleMute() {
  audio.muted = !audio.muted;
  updatePlayerUI();
}

/**
 * Actualiza a interface do reprodutor sen re-renderizar todo.
 * Cambia: clase "playing" do botón, texto de estado,
 * clase "muted" do botón de volume, e valor do slider.
 */
function updatePlayerUI() {
  var playBtn = document.querySelector('.play-button');
  var statusEl = document.getElementById('player-status');
  var muteBtn = document.querySelector('.volume-button');
  var slider = document.getElementById('volumeSlider');

  var isPlaying = !audio.paused && !audio.ended;

  // Actualizar botón play/pause
  if (playBtn) {
    playBtn.classList.toggle('playing', isPlaying);
  }

  // Actualizar texto de estado
  if (statusEl) {
    if (isPlaying) {
      statusEl.innerHTML = '&#9679; En directo';
      statusEl.className = 'live';
    } else {
      statusEl.innerHTML = 'Preme &#9654; para escoitar';
      statusEl.className = '';
    }
  }

  // Actualizar botón de silencio
  if (muteBtn) {
    muteBtn.classList.toggle('muted', audio.muted || audio.volume === 0);
  }

  // Actualizar valor do slider (só se non está a ser manipulado)
  if (slider && document.activeElement !== slider) {
    slider.value = audio.muted ? 0 : Math.round(audio.volume * 100);
  }
}

// Eventos do reprodutor: actualizar a UI cando cambia o estado
audio.addEventListener('play', updatePlayerUI);
audio.addEventListener('pause', updatePlayerUI);
audio.addEventListener('ended', updatePlayerUI);

// ============================================================================
// 10. LISTENER DE EVENTOS (delegación)
// ============================================================================
// Usa delegación de eventos: un só listener no document para todos os clics.
// Cada botón usa o atributo "data-action" para indicar que acción realizar.
// Isto é máis eficiente que engadir listeners a cada botón individual.
// ============================================================================

function initEventListeners() {
  // --- Delegación de clics por data-action ---
  document.addEventListener('click', function(e) {
    var target = e.target.closest('[data-action]');
    if (!target) return;

    var action = target.dataset.action;
    var feedId = target.dataset.feedId || null;
    var entryId = target.dataset.entryId || null;
    var slug = target.dataset.slug || null;

    e.preventDefault();

    switch (action) {
      // ---- Navegación / Autenticación ----
      case 'login':
        openDialog('login');
        break;

      case 'logout':
        handleLogout();
        break;

      // ---- Filtro temporal (manexado por data-filter) ----
      case 'filter':
        break;

      // ---- Acordeón (expandir/colapsar tarxeta de feed) ----
      case 'toggle-accordion':
        var card = target.closest('.card');
        if (card) card.classList.toggle('accordion-open');
        break;

      // ---- Ver todas as entradas dun feed ----
      case 'see-all-entries':
        if (feedId) {
          var saf = App.feeds.find(function(f) { return f.id === feedId; });
          if (saf) openDialog('seeAllEntries', saf);
        }
        break;

      // ---- CRUD de feeds ----
      case 'create-feed':
        openDialog('createFeed');
        break;

      case 'edit-feed':
        if (feedId) {
          var ef = App.feeds.find(function(f) { return f.id === feedId; });
          if (ef) openDialog('editFeed', ef);
        }
        break;

      case 'delete-feed':
        if (feedId) {
          var df = App.feeds.find(function(f) { return f.id === feedId; });
          if (df) openDialog('deleteFeed', df);
        }
        break;

      case 'confirm-delete':
        handleConfirmDelete();
        break;

      case 'add-entry':
        if (feedId) {
          var af = App.feeds.find(function(f) { return f.id === feedId; });
          if (af) openDialog('addEntry', af);
        }
        break;

      // ---- Eliminar entrada (con confirmación) ----
      case 'delete-entry':
        if (feedId && entryId) {
          handleDeleteEntry(feedId, entryId);
        }
        break;

      // ---- Editar entrada ----
      case 'edit-entry':
        if (feedId && entryId) {
          // Buscar a entrada nos datos do estado para pasar ao diálogo
          var editEntry = null;
          for (var ei = 0; ei < App.feeds.length; ei++) {
            if (App.feeds[ei].id === feedId && App.feeds[ei].entries) {
              for (var ej = 0; ej < App.feeds[ei].entries.length; ej++) {
                if (App.feeds[ei].entries[ej].id === entryId) {
                  editEntry = App.feeds[ei].entries[ej];
                  break;
                }
              }
              break;
            }
          }
          if (editEntry) openDialog('editEntry', { feedId: feedId, entry: editEntry });
        }
        break;

      // ---- RSS / XML ----
      case 'copy-rss':
        if (slug) copyToClipboard(getRssUrl(slug));
        break;

      case 'xml-preview':
        if (feedId) handleXmlPreview(feedId);
        break;

      case 'copy-xml':
        if (App.xmlContent) copyToClipboard(App.xmlContent);
        break;

      // ---- Rexistro de cambios ----
      case 'changelog':
        if (feedId) handleChangelog(feedId);
        break;

      // ---- Exportación / Importación ----
      case 'export-import':
        openDialog('exportImport');
        break;

      case 'export':
        exportData();
        break;

      // ---- Reprodutor de radio ----
      case 'toggle-play':
        togglePlay();
        break;

      case 'toggle-mute':
        toggleMute();
        break;

      // ---- Diálogos ----
      case 'close-dialog':
        closeDialogs();
        break;
    }
  });

  // --- Clics nas lapelas de filtro (data-filter) ---
  document.addEventListener('click', function(e) {
    var tab = e.target.closest('[data-filter]');
    if (!tab) return;

    e.preventDefault();
    var filter = tab.dataset.filter;
    if (filter && filter !== App.filter) {
      App.filter = filter;
      fetchFeeds(filter);
    }
  });

  // --- Cambio de volume (input range) ---
  document.addEventListener('input', function(e) {
    if (e.target.id === 'volumeSlider') {
      handleVolumeChange(e);
    }
  });

  // --- Delegación de envío de formularios ---
  document.addEventListener('submit', function(e) {
    var form = e.target.closest('.modal-body');
    if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') return;

    e.preventDefault();
    var formId = form.id;
    var formData = new FormData(form);

    // Segundo o ID do formulario, chamamos ao handler correspondente
    switch (formId) {
      case 'form-login':
        handleLoginSubmit(formData);
        break;
      case 'form-create-feed':
        handleCreateFeedSubmit(formData);
        break;
      case 'form-edit-feed':
        handleEditFeedSubmit(formData);
        break;
      case 'form-add-entry':
        handleAddEntrySubmit(formData);
        break;
      case 'form-edit-entry':
        handleEditEntrySubmit(formData);
        break;
    }
  });

  // --- Cambio de ficheiro de importación ---
  document.addEventListener('change', function(e) {
    if (e.target.id === 'import-file') {
      var btn = document.getElementById('btn-import-data');
      var nameEl = document.getElementById('import-file-name');
      if (e.target.files && e.target.files.length > 0) {
        if (btn) btn.disabled = false;
        if (nameEl) nameEl.textContent = e.target.files[0].name;
      } else {
        if (btn) btn.disabled = true;
        if (nameEl) nameEl.textContent = 'Ningún ficheiro seleccionado';
      }
    }
  });

  // --- Clic no botón de importación ---
  document.addEventListener('click', function(e) {
    if (e.target.id === 'btn-import-data' || e.target.closest('#btn-import-data')) {
      handleImportData();
    }
  });

  // --- Pechar diálogos con Escape ---
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      var activeKeys = Object.keys(App.dialogs);
      for (var i = 0; i < activeKeys.length; i++) {
        if (App.dialogs[activeKeys[i]]) {
          closeDialogs();
          return;
        }
      }
    }
  });

  // --- Pechar diálogos ao facer clic fóra (no overlay) ---
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay') && e.target.classList.contains('active')) {
      closeDialogs();
    }
  });
}

// ============================================================================
// 11. XESTORES DE ACCIÓNS (handlers dos formularios)
// ============================================================================
// Cada handler: recolle datos do formulario, valida, chama á API,
// pecha o diálogo e recarga os feeds.
// ============================================================================

/** Handler do formulario de inicio de sesión */
async function handleLoginSubmit(formData) {
  var username = (formData.get('username') || '').trim();
  var password = formData.get('password') || '';
  if (!username || !password) {
    showToast('Introduce usuario e contrasinal', 'error');
    return;
  }
  try {
    await login(username, password);
    closeDialogs();
    renderHeader();
    fetchFeeds(App.filter);
  } catch (err) {
    showToast(err.message, 'error');
  }
}

/** Handler do formulario de creación de feed */
async function handleCreateFeedSubmit(formData) {
  var name = (formData.get('name') || '').trim();
  var description = (formData.get('description') || '').trim();
  if (!name) {
    showToast('O nome do feed é obrigatorio', 'error');
    return;
  }
  try {
    await createFeed(name, description);
    closeDialogs();
    fetchFeeds(App.filter);
  } catch (err) {
    showToast(err.message, 'error');
  }
}

/** Handler do formulario de edición de feed */
async function handleEditFeedSubmit(formData) {
  var id = App.currentFeed ? App.currentFeed.id : null;
  var name = (formData.get('name') || '').trim();
  var description = (formData.get('description') || '').trim();
  if (!id || !name) {
    showToast('O nome do feed é obrigatorio', 'error');
    return;
  }
  try {
    await editFeed(id, name, description);
    closeDialogs();
    fetchFeeds(App.filter);
  } catch (err) {
    showToast(err.message, 'error');
  }
}

/** Handler do formulario de engadir entrada */
async function handleAddEntrySubmit(formData) {
  var feedId = App.currentFeed ? App.currentFeed.id : null;
  var title = (formData.get('title') || '').trim();
  var description = (formData.get('description') || '').trim();
  var audioUrl = (formData.get('audioUrl') || '').trim();
  if (!feedId || !title) {
    showToast('O título é obrigatorio', 'error');
    return;
  }
  if (!audioUrl) {
    showToast('A URL do audio é obrigatoria', 'error');
    return;
  }
  try {
    await addEntry(feedId, title, description, audioUrl);
    closeDialogs();
    fetchFeeds(App.filter);
  } catch (err) {
    showToast(err.message, 'error');
  }
}

/** Handler do formulario de edición de entrada */
async function handleEditEntrySubmit(formData) {
  var feedId = formData.get('feedId');
  var entryId = formData.get('entryId');
  var title = (formData.get('title') || '').trim();
  var description = (formData.get('description') || '').trim();
  var audioUrl = (formData.get('audioUrl') || '').trim();
  if (!feedId || !entryId || !title) {
    showToast('Datos incorrectos', 'error');
    return;
  }
  try {
    await editEntry(feedId, entryId, title, description, audioUrl);
    closeDialogs();
    fetchFeeds(App.filter);
  } catch (err) {
    showToast(err.message, 'error');
  }
}

/** Handler de confirmación de eliminación de feed */
async function handleConfirmDelete() {
  var feed = App.currentFeed;
  if (!feed) return;
  try {
    await deleteFeed(feed.id);
    closeDialogs();
    fetchFeeds(App.filter);
  } catch (err) {
    showToast(err.message, 'error');
  }
}

/** Handler de eliminación de entrada (con confirmación do navegador) */
async function handleDeleteEntry(feedId, entryId) {
  if (!window.confirm('Seguro que queres eliminar esta entrada?')) return;
  try {
    await deleteEntry(feedId, entryId);
    fetchFeeds(App.filter);
  } catch (err) {
    showToast(err.message, 'error');
  }
}

/** Handler de previsualización XML (carga e mostra o XML dun feed) */
async function handleXmlPreview(feedId) {
  var feed = App.feeds.find(function(f) { return f.id === feedId; });
  if (feed) openDialog('xmlPreview', feed);
  try {
    await getXmlPreview(feedId);
    renderDialogs();
  } catch (err) {
    showToast(err.message, 'error');
    closeDialogs();
  }
}

/** Handler do rexistro de cambios (carga e mostra os cambios dun feed) */
async function handleChangelog(feedId) {
  var feed = App.feeds.find(function(f) { return f.id === feedId; });
  if (feed) openDialog('changelog', feed);
  try {
    await getChangelog(feedId);
    renderDialogs();
  } catch (err) {
    showToast(err.message, 'error');
    closeDialogs();
  }
}

/** Handler de peche de sesión */
async function handleLogout() {
  try {
    await logout();
  } catch (err) {
    showToast(err.message, 'error');
  }
  renderHeader();
  renderMain();
}

/** Handler de importación de datos (lectura do ficheiro, validación, envío á API) */
async function handleImportData() {
  var fileInput = document.getElementById('import-file');
  if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
    showToast('Selecciona un ficheiro JSON primeiro', 'error');
    return;
  }
  var file = fileInput.files[0];
  if (!file.name.endsWith('.json') && file.type !== 'application/json') {
    showToast('O ficheiro debe ser de tipo JSON', 'error');
    return;
  }
  try {
    var text = await file.text();
    JSON.parse(text);   // Validar que é JSON válido
    if (!window.confirm('Seguro que queres importar os datos? Isto sobreescribirá os datos existentes.')) return;
    await importData(text);
    closeDialogs();
    fetchFeeds(App.filter);
    renderHeader();
  } catch (err) {
    if (err instanceof SyntaxError) {
      showToast('O ficheiro non contén JSON válido', 'error');
    } else {
      showToast(err.message, 'error');
    }
  }
}

// ============================================================================
// 12. INICIALIZACIÓN DA APLICACIÓN
// ============================================================================

/**
 * Inicializa a aplicación ao cargar a páxina (DOMContentLoaded).
 * 1. Renderiza todos os compoñentes da UI
 * 2. Inicializa os listeners de eventos
 * 3. Comproba a sesión do usuario
 * 4. Carga os feeds co filtro actual
 */
async function initApp() {
  // Renderizar a estrutura inicial da páxina
  renderHeader();
  renderMain();
  renderPlayer();
  renderFooter();

  // Activar todos os listeners de eventos
  initEventListeners();

  // Comprobar se hai sesión activa e actualizar a cabeceira
  await checkSession();
  renderHeader();

  // Cargar os feeds co filtro temporal seleccionado
  await fetchFeeds(App.filter);
}

// Arrancar a aplicación cando o DOM estea listo
document.addEventListener('DOMContentLoaded', initApp);
