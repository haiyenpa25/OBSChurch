/**
 * ItemView — Render danh sách Event Items vào #item-list
 * @module views/ItemView
 */

const TYPE_ICON = Object.freeze({
  music       : 'music_note',
  prayer      : 'church',
  speaker     : 'mic',
  scripture   : 'menu_book',
  announcement: 'campaign',
  camera      : 'videocam',
  topic       : 'lightbulb',
});

export default class ItemView {
  constructor(rootEl) {
    this._listEl  = document.getElementById('item-list');
    this._subEl   = document.getElementById('items-scene-sub');
  }

  render({ items, activeItemId, overlayVisible, sceneLabel, totalItems }) {
    if (!this._listEl) return;

    if (this._subEl) {
      this._subEl.textContent = sceneLabel ? `— ${sceneLabel} —` : '';
    }

    if (!items || items.length === 0) {
      this._listEl.innerHTML = `
        <div class="items-empty">
          <span class="material-symbols-outlined">playlist_remove</span>
          Chọn cảnh để xem tiết mục
        </div>`;
      return;
    }

    this._listEl.innerHTML = items.map((item, idx) =>
      this._renderItem(item, item.id === activeItemId, overlayVisible, idx + 1, totalItems)
    ).join('');
  }

  _renderItem(item, isActive, overlayVisible, index, total) {
    const isOnAir = isActive && overlayVisible;
    const icon    = TYPE_ICON[item.type] ?? 'circle';

    return `
      <div class="item-card ${isActive ? 'active' : ''}"
           data-item-id="${item.id}"
           role="button"
           tabindex="0"
           aria-pressed="${isActive}">
        <div class="item-card-top">
          <span class="item-idx">${String(index).padStart(2,'0')}</span>
          <span class="material-symbols-outlined item-type-icon">${icon}</span>
          <span class="item-label">${item.label}</span>
          ${isOnAir ? '<span class="item-on-air">● ON AIR</span>' : ''}
        </div>
        <div class="item-card-bot">
          <span class="item-tpl">${item.templateId}</span>
          <span class="item-pos">${index}/${total}</span>
        </div>
      </div>
    `;
  }
}
