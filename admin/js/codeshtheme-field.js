/**
 * CodeSh Theme Picker Field
 */
(function() {
    'use strict';

    const ESCAPE_MAP = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ESCAPE_MAP[char] || char);
    }

    function buildUrl(endpoint) {
        try {
            return new URL(endpoint, window.location.origin);
        } catch (err) {
            const anchor = document.createElement('a');
            anchor.href = endpoint;
            return new URL(anchor.href);
        }
    }

    /**
     * API class for theme operations
     */
    class CodeshThemeAPI {
        constructor(endpoint, nonce) {
            this.endpoint = endpoint;
            this.nonce = nonce;
            this.themes = null;
        }

        async getThemes() {
            if (this.themes) {
                return this.themes;
            }

            const url = buildUrl(this.endpoint + '/list');
            const response = await this.request(url, 'GET');

            this.themes = {
                builtin: response.builtin || [],
                custom: response.custom || []
            };

            return this.themes;
        }

        async deleteTheme(name) {
            const url = buildUrl(this.endpoint + '/' + encodeURIComponent(name));
            const response = await this.request(url, 'DELETE');

            // Invalidate cache
            this.themes = null;

            return response;
        }

        async importTheme(file) {
            const url = buildUrl(this.endpoint + '/import');

            const formData = new FormData();
            formData.append('theme_file', file);
            formData.append('admin-nonce', this.nonce);

            const options = {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            };

            const response = await fetch(url.toString(), options);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || `Request failed with status ${response.status}`);
            }

            // Invalidate cache
            this.themes = null;

            return response.json();
        }

        async request(url, method = 'GET') {
            const options = {
                method,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (this.nonce) {
                options.headers['X-Grav-Nonce'] = this.nonce;
            }

            const response = await fetch(url.toString(), options);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || `Request failed with status ${response.status}`);
            }

            return response.json();
        }

        clearCache() {
            this.themes = null;
        }
    }

    /**
     * Modal class for theme selection
     */
    class CodeshThemeModal {
        constructor(api) {
            this.api = api;
            this.activeField = null;
            this.themes = { builtin: [], custom: [] };
            this.searchQuery = '';
            this.filterVariant = 'all'; // 'all', 'dark', 'light'
            this.debounceHandle = null;
            this.deleteConfirmTheme = null;
            this._build();
        }

        static getInstance(endpoint, nonce) {
            if (!CodeshThemeModal.instance) {
                CodeshThemeModal.instance = new CodeshThemeModal(new CodeshThemeAPI(endpoint, nonce));
            }
            return CodeshThemeModal.instance;
        }

        _build() {
            this.element = document.createElement('div');
            this.element.className = 'codeshtheme-modal';
            this.element.innerHTML = `
                <div class="codeshtheme-modal__overlay" data-codeshtheme-overlay></div>
                <div class="codeshtheme-modal__dialog" role="dialog" aria-modal="true" aria-label="Select theme">
                    <div class="codeshtheme-modal__header">
                        <h2>Select Theme</h2>
                        <div class="codeshtheme-modal__header-actions">
                            <button type="button" class="codeshtheme-modal__import-btn" data-codeshtheme-import>
                                <i class="fa fa-upload"></i> Import
                            </button>
                            <button type="button" class="codeshtheme-modal__close" aria-label="Close">&times;</button>
                        </div>
                    </div>
                    <div class="codeshtheme-modal__controls">
                        <input type="search" class="codeshtheme-modal__search" placeholder="Search themes..." autocomplete="off" />
                        <div class="codeshtheme-modal__filters">
                            <label class="codeshtheme-modal__filter-label">
                                <input type="radio" name="codeshtheme-variant" value="all" checked> All
                            </label>
                            <label class="codeshtheme-modal__filter-label">
                                <input type="radio" name="codeshtheme-variant" value="dark"> Dark
                            </label>
                            <label class="codeshtheme-modal__filter-label">
                                <input type="radio" name="codeshtheme-variant" value="light"> Light
                            </label>
                            <label class="codeshtheme-modal__filter-label">
                                <input type="radio" name="codeshtheme-variant" value="custom"> Custom
                            </label>
                        </div>
                    </div>
                    <div class="codeshtheme-modal__grid" data-codeshtheme-grid></div>
                    <div class="codeshtheme-modal__footer" data-codeshtheme-footer></div>
                </div>
                <input type="file" class="codeshtheme-modal__file-input" accept=".json" data-codeshtheme-file />
            `;

            this.overlay = this.element.querySelector('[data-codeshtheme-overlay]');
            this.closeButton = this.element.querySelector('.codeshtheme-modal__close');
            this.importButton = this.element.querySelector('[data-codeshtheme-import]');
            this.fileInput = this.element.querySelector('[data-codeshtheme-file]');
            this.searchInput = this.element.querySelector('.codeshtheme-modal__search');
            this.grid = this.element.querySelector('[data-codeshtheme-grid]');
            this.footer = this.element.querySelector('[data-codeshtheme-footer]');
            this.variantRadios = this.element.querySelectorAll('input[name="codeshtheme-variant"]');

            this.status = document.createElement('div');
            this.status.className = 'codeshtheme-modal__status';
            this.status.style.display = 'none';
            this.grid.appendChild(this.status);

            // Event listeners
            this.closeButton.addEventListener('click', () => this.close());
            this.overlay.addEventListener('click', () => this.close());

            this.importButton.addEventListener('click', () => this.fileInput.click());
            this.fileInput.addEventListener('change', (e) => this.handleImport(e));

            this.searchInput.addEventListener('input', () => {
                clearTimeout(this.debounceHandle);
                this.debounceHandle = setTimeout(() => {
                    this.searchQuery = this.searchInput.value.trim().toLowerCase();
                    this.renderThemes();
                }, 250);
            });

            this.variantRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    this.filterVariant = radio.value;
                    this.renderThemes();
                });
            });

            this._handleKeydown = this._handleKeydown.bind(this);
        }

        async open(field) {
            this.activeField = field;

            // Set filter based on field variant
            const variant = field.variant || 'all';
            this.filterVariant = variant === 'dark' || variant === 'light' ? variant : 'all';

            // Update radio buttons
            this.variantRadios.forEach(radio => {
                radio.checked = radio.value === this.filterVariant;
            });

            this.searchQuery = '';
            this.searchInput.value = '';

            document.body.appendChild(this.element);
            requestAnimationFrame(() => {
                this.element.classList.add('is-open');
            });
            document.addEventListener('keydown', this._handleKeydown, true);

            await this.loadThemes();
        }

        close() {
            this.element.classList.remove('is-open');
            document.removeEventListener('keydown', this._handleKeydown, true);
            setTimeout(() => {
                if (this.element.parentNode) {
                    this.element.parentNode.removeChild(this.element);
                }
            }, 150);
            this.activeField = null;
            this.deleteConfirmTheme = null;
        }

        _handleKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
            }
        }

        async loadThemes() {
            this.showStatus('Loading themes...', 'loading');

            try {
                this.themes = await this.api.getThemes();
                this.renderThemes();
            } catch (error) {
                console.error('Failed to load themes:', error);
                this.showStatus('Failed to load themes: ' + error.message, 'error');
            }
        }

        renderThemes() {
            // Clear grid except status
            this.grid.innerHTML = '';
            this.grid.appendChild(this.status);

            let allThemes = [...this.themes.builtin, ...this.themes.custom];

            // Filter by variant/type
            if (this.filterVariant === 'custom') {
                allThemes = allThemes.filter(t => t.custom);
            } else if (this.filterVariant !== 'all') {
                allThemes = allThemes.filter(t => t.type === this.filterVariant);
            }

            // Filter by search
            if (this.searchQuery) {
                allThemes = allThemes.filter(t =>
                    t.name.toLowerCase().includes(this.searchQuery) ||
                    (t.displayName && t.displayName.toLowerCase().includes(this.searchQuery))
                );
            }

            if (allThemes.length === 0) {
                this.showStatus('No themes match the current filters', 'empty');
                this.updateFooter(0, 0);
                return;
            }

            this.showStatus('', '');

            const fragment = document.createDocumentFragment();
            const selectedValue = this.activeField?.getValue();

            allThemes.forEach(theme => {
                const card = this.createThemeCard(theme, selectedValue);
                fragment.appendChild(card);
            });

            this.grid.insertBefore(fragment, this.status);
            this.updateFooter(allThemes.length, this.themes.builtin.length + this.themes.custom.length);
        }

        createThemeCard(theme, selectedValue) {
            const card = document.createElement('div');
            card.className = 'codeshtheme-modal__card';
            if (theme.custom) card.classList.add('is-custom');
            if (selectedValue === theme.name) card.classList.add('is-selected');
            card.dataset.theme = theme.name;

            const colors = theme.colors || {};
            const bg = colors.background || (theme.type === 'light' ? '#fafafa' : '#1f1f22');
            const fg = colors.foreground || (theme.type === 'light' ? '#383a42' : '#abb2bf');
            const kw = colors.keyword || fg;
            const str = colors.string || fg;
            const cmt = colors.comment || fg;
            const fn = colors.function || fg;
            const tp = colors.type || fg;

            const previewCode = `<span style="color: ${kw}">class</span> <span style="color: ${tp}">UserService</span> {
    <span style="color: ${kw}">private</span> <span style="color: ${tp}">array</span> $users = [];

    <span style="color: ${cmt}">// Find user by ID</span>
    <span style="color: ${kw}">public function</span> <span style="color: ${fn}">find</span>(<span style="color: ${tp}">int</span> $id): ?<span style="color: ${tp}">User</span> {
        <span style="color: ${kw}">return</span> $this->users[$id] ?? <span style="color: ${kw}">null</span>;
    }

    <span style="color: ${kw}">public function</span> <span style="color: ${fn}">create</span>(<span style="color: ${str}">string</span> $name) {
        $user = <span style="color: ${kw}">new</span> <span style="color: ${tp}">User</span>($name);
        <span style="color: ${kw}">return</span> $user;
    }
}`;

            card.innerHTML = `
                <div class="codeshtheme-modal__card-preview" style="background: ${bg} !important;">
                    <pre style="color: ${fg}; background: ${bg}; margin: 0; padding: 0.5rem; font-size: 9px; line-height: 1.35; font-family: 'JetBrains Mono', 'Fira Code', monospace;">${previewCode}</pre>
                    ${theme.custom ? '<div class="codeshtheme-modal__card-badges"><span class="codeshtheme-modal__card-badge is-custom">Custom</span></div>' : ''}
                </div>
                <div class="codeshtheme-modal__card-info">
                    <span class="codeshtheme-modal__card-name">${escapeHtml(theme.displayName || theme.name)}</span>
                    ${theme.custom ? '<button type="button" class="codeshtheme-modal__card-delete" data-delete-theme="' + escapeHtml(theme.name) + '" title="Delete theme"><i class="fa fa-trash"></i></button>' : ''}
                </div>
            `;

            // Click to select
            card.addEventListener('click', (e) => {
                // Don't select if clicking delete button
                if (e.target.closest('[data-delete-theme]')) return;
                // Don't select if there's a confirm dialog
                if (card.querySelector('.codeshtheme-modal__confirm')) return;

                if (this.activeField) {
                    this.activeField.setValue(theme.name);
                }
                this.close();
            });

            // Delete button
            const deleteBtn = card.querySelector('[data-delete-theme]');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.showDeleteConfirm(card, theme.name);
                });
            }

            return card;
        }

        showDeleteConfirm(card, themeName) {
            // Remove any existing confirm dialogs
            this.element.querySelectorAll('.codeshtheme-modal__confirm').forEach(el => el.remove());

            const confirm = document.createElement('div');
            confirm.className = 'codeshtheme-modal__confirm';
            confirm.innerHTML = `
                <p>Delete "${escapeHtml(themeName)}"?</p>
                <div class="codeshtheme-modal__confirm-actions">
                    <button type="button" class="codeshtheme-modal__confirm-btn codeshtheme-modal__confirm-btn--cancel">Cancel</button>
                    <button type="button" class="codeshtheme-modal__confirm-btn codeshtheme-modal__confirm-btn--delete">Delete</button>
                </div>
            `;

            confirm.querySelector('.codeshtheme-modal__confirm-btn--cancel').addEventListener('click', (e) => {
                e.stopPropagation();
                confirm.remove();
            });

            confirm.querySelector('.codeshtheme-modal__confirm-btn--delete').addEventListener('click', async (e) => {
                e.stopPropagation();
                await this.deleteTheme(themeName, card);
            });

            card.appendChild(confirm);
        }

        async deleteTheme(themeName, card) {
            try {
                await this.api.deleteTheme(themeName);

                // Remove from local cache
                this.themes.custom = this.themes.custom.filter(t => t.name !== themeName);

                // Remove card with animation
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.remove();
                    this.updateFooter(
                        this.grid.querySelectorAll('.codeshtheme-modal__card').length,
                        this.themes.builtin.length + this.themes.custom.length
                    );
                }, 200);

                // If the deleted theme was selected, clear the field
                if (this.activeField && this.activeField.getValue() === themeName) {
                    this.activeField.clear();
                }

            } catch (error) {
                console.error('Failed to delete theme:', error);
                alert('Failed to delete theme: ' + error.message);
            }
        }

        async handleImport(event) {
            const file = event.target.files[0];
            if (!file) return;

            try {
                this.showStatus('Importing theme...', 'loading');

                const result = await this.api.importTheme(file);

                if (result.success) {
                    const importedThemeName = result.theme;

                    // Reload themes
                    this.api.clearCache();
                    await this.loadThemes();

                    // Show success message
                    this.showStatus(`Theme "${importedThemeName}" imported successfully!`, 'success');

                    // Scroll to and highlight the new theme
                    setTimeout(() => {
                        const newCard = this.grid.querySelector(`[data-theme="${importedThemeName}"]`);
                        if (newCard) {
                            newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            newCard.classList.add('is-just-imported');
                            setTimeout(() => newCard.classList.remove('is-just-imported'), 2000);
                        }
                    }, 100);

                    // Clear status after a delay
                    setTimeout(() => {
                        this.showStatus('', '');
                    }, 3000);
                } else {
                    throw new Error(result.error || 'Import failed');
                }
            } catch (error) {
                console.error('Failed to import theme:', error);
                alert('Failed to import theme: ' + error.message);
                this.showStatus('', '');
            }

            // Clear file input
            this.fileInput.value = '';
        }

        updateFooter(shown, total) {
            if (total === 0) {
                this.footer.textContent = '';
            } else {
                this.footer.textContent = `Showing ${shown} of ${total} themes`;
            }
        }

        showStatus(message, state) {
            if (!this.status) return;

            this.status.textContent = message;
            this.status.className = 'codeshtheme-modal__status';
            if (state) {
                this.status.classList.add(`codeshtheme-modal__status--${state}`);
            }
            this.status.style.display = message ? 'block' : 'none';
        }
    }

    /**
     * Field class for individual theme picker fields
     */
    class CodeshThemeField {
        constructor(container) {
            this.container = container;
            this.input = container.querySelector('[data-codeshtheme-input]');
            this.preview = container.querySelector('[data-codeshtheme-preview]');
            this.chooseButton = container.querySelector('[data-codeshtheme-choose]');
            this.clearButton = container.querySelector('[data-codeshtheme-clear]');

            this.placeholder = container.dataset.placeholder || 'Select a theme...';
            this.variant = container.dataset.variant || 'dark';
            this.endpoint = container.dataset.endpoint || '';
            this.nonce = container.dataset.nonce || '';

            this.chooseButton?.addEventListener('click', () => this.openPicker());
            this.clearButton?.addEventListener('click', () => this.clear());
        }

        openPicker() {
            CodeshThemeModal.getInstance(this.endpoint, this.nonce).open(this);
        }

        getValue() {
            return (this.input?.value || '').trim();
        }

        setValue(value) {
            if (!this.input) return;

            this.input.value = value;
            this.container.dataset.value = value;
            this.updatePreview();
            this.triggerChange();
        }

        clear() {
            if (!this.input) return;

            this.input.value = '';
            this.container.dataset.value = '';
            this.updatePreview();
            this.triggerChange();
        }

        updatePreview() {
            if (!this.preview) return;

            const value = this.getValue();

            if (!value) {
                this.preview.innerHTML = `<span class="codeshtheme-field__placeholder">${escapeHtml(this.placeholder)}</span>`;
                if (this.clearButton) {
                    this.clearButton.disabled = true;
                }
                return;
            }

            this.preview.innerHTML = `<span class="codeshtheme-field__value">${escapeHtml(value)}</span>`;

            if (this.clearButton) {
                this.clearButton.disabled = false;
            }
        }

        triggerChange() {
            if (!this.input) return;

            const changeEvent = new Event('change', { bubbles: true });
            const inputEvent = new Event('input', { bubbles: true });
            this.input.dispatchEvent(changeEvent);
            this.input.dispatchEvent(inputEvent);
        }
    }

    /**
     * Initialize all theme picker fields
     */
    function initialize() {
        const containers = document.querySelectorAll('[data-grav-codeshtheme-field] .codeshtheme-field__container');
        containers.forEach((container) => {
            if (!container._codeshThemeField) {
                const field = new CodeshThemeField(container);
                field.updatePreview();
                container._codeshThemeField = field;
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    // Re-initialize when Grav admin adds new fields dynamically
    document.addEventListener('change', (event) => {
        setTimeout(initialize, 100);
    });

})();
