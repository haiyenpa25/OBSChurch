/**
 * OBSChurch WebSocket Server
 * @module ws-server
 * @version 1.0.0
 *
 * Vai trò: Hub trung tâm — nhận lệnh từ Control Panel,
 * broadcast tới tất cả clients (kể cả OBS Browser Source).
 *
 * Nguyên tắc:
 * - Mỗi function < 30 dòng
 * - Tên hàm private prefix _
 * - Constants SCREAMING_SNAKE_CASE
 */

'use strict';

const { WebSocketServer } = require('ws');
const http = require('http');
const fs   = require('fs');
const path = require('path');

// ─── Constants ───────────────────────────────────────
const PORT         = 3000;
const PING_MS      = 20_000;
const SCENES_PATH  = path.join(__dirname, 'data', 'scenes.json');
const LAYOUTS_PATH = path.join(__dirname, 'data', 'layouts.json');
const WIDGETS_PATH = path.join(__dirname, '..', 'widgets');

// ─── Bootstrap ───────────────────────────────────────
const httpServer = http.createServer(_handleHttpRequest);
const wss        = new WebSocketServer({ server: httpServer });

/** @type {Map<string, object>} In-memory state */
const appState = {
  activeSceneId : 'chinh-le',
  overlayVisible: false,
  currentPayload: {},
};

// ─── HTTP Handler (phục vụ scenes.json qua REST) ─────
function _handleHttpRequest(req, res) {
  _setCorsHeaders(res);

  if (req.method === 'OPTIONS') {
    res.writeHead(204).end();
    return;
  }

  if (req.url === '/api/scenes' && req.method === 'GET') {
    _handleGetScenes(res);
    return;
  }

  if (req.url === '/api/layouts' && req.method === 'GET') {
    _handleGetLayouts(res);
    return;
  }

  if (req.url === '/api/layouts' && req.method === 'POST') {
    _handleSaveLayouts(req, res);
    return;
  }

  if (req.url === '/api/state' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(appState));
    return;
  }

  if (req.url === '/api/widgets' && req.method === 'GET') {
    _handleGetWidgets(res);
    return;
  }

  // /api/widgets/:id/template
  const tplMatch = req.url.match(/^\/api\/widgets\/([\w-]+)\/template$/);
  if (tplMatch && req.method === 'GET') {
    _handleGetWidgetTemplate(tplMatch[1], res);
    return;
  }

  res.writeHead(404).end('Not Found');
}

function _handleGetScenes(res) {
  fs.readFile(SCENES_PATH, 'utf8', (err, data) => {
    if (err) {
      res.writeHead(500).end('Internal Server Error');
      return;
    }
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(data);
  });
}

function _handleGetLayouts(res) {
  fs.readFile(LAYOUTS_PATH, 'utf8', (err, data) => {
    if (err) {
      res.writeHead(500).end('Internal Server Error');
      return;
    }
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(data);
  });
}

function _handleSaveLayouts(req, res) {
  let body = '';
  req.on('data', (chunk) => body += chunk);
  req.on('end', () => {
    try {
      const parsed = JSON.parse(body);
      fs.writeFile(LAYOUTS_PATH, JSON.stringify(parsed, null, 2), 'utf8', (err) => {
        if (err) {
          res.writeHead(500).end('Internal Server Error');
          return;
        }
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true }));
      });
    } catch (e) {
      res.writeHead(400).end('Invalid JSON');
    }
  });
}

function _handleGetWidgets(res) {
  fs.readdir(WIDGETS_PATH, { withFileTypes: true }, (err, entries) => {
    if (err) { res.writeHead(200, { 'Content-Type': 'application/json' }); res.end('[]'); return; }
    const dirs = entries.filter(e => e.isDirectory()).map(e => e.name);
    const results = [];
    let pending = dirs.length;
    if (!pending) { res.writeHead(200, { 'Content-Type': 'application/json' }); res.end('[]'); return; }
    dirs.forEach(dir => {
      const jsonPath = path.join(WIDGETS_PATH, dir, 'widget.json');
      fs.readFile(jsonPath, 'utf8', (err2, data) => {
        if (!err2) { try { results.push(JSON.parse(data)); } catch(_) {} }
        if (--pending === 0) {
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify(results));
        }
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
wss.on('connection', (socket) => {
  console.log(`[WS] Client connected (total: ${wss.clients.size})`);

  // Gửi state hiện tại ngay khi kết nối
  _sendToClient(socket, { type: 'STATE_SYNC', payload: appState });

  socket.on('message', (raw) => _onMessage(socket, raw));
  socket.on('close',   ()    => _onClose());
  socket.on('error',   (err) => console.error('[WS] Error:', err.message));
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

function _onClose() {
  console.log(`[WS] Client disconnected (total: ${wss.clients.size})`);
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
      appState.activeSceneId = msg.payload.sceneId;
      break;
  }
}

// ─── Broadcast ───────────────────────────────────────
/**
 * Gửi message tới tất cả clients (kể cả sender).
 * OBS Browser Source cũng là 1 client → nhận được.
 */
function _broadcastAll(msg, _sender) {
  const raw = JSON.stringify(msg);
  wss.clients.forEach((client) => {
    if (client.readyState === 1) client.send(raw);
  });
}

function _sendToClient(socket, msg) {
  if (socket.readyState === 1) {
    socket.send(JSON.stringify(msg));
  }
}

// ─── Keep-Alive Ping ─────────────────────────────────
setInterval(() => {
  wss.clients.forEach((client) => {
    if (client.readyState === 1) client.ping();
  });
}, PING_MS);

// ─── Start ───────────────────────────────────────────
httpServer.listen(PORT, () => {
  console.log(`\n🎛️  OBSChurch WS Server running`);
  console.log(`   WebSocket : ws://localhost:${PORT}`);
  console.log(`   REST API  : http://localhost:${PORT}/api/scenes\n`);
});
