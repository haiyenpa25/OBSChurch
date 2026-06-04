/**
 * MonitorView — PGM Preview + WS Status + Timecode
 * @module views/MonitorView
 */

export default class MonitorView {
  constructor(rootEl) {
    this._overlayEl     = document.getElementById('preview-overlay');
    this._primaryEl     = document.getElementById('preview-primary');
    this._secondaryEl   = document.getElementById('preview-secondary');
    this._wsStatusDot   = document.getElementById('ws-status-dot');
    this._wsStatusLabel = document.getElementById('ws-status-label');
    this._sessionEl     = document.getElementById('topbar-session');
    // Timecode — id "timecode" có 2 nơi (topbar + pgm), lấy phần pgm
    this._timecodeEl    = document.querySelector('#panel-pgm #timecode');
    this._startClock();
  }

  /** Cập nhật preview (không gửi WS) */
  updatePreview(payload) {
    if (!this._overlayEl) return;
    const primary   = payload.primaryText;
    const secondary = payload.secondaryText;

    if (this._primaryEl)   this._primaryEl.textContent   = primary   || '—';
    if (this._secondaryEl) this._secondaryEl.textContent = secondary || '';

    const hasContent = primary && primary !== '—';
    if (hasContent) {
      this._overlayEl.classList.add('visible');
    } else {
      this._overlayEl.classList.remove('visible');
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
