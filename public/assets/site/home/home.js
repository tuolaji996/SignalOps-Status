// SignalOps-Status home area (overview / servers / latency / logs / events / alerts pages).
// Loaded as an ES module after core.js. Hand-maintained plain JS, no build step.

// ---- Live refresh ---------------------------------------------------------------------------
// In-place refresh of the monitor numbers. The server prints every refreshable value with a
// data-live key; the live endpoint (data-home-live, e.g. /overview/live) returns the same keys
// from the current snapshot as { created_at, cache_state, values: { key: { t, tone?, pct? } } }.
// Nothing here re-renders HTML: text, tone class and bar width only.
const TONES = ["ui-tone-ok", "ui-tone-warn", "ui-tone-info", "ui-tone-neutral", "ui-tone-none"];

function setTone(el, tone) {
  if (tone === undefined) return;
  if (el.hasAttribute("data-live-tone-only-warn")) {
    el.classList.toggle("ui-tone-warn", tone === "warn");
    return;
  }
  const next = `ui-tone-${tone}`;
  if (el.classList.contains(next)) return;
  el.classList.remove(...TONES);
  if (tone !== "none") el.classList.add(next);
}

export function apply(root, values) {
  let changed = 0;
  root.querySelectorAll("[data-live]").forEach((el) => {
    const value = values[el.dataset.live || ""];
    if (!value || el.textContent === value.t) return;
    el.textContent = value.t;
    el.classList.remove("is-updated");
    void el.offsetWidth; // restart the flash animation
    el.classList.add("is-updated");
    changed++;
  });
  root.querySelectorAll("[data-live-tone]").forEach((el) => {
    const key = el.dataset.liveTone || el.dataset.live || "";
    const value = values[key];
    setTone(el, value ? value.tone : undefined);
  });
  root.querySelectorAll("[data-live-title]").forEach((el) => {
    const value = values[el.dataset.liveTitle || ""];
    if (value) el.title = value.t;
  });
  root.querySelectorAll("[data-live-meter]").forEach((el) => {
    const value = values[el.dataset.liveMeter || ""];
    const meter = el.querySelector(".ui-meter");
    if (!value || !meter) return;
    setTone(meter, value.tone);
    const fill = meter.querySelector(".ui-meter-fill");
    const text = meter.querySelector(".ui-meter-value");
    if (fill && typeof value.pct === "number") fill.style.width = `${value.pct}%`;
    if (text && text.textContent !== value.t) {
      text.textContent = value.t;
      changed++;
    }
  });
  root.querySelectorAll("[data-live-bar]").forEach((el) => {
    const value = values[el.dataset.liveBar || ""];
    if (value && typeof value.pct === "number") el.style.width = `${value.pct}%`;
  });
  return changed;
}

export function initLive(page) {
  const url = page.dataset.homeLive;
  const every = Math.max(15, Number(page.dataset.refresh || 60)) * 1000;
  if (!url || !("fetch" in window)) return;
  let last = Date.now();
  let busy = false;

  const refresh = async () => {
    if (busy) return;
    busy = true;
    last = Date.now();
    try {
      const res = await fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" }, cache: "no-store" });
      if (!res.ok) return;
      const data = await res.json();
      if (data && data.values && typeof data.values === "object") apply(document, data.values);
    } catch {
      // Offline or a deploy in progress: keep the numbers on screen and try again next time.
    } finally {
      busy = false;
    }
  };

  window.setInterval(() => {
    if (!document.hidden) void refresh();
  }, every);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden && Date.now() - last >= every) void refresh();
  });
}

// ---- Latency mesh ---------------------------------------------------------------------------
// Matrix / Map switch, map zoom (Ctrl/Cmd + wheel, pinch, +/-, reset), drag to pan, and the node
// filter (map nodes, filter buttons, matrix and the latency links list).
const VIEW_W = 1000;
const VIEW_H = 620;
const LIMITS = { min: 0.8, max: 4 };

