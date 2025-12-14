/**
 * Codesh - Code Syntax Highlighter
 * Copy to clipboard functionality
 */
(function() {
    'use strict';

    /**
     * Copy code to clipboard
     */
    async function copyCode(button) {
        const block = button.closest('.codesh-block');
        if (!block) return;

        const codeEl = block.querySelector('.codesh-code code');
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
     * Initialize copy buttons
     */
    function init() {
        document.querySelectorAll('.codesh-copy').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                copyCode(this);
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
