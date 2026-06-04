/**
 * EventBus — Pub/Sub Observer Pattern
 * @module core/EventBus
 *
 * Tách rời hoàn toàn Controller và View.
 * Không component nào import component khác trực tiếp.
 *
 * Sử dụng:
 *   EventBus.on('scene:changed', handler)
 *   EventBus.emit('scene:changed', { sceneId })
 *   EventBus.off('scene:changed', handler)
 */

const EventBus = (() => {
  /** @type {Map<string, Set<Function>>} */
  const _listeners = new Map();

  /**
   * Đăng ký lắng nghe sự kiện.
   * @param {string}   event   - Tên sự kiện (namespace:action)
   * @param {Function} handler - Callback nhận data
   * @returns {Function} Unsubscribe function
   */
  function on(event, handler) {
    if (!_listeners.has(event)) _listeners.set(event, new Set());
    _listeners.get(event).add(handler);
    return () => off(event, handler);
  }

  /**
   * Hủy đăng ký listener.
   * @param {string}   event
   * @param {Function} handler
   */
  function off(event, handler) {
    _listeners.get(event)?.delete(handler);
  }

  /**
   * Phát sự kiện tới tất cả listeners.
   * @param {string} event
   * @param {*}      data
   */
  function emit(event, data) {
    _listeners.get(event)?.forEach((handler) => {
      try {
        handler(data);
      } catch (err) {
        console.error(`[EventBus] Error in handler for "${event}":`, err);
      }
    });
  }

  /** Xóa tất cả listeners của 1 event (dùng khi teardown component). */
  function clear(event) {
    _listeners.delete(event);
  }

  return { on, off, emit, clear };
})();

export default EventBus;
