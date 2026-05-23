var buttons = document.querySelectorAll('[data-password-toggle]');

for (var i = 0; i < buttons.length; i++) {
    buttons[i].addEventListener('click', function () {
        var wrapper = this.parentElement;
        var input = wrapper ? wrapper.querySelector('input') : null;
        if (!input) {
            return;
        }

        var icon = this.querySelector('i');
        var label = this.getAttribute('data-password-label') || 'password';

        if (input.type === 'password') {
            input.type = 'text';
            this.setAttribute('aria-label', 'Hide ' + label);
            if (icon) {
                icon.className = 'fa-regular fa-eye-slash';
            }
        } else {
            input.type = 'password';
            this.setAttribute('aria-label', 'Show ' + label);
            if (icon) {
                icon.className = 'fa-regular fa-eye';
            }
        }
    });
}