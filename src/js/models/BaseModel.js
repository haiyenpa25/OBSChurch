/**
 * BaseModel — Abstract base for all data models
 * @module models/BaseModel
 *
 * Nguyên tắc Kế thừa:
 * Tất cả models (Scene, EventItem, OverlayPayload) extends BaseModel.
 * BaseModel định nghĩa interface chung: fromJSON(), toJSON(), validate().
 */

export class BaseModel {
  /**
   * @param {object} data - Raw data object
   */
  constructor(data) {
    if (new.target === BaseModel) {
      throw new Error('BaseModel is abstract — cannot instantiate directly.');
    }
    this._raw = data;
  }

  /**
   * Serialize thành plain object (để gửi WS hoặc lưu localStorage).
   * @abstract
   * @returns {object}
   */
  toJSON() {
    throw new Error(`${this.constructor.name} must implement toJSON()`);
  }

  /**
   * Factory method — tạo instance từ raw JSON.
   * @abstract
   * @param {object} data
   * @returns {BaseModel}
   */
  static fromJSON(_data) {
    throw new Error('Subclass must implement static fromJSON()');
  }

  /**
   * Validate dữ liệu model.
   * Subclass có thể override để thêm validation riêng.
   * @returns {{ valid: boolean, errors: string[] }}
   */
  validate() {
    return { valid: true, errors: [] };
  }

  /** Tiện ích: kiểm tra field có tồn tại và không rỗng. */
  _requireField(value, fieldName) {
    if (!value || (typeof value === 'string' && value.trim() === '')) {
      return `"${fieldName}" là bắt buộc.`;
    }
    return null;
  }
}
