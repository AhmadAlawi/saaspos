/**
 * Dashboard Alpine component.
 *
 * All chart data is injected from the server via dashboardPage(config).
 * The component renders inline SVG charts (area, sparkline, dual-line,
 * donut, heatmap) matching the design-system/admin/Dashboard.html mockup.
 */
export function dashboardPage() {
    const cfg = window.DASHBOARD_DATA ?? {};
    return {
        perfRange: '14d',
        rangeLoading: false,
        chartRangeUrl: cfg.chartRangeUrl ?? '',

        // ── Hero series (driven by the header date-range filter) ──────
        heroSeries:   cfg.heroSeries   ?? Array(24).fill(0),
        heroLabels:   cfg.heroLabels   ?? [],
        heroIsHourly: cfg.heroIsHourly ?? true,

        // ── Server data (sparklines — always 14-day, never changes) ──
        revByDay:     cfg.revByDay    ?? Array(14).fill(0),
        txnByDay:     cfg.txnByDay    ?? Array(14).fill(0),
        topProducts:  cfg.topProducts  ?? [],
        lowStockItems: cfg.lowStockItems ?? [],
        heatmapData:  cfg.heatmapData  ?? [],
        heatmapTxns:  cfg.heatmapTxns  ?? [],
        heatmapItems: cfg.heatmapItems ?? [],
        activity:     cfg.activity     ?? [],
        shiftStaff:   cfg.shiftStaff   ?? [],

        // ── Chart tooltip ─────────────────────────────────────────────
        // `lines` drives the simple chart tooltip; `card` (when present) drives
        // the richer heatmap tooltip (header + headline + breakdown + footer).
        tip: { show: false, x: 0, y: 0, lines: [], card: null },

        onChartMove(e) {
            const el = e.target.closest('[data-tip]');
            if (!el) { this.tip.show = false; return; }
            let d;
            try { d = JSON.parse(el.dataset.tip); } catch { this.tip.show = false; return; }
            this.tip.x     = e.clientX;
            this.tip.y     = e.clientY;
            this.tip.lines = d.lines ?? [];
            this.tip.card  = d.card ?? null;
            this.tip.show  = true;
        },
        onChartLeave() { this.tip.show = false; },

        // ── Performance section data (responds to perfRange filter) ─────
        perfRevByDay:   cfg.revByDay        ?? Array(14).fill(0),
        perfTxnByDay:   cfg.txnByDay        ?? Array(14).fill(0),
        perfDayLabels:  cfg.dayLabels       ?? Array(14).fill(''),
        perfCategories: cfg.salesByCategory ?? [],

        // ── Catalog section data (responds to catalogRange filter) ───────
        catalogRange:    'today',
        catalogLoading:  false,
        catalogUrl:      cfg.catalogRangeUrl ?? '',
        catalogProducts: cfg.topProducts     ?? [],

        init() {
            this.$watch('perfRange',    range => this.fetchRange(range));
            this.$watch('catalogRange', range => this.fetchCatalog(range));
        },

        async fetchRange(range) {
            if (!this.chartRangeUrl) return;
            this.rangeLoading = true;
            try {
                const { data } = await posGet(this.chartRangeUrl, { range });
                this.perfRevByDay   = data.revByDay;
                this.perfTxnByDay   = data.txnByDay;
                this.perfDayLabels  = data.dayLabels;
                this.perfCategories = data.salesByCategory;
            } finally {
                this.rangeLoading = false;
            }
        },

        async fetchCatalog(range) {
            if (!this.catalogUrl) return;
            this.catalogLoading = true;
            try {
                const { data } = await posGet(this.catalogUrl, { range });
                this.catalogProducts = data.topProducts;
            } finally {
                this.catalogLoading = false;
            }
        },

        // Returns a short human label for a range key
        periodLabel(range) {
            return { today: 'Today', '7d': '7d', '14d': '14d', '30d': '30d' }[range] ?? range;
        },

        // ── Chart helpers ─────────────────────────────────────────────
        smoothPath(points) {
            if (!points || points.length < 2) return '';
            let d = `M ${points[0][0]} ${points[0][1]}`;
            for (let i = 1; i < points.length; i++) {
                const p0 = points[i - 1], p1 = points[i];
                const cp1x = p0[0] + (p1[0] - p0[0]) * 0.4;
                const cp2x = p0[0] + (p1[0] - p0[0]) * 0.6;
                d += ` C ${cp1x.toFixed(1)} ${p0[1].toFixed(1)}, ${cp2x.toFixed(1)} ${p1[1].toFixed(1)}, ${p1[0].toFixed(1)} ${p1[1].toFixed(1)}`;
            }
            return d;
        },

        // Hero area chart — hourly profile (single-day ranges) or a daily
        // revenue series (multi-day ranges); both driven by the header filter.
        heroAreaChart() {
            const data = this.heroSeries;
            const labels = this.heroLabels;
            const w = 1000, h = 120, padT = 12, padB = 0;
            const max = Math.max(...data, 1);
            const stepX = w / Math.max(data.length - 1, 1);
            const pts = data.map((v, i) => [i * stepX, padT + (1 - v / max) * (h - padT - padB)]);
            const linePath = this.smoothPath(pts);
            const areaPath = linePath + ` L ${w} ${h} L 0 ${h} Z`;
            const last = pts[pts.length - 1];
            const uid = 'hg';
            const fmt = v => window.posFormatMoney ? window.posFormatMoney(v) : '$' + v.toFixed(2);
            let hitBars = '';
            data.forEach((v, i) => {
                const cx = i * stepX;
                const x  = i === 0 ? 0 : cx - stepX / 2;
                const bw = i === 0 || i === data.length - 1 ? stepX / 2 : stepX;
                const label = labels[i] ?? (this.heroIsHourly ? String(i).padStart(2,'0') + ':00' : '');
                const tip = JSON.stringify({ lines: [label, fmt(v)] }).replace(/"/g, '&quot;');
                hitBars += `<rect x="${x.toFixed(1)}" y="0" width="${bw.toFixed(1)}" height="${h}" fill="transparent" data-tip="${tip}" style="cursor:crosshair"/>`;
            });
            return `<svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" style="display:block;width:100%;height:100%">
              <defs><linearGradient id="${uid}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.28"/>
                <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"/>
              </linearGradient></defs>
              <path d="${areaPath}" fill="url(#${uid})"/>
              <path d="${linePath}" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
              <circle cx="${last[0]}" cy="${last[1]}" r="10" fill="var(--accent)" fill-opacity="0.16" vector-effect="non-scaling-stroke"/>
              <circle cx="${last[0]}" cy="${last[1]}" r="4" fill="var(--accent)" vector-effect="non-scaling-stroke"/>
              ${hitBars}
            </svg>`;
        },

        // Mini sparkline for KPI cards
        miniSparkSmooth(data, color) {
            const w = 70, h = 28, padT = 3, padB = 3;
            const max = Math.max(...data, 1), min = Math.min(...data, 0), range = max - min || 1;
            const stepX = w / (data.length - 1);
            const pts = data.map((v, i) => [i * stepX, padT + (1 - (v - min) / range) * (h - padT - padB)]);
            const linePath = this.smoothPath(pts);
            const areaPath = linePath + ` L ${w} ${h} L 0 ${h} Z`;
            const uid = 'sp' + color.replace(/[^a-z0-9]/gi, '').slice(-6);
            return `<svg viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" preserveAspectRatio="none">
              <defs><linearGradient id="${uid}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="${color}" stop-opacity="0.22"/>
                <stop offset="100%" stop-color="${color}" stop-opacity="0"/>
              </linearGradient></defs>
              <path d="${areaPath}" fill="url(#${uid})"/>
              <path d="${linePath}" fill="none" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/>
              <circle cx="${pts[pts.length-1][0]}" cy="${pts[pts.length-1][1]}" r="2" fill="${color}"/>
            </svg>`;
        },

        // Performance net-sales chart — tracks perfRange (current vs shadow)
        netSalesChart() {
            const cur  = this.perfRevByDay;
            const prev = cur.map((v, i) => v * 0.88 + Math.sin(i * 1.7) * (v * 0.08));
            const labels = this.perfDayLabels;
            const w = 900, h = 220, padL = 56, padB = 28, padT = 10, padR = 12;
            const all = [...cur, ...prev];
            const max = Math.max(...all, 1), minV = 0;
            const range = max - minV;
            const stepX = (w - padL - padR) / (cur.length - 1);
            const toPts = arr => arr.map((v, i) => [padL + i * stepX, padT + (1 - (v - minV) / range) * (h - padT - padB)]);
            const curPts  = toPts(cur);
            const prevPts = toPts(prev);
            const curLine  = this.smoothPath(curPts);
            const prevLine = this.smoothPath(prevPts);
            const curArea  = curLine + ` L ${w - padR} ${h - padB} L ${padL} ${h - padB} Z`;
            const fmt = v => window.posFormatMoney ? window.posFormatMoney(v) : '$' + v.toFixed(2);
            let yLabels = '', xLabels = '', hitBars = '';
            for (let i = 0; i <= 4; i++) {
                const v = minV + range * (1 - i / 4);
                const y = padT + (i * (h - padT - padB)) / 4;
                const label = v >= 1000 ? '$' + (v / 1000).toFixed(1) + 'k' : '$' + v.toFixed(0);
                yLabels += `<text x="${padL - 8}" y="${y + 4}" text-anchor="end">${label}</text>`;
                yLabels += `<line x1="${padL}" x2="${w - padR}" y1="${y}" y2="${y}" stroke="var(--border-subtle)" stroke-dasharray="2 3"/>`;
            }
            labels.forEach((lbl, i) => {
                if (i % 2 === 0 || i === labels.length - 1) {
                    xLabels += `<text x="${padL + i * stepX}" y="${h - 6}" text-anchor="middle">${lbl}</text>`;
                }
            });
            curPts.forEach((pt, i) => {
                const x  = i === 0 ? padL : pt[0] - stepX / 2;
                const bw = i === 0 || i === curPts.length - 1 ? stepX / 2 : stepX;
                const tip = JSON.stringify({ lines: [labels[i], fmt(cur[i])] }).replace(/"/g, '&quot;');
                hitBars += `<rect x="${x.toFixed(1)}" y="${padT}" width="${bw.toFixed(1)}" height="${h - padT - padB}" fill="transparent" data-tip="${tip}" style="cursor:crosshair"/>`;
            });
            const lastCur = curPts[curPts.length - 1];
            return `<svg viewBox="0 0 ${w} ${h}" width="100%" class="chart-axis" preserveAspectRatio="xMidYMid meet">
              <defs><linearGradient id="ns-g" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.22"/>
                <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"/>
              </linearGradient></defs>
              ${yLabels}
              <path d="${curArea}" fill="url(#ns-g)"/>
              <path d="${prevLine}" fill="none" stroke="var(--text-tertiary)" stroke-width="1.5" stroke-dasharray="4 4" opacity="0.55"/>
              <path d="${curLine}"  fill="none" stroke="var(--accent)" stroke-width="2.25" stroke-linecap="round"/>
              <circle cx="${lastCur[0]}" cy="${lastCur[1]}" r="8" fill="var(--accent)" fill-opacity="0.16"/>
              <circle cx="${lastCur[0]}" cy="${lastCur[1]}" r="3.5" fill="var(--accent)"/>
              ${xLabels}
              ${hitBars}
            </svg>`;
        },

        donutColors: ['var(--accent)', '#5E6AD2', '#10B981', '#EAB308', '#EC4899', '#06B6D4', '#8B5CF6', '#F43F5E'],
        donutColor(i) { return this.donutColors[i % this.donutColors.length]; },

        donutChart() {
            const data = this.perfCategories;
            if (!data.length) {
                return `<svg viewBox="0 0 176 176" width="176" height="176">
                  <circle cx="88" cy="88" r="60" fill="none" stroke="var(--border-subtle)" stroke-width="28"/>
                  <text x="88" y="92" text-anchor="middle" class="donut-sub">No data</text>
                </svg>`;
            }
            const total = data.reduce((s, c) => s + c.rev, 0) || 1;
            const r = 60, R = 88, cx = 88, cy = 88;
            let angle = -Math.PI / 2, paths = '';
            const gap = 0.012;
            const fmt = v => window.posFormatMoney ? window.posFormatMoney(v) : '$' + v.toFixed(2);
            data.forEach((d, i) => {
                const portion = (d.rev / total) * Math.PI * 2;
                const a1 = angle + gap, a2 = angle + portion - gap;
                if (a2 > a1) {
                    const [x1, y1] = [cx + Math.cos(a1) * R, cy + Math.sin(a1) * R];
                    const [x2, y2] = [cx + Math.cos(a2) * R, cy + Math.sin(a2) * R];
                    const [x3, y3] = [cx + Math.cos(a2) * r, cy + Math.sin(a2) * r];
                    const [x4, y4] = [cx + Math.cos(a1) * r, cy + Math.sin(a1) * r];
                    const large = (a2 - a1) > Math.PI ? 1 : 0;
                    const pct = ((d.rev / total) * 100).toFixed(1);
                    const title = `${d.name} · ${pct}% · ${fmt(d.rev)}`;
                    paths += `<path d="M ${x1.toFixed(2)} ${y1.toFixed(2)} A ${R} ${R} 0 ${large} 1 ${x2.toFixed(2)} ${y2.toFixed(2)} L ${x3.toFixed(2)} ${y3.toFixed(2)} A ${r} ${r} 0 ${large} 0 ${x4.toFixed(2)} ${y4.toFixed(2)} Z" fill="${this.donutColor(i)}" style="cursor:pointer"><title>${title}</title></path>`;
                }
                angle += portion;
            });
            const totalFmt = window.posFormatMoney ? window.posFormatMoney(total) : '$' + total.toFixed(0);
            // Sub-label reflects the selected range filter, not a fixed "TODAY".
            const subLabel = `TOTAL ${this.periodLabel(this.perfRange).toUpperCase()}`;
            return `<svg viewBox="0 0 176 176" width="176" height="176">
              ${paths}
              <text x="88" y="86" text-anchor="middle" class="donut-label">${totalFmt}</text>
              <text x="88" y="104" text-anchor="middle" class="donut-sub">${subLabel}</text>
            </svg>`;
        },

        heatmap() {
            const days    = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const dayFull = { Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday' };
            const hours = Array.from({ length: 24 }, (_, i) => i); // 00 … 23
            const data  = this.heatmapData;  // [7][24] revenue
            const txns  = this.heatmapTxns;  // [7][24] transaction counts
            const items = this.heatmapItems; // [7][24] units sold
            const cellW = 20, cellH = 20, padL = 36, padT = 22;
            const w = padL + cellW * hours.length + 6;
            const h = padT + cellH * days.length + 8;

            // Max (colour normalisation, across shown hours), week total
            // (share-of-week %), and per-day totals (share-of-day %), all across
            // the full 24h matrix.
            let maxVal = 0, totalRev = 0;
            const dayTotals = days.map(() => 0);
            days.forEach((_, di) => hours.forEach((hr) => { maxVal = Math.max(maxVal, data[di]?.[hr] ?? 0); }));
            for (let di = 0; di < (data.length || 0); di++) {
                for (let hr = 0; hr < 24; hr++) {
                    const cell = data[di]?.[hr] ?? 0;
                    totalRev += cell;
                    if (di < dayTotals.length) dayTotals[di] += cell;
                }
            }

            const fmtCell = v => window.posFormatMoney ? window.posFormatMoney(v) : '$' + Number(v).toFixed(2);
            const fmtQty  = v => window.posFormatQty ? window.posFormatQty(v) : String(v);
            let cells = '';
            days.forEach((d, di) => {
                hours.forEach((hr, hi) => {
                    const v   = data[di]?.[hr] ?? 0;
                    const t   = txns[di]?.[hr] ?? 0;
                    const qty = items[di]?.[hr] ?? 0;
                    const opc = maxVal > 0 ? (0.1 + (v / maxVal) * 0.9).toFixed(2) : '0.05';
                    const hrLabel = String(hr).padStart(2,'0') + ':00 — ' + String(hr).padStart(2,'0') + ':59';
                    const avg      = t > 0 ? v / t : 0;
                    const share    = totalRev > 0 ? (v / totalRev) * 100 : 0;
                    const dayShare = dayTotals[di] > 0 ? (v / dayTotals[di]) * 100 : 0;
                    const peak     = maxVal > 0 ? (v / maxVal) * 100 : 0;
                    const card = {
                        title: dayFull[d] ?? d,
                        meta: hrLabel,
                        headline: { label: 'Sales', value: fmtCell(v) },
                        rows: [
                            { label: 'Transactions', value: String(t) },
                            { label: 'Items sold',   value: fmtQty(qty) },
                            { label: 'Avg sale',     value: fmtCell(avg) },
                            { label: 'Share of day', value: dayShare.toFixed(1) + '%' },
                            { label: 'vs peak hour', value: peak.toFixed(0) + '%' },
                        ],
                        footer: share.toFixed(1) + '% of this week’s sales',
                        empty: v <= 0,
                    };
                    const tip = JSON.stringify({ card }).replace(/"/g, '&quot;');
                    cells += `<rect x="${padL + hi * cellW}" y="${padT + di * cellH}" width="${cellW - 3}" height="${cellH - 3}" rx="3" fill="var(--accent)" fill-opacity="${opc}" data-tip="${tip}" style="cursor:pointer"></rect>`;
                });
            });

            const xLabs = hours.map((hr, hi) =>
                `<text x="${padL + hi * cellW + (cellW - 3) / 2}" y="${padT - 7}" text-anchor="middle" style="font-size:8px">${String(hr).padStart(2, '0')}</text>`
            ).join('');
            const yLabs = days.map((d, di) =>
                `<text x="${padL - 8}" y="${padT + di * cellH + cellH / 2 + 4}" text-anchor="end">${d}</text>`
            ).join('');

            return `<svg viewBox="0 0 ${w} ${h}" width="100%" class="chart-axis" preserveAspectRatio="xMidYMid meet">${xLabs}${yLabs}${cells}</svg>`;
        },

        // Trend chip delta as percentage string
        trendPct(current, previous) {
            if (!previous || previous === 0) return null;
            return (((current - previous) / previous) * 100).toFixed(1);
        },
    };
}
