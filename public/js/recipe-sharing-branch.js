(function (window, document) {
    'use strict';

    function escapeHtml(v) {
        if (v === null || v === undefined) { return ''; }
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function extractMsg(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message) || 'Error inesperado.';
    }

    function extractCode(err) {
        return (err && err.payload && err.payload.error && err.payload.error.code) || '';
    }

    // ─── Render ──────────────────────────────────────────────────────────────────

    function render(s) {
        if (!s.el) { return; }

        var shareBtn = '';
        if (s.isOwner) {
            if (s.isPublic) {
                shareBtn =
                    '<button type="button" data-sb-unshare ' +
                    'style="border:1px solid #dde3e8;background:#fff;color:#697681;border-radius:50px;padding:7px 14px;font-size:13px;font-weight:700;cursor:pointer"' +
                    (s.shareLoading ? ' disabled' : '') + '>' +
                    (s.shareLoading ? 'Procesando...' : 'Privada') +
                    '</button>';
            } else {
                shareBtn =
                    '<button type="button" data-sb-share ' +
                    'style="border:1px solid #04ac85;background:#e7f7f2;color:#04ac85;border-radius:50px;padding:7px 14px;font-size:13px;font-weight:700;cursor:pointer"' +
                    (s.shareLoading ? ' disabled' : '') + '>' +
                    (s.shareLoading ? 'Procesando...' : 'Compartir') +
                    '</button>';
            }
        }

        var branchBtn =
            '<button type="button" data-sb-branch ' +
            'style="border:1px solid #dde3e8;background:#fff;color:#24252a;border-radius:50px;padding:7px 14px;font-size:13px;font-weight:700;cursor:pointer"' +
            (s.branchLoading ? ' disabled' : '') + '>' +
            (s.branchLoading ? 'Creando variante...' : 'Crear variante') +
            '</button>';

        var statusBadge = s.isPublic
            ? '<span style="font-size:11px;background:#e7f7f2;color:#04ac85;border-radius:4px;padding:2px 8px;font-weight:700">Pública</span>'
            : '<span style="font-size:11px;background:#f0f4f8;color:#697681;border-radius:4px;padding:2px 8px">Privada</span>';

        var branchInfo = '';
        if (s.branchedFrom) {
            branchInfo =
                '<div style="font-size:12px;color:#697681;margin-top:4px">Variante de receta #' + escapeHtml(s.branchedFrom) + '</div>';
        }

        s.el.innerHTML =
            '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8">' +
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">' +
            statusBadge + branchInfo +
            '</div>' +
            '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
            shareBtn + branchBtn +
            '</div>' +
            '<div data-sb-msg style="display:none;font-size:12px;margin-top:8px;padding:6px 10px;border-radius:4px"></div>' +
            '</div>';

        bindEvents(s);
    }

    function showMsg(s, type, html) {
        var msgEl = s.el ? s.el.querySelector('[data-sb-msg]') : null;
        if (!msgEl) { return; }
        msgEl.innerHTML = html;
        msgEl.style.display = 'block';
        msgEl.style.background = type === 'ok' ? '#e7f7f2' : (type === 'info' ? '#f0f4ff' : '#f7e7e7');
        msgEl.style.color      = type === 'ok' ? '#04ac85' : (type === 'info' ? '#2f5fc4' : '#b33a3a');
        if (type === 'ok') { setTimeout(function () { if (msgEl) { msgEl.style.display = 'none'; } }, 5000); }
    }

    // ─── Events ──────────────────────────────────────────────────────────────────

    function bindEvents(s) {
        if (!s.el) { return; }

        var shareBtn  = s.el.querySelector('[data-sb-share]');
        var unshareBtn= s.el.querySelector('[data-sb-unshare]');
        var branchBtn = s.el.querySelector('[data-sb-branch]');

        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                if (s.shareLoading) { return; }
                s.shareLoading = true;
                render(s);

                window.CCApi.request(endpoint('/recipes/' + s.recipeId + '/share'), { method: 'POST' })
                    .then(function (res) {
                        s.isPublic     = (res.data && res.data.is_public) !== undefined ? res.data.is_public : true;
                        s.shareLoading = false;
                        render(s);
                        showMsg(s, 'ok', 'Receta compartida. Quedó pública como no verificada.');
                    })
                    .catch(function (err) {
                        s.shareLoading = false;
                        var code = extractCode(err);
                        if (code === 'RECIPE_ALREADY_PUBLIC') { s.isPublic = true; }
                        render(s);
                        if (code !== 'RECIPE_ALREADY_PUBLIC') {
                            showMsg(s, 'err', escapeHtml(extractMsg(err)));
                        }
                    });
            });
        }

        if (unshareBtn) {
            unshareBtn.addEventListener('click', function () {
                if (s.shareLoading) { return; }
                s.shareLoading = true;
                render(s);

                window.CCApi.request(endpoint('/recipes/' + s.recipeId + '/unshare'), { method: 'POST' })
                    .then(function (res) {
                        s.isPublic     = (res.data && res.data.is_public !== undefined) ? res.data.is_public : false;
                        s.shareLoading = false;
                        render(s);
                        showMsg(s, 'ok', 'Receta privada. Ya no es visible para otros usuarios.');
                    })
                    .catch(function (err) {
                        s.shareLoading = false;
                        var code = extractCode(err);
                        if (code === 'RECIPE_ALREADY_PRIVATE') { s.isPublic = false; }
                        render(s);
                        if (code !== 'RECIPE_ALREADY_PRIVATE') {
                            showMsg(s, 'err', escapeHtml(extractMsg(err)));
                        }
                    });
            });
        }

        if (branchBtn) {
            branchBtn.addEventListener('click', function () {
                if (s.branchLoading) { return; }
                s.branchLoading = true;
                render(s);

                window.CCApi.request(endpoint('/recipes/' + s.recipeId + '/branch'), { method: 'POST' })
                    .then(function (res) {
                        s.branchLoading = false;
                        render(s);
                        var newRecipe = res.data || {};
                        showMsg(s, 'info',
                            'Variante creada: <strong>' + escapeHtml(newRecipe.name || 'Nueva receta') + '</strong>. ' +
                            'Podés encontrarla en <a href="/web/recipes" style="color:#2f5fc4">Mis recetas</a>.');
                    })
                    .catch(function (err) {
                        s.branchLoading = false;
                        render(s);
                        showMsg(s, 'err', escapeHtml(extractMsg(err)));
                    });
            });
        }
    }

    // ─── Public ──────────────────────────────────────────────────────────────────

    window.RecipeSharingBranch = {
        mount: function (containerEl, recipeId, isOwner, isPublic, branchedFrom) {
            var s = {
                el:            containerEl,
                recipeId:      recipeId,
                isOwner:       !!isOwner,
                isPublic:      !!isPublic,
                branchedFrom:  branchedFrom || null,
                shareLoading:  false,
                branchLoading: false,
            };
            render(s);
        },
    };

})(window, document);
