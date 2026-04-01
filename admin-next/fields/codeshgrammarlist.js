/**
 * Codesh Grammar List — Web Component for admin-next
 *
 * Displays built-in and custom grammars in a multi-column layout.
 * Supports importing custom grammar JSON files.
 */

const TAG = window.__GRAV_FIELD_TAG;

function getAuth() {
  try {
    const a = JSON.parse(localStorage.getItem('grav_admin_auth') || '{}');
    return { token: a.accessToken || '', env: a.environment || '' };
  } catch { return { token: '', env: '' }; }
}

function apiHeaders() {
  const { token, env } = getAuth();
  const h = {};
  if (token) h['Authorization'] = `Bearer ${token}`;
  if (env) h['X-Grav-Environment'] = env;
  return h;
}

class CodeshGrammarListField extends HTMLElement {
  _field = null;
  _value = null;
  _grammars = null;

  set field(f) { this._field = f; }
  set value(v) { this._value = v; }
  get value() { return this._value; }

  connectedCallback() {
    this.attachShadow({ mode: 'open' });
    this._render();
    this._loadGrammars();
  }

  async _loadGrammars() {
    try {
      const serverUrl = window.__GRAV_API_SERVER_URL || '';
      const apiPrefix = window.__GRAV_API_PREFIX || '/api/v1';
      const url = `${serverUrl}${apiPrefix}/codesh/grammars`;
      const resp = await fetch(url, { headers: apiHeaders() });
      const json = await resp.json();
      this._grammars = json.data || json;
      this._render();
    } catch (err) {
      console.error('[CodeshGrammarList] Failed to load grammars:', err);
    }
  }

