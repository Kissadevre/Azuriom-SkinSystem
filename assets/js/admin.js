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
    });
})();
