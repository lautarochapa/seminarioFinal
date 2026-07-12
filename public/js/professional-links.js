(function (window, document) {
    'use strict';

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function text(value) {
        return value === null || value === undefined || value === '' ? '-' : String(value);
    }

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-professional-links-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-professional-links-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = '';
        alert.className = 'alert';
        alert.style.display = 'none';
    }

    function formatDateTime(value) {
        if (!value) {
            return '-';
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('es-AR');
    }

    function setLoading(form, loading) {
        var submit = qs('[type="submit"]', form);
        if (!submit) {
            return;
        }

        if (!submit.dataset.originalText) {
            submit.dataset.originalText = submit.textContent;
        }

        submit.disabled = loading;
        submit.textContent = loading ? 'Guardando...' : submit.dataset.originalText;
    }

    function checked(form, name) {
        var input = qs('[name="' + name + '"]', form);
        return !!(input && input.checked);
    }

    function valueOf(form, name) {
        var input = qs('[name="' + name + '"]', form);
        return input ? input.value : '';
    }

    function formData(form) {
        return {
            professional_user_id: Number(valueOf(form, 'professional_user_id')),
            can_view_profile: checked(form, 'can_view_profile'),
            can_view_stock: checked(form, 'can_view_stock'),
            can_view_meal_plans: checked(form, 'can_view_meal_plans'),
            can_edit_meal_plans: checked(form, 'can_edit_meal_plans'),
            can_view_reports: checked(form, 'can_view_reports')
        };
    }

    function updateData(form) {
        return {
            can_view_profile: checked(form, 'can_view_profile'),
            can_view_stock: checked(form, 'can_view_stock'),
            can_view_meal_plans: checked(form, 'can_view_meal_plans'),
            can_edit_meal_plans: checked(form, 'can_edit_meal_plans'),
            can_view_reports: checked(form, 'can_view_reports')
        };
    }

    function renderLinks(root, links) {
        var body = qs('[data-professional-links-body]', root);
        if (!body) {
            return;
        }

        if (!links.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">Todavia no hay profesionales vinculados.</td></tr>';
            return;
        }

        body.innerHTML = links.map(function (link) {
            var permissions = [];
            if (link.can_view_profile) { permissions.push('Perfil'); }
            if (link.can_view_stock) { permissions.push('Stock'); }
            if (link.can_view_meal_plans) { permissions.push('Planes'); }
            if (link.can_edit_meal_plans) { permissions.push('Edicion'); }
            if (link.can_view_reports) { permissions.push('Reportes'); }

            return '<tr>' +
                '<td><strong>ID profesional ' + escapeHtml(link.professional_user_id) + '</strong></td>' +
                '<td>' + escapeHtml(permissions.join(', ') || 'Sin permisos') + '</td>' +
                '<td>' + escapeHtml(link.status === 'active' ? 'Activo' : link.status) + '</td>' +
                '<td>' + escapeHtml(formatDateTime(link.granted_at)) + '</td>' +
                '<td>' + escapeHtml(formatDateTime(link.revoked_at)) + '</td>' +
                '<td>' +
                    '<button type="button" class="btn-main btn-sm" data-professional-link-edit="' + link.id + '">Editar</button> ' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-professional-link-delete="' + link.id + '">Revocar</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function fillForm(form, link) {
        form.dataset.editingId = link ? String(link.id) : '';
        qs('[name="professional_user_id"]', form).value = link ? link.professional_user_id : '';
        qs('[name="professional_user_id"]', form).disabled = !!link;
        qsa('input[type="checkbox"]', form).forEach(function (input) {
            input.checked = !!(link && link[input.name]);
        });
    }

    function loadLinks(root) {
        return window.CCApi.request('/professional-links')
            .then(function (response) {
                var links = response.data || [];
                root.__professionalLinks = links;
                renderLinks(root, links);
                var count = qs('[data-professional-links-count]', root);
                if (count) {
                    count.textContent = String(links.length);
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-professional-links]');
        if (!root || !window.CCApi) {
            return;
        }

        var form = qs('[data-professional-links-form]', root);
        var reset = qs('[data-professional-links-reset]', root);

        loadLinks(root).catch(function (error) {
            showMessage(root, 'warning', error.message || 'No se pudieron cargar los vinculos profesionales.');
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);
            setLoading(form, true);

            var editingId = form.dataset.editingId;
            var endpoint = '/professional-links' + (editingId ? '/' + editingId : '');
            var method = editingId ? 'PATCH' : 'POST';
            var payload = editingId ? updateData(form) : formData(form);

            window.CCApi.request(endpoint, { method: method, body: payload })
                .then(function () {
                    showMessage(root, 'success', editingId ? 'Permisos profesionales actualizados correctamente.' : 'Profesional vinculado correctamente.');
                    form.reset();
                    fillForm(form, null);
                    return loadLinks(root);
                })
                .catch(function (error) {
                    showMessage(root, 'danger', error.message || 'No se pudo guardar el vinculo profesional.');
                })
                .finally(function () {
                    setLoading(form, false);
                });
        });

        reset.addEventListener('click', function () {
            form.reset();
            fillForm(form, null);
            clearMessage(root);
        });

        root.addEventListener('click', function (event) {
            var editId = event.target.getAttribute('data-professional-link-edit');
            var deleteId = event.target.getAttribute('data-professional-link-delete');

            if (editId) {
                var link = (root.__professionalLinks || []).find(function (item) {
                    return String(item.id) === String(editId);
                });

                if (!link) {
                    return;
                }

                fillForm(form, link);
                showMessage(root, 'info', 'Editando permisos del profesional seleccionado.');
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            if (deleteId) {
                clearMessage(root);
                window.CCApi.request('/professional-links/' + deleteId, { method: 'DELETE' })
                    .then(function () {
                        showMessage(root, 'success', 'Acceso profesional revocado correctamente.');
                        if (String(form.dataset.editingId || '') === String(deleteId)) {
                            form.reset();
                            fillForm(form, null);
                        }
                        return loadLinks(root);
                    })
                    .catch(function (error) {
                        showMessage(root, 'danger', error.message || 'No se pudo revocar el acceso profesional.');
                    });
            }
        });

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var first = form.querySelector('input:not([type=hidden]),select');
                if (first) { first.focus(); }
            });
        }

        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var list = qs('[data-professional-links-list]', root);
                if (list) { list.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        }
    });
})(window, document);