function setView(root, view) {
  root.dataset.view = view;
  root.querySelectorAll("[data-lat-pane]").forEach((pane) => {
    pane.hidden = pane.dataset.latPane !== view;
  });
  const panel = root.closest(".ui-panel");
  if (panel) {
    panel.querySelectorAll("[data-lat-view]").forEach((btn) => {
      const active = btn.dataset.latView === view;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-pressed", String(active));
    });
  }
  const url = new URL(location.href);
  if (view === "map") url.searchParams.set("view", "map");
  else url.searchParams.delete("view");
  history.replaceState(history.state, "", url.pathname + url.search + url.hash);
}

function initMap(root, onFocus) {
  const map = root.querySelector("[data-lat-map]");
  const svg = map ? map.querySelector("svg") : null;
  const stage = map ? map.querySelector("[data-map-stage]") : null;
  if (!map || !svg || !stage) return;
  const state = { scale: 1, x: 0, y: 0 };
  const pointers = new Map();
  let pinchDistance = 0;
  let moved = false;

  const apply = () => {
    stage.setAttribute("transform", `translate(${state.x.toFixed(2)} ${state.y.toFixed(2)}) scale(${state.scale.toFixed(3)})`);
  };
  const toView = (clientX, clientY) => {
    const rect = svg.getBoundingClientRect();
    return { x: ((clientX - rect.left) / rect.width) * VIEW_W, y: ((clientY - rect.top) / rect.height) * VIEW_H };
  };
  const zoomAt = (next, center) => {
    const scale = Math.max(LIMITS.min, Math.min(LIMITS.max, next));
    const ratio = scale / state.scale;
    state.x = center.x - (center.x - state.x) * ratio;
    state.y = center.y - (center.y - state.y) * ratio;
    state.scale = scale;
    apply();
  };
  const middle = { x: VIEW_W / 2, y: VIEW_H / 2 };
  const on = (selector, handler) => {
    const el = map.querySelector(selector);
    if (el) el.addEventListener("click", handler);
  };

  on("[data-map-zoom-in]", () => zoomAt(state.scale * 1.25, middle));
  on("[data-map-zoom-out]", () => zoomAt(state.scale / 1.25, middle));
  on("[data-map-reset]", () => {
    Object.assign(state, { scale: 1, x: 0, y: 0 });
    apply();
    onFocus("all");
  });
  // Plain wheel scrolls the page; Ctrl/Cmd + wheel (and trackpad pinch, which sends ctrlKey) zooms.
  map.addEventListener("wheel", (event) => {
    if (!event.ctrlKey && !event.metaKey) return;
    event.preventDefault();
    zoomAt(state.scale * (event.deltaY > 0 ? 0.9 : 1.1), toView(event.clientX, event.clientY));
  }, { passive: false });

  map.addEventListener("pointerdown", (event) => {
    if (event.target instanceof Element && event.target.closest(".lat-map-tools")) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    moved = false;
    if (pointers.size === 2) {
      const [a, b] = [...pointers.values()];
      pinchDistance = Math.hypot(a.x - b.x, a.y - b.y);
    }
  });
  map.addEventListener("pointermove", (event) => {
    const prev = pointers.get(event.pointerId);
    if (!prev) return;
    const next = { x: event.clientX, y: event.clientY };
    pointers.set(event.pointerId, next);
    if (pointers.size === 2) {
      const [a, b] = [...pointers.values()];
      const distance = Math.hypot(a.x - b.x, a.y - b.y);
      if (pinchDistance > 0) zoomAt(state.scale * (distance / pinchDistance), toView((a.x + b.x) / 2, (a.y + b.y) / 2));
      pinchDistance = distance;
      moved = true;
      return;
    }
    const dx = next.x - prev.x;
    const dy = next.y - prev.y;
    if (!moved && Math.hypot(dx, dy) < 3) return;
    if (!moved) {
      moved = true;
      map.setPointerCapture(event.pointerId);
      map.classList.add("is-dragging");
    }
    const rect = svg.getBoundingClientRect();
    state.x += (dx / rect.width) * VIEW_W;
    state.y += (dy / rect.height) * VIEW_H;
    apply();
  });
  const release = (event) => {
    pointers.delete(event.pointerId);
    if (pointers.size < 2) pinchDistance = 0;
    if (pointers.size === 0) map.classList.remove("is-dragging");
  };
  map.addEventListener("pointerup", release);
  map.addEventListener("pointercancel", release);

  map.querySelectorAll(".lat-node").forEach((node) => {
    const key = node.dataset.nodeKey || "all";
    node.addEventListener("click", () => {
      if (moved) return;
      onFocus(root.dataset.focus === key ? "all" : key);
    });
    node.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      event.preventDefault();
      onFocus(root.dataset.focus === key ? "all" : key);
    });
  });
  apply();
}

