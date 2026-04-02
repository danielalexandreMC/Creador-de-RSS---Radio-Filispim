# Rádio FilispiM — Xestor de RSS

> Aplicación web para a creación e xestión de feeds RSS pensada para a **Rádio FilispiM**, a radio libre e comunitaria da Terra de Trasancos (102.3 FM). Desenvolvida por [ideia.gal](https://ideia.gal).

---

## Descrición

**Rádio FilispiM — Xestor de RSS** é unha aplicación web lixeira e autónoma que permite crear, editar e xestionar feeds RSS para os programas e podcast da emisora. Está pensada para radios comunitarias e de libre que necesitan distribuír os seus contidos a través de plataformas de podcast (Spotify, Apple Podcasts, Google Podcasts, Ivoox, etc.) mediante feeds RSS estándar.

A aplicación ten unha interface completa en **galego (gl)**, cun tema escuro corporativo inspirado na web oficial da emisora. Non precisa base de datos: usa ficheiros JSON para o almacenamento, o que simplifica enormemente a instalación en calquera hosting compartido.

Entre as súas funcionalidades principais están: a creación e xestión de feeds RSS, a subida de episodios con detección automática do tipo de audio, un reprodutor de streaming en directo integrado, sistema de autenticación con contrasinais seguros (bcrypt), acoesón para ver/ocultar entradas, modal para ver todas as entradas dun feed, previsualización XML, copia de enlaces RSS con un clic, rexistro de cambios (changelog), e exportación/importación de datos en JSON.

---

## Características principais

### Xestión de feeds RSS

- **Creación e xestión de feeds RSS para podcasts** — Crea feeds RSS para cada programa ou podcast da emisora con nome e descrición personalizados.
- **URLs amigables** — Cada feed obtén un slug único e lexible (ex: `meu-programa-galego`) que se usa na URL pública.
- **Detección automática do tipo de audio** — A aplicación detecta automaticamente se o audio é MP3, OGG, WAV, FLAC ou M4A segundo a extensión da URL, e asigna o tipo MIME correcto á etiqueta `<enclosure>` do RSS.
- **Entradas ilimitadas** — Engade todos os episodios que necesites a cada feed. Non hai límite de entradas.
- **Orde cronolóxica** — Os episodios ordénanse automaticamente por data de publicación (máis recente primeiro).
- **RSS 2.0 válido con namespace Atom** — Os feeds xéranse cumprindo a especificación RSS 2.0, co namespace Atom para auto-referencia, etiqueta `<language>` configurada como `gl` (galego), e etiquetas `<guid>` únicas por episodio.

### Interface de usuario

- **Interface en galego con tema escuro corporativo** — Toda a aplicación está en galego, con cor vermella institucional da Rádio FilispiM e fondo escuro para confort visual.
- **Reprodutor de streaming en directo integrado** — Reproduce o streaming en directo de CUAC FM directamente dende a interface, con controles de play/pause e volume.
- **Acordeón para ver/ocultar entradas** — Cada tarxeta de feed mostra a primeira entrada de forma visible; as seguintes están ocultas nun acordeón desplegable co chevron.
- **Modal "Ver todas" para feeds con moitas entradas** — Cando un feed ten máis de 5 entradas, aparece un botón "Ver todas as N entradas" que abre un modal con todas as entradas desprazables.
- **Editor de entradas (título, descrición, URL de audio)** — Edita calquera entrada existente directamente dende a interface sen ter que eliminala e volver crear.
- **Previsualización XML do feed** — Consulta o código fonte XML do feed RSS xerado, nun bloque de código monoespazado, e cópiao ao portapapeis.
- **Copia de enlace RSS con un clic** — Cada tarxeta ten un botón "RSS" que copia a URL pública do feed ao portapapeis do usuario.
- **Totalmente responsivo (móbil, tablet, escritorio)** — A interface adaptaase a calquera tamaño de pantalla, desde móbiles pequenos ata pantallas grandes de escritorio.
- **Indicador "NO AR" animado** — Badge na cabeceira con punto pulsante que indica que a emisora está emitindo en directo.

### Administración

- **Sistema de autenticación con sesións seguras (bcrypt)** — O contrasinal do administrador gárdase con hash bcrypt, o algoritmo máis seguro para contrasinais.
- **Rexistro de cambios (changelog) por feed** — Cada acción (crear, editar, eliminar feeds ou entradas) rexístrase automaticamente nun historial consultable.
- **Exportación/importación de datos en JSON** — Descarga unha copia de seguranza de todos os datos en formato JSON, ou impórta unha copia anterior para restaurar.
- **Contador de accesos por feed** — Cada vez que un lector de podcast ou agregador RSS accede ao feed, incrémentase un contador de accesos visible na tarxeta.
- **Filtro temporal (7, 15, 30 días ou todos)** — Filtra os feeds mostrados segundo a data da súa última actualización.

### Técnico

- **Resposta rápida, sen base de datos (almacenamento JSON)** — Os datos gárdanse en `data/data.json`, sen necesidade de MySQL ou calquera outra base de datos.
- **Funciona en calquera subdirectorio** — A aplicación detecta automaticamente a ruta base, polo que funciona tanto en `/` como en `/rfm/rss/` ou calquera outro subdirectorio.
- **Compatible con hosting compartido (PHP 7.4+)** — Soporta calquera hosting con PHP 7.4 ou superior, incluídos os máis económicos.
- **Protección do directorio de datos con .htaccess** — O directorio `data/` está protexido contra o acceso directo dende o navegador web mediante un ficheiro `.htaccess`.
- **Bloqueo de ficheiros para evitar corrupción de datos** — Usa `flock()` para bloquear o ficheiro de datos durante as operacións de lectura/escritura, evitando condicións de carreira.
- **Cabeceiras de seguranza HTTP** — Inclúe `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection` e `Referrer-Policy`.
- **Compresión GZIP** — Os recursos estáticos (CSS, JS, JSON, RSS) comprídense automaticamente para reducir o tempo de transferencia.

---

## Requisitos

### Requisitos do servidor

- **PHP 7.4 ou superior** — Require a versión 7.4 ou posterior de PHP. Probado con PHP 7.4, 8.0, 8.1, 8.2 e 8.3.
- **Extensión JSON** — Necesaria para a función `json_encode()`/`json_decode()`. Incluída por defecto en case todas as instalacións de PHP.
- **Extensión de contrasinais (bcrypt)** — Necesaria para a función `password_hash()`. Incluída por defecto en PHP 7.4+.
- **Sesións PHP** — Necesaria para `session_start()`. Normalmente activada por defecto.
- **Permisos de escritura** — PHP necesita permisos de escritura no directorio da aplicación para crear o directorio `data/` e o ficheiro `data.json`.
- **Apache con mod_rewrite** — Recomendado pero non obrigatorio (sen el, só funciona o index.php directamente).

### Requisitos do cliente

- **Navegador moderno** — Chrome, Firefox, Safari, Edge (últimas 2 versións).
- **JavaScript activado** — A interface constrúese dinámicamente con JavaScript.
- **Conexión a internet** — Para o streaming en directo e para que os lectores de podcast poidan acceder aos ficheiros de audio.

---

## Instalación

### Paso 1: Subir os ficheiros ao servidor

Descomprime o ficheiro `radiofilispim-rss.zip` e sube todo o contido do directorio `radiofilispim-rss/` ao teu servidor web mediante FTP, SFTP ou o xestor de ficheiros do teu hosting.

A estrutura de ficheiros debe quedar así no servidor:

```
/public_html/radiofilispim-rss/
├── index.php
├── api.php
├── rss.php
├── install.php
├── .htaccess
├── css/
│   └── style.css
├── js/
│   └── app.js
├── data/
│   └── .htaccess
└── README.md
```

> **Importante**: O directorio `data/` debe ter permisos de escritura para o usuario de PHP (normalmente `www-data` ou o usuario do teu hosting).

Se os permisos son incorrectos, podes axustarlos dende SSH:

```bash
chmod 755 radiofilispim-rss/
chmod 755 radiofilispim-rss/data/
```

Ou contacta co teu provedor de hosting se non tes acceso SSH.

### Paso 2: Acceder a install.php

Abre o navegador web e navega até:

```
https://tudominio.com/radiofilispim-rss/install.php
```

( cambia `tudominio.com` e a ruta polo teu dominio e directorio reais ).

Verás unha páxina de instalación que mostra:

1. **Comprobación de requisitos** — Unha lista verde/vermella indicando se o teu servidor cumpre os requisitos.
2. **Formulario de creación de usuario** — Campos para:
   - **Nome de usuario** (mínimo 3 caracteres, por defecto "admin")
   - **Contrasinal** (mínimo 6 caracteres, recomendamos usar un xerador de contrasinais forte)
   - **Confirmar contrasinal** (debe coincidir co anterior)

Fai clic en **"Crear usuario e instalar"**.

### Paso 3: Eliminar install.php por seguranza

Unha vez completada a instalación correctamente, **elimina o ficheiro `install.php` do servidor**. Isto evita que alguén poida volver a executar a instalación e sobreescribir os teus datos.

Podes eliminalo dende o xestor de ficheiros do teu hosting ou por SSH:

```bash
rm radiofilispim-rss/install.php
```

### Paso 4: Comezar a usar a aplicación

Navega ata:

```
https://tudominio.com/radiofilispim-rss/
```

Fai clic en **"Acceder"** e introduce as credenciais que creaches no paso 2.

---

## Estrutura de ficheiros

```
radiofilispim-rss/
│
├── index.php              # Páxina principal (shell HTML que carga a interface JS)
│
├── api.php                # API REST — Manexa todas as peticións AJAX do cliente
│                           # Accións: sesión, feeds, entradas, exportación, etc.
│
├── rss.php                # Endpoint público RSS 2.0 — Serve o XML aos lectores
│                           # de podcast e agregadores RSS
│
├── install.php            # Script de instalación (únase, eliminar despois)
│                           # Crea o usuario admin e o ficheiro de datos
│
├── .htaccess              # Configuración de Apache: seguridade, caché, compresión
│
├── css/
│   └── style.css          # Follas de estilo: tema escuro, responsivo, animacións
│
├── js/
│   └── app.js             # JavaScript principal: interface, API, reprodutor, diálogos
│
├── data/
│   ├── .htaccess          # Protección do directorio de datos (Deny from all)
│   ├── data.json          # Base de datos JSON (creado por install.php)
│   └── data.lock          # Ficheiro de bloqueo para evitar corrupción (auto-xerado)
│
└── README.md              # Este ficheiro de documentación
```

### Descrición detallada de cada ficheiro

| Ficheiro | Descrición |
|----------|-------------|
| `index.php` | Páxina principal. É un "shell" HTML baleiro que carga os ficheiros CSS e JS. Toda a interface xerase dinámicamente con JavaScript. Redirixe a `install.php` se a aplicación non está instalada. |
| `api.php` | API principal. Recibe peticións AJAX (GET e POST) co parámetro `action` e executa a lógica correspondente. Xestiona: sesións, feeds (CRUD), entradas (CRUD), previsualización XML, changelog, exportación/importación. |
| `rss.php` | Endpoint público que xera e serve feeds RSS 2.0 en XML. É a URL que os lectores de podcast consultan. Inclúe contador de accesos e cabeceiras de non-caché. |
| `install.php` | Script de instalación de único uso. Comproba requisitos do servidor, crea o directorio `data/`, o ficheiro `.htaccess` de protección, e o ficheiro `data.json` co usuario administrador. |
| `.htaccess` | Configuración de Apache: reescritura de URLs, seguranza (X-Frame-Options, etc.), caché de ficheiros estáticos e compresión GZIP. As directivas php_value están comentadas para compatibilidade con DINAHOSTING. |
| `css/style.css` | Follas de estilo completas con variables CSS (design tokens), tema escuro, responsivo, animacións (pulse, glow, spin), estilos de formularios, modais, toasts e o reprodutor de radio. |
| `js/app.js` | Aplicación JavaScript SPA (Single Page Application) sen framework. Contén: estado global, utilidades, chamadas á API, renderizado da interface, xestión de diálogos, reprodutor de radio, delegación de eventos, e inicialización. |
| `data/.htaccess` | Denega o acceso web directo ao directorio de datos, protexendo o ficheiro `data.json` contra visualización non autorizada. |
| `data/data.json` | Base de datos principal en formato JSON. Contén: datos do administrador (con hash bcrypt), lista de feeds con entradas, e rexistro de cambios. Xerase automaticamente pola aplicación. |

---

## Uso

### Crear un novo feed RSS

1. Accede á aplicación e inicia sesión coas túas credenciais de administrador.
2. Fai clic no botón **"Crear novo feed"** (botón vermello con icona +).
3. No formulario que se abre, introduce:
   - **Nome do feed** *(obrigatorio)*: O nome do programa ou podcast (ex: "O Noso Programa").
   - **Descrición** *(opcional)*: Unha breve descrición do contido do feed.
4. Fai clic en **"Crear feed"**.

A aplicación xerará automaticamente un **slug** (URL amigable) a partires do nome. Por exemplo, "O Noso Programa" → `o-noso-programa`.

### Engadir un episodio (entrada) a un feed

1. Na tarxeta do feed desexado, fai clic no botón verde **(+)** da cabeceira.
2. No formulario que se abre, introduce:
   - **Título** *(obrigatorio)*: O título do episodio (ex: "Entrevista con Xan Carballo").
   - **Descrición** *(opcional)*: Un resumo do contido do episodio.
   - **URL do audio** *(obrigatorio)*: O enlace directo ao ficheiro de audio (ex: `https://exemplo.com/episodio-001.mp3`). Pode ser MP3, OGG, WAV, FLAC ou M4A.
3. Fai clic en **"Engadir entrada"**.

O episodio engadirase ao principio da lista (como o máis recente).

### Editar unha entrada existente

1. Na lista de entradas dun feed, fai clic na icona do **lapis** (azul) á dereita da entrada.
2. No formulario que se abre, modifica os campos que queiras cambiar.
3. Se deixas o campo **URL do audio** baleiro, manterase a URL actual.
4. Fai clic en **"Gardar cambios"**.

### Editar un feed

1. Na tarxeta do feed, fai clic na icona do **lapis** (azul) da cabeceira.
2. Modifica o nome e/ou descrición.
3. Fai clic en **"Gardar"**.

### Eliminar un feed ou entrada

- **Eliminar feed**: Fai clic na icona da **papeleira** (vermella) da cabeceira da tarxeta. Confirma a eliminación no diálogo.
- **Eliminar entrada**: Fai clic na icona da **papeleira** (vermella) á dereita da entrada. Confirma no diálogo do navegador.

### Ver/ocultar entradas dun feed (acordeón)

- A primeira entrada de cada feed é sempre visible.
- As seguintes están ocultas. Fai clic na **frecha (chevron)** da cabeceira da tarxeta para despregar o acordeón e ver ata 4 entradas adicionais.
- Fai clic de novo para colapsar as entradas.

### Ver todas as entradas dun feed

Se un feed ten máis de 5 entradas, aparecerá un botón **"Ver todas as N entradas →"** ao final do acordeón. Fai clic nel para abrir un modal con todas as entradas desprazables.

### Copiar o enlace RSS dun feed

Cada tarxeta ten un botón **"RSS"** con icona de enlace. Fai clic nel para copiar a URL pública do feed RSS ao portapapeis. Esta é a URL que debes engadir aos lectores de podcast:

```
https://tudominio.com/radiofilispim-rss/rss.php?feed=slug-do-feed
```

### Ver a previsualización XML

Fai clic no botón **"XML"** da cabeceira da tarxeta. Abrirase un modal co código fonte XML do feed RSS, que podes revisar ou copiar.

### Ver o rexistro de cambios (changelog)

Fai clic na icona do **calendario** (gris) da cabeceira da tarxeta. Verás unha lista de todas as accións realizadas no feed (creación, edición, eliminación de feeds e entradas), con data e detalles.

### Filtrar feeds por data de actualización

Usa as lapelas na parte superior:
- **Últimos 7 días** — Só mostra feeds actualizados nos últimos 7 días.
- **+15 días** — Só mostra feeds actualizados nos últimos 15 días.
- **+30 días** — Só mostra feeds actualizados nos últimos 30 días.
- **Todos** — Mostra todos os feeds sen filtro temporal.

### Exportar datos (copia de seguranza)

1. Fai clic no botón **"Datos"** (preto de "Crear novo feed").
2. No diálogo que se abre, fai clic en **"Exportar datos"**.
3. Descargarase un ficheiro JSON con todos os feeds e entradas.

### Importar datos (restaurar copia)

1. Fai clic no botón **"Datos"**.
2. No diálogo, fai clic en **"Seleccionar ficheiro JSON"** e escolle o ficheiro de copia de seguranza.
3. Fai clic en **"Importar datos"** (vermello).
4. Confirma a importación no diálogo.

**Importante**: A importación non sobreescribe as credenciais do administrador. Se un feed xa existe (polo slug), só se engaden as entradas novas (non se duplican).

### Cambiar o contrasinal

Para cambiar o contrasinal do administrador, contacta co desenvolvedor ou usa a API directamente. Esta funcionalidade está dispoñible na API coa acción `reset_password`.

### Escoitar a radio en directo

O reprodutor de radio está fixo na parte inferior da pantalla. Fai clic no botón vermello **(▶)** para comezar a escoitar o streaming en directo de CUAC FM (102.3 FM). Usa o control deslizante para axustar o volume e a icona do altofalante para silenciar.

---

## URLs RSS

### Como funcionan as URLs RSS

Cada feed RSS ten unha URL pública única baseada no seu **slug**:

```
https://tudominio.com/radiofilispim-rss/rss.php?feed=slug-do-feed
```

O parámetro `feed` indica que feed RSS se quere obter. Exemplos:

| Feed (nome) | Slug | URL RSS |
|-------------|------|---------|
| O Noso Programa | o-noso-programa | `rss.php?feed=o-noso-programa` |
| Podcast Galego | podcast-galego | `rss.php?feed=podcast-galego` |
| Música na Terra | musica-na-terra | `rss.php?feed=musica-na-terra` |

### Como engadir o feed a plataformas de podcast

Cada plataforma ten unha forma diferente de engadir feeds RSS, pero en xeral:

1. Copia a URL do feed RSS (botón "RSS" na tarxeta).
2. Vai á plataforma de podcast (Spotify for Podcasters, Apple Podcasts Connect, Ivoox, etc.).
3. Busca a opción "Engadir feed RSS" ou "Add RSS feed".
4. Pega a URL do feed e confirme.

A plataforma verificará o feed e, se todo é correcto, comezará a mostrar os teus episodios.

### Validación do feed RSS

Podes validar que o teu feed RSS é correcto usando ferramentas en liña como:

- **[W3C Feed Validation Service](https://validator.w3.org/feed/)** — O validador oficial do W3C.
- **[Cast Feed Validator](https://castfeedvalidator.com/)** — Validador específico para podcasts.
- **[Podbase](https://podba.se/validate/)** — Outro validador popular de podcasts.

---

## Seguridade

A aplicación implementa varias capas de seguranza para protexer os datos e o acceso á administración:

### Autenticación

- **Contrasinais con hash bcrypt** — Os contrasinais gárdanse usando a función `password_hash()` de PHP co algoritmo `PASSWORD_DEFAULT` (bcrypt). O contrasinal en texto plano nunca se almacena nin se envía polo cable.
- **Sesións PHP seguras** — A autenticación xestiónase con sesións PHP. O ID de sesión almacénase na variable `$_SESSION['filispim_admin_id']`.
- **Verificación de sesión en cada petición** — Todas as accións que modifican datos (crear, editar, eliminar) requiren autenticación. Se a sesión non é válida, devólvese un erro 401.

### Protección de datos

- **Directorio `data/` protexido con `.htaccess`** — O ficheiro `data/.htaccess` contén `Deny from all`, impedindo que alguén acceda directamente a `data.json` dende o navegador web.
- **Bloqueo de ficheiros (flock)** — Todas as operacións de lectura e escritura usan `flock()` con bloqueo compartido (lectura) ou exclusivo (escritura) para evitar que dous procesos PHP corrompan o ficheiro de datos simultaneamente.
- **Ficheiro `.htaccess` principal con seguridade** — Inclúe:
  - `Options -Indexes` para desactivar o listado de directorios.
  - Bloqueo de acceso a ficheiros ocultos (comezan por punto).
  - Cabeceiras HTTP de seguranza: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`.
- **Escape de saída** — Todos os datos dinámicos que se inseren no HTML (JavaScript) ou XML (PHP) escápase correctamente para previr inxeccións XSS.
- **Validación de entrada** — A API valida todos os parámetros recibidos (campos obrigatorios, formato de URL, lonxitude de contrasinal, etc.).
- **Non se exporta o contrasinal** — A función de exportación exclúe o hash do contrasinal do administrador.

### Post-instalación

- **Eliminar `install.php`** — Despois da instalación, recoméndase eliminar este ficheiro para que ninguén poida volver a executar a instalación.
- **Protección de `install.php`** — Mesmo antes de eliminar, o `.htaccess` inclúe directivas para protexer ficheiros sensibles.

---

## Resolución de problemas

### "A aplicación non está instalada. Executa install.php."

- Asegúrate de que o ficheiro `install.php` existe e que o podes acceder a el dende o navegador.
- Comproba que o directorio ten permisos de escritura para PHP.
- Se xa instalaches a aplicación antes, comproba que `data/data.json` existe no servidor.

### "Non se puido crear o directorio de datos"

- O directorio onde está instalada a aplicación necesita permisos de escritura (chmod 755 ou superior).
- Contacta co teu provedor de hosting se non podes cambiar os permisos.
- En alguns hostings, o directorio debe pertencer ao usuario `www-data`.

### "O ficheiro de datos non se pode escribir"

- Comproba os permisos do directorio `data/` (debe ser 755).
- Comproba que non hai un ficheiro `.htaccess` no directorio pai que restrinxas as operacións de PHP.
- Nalgúns hostings, podes necesitar crear o directorio `data/` manualmente por FTP antes de executar a instalación.

### "Non se puideron cargar os feeds" / Erro 503

- O ficheiro `data/data.json` non existe. Executa a instalación primeiro.
- Comproba que `api.php` e `data/data.json` están no mesmo directorio.

### As URLs RSS non funcionan nos lectores de podcast

- Comproba que o enlace RSS é correcto: `https://tudominio.com/ruta/radiofilispim-rss/rss.php?feed=slug`.
- Proba abrir a URL directamente no navegador — deberías ver o código XML do RSS.
- Se ves un erro, comproba os logs do servidor PHP.
- Algúns hostings bloquean o acceso a `rss.php` se non tes `mod_rewrite` activado.

### O reprodutor de radio non funciona

- O streaming require conexión a internet.
- O audio pode estar bloqueado polo navegador se a páxina se serve por HTTP (non HTTPS) e o streaming é HTTPS.
- Proba actualizar o navegador á última versión.

### A interface non se carga correctamente

- Comproba que JavaScript está activado no navegador.
- Abre a consola do navegador (F12) para ver se hai erros.
- Comproba que os ficheiros `css/style.css` e `js/app.js` se cargan correctamente.

### Os filtros temporais non funcionan

- Os filtros baséanse na data `updated_at` do feed. Se ningún feed se actualizou recentemente, o filtro pode devolver resultados baleiros.
- Crea ou actualiza un feed e volve a probar.

### Quero cambiar a ruta da aplicación

- A aplicación detecta automaticamente a ruta base. Se moves os ficheiros a outro subdirectorio, non tes que configurar nada — simplemente move todo o contido e actualiza os teus marcadores de podcast coas novas URLs RSS.

---

## Licenza

Este software é **gratuíto e de código aberto** para uso comunitario. Podes usalo, modificalo e distribuílo libremente para a túa radio comunitaria, asociación cultural ou calquera outro proxecto sen ánimo de lucro.

Non se require atribución obrigatoria, pero agradecemos que mención a **Rádio FilispiM** e a [ideia.gal](https://ideia.gal) se usas esta aplicación.

---

## Créditos

- **Rádio FilispiM** — A radio libre e comunitaria da Terra de Trasancos.
- **102.3 FM** — Frecuencia de emisión en FM.
- **CUAC FM** — Emisora que aloxa o streaming en directo.
- **[ideia.gal](https://ideia.gal)** — Deseño e desenvolvemento da aplicación.

---

## Soporte técnico

Para problemas de instalación ou uso, contacta co equipo de [ideia.gal](https://ideia.gal) ou abre unha incidencia no repositorio do proxecto.

---

*Rádio FilispiM — A voz libre da Terra de Trasancos desde o 102.3 FM*
