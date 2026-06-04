/**
 * OverlayPayload — Data Model
 * @module models/OverlayPayload
 *
 * Chuẩn hóa payload gửi qua WebSocket khi push to PGM.
 * Validate trước khi send để tránh dữ liệu lỗi hiển thị trên sân khấu.
 */

import { BaseModel } from './BaseModel.js';

export class OverlayPayload extends BaseModel {
  /**
   * @param {object} data
   * @param {string} data.templateId
   * @param {string} [data.songName]
   * @param {string} [data.scriptureRef]
   * @param {string} [data.scriptureVerse]
   * @param {string} [data.speakerName]
   * @param {string} [data.announcement]
   */
  constructor(data) {
    super(data);
    this.templateId     = data.templateId     ?? 'clean-dark';
    this.songName       = data.songName       ?? '';
    this.scriptureRef   = data.scriptureRef   ?? '';
    this.scriptureVerse = data.scriptureVerse ?? '';
    this.speakerName    = data.speakerName    ?? '';
    this.announcement   = data.announcement   ?? '';
    
    // Layout fields
    this.camX           = data.camX           ?? 5;
    this.camY           = data.camY           ?? 5;
    this.camW           = data.camW           ?? 25;
    this.camH           = data.camH           ?? 25;
    this.camR           = data.camR           ?? 8;
    this.textX          = data.textX          ?? 5;
    this.textY          = data.textY          ?? 80;
    this.customCss      = data.customCss      ?? '';
    
    this.timestamp      = Date.now();
  }

  /** @override */
  toJSON() {
    return {
      templateId    : this.templateId,
      songName      : this.songName,
      scriptureRef  : this.scriptureRef,
      scriptureVerse: this.scriptureVerse,
      speakerName   : this.speakerName,
      announcement  : this.announcement,
      camX          : this.camX,
      camY          : this.camY,
      camW          : this.camW,
      camH          : this.camH,
      camR          : this.camR,
      textX         : this.textX,
      textY         : this.textY,
      customCss     : this.customCss,
      timestamp     : this.timestamp,
    };
  }

  /** @override */
  static fromJSON(data) {
    return new OverlayPayload(data);
  }

  /**
   * @override
   * Ít nhất 1 field nội dung phải có giá trị.
   */
  validate() {
    const hasContent = [
      this.songName, this.scriptureRef, this.speakerName, this.announcement,
    ].some((v) => v.trim() !== '');

    const errors = hasContent ? [] : ['Cần ít nhất 1 trường nội dung để hiển thị overlay.'];
    return { valid: hasContent, errors };
  }

  /**
   * Lấy primary text để hiển thị trên preview (Col4).
   * Ưu tiên: songName → speakerName → announcement → scriptureRef
   */
  get primaryText() {
    return this.songName || this.speakerName || this.announcement || this.scriptureRef || '—';
  }

  /** Lấy secondary text (subtitle) cho overlay. */
  get secondaryText() {
    if (this.scriptureRef) {
      return this.scriptureVerse
        ? `${this.scriptureRef}:${this.scriptureVerse}`
        : this.scriptureRef;
    }
    return this.speakerName || '';
  }
}
