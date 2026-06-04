/**
 * PanelCollapseController — Collapse/Expand cho 3 cột trái
 * @module controllers/PanelCollapseController
 *
 * Tính năng:
 * - Mỗi panel có toggle button → collapse về 40px
 * - State lưu localStorage key "obs_panel_state"
 * - Animation: CSS width transition
 * - Label dọc khi collapsed
 */

import { BaseController } from './BaseController.js';

// IDs của các panels có thể collapse
const COLLAPSIBLE_PANELS = Object.freeze([
  { id: 'col-scenes', label: 'Scenes' },
  { id: 'col-items',  label: 'Items'  },
  { id: 'col-input',  label: 'Input'  },
]);

const STORAGE_KEY = 'obs_panel_state';

export class PanelCollapseController extends BaseController {
  constructor() {
    super({ rootSelector: '#main-content' });
    /** @type {Map<string, boolean>} panelId → isCollapsed */
    this._collapseState = new Map();
  }

  /** @override */
  init() {
    // Load từ localStorage
    this._loadState();

    COLLAPSIBLE_PANELS.forEach(({ id }) => {
      const panel   = document.getElementById(id);
      const toggleBtn = panel?.querySelector('[data-panel-toggle]');
      if (!toggleBtn) return;

      // Apply saved state
      if (this._collapseState.get(id)) {
        this._applyCollapse(id, true, false); // no animation on init
      }

      // Click handler
      this._on(toggleBtn, 'click', () => this._toggle(id));
    });

    this._isActive = true;
  }

  // ── Private ──────────────────────────────────────
  _toggle(panelId) {
    const isCollapsed = this._collapseState.get(panelId) ?? false;
    this._applyCollapse(panelId, !isCollapsed, true);
    this._saveState();
  }

  _applyCollapse(panelId, collapse, animate) {
    const panel = document.getElementById(panelId);
    if (!panel) return;

    this._collapseState.set(panelId, collapse);

    if (!animate) panel.classList.add('no-transition');

    if (collapse) {
      panel.classList.add('panel--collapsed');
      panel.setAttribute('aria-expanded', 'false');
    } else {
      panel.classList.remove('panel--collapsed');
      panel.setAttribute('aria-expanded', 'true');
    }

    // Update toggle button icon
    const btn  = panel.querySelector('[data-panel-toggle]');
    const icon = btn?.querySelector('.material-symbols-outlined');
    if (icon) {
      icon.textContent = collapse ? 'chevron_right' : 'chevron_left';
    }

    if (!animate) {
      requestAnimationFrame(() => panel.classList.remove('no-transition'));
    }
  }

  _saveState() {
    const obj = {};
    this._collapseState.forEach((val, key) => { obj[key] = val; });
    localStorage.setItem(STORAGE_KEY, JSON.stringify(obj));
  }

  _loadState() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const obj = JSON.parse(saved);
        Object.entries(obj).forEach(([k, v]) => this._collapseState.set(k, v));
      }
    } catch (_) { /* ignore */ }
  }
}
