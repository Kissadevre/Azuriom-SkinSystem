(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-integer-only]').forEach(function (input) {
            const minimum = Number.parseInt(input.dataset.minimum || '1', 10);
            const maximum = Number.parseInt(input.dataset.maximum || '', 10);

            function validateMinimum() {
                const value = Number.parseInt(input.value, 10);
                if (input.value !== '' && value < minimum) {
                    input.setCustomValidity(input.dataset.minimumMessage);
                } else if (input.value !== '' && Number.isInteger(maximum) && value > maximum) {
                    input.setCustomValidity(input.dataset.maximumMessage);
                } else {
                    input.setCustomValidity('');
                }
            }

            input.addEventListener('beforeinput', function (event) {
                if (event.data && !/^\d+$/.test(event.data)) {
                    event.preventDefault();
                }
            });

            input.addEventListener('input', function () {
                input.value = input.value.replace(/\D/g, '');
                validateMinimum();
            });

            validateMinimum();
        });

        document.querySelectorAll('[data-mineskin-integration]').forEach(function (integration) {
            const keyConfigured = integration.dataset.keyConfigured === 'true';
            const editorStartsOpen = integration.dataset.editorOpen === 'true';
            const editor = integration.querySelector('[data-mineskin-key-editor]');
            const keyInput = integration.querySelector('#mineSkinApiKey');
            const keyActions = integration.querySelector('[data-mineskin-key-actions]');
            const replaceButton = integration.querySelector('[data-mineskin-replace]');
            const cancelEditButton = integration.querySelector('[data-mineskin-cancel-edit]');
            const removeButton = integration.querySelector('[data-mineskin-remove]');
            const removalInput = document.getElementById('removeMineSkinApiKey');
            const removalNotice = integration.querySelector('[data-mineskin-removal-notice]');
            const cancelRemovalButton = integration.querySelector('[data-mineskin-cancel-removal]');
            const visibilityButton = integration.querySelector('[data-mineskin-key-visibility]');

            if (!editor || !keyInput || !removalInput) {
                return;
            }

            function setEditing(editing, focusInput) {
                editor.hidden = !editing;

                if (keyActions) {
                    keyActions.hidden = editing;
                }

                if (editing && focusInput) {
                    keyInput.focus();
                }
            }

            function setRemovalPending(pending) {
                removalInput.value = pending ? '1' : '0';
                keyInput.disabled = pending;

                if (removalNotice) {
                    removalNotice.hidden = !pending;
                }

                if (pending) {
                    keyInput.value = '';
                    setEditing(false, false);

                    if (keyActions) {
                        keyActions.hidden = true;
                    }
                } else if (keyActions) {
                    keyActions.hidden = false;
                }
            }

            if (replaceButton) {
                replaceButton.addEventListener('click', function () {
                    setRemovalPending(false);
                    setEditing(true, true);
                });
            }

            if (cancelEditButton) {
                cancelEditButton.addEventListener('click', function () {
                    keyInput.value = '';
                    keyInput.setCustomValidity('');
                    setEditing(false, false);
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    setRemovalPending(true);
                });
            }

            if (cancelRemovalButton) {
                cancelRemovalButton.addEventListener('click', function () {
                    setRemovalPending(false);
                });
            }

            if (visibilityButton) {
                visibilityButton.addEventListener('click', function () {
                    const shouldShow = keyInput.type === 'password';
                    const label = shouldShow
                        ? visibilityButton.dataset.hideLabel
                        : visibilityButton.dataset.showLabel;

                    keyInput.type = shouldShow ? 'text' : 'password';
                    visibilityButton.setAttribute('aria-label', label);
                    visibilityButton.setAttribute('title', label);
                    visibilityButton.querySelector('i').className = shouldShow ? 'bi bi-eye-slash' : 'bi bi-eye';
                });
            }

            keyInput.addEventListener('input', function () {
                if (removalInput.value === '1') {
                    setRemovalPending(false);
                }
            });

            setEditing(!keyConfigured || editorStartsOpen, false);
        });
    });
})();
