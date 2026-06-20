/**
 * OBSChurch WebSocket Server
 * @module ws-server
 * @version 2.0.0
 *
 * Vai trò: Hub trung tâm — nhận lệnh từ Control Panel,
 * broadcast tới tất cả clients (kể cả OBS Browser Source).
 *
 * v2.0: Data moved to /data/, thêm scene-types, bindings, config APIs.
 */

'use strict';

const { WebSocketServer } = require('ws');
const http = require('http');
const fs   = require('fs');
const path = require('path');

// OBS WebSocket v5 bridge (obs-websocket-js)
let OBSWebSocket;
try { OBSWebSocket = require('obs-websocket-js').default; } catch(_) {}

// ─── Constants ───────────────────────────────────────
const PORT = process.env.PORT || 3001;
const PING_MS = 20_000;

// Tất cả data đọc từ /data/ (gốc project)
const DATA_DIR     = path.join(__dirname, '..', 'data');
const WIDGETS_PATH = path.join(__dirname, '..', 'widgets');

const PATHS = {
  scenes:       path.join(DATA_DIR, 'scenes.json'),
  layouts:      path.join(DATA_DIR, 'layouts.json'),
  sceneTypes:   path.join(DATA_DIR, 'scene-types.json'),
  bindings:     path.join(DATA_DIR, 'widget-bindings.json'),
  config:       path.join(DATA_DIR, 'service-config.json'),
  songs:        path.join(DATA_DIR, 'songs.json'),
  presets:      path.join(DATA_DIR, 'overlay-presets.json'),
  themes:       path.join(DATA_DIR, 'themes.json'),
  categories:   path.join(WIDGETS_PATH, '_categories.json'),
};

// ─── Bootstrap ───────────────────────────────────────
const httpServer = http.createServer(_handleHttpRequest);
const wss        = new WebSocketServer({ server: httpServer });

/** @type {object} In-memory state */
const appState = {
  activeSceneId:   'chinh-le',
  activeSceneType: null,
  activeOverlayId: null,
  overlayVisible:  false,
  currentPayload:  {},
  tickerVisible:   false,
  tickerMessages:  [],
  isRecording:     false,
  isStreaming:     false,
  obsConnected:    false,
  obsScenes:       [],
  obsCurrentScene: null,
};

// ─── OBS WebSocket Bridge ────────────────────────────
let obsWS = null;
const obsState = { connected: false, host: '', password: '' };

async function obsConnect(host = 'localhost:4455', password = '') {
  if (!OBSWebSocket) {
    console.warn('[OBS] obs-websocket-js not installed');
    return { success: false, error: 'Library not installed' };
  }
  try {
    if (obsWS) { try { obsWS.disconnect(); } catch(_) {} }
    obsWS = new OBSWebSocket();

    obsWS.on('ConnectionOpened',   () => { console.log('[OBS] Connected'); });
    obsWS.on('ConnectionClosed',   () => {
      obsState.connected = false;
      appState.obsConnected = false;
      appState.obsCurrentScene = null;
      _broadcastAll({ type: 'OBS_STATE', payload: { connected: false } }, null);
      console.log('[OBS] Disconnected');
    });
    obsWS.on('CurrentProgramSceneChanged', ({ sceneName }) => {
      appState.obsCurrentScene = sceneName;
      _broadcastAll({ type: 'OBS_SCENE_CHANGED', payload: { scene: sceneName } }, null);
    });
    obsWS.on('RecordStateChanged', ({ outputActive }) => {
      appState.isRecording = outputActive;
      _broadcastAll({ type: outputActive ? 'OBS_RECORD_START' : 'OBS_RECORD_STOP', payload: {} }, null);
    });
    obsWS.on('StreamStateChanged', ({ outputActive }) => {
      appState.isStreaming = outputActive;
      _broadcastAll({ type: outputActive ? 'OBS_STREAM_START' : 'OBS_STREAM_STOP', payload: {} }, null);
    });

    const url = host.startsWith('ws') ? host : `ws://${host}`;
    await obsWS.connect(url, password);

    // Fetch initial state
    const { scenes, currentProgramSceneName } = await obsWS.call('GetSceneList');
    const sceneNames = scenes.map(s => s.sceneName);
    appState.obsConnected    = true;
    appState.obsScenes       = sceneNames;
    appState.obsCurrentScene = currentProgramSceneName;
    obsState.connected = true;
    obsState.host      = host;
    obsState.password  = password;

    _broadcastAll({
      type: 'OBS_STATE',
      payload: { connected: true, scenes: sceneNames, currentScene: currentProgramSceneName },
    }, null);

    return { success: true, scenes: sceneNames, currentScene: currentProgramSceneName };
  } catch (e) {
    console.error('[OBS] Connect error:', e.message);
    return { success: false, error: e.message };
  }
}

