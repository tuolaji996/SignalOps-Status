// SignalOps-Status core behaviour (loaded on every page as an ES module): theme switcher,
// top-bar clock and expandable table rows. Hand-maintained plain JS, no build step.

// ---- Theme ----------------------------------------------------------------------------------
// Cycles system -> light -> dark without a reload. The link still works without JS
// (?theme=... sets the cookie server-side and redirects back). The server writes
// <html data-theme="light|dark"> (omitted for "system") and data-theme-pref="system|light|dark".
// The cookie name defaults to "signalops_theme" (matches the server's Preferences::THEME_COOKIE) and can be
// overridden with <html data-theme-cookie="...">.
const THEME_ORDER = ["system", "light", "dark"];
const DEFAULT_THEME_COOKIE = "signalops_theme";

function themeCookieName() {
  return document.documentElement.dataset.themeCookie || DEFAULT_THEME_COOKIE;
}

export function setTheme(theme) {
  const root = document.documentElement;
  root.dataset.themePref = theme;
  if (theme === "system") {
    delete root.dataset.theme;
  } else {
    root.dataset.theme = theme;
  }
  const meta = document.querySelector('meta[name="color-scheme"]');
  if (meta) meta.content = theme === "system" ? "dark light" : theme;
  const secure = location.protocol === "https:" ? "; Secure" : "";
  document.cookie = `${themeCookieName()}=${theme}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
}

export function initTheme() {
  document.querySelectorAll("[data-theme-toggle]").forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      const pref = document.documentElement.dataset.themePref;
      const current = THEME_ORDER.includes(pref) ? pref : "system";
      const next = THEME_ORDER[(THEME_ORDER.indexOf(current) + 1) % THEME_ORDER.length];
      setTheme(next);
      // data-label-system / data-label-light / data-label-dark
      const label = link.dataset[`label${next[0].toUpperCase()}${next.slice(1)}`];
      if (label) link.textContent = label;
      if (link.href) {
        const url = new URL(link.href, location.href);
        url.searchParams.set("theme", THEME_ORDER[(THEME_ORDER.indexOf(next) + 1) % THEME_ORDER.length]);
        link.href = url.pathname + url.search;
      }
    });
  });
}

// ---- Clock ----------------------------------------------------------------------------------
// Ticks the top-bar clock once a second. Time zone: data-clock-tz on the [data-clock] element,
// else <html data-timezone>, else America/Toronto.
export function initClock() {
  const date = document.querySelector("[data-clock-date]");
  const time = document.querySelector("[data-clock-time]");
  if (!date || !time) return;
  const holder = document.querySelector("[data-clock]");
  const timeZone = (holder && holder.dataset.clockTz) || document.documentElement.dataset.timezone || "America/Toronto";
  const options = {
    year: "numeric", month: "2-digit", day: "2-digit",
    hour: "2-digit", minute: "2-digit", second: "2-digit",
    hour12: false, timeZoneName: "short",
  };
  let fmt;
  try {
    fmt = new Intl.DateTimeFormat("en-CA", { ...options, timeZone });
  } catch {
    fmt = new Intl.DateTimeFormat("en-CA", options);
  }
  const tick = () => {
    const p = Object.fromEntries(fmt.formatToParts(new Date()).map((part) => [part.type, part.value]));
    date.textContent = `${p.year}-${p.month}-${p.day}`;
    time.textContent = `${p.hour === "24" ? "00" : p.hour}:${p.minute}:${p.second} ${p.timeZoneName}`;
  };
  tick();
  window.setInterval(tick, 1000);
}

// ---- Tables ---------------------------------------------------------------------------------
// Expandable table rows (rows with a detail row). Click anywhere on the row or use the toggle
// button (keyboard accessible).
export function initTables(root) {
  root.querySelectorAll("tr.ui-row-expandable").forEach((row) => {
    if (row.dataset.bound) return;
    row.dataset.bound = "1";
    const button = row.querySelector(".ui-row-toggle");
    if (!button) return;
    const toggle = () => {
      const detail = document.getElementById(button.getAttribute("aria-controls") || "");
      if (!detail) return;
      const open = button.getAttribute("aria-expanded") !== "true";
      button.setAttribute("aria-expanded", String(open));
      detail.hidden = !open;
      if (row.parentElement) row.parentElement.classList.toggle("is-open", open);
    };
    button.addEventListener("click", (event) => {
      event.stopPropagation();
      toggle();
    });
    row.addEventListener("click", (event) => {
      const target = event.target;
      if (target instanceof Element && target.closest("a, button, input, select, textarea")) return;
      const selection = window.getSelection();
      if (selection && selection.toString()) return;
      toggle();
    });
  });
}

initTheme();
initClock();
initTables(document);
