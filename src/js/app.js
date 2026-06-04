/**
 * app.js — Bootstrap & Dependency Injection
 * @module app
 *
 * Điểm khởi động duy nhất.
 * MonitorView là SINGLETON — tạo 1 lần, inject vào controllers.
 */

import StateManager              from './core/StateManager.js';
import WSClient                  from './core/WSClient.js';
import OBSClient                 from './core/OBSClient.js';
import EventBus                  from './core/EventBus.js';
import { Scene                 } from './models/Scene.js';
import { SceneController       } from './controllers/SceneController.js';
import { ItemController        } from './controllers/ItemController.js';
import { InputController       } from './controllers/InputController.js';
import { SwitcherController    } from './controllers/SwitcherController.js';
import { ServiceRunnerController } from './controllers/ServiceRunnerController.js';
import MonitorView               from './views/MonitorView.js';

// ─── Constants ───────────────────────────────────────
const API_SCENES_URL  = 'http://localhost:3000/api/scenes';
const API_LAYOUTS_URL = 'http://localhost:3000/api/layouts';

// ─── Bootstrap ───────────────────────────────────────
async function main() {
  // 1. MonitorView SINGLETON — tạo trước, inject vào controllers
  const monitorView = new MonitorView(document.querySelector('#col-monitor'));

  // 2. Kết nối WebSocket & OBS
  WSClient.connect();
  OBSClient.connect();

  // 3. Load layouts & scenes data
  await _loadLayouts();
  await _loadScenes();

  // 4. Khởi tạo controllers theo thứ tự DI
  const itemCtrl  = new ItemController();
  const controllers = [
    new SceneController(),
    itemCtrl,
    new InputController(monitorView),        // inject MonitorView
    new SwitcherController(itemCtrl),        // inject ItemController
    new ServiceRunnerController(),           // Service Timeline Runner
  ];
  controllers.forEach((ctrl) => ctrl.init());

  // 5. Wiring global events
  _wireGlobalEvents(monitorView);
  _setupSidebarNavigation();

  // 6. Restore session
  _restoreSession();

  console.log('[App] OBSChurch v2 initialized ✓');
}


// ── Private ──────────────────────────────────────────
async function _loadLayouts() {
  try {
    const res = await fetch(API_LAYOUTS_URL);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const layouts = await res.json();
    StateManager.setLayouts(layouts);
  } catch (err) {
    console.warn('[App] Server không có layouts — dùng mặc định:', err.message);
    StateManager.setLayouts({
      'clean-dark': { camX: 5, camY: 5, camW: 25, camH: 25, camR: 8, textX: 5, textY: 80, customCss: '' },
      'vintage': { camX: 5, camY: 5, camW: 30, camH: 30, camR: 4, textX: 5, textY: 85, customCss: '' },
      'scifi': { camX: 70, camY: 5, camW: 25, camH: 25, camR: 0, textX: 5, textY: 80, customCss: '' },
      'glassmorphism': { camX: 5, camY: 5, camW: 25, camH: 25, camR: 12, textX: 5, textY: 80, customCss: '' },
      'sermon-topic': { camX: 0, camY: 0, camW: 0, camH: 0, camR: 0, textX: 25, textY: 45, customCss: '' },
      'scripture-fullscreen': { camX: 0, camY: 0, camW: 0, camH: 0, camR: 0, textX: 0, textY: 0, customCss: '' }
    });
  }
}

async function _loadScenes() {
  try {
    const res  = await fetch(API_SCENES_URL);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    const scenes = data.scenes.map((s) => Scene.fromJSON(s));
    StateManager.setScenes(scenes);
  } catch (err) {
    console.warn('[App] Server không có — dùng dữ liệu mặc định:', err.message);
    _loadFallbackScenes();
  }
}

