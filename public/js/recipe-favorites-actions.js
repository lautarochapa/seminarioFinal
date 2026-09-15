(function (window, document) {
    'use strict';

    function escapeHtml(v) {
        if (v === null || v === undefined) { return ''; }
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function createCookIdempotencyKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return 'web-cook-' + window.crypto.randomUUID();
        }
        return 'web-cook-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    function extractMsg(err) {
        var fields = err && err.payload && err.payload.error && err.payload.error.field_errors;
        if (fields) {
            var first = Object.keys(fields)[0];
            if (first && fields[first] && fields[first][0]) { return fields[first][0]; }
        }
        return (err && err.payload && err.payload.error && err.payload.error.message) || 'Error inesperado.';
    }

    // ─── Render ──────────────────────────────────────────────────────────────────

    function render(s) {
        if (!s.el) { return; }

        var favBtnLabel = s.isFavorited ? '♥ Guardado' : '♡ Favorito';
        var favBtnBg    = s.isFavorited ? '#e7f7f2' : '#fff';
        var favBtnColor = s.isFavorited ? '#04ac85' : '#697681';

        var cookForm = '';
        if (s.cookOpen) {
            var groupOptions = '<option value="">Sin grupo familiar</option>';
            s.groups.forEach(function (g) {
                groupOptions += '<option value="' + g.id + '"' + (String(g.id) === String(s.selectedGroupId) ? ' selected' : '') + '>' + escapeHtml(g.name) + '</option>';
            });
            cookForm =
                '<div style="margin-top:10px;padding:12px;background:#f9fbfb;border-radius:6px;border:1px solid #dde3e8">' +
                '<div style="font-size:13px;font-weight:700;margin-bottom:8px">Registrar cocinada</div>' +
                '<div style="margin-bottom:8px">' +
                '<label style="font-size:12px;color:#697681;display:block;margin-bottom:3px">Porciones *</label>' +
                '<input type="number" min="1" max="999" class="form-control" data-cook-servings value="' + escapeHtml(s.servings) + '" style="max-width:100px">' +
                '</div>' +
                '<div style="margin-bottom:8px">' +
                '<label style="font-size:12px;color:#697681;display:block;margin-bottom:3px">Grupo familiar</label>' +
                '<select class="form-control" data-cook-group>' + groupOptions + '</select>' +
                '</div>' +
                '<div data-cook-deduct-row style="margin-bottom:10px;display:' + (s.selectedGroupId ? 'block' : 'none') + '">' +
                '<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">' +
                '<input type="checkbox" data-cook-deduct' + (s.deductStock ? ' checked' : '') + '> Descontar ingredientes de Mi cocina' +
                '</label>' +
                '</div>' +
                '<div style="display:flex;gap:8px">' +
                '<button type="button" class="btn-main btn-sm" data-cook-submit' + (s.cookLoading ? ' disabled' : '') + '>' +
                (s.cookLoading ? 'Guardando...' : 'Confirmar') + '</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-cook-cancel>Cancelar</button>' +
                '</div>' +
                '<div data-cook-msg style="display:none;font-size:12px;margin-top:6px;padding:5px 8px;border-radius:4px"></div>' +
                '</div>';
        }

        s.el.innerHTML =
            '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8">' +
            '<button type="button" data-fav-toggle ' +
            'style="border:1px solid ' + (s.isFavorited ? '#04ac85' : '#dde3e8') + ';background:' + favBtnBg + ';color:' + favBtnColor + ';' +
            'border-radius:50px;padding:7px 14px;font-size:13px;font-weight:700;cursor:pointer"' +
            (s.favLoading ? ' disabled' : '') + '>' +
            escapeHtml(favBtnLabel) + '</button>' +
            '<button type="button" data-cook-toggle ' +
            'style="border:1px solid #dde3e8;background:#fff;color:#24252a;border-radius:50px;padding:7px 14px;font-size:13px;font-weight:700;cursor:pointer">' +
            (s.cookOpen ? 'Cancelar cocinar' : 'Cocinar') + '</button>' +
            '</div>' +
            cookForm;

        bindEvents(s);
    }

    function showCookMsg(s, type, text) {
        var msgEl = s.el ? s.el.querySelector('[data-cook-msg]') : null;
        if (!msgEl) { return; }
        msgEl.textContent = text;
        msgEl.style.display = 'block';
        msgEl.style.background = type === 'ok' ? '#e7f7f2' : '#f7e7e7';
        msgEl.style.color      = type === 'ok' ? '#04ac85' : '#b33a3a';
        if (type === 'ok') { setTimeout(function () { if (msgEl) { msgEl.style.display = 'none'; } }, 4000); }
    }

    // ─── Events ──────────────────────────────────────────────────────────────────

    function bindEvents(s) {
        if (!s.el) { return; }

        var favBtn      = s.el.querySelector('[data-fav-toggle]');
        var cookToggle  = s.el.querySelector('[data-cook-toggle]');
        var cookSubmit  = s.el.querySelector('[data-cook-submit]');
        var cookCancel  = s.el.querySelector('[data-cook-cancel]');
        var cookGroup   = s.el.querySelector('[data-cook-group]');
        var cookDeduct  = s.el.querySelector('[data-cook-deduct]');
        var deductRow   = s.el.querySelector('[data-cook-deduct-row]');

        if (favBtn) {
            favBtn.addEventListener('click', function () {
                if (s.favLoading) { return; }
                s.favLoading = true;
                render(s);

                if (s.isFavorited) {
                    window.CCApi.request(endpoint('/recipes/' + s.recipeId + '/favorite'), { method: 'DELETE' })
                        .then(function () {
                            s.isFavorited = false;
                            s.favLoading  = false;
                            render(s);
                        })
                        .catch(function (err) {
                            s.favLoading = false;
                            var code = err && err.payload && err.payload.error && err.payload.error.code;
                            if (code === 'RECIPE_NOT_FAVORITED') { s.isFavorited = false; }
                            render(s);
                        });
                } else {
                    window.CCApi.request(endpoint('/recipes/' + s.recipeId + '/favorite'), { method: 'POST' })
                        .then(function () {
                            s.isFavorited = true;
                            s.favLoading  = false;
                            render(s);
                        })
                        .catch(function (err) {
                            s.favLoading = false;
                            var code = err && err.payload && err.payload.error && err.payload.error.code;
                            if (code === 'RECIPE_ALREADY_FAVORITED') { s.isFavorited = true; }
                            render(s);
                        });
                }
            });
        }

        if (cookToggle) {
            cookToggle.addEventListener('click', function () {
                s.cookOpen = !s.cookOpen;
                if (!s.cookOpen) { s.cookAttempt = null; }
                if (s.cookOpen && !s.groupsLoaded) { loadGroups(s); }
                render(s);
            });
        }

        if (cookGroup && deductRow) {
            cookGroup.addEventListener('change', function () {
                s.selectedGroupId = cookGroup.value ? parseInt(cookGroup.value, 10) : null;
                if (!s.selectedGroupId) { s.deductStock = false; }
                deductRow.style.display = cookGroup.value ? '' : 'none';
            });
        }

        if (cookDeduct) {
            cookDeduct.addEventListener('change', function () {
                s.deductStock = cookDeduct.checked;
            });
        }

        if (cookCancel) {
            cookCancel.addEventListener('click', function () {
                s.cookOpen = false;
                s.cookAttempt = null;
                render(s);
            });
        }

        if (cookSubmit) {
            cookSubmit.addEventListener('click', function () {
                if (s.cookLoading) { return; }
                var srvInput = s.el ? s.el.querySelector('[data-cook-servings]') : null;
                var grpSel   = s.el ? s.el.querySelector('[data-cook-group]') : null;
                var deductCk = s.el ? s.el.querySelector('[data-cook-deduct]') : null;

                var srv = parseInt(srvInput ? srvInput.value : s.servings, 10);
                if (!srv || srv < 1) {
                    showCookMsg(s, 'err', 'Ingresá una cantidad de porciones válida.');
                    return;
                }

                var body = { servings: srv };
                if (grpSel && grpSel.value) {
                    body.family_group_id = parseInt(grpSel.value, 10);
                    if (deductCk && deductCk.checked) { body.deduct_stock = true; }
                }

                var signature = JSON.stringify(body);
                if (!s.cookAttempt || s.cookAttempt.signature !== signature) {
                    s.cookAttempt = { signature: signature, key: createCookIdempotencyKey() };
                }
                body.idempotency_key = s.cookAttempt.key;

                s.servings = srv;
                s.selectedGroupId = body.family_group_id || null;
                s.deductStock = body.deduct_stock === true;
                s.cookLoading = true;
                render(s);

                window.CCApi.request(endpoint('/recipes/' + s.recipeId + '/cook'), {
                    method: 'POST',
                    body:   body,
                })
                    .then(function () {
                        s.cookLoading = false;
                        s.cookOpen    = false;
                        s.cookAttempt = null;
                        render(s);
                        // Show a brief success in the (now re-rendered) container
                        var ok = document.createElement('div');
                        ok.textContent = 'Receta registrada como cocinada.';
                        ok.style.cssText = 'font-size:12px;padding:5px 9px;border-radius:4px;margin-top:8px;background:#e7f7f2;color:#04ac85';
                        if (s.el) {
                            s.el.appendChild(ok);
                            setTimeout(function () { if (ok.parentNode) { ok.parentNode.removeChild(ok); } }, 4000);
                        }
                    })
                    .catch(function (err) {
                        s.cookLoading = false;
                        if (err && err.status) { s.cookAttempt = null; }
                        render(s);
                        showCookMsg(s, 'err', extractMsg(err));
                    });
            });
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    function loadGroups(s) {
        window.CCApi.request(endpoint('/family-groups?per_page=20'))
            .then(function (res) {
                s.groups        = res.data || [];
                s.groupsLoaded  = true;
                if (!s.selectedGroupId && s.groups.length) { s.selectedGroupId = s.groups[0].id; }
                if (s.cookOpen) { render(s); }
            })
            .catch(function () {
                s.groups        = [];
                s.groupsLoaded  = true;
            });
    }

    // ─── Public ──────────────────────────────────────────────────────────────────

    window.RecipeFavoritesActions = {
        mount: function (containerEl, recipeId, initialServings) {
            var s = {
                el:           containerEl,
                recipeId:     recipeId,
                servings:     initialServings || 1,
                isFavorited:  null,
                favLoading:   false,
                cookOpen:     false,
                cookLoading:  false,
                cookAttempt:  null,
                deductStock:  false,
                groups:       [],
                groupsLoaded: false,
                selectedGroupId: null,
            };
            render(s);
        },
    };

})(window, document);