  async _handleImport() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = async () => {
      const file = input.files?.[0];
      if (!file) return;

      const serverUrl = window.__GRAV_API_SERVER_URL || '';
      const apiPrefix = window.__GRAV_API_PREFIX || '/api/v1';
      const url = `${serverUrl}${apiPrefix}/codesh/grammars/import`;

      const form = new FormData();
      form.append('file', file);

      try {
        const resp = await fetch(url, { method: 'POST', headers: apiHeaders(), body: form });
        const json = await resp.json();
        if (!resp.ok) {
          alert(json.error || 'Import failed');
          return;
        }
        // Reload grammars
        this._grammars = null;
        this._render();
        this._loadGrammars();
      } catch (err) {
        alert('Import failed: ' + err.message);
      }
    };
    input.click();
  }

  async _deleteGrammar(slug) {
    if (!confirm(`Delete custom grammar "${slug}"?`)) return;
    const serverUrl = window.__GRAV_API_SERVER_URL || '';
    const apiPrefix = window.__GRAV_API_PREFIX || '/api/v1';
    try {
      const resp = await fetch(`${serverUrl}${apiPrefix}/codesh/grammars/${encodeURIComponent(slug)}`, {
        method: 'DELETE', headers: apiHeaders()
      });
      if (!resp.ok && resp.status !== 204) {
        alert('Failed to delete grammar');
        return;
      }
      this._grammars = null;
      this._render();
      this._loadGrammars();
    } catch (err) {
      alert('Failed to delete: ' + err.message);
    }
  }

  _render() {
    if (!this.shadowRoot) return;

    if (!this._grammars) {
      this.shadowRoot.innerHTML = `
        <style>:host { display: block; } .loading { text-align: center; padding: 24px;
          color: var(--color-muted-foreground, #64748b); font-size: 13px; }</style>
        <div class="loading">Loading grammars...</div>
      `;
      return;
    }

    const custom = this._grammars.custom || [];
    const builtin = this._grammars.builtin || [];

    const renderList = (grammars, showDelete = false) => {
      if (!grammars.length) return '<div class="empty">No custom grammars. Click "Import Grammar" to add one.</div>';
      return grammars.map(g => {
        const slug = g.slug || g.name || '';
        const aliases = (g.fileTypes || g.aliases || [])
          .filter(a => a !== slug && a.length <= 10)
          .slice(0, 3);
        const aliasHtml = aliases.length
          ? `<span class="aliases">(${this._esc(aliases.join(', '))})</span>` : '';
        const deleteHtml = showDelete
          ? `<button class="delete-btn" data-slug="${this._esc(slug)}" title="Delete">&times;</button>` : '';
        return `<div class="grammar"><code class="slug">${this._esc(slug)}</code> ${aliasHtml} ${deleteHtml}</div>`;
      }).join('');
    };

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: block; }
        .header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .header-text { flex: 1; font-size: 13px; color: var(--color-muted-foreground, #64748b); line-height: 1.5; }
        .header-text code { background: var(--color-muted, #f1f5f9); padding: 1px 5px;
          border-radius: 3px; font-size: 12px; }
        .import-btn { display: inline-flex; align-items: center; gap: 6px;
          padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500;
          cursor: pointer; white-space: nowrap;
          background: var(--color-primary, #3b82f6); color: white; border: none; }
        .import-btn:hover { opacity: 0.9; }
        .section-header { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; margin-top: 20px; }
        .section-header:first-of-type { margin-top: 0; }
        .section-header h4 { margin: 0; font-size: 14px; font-weight: 600;
          color: var(--color-foreground, #0f172a); }
        .count { font-size: 12px; color: var(--color-muted-foreground, #64748b); }
        .grammar-grid { column-count: 4; column-gap: 16px; }
        @media (max-width: 900px) { .grammar-grid { column-count: 3; } }
        @media (max-width: 600px) { .grammar-grid { column-count: 2; } }
        .grammar { break-inside: avoid; padding: 3px 0; font-size: 13px; line-height: 1.4; }
        .slug { font-family: ui-monospace, monospace; font-size: 12px;
          color: var(--color-primary, #3b82f6); background: none; padding: 0; }
        .aliases { font-size: 11px; color: var(--color-muted-foreground, #64748b); margin-left: 4px; }
        .empty { text-align: center; padding: 20px;
          color: var(--color-muted-foreground, #64748b); font-size: 13px;
          border: 1px dashed var(--color-border, #e2e8f0); border-radius: 8px; }
        .delete-btn { background: none; border: none; cursor: pointer; color: var(--color-muted-foreground, #94a3b8);
          font-size: 14px; line-height: 1; padding: 0 2px; vertical-align: middle; }
        .delete-btn:hover { color: #ef4444; }
        hr { border: none; border-top: 1px solid var(--color-border, #e2e8f0); margin: 16px 0; }
      </style>

      <div class="header">
        <div class="header-text">
          Manage TextMate grammar files for syntax highlighting. Use the alias as the <code>lang</code> attribute in code blocks.
        </div>
        <button class="import-btn" type="button" id="import-btn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Import Grammar
        </button>
      </div>

      ${custom.length ? `
        <div class="section-header">
          <h4>Custom Grammars</h4>
          <span class="count">(${custom.length})</span>
        </div>
        <div class="grammar-grid">${renderList(custom, true)}</div>
        <hr />
      ` : `
        <div class="section-header">
          <h4>Custom Grammars</h4>
          <span class="count">(0)</span>
        </div>
        ${renderList([])}
        <hr />
      `}

      <div class="section-header">
        <h4>Built-in Grammars</h4>
        <span class="count">(${builtin.length})</span>
      </div>
      <div class="grammar-grid">${renderList(builtin.length ? builtin : [{ slug: 'loading...' }])}</div>
    `;

    this.shadowRoot.getElementById('import-btn')?.addEventListener('click', () => this._handleImport());
    this.shadowRoot.querySelectorAll('.delete-btn[data-slug]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this._deleteGrammar(btn.dataset.slug);
      });
    });
  }

  _esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }
}

customElements.define(TAG, CodeshGrammarListField);