async function obsSwitchScene(sceneName) {
  if (!obsWS || !obsState.connected) return { success: false, error: 'Not connected' };
  try {
    await obsWS.call('SetCurrentProgramScene', { sceneName });
    return { success: true };
  } catch(e) { return { success: false, error: e.message }; }
}

async function obsStartRecord() {
  if (!obsWS || !obsState.connected) return;
  try { await obsWS.call('StartRecord'); } catch(e) {}
}
async function obsStopRecord() {
  if (!obsWS || !obsState.connected) return;
  try { await obsWS.call('StopRecord'); } catch(e) {}
}
async function obsStartStream() {
  if (!obsWS || !obsState.connected) return;
  try { await obsWS.call('StartStream'); } catch(e) {}
}
async function obsStopStream() {
  if (!obsWS || !obsState.connected) return;
  try { await obsWS.call('StopStream'); } catch(e) {}
}

// ─── HTTP Router ─────────────────────────────────────
function _handleHttpRequest(req, res) {
  _setCorsHeaders(res);
  if (req.method === 'OPTIONS') { res.writeHead(204).end(); return; }

  const { method, url } = req;

  // ── GET endpoints ──
  const getMap = {
    '/api/scenes':            _handleGetScenes,
    '/api/layouts':           _handleGetLayouts,
    '/api/state':             (_, r) => _jsonOk(r, appState),
    '/api/scene-types':       _handleGetSceneTypes,
    '/api/bindings':          _handleGetBindings,
    '/api/config':            _handleGetConfig,
    '/api/songs':             (_, r) => _readJson(PATHS.songs, r),
    '/api/presets':           (_, r) => _readJson(PATHS.presets, r),
    '/api/themes':            (_, r) => _readJson(PATHS.themes, r),
    '/api/widget-categories': _handleGetWidgetCategories,
    '/api/widgets':           _handleGetWidgets,
  };

  if (method === 'GET' && getMap[url]) {
    getMap[url](req, res); return;
  }

  // ── POST endpoints ──
  const postMap = {
    '/api/scenes':      (r, s) => _handleSaveJson(r, s, PATHS.scenes),
    '/api/layouts':     (r, s) => _handleSaveJson(r, s, PATHS.layouts),
    '/api/scene-types': (r, s) => _handleSaveJson(r, s, PATHS.sceneTypes),
    '/api/bindings':    (r, s) => _handleSaveJson(r, s, PATHS.bindings),
    '/api/config':      (r, s) => _handleSaveJson(r, s, PATHS.config),
    '/api/songs':       (r, s) => _handleSaveJson(r, s, PATHS.songs),
    '/api/presets':     (r, s) => _handleSaveJson(r, s, PATHS.presets),
    '/api/themes':      (r, s) => _handleSaveJson(r, s, PATHS.themes),
  };


  if (method === 'POST' && postMap[url]) {
    postMap[url](req, res); return;
  }

  // /api/widgets/:id/template  (GET + POST)
  const tplMatch = url.match(/^\/api\/widgets\/([\w-]+)\/template$/);
  if (tplMatch && !_isValidWidgetId(tplMatch[1])) { res.writeHead(400).end('Invalid widget id'); return; }
  if (tplMatch && method === 'GET') { _handleGetWidgetTemplate(tplMatch[1], res); return; }
  if (tplMatch && method === 'POST') { _handleSaveWidgetTemplate(tplMatch[1], req, res); return; }

  // /api/widgets/:id/meta  (GET + POST)
  const metaMatch = url.match(/^\/api\/widgets\/([\w-]+)\/meta$/);
  if (metaMatch && !_isValidWidgetId(metaMatch[1])) { res.writeHead(400).end('Invalid widget id'); return; }
  if (metaMatch && method === 'GET') { _handleGetWidgetMeta(metaMatch[1], res); return; }
  if (metaMatch && method === 'POST') { _handleSaveWidgetMeta(metaMatch[1], req, res); return; }

  // /api/widgets/new  (POST) — create new widget
  if (url === '/api/widgets/new' && method === 'POST') { _handleCreateWidget(req, res); return; }

  // /api/widgets/:id/delete  (POST)
  const delMatch = url.match(/^\/api\/widgets\/([\w-]+)\/delete$/);
  if (delMatch && !_isValidWidgetId(delMatch[1])) { res.writeHead(400).end('Invalid widget id'); return; }
  if (delMatch && method === 'POST') { _handleDeleteWidget(delMatch[1], res); return; }

  // /api/categories  (POST) — save _categories.json
  if (url === '/api/categories' && method === 'POST') { _handleSaveJson(req, res, PATHS.categories); return; }

  // /api/broadcast  (POST) — send WS message to all clients
  if (url === '/api/broadcast' && method === 'POST') { _handleBroadcast(req, res); return; }

  // OBS Bridge REST endpoints
  if (url === '/api/obs/connect' && method === 'POST') { _handleObsConnect(req, res); return; }
  if (url === '/api/obs/disconnect' && method === 'POST') { _handleObsDisconnect(req, res); return; }
  if (url === '/api/obs/scenes' && method === 'GET') {
    _jsonOk(res, { scenes: appState.obsScenes, currentScene: appState.obsCurrentScene, connected: appState.obsConnected });
    return;
  }
  if (url === '/api/obs/switch-scene' && method === 'POST') { _handleObsSwitchScene(req, res); return; }
  if (url === '/api/obs/record/start'  && method === 'POST') { obsStartRecord(); _jsonOk(res, { success: true }); return; }
  if (url === '/api/obs/record/stop'   && method === 'POST') { obsStopRecord();  _jsonOk(res, { success: true }); return; }
  if (url === '/api/obs/stream/start'  && method === 'POST') { obsStartStream(); _jsonOk(res, { success: true }); return; }
  if (url === '/api/obs/stream/stop'   && method === 'POST') { obsStopStream();  _jsonOk(res, { success: true }); return; }

  res.writeHead(404).end('Not Found');
}

