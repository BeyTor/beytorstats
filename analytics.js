/**
 * SiteStats Analytics v2.0
 * ─────────────────────────
 * Kurulum: </body> kapanış etiketinden önce şu satırı ekleyin:
 * <script src="/beytorstats/analytics.js"></script>
 *
 * stats.php yolu aynı klasörde olmalıdır.
 * Farklıysa aşağıdaki STATS_URL'yi düzenleyin.
 */

(function () {
  "use strict";

  /* ─── YAPILANDIRMA ──────────────────────────────────────── */
  const STATS_URL     = "/beytorstats/stats.php";
  const HEARTBEAT_SEC = 30; // Anlık kullanıcı güncelleme sıklığı (saniye)
  const WIDGET_ID     = "ss-footer-widget";

  /* ─── SAYFA GÖRÜNTÜLEME + ZİYARET KAYDI ────────────────── */
  async function trackVisit() {
    try {
      const qs = new URLSearchParams({
        action: "visit",
        page: window.location.pathname || "/",
        title: document.title || "",
        referrer: document.referrer || "direct"
      });
      const res = await fetch(STATS_URL + "?" + qs.toString(), {
        credentials: "include",
        cache: "no-store",
      });
      const data = await res.json();
      if (data.success) updateWidget(data);
      return data;
    } catch (_) {
      return null;
    }
  }

  function trackPageView() {
    const fd = new FormData();
    fd.append("action",   "pageview");
    fd.append("page",     window.location.pathname);
    fd.append("title",    document.title);
    fd.append("referrer", document.referrer || "direct");

    return fetch(STATS_URL, { method: "POST", body: fd, credentials: "include", keepalive: true })
      .catch(() => {});
  }

  /* ─── DIŞ LİNK TAKİBİ ──────────────────────────────────── */
  function setupLinkTracking() {
    document.addEventListener("click", function (e) {
      const link = e.target.closest("a[href]");
      if (!link) return;
      const href = link.href || "";
      if (href.startsWith("http") && !href.includes(window.location.hostname)) {
        const fd = new FormData();
        fd.append("action", "linkclick");
        fd.append("url",    href);
        fd.append("text",   link.textContent.trim().slice(0, 120));
        fd.append("source", window.location.pathname);
        fetch(STATS_URL, { method: "POST", body: fd, credentials: "include", keepalive: true })
          .catch(() => {});
      }
    });
  }

  /* ─── HEARTBEAT (anlık kullanıcı güncellemesi) ───────────── */
  function startHeartbeat() {
    setInterval(async () => {
      try {
        const res = await fetch(STATS_URL + "?action=visit", {
          credentials: "include",
          cache: "no-store",
        });
        const data = await res.json();
        if (data.success) updateWidget(data);
      } catch (_) {}
    }, HEARTBEAT_SEC * 1000);
  }

  /* ─── WIDGET STİLLERİ ───────────────────────────────────── */
  function injectStyles() {
    if (document.getElementById("ss-styles")) return;
    const s = document.createElement("style");
    s.id = "ss-styles";
    s.textContent = `
      #${WIDGET_ID} {
        --ss-bg:      #09090f;
        --ss-border:  rgba(255,255,255,0.07);
        --ss-g1:      #6ee7b7;
        --ss-g2:      #818cf8;
        --ss-g3:      #fb923c;
        --ss-live:    #34d399;
        --ss-text:    #cbd5e1;
        --ss-muted:   #475569;
        font-family: 'DM Mono', 'Fira Mono', ui-monospace, monospace;
        background: var(--ss-bg);
        border-top: 1px solid var(--ss-border);
        padding: 14px 28px;
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0;
        position: relative;
        overflow: hidden;
        z-index: 9999;
        width: 100%;
        box-sizing: border-box;
      }
      #${WIDGET_ID}::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
          radial-gradient(ellipse 50% 120% at 5% 50%, rgba(110,231,183,.05) 0%, transparent 70%),
          radial-gradient(ellipse 40% 120% at 95% 50%, rgba(129,140,248,.04) 0%, transparent 70%);
        pointer-events: none;
      }
      /* Title */
      .ss-title-wrap {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 7px;
        flex-shrink: 0;
        margin-right: 16px;
      }
      .ss-title-icon { font-size: 18px; line-height: 1; }
      .ss-title-text {
        font-size: 12px;
        font-weight: 500;
        letter-spacing: .07em;
        color: var(--ss-text);
        white-space: nowrap;
        opacity: .8;
      }
      /* Divider */
      .ss-divider {
        width: 1px;
        height: 28px;
        background: var(--ss-border);
        flex-shrink: 0;
        margin: 0 16px;
      }
      /* Stats */
      .ss-stats-group {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-shrink: 0;
      }
      .ss-stat {
        display: flex;
        flex-direction: column;
        gap: 2px;
        align-items: flex-start;
      }
      .ss-lbl {
        font-size: 9px;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--ss-muted);
        white-space: nowrap;
      }
      .ss-val {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: -.03em;
        line-height: 1;
        transition: all .35s cubic-bezier(.4,0,.2,1);
      }
      .ss-stat.day   .ss-val { color: var(--ss-g1); }
      .ss-stat.month .ss-val { color: var(--ss-g2); }
      .ss-stat.year  .ss-val { color: var(--ss-g3); }
      /* Live */
      .ss-live-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
      }
      .ss-dot {
        position: relative;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--ss-live);
        flex-shrink: 0;
      }
      .ss-dot::after {
        content: '';
        position: absolute;
        inset: -3px;
        border-radius: 50%;
        border: 1.5px solid var(--ss-live);
        animation: ss-ring 1.8s ease-out infinite;
        opacity: 0;
      }
      @keyframes ss-ring {
        0%   { transform: scale(1);   opacity: .7; }
        100% { transform: scale(2.4); opacity: 0;  }
      }
      .ss-live-num {
        font-size: 16px;
        font-weight: 600;
        color: var(--ss-live);
        letter-spacing: -.01em;
      }
      .ss-live-lbl {
        font-size: 9px;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--ss-muted);
      }
      .ss-brand {
        margin-left: auto;
        font-size: 9px;
        font-weight: 500;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--ss-text);
        opacity: .6;
        user-select: none;
        text-decoration: none;
        flex-shrink: 0;
        transition: opacity .2s;
      }
      .ss-brand:hover { opacity: 1; }
      .ss-loading {
        opacity: .25;
        animation: ss-pulse 1.1s ease-in-out infinite;
      }
      @keyframes ss-pulse {
        0%,100% { opacity: .25; }
        50%      { opacity: .55; }
      }
      .ss-bump { animation: ss-bump .4s cubic-bezier(.4,0,.2,1); }
      @keyframes ss-bump {
        0%   { transform: translateY(-3px); opacity: .6; }
        60%  { transform: translateY(1px); }
        100% { transform: translateY(0);   opacity: 1;   }
      }
      /* Mobil */
      @media (max-width: 600px) {
        #${WIDGET_ID} { padding: 10px 14px; }
        .ss-title-wrap { margin-right: 10px; }
        .ss-title-icon { font-size: 12px; }
        .ss-title-text { font-size: 8px; }
        .ss-divider { margin: 0 10px; height: 24px; }
        .ss-stats-group { gap: 10px; }
        .ss-val { font-size: 14px; }
        .ss-lbl { font-size: 6.5px; }
        .ss-live-num { font-size: 11px; }
        .ss-live-lbl { font-size: 6.5px; }
        .ss-brand { display: none; }
      }
      @media (max-width: 380px) {
        .ss-title-text { display: none; }
        .ss-divider:first-of-type { display: none; }
        .ss-stats-group { gap: 8px; }
        .ss-val { font-size: 13px; }
      }
    `;
    document.head.appendChild(s);
  }

  /* ─── WIDGET HTML ───────────────────────────────────────── */
  function injectWidget() {
    if (document.getElementById(WIDGET_ID)) return;
    const w = document.createElement("div");
    w.id = WIDGET_ID;
    w.setAttribute("role", "contentinfo");
    w.setAttribute("aria-label", "Ziyaretçi istatistikleri");
    w.innerHTML = `
      <div class="ss-title-wrap">
        <div class="ss-title-icon">👥</div>
        <div class="ss-title-text">Ziyaretçilerimiz</div>
      </div>
      <div class="ss-divider"></div>
      <div class="ss-stats-group">
        <div class="ss-stat day">
          <div class="ss-lbl">Bugün</div>
          <div class="ss-val ss-loading" id="ss-day">—</div>
        </div>
        <div class="ss-stat month">
          <div class="ss-lbl">Bu Ay</div>
          <div class="ss-val ss-loading" id="ss-month">—</div>
        </div>
        <div class="ss-stat year">
          <div class="ss-lbl">Bu Yıl</div>
          <div class="ss-val ss-loading" id="ss-year">—</div>
        </div>
      </div>
      <div class="ss-divider"></div>
      <div class="ss-live-wrap">
        <span class="ss-dot"></span>
        <span class="ss-live-num ss-loading" id="ss-live">—</span>
        <span class="ss-live-lbl">Anlık</span>
      </div>
      <a href="https://www.beytor.com" target="_blank" class="ss-brand">BeyTor Stats</a>
    `;
    document.body.appendChild(w);
  }

  /* ─── WIDGET GÜNCELLEME ─────────────────────────────────── */
  function fmt(n) {
    return Number(n || 0).toLocaleString("tr-TR");
  }

  function setVal(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    const prev = el.dataset.prev;
    el.classList.remove("ss-loading");
    el.textContent = fmt(value);
    if (prev !== undefined && prev !== String(value)) {
      el.classList.remove("ss-bump");
      void el.offsetWidth; // reflow
      el.classList.add("ss-bump");
    }
    el.dataset.prev = String(value);
  }

  function updateWidget(data) {
    setVal("ss-day",   data.daily_visits);
    setVal("ss-month", data.monthly_visits);
    setVal("ss-year",  data.yearly_visits);
    setVal("ss-live",  data.live_visitors);
  }

  /* ─── BAŞLAT ─────────────────────────────────────────────── */
  function init() {
    injectStyles();
    injectWidget();
    trackVisit().finally(() => {
      trackPageView();
    });
    setupLinkTracking();
    startHeartbeat();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