export function initLatency(root) {
  const panel = root.closest(".ui-panel") || root;
  panel.querySelectorAll("[data-lat-view]").forEach((btn) => {
    btn.addEventListener("click", (event) => {
      event.preventDefault();
      setView(root, btn.dataset.latView === "map" ? "map" : "matrix");
    });
  });

  const title = root.querySelector("[data-lat-focus-title]");
  const count = root.querySelector("[data-lat-focus-count]");
  const labels = {};
  root.querySelectorAll("[data-lat-filter]").forEach((btn) => {
    labels[btn.dataset.latFilter || ""] = (btn.textContent || "").trim();
  });

  const focus = (key) => {
    const all = key === "all";
    const mapEl = root.querySelector("[data-lat-map]");
    for (const el of [root, mapEl]) {
      if (!el) continue;
      if (all) delete el.dataset.focus;
      else el.dataset.focus = key;
    }
    const linked = new Set();
    root.querySelectorAll("[data-link-from][data-link-to]").forEach((el) => {
      const from = el.getAttribute("data-link-from") || "";
      const to = el.getAttribute("data-link-to") || "";
      const match = all || from === key || to === key;
      el.classList.toggle("is-focus", !all && match);
      if (match && !all) {
        linked.add(from);
        linked.add(to);
      }
      if (el.classList.contains("lat-row")) el.hidden = !match;
    });
    root.querySelectorAll(".lat-node").forEach((node) => {
      const nodeKey = node.dataset.nodeKey || "";
      node.classList.toggle("is-active", nodeKey === key);
      node.classList.toggle("is-linked", linked.has(nodeKey));
      node.setAttribute("aria-pressed", String(nodeKey === key));
    });
    root.querySelectorAll("[data-lat-filter]").forEach((btn) => {
      const active = btn.dataset.latFilter === key;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-pressed", String(active));
    });
    if (title) {
      title.textContent = all
        ? (title.dataset.titleAll || "")
        : (title.dataset.titleNode || "{node}").replace("{node}", labels[key] || key);
    }
    if (count) {
      const visible = root.querySelectorAll(".lat-row:not([hidden])").length;
      count.textContent = (count.dataset.countLabel || "{n}").replace("{n}", String(visible));
    }
  };

  root.querySelectorAll("[data-lat-filter]").forEach((btn) => {
    btn.addEventListener("click", () => focus(btn.dataset.latFilter || "all"));
  });
  // Matrix: clicking a row header focuses that server.
  root.querySelectorAll("[data-lat-row] th").forEach((th) => {
    const key = (th.parentElement && th.parentElement.dataset.latRow) || "all";
    th.style.cursor = "pointer";
    th.addEventListener("click", () => focus(root.dataset.focus === key ? "all" : key));
  });
  initMap(root, focus);
}

// ---- Entry ----------------------------------------------------------------------------------
document.querySelectorAll("[data-latency]").forEach(initLatency);
const livePage = document.querySelector("[data-home-live]");
if (livePage) initLive(livePage);