// ─── OBS REST Handlers ───────────────────────────────
function _handleObsConnect(req, res) {
  let body = '';
  req.on('data', c => body += c);
  req.on('end', async () => {
    try {
      const { host, password } = JSON.parse(body);
      const result = await obsConnect(host || 'localhost:4455', password || '');
      _jsonOk(res, result);
    } catch(e) { res.writeHead(400).end('Invalid JSON'); }
  });
}

function _handleObsDisconnect(req, res) {
  try { if (obsWS) { obsWS.disconnect(); obsWS = null; } } catch(_) {}
  obsState.connected = false;
  appState.obsConnected = false;
  _jsonOk(res, { success: true });
}

function _handleObsSwitchScene(req, res) {
  let body = '';
  req.on('data', c => body += c);
  req.on('end', async () => {
    try {
      const { sceneName } = JSON.parse(body);
      const result = await obsSwitchScene(sceneName);
      _jsonOk(res, result);
    } catch(e) { res.writeHead(400).end('Invalid JSON'); }
  });
}


// ─── Generic Handlers ────────────────────────────────
function _handleGetScenes(_, res)      { _readJson(PATHS.scenes, res); }
function _handleGetLayouts(_, res)     { _readJson(PATHS.layouts, res); }
function _handleGetSceneTypes(_, res)  { _readJson(PATHS.sceneTypes, res); }
function _handleGetBindings(_, res)    { _readJson(PATHS.bindings, res); }
function _handleGetConfig(_, res)      { _readJson(PATHS.config, res); }

function _readJson(filePath, res) {
  fs.readFile(filePath, 'utf8', (err, data) => {
    if (err) {
      // Return empty default if file not found
      const defaults = {
        [PATHS.sceneTypes]: '{"sceneTypes":{}}',
        [PATHS.bindings]:   '{"bindings":{}}',
        [PATHS.config]:     '{}',
      };
      const fallback = defaults[filePath] || '{}';
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(fallback);
      return;
    }
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(data);
  });
}

function _handleSaveJson(req, res, filePath) {
  let body = '';
  req.on('data', chunk => body += chunk);
  req.on('end', () => {
    try {
      const parsed = JSON.parse(body);
      fs.writeFile(filePath, JSON.stringify(parsed, null, 2), 'utf8', err => {
        if (err) { res.writeHead(500).end('Write error'); return; }
        _jsonOk(res, { success: true });
      });
    } catch { res.writeHead(400).end('Invalid JSON'); }
  });
}

