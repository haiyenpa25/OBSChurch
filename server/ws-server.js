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

  if (req.url === '/api/scenes' && req.method === 'GET') {
    const data = fs.readFileSync(SCENES_PATH, 'utf8');
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(data);
    return;
  }

  if (req.url === '/api/state' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(appState));
    return;
  }

  res.writeHead(404).end('Not Found');
}

function _setCorsHeaders(res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
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
