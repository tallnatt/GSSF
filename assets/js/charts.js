document.addEventListener('DOMContentLoaded', () => {
    const lineContainer = document.getElementById('progress-line-chart');
    const barContainer = document.getElementById('stage-bar-chart');
    const summaryBody = document.getElementById('compare-summary-body');

    if (!lineContainer || !barContainer || !summaryBody) {
        return;
    }

    const shooterCheckboxes = Array.from(document.querySelectorAll('.js-compare-shooter'));
    const metricSelect = document.getElementById('compare-metric');
    const divisionSelect = document.getElementById('compare-division');
    const stageAggSelect = document.getElementById('compare-stage-agg');
    const lineTitle = document.getElementById('line-chart-title');
    const barTitle = document.getElementById('bar-chart-title');

    const metricLabels = {
        total_time: 'Total Match Time',
        five_to_glock: 'Five to Glock',
        glock_the_plates: 'Glock the Plates',
        glock_m: "Glock 'M",
    };

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    };

    async function loadAndRender() {
        const selectedIds = shooterCheckboxes
            .filter((cb) => cb.checked)
            .map((cb) => cb.value);

        if (selectedIds.length === 0) {
            lineContainer.innerHTML = '<div class="chart-empty">Select at least one family shooter above to view progress graphs.</div>';
            barContainer.innerHTML = '<div class="chart-empty">Select at least one family shooter above to compare stage times.</div>';
            summaryBody.innerHTML = '<tr><td colspan="7" class="empty-cell">No shooters selected.</td></tr>';
            return;
        }

        const params = new URLSearchParams({
            action: 'chart_data',
            user_ids: selectedIds.join(','),
            division: divisionSelect.value,
        });

        try {
            const resp = await fetch('api.php?' + params.toString(), {
                headers: { Accept: 'application/json' },
            });
            if (!resp.ok) {
                throw new Error('Failed to load chart data');
            }
            const data = await resp.json();
            const results = data.results || [];
            const metric = metricSelect.value;
            const aggMode = stageAggSelect.value;

            lineTitle.textContent = `${metricLabels[metric] || 'Time'} Over Time`;
            barTitle.textContent = `Stage-by-Stage Comparison (${aggMode === 'best' ? 'Personal Best' : 'Average'})`;

            renderLineChart(results, metric);
            renderBarChart(results, aggMode);
            renderSummaryTable(results, metric);
        } catch (err) {
            lineContainer.innerHTML = '<div class="chart-empty">Unable to load chart data.</div>';
            barContainer.innerHTML = '<div class="chart-empty">Unable to load chart data.</div>';
        }
    }

    function renderLineChart(results, metric) {
        if (!results.length) {
            lineContainer.innerHTML = '<div class="chart-empty">No match results found for the selected filters.</div>';
            return;
        }

        // Group by shooter
        const seriesMap = new Map();
        const dateSet = new Set();

        results.forEach((r) => {
            dateSet.add(r.match_date);
            if (!seriesMap.has(r.user_id)) {
                seriesMap.set(r.user_id, {
                    id: r.user_id,
                    name: r.shooter_name,
                    color: r.shooter_color || '#1d4ed8',
                    points: [],
                });
            }
            seriesMap.get(r.user_id).points.push(r);
        });

        const dates = Array.from(dateSet).sort();
        const values = results.map((r) => Number(r[metric]));
        let minY = Math.min(...values);
        let maxY = Math.max(...values);
        if (minY === maxY) {
            minY = Math.max(0, minY - 2);
            maxY = maxY + 2;
        } else {
            const pad = (maxY - minY) * 0.12;
            minY = Math.max(0, minY - pad);
            maxY = maxY + pad;
        }

        const width = 880;
        const height = 310;
        const margin = { top: 20, right: 24, bottom: 44, left: 56 };
        const plotW = width - margin.left - margin.right;
        const plotH = height - margin.top - margin.bottom;

        const xForDate = (dateStr) => {
            if (dates.length === 1) return margin.left + plotW / 2;
            const idx = dates.indexOf(dateStr);
            return margin.left + (idx / (dates.length - 1)) * plotW;
        };

        const yForVal = (val) => {
            return margin.top + plotH - ((val - minY) / (maxY - minY)) * plotH;
        };

        // Y-axis ticks (5 steps)
        const yTicks = [];
        for (let i = 0; i <= 4; i++) {
            const v = minY + ((maxY - minY) * i) / 4;
            yTicks.push(v);
        }

        let svg = `<svg class="chart-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Progress line chart">`;

        // Gridlines & Y labels
        yTicks.forEach((v) => {
            const y = yForVal(v).toFixed(1);
            svg += `<line x1="${margin.left}" y1="${y}" x2="${width - margin.right}" y2="${y}" stroke="var(--border)" stroke-dasharray="3,3" />`;
            svg += `<text x="${margin.left - 8}" y="${Number(y) + 4}" text-anchor="end" font-size="11" fill="var(--text-faint)" font-family="IBM Plex Mono, monospace">${v.toFixed(1)}s</text>`;
        });

        // X-axis labels
        const maxXLabels = 8;
        const step = Math.max(1, Math.ceil(dates.length / maxXLabels));
        dates.forEach((d, idx) => {
            if (idx % step !== 0 && idx !== dates.length - 1) return;
            const x = xForDate(d).toFixed(1);
            svg += `<text x="${x}" y="${height - 14}" text-anchor="middle" font-size="11" fill="var(--text-faint)" font-family="IBM Plex Mono, monospace">${escapeHtml(d)}</text>`;
        });

        // Series lines & points
        seriesMap.forEach((series) => {
            const pts = series.points;
            if (pts.length > 1) {
                const pathD = pts
                    .map((p, i) => {
                        const x = xForDate(p.match_date).toFixed(1);
                        const y = yForVal(Number(p[metric])).toFixed(1);
                        return `${i === 0 ? 'M' : 'L'}${x},${y}`;
                    })
                    .join(' ');
                svg += `<path d="${pathD}" fill="none" stroke="${escapeHtml(series.color)}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />`;
            }

            pts.forEach((p) => {
                const cx = xForDate(p.match_date).toFixed(1);
                const cy = yForVal(Number(p[metric])).toFixed(1);
                const val = Number(p[metric]).toFixed(2);
                svg += `<circle class="js-chart-pt" cx="${cx}" cy="${cy}" r="5" fill="${escapeHtml(series.color)}" stroke="var(--surface)" stroke-width="2"
                    data-shooter="${escapeHtml(series.name)}"
                    data-match="${escapeHtml(p.match_name)}"
                    data-date="${escapeHtml(p.match_date)}"
                    data-division="${escapeHtml(p.division)}"
                    data-value="${val}s"
                    style="cursor:pointer;" />`;
            });
        });

        svg += '</svg>';

        // Legend
        let legend = '<div class="chart-legend">';
        seriesMap.forEach((s) => {
            legend += `<span class="legend-item"><span class="color-dot" style="background-color:${escapeHtml(s.color)};"></span>${escapeHtml(s.name)}</span>`;
        });
        legend += '</div>';

        lineContainer.innerHTML = svg + legend + '<div class="chart-tooltip" id="line-tooltip"></div>';

        const tooltip = document.getElementById('line-tooltip');
        lineContainer.querySelectorAll('.js-chart-pt').forEach((pt) => {
            pt.addEventListener('mouseenter', (e) => {
                const t = e.target;
                tooltip.innerHTML = `<strong>${escapeHtml(t.dataset.shooter)}</strong> · ${escapeHtml(t.dataset.value)}<br>${escapeHtml(t.dataset.match)} (${escapeHtml(t.dataset.date)})<br>${escapeHtml(t.dataset.division)}`;
                tooltip.style.display = 'block';
            });
            pt.addEventListener('mousemove', (e) => {
                const rect = lineContainer.getBoundingClientRect();
                tooltip.style.left = `${e.clientX - rect.left + 12}px`;
                tooltip.style.top = `${e.clientY - rect.top - 14}px`;
            });
            pt.addEventListener('mouseleave', () => {
                tooltip.style.display = 'none';
            });
        });
    }

    function renderBarChart(results, aggMode) {
        if (!results.length) {
            barContainer.innerHTML = '<div class="chart-empty">No stage data available for the selected filters.</div>';
            return;
        }

        const stages = [
            { key: 'five_to_glock', label: 'Five to Glock' },
            { key: 'glock_the_plates', label: 'Glock the Plates' },
            { key: 'glock_m', label: "Glock 'M" },
        ];

        const shooterMap = new Map();
        results.forEach((r) => {
            if (!shooterMap.has(r.user_id)) {
                shooterMap.set(r.user_id, {
                    id: r.user_id,
                    name: r.shooter_name,
                    color: r.shooter_color || '#1d4ed8',
                    rows: [],
                });
            }
            shooterMap.get(r.user_id).rows.push(r);
        });

        const shooters = Array.from(shooterMap.values());
        const computed = new Map();
        let maxVal = 0;

        shooters.forEach((s) => {
            const stageVals = {};
            stages.forEach((st) => {
                const vals = s.rows.map((r) => Number(r[st.key]));
                const val = aggMode === 'best'
                    ? Math.min(...vals)
                    : vals.reduce((a, b) => a + b, 0) / vals.length;
                stageVals[st.key] = val;
                if (val > maxVal) maxVal = val;
            });
            computed.set(s.id, stageVals);
        });

        maxVal = Math.max(5, maxVal * 1.15);

        const width = 880;
        const height = 270;
        const margin = { top: 20, right: 24, bottom: 40, left: 56 };
        const plotW = width - margin.left - margin.right;
        const plotH = height - margin.top - margin.bottom;

        const yForVal = (val) => margin.top + plotH - (val / maxVal) * plotH;

        let svg = `<svg class="chart-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Stage comparison bar chart">`;

        for (let i = 0; i <= 4; i++) {
            const v = (maxVal * i) / 4;
            const y = yForVal(v).toFixed(1);
            svg += `<line x1="${margin.left}" y1="${y}" x2="${width - margin.right}" y2="${y}" stroke="var(--border)" stroke-dasharray="3,3" />`;
            svg += `<text x="${margin.left - 8}" y="${Number(y) + 4}" text-anchor="end" font-size="11" fill="var(--text-faint)" font-family="IBM Plex Mono, monospace">${v.toFixed(1)}s</text>`;
        }

        const groupW = plotW / stages.length;
        const innerPad = 28;
        const usableGroupW = groupW - innerPad * 2;
        const barGap = 6;
        const barW = Math.min(44, (usableGroupW - barGap * (shooters.length - 1)) / shooters.length);
        const totalBarsW = shooters.length * barW + (shooters.length - 1) * barGap;

        stages.forEach((st, sIdx) => {
            const groupX = margin.left + sIdx * groupW;
            const startX = groupX + (groupW - totalBarsW) / 2;

            svg += `<text x="${(groupX + groupW / 2).toFixed(1)}" y="${height - 12}" text-anchor="middle" font-size="12" font-weight="600" fill="var(--text)">${escapeHtml(st.label)}</text>`;

            shooters.forEach((sh, shIdx) => {
                const val = computed.get(sh.id)[st.key];
                const x = startX + shIdx * (barW + barGap);
                const y = yForVal(val);
                const h = margin.top + plotH - y;
                svg += `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${barW.toFixed(1)}" height="${Math.max(1, h).toFixed(1)}" rx="3" fill="${escapeHtml(sh.color)}" />`;
                svg += `<text x="${(x + barW / 2).toFixed(1)}" y="${(y - 5).toFixed(1)}" text-anchor="middle" font-size="10" fill="var(--text-muted)" font-family="IBM Plex Mono, monospace">${val.toFixed(1)}</text>`;
            });
        });

        svg += '</svg>';

        let legend = '<div class="chart-legend">';
        shooters.forEach((s) => {
            legend += `<span class="legend-item"><span class="color-dot" style="background-color:${escapeHtml(s.color)};"></span>${escapeHtml(s.name)}</span>`;
        });
        legend += '</div>';

        barContainer.innerHTML = svg + legend;
    }

    function renderSummaryTable(results, metric) {
        if (!results.length) {
            summaryBody.innerHTML = '<tr><td colspan="7" class="empty-cell">No match results found.</td></tr>';
            return;
        }

        const byShooter = new Map();
        results.forEach((r) => {
            if (!byShooter.has(r.user_id)) {
                byShooter.set(r.user_id, {
                    name: r.shooter_name,
                    color: r.shooter_color || '#1d4ed8',
                    rows: [],
                });
            }
            byShooter.get(r.user_id).rows.push(r);
        });

        let html = '';
        byShooter.forEach((s) => {
            const vals = s.rows.map((r) => Number(r[metric]));
            const first = vals[0];
            const latest = vals[vals.length - 1];
            const best = Math.min(...vals);
            const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
            const delta = latest - first;
            const deltaClass = delta < 0 ? 'delta-good' : (delta > 0 ? 'delta-bad' : '');
            const deltaPrefix = delta > 0 ? '+' : '';
            const deltaStr = s.rows.length > 1 ? `${deltaPrefix}${delta.toFixed(2)}s` : '—';

            html += `
                <tr>
                    <td>
                        <span class="shooter-cell">
                            <span class="color-dot" style="background-color:${escapeHtml(s.color)};"></span>
                            <strong>${escapeHtml(s.name)}</strong>
                        </span>
                    </td>
                    <td class="num mono">${s.rows.length}</td>
                    <td class="num mono">${first.toFixed(2)}s</td>
                    <td class="num mono">${latest.toFixed(2)}s</td>
                    <td class="num mono strong-time">${best.toFixed(2)}s</td>
                    <td class="num mono">${avg.toFixed(2)}s</td>
                    <td class="num mono ${deltaClass}">${deltaStr}</td>
                </tr>
            `;
        });

        summaryBody.innerHTML = html;
    }

    shooterCheckboxes.forEach((cb) => cb.addEventListener('change', loadAndRender));
    metricSelect.addEventListener('change', loadAndRender);
    divisionSelect.addEventListener('change', loadAndRender);
    stageAggSelect.addEventListener('change', loadAndRender);

    loadAndRender();
});
