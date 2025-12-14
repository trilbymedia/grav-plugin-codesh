/**
 * Codesh - Code Syntax Highlighter
 * Copy to clipboard and code group tab functionality
 */
(function() {
    'use strict';

    const STORAGE_KEY = 'codesh-tabs';

    /**
     * Get stored tab preferences
     */
    function getStoredTabs() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch (e) {
            return {};
        }
    }

    /**
     * Store tab preference
     */
    function storeTab(syncKey, tabTitle) {
        try {
            const stored = getStoredTabs();
            stored[syncKey] = tabTitle;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
        } catch (e) {
            // Ignore storage errors
        }
    }

    /**
     * Copy code to clipboard
     */
    async function copyCode(button) {
        // Check if we're in a code group or a regular block
        const group = button.closest('.codesh-group');
        const block = button.closest('.codesh-block');

        let codeEl;
        if (group) {
            // For groups, get the active panel's code
            const activePanel = group.querySelector('.codesh-group-panel.active');
            codeEl = activePanel ? activePanel.querySelector('code') : null;
        } else if (block) {
            codeEl = block.querySelector('.codesh-code code');
        }

        if (!codeEl) return;

        try {
            // Clone and remove line numbers for clean copy
            const clone = codeEl.cloneNode(true);
            const lineNumbers = clone.querySelectorAll('.line-number');
            lineNumbers.forEach(el => el.remove());

            await navigator.clipboard.writeText(clone.textContent);

            // Show success state
            const textEl = button.querySelector('.codesh-copy-text');
            const iconEl = button.querySelector('.codesh-copy-icon');

            if (textEl) {
                const originalText = textEl.textContent;
                textEl.textContent = 'Copied!';
                button.classList.add('copied');

                // Swap icon to checkmark
                if (iconEl) {
                    iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
                }

                setTimeout(() => {
                    textEl.textContent = originalText;
                    button.classList.remove('copied');
                    if (iconEl) {
                        iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
                    }
                }, 2000);
            }
        } catch (err) {
            console.error('Codesh: Failed to copy code:', err);
        }
    }

    /**
     * Switch to a tab in a code group
     */
    function switchTab(group, tabButton) {
        const tabId = tabButton.dataset.tab;
        const tabTitle = tabButton.textContent.trim();

        // Update tab active states
        group.querySelectorAll('.codesh-group-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabId);
        });

        // Update panel active states
        group.querySelectorAll('.codesh-group-panel').forEach(panel => {
            panel.classList.toggle('active', panel.dataset.panel === tabId);
        });

        // Sync with other groups if sync key is present
        const syncKey = group.dataset.sync;
        if (syncKey) {
            storeTab(syncKey, tabTitle);
            syncTabs(syncKey, tabTitle, group);
        }
    }

    /**
     * Sync tabs across groups with same sync key
     */
    function syncTabs(syncKey, tabTitle, excludeGroup) {
        document.querySelectorAll(`.codesh-group[data-sync="${syncKey}"]`).forEach(group => {
            if (group === excludeGroup) return;

            // Find a tab with matching title
            const matchingTab = Array.from(group.querySelectorAll('.codesh-group-tab'))
                .find(tab => tab.textContent.trim() === tabTitle);

            if (matchingTab) {
                switchTab(group, matchingTab);
            }
        });
    }

    /**
     * Restore stored tab preferences
     */
    function restoreStoredTabs() {
        const stored = getStoredTabs();

        Object.entries(stored).forEach(([syncKey, tabTitle]) => {
            document.querySelectorAll(`.codesh-group[data-sync="${syncKey}"]`).forEach(group => {
                const matchingTab = Array.from(group.querySelectorAll('.codesh-group-tab'))
                    .find(tab => tab.textContent.trim() === tabTitle);

                if (matchingTab && !matchingTab.classList.contains('active')) {
                    switchTab(group, matchingTab);
                }
            });
        });
    }

    /**
     * Initialize copy buttons
     */
    function initCopyButtons() {
        document.querySelectorAll('.codesh-copy').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                copyCode(this);
            });
        });
    }

    /**
     * Initialize code groups
     */
    function initCodeGroups() {
        document.querySelectorAll('.codesh-group').forEach(group => {
            group.querySelectorAll('.codesh-group-tab').forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    switchTab(group, this);
                });
            });
        });

        // Restore stored preferences
        restoreStoredTabs();
    }

    /**
     * Initialize all functionality
     */
    function init() {
        initCopyButtons();
        initCodeGroups();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
