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

// ─── Constants ───────────────────────────────────────
const PORT = 3000;
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
};

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
    '/api/widget-categories': _handleGetWidgetCategories,
    '/api/widgets':           _handleGetWidgets,
  };

  if (method === 'GET' && getMap[url]) {
    getMap[url](req, res); return;
  }

  // ── POST endpoints ──
  const postMap = {
    '/api/layouts':     (r, s) => _handleSaveJson(r, s, PATHS.layouts),
    '/api/scene-types': (r, s) => _handleSaveJson(r, s, PATHS.sceneTypes),
    '/api/bindings':    (r, s) => _handleSaveJson(r, s, PATHS.bindings),
    '/api/config':      (r, s) => _handleSaveJson(r, s, PATHS.config),
  };

  if (method === 'POST' && postMap[url]) {
    postMap[url](req, res); return;
  }

  // /api/widgets/:id/template
  const tplMatch = url.match(/^\/api\/widgets\/([\w-]+)\/template$/);
  if (tplMatch && method === 'GET') {
    _handleGetWidgetTemplate(tplMatch[1], res); return;
  }

  res.writeHead(404).end('Not Found');
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

function _setCorsHeaders(res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
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
      // Switch overlay within a scene type
      appState.activeOverlayId   = msg.payload?.overlayId;
      appState.activeSceneType   = msg.payload?.sceneTypeId;
      appState.currentPayload    = { ...appState.currentPayload, ...msg.payload };
      break;
  }
}

// ─── Broadcast ───────────────────────────────────────
function _broadcastAll(msg, _sender) {
  const raw = JSON.stringify(msg);
  wss.clients.forEach(client => {
    if (client.readyState === 1) client.send(raw);
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
  console.log('\n🎛️  OBSChurch WS Server v2.0');
  console.log(`   WebSocket  : ws://localhost:${PORT}`);
  console.log(`   REST API   : http://localhost:${PORT}/api/`);
  console.log('   Endpoints  : scenes | layouts | scene-types | bindings | config | widget-categories | widgets\n');
});
