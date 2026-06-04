/**
 * SceneController — Quản lý Cột 1 (Master Scenes)
 * @module controllers/SceneController
 */

import { BaseController } from './BaseController.js';
import StateManager       from '../core/StateManager.js';
import WSClient           from '../core/WSClient.js';
import SceneView          from '../views/SceneView.js';

export class SceneController extends BaseController {
  constructor() {
    super({ rootSelector: '#col-scenes' });
    this._view = new SceneView(this._rootEl);
  }

  /** @override */
  init() {
    // Re-render khi scene thay đổi
    StateManager.on(StateManager.EVENTS.SCENE_CHANGED,  () => this._render());
    // Re-render khi overlay state đổi (hiện dot ON AIR)
    StateManager.on(StateManager.EVENTS.OVERLAY_CHANGED, () => this._render());

    // Event delegation trên toàn body — vì list re-render
    const listEl = document.getElementById('scene-list');
    if (listEl) {
      listEl.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-scene-id]');
        if (btn) this._handleSceneClick(btn.dataset.sceneId);
      });
    }

    // Render lần đầu
    this._render();
    this._isActive = true;
  }

  // ── Private ──────────────────────────────────────
  _handleSceneClick(sceneId) {
    if (!sceneId || sceneId === StateManager.getState().activeSceneId) return;
    StateManager.setActiveScene(sceneId);
    WSClient.changeScene(sceneId);
  }

  _render() {
    const state = StateManager.getState();
    this._view.render({
      scenes        : state.scenes,
      activeSceneId : state.activeSceneId,
      overlayVisible: state.overlayVisible,
    });
  }
}