function _jsonOk(res, data) {
  res.writeHead(200, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(data));
}

// ─── Widget Categories ───────────────────────────────
function _handleGetWidgetCategories(_, res) {
  // Read _categories.json + scan widget.json files to group by category
  fs.readFile(PATHS.categories, 'utf8', (err, catData) => {
    const cats = err ? { categories: [] } : (JSON.parse(catData) || { categories: [] });

    // Scan all widget.json files
    fs.readdir(WIDGETS_PATH, { withFileTypes: true }, (err2, entries) => {
      if (err2) { _jsonOk(res, cats); return; }

      const dirs = entries.filter(e => e.isDirectory()).map(e => e.name);
      const widgets = [];
      let pending = dirs.length;
      if (!pending) { _jsonOk(res, _groupByCategory(cats, [])); return; }

      dirs.forEach(dir => {
        const jsonPath = path.join(WIDGETS_PATH, dir, 'widget.json');
        fs.readFile(jsonPath, 'utf8', (e, data) => {
          if (!e) { try { widgets.push(JSON.parse(data)); } catch(_) {} }
          if (--pending === 0) {
            _jsonOk(res, _groupByCategory(cats, widgets));
          }
        });
      });
    });
  });
}

function _groupByCategory(catRegistry, widgets) {
  const result = catRegistry.categories.map(cat => ({
    ...cat,
    widgets: widgets.filter(w => w.category === cat.id),
  }));
  // Uncategorized widgets
  const knownCats = new Set(catRegistry.categories.map(c => c.id));
  const uncategorized = widgets.filter(w => !knownCats.has(w.category));
  return { categories: result, uncategorized };
}

// ─── Widget Handlers ────────────────────────────────
function _handleGetWidgets(_, res) {
  fs.readdir(WIDGETS_PATH, { withFileTypes: true }, (err, entries) => {
    if (err) { _jsonOk(res, []); return; }
    const dirs = entries.filter(e => e.isDirectory()).map(e => e.name);
    const results = [];
    let pending = dirs.length;
    if (!pending) { _jsonOk(res, []); return; }
    dirs.forEach(dir => {
      const jsonPath = path.join(WIDGETS_PATH, dir, 'widget.json');
      fs.readFile(jsonPath, 'utf8', (e, data) => {
        if (!e) { try { results.push(JSON.parse(data)); } catch(_) {} }
        if (--pending === 0) _jsonOk(res, results);
      });
    });
  });
}

function _handleGetWidgetTemplate(widgetId, res) {
  const tplPath = path.join(WIDGETS_PATH, widgetId, 'template.html');
  fs.readFile(tplPath, 'utf8', (err, data) => {
    if (err) { res.writeHead(404).end('Not Found'); return; }
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(data);
  });
}

function _handleSaveWidgetTemplate(widgetId, req, res) {
  const tplPath = path.join(WIDGETS_PATH, widgetId, 'template.html');
  let body = '';
  req.on('data', c => body += c);
  req.on('end', () => {
    fs.writeFile(tplPath, body, 'utf8', err => {
      if (err) { res.writeHead(500).end('Write error'); return; }
      _jsonOk(res, { success: true });
    });
  });
}

function _handleGetWidgetMeta(widgetId, res) {
  const metaPath = path.join(WIDGETS_PATH, widgetId, 'widget.json');
  fs.readFile(metaPath, 'utf8', (err, data) => {
    if (err) { res.writeHead(404).end('Not Found'); return; }
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(data);
  });
}

function _handleSaveWidgetMeta(widgetId, req, res) {
  const metaPath = path.join(WIDGETS_PATH, widgetId, 'widget.json');
  let body = '';
  req.on('data', c => body += c);
  req.on('end', () => {
    try {
      const parsed = JSON.parse(body);
      fs.writeFile(metaPath, JSON.stringify(parsed, null, 2), 'utf8', err => {
        if (err) { res.writeHead(500).end('Write error'); return; }
        _jsonOk(res, { success: true });
      });
    } catch { res.writeHead(400).end('Invalid JSON'); }
  });
}

