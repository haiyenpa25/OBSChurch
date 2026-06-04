/**
 * Scene — Data Model
 * @module models/Scene
 *
 * Kế thừa từ BaseModel.
 * Đại diện cho 1 giai đoạn lớn của buổi lễ.
 */

import { BaseModel } from './BaseModel.js';
import { EventItem  } from './EventItem.js';

export class Scene extends BaseModel {
  /**
   * @param {object} data
   * @param {string} data.id
   * @param {string} data.label
   * @param {Array}  data.items
   */
  constructor(data) {
    super(data);
    this.id    = data.id    ?? '';
    this.label = data.label ?? '';
    this.items = (data.items ?? []).map((i) => new EventItem(i));
  }

  /** @override */
  toJSON() {
    return {
      id   : this.id,
      label: this.label,
      items: this.items.map((i) => i.toJSON()),
    };
  }

  /** Factory: tạo từ JSON thô. @override */
  static fromJSON(data) {
    return new Scene(data);
  }

  /** Validate: scene phải có id và label. @override */
  validate() {
    const errors = [];
    const e1 = this._requireField(this.id,    'id');
    const e2 = this._requireField(this.label, 'label');
    if (e1) errors.push(e1);
    if (e2) errors.push(e2);
    return { valid: errors.length === 0, errors };
  }

  /** Lấy item theo ID. @returns {EventItem|null} */
  getItem(itemId) {
    return this.items.find((i) => i.id === itemId) ?? null;
  }

  /** Lấy item tiếp theo so với itemId hiện tại. */
  getNextItem(currentItemId) {
    const idx = this.items.findIndex((i) => i.id === currentItemId);
    if (idx === -1) return this.items[0] ?? null;
    return this.items[idx + 1] ?? null;
  }

  /** Lấy item trước đó. */
  getPrevItem(currentItemId) {
    const idx = this.items.findIndex((i) => i.id === currentItemId);
    if (idx <= 0) return null;
    return this.items[idx - 1];
  }

  /** Index của item hiện tại (1-based). */
  getItemIndex(itemId) {
    return this.items.findIndex((i) => i.id === itemId) + 1;
  }
}
