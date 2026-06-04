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
  OBS_STATUS     : 'state:obs_status',
});

const _savedObs = (() => {
  try { return JSON.parse(localStorage.getItem('obs_ws_config') || '{}'); } catch(_) { return {}; }
})();

/** @type {AppState} */
const _state = {
  scenes        : [],
  layouts       : {}, // cache loaded layouts from server
  activeSceneId : null,
  activeItemId  : null,
  overlayVisible: false,
  wsConnected   : false,
  obsConnected  : false,
  obsConfig: {
    host: _savedObs.host || 'localhost',
    port: Number(_savedObs.port) || 4455,
    password: _savedObs.password || '',
  },
  form: {
    templateId   : 'clean-dark',
    songName     : '',
    scriptureRef : '',
    scriptureVerse: '',
    speakerName  : '',
    announcement  : '',
    camX          : 5,
    camY          : 5,
    camW          : 25,
    camH          : 25,
    camR          : 8,
    textX         : 5,
    textY         : 80,
    customCss     : '',
  },
};

const StateManager = (() => {

  /** Lấy bản sao state (immutable read). */
  function getState() {
    return structuredClone(_state);
  }

  /** Cache all template layouts */
  function setLayouts(layouts) {
    _state.layouts = layouts;
  }

  function getLayouts() {
    return _state.layouts;
  }

  function getLayoutForTemplate(templateId) {
    return _state.layouts[templateId] ?? {
      camX: 5, camY: 5, camW: 25, camH: 25, camR: 8, textX: 5, textY: 80, customCss: ''
    };
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
    if (field === 'templateId') {
      const layout = getLayoutForTemplate(value);
      Object.assign(_state.form, {
        camX      : layout.camX,
        camY      : layout.camY,
        camW      : layout.camW,
        camH      : layout.camH,
        camR      : layout.camR,
        textX     : layout.textX,
        textY     : layout.textY,
        customCss : layout.customCss,
      });
    }
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

  function getObsConfig() {
    return _state.obsConfig;
  }

  function setObsConfig(config) {
    Object.assign(_state.obsConfig, config);
    localStorage.setItem('obs_ws_config', JSON.stringify(_state.obsConfig));
  }

  function setObsStatus(connected) {
    _state.obsConnected = connected;
    EventBus.emit(STATE_EVENTS.OBS_STATUS, { connected });
  }

  // ── Private ──────────────────────────────────────
  /** Tự động điền form khi chọn item. */
  function _autoFillForm(item) {
    const layout = getLayoutForTemplate(item.templateId);
    const updates = {
      templateId: item.templateId,
      songName  : item.type === 'music' ? item.label : '',
      camX      : layout.camX,
      camY      : layout.camY,
      camW      : layout.camW,
      camH      : layout.camH,
      camR      : layout.camR,
      textX     : layout.textX,
      textY     : layout.textY,
      customCss : layout.customCss,
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
    getObsConfig, setObsConfig, setObsStatus,
    setLayouts, getLayouts, getLayoutForTemplate,
    on, off,
    EVENTS: STATE_EVENTS,
  };
})();

export default StateManager;
