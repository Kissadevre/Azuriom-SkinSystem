(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-skinsystem-viewer]');
        const form = document.querySelector('[data-skinsystem-upload]');

        if (!root || !form) {
            return;
        }

        const canvas = root.querySelector('canvas');
        const placeholder = root.querySelector('[data-viewer-placeholder]');
        const loading = root.querySelector('[data-viewer-loading]');
        const viewerError = document.querySelector('[data-viewer-error]');
        const fileInput = form.querySelector('#skinInput');
        const variantInputs = form.querySelectorAll('input[name="variant"]');
        const dropzone = form.querySelector('[data-skin-dropzone]');
        const dropzoneCopy = form.querySelector('[data-dropzone-copy]');
        const selection = form.querySelector('[data-selected-file]');
        const fileName = form.querySelector('[data-file-name]');
        const fileSize = form.querySelector('[data-file-size]');
        const fileError = form.querySelector('[data-file-error]');
        const submitButton = form.querySelector('[data-upload-submit]');
        const submitLabel = form.querySelector('[data-submit-label]');
        const submitLoading = form.querySelector('[data-submit-loading]');
        const saveOpenButton = form.querySelector('[data-save-skin-open]');
        const saveModal = document.getElementById('saveSkinModal');
        const maxFileSize = 3 * 1024 * 1024;
        let previewUrl = root.dataset.skinUrl || null;
        let objectUrl = null;
        let viewer = null;

        if (saveModal) {
            const nameInput = saveModal.querySelector('#skinName');
            const replacementInput = saveModal.querySelector('#replacementSkin');

            saveModal.addEventListener('show.bs.modal', function () {
                if (nameInput) nameInput.required = true;
                if (replacementInput) replacementInput.required = true;
            });

            saveModal.addEventListener('shown.bs.modal', function () {
                if (nameInput) nameInput.focus();
            });

            saveModal.addEventListener('hidden.bs.modal', function () {
                if (nameInput) nameInput.required = false;
                if (replacementInput) replacementInput.required = false;
            });

            if (saveModal.hasAttribute('data-open-on-load') && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(saveModal).show();
            }
        }

        const deleteSavedModal = document.getElementById('deleteSavedSkinModal');

        if (deleteSavedModal) {
            deleteSavedModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const deleteForm = deleteSavedModal.querySelector('[data-delete-saved-form]');
                const message = deleteSavedModal.querySelector('[data-delete-saved-message]');

                if (!button || !deleteForm || !message) {
                    return;
                }

                deleteForm.action = button.dataset.deleteSavedUrl;
                message.textContent = message.dataset.messageTemplate.replace(':name', button.dataset.deleteSavedName);
            });
        }

        function selectedModel() {
            const selected = form.querySelector('input[name="variant"]:checked');

            if (!selected || selected.value === 'auto') {
                return 'auto-detect';
            }

            return selected.value === 'classic' ? 'default' : 'slim';
        }

        function setViewerState(state) {
            if (placeholder) {
                placeholder.hidden = state !== 'empty';
            }

            if (loading) {
                loading.hidden = state !== 'loading';
            }
        }

        function showViewerError(show) {
            if (viewerError) {
                viewerError.hidden = !show;
            }
        }

        async function loadSkin(url) {
            if (!viewer || !url) {
                if (viewer) {
                    viewer.resetSkin();
                }

                setViewerState('empty');
                return;
            }

            setViewerState('loading');
            showViewerError(false);

            try {
                await viewer.loadSkin(url, { model: selectedModel() });
                setViewerState('ready');
            } catch (exception) {
                viewer.resetSkin();
                setViewerState('empty');
                showViewerError(true);
            }
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) {
                return bytes + ' B';
            }

            if (bytes < 1024 * 1024) {
                return (bytes / 1024).toFixed(1) + ' KB';
            }

            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function fileValidationMessage(file) {
            const hasPngExtension = file.name.toLowerCase().endsWith('.png');
            const hasPngMime = !file.type || file.type === 'image/png';

            if (!hasPngExtension || !hasPngMime) {
                return dropzone ? dropzone.dataset.invalidType : 'Choose a PNG image.';
            }

            if (file.size > maxFileSize) {
                return dropzone ? dropzone.dataset.invalidSize : 'The PNG must not exceed 3 MB.';
            }

            return '';
        }

        function setFileError(message) {
            fileInput.setCustomValidity(message);

            if (fileError) {
                fileError.textContent = message;
                fileError.hidden = !message;
            }

            if (dropzone) {
                dropzone.classList.toggle('is-invalid', Boolean(message));
            }
        }

        function updateFileSelection() {
            const file = fileInput.files && fileInput.files[0];

            if (!file) {
                setFileError('');

                if (dropzoneCopy) dropzoneCopy.hidden = false;
                if (selection) selection.hidden = true;
                if (submitButton) submitButton.disabled = true;
                if (saveOpenButton) saveOpenButton.disabled = true;

                return;
            }

            const validationMessage = fileValidationMessage(file);
            setFileError(validationMessage);

            if (fileName) fileName.textContent = file.name;
            if (fileSize) fileSize.textContent = formatFileSize(file.size);
            if (dropzoneCopy) dropzoneCopy.hidden = true;
            if (selection) selection.hidden = false;
            if (submitButton) submitButton.disabled = Boolean(validationMessage);
            if (saveOpenButton) saveOpenButton.disabled = Boolean(validationMessage);

            if (validationMessage) {
                return;
            }

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }

            objectUrl = URL.createObjectURL(file);
            previewUrl = objectUrl;
            loadSkin(previewUrl);
        }

        if (canvas && typeof window.skinview3d !== 'undefined') {
            try {
                viewer = new window.skinview3d.SkinViewer({
                    canvas: canvas,
                    width: 320,
                    height: 448,
                    zoom: 0.82,
                    fov: 55,
                });
                viewer.animation = new window.skinview3d.IdleAnimation();
                viewer.autoRotate = true;
                viewer.autoRotateSpeed = 0.55;
            } catch (exception) {
                showViewerError(true);
                setViewerState('empty');
            }
        } else {
            showViewerError(true);
        }

        function resizeViewer() {
            if (!viewer) {
                return;
            }

            const width = Math.max(240, Math.min(380, root.clientWidth - 24));
            viewer.width = width;
            viewer.height = Math.round(width * 1.4);
        }

        fileInput.addEventListener('change', updateFileSelection);

        variantInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                loadSkin(previewUrl);
            });
        });

        if (dropzone) {
            ['dragenter', 'dragover'].forEach(function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.add('is-dragging');
                });
            });

            ['dragleave', 'drop'].forEach(function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.remove('is-dragging');
                });
            });

            dropzone.addEventListener('drop', function (event) {
                const files = event.dataTransfer && event.dataTransfer.files;

                if (!files || files.length !== 1) {
                    return;
                }

                try {
                    fileInput.files = files;
                    updateFileSelection();
                } catch (exception) {
                    // Some browsers do not allow assigning a dropped FileList.
                }
            });
        }

        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }

            const submitter = event.submitter;

            if (submitter && submitter.name === 'action') {
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = submitter.value;
                form.appendChild(actionInput);
                submitter.disabled = true;
            }

            if (submitter === submitButton) {
                if (submitLabel) submitLabel.hidden = true;
                if (submitLoading) submitLoading.hidden = false;
            }
        });

        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(resizeViewer);
            observer.observe(root);
        } else {
            window.addEventListener('resize', resizeViewer);
        }

        window.addEventListener('pagehide', function () {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }

            if (viewer) {
                viewer.dispose();
            }
        }, { once: true });

        if (submitButton) submitButton.disabled = !fileInput.files.length;
        if (saveOpenButton) saveOpenButton.disabled = !fileInput.files.length;
        resizeViewer();
        loadSkin(previewUrl);
    });
})();