function _handleCreateWidget(req, res) {
  let body = '';
  req.on('data', c => body += c);
  req.on('end', () => {
    try {
      const { id, name, icon, category, description } = JSON.parse(body);
      if (!id || !name) { res.writeHead(400).end('id and name required'); return; }
      const widgetDir = path.join(WIDGETS_PATH, id);
      if (fs.existsSync(widgetDir)) { res.writeHead(409).end('Widget already exists'); return; }
      fs.mkdirSync(widgetDir, { recursive: true });

      const meta = {
        id, name, icon: icon || '🧩', version: '1.0.0',
        author: 'OBSChurch', category: category || 'graphics',
        description: description || '', tags: [],
        defaultSize: { w: 40, h: 14 }, minSize: { w: 20, h: 8 },
        resizable: true, lockAR: false,
        props: [
          { key: 'title', label: 'Tiêu đề', type: 'text', default: name },
          { key: 'textColor', label: 'Màu chữ', type: 'color', default: '#ffffff' },
          { key: 'accentColor', label: 'Màu nhấn', type: 'color', default: '#a855f7' },
          { key: 'fontSize', label: 'Cỡ chữ (em)', type: 'number', min: 0.5, max: 5, step: 0.1, default: 1.2 },
        ],
      };

      const template = `<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>${name}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    width: 100%; height: 100%;
    background: transparent;
    font-family: 'Segoe UI', system-ui, sans-serif;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
  }
  .widget-wrap {
    padding: 12px 20px;
    border-left: 4px solid var(--accent, #a855f7);
    background: rgba(0,0,0,0.75);
    border-radius: 0 8px 8px 0;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.4s ease;
  }
  .widget-wrap.visible { opacity: 1; transform: none; }
  .title {
    font-size: var(--font-size, 1.2em);
    font-weight: 700;
    color: var(--text-color, #fff);
  }
</style>
</head>
<body>
<div class="widget-wrap" id="wrap">
  <span class="title" id="title">${name}</span>
</div>
<script>
const P = window.__WIDGET_PROPS || {};
function apply(p) {
  document.documentElement.style.setProperty('--accent', p.accentColor || '#a855f7');
  document.documentElement.style.setProperty('--text-color', p.textColor || '#ffffff');
  document.documentElement.style.setProperty('--font-size', (p.fontSize || 1.2) + 'em');
  document.getElementById('title').textContent = p.title || '${name}';
}
apply(P);
setTimeout(() => document.getElementById('wrap').classList.add('visible'), 100);
window.__widgetReload = (np) => { Object.assign(P, np); apply(P); };
</script>
</body>
</html>`;

      fs.writeFile(path.join(widgetDir, 'widget.json'), JSON.stringify(meta, null, 2), 'utf8', err1 => {
        if (err1) { res.writeHead(500).end('Write meta error'); return; }
        fs.writeFile(path.join(widgetDir, 'template.html'), template, 'utf8', err2 => {
          if (err2) { res.writeHead(500).end('Write template error'); return; }
          _jsonOk(res, { success: true, widget: meta });
        });
      });
    } catch(e) { res.writeHead(400).end('Invalid JSON: ' + e.message); }
  });
}

function _handleDeleteWidget(widgetId, res) {
  const widgetDir = path.join(WIDGETS_PATH, widgetId);
  // Double-check resolved path stays inside WIDGETS_PATH (defense in depth)
  if (!widgetDir.startsWith(WIDGETS_PATH + path.sep)) { res.writeHead(400).end('Invalid path'); return; }
  if (!fs.existsSync(widgetDir)) { res.writeHead(404).end('Not Found'); return; }
  fs.rmSync(widgetDir, { recursive: true, force: true });
  _jsonOk(res, { success: true });
}

function _handleBroadcast(req, res) {
  let body = '';
  req.on('data', c => body += c);
  req.on('end', () => {
    try {
      const msg = JSON.parse(body);
      _updateState(msg);
      _broadcastAll(msg, null);
      console.log(`[HTTP→WS] broadcast ${msg.type}`);
      _jsonOk(res, { success: true, type: msg.type });
    } catch(e) { res.writeHead(400).end('Invalid JSON: ' + e.message); }
  });
}


function _setCorsHeaders(res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
}

/**
 * Validate widget ID: chỉ chấp nhận ký tự an toàn, ngăn path traversal.
 * @param {string} id
 * @returns {boolean}
 */
function _isValidWidgetId(id) {
  return typeof id === 'string' && /^[a-zA-Z0-9_-]{1,64}$/.test(id);
}

// ─── WebSocket Handlers ───────────────────────────────
wss.on('connection', socket => {
  console.log(`[WS] Client connected (total: ${wss.clients.size})`);
  _sendToClient(socket, { type: 'STATE_SYNC', payload: appState });
  socket.on('message', raw => _onMessage(socket, raw));
  socket.on('close',   ()  => console.log(`[WS] Client disconnected (total: ${wss.clients.size})`));
  socket.on('error',   err => console.error('[WS] Error:', err.message));
});

