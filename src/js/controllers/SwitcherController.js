/**
 * SwitcherController — Action Grid + Keyboard Shortcuts
 * @module controllers/SwitcherController
 *
 * Quan trọng nhất: Safety Cam Cut (F12) phải tức thì.
 * ItemController được inject để gọi navigateNext/Prev.
 */

import { BaseController } from './BaseController.js';
import StateManager       from '../core/StateManager.js';
import WSClient           from '../core/WSClient.js';

/** Map phím tắt → action name */
const HOTKEYS = Object.freeze({
  F1 : 'show',
  F2 : 'hide',
  F3 : 'transition',
  F4 : 'clear',
  F5 : 'next-item',
  F6 : 'prev-item',
  F12: 'safety-cut',
});

export class SwitcherController extends BaseController {
  /** @param {ItemController} itemController - Injected */
  constructor(itemController) {
    super({ rootSelector: '#col-monitor' });
    this._itemCtrl = itemController;
  }

  /** @override */
  init() {
    // Action button clicks
    this._delegate(this._rootEl, 'click', '[data-action]', (e, btn) => {
      this._handleAction(btn.dataset.action, btn);
    });

    // Push to PGM button (col3) cũng có data-action="show"
    const pushBtn = document.querySelector('#btn-push-pgm');
    if (pushBtn) {
      // Đã xử lý trong InputController — không cần handle ở đây
    }

    // Global keyboard shortcuts
    const keyHandler = (e) => this._handleKey(e);
    document.addEventListener('keydown', keyHandler);
    this._handlers.push({ el: document, event: 'keydown', fn: keyHandler });

    this._isActive = true;
  }

  // ── Private ──────────────────────────────────────
  _handleKey(e) {
    // Bỏ qua khi đang gõ trong input
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

    const action = HOTKEYS[e.key];
    if (!action) return;
    e.preventDefault();

    // Flash button tương ứng
    const btn = this._rootEl?.querySelector(`[data-action="${action}"]`);
    this._flashButton(btn);
    this._handleAction(action, btn);
  }

  /**
   * Dispatch action — polymorphic map.
   * Mỗi action gọi đúng helper.
   */
  _handleAction(action, btnEl) {
    const dispatch = {
      'show'       : () => this._doShow(),
      'hide'       : () => this._doHide(),
      'transition' : () => this._doTransition(),
      'clear'      : () => this._doClear(),
      'safety-cut' : () => this._doSafetyCut(),
      'next-item'  : () => this._itemCtrl?.navigateNext(),
      'prev-item'  : () => this._itemCtrl?.navigatePrev(),
      'record'     : () => WSClient.toggleRecord(),
      'stream'     : () => WSClient.toggleStream(),
    };

    const fn = dispatch[action];
    if (fn) fn();
    else console.warn(`[Switcher] Unknown action: ${action}`);
  }

  _doShow() {
    const form = StateManager.getState().form;
    WSClient.showOverlay(form);
    StateManager.setOverlayVisible(true);
    _showToast('Overlay đã hiện ✓', 'success');
  }

  _doHide() {
    WSClient.hideOverlay();
    StateManager.setOverlayVisible(false);
    _showToast('Overlay đã ẩn', 'info');
  }

  _doTransition() {
    WSClient.transition('slide-up');
    _showToast('Transition triggered', 'info');
  }

  _doClear() {
    WSClient.clearAll();
    StateManager.setOverlayVisible(false);
    _showToast('Đã xóa tất cả overlay', 'warning');
  }

  /**
   * Safety Cut — ưu tiên cao nhất.
   * Flash đỏ toàn màn hình + ẩn overlay ngay lập tức.
   */
  _doSafetyCut() {
    WSClient.safetyCut();
    StateManager.setOverlayVisible(false);
    _triggerSafetyFlash();
    _showToast('⚠ SAFETY CAM CUT ACTIVE', 'danger');
    console.warn('[Switcher] ⚠️ SAFETY CUT ACTIVATED');
  }

  /** Flash button khi nhấn phím tắt */
  _flashButton(btnEl) {
    if (!btnEl) return;
    btnEl.classList.add('flashing');
    setTimeout(() => btnEl.classList.remove('flashing'), 300);
  }
}

// ── Module-level helpers ──────────────────────────────
function _showToast(message, type = 'info') {
  if (typeof window._toast === 'function') { window._toast(message, type); return; }
  console.log(`[Toast:${type}] ${message}`);
}

function _triggerSafetyFlash() {
  if (typeof window._safetyFlash === 'function') { window._safetyFlash(); return; }
}
