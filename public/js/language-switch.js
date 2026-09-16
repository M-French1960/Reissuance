/**
 * Submits the language form as soon as the choice changes.
 *
 * Progressive enhancement, and nothing more: without this file the select and
 * its button still work, which is why the button is only hidden once this
 * code has run and taken the job over.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-lang-switch]').forEach(function (form) {
        var go = form.querySelector('[data-lang-go]');
        var select = form.querySelector('select');

        if (!go || !select) {
            return;
        }

        go.hidden = true;
        select.addEventListener('change', function () {
            form.submit();
        });
    });
});
