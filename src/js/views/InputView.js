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
    this._errorEl     = document.getElementById('input-error');
    this._successEl   = document.getElementById('input-success');
    this._timestampEl = document.getElementById('save-timestamp');
    this._showAllBtn  = document.getElementById('btn-show-all-fields');
    this._showAll     = false;
    this._allGroupEls = this._queryAllGroups();
  }

  _queryAllGroups() {
    return Array.from(
      document.querySelectorAll('[data-field-group]')
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
    };
    Object.entries(map).forEach(([key, sel]) => {
      const el = document.querySelector(sel);
      if (el && el.value !== (form[key] ?? '')) el.value = form[key] ?? '';
    });
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
