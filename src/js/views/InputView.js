/**
 * InputView — Cột 3: quản lý DOM form
 * @module views/InputView
 */

// Fields hiện theo item type
const CONTEXT_MAP = Object.freeze({
  music       : ['songName'],
  speaker     : ['speakerName'],
  scripture   : ['scriptureRef', 'scriptureVerse'],
  announcement: ['announcement'],
  prayer      : ['announcement'],
  topic       : ['songName', 'speakerName'],
  camera      : [],
});

// Tất cả field groups
const ALL_FIELDS = ['songName', 'scriptureRef', 'scriptureVerse', 'speakerName', 'announcement'];

export default class InputView {
  constructor(rootEl) {
    this._rootEl      = rootEl ?? document;
    this._errorEl     = this._rootEl.querySelector('#input-error');
    this._successEl   = this._rootEl.querySelector('#input-success');
    this._timestampEl = this._rootEl.querySelector('#save-timestamp');
    this._showAllBtn  = this._rootEl.querySelector('#btn-show-all-fields');
    this._showAll     = false;
    this._allGroupEls = this._queryAllGroups();
  }

  _queryAllGroups() {
    return Array.from(
      this._rootEl.querySelectorAll('[data-field-group]')
    );
  }

  /** Ẩn/hiện fields theo item type */
  applyItemContext(type) {
    if (this._showAll) return;
    const visible = CONTEXT_MAP[type] ?? ALL_FIELDS;
    this._allGroupEls.forEach((el) => {
      const name = el.dataset.fieldGroup;
      el.style.display = visible.includes(name) ? '' : 'none';
    });
    if (this._showAllBtn) {
      this._showAllBtn.style.display = visible.length < ALL_FIELDS.length ? '' : 'none';
    }
  }

  /** Hiện tất cả fields */
  showAllFields() {
    this._showAll = true;
    this._allGroupEls.forEach((el) => { el.style.display = ''; });
    if (this._showAllBtn) this._showAllBtn.style.display = 'none';
  }

  /** Sync DOM inputs từ state */
  setFormValues(form) {
    const map = {
      templateId    : '#select-template',
      songName      : '#input-song-name',
      scriptureRef  : '#input-scripture-ref',
      scriptureVerse: '#input-scripture-verse',
      speakerName   : '#input-speaker',
      announcement  : '#input-announcement',
      camX          : '[data-field="camX"]',
      camY          : '[data-field="camY"]',
      camW          : '[data-field="camW"]',
      camH          : '[data-field="camH"]',
      camR          : '[data-field="camR"]',
      textX         : '[data-field="textX"]',
      textY         : '[data-field="textY"]',
      customCss     : '[data-field="customCss"]',
    };
    Object.entries(map).forEach(([key, sel]) => {
      const el = this._rootEl.querySelector(sel);
      if (el && el.value !== String(form[key] ?? '')) el.value = form[key] ?? '';
    });
    // Sync active class cho Template Cards Gallery
    this._syncActiveTemplateCard(form.templateId);
    // Sync khung mini ảo
    this.updateMiniLayout(form);
  }

  updateMiniLayout(form) {
    const camBox = this._rootEl.querySelector('#mini-cam-box');
    const textBox = this._rootEl.querySelector('#mini-text-box');
    if (camBox) {
      camBox.style.left = `${form.camX}%`;
      camBox.style.top = `${form.camY}%`;
      camBox.style.width = `${form.camW}%`;
      camBox.style.height = `${form.camH}%`;
      camBox.style.borderRadius = `${form.camR}px`;
    }
    if (textBox) {
      textBox.style.left = `${form.textX}%`;
      textBox.style.top = `${form.textY}%`;
    }
  }

  selectTemplateCard(templateId) {
    const input = this._rootEl.querySelector('#select-template');
    if (input) {
      input.value = templateId;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    this._syncActiveTemplateCard(templateId);
  }

  _syncActiveTemplateCard(templateId) {
    const cards = this._rootEl.querySelectorAll('.tpl-card');
    cards.forEach((c) => {
      if (c.dataset.templateId === templateId) c.classList.add('active');
      else c.classList.remove('active');
    });
  }

  toggleObsModal(show) {
    const modal = document.getElementById('obs-settings-modal');
    if (modal) modal.classList.toggle('hidden', !show);
  }

  setObsConfigValues(config) {
    const host = document.getElementById('obs-input-host');
    const port = document.getElementById('obs-input-port');
    const pass = document.getElementById('obs-input-password');
    if (host) host.value = config.host || '';
    if (port) port.value = config.port || 4455;
    if (pass) pass.value = config.password || '';
  }

  getObsConfigValues() {
    return {
      host: document.getElementById('obs-input-host')?.value || 'localhost',
      port: Number(document.getElementById('obs-input-port')?.value) || 4455,
      password: document.getElementById('obs-input-password')?.value || '',
    };
  }

  showError(msg) {
    if (this._errorEl) {
      this._errorEl.textContent = '⚠ ' + msg;
      this._errorEl.classList.remove('hidden');
    }
    this._successEl?.classList.add('hidden');
  }

  clearError() {
    this._errorEl?.classList.add('hidden');
  }

  flashSuccess(msg, ms = 2500) {
    if (!this._successEl) return;
    this._successEl.textContent = '✓ ' + msg;
    this._successEl.classList.remove('hidden');
    this.clearError();
    clearTimeout(this._successTimer);
    this._successTimer = setTimeout(() => this._successEl?.classList.add('hidden'), ms);
  }

  setSaveTimestamp(ts) {
    if (this._timestampEl) {
      this._timestampEl.textContent = `Đã lưu lúc ${ts}`;
      this._timestampEl.classList.remove('hidden');
    }
  }
}
