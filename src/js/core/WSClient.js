/**
 * WSClient — WebSocket Client Wrapper
 * @module core/WSClient
 *
 * Tính năng:
 * - Auto-reconnect với exponential backoff
 * - Queue tin nhắn khi mất kết nối
 * - Typed send helpers
 *
 * Nguyên tắc: Singleton, tách rời transport khỏi business logic
 */

import StateManager from './StateManager.js';

// ─── Constants ───────────────────────────────────────
const WS_URL             = 'ws://localhost:3001';
const RECONNECT_BASE_MS  = 1_000;
const RECONNECT_MAX_MS   = 30_000;
const RECONNECT_FACTOR   = 2;
const QUEUE_TTL_MS       = 30_000;  // Huỷ message đã queue > 30s

/** Enum các message type gửi đi. */
const WS_EVENTS = Object.freeze({
  OVERLAY_SHOW : 'OVERLAY_SHOW',
  OVERLAY_HIDE : 'OVERLAY_HIDE',
  OVERLAY_TRANS: 'OVERLAY_TRANS',
  CLEAR_ALL    : 'CLEAR_ALL',
  SAFETY_CUT   : 'SAFETY_CUT',
  SCENE_CHANGE : 'SCENE_CHANGE',
  RECORD_TOGGLE: 'RECORD_TOGGLE',
  STREAM_TOGGLE: 'STREAM_TOGGLE',
});

const WSClient = (() => {
  let _socket       = null;
  let _reconnectMs  = RECONNECT_BASE_MS;
  let _reconnectTimer = null;
  /** @type {Array<{raw:string, ts:number}>} Hàng đợi khi offline */
  const _queue      = [];

  // ── Public API ───────────────────────────────────
  function connect() {
    _socket = new WebSocket(WS_URL);
    _socket.addEventListener('open',    _onOpen);
    _socket.addEventListener('message', _onMessage);
    _socket.addEventListener('close',   _onClose);
    _socket.addEventListener('error',   _onError);
  }

  /**
   * Gửi message có cấu trúc { type, payload }.
   * Nếu chưa kết nối → đưa vào queue.
   */
  function send(type, payload = {}) {
    const raw = JSON.stringify({ type, payload });
    if (_isOpen()) {
      _socket.send(raw);
    } else {
      _queue.push({ raw, ts: Date.now() });
      console.warn(`[WS] Queued: ${type}`);
    }
  }

  // ── Typed Helpers ────────────────────────────────
  const showOverlay  = (payload) => send(WS_EVENTS.OVERLAY_SHOW,  payload);
  const hideOverlay  = ()        => send(WS_EVENTS.OVERLAY_HIDE,  {});
  const transition   = (effect)  => send(WS_EVENTS.OVERLAY_TRANS, { effect });
  const clearAll     = ()        => send(WS_EVENTS.CLEAR_ALL,     {});
  const safetyCut    = ()        => send(WS_EVENTS.SAFETY_CUT,    {});
  const changeScene  = (sceneId) => send(WS_EVENTS.SCENE_CHANGE,  { sceneId });
  const toggleRecord = ()        => send(WS_EVENTS.RECORD_TOGGLE, {});
  const toggleStream = ()        => send(WS_EVENTS.STREAM_TOGGLE, {});

  // ── Private ──────────────────────────────────────
  function _isOpen() {
    return _socket?.readyState === WebSocket.OPEN;
  }

  function _onOpen() {
    console.log('[WS] Connected');
    _reconnectMs = RECONNECT_BASE_MS;
    clearTimeout(_reconnectTimer);
    StateManager.setWsStatus(true);
    _flushQueue();
  }

  function _onMessage(event) {
    try {
      const msg = JSON.parse(event.data);
      _handleIncoming(msg);
    } catch (e) {
      console.error('[WS] Parse error:', e);
    }
  }

  function _handleIncoming(msg) {
    switch (msg.type) {
      case 'STATE_SYNC':
        StateManager.setOverlayVisible(msg.payload.overlayVisible ?? false);
        if (msg.payload.obsConnected !== undefined) {
          StateManager.setObsStatus(msg.payload.obsConnected);
        }
        break;

      case 'OBS_STATE':
        StateManager.setObsStatus(msg.payload.connected ?? false);
        break;

      case 'OBS_SCENE_CHANGED':
        // Server đã đồng bộ — chỉ log, OBSClient xử lý riêng
        console.log(`[WS] OBS scene: ${msg.payload.scene}`);
        break;

      case 'OBS_RECORD_START':
        StateManager.setRecordStatus?.(true);
        break;
      case 'OBS_RECORD_STOP':
        StateManager.setRecordStatus?.(false);
        break;

      case 'OBS_STREAM_START':
        StateManager.setStreamStatus?.(true);
        break;
      case 'OBS_STREAM_STOP':
        StateManager.setStreamStatus?.(false);
        break;

      default:
        break;
    }
  }

  function _onClose() {
    StateManager.setWsStatus(false);
    console.warn(`[WS] Disconnected. Reconnect in ${_reconnectMs}ms`);
    _scheduleReconnect();
  }

  function _onError(err) {
    console.error('[WS] Error:', err.message ?? err);
  }

  function _scheduleReconnect() {
    _reconnectTimer = setTimeout(() => {
      connect();
      _reconnectMs = Math.min(_reconnectMs * RECONNECT_FACTOR, RECONNECT_MAX_MS);
    }, _reconnectMs);
  }

  function _flushQueue() {
    const now = Date.now();
    // Loại bỏ messages quá TTL trước khi gửi
    while (_queue.length > 0) {
      const item = _queue[0];
      if (now - item.ts > QUEUE_TTL_MS) {
        _queue.shift();
        console.warn('[WS] Dropped expired queued message');
        continue;
      }
      if (!_isOpen()) break;
      _socket.send(_queue.shift().raw);
    }
  }

  return {
    connect, send, EVENTS: WS_EVENTS,
    showOverlay, hideOverlay, transition, clearAll,
    safetyCut, changeScene, toggleRecord, toggleStream,
  };
})();

export default WSClient;
