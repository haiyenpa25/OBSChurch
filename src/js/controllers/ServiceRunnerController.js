/**
 * ServiceRunnerController — Điều khiển Trình chạy lễ một chạm
 * @module controllers/ServiceRunnerController
 */

import StateManager from '../core/StateManager.js';
import OBSClient    from '../core/OBSClient.js';
import WSClient     from '../core/WSClient.js';
import { OverlayPayload } from '../models/OverlayPayload.js';

export class ServiceRunnerController {
  constructor() {
    this._rootEl     = document.getElementById('col-timeline');
    this._listEl     = document.getElementById('timeline-list');
    this._btnNext    = document.getElementById('btn-auto-next');
    this._btnAuto    = document.getElementById('btn-flow-auto');
    this._statusEl   = document.getElementById('auto-status');
    this._nextLblEl  = document.getElementById('auto-next-label');
    
    this._flatItems  = [];
    this._currentIndex = -1;
    this._autoPilot  = false;
    this._timerId    = null;
  }

  init() {
    if (!this._rootEl) return;
    
    // Đăng ký sự kiện
    StateManager.on(StateManager.EVENTS.SCENE_CHANGED, () => this._syncActiveState());
    StateManager.on(StateManager.EVENTS.ITEM_SELECTED, () => this._syncActiveState());
    
    // Lắng nghe khi danh sách scenes được load
    StateManager.on('state:scene_changed', () => this._rebuildTimeline());
    
    this._btnNext.addEventListener('click', () => this._handleNextClick());
    this._btnAuto.addEventListener('click', () => this._toggleAutoPilot());
    
    // Bắt sự kiện click trực tiếp vào timeline card
    this._listEl.addEventListener('click', (e) => {
      const card = e.target.closest('.timeline-card');
      if (card) this._selectTimelineIndex(parseInt(card.dataset.index));
    });

    this._rebuildTimeline();
  }

  // ── Private ──────────────────────────────────────
  _rebuildTimeline() {
    const state = StateManager.getState();
    this._flatItems = [];
    
    state.scenes.forEach(scene => {
      scene.items.forEach(item => {
        this._flatItems.push({
          ...item,
          sceneId: scene.id,
          sceneLabel: scene.label
        });
      });
    });

    this._renderTimeline();
    this._syncActiveState();
  }

  _renderTimeline() {
    if (this._flatItems.length === 0) {
      this._listEl.innerHTML = '<div class="items-empty">Chưa có lộ trình lễ</div>';
      return;
    }

    const typeIcons = {
      music: 'music_note', announcement: 'campaign', prayer: 'front_hand',
      speaker: 'mic', scripture: 'menu_book', topic: 'subject', camera: 'videocam'
    };

    this._listEl.innerHTML = this._flatItems.map((item, idx) => `
      <div class="timeline-card item-card" data-index="${idx}" id="tl-card-${idx}">
        <div class="item-card-top">
          <span class="item-idx">${String(idx + 1).padStart(2, '0')}</span>
          <span class="material-symbols-outlined item-type-icon">${typeIcons[item.type] || 'label'}</span>
          <span class="item-label">${item.label}</span>
          <span class="timeline-status-badge hidden">PGM</span>
        </div>
        <div class="item-card-bot">
          <span class="item-tpl" style="color: #666;">Cảnh: ${item.sceneLabel}</span>
          <span class="item-pos">${item.templateId}</span>
        </div>
      </div>
    `).join('');
  }

  _selectTimelineIndex(index) {
    if (index < 0 || index >= this._flatItems.length) return;
    this._currentIndex = index;
    
    const item = this._flatItems[index];
    
    // 1. Chuyển scene trên OBS
    if (StateManager.getState().obsConnected) {
      OBSClient.sendRequest('SetCurrentProgramScene', { sceneName: item.sceneLabel })
        .catch(err => console.warn('[Auto] Không chuyển cảnh OBS:', err));
    }
    
    // 2. Select item trong State
    StateManager.setActiveScene(item.sceneId);
    StateManager.setActiveItem(item.id);
    
    // 3. Tự động Push overlay nếu không phải camera trần
    if (item.type !== 'camera') {
      this._triggerPush();
    } else {
      WSClient.clearOverlay();
      StateManager.setOverlayVisible(false);
    }

    this._syncActiveState();
    this._checkAutoAdvance(item);
  }

