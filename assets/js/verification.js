document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.verification-code');
    if (!container) return;

    const inputs = Array.from(container.querySelectorAll('input'));
    if (inputs.length === 0) return;

    function focusIndex(index) {
        const target = inputs[index];
        if (target) {
            target.focus();
            target.select();
        }
    }

    inputs.forEach((input, index) => {
        input.addEventListener('input', (event) => {
            const value = event.target.value.replace(/\D/g, '');
            event.target.value = value.slice(0, 1);

            if (event.target.value && index < inputs.length - 1) {
                focusIndex(index + 1);
            }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !event.target.value && index > 0) {
                focusIndex(index - 1);
            }

            if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                focusIndex(index - 1);
            }

            if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                event.preventDefault();
                focusIndex(index + 1);
            }
        });

        input.addEventListener('paste', (event) => {
            const paste = (event.clipboardData || window.clipboardData).getData('text');
            const digits = paste.replace(/\D/g, '').slice(0, inputs.length);

            if (!digits) return;

            event.preventDefault();
            digits.split('').forEach((digit, offset) => {
                if (inputs[offset]) {
                    inputs[offset].value = digit;
                }
            });

            const nextIndex = Math.min(digits.length, inputs.length - 1);
            focusIndex(nextIndex);
        });
    });

    focusIndex(0);
});
