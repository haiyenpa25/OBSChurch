/**
 * MonitorView — PGM Preview + WS Status + Timecode
 * @module views/MonitorView
 */

export default class MonitorView {
  constructor(rootEl) {
    const root = rootEl ?? document;
    this._overlayEl     = root.querySelector('#preview-overlay');
    this._primaryEl     = root.querySelector('#preview-primary');
    this._secondaryEl   = root.querySelector('#preview-secondary');
    this._timecodeEl    = root.querySelector('#panel-pgm #timecode');
    this._camFrameEl    = root.querySelector('#preview-cam-frame');

    // TopBar elements (nằm ngoài rootEl)
    this._wsStatusDot   = document.getElementById('ws-status-dot');
    this._wsStatusLabel = document.getElementById('ws-status-label');
    this._sessionEl     = document.getElementById('topbar-session');

    this._startClock();
  }

  /** Cập nhật preview (không gửi WS) */
  updatePreview(payload) {
    if (!this._overlayEl) return;
    const primary   = payload.primaryText;
    const secondary = payload.secondaryText;

    if (this._primaryEl)   this._primaryEl.textContent   = primary   || '—';
    if (this._secondaryEl) this._secondaryEl.textContent = secondary || '';

    // Đồng bộ class template phong cách
    const CLASSES = ['tpl-clean-dark', 'tpl-vintage', 'tpl-scifi', 'tpl-glassmorphism', 'tpl-sermon-topic', 'tpl-scripture-fullscreen'];
    CLASSES.forEach((c) => this._overlayEl.classList.remove(c));
    this._overlayEl.classList.add(`tpl-${payload.templateId}`);

    // Cập nhật vị trí & kích thước khung Camera ảo trên Preview
    if (this._camFrameEl) {
      this._camFrameEl.style.left         = `${payload.camX}%`;
      this._camFrameEl.style.top          = `${payload.camY}%`;
      this._camFrameEl.style.width        = `${payload.camW}%`;
      this._camFrameEl.style.height       = `${payload.camH}%`;
      this._camFrameEl.style.borderRadius = `${payload.camR}px`;
      this._camFrameEl.style.display      = (payload.camW > 0 && payload.camH > 0) ? '' : 'none';
    }

    // Cập nhật vị trí khung Chữ (trừ khi là template Kinh Thánh toàn màn hình)
    if (payload.templateId !== 'scripture-fullscreen') {
      this._overlayEl.style.left   = `${payload.textX}%`;
      this._overlayEl.style.top    = `${payload.textY}%`;
      this._overlayEl.style.bottom = 'auto';
      this._overlayEl.style.right  = 'auto';
    } else {
      this._overlayEl.style.left   = '';
      this._overlayEl.style.top    = '';
      this._overlayEl.style.bottom = '';
      this._overlayEl.style.right  = '';
    }

    const hasContent = primary && primary !== '—';
    if (hasContent) {
      this._overlayEl.classList.add('visible');
    } else {
      this._overlayEl.classList.remove('visible');
    }
  }

  /** Trạng thái kết nối OBS Studio */
  updateObsStatus(connected) {
    const dot   = document.getElementById('obs-status-dot');
    const label = document.getElementById('obs-status-label');
    if (!dot || !label) return;

    if (connected) {
      dot.style.background = '#4edea3';
      dot.style.boxShadow  = '0 0 6px rgba(78,222,163,0.6)';
      dot.className        = 'status-dot animate-pulse';
      label.textContent    = 'OBS Connected';
      label.style.color    = '#4edea3';
    } else {
      dot.style.background = '#666';
      dot.style.boxShadow  = 'none';
      dot.className        = 'status-dot';
      label.textContent    = 'OBS Offline';
      label.style.color    = '#666';
    }
  }

  /** WS Status — 3 trạng thái: online / offline / reconnecting */
  updateWsStatus(connected, mode = 'online') {
    if (!this._wsStatusDot || !this._wsStatusLabel) return;

    const CFG = {
      online       : { color: '#4edea3', shadow: '0 0 6px rgba(78,222,163,0.6)',  label: 'Live System Active', anim: 'animate-pulse' },
      reconnecting : { color: '#ff4444', shadow: '0 0 6px rgba(255,68,68,0.6)',   label: 'Đang kết nối lại...', anim: 'blink' },
      offline      : { color: '#ffb95f', shadow: '0 0 6px rgba(255,185,95,0.5)',  label: 'Offline Mode', anim: 'animate-pulse' },
    };

    const key = connected ? 'online' : mode;
    const cfg = CFG[key] ?? CFG.offline;

    this._wsStatusDot.style.background  = cfg.color;
    this._wsStatusDot.style.boxShadow   = cfg.shadow;
    this._wsStatusDot.className         = `status-dot ${cfg.anim}`;
    this._wsStatusLabel.textContent     = cfg.label;
    this._wsStatusLabel.style.color     = cfg.color;
  }

  /** Show/hide overlay preview */
  setOverlayVisible(visible) {
    if (!this._overlayEl) return;
    if (visible) this._overlayEl.classList.add('visible');
    else         this._overlayEl.classList.remove('visible');
  }

  /** Session label ở topbar */
  updateSession(sceneLabel) {
    if (this._sessionEl) {
      this._sessionEl.textContent = sceneLabel ? `SESSION: ${sceneLabel}` : '';
    }
  }

  /** Timecode HH:MM:SS:FF @30fps */
  _startClock() {
    const FPS = 30;
    const tick = () => {
      if (!this._timecodeEl) return;
      const now = new Date();
      const h   = String(now.getHours()).padStart(2, '0');
      const m   = String(now.getMinutes()).padStart(2, '0');
      const s   = String(now.getSeconds()).padStart(2, '0');
      const f   = String(Math.floor((now.getMilliseconds() / 1000) * FPS)).padStart(2, '0');
      this._timecodeEl.textContent = `${h}:${m}:${s}:${f}`;
    };
    setInterval(tick, Math.round(1000 / FPS));
  }
}
