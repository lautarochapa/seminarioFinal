(function (window, document) {
    'use strict';

    var state = {
        branches: [],
        selectedId: null,
        map: null,
        markers: [],
        promotionsRequestSeq: 0,
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined || v === '') { return ''; }
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function showMessage(root, type, msg) {
        var el = qs('[data-branches-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-branches-message]', root);
        if (!el) { return; }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function setGeoStatus(root, msg) {
        var el = qs('[data-branches-geo-status]', root);
        if (el) { el.textContent = msg; }
    }

    // ── Selectors ──────────────────────────────────────────────────────────────

    function loadSelectors(root) {
        Promise.all([
            window.CCApi.request(endpoint('/cities')),
            window.CCApi.request(endpoint('/supermarkets')),
        ]).then(function (results) {
            var cities  = results[0].data || [];
            var chains  = results[1].data || [];

            var cityOpts = cities.map(function (c) {
                return '<option value="' + c.id + '">' + escapeHtml(c.name) +
                    (c.province ? ' (' + escapeHtml(c.province) + ')' : '') + '</option>';
            }).join('');

            var chainOpts = chains.map(function (c) {
                return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
            }).join('');

            qs('[data-branches-filter-city]', root).innerHTML =
                '<option value="">Todas las ciudades</option>' + cityOpts;

            qs('[data-branches-filter-chain]', root).innerHTML =
                '<option value="">Todas las cadenas</option>' + chainOpts;
        }).catch(function () {});
    }

    // ── Map ────────────────────────────────────────────────────────────────────

    function initMap(root) {
        if (!window.L) { return; }
        if (state.map) { return; }
        var el = qs('[data-branches-map]', root);
        if (!el) { return; }
        state.map = window.L.map(el).setView([-34.6, -58.4], 12);
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(state.map);
    }

    function clearMarkers() {
        if (!state.map) { return; }
        state.markers.forEach(function (m) { state.map.removeLayer(m); });
        state.markers = [];
    }

    function placeMarkers(root, branches) {
        if (!state.map || !window.L) { return; }
        clearMarkers();
        var validBranches = branches.filter(function (b) {
            return b.latitude !== null && b.latitude !== undefined &&
                   b.longitude !== null && b.longitude !== undefined;
        });
        if (!validBranches.length) { return; }

        var latLngs = [];
        validBranches.forEach(function (b) {
            var lat = parseFloat(b.latitude);
            var lng = parseFloat(b.longitude);
            if (isNaN(lat) || isNaN(lng)) { return; }
            var marker = window.L.marker([lat, lng])
                .bindPopup('<strong>' + escapeHtml(b.name) + '</strong>' +
                    (b.chain ? '<br>' + escapeHtml(b.chain.name) : '') +
                    (b.address ? '<br><span style="color:#66746b">' + escapeHtml(b.address) + '</span>' : ''))
                .addTo(state.map);
            marker._branchId = b.id;
            marker.on('click', function () { showDetail(root, b.id); });
            state.markers.push(marker);
            latLngs.push([lat, lng]);
        });

        if (latLngs.length === 1) {
            state.map.setView(latLngs[0], 15);
        } else if (latLngs.length > 1) {
            state.map.fitBounds(latLngs, { padding: [20, 20] });
        }
    }

    // ── List ───────────────────────────────────────────────────────────────────

    function renderList(root, branches) {
        var el = qs('[data-branches-list]', root);
        qs('[data-branches-count]', root).textContent = branches.length + ' sucursal' + (branches.length !== 1 ? 'es' : '');

        if (!branches.length) {
            el.innerHTML = '<p class="muted" style="margin:12px 0">No se encontraron sucursales.</p>';
            return;
        }

        el.innerHTML = branches.map(function (b) {
            var distLabel = (b.distance_km !== undefined && b.distance_km !== null)
                ? ' <span style="color:var(--muted);font-size:12px">· ' + parseFloat(b.distance_km).toFixed(1) + ' km</span>'
                : '';
            var delivChip = b.delivery_available
                ? '<span style="background:#e7f7f2;color:#04ac85;border-radius:50px;padding:2px 7px;font-size:11px">Delivery</span> '
                : '';
            var pickChip = b.pickup_available
                ? '<span style="background:#e8f0fe;color:#2f80ed;border-radius:50px;padding:2px 7px;font-size:11px">Pickup</span>'
                : '';
            return '<div data-branch-card="' + b.id + '" style="border:1px solid var(--line);border-radius:8px;padding:12px;margin-bottom:8px;cursor:pointer;background:#fff">' +
                '<strong>' + escapeHtml(b.name) + '</strong>' + distLabel + '<br>' +
                '<span style="color:var(--muted);font-size:13px">' +
                (b.chain ? escapeHtml(b.chain.name) : '') +
                (b.city  ? ' · ' + escapeHtml(b.city.name)  : '') +
                '</span><br>' +
                '<span style="color:var(--muted);font-size:12px">' + escapeHtml(b.address) + '</span>' +
                (delivChip || pickChip ? '<div style="margin-top:6px">' + delivChip + pickChip + '</div>' : '') +
                '</div>';
        }).join('');
    }

    // ── Load branches ──────────────────────────────────────────────────────────

    function loadBranches(root) {
        clearMessage(root);
        var cityId  = qs('[data-branches-filter-city]',  root).value;
        var chainId = qs('[data-branches-filter-chain]', root).value;

        var params = '?' +
            (cityId  ? 'city_id='  + encodeURIComponent(cityId)  + '&' : '') +
            (chainId ? 'chain_id=' + encodeURIComponent(chainId) + '&' : '');

        showMessage(root, 'info', 'Cargando sucursales...');

        window.CCApi.request(endpoint('/supermarket-branches') + params)
            .then(function (r) {
                state.branches   = r.data || [];
                state.selectedId = null;
                clearMessage(root);
                renderList(root, state.branches);
                initMap(root);
                placeMarkers(root, state.branches);
                clearDetail(root);
            })
            .catch(function (err) {
                var msg = (err.payload && err.payload.error && err.payload.error.message) ||
                    'Error al cargar las sucursales.';
                showMessage(root, 'danger', msg);
            });
    }

    // ── Nearby ─────────────────────────────────────────────────────────────────

    function loadNearby(root, lat, lng) {
        var radiusEl = qs('[data-branches-radius]', root);
        var radius   = radiusEl ? parseFloat(radiusEl.value) : 5;
        if (isNaN(radius) || radius < 0.1 || radius > 500) { radius = 5; }

        setGeoStatus(root, 'Buscando sucursales cercanas...');

        window.CCApi.request(endpoint('/supermarket-branches/nearby?lat=' + lat + '&lng=' + lng + '&radius=' + radius))
            .then(function (r) {
                state.branches   = r.data || [];
                state.selectedId = null;
                setGeoStatus(root, state.branches.length
                    ? 'Se encontraron ' + state.branches.length + ' sucursal' + (state.branches.length !== 1 ? 'es' : '') + ' en ' + radius + ' km.'
                    : 'No se encontraron sucursales en un radio de ' + radius + ' km.');
                clearMessage(root);
                renderList(root, state.branches);
                initMap(root);
                placeMarkers(root, state.branches);
                clearDetail(root);
            })
            .catch(function (err) {
                var msg = (err.payload && err.payload.error && err.payload.error.message) ||
                    'Error al buscar sucursales cercanas.';
                setGeoStatus(root, msg);
            });
    }

    function requestGeolocation(root) {
        if (!navigator.geolocation) {
            setGeoStatus(root, 'Tu navegador no soporta geolocalización.');
            return;
        }
        setGeoStatus(root, 'Solicitando ubicación...');
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                loadNearby(root, pos.coords.latitude, pos.coords.longitude);
            },
            function (err) {
                var msgs = {
                    1: 'Permiso de ubicación denegado.',
                    2: 'Ubicación no disponible.',
                    3: 'Tiempo de espera agotado.',
                };
                setGeoStatus(root, msgs[err.code] || 'Error al obtener la ubicación.');
            },
            { timeout: 10000, maximumAge: 60000 }
        );
    }

    // ── Detail ─────────────────────────────────────────────────────────────────

    function clearDetail(root) {
        var el = qs('[data-branches-detail]', root);
        if (el) { el.innerHTML = ''; }
    }

    function showDetail(root, branchId) {
        state.selectedId = branchId;

        var cards = root.querySelectorAll('[data-branch-card]');
        cards.forEach(function (c) {
            var selected = parseInt(c.getAttribute('data-branch-card'), 10) === branchId;
            c.style.border = selected ? '2px solid var(--green, #04ac85)' : '1px solid var(--line)';
        });

        window.CCApi.request(endpoint('/supermarket-branches/' + branchId))
            .then(function (r) {
                var b = r.data || r;
                renderDetail(root, b);

                if (b.latitude && b.longitude && state.map) {
                    state.map.setView([parseFloat(b.latitude), parseFloat(b.longitude)], 16);
                    var marker = state.markers.find(function (m) { return m._branchId === b.id; });
                    if (marker) { marker.openPopup(); }
                }
            })
            .catch(function () {
                var el = qs('[data-branches-detail]', root);
                if (el) { el.innerHTML = '<p class="muted">Error al cargar el detalle.</p>'; }
            });
    }

    function renderDetail(root, b) {
        var el = qs('[data-branches-detail]', root);
        if (!el) { return; }

        var delivChip = b.delivery_available
            ? '<span style="background:#e7f7f2;color:#04ac85;border-radius:50px;padding:3px 10px;font-size:12px;font-weight:700">Delivery</span> '
            : '<span style="background:#f5f5f5;color:#999;border-radius:50px;padding:3px 10px;font-size:12px">Sin delivery</span> ';
        var pickChip = b.pickup_available
            ? '<span style="background:#e8f0fe;color:#2f80ed;border-radius:50px;padding:3px 10px;font-size:12px;font-weight:700">Pickup</span>'
            : '<span style="background:#f5f5f5;color:#999;border-radius:50px;padding:3px 10px;font-size:12px">Sin pickup</span>';

        var hasCoords = b.latitude !== null && b.latitude !== undefined &&
                        b.longitude !== null && b.longitude !== undefined;
        var routeLink = hasCoords
            ? '<a href="https://www.openstreetmap.org/?mlat=' + b.latitude +
              '&mlon=' + b.longitude + '&zoom=16" target="_blank" rel="noopener" ' +
              'style="display:inline-block;margin-top:10px;color:#2f80ed;font-size:13px">📍 Cómo llegar</a>'
            : '';

        var hoursHtml = b.opening_hours
            ? '<div style="margin-top:10px"><strong style="font-size:13px">Horarios</strong>' +
              '<pre style="margin:6px 0 0;white-space:pre-wrap;font-family:inherit;font-size:13px;color:var(--muted)">' +
              escapeHtml(b.opening_hours) + '</pre></div>'
            : '';

        el.innerHTML =
            '<div style="border:1px solid var(--line);border-radius:8px;padding:14px;background:#fff">' +
            '<strong style="font-size:16px">' + escapeHtml(b.name) + '</strong><br>' +
            (b.chain ? '<span style="color:var(--muted);font-size:13px">' + escapeHtml(b.chain.name) + '</span><br>' : '') +
            (b.city  ? '<span style="color:var(--muted);font-size:13px">' + escapeHtml(b.city.name)  + '</span><br>' : '') +
            '<p style="margin:8px 0;font-size:14px">' + escapeHtml(b.address) + '</p>' +
            '<div style="display:flex;gap:6px;flex-wrap:wrap">' + delivChip + pickChip + '</div>' +
            hoursHtml +
            routeLink +
            '<h3 style="font-size:13px;font-weight:900;margin:16px 0 6px">Promociones vigentes</h3>' +
            '<div data-branch-promotions-list><p class="muted" style="font-size:13px">Cargando promociones...</p></div>' +
            '<h3 style="font-size:13px;font-weight:900;margin:16px 0 6px">Productos disponibles</h3>' +
            '<div data-branch-products-list><p class="muted" style="font-size:13px">Cargando productos...</p></div>' +
            '</div>';
        loadBranchPromotions(root, b.id);
        loadBranchProducts(root, b.id);
    }

    function promotionTypeLabel(type) {
        var labels = {
            percentage: 'Porcentaje',
            fixed_amount: 'Monto fijo',
            buy_x_pay_y: '2x1 / Buy X Pay Y',
            payment_method: 'Metodo de pago',
            day_discount: 'Descuento por dia',
        };
        return labels[type] || 'Promocion';
    }

    function promotionDayLabel(day) {
        var days = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
        return day !== null && day !== undefined && days[day] ? days[day] : '';
    }

    function formatPromotionDate(value) {
        if (!value) { return '-'; }
        var date = new Date(value);
        if (isNaN(date.getTime())) { return value; }
        return date.toLocaleDateString('es-AR', { year: 'numeric', month: '2-digit', day: '2-digit' });
    }

    function promotionBenefit(row) {
        var type = promotionTypeLabel(row.discount_type);
        var value = row.discount_value !== null && row.discount_value !== undefined ? row.discount_value : null;
        if (row.discount_type === 'percentage' && value !== null) {
            return type + ' ' + value + '%';
        }
        if (row.discount_type === 'fixed_amount' && value !== null) {
            return type + ' ARS ' + value;
        }
        if (row.discount_type === 'day_discount') {
            return type + (promotionDayLabel(row.day_of_week) ? ' - ' + promotionDayLabel(row.day_of_week) : '');
        }
        if (row.discount_type === 'payment_method') {
            return type;
        }
        if (row.discount_type === 'buy_x_pay_y') {
            return type + (value !== null ? ' - valor ' + value : '');
        }
        return type + (value !== null ? ' ' + value : '');
    }

    function loadBranchPromotions(root, branchId) {
        var target = qs('[data-branch-promotions-list]', root);
        if (!target) { return; }

        var seq = ++state.promotionsRequestSeq;
        target.innerHTML = '<p class="muted" style="font-size:13px">Cargando promociones...</p>';

        window.CCApi.request(endpoint('/supermarket-branches/' + encodeURIComponent(branchId) + '/promotions'))
            .then(function (r) {
                if (seq !== state.promotionsRequestSeq) { return; }
                var rows = r.data || [];
                if (!rows.length) {
                    target.innerHTML = '<p class="muted" style="font-size:13px">No hay promociones vigentes para esta sucursal.</p>';
                    return;
                }
                target.innerHTML = rows.map(function (row) {
                    var scope = row.supermarket_branch_id ? 'Sucursal' : 'Cadena';
                    var dateLabel = formatPromotionDate(row.valid_from) + ' a ' + formatPromotionDate(row.valid_to);
                    var payment = row.requires_payment_method ? '<br><span class="muted">Requiere metodo de pago</span>' : '';
                    return '<div class="table-line">' +
                        '<span><strong>' + escapeHtml(row.name) + '</strong><br>' +
                        '<span class="muted">' + escapeHtml(scope) + ' - ' + escapeHtml(dateLabel) + '</span>' + payment + '</span>' +
                        '<strong>' + escapeHtml(promotionBenefit(row)) + '</strong>' +
                        '</div>';
                }).join('');
            })
            .catch(function () {
                if (seq !== state.promotionsRequestSeq) { return; }
                target.innerHTML = '<p class="muted" style="font-size:13px">No se pudieron cargar las promociones.</p>';
            });
    }

    function loadBranchProducts(root, branchId) {
        var target = qs('[data-branch-products-list]', root);
        if (!target) { return; }

        window.CCApi.request(endpoint('/supermarket-branches/' + encodeURIComponent(branchId) + '/products?per_page=12'))
            .then(function (r) {
                var rows = r.data || [];
                if (!rows.length) {
                    target.innerHTML = '<p class="muted" style="font-size:13px">Esta sucursal no tiene productos mapeados.</p>';
                    return;
                }
                target.innerHTML = rows.map(function (row) {
                    var product = row.product || {};
                    var price = row.current_price
                        ? escapeHtml(row.current_price.currency || 'ARS') + ' ' + escapeHtml(row.current_price.price)
                        : 'Sin precio';
                    return '<div class="table-line">' +
                        '<span>' + escapeHtml(product.name || ('Producto #' + row.product_id)) + '</span>' +
                        '<strong>' + price + '</strong>' +
                        '</div>';
                }).join('');
            })
            .catch(function () {
                target.innerHTML = '<p class="muted" style="font-size:13px">No se pudieron cargar los productos.</p>';
            });
    }

    // ── Bind ───────────────────────────────────────────────────────────────────

    function bind(root) {
        var loadBtn = qs('[data-branches-load]', root);
        if (loadBtn) {
            loadBtn.addEventListener('click', function () { loadBranches(root); });
        }

        var nearbyBtn = qs('[data-branches-nearby-btn]', root);
        if (nearbyBtn) {
            nearbyBtn.addEventListener('click', function () { requestGeolocation(root); });
        }

        root.addEventListener('click', function (e) {
            var card = e.target.closest('[data-branch-card]');
            if (card) {
                var id = parseInt(card.getAttribute('data-branch-card'), 10);
                showDetail(root, id);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-branches]');
        if (!root || !window.CCApi) { return; }
        bind(root);
        loadSelectors(root);
        initMap(root);
    });
})(window, document);
