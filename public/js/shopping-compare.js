(function (window, document) {
    'use strict';

    function escapeHtml(v) {
        return (v === null || v === undefined ? '' : String(v))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmt(val) {
        var n = parseFloat(val);
        return isNaN(n) ? '-' : '$' + n.toFixed(2);
    }

    function coverageBadge(found, total) {
        if (!total) { return ''; }
        var pct = Math.round((found / total) * 100);
        var color = pct >= 90 ? '#2a7a2a' : pct >= 60 ? '#b35c00' : '#b33a3a';
        return '<span style="color:' + color + ';font-size:11px;font-weight:700">' + found + '/' + total + ' ítems (' + pct + '%)</span>';
    }

    function renderCompareResults(data) {
        var results = data.results || [];
        if (!results.length) {
            return '<p style="font-size:13px;color:#66746b;margin:0">No se encontraron precios en ningún supermercado para esta lista.</p>';
        }

        var itemsTotal = data.items_total || 0;

        // Find minimum total for highlighting best option
        var minTotal = null;
        results.forEach(function (r) {
            var t = parseFloat(r.total);
            if (!isNaN(t) && (minTotal === null || t < minTotal)) { minTotal = t; }
        });

        return results.map(function (r) {
            var isBest = minTotal !== null && parseFloat(r.total) === minTotal;
            var borderStyle = isBest ? 'border:2px solid #04ac85;' : 'border:1px solid #dde6df;';
            var headerBg = isBest ? 'background:#e7f7f2;' : 'background:#fafdfb;';
            var discount = parseFloat(r.promotions_discount);
            var hasDiscount = !isNaN(discount) && discount > 0;
            var branchName = r.branch ? (r.branch.name || r.branch.address || ('Sucursal #' + r.branch.id)) : null;

            return '<div style="' + borderStyle + 'border-radius:8px;margin-bottom:10px;overflow:hidden">' +
                '<div style="' + headerBg + 'padding:10px 12px;display:flex;align-items:center;justify-content:space-between;gap:8px">' +
                '<div>' +
                '<span style="font-size:13px;font-weight:900">' + escapeHtml(r.supermarket_name || ('Supermercado #' + r.supermarket_id)) + '</span>' +
                (branchName ? '<span style="font-size:11px;color:#66746b;display:block">' + escapeHtml(branchName) + '</span>' : '') +
                '</div>' +
                (isBest ? '<span style="background:#04ac85;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700">Mejor precio</span>' : '') +
                '</div>' +
                '<div style="padding:10px 12px">' +
                '<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">' +
                '<span style="color:#66746b">Cobertura</span>' +
                coverageBadge(r.items_found || 0, itemsTotal || (r.items_found + r.items_not_found)) +
                '</div>' +
                (hasDiscount
                    ? '<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">' +
                      '<span style="color:#66746b">Subtotal</span><span>' + fmt(r.subtotal) + '</span></div>' +
                      '<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;color:#2a7a2a">' +
                      '<span>Descuentos/promos</span><span>-' + fmt(r.promotions_discount) + '</span></div>'
                    : '') +
                '<div style="display:flex;justify-content:space-between;font-size:15px;font-weight:900;margin-top:4px">' +
                '<span>Total</span><span style="color:' + (isBest ? '#04ac85' : '#24252a') + '">' + fmt(r.total) + '</span>' +
                '</div>' +
                '</div>' +
                '</div>';
        }).join('');
    }

    function renderOptimizeResults(data) {
        if (!data) {
            return '<p style="font-size:13px;color:#66746b;margin:0">Sin resultado de optimización.</p>';
        }

        var strategy = data.strategy || '';
        var strategyLabel = strategy === 'split' ? 'Compra dividida' : strategy === 'cheapest_single' ? 'Un solo supermercado' : strategy;
        var routes = data.routes || [];
        var savings = parseFloat(data.savings);
        var hasSavings = !isNaN(savings) && savings > 0;

        var html = '';
        if (strategyLabel) {
            html += '<div style="margin-bottom:10px">' +
                '<span style="background:#e7f3ff;color:#1a5fb4;border-radius:999px;padding:3px 10px;font-size:12px;font-weight:700">' + escapeHtml(strategyLabel) + '</span>' +
                (hasSavings ? '<span style="font-size:12px;color:#2a7a2a;margin-left:8px;font-weight:700">Ahorro estimado: ' + fmt(savings) + '</span>' : '') +
                '</div>';
        }

        routes.forEach(function (route) {
            var items = route.items || [];
            html += '<div style="border:1px solid #dde6df;border-radius:6px;margin-bottom:8px;overflow:hidden">' +
                '<div style="background:#fafdfb;padding:8px 12px;display:flex;justify-content:space-between;align-items:center">' +
                '<span style="font-size:13px;font-weight:700">' + escapeHtml(route.supermarket_name || 'Supermercado') + '</span>' +
                '<span style="font-size:13px;font-weight:900">' + fmt(route.subtotal) + '</span>' +
                '</div>';
            if (items.length) {
                html += '<div style="padding:6px 12px">' +
                    items.map(function (item) {
                        return '<div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;border-bottom:1px solid #f0f0f0">' +
                            '<span>' + escapeHtml(item.item_name || item.product_name || ('Item #' + item.item_id)) + '</span>' +
                            '<span style="color:#66746b">' + fmt(item.price) + '</span>' +
                            '</div>';
                    }).join('') +
                    '</div>';
            }
            html += '</div>';
        });

        if (data.total !== null && data.total !== undefined) {
            html += '<div style="text-align:right;font-size:15px;font-weight:900;padding-top:6px">Total optimizado: <span style="color:#04ac85">' + fmt(data.total) + '</span></div>';
        }

        return html;
    }

    window.ShoppingCompare = {
        mount: function (containerEl, groupId, listId) {
            if (!containerEl || !groupId || !listId) { return; }

            var state = {
                groupId: groupId,
                listId: listId,
                compareData: null,
                optimizeData: null,
                compareLoading: false,
                optimizeLoading: false,
                optimizeVisible: false,
            };

            function basePath() {
                return '/api/v1/family-groups/' + encodeURIComponent(state.groupId) +
                    '/shopping-lists/' + encodeURIComponent(state.listId);
            }

            function errMsg(err) {
                return (err && err.payload && err.payload.error && err.payload.error.message)
                    ? err.payload.error.message
                    : (err && err.message) || 'Error inesperado.';
            }

            function render() {
                // Compare section
                var compareHtml = '<div style="margin-top:14px;padding-top:14px;border-top:1px solid #dde6df">' +
                    '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">' +
                    '<h4 style="margin:0;font-size:14px;font-weight:900">Comparar supermercados</h4>' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-compare-load>' +
                    (state.compareData ? 'Actualizar' : 'Comparar precios') +
                    '</button></div>';

                if (state.compareLoading) {
                    compareHtml += '<div style="font-size:13px;color:#66746b">Comparando precios...</div>';
                } else if (!state.compareData) {
                    compareHtml += '<p style="font-size:12px;color:#66746b;margin:0">Compara el costo total en Carrefour, ChangoMás, La Anónima y otras sucursales cercanas.</p>';
                } else {
                    compareHtml += renderCompareResults(state.compareData);
                }
                compareHtml += '</div>';

                // Optimize section — only show after compare loaded
                var optimizeHtml = '';
                if (state.compareData) {
                    optimizeHtml = '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #dde6df">' +
                        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">' +
                        '<h4 style="margin:0;font-size:14px;font-weight:900">Optimizar compra</h4>' +
                        '<button type="button" class="btn-secondary-web btn-sm" data-optimize-load>' +
                        (state.optimizeData ? 'Recalcular' : 'Optimizar') +
                        '</button></div>';

                    if (state.optimizeLoading) {
                        optimizeHtml += '<div style="font-size:13px;color:#66746b">Calculando ruta óptima...</div>';
                    } else if (!state.optimizeData) {
                        optimizeHtml += '<p style="font-size:12px;color:#66746b;margin:0">Sugiere en qué supermercado o combinación de supermercados comprar para minimizar el costo.</p>';
                    } else {
                        optimizeHtml += renderOptimizeResults(state.optimizeData);
                    }
                    optimizeHtml += '</div>';
                }

                containerEl.innerHTML = compareHtml + optimizeHtml;
            }

            function loadCompare() {
                state.compareLoading = true;
                render();
                window.CCApi.request(basePath() + '/compare-supermarkets')
                    .then(function (response) {
                        state.compareData = response.data || null;
                        state.compareLoading = false;
                        render();
                    })
                    .catch(function (err) {
                        state.compareLoading = false;
                        state.compareData = null;
                        containerEl.querySelector
                            ? null
                            : null;
                        // Re-render with error inline
                        state._compareError = errMsg(err);
                        renderWithError();
                    });
            }

            function loadOptimize() {
                state.optimizeLoading = true;
                render();
                window.CCApi.request(basePath() + '/optimize')
                    .then(function (response) {
                        state.optimizeData = response.data || null;
                        state.optimizeLoading = false;
                        render();
                    })
                    .catch(function (err) {
                        state.optimizeLoading = false;
                        state.optimizeData = null;
                        state._optimizeError = errMsg(err);
                        renderWithOptimizeError();
                    });
            }

            function renderWithError() {
                var msg = state._compareError || 'No se pudo comparar.';
                containerEl.innerHTML = '<div style="margin-top:14px;padding-top:14px;border-top:1px solid #dde6df">' +
                    '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">' +
                    '<h4 style="margin:0;font-size:14px;font-weight:900">Comparar supermercados</h4>' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-compare-load>Reintentar</button></div>' +
                    '<p style="font-size:13px;color:#b33a3a;margin:0">' + escapeHtml(msg) + '</p></div>';
            }

            function renderWithOptimizeError() {
                render();
                var errDiv = document.createElement('p');
                errDiv.style.cssText = 'font-size:13px;color:#b33a3a;margin:6px 0 0';
                errDiv.textContent = state._optimizeError || 'No se pudo optimizar.';
                var optSection = containerEl.querySelector('[data-optimize-load]');
                if (optSection && optSection.parentNode) {
                    optSection.parentNode.appendChild(errDiv);
                }
            }

            containerEl.addEventListener('click', function (event) {
                if (event.target.closest('[data-compare-load]')) {
                    state._compareError = null;
                    loadCompare();
                    return;
                }
                if (event.target.closest('[data-optimize-load]')) {
                    state._optimizeError = null;
                    loadOptimize();
                }
            });

            render();
        },
    };
})(window, document);
