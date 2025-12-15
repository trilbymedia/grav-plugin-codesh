/**
 * CodeSh Grammar List Field
 */
(function() {
    'use strict';

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get the admin base URL from the current page location
     */
    function getAdminBaseUrl() {
        // Current URL is like /grav-helios/admin/plugins/codesh
        // We need to extract /grav-helios/admin
        const path = window.location.pathname;
        const adminMatch = path.match(/^(.*\/admin)\//);
        if (adminMatch) {
            return adminMatch[1];
        }
        // Fallback: assume /admin
        return '/admin';
    }

    /**
     * Grammar List Field Controller
     */
    class GrammarListField {
        constructor(element) {
            this.element = element;
            // Derive endpoint from current URL instead of data attribute
            this.endpoint = getAdminBaseUrl() + '/codesh-themes/grammars';
            this.nonce = element.dataset.nonce;
            this.deleteTarget = null;

            this.customList = element.querySelector('[data-codeshgrammarlist-custom]');
            this.builtinList = element.querySelector('[data-codeshgrammarlist-builtin]');
            this.customCount = element.querySelector('[data-codeshgrammarlist-custom-count]');
            this.builtinCount = element.querySelector('[data-codeshgrammarlist-builtin-count]');
            this.emptyState = element.querySelector('[data-codeshgrammarlist-empty]');
            this.modal = element.querySelector('[data-codeshgrammarlist-modal]');
            this.deleteNameEl = element.querySelector('[data-codeshgrammarlist-delete-name]');
            this.importInput = element.querySelector('[data-codeshgrammarlist-import]');

            this.init();
        }

        init() {
            this.loadGrammars();
            this.bindEvents();
        }

        bindEvents() {
            // Import file
            if (this.importInput) {
                this.importInput.addEventListener('change', (e) => this.handleImport(e));
            }

            // Delete buttons (delegated)
            this.element.addEventListener('click', (e) => {
                const deleteBtn = e.target.closest('[data-delete-grammar]');
                if (deleteBtn) {
                    e.preventDefault();
                    this.showDeleteModal(deleteBtn.dataset.deleteGrammar);
                }
            });

            // Modal close
            if (this.modal) {
                this.modal.querySelector('[data-codeshgrammarlist-modal-close]')
                    .addEventListener('click', () => this.hideModal());
                this.modal.querySelector('[data-codeshgrammarlist-modal-cancel]')
                    .addEventListener('click', () => this.hideModal());
                this.modal.querySelector('[data-codeshgrammarlist-modal-confirm]')
                    .addEventListener('click', () => this.confirmDelete());

                // Close on backdrop click
                this.modal.addEventListener('click', (e) => {
                    if (e.target === this.modal) {
                        this.hideModal();
                    }
                });
            }

            // Escape key to close modal
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal && this.modal.style.display !== 'none') {
                    this.hideModal();
                }
            });
        }

        async loadGrammars() {
            try {
                const response = await fetch(this.endpoint + '/list', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Grav-Nonce': this.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to load grammars');
                }

                const data = await response.json();

                // Render both sections
                this.renderGrammars(data.custom || [], this.customList, true);
                this.renderGrammars(data.builtin || [], this.builtinList, false);

                // Update counts
                if (this.customCount) {
                    this.customCount.textContent = (data.custom || []).length;
                }
                if (this.builtinCount) {
                    this.builtinCount.textContent = (data.builtin || []).length;
                }

                // Show/hide empty state for custom grammars
                if (data.custom && data.custom.length > 0) {
                    this.customList.style.display = '';
                    this.emptyState.style.display = 'none';
                } else {
                    this.customList.style.display = 'none';
                    this.emptyState.style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading grammars:', error);
                this.customList.innerHTML = '<li class="codeshgrammarlist-field__loading">Failed to load grammars</li>';
                this.builtinList.innerHTML = '';
            }
        }

        renderGrammars(grammars, container, allowDelete) {
            if (!container) return;

            if (grammars.length === 0) {
                container.innerHTML = '';
                return;
            }

            container.innerHTML = grammars.map(grammar => {
                // Build aliases string from fileTypes (excluding the slug itself)
                const aliases = (grammar.fileTypes || [])
                    .filter(ft => ft !== grammar.slug && ft.length <= 10)
                    .slice(0, 3);
                const aliasStr = aliases.length > 0
                    ? ` <span class="codeshgrammarlist-field__aliases">(${aliases.join(', ')})</span>`
                    : '';

                const deleteBtn = allowDelete
                    ? `<button type="button" class="codeshgrammarlist-field__delete-btn" data-delete-grammar="${escapeHtml(grammar.slug)}" title="Delete"><i class="fa fa-trash"></i></button>`
                    : '';

                return `<li><code>${escapeHtml(grammar.slug)}</code>${aliasStr}${deleteBtn}</li>`;
            }).join('');
        }

        async handleImport(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('grammar_file', file);

            try {
                const response = await fetch(this.endpoint + '/import', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Grav-Nonce': this.nonce
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('Grammar imported successfully: ' + data.slug);
                    this.loadGrammars();
                } else {
                    alert('Import failed: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Import error:', error);
                alert('Import failed: ' + error.message);
            }

            // Reset file input
            e.target.value = '';
        }

        showDeleteModal(slug) {
            this.deleteTarget = slug;
            this.deleteNameEl.textContent = slug;
            this.modal.style.display = 'flex';
        }

        hideModal() {
            this.deleteTarget = null;
            this.modal.style.display = 'none';
        }

        async confirmDelete() {
            if (!this.deleteTarget) return;

            try {
                const response = await fetch(this.endpoint + '/delete/' + this.deleteTarget, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Grav-Nonce': this.nonce
                    }
                });

                const data = await response.json();

                if (data.success) {
                    this.hideModal();
                    this.loadGrammars();
                } else {
                    alert('Delete failed: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('Delete failed: ' + error.message);
            }
        }
    }

    /**
     * Initialize all grammar list fields on page
     */
    function initFields() {
        document.querySelectorAll('[data-grav-codeshgrammarlist-field]').forEach(element => {
            if (!element._grammarListField) {
                element._grammarListField = new GrammarListField(element);
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFields);
    } else {
        initFields();
    }

    // Re-initialize on AJAX content load (for admin panel)
    document.addEventListener('grav-admin-after-refresh', initFields);
})();
