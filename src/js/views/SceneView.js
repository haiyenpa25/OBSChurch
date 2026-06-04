/**
 * SceneView — Render danh sách Scene vào #scene-list
 * @module views/SceneView
 */

export default class SceneView {
  constructor(rootEl) {
    this._listEl = document.getElementById('scene-list');
  }

  render({ scenes, activeSceneId, overlayVisible }) {
    if (!this._listEl) return;
    if (!scenes || scenes.length === 0) {
      this._listEl.innerHTML = '<div style="padding:8px;color:#444;font-size:11px;font-family:monospace;text-align:center">No scenes</div>';
      return;
    }
    this._listEl.innerHTML = scenes.map((scene) =>
      this._renderItem(scene, scene.id === activeSceneId, overlayVisible)
    ).join('');
  }

  _renderItem(scene, isActive, overlayVisible) {
    const isOnAir = isActive && overlayVisible;
    return `
      <button
        class="scene-btn ${isActive ? 'active' : ''}"
        data-scene-id="${scene.id}"
        aria-pressed="${isActive}"
        title="${scene.label}"
      >
        <span class="scene-name">${scene.label}</span>
        ${isOnAir ? '<span class="scene-air-dot"></span>' : ''}
        <span class="scene-count">${scene.items.length}</span>
      </button>
    `;
  }
}
