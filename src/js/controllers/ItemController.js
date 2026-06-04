/**
 * ItemController — Quản lý Cột 2 (Event Items)
 * @module controllers/ItemController
 */

import { BaseController } from './BaseController.js';
import StateManager       from '../core/StateManager.js';
import ItemView           from '../views/ItemView.js';

export class ItemController extends BaseController {
  constructor() {
    super({ rootSelector: '#col-items' });
    this._view = new ItemView(this._rootEl);
  }

  /** @override */
  init() {
    StateManager.on(StateManager.EVENTS.SCENE_CHANGED,   () => this._render());
    StateManager.on(StateManager.EVENTS.ITEM_SELECTED,   () => this._render());
    StateManager.on(StateManager.EVENTS.OVERLAY_CHANGED, () => this._render());

    // Click item
    this._delegate(this._rootEl, 'click', '[data-item-id]', (e, el) => {
      this._handleItemClick(el.dataset.itemId);
    });

    // Prev/Next buttons trong header
    this._on(this._q('#btn-item-prev'), 'click', () => this._navigate(-1));
    this._on(this._q('#btn-item-next'), 'click', () => this._navigate(1));

    this._render();
    this._isActive = true;
  }

  // ── Public API (gọi từ SwitcherController) ──────
  navigateNext() { this._navigate(1); }
  navigatePrev() { this._navigate(-1); }

  // ── Private ──────────────────────────────────────
  _handleItemClick(itemId) {
    if (!itemId) return;
    StateManager.setActiveItem(itemId);
    // Scroll item vào view
    this._scrollToActive(itemId);
  }

  _navigate(direction) {
    const scene  = StateManager.getActiveScene();
    const itemId = StateManager.getState().activeItemId;
    if (!scene) return;

    let nextItem;
    if (!itemId) {
      nextItem = direction > 0 ? scene.items[0] : scene.items[scene.items.length - 1];
    } else {
      nextItem = direction > 0
        ? scene.getNextItem(itemId)
        : scene.getPrevItem(itemId);
    }

    if (nextItem) {
      StateManager.setActiveItem(nextItem.id);
      this._scrollToActive(nextItem.id);
    }
  }

  _scrollToActive(itemId) {
    requestAnimationFrame(() => {
      const el = this._rootEl?.querySelector(`[data-item-id="${itemId}"]`);
      el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  }

  _render() {
    const scene          = StateManager.getActiveScene();
    const state          = StateManager.getState();
    const activeItemId   = state.activeItemId;
    const overlayVisible = state.overlayVisible;

    this._view.render({
      items         : scene?.items ?? [],
      activeItemId,
      overlayVisible,
      sceneLabel    : scene?.label ?? '',
      totalItems    : scene?.items.length ?? 0,
    });
  }
}
