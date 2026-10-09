(function (window, document) {
    'use strict';

    function escapeHtml(v) {
        return (v === null || v === undefined ? '' : String(v))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function currencyCode(currency) {
        return currency === null || currency === undefined ? '' : String(currency).trim().toUpperCase();
    }

    function fmt(val, currency) {
        var n = parseFloat(val);
        var code = currencyCode(currency);
        return isNaN(n) ? '-' : '$' + n.toFixed(2) + (code ? ' ' + escapeHtml(code) : '');
    }

    function coverageBadge(found, total) {
        if (!total) { return ''; }
        var pct = Math.round((found / total) * 100);
        var color = pct >= 90 ? '#2a7a2a' : pct >= 60 ? '#b35c00' : '#b33a3a';
        return '<span style="color:' + color + ';font-size:11px;font-weight:700">' + found + '/' + total + ' ítems (' + pct + '%)</span>';
    }

    function hasCompleteCoverage(result, itemsTotal) {
        var found = Number(result.items_found);
        var missing = Number(result.items_not_found);
        return found > 0 && result.items_not_found !== null && result.items_not_found !== undefined &&
            missing === 0 && (!itemsTotal || found >= Number(itemsTotal));
    }

    function renderCompareResults(data) {
        var canonical = Array.isArray(data.branches);
        var results = canonical ? data.branches.map(function (row) {
            return {
                branch: row.branch,
                supermarket_name: row.branch && row.branch.chain,
                supermarket_id: row.branch && row.branch.id,
                total: row.total,
                currency: row.currency,
                items_found: Number(row.found_count) || 0,
                items_not_found: row.missing_count === null || row.missing_count === undefined ? null : Number(row.missing_count),
            };
        }) : (data.results || []);
        if (!results.length) {
            return '<p style="font-size:13px;color:#66746b;margin:0">No se encontraron precios en ningún supermercado para esta lista.</p>';
        }

        var itemsTotal = data.items_total || 0;

        // Complete quotes must share a currency to have a comparable best price.
        var completeQuotes = results.filter(function (r) { return hasCompleteCoverage(r, itemsTotal); });
        var currencies = completeQuotes.map(function (r) { return currencyCode(r.currency); });
        var comparable = currencies.length > 0 && (!canonical || currencies[0] !== '') &&
            currencies.every(function (currency) { return currency === currencies[0]; });
        var minTotal = null;
        completeQuotes.forEach(function (r) {
            var t = parseFloat(r.total);
            if (comparable && !isNaN(t) && (minTotal === null || t < minTotal)) { minTotal = t; }
        });

        return results.map(function (r) {
            var isBest = hasCompleteCoverage(r, itemsTotal) && minTotal !== null && parseFloat(r.total) === minTotal;
            var borderStyle = isBest ? 'border:2px solid #04ac85;' : 'border:1px solid #dde6df;';
            var headerBg = isBest ? 'background:#e7f7f2;' : 'background:#fafdfb;';
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
                '<div style="display:flex;justify-content:space-between;font-size:15px;font-weight:900;margin-top:4px">' +
                '<span>Total</span><span style="color:' + (isBest ? '#04ac85' : '#24252a') + '">' + fmt(r.total, r.currency) + '</span>' +
                '</div>' +
                '</div>' +
                '</div>';
        }).join('');
    }

    function normalizeOptimization(data) {
        if (!data.combined || Array.isArray(data.routes)) { return data; }

        // The API supplies item and overall totals, but no branch subtotal.
        var routes = [];
        (data.combined.items || []).forEach(function (item) {
            var branch = item.branch || {};
            var route = routes.find(function (entry) { return entry.branch_id === branch.id; });
            if (!route) {
                route = {
                    branch_id: branch.id,
                    supermarket_name: [branch.chain, branch.name].filter(Boolean).join(' · ') || ('Sucursal #' + branch.id),
                    items: [],
                };
                routes.push(route);
            }
            route.items.push({
                item_id: item.item_id,
                product_name: item.product && item.product.name,
                price: item.total,
                currency: item.currency,
            });
        });

        return {
            strategy: routes.length > 1 ? 'split' : (routes.length ? 'cheapest_single' : ''),
            routes: routes,
            savings: data.estimated_savings,
            total: data.combined.total,
            currency: data.combined.currency,
            pricedItemsOnly: true,
        };
    }

    function renderOptimizeResults(data) {
        if (!data) {
            return '<p style="font-size:13px;color:#66746b;margin:0">Sin resultado de optimización.</p>';
        }

        data = normalizeOptimization(data);

        var strategy = data.strategy || '';
        var strategyLabel = strategy === 'split' ? 'Compra dividida' : strategy === 'cheapest_single' ? 'Un solo supermercado' : strategy;
        var routes = data.routes || [];
        var savings = parseFloat(data.savings);
        var hasSavings = !isNaN(savings) && savings > 0;

        var html = '';
        if (data.pricedItemsOnly) {
            html += '<p style="font-size:12px;color:#66746b;margin:0 0 10px">' +
                (routes.length
                    ? 'Incluye únicamente artículos con precio. Revisá la cobertura de cada sucursal en la comparación.'
                    : 'No hay artículos con precio para optimizar.') + '</p>';
            if (routes.length && data.total === null) {
                html += '<p style="font-size:12px;color:#b35c00;margin:0 0 10px">No hay un total comparable para estos artículos.</p>';
            }
        }
        if (strategyLabel) {
            html += '<div style="margin-bottom:10px">' +
                '<span style="background:#e7f3ff;color:#1a5fb4;border-radius:999px;padding:3px 10px;font-size:12px;font-weight:700">' + escapeHtml(strategyLabel) + '</span>' +
                (hasSavings ? '<span style="font-size:12px;color:#2a7a2a;margin-left:8px;font-weight:700">Ahorro estimado: ' + fmt(savings, data.currency) + '</span>' : '') +
                '</div>';
        }

        routes.forEach(function (route) {
            var items = route.items || [];
            html += '<div style="border:1px solid #dde6df;border-radius:6px;margin-bottom:8px;overflow:hidden">' +
                '<div style="background:#fafdfb;padding:8px 12px;display:flex;justify-content:space-between;align-items:center">' +
                '<span style="font-size:13px;font-weight:700">' + escapeHtml(route.supermarket_name || 'Supermercado') + '</span>' +
                (route.subtotal !== null && route.subtotal !== undefined
                    ? '<span style="font-size:13px;font-weight:900">' + fmt(route.subtotal, route.currency) + '</span>' : '') +
                '</div>';
            if (items.length) {
                html += '<div style="padding:6px 12px">' +
                    items.map(function (item) {
                        return '<div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;border-bottom:1px solid #f0f0f0">' +
                            '<span>' + escapeHtml(item.item_name || item.product_name || ('Item #' + item.item_id)) + '</span>' +
                            '<span style="color:#66746b">' + fmt(item.price, item.currency) + '</span>' +
                            '</div>';
                    }).join('') +
                    '</div>';
            }
            html += '</div>';
        });

        if (data.total !== null && data.total !== undefined && (!data.pricedItemsOnly || routes.length)) {
            var totalLabel = data.pricedItemsOnly ? 'Total de artículos con precio' : 'Total optimizado';
            html += '<div style="text-align:right;font-size:15px;font-weight:900;padding-top:6px">' + totalLabel + ': <span style="color:#04ac85">' + fmt(data.total, data.currency) + '</span></div>';
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
