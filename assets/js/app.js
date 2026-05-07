document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.querySelector('[data-nav]');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            nav.classList.toggle('is-open');
        });
    }

    var confirmForms = document.querySelectorAll('[data-confirm]');

    for (var i = 0; i < confirmForms.length; i += 1) {
        confirmForms[i].addEventListener('submit', function (event) {
            var message = this.getAttribute('data-confirm') || 'Are you sure you want to continue?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    }
});
