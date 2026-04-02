/**
 * Codesh Theme Picker — Web Component for admin-next
 */

const TAG = window.__GRAV_FIELD_TAG;
const MODAL_ID = '__codesh-theme-modal';
const SAMPLE_CODE = `class UserService {
    private users = [];

    // Find user by ID
    public function find(int $id)
        return $this->users[$id] ??
    }

    public function create(string $n)
        $user = new User($name);
        return $user;
    }
}`;

function getAuth() {
  try {
    const a = JSON.parse(localStorage.getItem('grav_admin_auth') || '{}');
    return { token: a.accessToken || '', env: a.environment || '' };
  } catch { return { token: '', env: '' }; }
}

class CodeshThemeField extends HTMLElement {
  _field = null;
  _value = '';
  _themes = null;
  _search = '';
  _filter = 'all';
  _escHandler = null;

  set field(f) { this._field = f; this._renderSelected(); }
  set value(v) {
    if (v !== this._value) {
      this._value = v || '';
      this._renderSelected();
    }
  }
  get value() { return this._value; }

  connectedCallback() {
    this.attachShadow({ mode: 'open' });
    this._renderSelected();
  }

  disconnectedCallback() {
    this._removeModal();
  }

  _renderSelected() {
    if (!this.shadowRoot) return;
    const variant = this._field?.variant || 'dark';
    const val = this._value;

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: block; }
        .selected { display: flex; align-items: center; gap: 8px; min-height: 40px;
          border: 1px solid var(--color-border, #e2e8f0); border-radius: 8px;
          padding: 4px 12px; background: var(--color-muted, #f1f5f9); cursor: pointer; }
        .selected:hover { border-color: var(--color-primary, #3b82f6); }
        .name { flex: 1; font-size: 14px; color: var(--color-foreground, #0f172a); }
        .placeholder { flex: 1; font-size: 14px; color: var(--color-muted-foreground, #64748b); }
        .btn { padding: 4px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;
          border: 1px solid var(--color-border, #e2e8f0); background: var(--color-background, white);
          color: var(--color-foreground, #0f172a); }
        .btn:hover { background: var(--color-accent, #f1f5f9); }
        .btn-clear { color: var(--color-muted-foreground, #64748b); }
      </style>
      <div class="selected" id="trigger">
        ${val ? `<span class="name">${this._esc(val)}</span>` : `<span class="placeholder">Select a ${variant} theme...</span>`}
        <button class="btn" type="button" id="choose">Choose Theme</button>
        ${val ? `<button class="btn btn-clear" type="button" id="clear">Clear</button>` : ''}
      </div>
    `;
    this.shadowRoot.getElementById('choose')?.addEventListener('click', () => this._openModal());
    this.shadowRoot.getElementById('clear')?.addEventListener('click', () => this._selectTheme(''));
    this.shadowRoot.getElementById('trigger')?.addEventListener('click', (e) => {
      if (e.target.classList.contains('name') || e.target.classList.contains('placeholder'))
        this._openModal();
    });
  }

  async _openModal() {
    if (!this._themes) await this._loadThemes();
    this._search = '';
    this._filter = this._field?.variant || 'all';
    this._renderModal();
  }

  async _loadThemes() {
    try {
      const { token, env } = getAuth();
      const serverUrl = window.__GRAV_API_SERVER_URL || '';
      const apiPrefix = window.__GRAV_API_PREFIX || '/api/v1';
      const headers = {};
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (env) headers['X-Grav-Environment'] = env;
      const resp = await fetch(`${serverUrl}${apiPrefix}/codesh/themes`, { headers });
      const json = await resp.json();
      const data = json.data || json;
      this._themes = [...(data.builtin || []), ...(data.custom || [])];
    } catch (err) {
      console.error('[CodeshTheme] Failed to load themes:', err);
      this._themes = [];
    }
  }

  _renderModal() {
    this._removeModal();
    const variant = this._field?.variant || 'dark';

    let themes = this._themes || [];
    if (this._filter === 'dark') themes = themes.filter(t => t.type === 'dark');
    else if (this._filter === 'light') themes = themes.filter(t => t.type === 'light');
    else if (this._filter === 'custom') themes = themes.filter(t => t.custom);
    if (this._search) {
      const q = this._search.toLowerCase();
      themes = themes.filter(t => (t.displayName || t.name).toLowerCase().includes(q));
    }

    const filtersHtml = ['all', 'dark', 'light', 'custom'].map(f =>
      `<button class="ctm-filter ${f === this._filter ? 'active' : ''}" data-filter="${f}">${f.charAt(0).toUpperCase() + f.slice(1)}</button>`
    ).join('');

    const cardsHtml = themes.length ? themes.map(t => {
      const bg = t.colors?.background || '#1e1e1e';
      const fg = t.colors?.foreground || '#d4d4d4';
      const kw = t.colors?.keyword || '#569cd6';
      const str = t.colors?.string || '#ce9178';
      const cm = t.colors?.comment || '#6a9955';
      const fn = t.colors?.function || '#dcdcaa';
      const sel = t.name === this._value ? 'selected' : '';
      // Tokenize first, then colorize — avoids regex matching its own output
      const highlighted = SAMPLE_CODE.replace(
        /(\/\/[^\n]*)|('[^']*'|"[^"]*")|(class |public |function |return |new |private )|(\w+)\(/g,
        (m, comment, str2, keyword, func) => {
          if (comment) return `<span style=color:${cm}>${this._esc(comment)}</span>`;
          if (str2) return `<span style=color:${str}>${this._esc(str2)}</span>`;
          if (keyword) return `<span style=color:${kw}>${this._esc(keyword)}</span>`;
          if (func) return `<span style=color:${fn}>${this._esc(func)}</span>(`;
          return this._esc(m);
        }
      );
      return `<div class="ctm-card ${sel}" data-theme="${this._esc(t.name)}">
        <div class="ctm-preview" style="background:${bg};color:${fg}">${highlighted}</div>
        <div class="ctm-card-name">${this._esc(t.displayName || t.name)}</div>
      </div>`;
    }).join('') : '<div class="ctm-empty">No themes match</div>';

    // Append modal to document.body so it escapes all overflow/sizing constraints
    const wrapper = document.createElement('div');
    wrapper.id = MODAL_ID;
    wrapper.innerHTML = `
      <style>
        #${MODAL_ID} { position: fixed; inset: 0; z-index: 10000; display: flex;
          align-items: center; justify-content: center;
          background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); font-family: system-ui, sans-serif; }
        .ctm-modal { background: white; border-radius: 12px;
          width: 90vw; max-width: 940px; height: 85vh; display: flex; flex-direction: column;
          box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; }
        .ctm-header { display: flex; align-items: center; padding: 16px 20px;
          border-bottom: 1px solid #e5e7eb; flex-shrink: 0; }
        .ctm-header h3 { flex: 1; margin: 0; font-size: 16px; font-weight: 600; color: #111; }
        .ctm-close { background: none; border: none; cursor: pointer; padding: 4px;
          color: #6b7280; font-size: 22px; line-height: 1; }
        .ctm-close:hover { color: #111; }
        .ctm-import { display: inline-flex; align-items: center; gap: 6px;
          padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 500;
          cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; }
        .ctm-import:hover { background: #f3f4f6; }
        .ctm-toolbar { display: flex; align-items: center; gap: 8px; padding: 12px 20px;
          border-bottom: 1px solid #e5e7eb; flex-shrink: 0; }
        .ctm-search { flex: 1; height: 36px; border: 1px solid #d1d5db;
          border-radius: 8px; padding: 0 12px; font-size: 14px; background: #f9fafb; color: #111;
          direction: ltr; text-align: left; }
        .ctm-search:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
        .ctm-filters { display: flex; gap: 4px; }
        .ctm-filter { padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;
          border: 1px solid #d1d5db; background: white; color: #6b7280; font-weight: 500; }
        .ctm-filter:hover { background: #f3f4f6; }
        .ctm-filter.active { background: #3b82f6; color: white; border-color: transparent; }
        .ctm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
          gap: 14px; padding: 16px 20px; overflow-y: auto; flex: 1 1 0; min-height: 0;
          align-content: start; }
        #${MODAL_ID} * { min-height: auto; }
        .ctm-card { border-radius: 8px; cursor: pointer; overflow: clip;
          border: 2px solid transparent; transition: border-color 0.15s; }
        .ctm-card:hover { border-color: #3b82f6; }
        .ctm-card.selected { border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
        .ctm-preview { padding: 12px; font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
          font-size: 11px; line-height: 1.5; white-space: pre; overflow: hidden; height: 170px; }
        .ctm-card-name { padding: 8px 12px; font-size: 12px; font-weight: 500; color: #111;
          border-top: 1px solid #e5e7eb; background: #f9fafb; }
        .ctm-empty { grid-column: 1 / -1; text-align: center; padding: 40px; color: #6b7280; font-size: 13px; }
        .ctm-card { position: relative; }
        .ctm-delete { position: absolute; top: 6px; right: 6px; width: 22px; height: 22px;
          border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center;
          justify-content: center; background: rgba(0,0,0,0.5); color: white; font-size: 14px;
          line-height: 1; opacity: 0; transition: opacity 0.15s; }
        .ctm-card:hover .ctm-delete { opacity: 1; }
        .ctm-delete:hover { background: #ef4444; }

        /* Dark mode — admin-next sets .dark on <html> */
        .dark .ctm-modal { background: #1c1c1e; }
        .dark .ctm-header { border-color: #2e2e30; }
        .dark .ctm-header h3 { color: #e5e5e7; }
        .dark .ctm-close { color: #8e8e93; }
        .dark .ctm-close:hover { color: #e5e5e7; }
        .dark .ctm-import { background: #2e2e30; border-color: #3a3a3c; color: #e5e5e7; }
        .dark .ctm-import:hover { background: #3a3a3c; }
        .dark .ctm-toolbar { border-color: #2e2e30; }
        .dark .ctm-search { background: #2e2e30; border-color: #3a3a3c; color: #e5e5e7; }
        .dark .ctm-search::placeholder { color: #8e8e93; }
        .dark .ctm-filter { background: #2e2e30; border-color: #3a3a3c; color: #8e8e93; }
        .dark .ctm-filter:hover { background: #3a3a3c; }
        .dark .ctm-filter.active { background: #3b82f6; color: white; border-color: transparent; }
        .dark .ctm-card-name { color: #e5e5e7; border-color: #2e2e30; background: #2a2a2c; }
        .dark .ctm-empty { color: #8e8e93; }
      </style>
      <div class="ctm-modal">
        <div class="ctm-header">
          <h3>Select ${variant.charAt(0).toUpperCase() + variant.slice(1)} Theme</h3>
          <button class="ctm-import" id="ctm-import" type="button">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Import
          </button>
          <button class="ctm-close" id="ctm-close" type="button">&times;</button>
        </div>
        <div class="ctm-toolbar">
          <input class="ctm-search" type="text" placeholder="Search themes..." id="ctm-search" value="${this._esc(this._search)}" />
          <div class="ctm-filters">${filtersHtml}</div>
        </div>
        <div class="ctm-grid">${cardsHtml}</div>
      </div>
    `;

    document.body.appendChild(wrapper);

    // Events
    wrapper.querySelector('#ctm-close')?.addEventListener('click', () => this._removeModal());
    wrapper.querySelector('#ctm-import')?.addEventListener('click', () => this._handleImport());
    wrapper.addEventListener('click', (e) => { if (e.target === wrapper) this._removeModal(); });
    wrapper.querySelector('#ctm-search')?.addEventListener('input', (e) => {
      this._search = e.target.value;
      this._updateGrid();
    });
    wrapper.querySelectorAll('.ctm-filter').forEach(btn => {
      btn.addEventListener('click', () => {
        this._filter = btn.dataset.filter;
        // Update active state on filter buttons
        wrapper.querySelectorAll('.ctm-filter').forEach(b => b.classList.toggle('active', b === btn));
        this._updateGrid();
      });
    });
    wrapper.querySelectorAll('.ctm-card[data-theme]').forEach(card => {
      card.addEventListener('click', () => { this._selectTheme(card.dataset.theme); this._removeModal(); });
    });
    wrapper.querySelector('#ctm-search')?.focus();

    this._escHandler = (e) => { if (e.key === 'Escape') this._removeModal(); };
    document.addEventListener('keydown', this._escHandler);
  }

  _updateGrid() {
    const wrapper = document.getElementById(MODAL_ID);
    const grid = wrapper?.querySelector('.ctm-grid');
    if (!grid) return;

    let themes = this._themes || [];
    if (this._filter === 'dark') themes = themes.filter(t => t.type === 'dark');
    else if (this._filter === 'light') themes = themes.filter(t => t.type === 'light');
    else if (this._filter === 'custom') themes = themes.filter(t => t.custom);
    if (this._search) {
      const q = this._search.toLowerCase();
      themes = themes.filter(t => (t.displayName || t.name).toLowerCase().includes(q));
    }

    grid.innerHTML = themes.length ? themes.map(t => {
      const bg = t.colors?.background || '#1e1e1e';
      const fg = t.colors?.foreground || '#d4d4d4';
      const kw = t.colors?.keyword || '#569cd6';
      const str = t.colors?.string || '#ce9178';
      const cm = t.colors?.comment || '#6a9955';
      const fn = t.colors?.function || '#dcdcaa';
      const sel = t.name === this._value ? 'selected' : '';
      const highlighted = SAMPLE_CODE.replace(
        /(\/\/[^\n]*)|('[^']*'|"[^"]*")|(class |public |function |return |new |private )|(\w+)\(/g,
        (m, comment, str2, keyword, func) => {
          if (comment) return `<span style=color:${cm}>${this._esc(comment)}</span>`;
          if (str2) return `<span style=color:${str}>${this._esc(str2)}</span>`;
          if (keyword) return `<span style=color:${kw}>${this._esc(keyword)}</span>`;
          if (func) return `<span style=color:${fn}>${this._esc(func)}</span>(`;
          return this._esc(m);
        }
      );
      const deleteBtn = t.custom ? `<button class="ctm-delete" data-delete="${this._esc(t.name)}" title="Delete">&times;</button>` : '';
      return `<div class="ctm-card ${sel}" data-theme="${this._esc(t.name)}">
        ${deleteBtn}
        <div class="ctm-preview" style="background:${bg};color:${fg}">${highlighted}</div>
        <div class="ctm-card-name">${this._esc(t.displayName || t.name)}</div>
      </div>`;
    }).join('') : '<div class="ctm-empty">No themes match</div>';

    // Re-bind card click events
    grid.querySelectorAll('.ctm-card[data-theme]').forEach(card => {
      card.addEventListener('click', (e) => {
        if (e.target.closest('.ctm-delete')) return;
        this._selectTheme(card.dataset.theme);
        this._removeModal();
      });
    });
    // Delete button events
    grid.querySelectorAll('.ctm-delete[data-delete]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this._deleteTheme(btn.dataset.delete);
      });
    });
  }

  _removeModal() {
    document.getElementById(MODAL_ID)?.remove();
    if (this._escHandler) {
      document.removeEventListener('keydown', this._escHandler);
      this._escHandler = null;
    }
  }

  async _deleteTheme(name) {
    if (!confirm(`Delete custom theme "${name}"?`)) return;
    const { token, env } = getAuth();
    const serverUrl = window.__GRAV_API_SERVER_URL || '';
    const apiPrefix = window.__GRAV_API_PREFIX || '/api/v1';
    const headers = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;
    if (env) headers['X-Grav-Environment'] = env;
    try {
      const resp = await fetch(`${serverUrl}${apiPrefix}/codesh/themes/${encodeURIComponent(name)}`, {
        method: 'DELETE', headers
      });
      if (!resp.ok && resp.status !== 204) {
        alert('Failed to delete theme');
        return;
      }
      // Remove from cache and re-render
      if (this._themes) {
        this._themes = this._themes.filter(t => t.name !== name);
      }
      // Clear value if the deleted theme was selected
      if (this._value === name) {
        this._selectTheme('');
      }
      this._updateGrid();
    } catch (err) {
      alert('Failed to delete: ' + err.message);
    }
  }

  _handleImport() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = async () => {
      const file = input.files?.[0];
      if (!file) return;
      const { token, env } = getAuth();
      const serverUrl = window.__GRAV_API_SERVER_URL || '';
      const apiPrefix = window.__GRAV_API_PREFIX || '/api/v1';
      const headers = {};
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (env) headers['X-Grav-Environment'] = env;
      const form = new FormData();
      form.append('file', file);
      try {
        const resp = await fetch(`${serverUrl}${apiPrefix}/codesh/themes/import`, {
          method: 'POST', headers, body: form
        });
        if (!resp.ok) {
          const json = await resp.json().catch(() => ({}));
          alert(json.error || 'Import failed');
          return;
        }
        // Reload themes and re-render
        this._themes = null;
        await this._loadThemes();
        this._filter = 'custom';
        this._renderModal();
      } catch (err) {
        alert('Import failed: ' + (err.message || err));
        console.error('[CodeshTheme] Import error:', err);
      }
    };
    input.click();
  }

  _selectTheme(name) {
    this._value = name;
    this.dispatchEvent(new CustomEvent('change', { detail: name, bubbles: true }));
    this._renderSelected();
  }

  _esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }
}

customElements.define(TAG, CodeshThemeField);
