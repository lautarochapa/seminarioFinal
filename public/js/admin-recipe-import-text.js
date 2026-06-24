(function (window, document) {
    'use strict';

    var root  = null;
    var state = { loading: false };

    var AMBIGUOUS_LABELS = {
        ingredient_units_ambiguous: 'Algunas unidades de ingredientes no pudieron resolverse',
        no_ingredients_detected:    'No se detectaron ingredientes',
        no_steps_detected:          'No se detectaron pasos de preparación',
    };

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined) { return ''; }
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function fmtMinutes(m) {
        var n = parseInt(m, 10);
        if (!n) { return '—'; }
        if (n < 60) { return n + ' min'; }
        var h = Math.floor(n / 60); var rm = n % 60;
        return rm ? h + 'h ' + rm + 'min' : h + 'h';
    }

    function fmtQty(v) {
        if (v === null || v === undefined || v === '') { return ''; }
        var f = parseFloat(v);
        if (isNaN(f)) { return String(v); }
        return f % 1 === 0 ? String(parseInt(f, 10)) : String(parseFloat(f.toFixed(2)));
    }

    // ─── Messages ────────────────────────────────────────────────────────────────

    function showMsg(type, html) {
        var el = qs('[data-importtxt-message]', root);
        if (!el) { return; }
        el.innerHTML = html;
        el.style.display = 'block';
        el.style.background = type === 'ok'  ? '#e7f7f2' :
                              type === 'warn' ? '#fff8e1' : '#f7e7e7';
        el.style.color      = type === 'ok'  ? '#04ac85' :
                              type === 'warn' ? '#b88a00' : '#b33a3a';
    }

    function hideMsg() {
        var el = qs('[data-importtxt-message]', root);
        if (el) { el.style.display = 'none'; }
    }

    function extractErrMsg(err) {
        if (!err || !err.payload || !err.payload.error) { return 'Error inesperado.'; }
        var e = err.payload.error;
        if (e.field_errors && Object.keys(e.field_errors).length) {
            var msgs = [];
            Object.keys(e.field_errors).forEach(function (f) {
                var arr = e.field_errors[f];
                if (Array.isArray(arr)) { msgs = msgs.concat(arr); }
            });
            return msgs.join(' ');
        }
        return e.message || 'Error inesperado.';
    }

    // ─── Render detail ────────────────────────────────────────────────────────────

    function renderIngredient(ing) {
        if (typeof ing === 'string') { return escapeHtml(ing); }
        var parts = [];
        if (ing.quantity)  { parts.push(fmtQty(ing.quantity)); }
        if (ing.unit)      { parts.push(escapeHtml(ing.unit)); }
        if (ing.name)      { parts.push('<strong>' + escapeHtml(ing.name) + '</strong>'); }
        var line = parts.join(' ');
        if (ing.unit_ambiguous) {
            line += ' <span style="color:#b88a00;font-size:10px">⚠ unidad</span>';
        }
        return line;
    }

    function renderDetail(candidate) {
        var detailEl = qs('[data-importtxt-detail]', root);
        if (!detailEl) { return; }

        if (!candidate) {
            detailEl.innerHTML = '<p class="muted" style="font-size:13px">Sin resultado.</p>';
            return;
        }

        var parsed = candidate.parsed_recipe_json || {};
        var flags  = parsed.ambiguous_flags  || [];
        var obs    = parsed.observations     || [];

        // Ambiguous flags
        var flagsBlock = '';
        if (flags.length) {
            flagsBlock =
                '<div style="background:#fff8e1;border-radius:6px;padding:8px 10px;margin-bottom:10px">' +
                flags.map(function (f) {
                    return '<div style="font-size:12px;color:#b88a00">⚠ ' + escapeHtml(AMBIGUOUS_LABELS[f] || f) + '</div>';
                }).join('') +
                '</div>';
        }

        // Observations
        var obsBlock = '';
        if (obs.length) {
            obsBlock =
                '<div style="background:#f0f4ff;border-radius:6px;padding:8px 10px;margin-bottom:10px">' +
                obs.map(function (o) {
                    return '<div style="font-size:12px;color:#2f5fc4">' + escapeHtml(o) + '</div>';
                }).join('') +
                '</div>';
        }

        // Meta table
        var metaRows = '';
        if (parsed.servings)          { metaRows += '<tr><td style="padding:4px 0;color:var(--muted);font-size:13px;width:110px">Porciones</td><td style="padding:4px 0;font-size:13px">' + escapeHtml(parsed.servings) + '</td></tr>'; }
        if (parsed.prep_time_minutes) { metaRows += '<tr><td style="padding:4px 0;color:var(--muted);font-size:13px">Preparación</td><td style="padding:4px 0;font-size:13px">' + fmtMinutes(parsed.prep_time_minutes) + '</td></tr>'; }
        if (parsed.cook_time_minutes) { metaRows += '<tr><td style="padding:4px 0;color:var(--muted);font-size:13px">Cocción</td><td style="padding:4px 0;font-size:13px">' + fmtMinutes(parsed.cook_time_minutes) + '</td></tr>'; }

        // Description
        var descBlock = (parsed.description || candidate.raw_description)
            ? '<p style="font-size:13px;color:var(--muted);margin:4px 0 10px;line-height:1.5">' +
              escapeHtml((parsed.description || candidate.raw_description).slice(0, 400)) +
              ((parsed.description || candidate.raw_description).length > 400 ? '…' : '') +
              '</p>'
            : '';

        // Ingredients
        var ingrs     = candidate.raw_ingredients_json || [];
        var ingrsHtml = '';
        if (ingrs.length) {
            ingrsHtml =
                '<div style="margin-top:12px">' +
                '<p style="font-size:12px;font-weight:700;color:var(--muted);margin:0 0 6px">Ingredientes detectados (' + ingrs.length + ')</p>' +
                '<ul style="margin:0;padding-left:16px;font-size:13px;line-height:1.9">' +
                ingrs.map(function (i) { return '<li>' + renderIngredient(i) + '</li>'; }).join('') +
                '</ul>' +
                '</div>';
        } else {
            ingrsHtml = '<p style="font-size:12px;color:#b33a3a;margin-top:10px">No se detectaron ingredientes.</p>';
        }

        // Steps
        var steps     = candidate.raw_steps_json || [];
        var stepsHtml = '';
        if (steps.length) {
            stepsHtml =
                '<div style="margin-top:12px">' +
                '<p style="font-size:12px;font-weight:700;color:var(--muted);margin:0 0 6px">Pasos detectados (' + steps.length + ')</p>' +
                '<ol style="margin:0;padding-left:16px;font-size:13px;line-height:1.7">' +
                steps.map(function (s) {
                    var desc = typeof s === 'object' ? (s.description || '') : String(s);
                    return '<li style="margin-bottom:5px">' + escapeHtml(desc) + '</li>';
                }).join('') +
                '</ol>' +
                '</div>';
        } else {
            stepsHtml = '<p style="font-size:12px;color:#b33a3a;margin-top:10px">No se detectaron pasos.</p>';
        }

        detailEl.innerHTML =
            '<div style="display:inline-block;background:#e7f7f2;color:#04ac85;border-radius:4px;padding:3px 9px;font-size:12px;font-weight:700;margin-bottom:10px">Parseado</div>' +
            '<h2 style="font-size:16px;font-weight:900;margin:0 0 4px">' +
            escapeHtml(candidate.raw_title || parsed.name || 'Sin título') +
            '</h2>' +
            descBlock +
            flagsBlock +
            obsBlock +
            (metaRows ? '<table style="width:100%;border-collapse:collapse;margin-bottom:4px">' + metaRows + '</table>' : '') +
            ingrsHtml +
            stepsHtml;
    }

    // ─── Char counter ─────────────────────────────────────────────────────────────

    function updateCounter() {
        var ta  = qs('[data-importtxt-body]', root);
        var ctr = qs('[data-importtxt-chars]', root);
        if (!ta || !ctr) { return; }
        var len   = ta.value.length;
        var color = len > 20000 ? '#b33a3a' : (len < 20 && len > 0 ? '#b88a00' : '#697681');
        ctr.textContent = len + ' / 20000';
        ctr.style.color = color;
    }

    // ─── API ─────────────────────────────────────────────────────────────────────

    function doImport() {
        if (state.loading) { return; }

        var ta  = qs('[data-importtxt-body]', root);
        var btn = qs('[data-importtxt-btn]', root);
        var txt = ta ? ta.value.trim() : '';

        if (txt.length < 20) {
            showMsg('err', 'El texto debe tener al menos 20 caracteres.');
            return;
        }
        if (txt.length > 20000) {
            showMsg('err', 'El texto no puede superar los 20.000 caracteres.');
            return;
        }

        state.loading = true;
        hideMsg();
        if (btn) { btn.disabled = true; btn.textContent = 'Parseando...'; }

        window.CCApi.request(endpoint('/admin/recipes/import/text'), {
            method: 'POST',
            body:   JSON.stringify({ text: txt }),
        })
            .then(function (res) {
                state.loading = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Parsear receta'; }

                var candidate = res.data || null;
                var parsed    = candidate && candidate.parsed_recipe_json || {};
                var flags     = parsed.ambiguous_flags || [];

                if (flags.length) {
                    showMsg('warn', 'Receta parseada con advertencias. Revisá el resultado antes de aprobar.');
                } else {
                    showMsg('ok', 'Receta parseada correctamente.');
                }
                renderDetail(candidate);
            })
            .catch(function (err) {
                state.loading = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Parsear receta'; }

                var code = err && err.payload && err.payload.error && err.payload.error.code;
                if (code === 'RECIPE_IMPORT_DUPLICATE') {
                    showMsg('err', 'Este texto ya fue importado previamente.');
                } else if (code === 'RECIPE_IMPORT_PARSE_FAILED') {
                    showMsg('err', 'No se pudo extraer una receta del texto ingresado. Verificá que incluya un título y al menos ingredientes o pasos.');
                } else {
                    showMsg('err', escapeHtml(extractErrMsg(err)));
                }
            });
    }

    // ─── Events ──────────────────────────────────────────────────────────────────

    function bindEvents() {
        if (!root) { return; }

        var btn   = qs('[data-importtxt-btn]', root);
        var clear = qs('[data-importtxt-clear]', root);
        var ta    = qs('[data-importtxt-body]', root);

        if (btn) { btn.addEventListener('click', doImport); }

        if (clear) {
            clear.addEventListener('click', function () {
                if (ta) { ta.value = ''; }
                updateCounter();
                hideMsg();
                var detailEl = qs('[data-importtxt-detail]', root);
                if (detailEl) {
                    detailEl.innerHTML = '<p class="muted" style="font-size:13px">El resultado del parseo aparecerá aquí.</p>';
                }
            });
        }

        if (ta) {
            ta.addEventListener('input', function () {
                updateCounter();
                hideMsg();
            });
            ta.addEventListener('keydown', function (e) {
                // Ctrl+Enter submits
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    doImport();
                }
            });
        }
    }

    // ─── Boot ────────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        root = qs('[data-admin-recipe-import-text]');
        if (!root) { return; }

        updateCounter();
        bindEvents();
    });

})(window, document);
