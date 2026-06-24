(function (window, document) {
    'use strict';

    var root = null;

    var SUPPORTED_SOURCES = [
        'cookpad.com', 'allrecipes.com', 'recetasgratis.net', 'tasty.co',
        'food.com', 'bbcgoodfood.com', 'recetas.com', 'kiwilimon.com',
        'pequerecetas.com', 'elcomidista.com',
    ];

    var STATUS_META = {
        parsed:   { label: 'Parseado',  bg: '#e7f7f2', color: '#04ac85' },
        pending:  { label: 'Pendiente', bg: '#f0f4f8', color: '#697681' },
        failed:   { label: 'Falló',     bg: '#f7e7e7', color: '#b33a3a' },
        approved: { label: 'Aprobado',  bg: '#eef2ff', color: '#2f5fc4' },
    };

    var state = { loading: false };

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

    function fmtDateTime(iso) {
        if (!iso) { return '—'; }
        try {
            var d = new Date(iso);
            return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
                ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
        } catch (e) { return iso; }
    }

    // ─── Messages ────────────────────────────────────────────────────────────────

    function showMsg(type, html) {
        var el = qs('[data-import-message]', root);
        if (!el) { return; }
        el.innerHTML = html;
        el.style.display = 'block';
        el.style.background = type === 'ok'   ? '#e7f7f2' :
                              type === 'info'  ? '#f0f4ff' : '#f7e7e7';
        el.style.color      = type === 'ok'   ? '#04ac85' :
                              type === 'info'  ? '#2f5fc4' : '#b33a3a';
    }

    function hideMsg() {
        var el = qs('[data-import-message]', root);
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

    // ─── Render sources ──────────────────────────────────────────────────────────

    function renderSources() {
        var el = qs('[data-import-sources]', root);
        if (!el) { return; }
        el.innerHTML = SUPPORTED_SOURCES.map(function (s) {
            return '<span style="display:inline-block;background:#f0f4f8;color:#697681;border-radius:999px;padding:4px 10px;font-size:12px">' +
                escapeHtml(s) + '</span>';
        }).join('');
    }

    // ─── Render candidate detail ──────────────────────────────────────────────────

    function renderDetail(candidate) {
        var detailEl = qs('[data-import-detail]', root);
        if (!detailEl) { return; }

        if (!candidate) {
            detailEl.innerHTML = '<p class="muted" style="font-size:13px">Sin datos.</p>';
            return;
        }

        var sm = STATUS_META[candidate.status] || STATUS_META.pending;
        var statusBadge =
            '<span style="display:inline-block;background:' + sm.bg + ';color:' + sm.color + ';border-radius:4px;padding:3px 9px;font-size:12px;font-weight:700;margin-bottom:10px">' +
            escapeHtml(sm.label) + '</span>';

        var imgBlock = '';
        if (candidate.raw_image_url) {
            imgBlock = '<img src="' + escapeHtml(candidate.raw_image_url) + '" alt="" ' +
                'style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;margin-bottom:10px" ' +
                'onerror="this.style.display=\'none\'">';
        }

        var parsed = candidate.parsed_recipe_json || {};

        var metaRows = '';
        if (parsed.servings)          { metaRows += '<tr><td style="padding:4px 0;color:var(--muted);font-size:13px">Porciones</td><td style="padding:4px 0;font-size:13px">' + escapeHtml(parsed.servings) + '</td></tr>'; }
        if (parsed.prep_time_minutes) { metaRows += '<tr><td style="padding:4px 0;color:var(--muted);font-size:13px">Preparación</td><td style="padding:4px 0;font-size:13px">' + fmtMinutes(parsed.prep_time_minutes) + '</td></tr>'; }
        if (parsed.cook_time_minutes) { metaRows += '<tr><td style="padding:4px 0;color:var(--muted);font-size:13px">Cocción</td><td style="padding:4px 0;font-size:13px">' + fmtMinutes(parsed.cook_time_minutes) + '</td></tr>'; }
        metaRows += '<tr><td style="padding:4px 0;color:var(--muted);font-size:13px">Importado</td><td style="padding:4px 0;font-size:13px">' + fmtDateTime(candidate.created_at) + '</td></tr>';

        var ingrsBlock = '';
        var ingrs = candidate.raw_ingredients_json;
        if (ingrs && ingrs.length) {
            ingrsBlock =
                '<div style="margin-top:12px">' +
                '<p style="font-size:12px;font-weight:700;color:var(--muted);margin:0 0 6px">Ingredientes (' + ingrs.length + ')</p>' +
                '<ul style="margin:0;padding-left:16px;font-size:12px;line-height:1.7">' +
                ingrs.map(function (i) { return '<li>' + escapeHtml(String(i)) + '</li>'; }).join('') +
                '</ul>' +
                '</div>';
        }

        var stepsBlock = '';
        var steps = candidate.raw_steps_json;
        if (steps && steps.length) {
            stepsBlock =
                '<div style="margin-top:12px">' +
                '<p style="font-size:12px;font-weight:700;color:var(--muted);margin:0 0 6px">Pasos (' + steps.length + ')</p>' +
                '<ol style="margin:0;padding-left:16px;font-size:12px;line-height:1.7">' +
                steps.map(function (s) {
                    var desc = typeof s === 'object' ? (s.description || '') : String(s);
                    return '<li style="margin-bottom:4px">' + escapeHtml(desc) + '</li>';
                }).join('') +
                '</ol>' +
                '</div>';
        }

        var descBlock = (parsed.description || candidate.raw_description)
            ? '<p style="font-size:13px;color:var(--muted);margin:6px 0 0;line-height:1.5">' +
              escapeHtml((parsed.description || candidate.raw_description).slice(0, 300)) +
              ((parsed.description || candidate.raw_description).length > 300 ? '…' : '') +
              '</p>'
            : '';

        var sourceLink = candidate.source_url
            ? '<a href="' + escapeHtml(candidate.source_url) + '" target="_blank" rel="noopener noreferrer" ' +
              'style="font-size:12px;color:#04ac85;word-break:break-all">' +
              escapeHtml(candidate.source_site || candidate.source_url) + '</a>'
            : '';

        var failedNote = candidate.status === 'failed'
            ? '<div style="background:#f7e7e7;color:#b33a3a;border-radius:6px;padding:8px 10px;font-size:12px;margin-top:8px">' +
              'No se pudo extraer la receta del contenido obtenido. ' +
              'Verificá que la URL apunte a una receta válida en la fuente seleccionada.' +
              '</div>'
            : '';

        detailEl.innerHTML =
            statusBadge +
            imgBlock +
            '<h2 style="font-size:16px;font-weight:900;margin:0 0 4px">' +
            escapeHtml(candidate.raw_title || parsed.name || 'Sin título') +
            '</h2>' +
            descBlock +
            (sourceLink ? '<div style="margin-top:6px">' + sourceLink + '</div>' : '') +
            failedNote +
            (metaRows ? '<table style="width:100%;border-collapse:collapse;margin-top:10px">' + metaRows + '</table>' : '') +
            ingrsBlock +
            stepsBlock;
    }

    // ─── API ─────────────────────────────────────────────────────────────────────

    function doImport() {
        if (state.loading) { return; }

        var urlInput = qs('[data-import-url]', root);
        var btn      = qs('[data-import-btn]', root);
        var url      = urlInput ? urlInput.value.trim() : '';

        if (!url) {
            showMsg('err', 'Ingresá una URL válida.');
            return;
        }

        state.loading    = true;
        hideMsg();
        if (btn) { btn.disabled = true; btn.textContent = 'Importando...'; }
        showMsg('info', 'Importando... puede tardar hasta 15 segundos.');

        window.CCApi.request(endpoint('/admin/recipes/import/url'), {
            method: 'POST',
            body:   JSON.stringify({ url: url }),
        })
            .then(function (res) {
                state.loading = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Importar'; }

                var candidate = res.data || null;
                var sm = candidate && STATUS_META[candidate.status];
                if (candidate && candidate.status === 'failed') {
                    showMsg('err', 'La URL fue procesada pero no se pudo parsear la receta.');
                } else {
                    showMsg('ok', 'Receta importada correctamente como <strong>' + escapeHtml(sm ? sm.label : '') + '</strong>.');
                }
                renderDetail(candidate);
            })
            .catch(function (err) {
                state.loading = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Importar'; }

                var code = err && err.payload && err.payload.error && err.payload.error.code;
                if (code === 'RECIPE_IMPORT_DUPLICATE') {
                    showMsg('err', 'Esta URL ya fue importada o está pendiente de revisión.');
                } else if (code === 'RECIPE_IMPORT_UNSUPPORTED_SOURCE') {
                    showMsg('err', 'La fuente no está soportada. Usá una de las fuentes listadas abajo.');
                } else if (code === 'RECIPE_IMPORT_SSRF_BLOCKED') {
                    showMsg('err', 'La URL apunta a una dirección no permitida.');
                } else if (code === 'RECIPE_IMPORT_INVALID_URL') {
                    showMsg('err', 'La URL ingresada no es válida. Debe empezar con http:// o https://.');
                } else {
                    showMsg('err', escapeHtml(extractErrMsg(err)));
                }
            });
    }

    // ─── Events ──────────────────────────────────────────────────────────────────

    function bindEvents() {
        if (!root) { return; }

        var btn      = qs('[data-import-btn]', root);
        var urlInput = qs('[data-import-url]', root);

        if (btn) {
            btn.addEventListener('click', doImport);
        }

        if (urlInput) {
            urlInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { doImport(); }
            });
            urlInput.addEventListener('input', hideMsg);
        }
    }

    // ─── Boot ────────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        root = qs('[data-admin-recipe-import]');
        if (!root) { return; }

        renderSources();
        bindEvents();
    });

})(window, document);
