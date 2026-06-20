/**
 * OBSChurch — Floating Program Monitor PiP Widget
 * Tự inject vào mọi trang, hiển thị overlay preview góc màn hình.
 * Kéo để di chuyển, click để thu nhỏ/mở rộng.
 *
 * Usage: <script src="/OBSChurch/monitor-pip.js"></script>
 */
(function () {
  'use strict';

  const WS_URL      = 'ws://localhost:3001';
  const OVERLAY_URL = 'http://localhost:8080/OBSChurch/overlay/index.html';
  const STORE_KEY   = 'obsChurchPip';

  // ── Load saved state ────────────────────────────────────
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(STORE_KEY) || '{}'); } catch {}

  const state = {
    collapsed: saved.collapsed ?? false,
    x:  saved.x  ?? null,
    y:  saved.y  ?? null,
    ws: null,
    isLive: false,
    reconnectTimer: null,
  };

  // ── Inject CSS ──────────────────────────────────────────
  const css = `
    #obs-pip {
      position: fixed;
      z-index: 99999;
      bottom: 16px;
      right: 16px;
      width: 280px;
      background: #0a0a18;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 10px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
      font-family: 'Inter', system-ui, sans-serif;
      user-select: none;
      transition: width 0.2s, box-shadow 0.2s;
      overflow: hidden;
    }
    #obs-pip:hover {
      box-shadow: 0 12px 40px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.08);
    }
    #obs-pip.live-on {
      border-color: rgba(239,68,68,0.5);
      box-shadow: 0 8px 32px rgba(0,0,0,0.6), 0 0 20px rgba(239,68,68,0.15), 0 0 0 1px rgba(239,68,68,0.3);
    }
    #obs-pip.collapsed {
      width: 180px;
    }

    /* ── Drag handle / header ── */
    #obs-pip-hdr {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 6px 10px;
      background: rgba(255,255,255,0.04);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      cursor: grab;
      -webkit-user-select: none;
    }
    #obs-pip-hdr:active { cursor: grabbing; }
    #obs-pip-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #2a2a48;
      flex-shrink: 0;
      transition: all 0.3s;
    }
    #obs-pip-dot.on {
      background: #ef4444;
      box-shadow: 0 0 8px rgba(239,68,68,0.7);
      animation: pip-pulse 1.2s ease-in-out infinite;
    }
    @keyframes pip-pulse {
      0%,100% { box-shadow: 0 0 6px rgba(239,68,68,0.5); }
      50%      { box-shadow: 0 0 14px rgba(239,68,68,0.9), 0 0 22px rgba(239,68,68,0.3); }
    }
    #obs-pip-label {
      font-size: 8px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #505070;
      font-family: 'JetBrains Mono', monospace;
      flex: 1;
      transition: color 0.2s;
    }
    #obs-pip.live-on #obs-pip-label { color: #ef4444; }

    /* WS status */
    #obs-pip-ws {
      width: 5px; height: 5px;
      border-radius: 50%;
      background: #2a2a48;
      flex-shrink: 0;
      transition: all 0.3s;
    }
    #obs-pip-ws.ok { background: #4edea3; box-shadow: 0 0 5px rgba(78,222,163,0.5); }

    /* Toggle collapse button */
    #obs-pip-toggle {
      width: 18px; height: 18px;
      border-radius: 4px;
      border: 1px solid rgba(255,255,255,0.08);
      background: transparent;
      color: #505070;
      font-size: 11px;
      line-height: 1;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: all 0.15s;
      flex-shrink: 0;
      padding: 0;
    }
    #obs-pip-toggle:hover { background: rgba(255,255,255,0.08); color: #9090b0; }

    /* Close/hide button */
    #obs-pip-close {
      width: 18px; height: 18px;
      border-radius: 4px;
      border: none;
      background: transparent;
      color: #505070;
      font-size: 13px;
      line-height: 1;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: all 0.15s;
      flex-shrink: 0;
      padding: 0;
    }
    #obs-pip-close:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

    /* ── Screen / iframe ── */
    #obs-pip-screen {
      position: relative;
      background: #000;
      border-bottom: 2px solid rgba(255,255,255,0.04);
      transition: border-color 0.3s;
      overflow: hidden;
    }
    #obs-pip.live-on #obs-pip-screen { border-bottom-color: #ef4444; }
    #obs-pip.collapsed #obs-pip-screen { display: none; }
    #obs-pip-iframe {
      width: 100%;
      display: block;
      border: none;
      aspect-ratio: 16/9;
      background: #000;
      pointer-events: none;
    }
    #obs-pip-screen-label {
      position: absolute;
      top: 5px; left: 6px;
      background: rgba(0,0,0,0.75);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 3px;
      padding: 1px 5px;
      font-size: 6px;
      color: #505070;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      pointer-events: none;
    }
    #obs-pip-reload {
      position: absolute;
      bottom: 5px; right: 5px;
      background: rgba(0,0,0,0.65);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 3px;
      padding: 1px 5px;
      font-size: 9px;
      color: #505070;
      cursor: pointer;
      transition: all 0.15s;
    }
    #obs-pip-reload:hover { color: #d0d0f0; background: rgba(255,255,255,0.1); }

    /* ── Footer: next line ── */
    #obs-pip-footer {
      padding: 6px 10px 8px;
      transition: all 0.2s;
    }
    #obs-pip.collapsed #obs-pip-footer { display: none; }
    #obs-pip-next-lbl {
      font-size: 7px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #303050;
      margin-bottom: 3px;
      font-family: 'JetBrains Mono', monospace;
    }
    #obs-pip-next-txt {
      font-size: 11px;
      color: #505070;
      line-height: 1.45;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      transition: color 0.2s;
    }
    #obs-pip-next-txt.has-next { color: #9090b0; }

    /* Show button (when PiP is hidden) */
    #obs-pip-showbtn {
      position: fixed;
      bottom: 16px; right: 16px;
      z-index: 99999;
      background: #0a0a18;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 8px;
      padding: 7px 12px;
      font-size: 10px;
      font-family: 'Inter', system-ui, sans-serif;
      color: #505070;
      cursor: pointer;
      display: none;
      align-items: center;
      gap: 6px;
      transition: all 0.15s;
      box-shadow: 0 4px 16px rgba(0,0,0,0.4);
    }
    #obs-pip-showbtn:hover { color: #9090b0; background: #10101e; }
    #obs-pip-showbtn .dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #2a2a48;
      flex-shrink: 0;
    }
    #obs-pip-showbtn .dot.on {
      background: #ef4444;
      box-shadow: 0 0 6px rgba(239,68,68,0.6);
      animation: pip-pulse 1.2s ease-in-out infinite;
    }
  `;

  // Inject style
  const styleEl = document.createElement('style');
  styleEl.textContent = css;
  document.head.appendChild(styleEl);

  // ── Build DOM ──────────────────────────────────────────
  const pip = document.createElement('div');
  pip.id = 'obs-pip';
  if (state.collapsed) pip.classList.add('collapsed');

  pip.innerHTML = `
    <div id="obs-pip-hdr">
      <div id="obs-pip-dot"></div>
      <span id="obs-pip-label">OFFLINE</span>
      <div id="obs-pip-ws" title="WebSocket"></div>
      <button id="obs-pip-toggle" title="Thu/Mở">▾</button>
      <button id="obs-pip-close" title="Ẩn">✕</button>
    </div>
    <div id="obs-pip-screen">
      <iframe id="obs-pip-iframe"
        src="${OVERLAY_URL}"
        scrolling="no"
        sandbox="allow-scripts allow-same-origin"
        title="Live Overlay Preview"
      ></iframe>
      <div id="obs-pip-screen-label">OVERLAY PREVIEW</div>
      <button id="obs-pip-reload" title="Reload preview">⟳</button>
    </div>
    <div id="obs-pip-footer">
      <div id="obs-pip-next-lbl">▷ Tiếp Theo</div>
      <div id="obs-pip-next-txt">—</div>
    </div>
  `;

  const showBtn = document.createElement('button');
  showBtn.id = 'obs-pip-showbtn';
  showBtn.innerHTML = `<span class="dot" id="obs-pip-showdot"></span><span>Program Monitor</span>`;

  document.body.appendChild(pip);
  document.body.appendChild(showBtn);

  // ── Restore position ────────────────────────────────────
  if (state.x !== null && state.y !== null) {
    pip.style.left   = state.x + 'px';
    pip.style.bottom = 'auto';
    pip.style.right  = 'auto';
    pip.style.top    = state.y + 'px';
  }

  // ── Drag ───────────────────────────────────────────────
  const hdr = document.getElementById('obs-pip-hdr');
  let dragging = false, dragOX = 0, dragOY = 0;

  hdr.addEventListener('mousedown', e => {
    if (e.target.tagName === 'BUTTON') return;
    dragging = true;
    const rect = pip.getBoundingClientRect();
    dragOX = e.clientX - rect.left;
    dragOY = e.clientY - rect.top;
    pip.style.transition = 'none';
  });

  document.addEventListener('mousemove', e => {
    if (!dragging) return;
    const x = e.clientX - dragOX;
    const y = e.clientY - dragOY;
    pip.style.left   = Math.max(0, Math.min(window.innerWidth  - pip.offsetWidth,  x)) + 'px';
    pip.style.top    = Math.max(0, Math.min(window.innerHeight - pip.offsetHeight, y)) + 'px';
    pip.style.right  = 'auto';
    pip.style.bottom = 'auto';
  });

  document.addEventListener('mouseup', () => {
    if (!dragging) return;
    dragging = false;
    pip.style.transition = '';
    const rect = pip.getBoundingClientRect();
    state.x = rect.left;
    state.y = rect.top;
    saveState();
  });

  // ── Toggle collapse ─────────────────────────────────────
  document.getElementById('obs-pip-toggle').addEventListener('click', () => {
    state.collapsed = !state.collapsed;
    pip.classList.toggle('collapsed', state.collapsed);
    document.getElementById('obs-pip-toggle').textContent = state.collapsed ? '▸' : '▾';
    saveState();
  });
  if (state.collapsed) {
    document.getElementById('obs-pip-toggle').textContent = '▸';
  }

  // ── Close / Show ────────────────────────────────────────
  document.getElementById('obs-pip-close').addEventListener('click', () => {
    pip.style.display = 'none';
    showBtn.style.display = 'flex';
    state.hidden = true;
    saveState();
  });

  showBtn.addEventListener('click', () => {
    pip.style.display = '';
    showBtn.style.display = 'none';
    state.hidden = false;
    saveState();
  });

  if (saved.hidden) {
    pip.style.display = 'none';
    showBtn.style.display = 'flex';
  }

  // ── Reload iframe ───────────────────────────────────────
  document.getElementById('obs-pip-reload').addEventListener('click', () => {
    const iframe = document.getElementById('obs-pip-iframe');
    iframe.src = iframe.src;
  });

  // ── WebSocket ───────────────────────────────────────────
  function connectWS() {
    try {
      const ws = new WebSocket(WS_URL);
      state.ws = ws;

      ws.addEventListener('open', () => {
        setWSDot(true);
        ws.send(JSON.stringify({ type: 'REQUEST_STATE' }));
      });

      ws.addEventListener('message', e => {
        try {
          const msg = JSON.parse(e.data);
          if (msg.type === 'STATE_SYNC') {
            setLive(msg.payload?.overlayVisible);
          }
          if (msg.type === 'OVERLAY_SHOW') {
            setLive(true);
            const nx = msg.payload?.secondary || '';
            setNext(nx);
          }
          if (msg.type === 'OVERLAY_HIDE' || msg.type === 'CLEAR_ALL' || msg.type === 'SAFETY_CUT') {
            setLive(false);
            setNext('');
          }
        } catch {}
      });

      ws.addEventListener('close', () => {
        setWSDot(false);
        state.ws = null;
        state.reconnectTimer = setTimeout(connectWS, 3000);
      });

      ws.addEventListener('error', () => {
        setWSDot(false);
      });
    } catch {}
  }

  function setWSDot(ok) {
    const d = document.getElementById('obs-pip-ws');
    if (d) d.className = ok ? 'ok' : '';
  }

  function setLive(live) {
    state.isLive = live;
    const dot   = document.getElementById('obs-pip-dot');
    const label = document.getElementById('obs-pip-label');
    const showD = document.getElementById('obs-pip-showdot');

    if (dot)   dot.className   = live ? 'on' : '';
    if (label) label.textContent = live ? '🔴 ON AIR' : 'OFFLINE';
    if (showD) showD.className  = live ? 'dot on' : 'dot';
    pip.classList.toggle('live-on', !!live);
  }

  function setNext(text) {
    const el = document.getElementById('obs-pip-next-txt');
    if (!el) return;
    if (text) {
      el.textContent = text.length > 80 ? text.slice(0, 80) + '…' : text;
      el.className = 'has-next';
    } else {
      el.textContent = '—';
      el.className = '';
    }
  }

  // ── Save state ──────────────────────────────────────────
  function saveState() {
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify({
        collapsed: state.collapsed,
        hidden:    state.hidden || false,
        x: state.x,
        y: state.y,
      }));
    } catch {}
  }

  // ── Expose API for pages that want to update next-text ──
  window.ObsPip = {
    setLive,
    setNext,
    /** Call when a line is pushed: pushLine(text, nextText) */
    onPush(text, nextText) {
      setLive(true);
      setNext(nextText || '');
    },
    onClear() {
      setLive(false);
      setNext('');
    },
  };

  // ── Start WS ────────────────────────────────────────────
  // Delay to not conflict with page's own WS
  setTimeout(connectWS, 800);

})();
