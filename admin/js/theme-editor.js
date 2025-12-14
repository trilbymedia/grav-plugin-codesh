/**
 * CodeSh Theme Editor
 */
(function() {
    'use strict';

    const config = window.CODESH_EDITOR || {};
    let themes = { builtin: [], custom: [] };
    let currentTheme = null;
    let currentLanguage = 'php';
    let unsavedChanges = false;
    let previewDebounce = null;

    // Color presets
    const PRESETS = {
        'one-dark': {
            variant: 'dark',
            colors: {
                background: '#282C34',
                foreground: '#ABB2BF',
                comment: '#5C6370',
                keyword: '#C678DD',
                string: '#98C379',
                number: '#D19A66',
                function: '#61AFEF',
                type: '#E5C07B',
                variable: '#E06C75',
                operator: '#56B6C2'
            }
        },
        'github-dark': {
            variant: 'dark',
            colors: {
                background: '#0D1117',
                foreground: '#C9D1D9',
                comment: '#8B949E',
                keyword: '#FF7B72',
                string: '#A5D6FF',
                number: '#79C0FF',
                function: '#D2A8FF',
                type: '#7EE787',
                variable: '#FFA657',
                operator: '#C9D1D9'
            }
        },
        'dracula': {
            variant: 'dark',
            colors: {
                background: '#282A36',
                foreground: '#F8F8F2',
                comment: '#6272A4',
                keyword: '#FF79C6',
                string: '#F1FA8C',
                number: '#BD93F9',
                function: '#8BE9FD',
                type: '#8BE9FD',
                variable: '#F8F8F2',
                operator: '#FF79C6'
            }
        },
        'tokyo-night': {
            variant: 'dark',
            colors: {
                background: '#1A1B26',
                foreground: '#A9B1D6',
                comment: '#565F89',
                keyword: '#BB9AF7',
                string: '#9ECE6A',
                number: '#FF9E64',
                function: '#7AA2F7',
                type: '#2AC3DE',
                variable: '#C0CAF5',
                operator: '#89DDFF'
            }
        },
        'nord': {
            variant: 'dark',
            colors: {
                background: '#2E3440',
                foreground: '#D8DEE9',
                comment: '#616E88',
                keyword: '#81A1C1',
                string: '#A3BE8C',
                number: '#B48EAD',
                function: '#88C0D0',
                type: '#8FBCBB',
                variable: '#D8DEE9',
                operator: '#81A1C1'
            }
        },
        'github-light': {
            variant: 'light',
            colors: {
                background: '#FFFFFF',
                foreground: '#24292E',
                comment: '#6A737D',
                keyword: '#D73A49',
                string: '#032F62',
                number: '#005CC5',
                function: '#6F42C1',
                type: '#22863A',
                variable: '#E36209',
                operator: '#24292E'
            }
        },
        'one-light': {
            variant: 'light',
            colors: {
                background: '#FAFAFA',
                foreground: '#383A42',
                comment: '#A0A1A7',
                keyword: '#A626A4',
                string: '#50A14F',
                number: '#986801',
                function: '#4078F2',
                type: '#C18401',
                variable: '#E45649',
                operator: '#383A42'
            }
        },
        'solarized-light': {
            variant: 'light',
            colors: {
                background: '#FDF6E3',
                foreground: '#657B83',
                comment: '#93A1A1',
                keyword: '#859900',
                string: '#2AA198',
                number: '#D33682',
                function: '#268BD2',
                type: '#B58900',
                variable: '#CB4B16',
                operator: '#859900'
            }
        }
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        if (!document.querySelector('.codesh-theme-editor')) return;

        bindModeToggle();
        bindGalleryActions();
        bindEditorActions();
        bindColorInputs();
        bindPreviewTabs();
        bindModals();
        bindPresets();
        bindBeforeUnload();

        loadThemes();
    }

    // Mode Toggle
    function bindModeToggle() {
        document.querySelectorAll('.mode-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const mode = tab.dataset.mode;
                document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                document.querySelectorAll('.editor-mode').forEach(m => m.classList.add('hidden'));
                document.getElementById(mode + '-mode').classList.remove('hidden');
            });
        });
    }

    // Gallery Actions
    function bindGalleryActions() {
        document.getElementById('create-theme-btn')?.addEventListener('click', () => {
            showCopyModal();
        });

        document.getElementById('import-file')?.addEventListener('change', handleImport);

        // Filter checkboxes
        ['filter-dark', 'filter-light', 'filter-custom'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', renderGallery);
        });
    }

    // Editor Actions
    function bindEditorActions() {
        document.getElementById('back-to-gallery')?.addEventListener('click', () => {
            if (unsavedChanges && !confirm('You have unsaved changes. Are you sure you want to leave?')) {
                return;
            }
            switchToGallery();
        });

        document.getElementById('save-theme')?.addEventListener('click', saveTheme);
        document.getElementById('export-theme')?.addEventListener('click', exportTheme);
        document.getElementById('delete-theme')?.addEventListener('click', showDeleteModal);

        // Theme settings
        document.getElementById('theme-variant')?.addEventListener('change', (e) => {
            const variant = e.target.value;
            document.getElementById('editor-theme-type').textContent = variant;
            document.getElementById('editor-theme-type').className = 'badge ' + variant;

            // Load default colors for variant
            const defaults = config.defaultColors[variant];
            if (defaults) {
                loadColors(defaults);
            }
            refreshPreview();
        });
    }

    // Color Inputs
    function bindColorInputs() {
        document.querySelectorAll('.color-input').forEach(group => {
            const picker = group.querySelector('.color-picker');
            const hex = group.querySelector('.hex-input');
            const swatch = group.querySelector('.color-swatch');

            if (!picker || !hex) return;

            picker.addEventListener('input', (e) => {
                const value = e.target.value.toUpperCase();
                hex.value = value;
                if (swatch) swatch.style.background = value;
                markUnsaved();
                debouncePreview();
            });

            hex.addEventListener('input', (e) => {
                let value = e.target.value.trim().toUpperCase();
                if (!value.startsWith('#')) value = '#' + value;

                if (/^#[0-9A-F]{6}$/i.test(value)) {
                    picker.value = value;
                    if (swatch) swatch.style.background = value;
                    hex.classList.remove('invalid');
                    markUnsaved();
                    debouncePreview();
                } else {
                    hex.classList.add('invalid');
                }
            });

            hex.addEventListener('blur', (e) => {
                let value = e.target.value.trim().toUpperCase();
                if (!value.startsWith('#')) value = '#' + value;
                if (/^#[0-9A-F]{6}$/i.test(value)) {
                    e.target.value = value;
                }
            });
        });
    }

    // Preview Tabs
    function bindPreviewTabs() {
        document.querySelectorAll('.preview-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.preview-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                currentLanguage = tab.dataset.lang;
                refreshPreview();
            });
        });
    }

    // Modals
    function bindModals() {
        // Copy modal
        document.getElementById('cancel-copy')?.addEventListener('click', hideCopyModal);
        document.querySelector('#copy-modal .modal-close')?.addEventListener('click', hideCopyModal);
        document.getElementById('confirm-copy')?.addEventListener('click', copyTheme);

        // Delete modal
        document.getElementById('cancel-delete')?.addEventListener('click', hideDeleteModal);
        document.querySelector('#delete-modal .modal-close')?.addEventListener('click', hideDeleteModal);
        document.getElementById('confirm-delete')?.addEventListener('click', deleteTheme);

        // Close modals on backdrop click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    }

    // Presets
    function bindPresets() {
        document.getElementById('color-preset')?.addEventListener('change', (e) => {
            const preset = e.target.value;
            if (preset && PRESETS[preset]) {
                loadColors(PRESETS[preset].colors);
                document.getElementById('theme-variant').value = PRESETS[preset].variant;
                document.getElementById('editor-theme-type').textContent = PRESETS[preset].variant;
                document.getElementById('editor-theme-type').className = 'badge ' + PRESETS[preset].variant;
                markUnsaved();
                refreshPreview();
                showToast('Preset loaded', 'info');
            }
            e.target.value = '';
        });
    }

    // Unsaved changes warning
    function bindBeforeUnload() {
        window.addEventListener('beforeunload', (e) => {
            if (unsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    // Load themes from server
    async function loadThemes() {
        try {
            const response = await fetch(config.apiBase + '/list', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Admin-Nonce': config.nonce
                }
            });

            const data = await response.json();
            themes = data;

            renderGallery();
            populateCopySourceDropdown();

            // If theme parameter in URL, open editor
            if (config.currentTheme) {
                const theme = findTheme(config.currentTheme);
                if (theme) {
                    openThemeEditor(theme);
                }
            }
        } catch (error) {
            console.error('Failed to load themes:', error);
            showToast('Failed to load themes', 'error');
        }
    }

    // Render gallery
    function renderGallery() {
        const gallery = document.getElementById('themes-gallery');
        const showDark = document.getElementById('filter-dark')?.checked ?? true;
        const showLight = document.getElementById('filter-light')?.checked ?? true;
        const customOnly = document.getElementById('filter-custom')?.checked ?? false;

        let allThemes = [...themes.builtin, ...themes.custom];

        // Apply filters
        allThemes = allThemes.filter(theme => {
            if (!showDark && theme.type === 'dark') return false;
            if (!showLight && theme.type === 'light') return false;
            if (customOnly && !theme.custom) return false;
            return true;
        });

        if (allThemes.length === 0) {
            gallery.innerHTML = '<div class="loading">No themes match the current filters</div>';
            return;
        }

        gallery.innerHTML = allThemes.map(theme => `
            <div class="theme-card ${theme.custom ? 'custom' : ''}" data-theme="${theme.name}">
                <div class="theme-card-preview" id="preview-${theme.name}">
                    <div class="loading"><i class="fa fa-spinner fa-spin"></i></div>
                </div>
                <div class="theme-card-info">
                    <div>
                        <span class="theme-card-name">${theme.displayName}</span>
                        <span class="badge ${theme.type}">${theme.type}</span>
                        ${theme.custom ? '<span class="badge custom">Custom</span>' : ''}
                    </div>
                    <div class="theme-card-actions">
                        ${theme.custom ? `
                            <button class="button" onclick="window.codeshEditor.editTheme('${theme.name}')">
                                <i class="fa fa-edit"></i>
                            </button>
                        ` : `
                            <button class="button" onclick="window.codeshEditor.copyThemeAs('${theme.name}')">
                                <i class="fa fa-copy"></i>
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `).join('');

        // Load previews for visible themes
        allThemes.forEach(theme => {
            loadThemePreview(theme.name);
        });
    }

    // Load theme preview
    function loadThemePreview(themeName) {
        const container = document.getElementById('preview-' + themeName);
        if (!container) return;

        const theme = findTheme(themeName);
        if (!theme) return;

        const colors = theme.colors || {};
        const bg = colors.background || (theme.type === 'light' ? '#fafafa' : '#1f1f22');
        const fg = colors.foreground || (theme.type === 'light' ? '#383a42' : '#abb2bf');
        const kw = colors.keyword || fg;
        const str = colors.string || fg;
        const cmt = colors.comment || fg;
        const fn = colors.function || fg;
        const tp = colors.type || fg;

        container.style.cssText = `background: ${bg} !important;`;
        container.innerHTML = `<pre style="color: ${fg}; background: ${bg}; margin: 0; padding: 0.5rem; font-size: 9px; line-height: 1.35; font-family: 'JetBrains Mono', 'Fira Code', monospace;"><span style="color: ${kw}">class</span> <span style="color: ${tp}">UserService</span> {
    <span style="color: ${kw}">private</span> <span style="color: ${tp}">array</span> $users = [];

    <span style="color: ${cmt}">// Find user by ID</span>
    <span style="color: ${kw}">public function</span> <span style="color: ${fn}">find</span>(<span style="color: ${tp}">int</span> $id): ?<span style="color: ${tp}">User</span> {
        <span style="color: ${kw}">return</span> $this->users[$id] ?? <span style="color: ${kw}">null</span>;
    }

    <span style="color: ${kw}">public function</span> <span style="color: ${fn}">create</span>(<span style="color: ${str}">string</span> $name) {
        $user = <span style="color: ${kw}">new</span> <span style="color: ${tp}">User</span>($name);
        <span style="color: ${kw}">return</span> $user;
    }
}</pre>`;
    }

    // Find theme by name
    function findTheme(name) {
        return themes.builtin.find(t => t.name === name) ||
               themes.custom.find(t => t.name === name);
    }

    // Populate copy source dropdown
    function populateCopySourceDropdown() {
        const select = document.getElementById('copy-source');
        if (!select) return;

        let html = '<option value="">Select a theme...</option>';

        html += '<optgroup label="Built-in Dark">';
        themes.builtin.filter(t => t.type === 'dark').forEach(t => {
            html += `<option value="${t.name}">${t.displayName}</option>`;
        });
        html += '</optgroup>';

        html += '<optgroup label="Built-in Light">';
        themes.builtin.filter(t => t.type === 'light').forEach(t => {
            html += `<option value="${t.name}">${t.displayName}</option>`;
        });
        html += '</optgroup>';

        if (themes.custom.length > 0) {
            html += '<optgroup label="Custom Themes">';
            themes.custom.forEach(t => {
                html += `<option value="${t.name}">${t.displayName}</option>`;
            });
            html += '</optgroup>';
        }

        select.innerHTML = html;
    }

    // Switch to gallery
    function switchToGallery() {
        document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
        document.querySelector('.mode-tab[data-mode="gallery"]').classList.add('active');
        document.querySelectorAll('.editor-mode').forEach(m => m.classList.add('hidden'));
        document.getElementById('gallery-mode').classList.remove('hidden');
        unsavedChanges = false;
        currentTheme = null;
    }

    // Switch to editor
    function switchToEditor() {
        document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
        document.querySelector('.mode-tab[data-mode="editor"]').classList.add('active');
        document.querySelectorAll('.editor-mode').forEach(m => m.classList.add('hidden'));
        document.getElementById('editor-mode').classList.remove('hidden');
    }

    // Open theme editor
    function openThemeEditor(theme) {
        currentTheme = theme;
        switchToEditor();

        // Set theme info
        document.getElementById('editor-theme-name').textContent = theme.displayName;
        document.getElementById('editor-theme-type').textContent = theme.type;
        document.getElementById('editor-theme-type').className = 'badge ' + theme.type;

        document.getElementById('theme-name-input').value = theme.name;
        document.getElementById('theme-display-name').value = theme.displayName;
        document.getElementById('theme-variant').value = theme.type;

        // Enable/disable buttons based on whether it's a custom theme
        document.getElementById('export-theme').disabled = false;
        document.getElementById('delete-theme').disabled = !theme.custom;

        // Load colors if it's a custom theme
        if (theme.custom) {
            loadThemeColors(theme.name);
        } else {
            // Load default colors for the variant
            loadColors(config.defaultColors[theme.type]);
            refreshPreview();
        }

        unsavedChanges = false;
    }

    // Load theme colors from server
    async function loadThemeColors(themeName) {
        // For custom themes, we would need to fetch and extract colors
        // For now, just refresh preview
        refreshPreview();
    }

    // Load colors into form
    function loadColors(colors) {
        Object.keys(colors).forEach(key => {
            const picker = document.getElementById('color-' + key);
            const hex = picker?.parentElement?.querySelector('.hex-input');
            const swatch = picker?.closest('.color-input')?.querySelector('.color-swatch');

            if (picker) picker.value = colors[key];
            if (hex) hex.value = colors[key];
            if (swatch) swatch.style.background = colors[key];
        });
    }

    // Get current colors from form
    function getCurrentColors() {
        const colors = {};
        document.querySelectorAll('.color-picker').forEach(picker => {
            colors[picker.name] = picker.value.toUpperCase();
        });
        return colors;
    }

    // Mark as having unsaved changes
    function markUnsaved() {
        unsavedChanges = true;
        document.querySelector('.codesh-theme-editor')?.classList.add('has-unsaved-changes');
    }

    // Debounced preview refresh
    function debouncePreview() {
        clearTimeout(previewDebounce);
        previewDebounce = setTimeout(refreshPreview, 300);
    }

    // Refresh preview
    async function refreshPreview() {
        const container = document.getElementById('preview-container');
        if (!container) return;

        const colors = getCurrentColors();
        const variant = document.getElementById('theme-variant')?.value || 'dark';

        container.innerHTML = '<div class="preview-loading"><i class="fa fa-spinner fa-spin"></i> Generating preview...</div>';

        try {
            const response = await fetch(config.apiBase + '/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Admin-Nonce': config.nonce
                },
                body: JSON.stringify({
                    colors: colors,
                    language: currentLanguage,
                    variant: variant
                })
            });

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            container.innerHTML = data.html;
        } catch (error) {
            console.error('Preview error:', error);
            container.innerHTML = '<div class="preview-loading">Preview generation failed</div>';
        }
    }

    // Save theme
    async function saveTheme() {
        const name = document.getElementById('theme-name-input')?.value?.trim();
        const displayName = document.getElementById('theme-display-name')?.value?.trim();
        const variant = document.getElementById('theme-variant')?.value;
        const colors = getCurrentColors();

        if (!name || !/^[a-z0-9-_]+$/i.test(name)) {
            showToast('Invalid theme name', 'error');
            return;
        }

        const btn = document.getElementById('save-theme');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

        try {
            const response = await fetch(config.apiBase + '/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Admin-Nonce': config.nonce
                },
                body: JSON.stringify({
                    name: name,
                    displayName: displayName || name,
                    variant: variant,
                    colors: colors
                })
            });

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            showToast('Theme saved successfully', 'success');
            unsavedChanges = false;
            document.querySelector('.codesh-theme-editor')?.classList.remove('has-unsaved-changes');

            // Reload themes
            await loadThemes();

            // Update current theme reference
            currentTheme = findTheme(name);
            if (currentTheme) {
                document.getElementById('delete-theme').disabled = false;
            }
        } catch (error) {
            console.error('Save error:', error);
            showToast('Failed to save: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    // Export theme
    function exportTheme() {
        const name = document.getElementById('theme-name-input')?.value?.trim();
        if (!name) {
            showToast('Please save the theme first', 'error');
            return;
        }
        window.location.href = config.apiBase + '/export/' + name;
    }

    // Show copy modal
    function showCopyModal(sourceName = '') {
        const modal = document.getElementById('copy-modal');
        const sourceSelect = document.getElementById('copy-source');
        const nameInput = document.getElementById('copy-name');

        if (sourceName && sourceSelect) {
            sourceSelect.value = sourceName;
        }
        nameInput.value = '';

        modal.style.display = 'flex';
    }

    function hideCopyModal() {
        document.getElementById('copy-modal').style.display = 'none';
    }

    // Copy theme
    async function copyTheme() {
        const source = document.getElementById('copy-source')?.value;
        const newName = document.getElementById('copy-name')?.value?.trim();

        if (!source) {
            showToast('Please select a source theme', 'error');
            return;
        }

        if (!newName || !/^[a-z0-9-_]+$/i.test(newName)) {
            showToast('Invalid theme name', 'error');
            return;
        }

        try {
            const response = await fetch(config.apiBase + '/copy', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Admin-Nonce': config.nonce
                },
                body: JSON.stringify({
                    source: source,
                    name: newName
                })
            });

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            showToast('Theme copied successfully', 'success');
            hideCopyModal();

            // Reload and open editor
            await loadThemes();
            const newTheme = findTheme(newName);
            if (newTheme) {
                openThemeEditor(newTheme);
            }
        } catch (error) {
            console.error('Copy error:', error);
            showToast('Failed to copy: ' + error.message, 'error');
        }
    }

    // Show delete modal
    function showDeleteModal() {
        const name = document.getElementById('theme-name-input')?.value;
        if (!name) return;

        document.getElementById('delete-theme-name').textContent = name;
        document.getElementById('delete-modal').style.display = 'flex';
    }

    function hideDeleteModal() {
        document.getElementById('delete-modal').style.display = 'none';
    }

    // Delete theme
    async function deleteTheme() {
        const name = document.getElementById('theme-name-input')?.value;
        if (!name) return;

        try {
            const formData = new FormData();
            formData.append('name', name);
            formData.append('admin-nonce', config.nonce);

            const response = await fetch(config.apiBase + '/delete', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            showToast('Theme deleted', 'success');
            hideDeleteModal();

            // Reload and switch to gallery
            await loadThemes();
            switchToGallery();
        } catch (error) {
            console.error('Delete error:', error);
            showToast('Failed to delete: ' + error.message, 'error');
        }
    }

    // Handle import
    async function handleImport(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('theme_file', file);
        formData.append('admin-nonce', config.nonce);

        try {
            const response = await fetch(config.apiBase + '/import', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Admin-Nonce': config.nonce
                },
                body: formData
            });

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            showToast('Theme imported successfully', 'success');

            // Reload and open editor
            await loadThemes();
            const newTheme = findTheme(data.theme);
            if (newTheme) {
                openThemeEditor(newTheme);
            }
        } catch (error) {
            console.error('Import error:', error);
            showToast('Failed to import: ' + error.message, 'error');
        }

        // Reset file input
        e.target.value = '';
    }

    // Toast notification
    function showToast(message, type = 'info') {
        const existing = document.querySelector('.codesh-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'codesh-toast ' + type;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Expose global functions for onclick handlers
    window.codeshEditor = {
        editTheme: (name) => {
            const theme = findTheme(name);
            if (theme) openThemeEditor(theme);
        },
        copyThemeAs: (name) => {
            showCopyModal(name);
        }
    };

})();