/** Dữ liệu mặc định khi server chưa chạy */
function _loadFallbackScenes() {
  const FALLBACK = [
    { id: 'dau-gio',    label: 'Đầu giờ',         items: [{ id:'dg-01', order:1, label:'Nhạc nền đầu giờ',   templateId:'clean-dark', type:'music' },{ id:'dg-02', order:2, label:'Thông báo chào mừng', templateId:'glassmorphism', type:'announcement' }] },
    { id: 'chinh-le',   label: 'Chính lễ',         items: [{ id:'cl-01', order:1, label:'Bài hát khai lễ',   templateId:'clean-dark', type:'music' },{ id:'cl-02', order:2, label:'Cầu nguyện khai lễ', templateId:'vintage', type:'prayer' },{ id:'cl-03', order:3, label:'Bài hát 2', templateId:'clean-dark', type:'music' },{ id:'cl-04', order:4, label:'Bài hát 3', templateId:'clean-dark', type:'music' }] },
    { id: 'giang-luan', label: 'Giảng luận',       items: [{ id:'gl-01', order:1, label:'Giới thiệu diễn giả', templateId:'scifi', type:'speaker' },{ id:'gl-02', order:2, label:'Câu gốc Kinh Thánh', templateId:'vintage', type:'scripture' },{ id:'gl-03', order:3, label:'Đề tài giảng', templateId:'scifi', type:'topic' }] },
    { id: 'ton-vinh-don-ca',    label: 'Tôn vinh đơn ca',    items: [{ id:'tv-01', order:1, label:'Giới thiệu ca sĩ', templateId:'glassmorphism', type:'speaker' },{ id:'tv-02', order:2, label:'Bài hát đơn ca', templateId:'glassmorphism', type:'music' }] },
    { id: 'ton-vinh-ban-nganh', label: 'Tôn vinh ban ngành', items: [{ id:'tb-01', order:1, label:'Giới thiệu ban', templateId:'vintage', type:'speaker' },{ id:'tb-02', order:2, label:'Bài hát ban ngành', templateId:'vintage', type:'music' }] },
    { id: 'tiec-thanh', label: 'Tiệc thánh',       items: [{ id:'tt-01', order:1, label:'Bẻ bánh — Cầu nguyện', templateId:'vintage', type:'prayer' },{ id:'tt-02', order:2, label:'Dâng chén', templateId:'vintage', type:'prayer' }] },
    { id: 'cam-goc-rong', label: 'Cam gốc rộng',   items: [{ id:'cgr-01', order:1, label:'Wide Shot Active', templateId:'clean-dark', type:'camera' }] },
    { id: 'thong-bao',  label: 'Thông báo',        items: [{ id:'tba-01', order:1, label:'Thông báo 1', templateId:'glassmorphism', type:'announcement' },{ id:'tba-02', order:2, label:'Thông báo 2', templateId:'glassmorphism', type:'announcement' },{ id:'tba-03', order:3, label:'Thông báo 3', templateId:'glassmorphism', type:'announcement' }] },
    { id: 'tat-le',     label: 'Tất lễ',           items: [{ id:'tatl-01', order:1, label:'Bài hát kết thúc', templateId:'clean-dark', type:'music' },{ id:'tatl-02', order:2, label:'Cầu nguyện tất lễ', templateId:'vintage', type:'prayer' }] },
    { id: 'cuoi-gio',   label: 'Cuối giờ',         items: [{ id:'cg-01', order:1, label:'Nhạc nền cuối giờ', templateId:'clean-dark', type:'music' }] },
  ];
  const scenes = FALLBACK.map((s) => Scene.fromJSON(s));
  StateManager.setScenes(scenes);
  StateManager.setWsStatus(false);
}

/** Kết nối global events → MonitorView */
function _wireGlobalEvents(monitorView) {
  // WS status → TopBar
  StateManager.on(StateManager.EVENTS.WS_STATUS, ({ connected }) => {
    monitorView.updateWsStatus(connected);
  });

  // OBS status → TopBar
  StateManager.on(StateManager.EVENTS.OBS_STATUS, ({ connected }) => {
    monitorView.updateObsStatus(connected);
  });

  // Overlay state → Preview
  StateManager.on(StateManager.EVENTS.OVERLAY_CHANGED, ({ visible }) => {
    monitorView.setOverlayVisible(visible);
  });

  // Scene change → session label
  StateManager.on(StateManager.EVENTS.SCENE_CHANGED, ({ sceneId }) => {
    const scene = StateManager.getActiveScene();
    monitorView.updateSession(scene?.label ?? '');
  });

  // Offline mode indicator
  if (!StateManager.getState().wsConnected) {
    monitorView.updateWsStatus(false, 'offline');
  }
}

/** Phục hồi session từ localStorage */
function _restoreSession() {
  try {
    const saved = localStorage.getItem('obs_session');
    if (!saved) return;
    const { form, activeSceneId, activeItemId } = JSON.parse(saved);
    if (form)          StateManager.setForm(form);
    if (activeSceneId) StateManager.setActiveScene(activeSceneId);
    if (activeItemId)  setTimeout(() => StateManager.setActiveItem(activeItemId), 100);
  } catch (_) { /* ignore */ }
}

function _setupSidebarNavigation() {
  const btnRunner   = document.getElementById('sb-btn-runner');
  const btnAdvanced = document.getElementById('sb-btn-advanced');
  const colTimeline = document.getElementById('col-timeline');
  const colScenes   = document.getElementById('col-scenes');
  const colItems    = document.getElementById('col-items');

  if (!btnRunner || !btnAdvanced || !colTimeline || !colScenes || !colItems) return;

  btnRunner.addEventListener('click', () => {
    btnRunner.classList.add('active');
    btnAdvanced.classList.remove('active');
    colTimeline.classList.remove('hidden');
    colScenes.classList.add('hidden');
    colItems.classList.add('hidden');
  });

  btnAdvanced.addEventListener('click', () => {
    btnAdvanced.classList.add('active');
    btnRunner.classList.remove('active');
    colTimeline.classList.add('hidden');
    colScenes.classList.remove('hidden');
    colItems.classList.remove('hidden');
  });
}

// ─── Entry Point ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', main);

