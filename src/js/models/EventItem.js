/**
 * EventItem — Data Model
 * @module models/EventItem
 *
 * Đại diện cho 1 tiết mục con trong 1 Scene.
 * Type có thể là: music | prayer | speaker | scripture | announcement | camera
 */

import { BaseModel } from './BaseModel.js';

/** @enum {string} Các loại tiết mục */
const ITEM_TYPES = Object.freeze({
  MUSIC       : 'music',
  PRAYER      : 'prayer',
  SPEAKER     : 'speaker',
  SCRIPTURE   : 'scripture',
  ANNOUNCEMENT: 'announcement',
  CAMERA      : 'camera',
  TOPIC       : 'topic',
});

export class EventItem extends BaseModel {
  /**
   * @param {object} data
   * @param {string} data.id
   * @param {number} data.order
   * @param {string} data.label
   * @param {string} data.templateId
   * @param {string} data.type  - Một trong ITEM_TYPES
   */
  constructor(data) {
    super(data);
    this.id         = data.id         ?? '';
    this.order      = data.order      ?? 0;
    this.label      = data.label      ?? '';
    this.templateId = data.templateId ?? 'clean-dark';
    this.type       = data.type       ?? ITEM_TYPES.MUSIC;
  }

  /** @override */
  toJSON() {
    return {
      id        : this.id,
      order     : this.order,
      label     : this.label,
      templateId: this.templateId,
      type      : this.type,
    };
  }

  /** @override */
  static fromJSON(data) {
    return new EventItem(data);
  }

  /** @override — Validate bắt buộc có id và label. */
  validate() {
    const errors = [];
    const e1 = this._requireField(this.id,    'id');
    const e2 = this._requireField(this.label, 'label');
    if (e1) errors.push(e1);
    if (e2) errors.push(e2);
    return { valid: errors.length === 0, errors };
  }

  /** Label ngắn cho badge loại tiết mục. */
  get typeLabel() {
    const map = {
      [ITEM_TYPES.MUSIC]       : '♪ Music',
      [ITEM_TYPES.PRAYER]      : '🙏 Prayer',
      [ITEM_TYPES.SPEAKER]     : '🎤 Speaker',
      [ITEM_TYPES.SCRIPTURE]   : '📖 Scripture',
      [ITEM_TYPES.ANNOUNCEMENT]: '📢 Notice',
      [ITEM_TYPES.CAMERA]      : '📷 Camera',
      [ITEM_TYPES.TOPIC]       : '💡 Topic',
    };
    return map[this.type] ?? this.type;
  }
}

export { ITEM_TYPES };
