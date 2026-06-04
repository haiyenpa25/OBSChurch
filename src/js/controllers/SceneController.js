/**
 * SceneController — Quản lý Cột 1 (Master Scenes)
 * @module controllers/SceneController
 */

import { BaseController } from './BaseController.js';
import StateManager       from '../core/StateManager.js';
import WSClient           from '../core/WSClient.js';
import OBSClient          from '../core/OBSClient.js';
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

    // Event delegation qua BaseController utility
    this._delegate(this._rootEl, 'click', '[data-scene-id]', (e, target) => {
      this._handleSceneClick(target.dataset.sceneId);
    });

    // Render lần đầu
    this._render();
    this._isActive = true;
  }

  // ── Private ──────────────────────────────────────
  _handleSceneClick(sceneId) {
    if (!sceneId || sceneId === StateManager.getState().activeSceneId) return;
    StateManager.setActiveScene(sceneId);
    WSClient.changeScene(sceneId);
    
    // Đồng bộ sang OBS Studio
    const scene = StateManager.getActiveScene();
    if (scene && StateManager.getState().obsConnected) {
      OBSClient.sendRequest('SetCurrentProgramScene', { sceneName: scene.label })
        .catch((err) => console.warn('[OBS] SetScene error:', err));
    }
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
