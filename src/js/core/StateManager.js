/**
 * StateManager — Single Source of Truth
 * @module core/StateManager
 *
 * Toàn bộ state UI tập trung tại đây.
 * Components không giữ state riêng — chỉ đọc từ StateManager.
 *
 * Nguyên tắc: Singleton + Immutable reads
 */

import EventBus from './EventBus.js';

// ─── Constants ───────────────────────────────────────
const STATE_EVENTS = Object.freeze({
  SCENE_CHANGED  : 'state:scene_changed',
  ITEM_SELECTED  : 'state:item_selected',
  FORM_UPDATED   : 'state:form_updated',
  OVERLAY_CHANGED: 'state:overlay_changed',
  WS_STATUS      : 'state:ws_status',
});

/** @type {AppState} */
const _state = {
  scenes        : [],
  activeSceneId : null,
  activeItemId  : null,
  overlayVisible: false,
  wsConnected   : false,
  form: {
    templateId   : 'clean-dark',
    songName     : '',
    scriptureRef : '',
    scriptureVerse: '',
    speakerName  : '',
    announcement : '',
  },
};

const StateManager = (() => {

  /** Lấy bản sao state (immutable read). */
  function getState() {
    return structuredClone(_state);
  }

  /** Lấy scene đang active. @returns {Scene|null} */
  function getActiveScene() {
    return _state.scenes.find((s) => s.id === _state.activeSceneId) ?? null;
  }

  /** Lấy item đang active. @returns {EventItem|null} */
  function getActiveItem() {
    const scene = getActiveScene();
    return scene?.items.find((i) => i.id === _state.activeItemId) ?? null;
  }

  /** Khởi tạo scenes từ API. */
  function setScenes(scenes) {
    _state.scenes = scenes;
    if (!_state.activeSceneId && scenes.length > 0) {
      setActiveScene(scenes[0].id);
    }
  }

  /** Chuyển cảnh. */
  function setActiveScene(sceneId) {
    _state.activeSceneId = sceneId;
    _state.activeItemId  = null;
    EventBus.emit(STATE_EVENTS.SCENE_CHANGED, { sceneId });
  }

  /** Chọn tiết mục. */
  function setActiveItem(itemId) {
    _state.activeItemId = itemId;
    const item = getActiveItem();
    if (item) _autoFillForm(item);
    EventBus.emit(STATE_EVENTS.ITEM_SELECTED, { itemId, item });
  }

  /** Cập nhật form field. */
  function updateForm(field, value) {
    if (!(field in _state.form)) return;
    _state.form[field] = value;
    EventBus.emit(STATE_EVENTS.FORM_UPDATED, { field, value });
  }

  /** Cập nhật toàn bộ form. */
  function setForm(formData) {
    Object.assign(_state.form, formData);
    EventBus.emit(STATE_EVENTS.FORM_UPDATED, { field: 'all', value: formData });
  }

  /** Cập nhật trạng thái WS. */
  function setWsStatus(connected) {
    _state.wsConnected = connected;
    EventBus.emit(STATE_EVENTS.WS_STATUS, { connected });
  }

  /** Cập nhật trạng thái overlay. */
  function setOverlayVisible(visible) {
    _state.overlayVisible = visible;
    EventBus.emit(STATE_EVENTS.OVERLAY_CHANGED, { visible });
  }

  // ── Private ──────────────────────────────────────
  /** Tự động điền form khi chọn item. */
  function _autoFillForm(item) {
    const updates = {
      templateId: item.templateId,
      songName  : item.type === 'music' ? item.label : '',
    };
    setForm(updates);
  }

  /**
   * EventBus bridge — cho phép Controllers subscribe events
   * mà không cần import EventBus trực tiếp.
   * @param {string}   event
   * @param {Function} handler
   * @returns {Function} unsubscribe
   */
  function on(event, handler) {
    return EventBus.on(event, handler);
  }

  function off(event, handler) {
    return EventBus.off(event, handler);
  }

  return {
    getState, getActiveScene, getActiveItem,
    setScenes, setActiveScene, setActiveItem,
    updateForm, setForm, setWsStatus, setOverlayVisible,
    on, off,
    EVENTS: STATE_EVENTS,
  };
})();

export default StateManager;
