/**
 * BaseController — Abstract base for all controllers
 * @module controllers/BaseController
 *
 * Nguyên tắc Kế thừa:
 * SceneController, ItemController, InputController, SwitcherController
 * đều extends BaseController.
 *
 * Interface bắt buộc: init(), destroy()
 * Hook tùy chọn: onActivate(), onDeactivate()
 */

export class BaseController {
  /**
   * @param {object} opts
   * @param {string} opts.rootSelector - CSS selector của DOM container
   */
  constructor({ rootSelector }) {
    if (new.target === BaseController) {
      throw new Error('BaseController is abstract.');
    }
    this._rootEl    = document.querySelector(rootSelector);
    this._handlers  = []; // [{el, event, fn}] để cleanup
    this._isActive  = false;

    if (!this._rootEl) {
      console.warn(`[${this.constructor.name}] Root element not found: ${rootSelector}`);
    }
  }

  /**
   * Khởi tạo controller — subclass PHẢI override.
   * @abstract
   */
  init() {
    throw new Error(`${this.constructor.name} must implement init()`);
  }

  /**
   * Dọn dẹp event listeners khi teardown.
   * Subclass có thể override để thêm logic riêng.
   */
  destroy() {
    this._handlers.forEach(({ el, event, fn }) => {
      el.removeEventListener(event, fn);
    });
    this._handlers = [];
    this._isActive = false;
  }

  /** Hook: gọi khi controller trở thành active (optional override). */
  onActivate()   {}

  /** Hook: gọi khi controller không còn active (optional override). */
  onDeactivate() {}

  // ── Protected Utilities ────────────────────────
  /**
   * Đăng ký event listener và tự động track để cleanup.
   * @param {Element}  el
   * @param {string}   event
   * @param {Function} fn
   */
  _on(el, event, fn) {
    if (!el) return;
    el.addEventListener(event, fn);
    this._handlers.push({ el, event, fn });
  }

  /**
   * Delegate event từ container xuống target selector.
   * Tránh gắn listener vào từng item con.
   */
  _delegate(container, event, selector, fn) {
    const handler = (e) => {
      const target = e.target.closest(selector);
      if (target && container.contains(target)) fn(e, target);
    };
    this._on(container, event, handler);
  }

  /** Query con trong root. @returns {Element|null} */
  _q(selector) {
    return this._rootEl?.querySelector(selector) ?? null;
  }

  /** Query all trong root. @returns {NodeList} */
  _qAll(selector) {
    return this._rootEl?.querySelectorAll(selector) ?? [];
  }
}
