/**
 * OBSClient — OBS WebSocket v5.x Client (Vanilla JS)
 * @module core/OBSClient
 *
 * Giao tiếp với OBS Studio qua cổng mặc định 4455 (giao thức obswebsocket.json).
 * Sử dụng Web Crypto API để băm mật khẩu SHA256 (nếu có).
 */

import StateManager from './StateManager.js';

const OP_CODES = { HELLO: 0, IDENTIFY: 1, IDENTIFIED: 2, EVENT: 5, REQUEST: 6, RESPONSE: 7 };

const OBSClient = (() => {
  let _socket       = null;
  let _requestId    = 1;
  let _reconnectTimer = null;
  const _pendingRequests = new Map();

  function connect() {
    const { host, port } = StateManager.getObsConfig();
    const url = `ws://${host}:${port}`;
    console.log(`[OBS] Connecting to ${url}...`);

    clearTimeout(_reconnectTimer);
    try {
      _socket = new WebSocket(url, 'obswebsocket.json');
      _socket.addEventListener('message', _onMessage);
      _socket.addEventListener('close',   _onClose);
      _socket.addEventListener('error',   _onError);
    } catch (e) {
      _onClose();
    }
  }

  function disconnect() {
    clearTimeout(_reconnectTimer);
    if (_socket) {
      _socket.close();
      _socket = null;
    }
    StateManager.setObsStatus(false);
  }

  function sendRequest(requestType, requestData = {}) {
    if (!_socket || _socket.readyState !== WebSocket.OPEN) return Promise.reject('OBS offline');
    const id = String(_requestId++);
    const payload = { op: OP_CODES.REQUEST, d: { requestType, requestId: id, requestData } };
    
    return new Promise((resolve, reject) => {
      _pendingRequests.set(id, { resolve, reject });
      _socket.send(JSON.stringify(payload));
    });
  }

  // ── Private ──────────────────────────────────────
  function _onMessage(e) {
    try {
      const msg = JSON.parse(e.data);
      _handleOpCode(msg.op, msg.d);
    } catch (err) {
      console.error('[OBS] Parse error:', err);
    }
  }

  function _handleOpCode(op, d) {
    if (op === OP_CODES.HELLO)      _handleHello(d);
    if (op === OP_CODES.IDENTIFIED) _handleIdentified();
    if (op === OP_CODES.RESPONSE)   _handleResponse(d);
    if (op === OP_CODES.EVENT)      _handleEvent(d);
  }

  async function _handleHello(d) {
    const { password } = StateManager.getObsConfig();
    const authData = d.authentication;
    const identifyPayload = { op: OP_CODES.IDENTIFY, d: { rpcVersion: 1 } };

    if (authData && password) {
      const secret = await _generateAuthSecret(password, authData.salt, authData.challenge);
      identifyPayload.d.authentication = secret;
    }
    _socket.send(JSON.stringify(identifyPayload));
  }

  async function _generateAuthSecret(password, salt, challenge) {
    const pwHash = await _sha256(password + salt);
    const secret = await _sha256(pwHash + challenge);
    return secret;
  }

  async function _sha256(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
    return Array.from(new Uint8Array(hashBuffer))
      .map((b) => b.toString(16).padStart(2, '0'))
      .join('');
  }

  function _handleIdentified() {
    console.log('[OBS] Connected & Authenticated');
    StateManager.setObsStatus(true);
    _syncInitialState();
  }

  function _handleResponse(d) {
    const req = _pendingRequests.get(d.requestId);
    if (!req) return;
    _pendingRequests.delete(d.requestId);
    if (d.requestStatus.result) req.resolve(d.responseData);
    else req.reject(d.requestStatus.comment);
  }

  function _handleEvent(d) {
    if (d.eventType === 'CurrentProgramSceneChanged') {
      const sceneName = d.eventData.sceneName;
      _onObsSceneChanged(sceneName);
    }
  }

  function _onObsSceneChanged(sceneName) {
    const state = StateManager.getState();
    const matched = state.scenes.find((s) => s.label.toLowerCase() === sceneName.toLowerCase() || s.id === sceneName);
    if (matched && matched.id !== state.activeSceneId) {
      console.log(`[OBS Sync] OBS Scene changed -> ${sceneName}`);
      StateManager.setActiveScene(matched.id);
    }
  }

  async function _syncInitialState() {
    try {
      const data = await sendRequest('GetCurrentProgramScene');
      if (data && data.sceneName) _onObsSceneChanged(data.sceneName);
    } catch (e) {
      console.warn('[OBS Sync] Cannot get initial scene:', e);
    }
  }

  async function syncCameraTransform(sceneName, sourceName, layout) {
    try {
      const { camX, camY, camW, camH } = layout;
      const { sceneItemId } = await sendRequest('GetSceneItemId', { sceneName, sourceName });
      await sendRequest('SetSceneItemTransform', {
        sceneName,
        sceneItemId,
        sceneItemTransform: {
          positionX: (camX / 100) * 1920,
          positionY: (camY / 100) * 1080,
          boundsWidth: (camW / 100) * 1920,
          boundsHeight: (camH / 100) * 1080,
          boundsType: 'OBS_BOUNDS_SCALE_INNER'
        }
      });
      console.log(`[OBS Sync] Synced '${sourceName}' transform ✓`);
    } catch (e) {
      console.warn(`[OBS Sync] Cannot sync transform:`, e);
    }
  }

  function _onClose() {
    StateManager.setObsStatus(false);
    _pendingRequests.clear();
    _reconnectTimer = setTimeout(connect, 5000);
  }

  function _onError(err) {
    console.error('[OBS] WS Error:', err);
  }

  return { connect, disconnect, sendRequest, syncCameraTransform };
})();

export default OBSClient;
