(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-skinsystem-viewer]');

        if (!root) {
            return;
        }

        const canvas = root.querySelector('canvas');
        const placeholder = root.querySelector('[data-viewer-placeholder]');
        const error = document.querySelector('[data-viewer-error]');
        const fileInput = document.getElementById('skinInput');
        const variantInput = document.getElementById('variantInput');

        if (!canvas || typeof window.skinview3d === 'undefined') {
            if (error) {
                error.hidden = false;
            }

            return;
        }

        let viewer;

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
            if (error) {
                error.hidden = false;
            }

            if (placeholder) {
                placeholder.hidden = false;
            }

            return;
        }

        let previewUrl = root.dataset.skinUrl || null;
        let objectUrl = null;

        function selectedModel() {
            if (!variantInput || variantInput.value === 'auto') {
                return 'auto-detect';
            }

            return variantInput.value === 'classic' ? 'default' : 'slim';
        }

        function setLoadingState(isVisible) {
            if (placeholder) {
                placeholder.hidden = !isVisible;
            }
        }

        function showError() {
            if (error) {
                error.hidden = false;
            }
        }

        async function loadSkin(url) {
            if (!url) {
                viewer.resetSkin();
                setLoadingState(true);

                return;
            }

            try {
                await viewer.loadSkin(url, { model: selectedModel() });
                setLoadingState(false);

                if (error) {
                    error.hidden = true;
                }
            } catch (exception) {
                viewer.resetSkin();
                setLoadingState(true);
                showError();
            }
        }

        function resizeViewer() {
            const width = Math.max(240, Math.min(360, root.clientWidth - 24));
            viewer.width = width;
            viewer.height = Math.round(width * 1.4);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const file = fileInput.files && fileInput.files[0];

                if (!file) {
                    return;
                }

                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                }

                objectUrl = URL.createObjectURL(file);
                previewUrl = objectUrl;
                loadSkin(previewUrl);
            });
        }

        if (variantInput) {
            variantInput.addEventListener('change', function () {
                loadSkin(previewUrl);
            });
        }

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

            viewer.dispose();
        }, { once: true });

        resizeViewer();
        loadSkin(previewUrl);
    });
})();
