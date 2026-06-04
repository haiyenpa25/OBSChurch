/**
 * InputController — Quản lý Cột 3 (Input Panel)
 * @module controllers/InputController
 *
 * Xử lý: form input, template picker, validation, Push to PGM.
 * MonitorView được inject từ app.js (không tạo instance riêng).
 */

import { BaseController  } from './BaseController.js';
import StateManager        from '../core/StateManager.js';
import WSClient            from '../core/WSClient.js';
import InputView           from '../views/InputView.js';
import { OverlayPayload  } from '../models/OverlayPayload.js';

export class InputController extends BaseController {
  /** @param {MonitorView} monitorView - Injected singleton */
  constructor(monitorView) {
    super({ rootSelector: '#col-input' });
    this._view        = new InputView(this._rootEl);
    this._monitorView = monitorView; // injected — không tạo new ở đây
  }

  /** @override */
  init() {
    // Re-sync form khi state thay đổi (ví dụ: item click auto-fill)
    StateManager.on(StateManager.EVENTS.FORM_UPDATED,  () => this._syncFormUI());
    StateManager.on(StateManager.EVENTS.ITEM_SELECTED, (data) => {
      this._onItemSelected(data.item);
    });

    // Input live binding: mỗi keystroke → update state → live preview
    this._delegate(this._rootEl, 'input', '[data-field]', (e, el) => {
      StateManager.updateForm(el.dataset.field, el.value);
      this._updatePreview();
    });

    // Template select
    this._delegate(this._rootEl, 'change', '[data-field]', (e, el) => {
      StateManager.updateForm(el.dataset.field, el.value);
      this._updatePreview();
    });

    // Push to PGM button
    this._on(this._q('#btn-push-pgm'), 'click', () => this._handlePush());

    // Save Changes
    this._on(this._q('#btn-save'), 'click', () => this._handleSave());

    // "Hiện tất cả fields" toggle
    this._on(this._q('#btn-show-all-fields'), 'click', () => {
      this._view.showAllFields();
    });

    this._syncFormUI();
    this._isActive = true;
  }

  // ── Private ──────────────────────────────────────
  /** Khi chọn item mới, context form theo type. */
  _onItemSelected(item) {
    if (!item) return;
    this._view.applyItemContext(item.type);
    this._syncFormUI();
    this._updatePreview();
  }

  /** Validate → build payload → gửi WS. */
  _handlePush() {
    const formData = StateManager.getState().form;
    const payload  = new OverlayPayload(formData);
    const { valid, errors } = payload.validate();

    if (!valid) {
      this._view.showError(errors[0]);
      return;
    }

    WSClient.showOverlay(payload.toJSON());
    StateManager.setOverlayVisible(true);
    this._view.clearError();
    this._view.flashSuccess('Đã Push to PGM ✓');
    this._monitorView?.setOverlayVisible(true);
  }

  /** Lưu session vào localStorage. */
  _handleSave() {
    const state = StateManager.getState();
    const session = {
      form         : state.form,
      activeSceneId: state.activeSceneId,
      activeItemId : state.activeItemId,
    };
    localStorage.setItem('obs_session', JSON.stringify(session));
    this._view.flashSuccess(`Đã lưu lúc ${_nowTimeStr()}`);
    this._view.setSaveTimestamp(_nowTimeStr());
  }

  /** Sync giá trị DOM inputs với state. */
  _syncFormUI() {
    const form = StateManager.getState().form;
    this._view.setFormValues(form);
    this._updatePreview();
  }

  /** Cập nhật Col4 preview (không gửi WS). */
  _updatePreview() {
    const form    = StateManager.getState().form;
    const payload = new OverlayPayload(form);
    this._monitorView?.updatePreview(payload);
  }
}

function _nowTimeStr() {
  const d = new Date();
  return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;
}