function _onMessage(socket, raw) {
  try {
    const msg = JSON.parse(raw);
    // REQUEST_STATE: gửi lại state hiện tại cho client đó thôi
    if (msg.type === 'REQUEST_STATE') {
      _sendToClient(socket, { type: 'STATE_SYNC', payload: appState });
      return;
    }
    _updateState(msg);
    _broadcastAll(msg, socket);
    console.log(`[WS] → ${msg.type}`);
  } catch (e) {
    console.error('[WS] Invalid message:', e.message);
  }
}

// ─── State Management ────────────────────────────────
function _updateState(msg) {
  switch (msg.type) {
    case 'OVERLAY_SHOW':
      appState.overlayVisible = true;
      appState.currentPayload = msg.payload;
      break;
    case 'OVERLAY_HIDE':
    case 'CLEAR_ALL':
    case 'SAFETY_CUT':
      appState.overlayVisible = false;
      appState.currentPayload = {};
      break;
    case 'SCENE_CHANGE':
      appState.activeSceneId = msg.payload?.sceneId;
      break;
    case 'OVERLAY_SWITCH':
      appState.activeOverlayId   = msg.payload?.overlayId;
      appState.activeSceneType   = msg.payload?.sceneTypeId;
      appState.currentPayload    = { ...appState.currentPayload, ...msg.payload };
      break;
    case 'OBS_RECORD_START': appState.isRecording = true;  obsStartRecord(); break;
    case 'OBS_RECORD_STOP':  appState.isRecording = false; obsStopRecord();  break;
    case 'OBS_STREAM_START': appState.isStreaming = true;  obsStartStream(); break;
    case 'OBS_STREAM_STOP':  appState.isStreaming = false; obsStopStream();  break;
    case 'OBS_SCENE_SWITCH':
      if (msg.payload?.sceneName) obsSwitchScene(msg.payload.sceneName);
      break;
    case 'TICKER_SHOW':
      appState.tickerVisible  = true;
      appState.tickerMessages = msg.payload?.messages || [];
      break;
    case 'TICKER_HIDE':
      appState.tickerVisible  = false;
      appState.tickerMessages = [];
      break;
    // ─ Monitor relay commands (broadcast-only, no appState change) ─
    case 'MONITOR_NAV':     // { dir: -1 | 1 } — relay to Lyric Station
    case 'MONITOR_PUSH':    // {} — relay to Lyric Station
    case 'MONITOR_SELECT':  // { lineIdx: N } — relay to Lyric Station
      // No state update needed — just pass through via broadcastAll
      break;
    case 'REQUEST_STATE':
      // Client requesting a full state sync — handled in _onMessage
      break;
  }
}

// ─── Broadcast ───────────────────────────────────────
/**
 * Broadcast message to all connected clients.
 * @param {object} msg     - Message to broadcast
 * @param {WebSocket|null} sender - Exclude this client (null = send to all)
 */
function _broadcastAll(msg, sender) {
  const raw = JSON.stringify(msg);
  wss.clients.forEach(client => {
    if (client !== sender && client.readyState === 1) client.send(raw);
  });
}

function _sendToClient(socket, msg) {
  if (socket.readyState === 1) socket.send(JSON.stringify(msg));
}

// ─── Keep-Alive Ping ─────────────────────────────────
setInterval(() => {
  wss.clients.forEach(client => {
    if (client.readyState === 1) client.ping();
  });
}, PING_MS);

// ─── Start ───────────────────────────────────────────
httpServer.listen(PORT, () => {
  console.log('\n🎛️  OBSChurch WS Server v2.3');
  console.log(`   WebSocket  : ws://localhost:${PORT}`);
  console.log(`   REST API   : http://localhost:${PORT}/api/`);
  console.log('   GET  : scenes | layouts | scene-types | bindings | config | songs | presets | themes | widgets | widget-categories');
  console.log('   GET  : obs/scenes');
  console.log('   POST : scenes | layouts | scene-types | bindings | config | songs | presets | themes | categories | broadcast');
  console.log('   POST : obs/connect | obs/disconnect | obs/switch-scene | obs/record/start | obs/record/stop');
  console.log('   POST : widgets/new | widgets/:id/template | widgets/:id/meta | widgets/:id/delete\n');
});