  _triggerPush() {
    const formData = StateManager.getState().form;
    const payload = new OverlayPayload(formData);
    WSClient.showOverlay(payload.toJSON());
    StateManager.setOverlayVisible(true);
    
    if (StateManager.getState().obsConnected) {
      OBSClient.syncCameraTransform(this._flatItems[this._currentIndex].sceneLabel, 'Camera', formData);
    }
  }

  _checkAutoAdvance(item) {
    clearTimeout(this._timerId);
    
    // Nếu là Đầu giờ (mở đầu) và đang bật tự động chạy
    if (item.sceneId === 'dau-gio' && this._autoPilot) {
      let seconds = 8;
      this._statusEl.textContent = `Tự chuyển cảnh sau ${seconds}s...`;
      this._statusEl.style.color = '#ffa040';
      
      const tick = () => {
        seconds--;
        if (seconds > 0) {
          if (this._currentIndex === this._flatItems.indexOf(item)) {
            this._statusEl.textContent = `Tự chuyển cảnh sau ${seconds}s...`;
            this._timerId = setTimeout(tick, 1000);
          }
        } else {
          this._selectTimelineIndex(this._currentIndex + 1);
        }
      };
      this._timerId = setTimeout(tick, 1000);
    }
  }

  _handleNextClick() {
    if (this._currentIndex < this._flatItems.length - 1) {
      this._selectTimelineIndex(this._currentIndex + 1);
    } else {
      // Kết thúc lễ, reset về đầu
      this._currentIndex = -1;
      this._syncActiveState();
      WSClient.clearOverlay();
      StateManager.setOverlayVisible(false);
    }
  }

  _toggleAutoPilot() {
    this._autoPilot = !this._autoPilot;
    this._btnAuto.classList.toggle('active', this._autoPilot);
    if (!this._autoPilot) {
      clearTimeout(this._timerId);
      this._syncActiveState();
    } else if (this._currentIndex >= 0) {
      this._checkAutoAdvance(this._flatItems[this._currentIndex]);
    }
  }

  _syncActiveState() {
    const state = StateManager.getState();
    const activeItem = StateManager.getActiveItem();
    
    // Tìm index của active item trong flat list
    if (activeItem) {
      const idx = this._flatItems.findIndex(i => i.id === activeItem.id);
      if (idx !== -1) this._currentIndex = idx;
    }

    // Cập nhật CSS cards
    this._flatItems.forEach((_, idx) => {
      const card = document.getElementById(`tl-card-${idx}`);
      if (!card) return;
      
      const isCurrent = idx === this._currentIndex;
      card.classList.toggle('active', isCurrent);
      
      const badge = card.querySelector('.timeline-status-badge');
      if (badge) badge.classList.toggle('hidden', !isCurrent);
    });

    // Cập nhật nút bấm
    if (this._currentIndex === -1) {
      this._btnNext.innerHTML = '<span class="material-symbols-outlined">play_arrow</span> KHỞI ĐỘNG BUỔI LỄ';
      this._btnNext.className = 'btn-action btn-push';
      this._statusEl.textContent = 'Chờ khởi động';
      this._statusEl.style.color = '';
      this._nextLblEl.textContent = this._flatItems[0]?.label || 'Trống';
    } else {
      const isLast = this._currentIndex === this._flatItems.length - 1;
      const nextItem = this._flatItems[this._currentIndex + 1];
      
      this._btnNext.innerHTML = `<span class="material-symbols-outlined">navigate_next</span> ${isLast ? 'KẾT THÚC LỄ ✓' : 'KÍCH HOẠT TIẾP THEO'}`;
      this._btnNext.className = isLast ? 'btn-action btn-clear' : 'btn-action btn-push';
      
      if (!this._timerId || !this._autoPilot || this._flatItems[this._currentIndex].sceneId !== 'dau-gio') {
        this._statusEl.textContent = 'Đang On-Air';
        this._statusEl.style.color = '#ff4444';
      }
      
      this._nextLblEl.textContent = nextItem ? nextItem.label : 'Hết lễ';
    }
  }
}
