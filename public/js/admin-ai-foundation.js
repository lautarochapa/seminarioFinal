(function (window, document) {
    'use strict';

    var state = {
        flag: null,
        flagLoading: false,
        testing: false,
        testResult: null,
        testError: null,
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v == null) { return ''; }
        return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function endpoint(path) { return path; }

    function errMsg(err) {
        return (err && err.message) ? err.message : 'Ocurrió un error inesperado.';
    }

    // ── Flag status panel ─────────────────────────────────────────────────────

    function renderFlagStatus(root) {
        var panel = qs('[data-ai-flag-panel]', root);
        if (!panel) { return; }

        if (state.flagLoading) {
            panel.innerHTML = '<p class="muted" style="text-align:center;padding:16px 0">Verificando estado...</p>';
            return;
        }
        if (!state.flag) {
            panel.innerHTML = '<p class="muted" style="text-align:center;padding:16px 0">No se pudo obtener el estado del flag.</p>';
            return;
        }

        var f       = state.flag;
        var enabled = !!f.enabled;
        var color   = enabled ? '#04ac85' : '#697681';
        var bg      = enabled ? '#e7f7f2' : '#f0f3f5';
        var dot     = enabled ? '🟢' : '⚪';

        panel.innerHTML =
            '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">' +

            '<div style="flex:1;min-width:220px">' +
            '<div style="font-size:11px;font-weight:700;color:#697681;text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px">Estado del módulo IA</div>' +
            '<div style="display:flex;align-items:center;gap:10px">' +
            '<span style="font-size:22px">' + dot + '</span>' +
            '<div>' +
            '<div style="font-size:18px;font-weight:900;color:' + color + '">' + (enabled ? 'Activo' : 'Inactivo') + '</div>' +
            '<div style="font-size:12px;color:#697681">Flag: <code>' + escapeHtml(f.key) + '</code></div>' +
            '</div>' +
            '</div>' +
            '</div>' +

            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;min-width:260px">' +

            '<div style="background:#f9fbfb;border:1px solid #edf1f4;border-radius:8px;padding:10px 12px">' +
            '<div style="font-size:10px;font-weight:700;color:#697681;text-transform:uppercase;margin-bottom:3px">Proveedor actual</div>' +
            '<div style="font-size:13px;font-weight:700">FakeAiSuggestionProvider</div>' +
            '<div style="font-size:11px;color:#697681;margin-top:2px">Respuestas simuladas para MVP</div>' +
            '</div>' +

            '<div style="background:#f9fbfb;border:1px solid #edf1f4;border-radius:8px;padding:10px 12px">' +
            '<div style="font-size:10px;font-weight:700;color:#697681;text-transform:uppercase;margin-bottom:3px">Interfaz</div>' +
            '<div style="font-size:13px;font-weight:700">AiSuggestionProviderInterface</div>' +
            '<div style="font-size:11px;color:#697681;margin-top:2px">Intercambiable por proveedor real</div>' +
            '</div>' +

            '</div>' +

            (!enabled
                ? '<div style="background:#fff9e6;border:1px solid #f0d060;border-radius:8px;padding:10px 14px;width:100%;margin-top:8px;font-size:13px;color:#7a6010">' +
                  '⚠ El módulo de IA está <strong>desactivado</strong>. Los tests de sugerencias devolverán 503. ' +
                  '<a href="' + escapeHtml(window.location.pathname.replace('ai-foundation', 'feature-flags')) + '" style="color:#7a6010;font-weight:700">Activar desde Feature flags →</a>' +
                  '</div>'
                : '') +

            '</div>';
    }

    function loadFlag(root) {
        state.flagLoading = true;
        renderFlagStatus(root);

        window.CCApi.request(endpoint('/api/v1/admin/feature-flags/ai_enabled'))
            .then(function (res) {
                state.flag        = res.data || null;
                state.flagLoading = false;
                renderFlagStatus(root);
                renderTestPanel(root);
            })
            .catch(function () {
                state.flag        = null;
                state.flagLoading = false;
                renderFlagStatus(root);
            });
    }

    // ── Test suggestion panel ─────────────────────────────────────────────────

    function renderTestPanel(root) {
        var panel = qs('[data-ai-test-panel]', root);
        if (!panel) { return; }

        var enabled = state.flag && !!state.flag.enabled;

        var disabledNotice = !enabled
            ? '<div style="background:#fff9e6;border:1px solid #f0d060;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12px;color:#7a6010">' +
              '⚠ El flag de IA está desactivado — el test devolverá error 503.</div>'
            : '';

        var resultHtml = '';
        if (state.testing) {
            resultHtml = '<div data-ai-test-result style="padding:12px;background:#f9fbfb;border:1px solid #edf1f4;border-radius:8px;font-size:13px;color:#697681;text-align:center">Consultando provider...</div>';
        } else if (state.testResult) {
            var r = state.testResult;
            var conf = r.confidence != null ? Math.round(r.confidence * 100) + '%' : '-';
            resultHtml =
                '<div data-ai-test-result style="margin-top:12px;padding:14px;background:#e7f7f2;border:1px solid rgba(4,172,133,.3);border-radius:8px">' +
                '<div style="font-size:10px;font-weight:700;color:#04ac85;text-transform:uppercase;margin-bottom:8px">Respuesta del provider</div>' +
                '<div style="display:grid;grid-template-columns:auto 1fr;gap:6px 12px;font-size:13px">' +
                '<span style="color:#697681;font-weight:700">Provider</span><span><code>' + escapeHtml(r.provider || '-') + '</code></span>' +
                '<span style="color:#697681;font-weight:700">Confianza</span><span>' + escapeHtml(conf) + '</span>' +
                '<span style="color:#697681;font-weight:700">Sugerencia</span><span style="font-style:italic">"' + escapeHtml(r.suggestion || '') + '"</span>' +
                '</div>' +
                '</div>';
        } else if (state.testError) {
            resultHtml = '<div data-ai-test-result style="margin-top:10px;padding:10px 14px;background:#f7e7e7;border:1px solid rgba(179,58,58,.3);border-radius:8px;font-size:13px;color:#b33a3a">' + escapeHtml(state.testError) + '</div>';
        }

        panel.innerHTML =
            disabledNotice +
            '<form data-ai-test-form>' +
            '<div style="margin-bottom:10px">' +
            '<label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px;color:#697681">CONTEXTO PARA LA SUGERENCIA *</label>' +
            '<textarea class="form-control" name="context" rows="3" maxlength="500" ' +
            'placeholder="Ej: Usuario quiere bajar de peso, tiene intolerancia a la lactosa y prefiere recetas rápidas." ' +
            'style="resize:vertical;font-size:13px" required></textarea>' +
            '<div style="font-size:11px;color:#697681;margin-top:3px">Máx. 500 caracteres.</div>' +
            '</div>' +
            '<button type="submit" class="btn-main" data-ai-test-submit style="font-size:13px"' + (state.testing ? ' disabled' : '') + '>' +
            (state.testing ? 'Consultando...' : 'Probar sugerencia') +
            '</button>' +
            '</form>' +
            resultHtml;

        var form = qs('[data-ai-test-form]', panel);
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var ctx = (form.elements.context && form.elements.context.value.trim()) || '';
                if (!ctx) { return; }
                runTest(root, ctx);
            });
        }
    }

    function runTest(root, context) {
        state.testing    = true;
        state.testResult = null;
        state.testError  = null;
        renderTestPanel(root);

        window.CCApi.request(endpoint('/api/v1/admin/ai/test-suggestion'), {
            method: 'POST',
            body: { context: context },
        })
            .then(function (res) {
                state.testing    = false;
                state.testResult = res.data || res;
                renderTestPanel(root);
            })
            .catch(function (err) {
                state.testing   = false;
                state.testError = errMsg(err);
                renderTestPanel(root);
            });
    }

    // ── Architecture info ─────────────────────────────────────────────────────

    function renderArchInfo(root) {
        var panel = qs('[data-ai-arch-panel]', root);
        if (!panel) { return; }

        panel.innerHTML =
            '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">' +

            archCard('Interfaz de provider', 'AiSuggestionProviderInterface', 'Contrato único para cualquier proveedor de IA (OpenAI, Gemini, local, etc.).',  '🔌') +
            archCard('Provider MVP',          'FakeAiSuggestionProvider',      'Devuelve sugerencias simuladas. Se reemplaza sin tocar el Service.',              '🧪') +
            archCard('Service de orquestación','AiFoundationService',          'Verifica el flag, llama al provider y registra en AuditLog.',                    '⚙') +
            archCard('Guard de flag',          'module.ai (FeatureFlag)',       'Si está desactivado el service lanza AI_FEATURE_DISABLED (503).',                '🔒') +
            archCard('Auditoría',              'AuditLog: ai.test_suggestion',  'Cada llamada queda registrada con actor, contexto y proveedor usado.',           '📋') +
            archCard('Extensión futura',       'Real AI provider',              'Implementar AiSuggestionProviderInterface y registrar en el ServiceProvider.',   '🚀') +

            '</div>';
    }

    function archCard(title, name, desc, icon) {
        return '<div style="background:#f9fbfb;border:1px solid #edf1f4;border-radius:8px;padding:12px 14px">' +
            '<div style="font-size:18px;margin-bottom:6px">' + icon + '</div>' +
            '<div style="font-size:12px;font-weight:700;color:#697681;margin-bottom:2px">' + escapeHtml(title) + '</div>' +
            '<div style="font-size:13px;font-weight:900;margin-bottom:4px">' + escapeHtml(name) + '</div>' +
            '<div style="font-size:12px;color:#697681;line-height:1.4">' + escapeHtml(desc) + '</div>' +
            '</div>';
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-admin-ai-foundation]');
        if (!root) { return; }
        renderArchInfo(root);
        loadFlag(root);
    });
})(window, document);
