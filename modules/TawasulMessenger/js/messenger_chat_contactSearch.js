/*
TawasulChat — the people picker on the new-chat screen.

The multiple select is filled server-side with the first page of contacts. This
narrows it as the user types, against the endpoint's contact search, so someone
with a thousand colleagues does not have to scroll a thousand-entry list.

It is deliberately an enhancement: the form already works with the options that
were rendered on the server, so a failed search costs convenience, not function.
*/

(function () {
    'use strict';

    var field = document.getElementById('search');
    var select = document.querySelector('select[name="personIDs[]"], select[name="personIDs"]');

    if (!field || !select) { return; }

    // The endpoint URL and CSRF token are already on the page: the form that
    // posts to chat_newProcess.php carries both.
    var form = document.querySelector('form');
    var csrfInput = form ? form.querySelector('input[name="csrftoken"]') : null;
    var ajaxURL = window.location.pathname.replace(/chat_new\.php.*$/, 'chat_ajax.php');
    var config = null;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function rememberChoices() {
        var chosen = Array.prototype.slice
            .call(select.selectedOptions)
            .map(function (option) { return option.value; });

        return {
            choices: chosen,
            selections: {}
        };
    }

    var memory = rememberChoices();

    function search() {
        var term = field.value.trim();
        if (term.length < 1) { return; }

        var body = new URLSearchParams();
        body.set('chatAction', 'contacts');
        body.set('q', term);
        if (csrfInput) { body.set('csrftoken', csrfInput.value); }

        fetch(ajaxURL + '?' + body.toString(), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!payload.ok || !payload.contacts) { return; }

                var previous = {};
                Array.prototype.slice.call(select.options).forEach(function (option) {
                    previous[option.value] = option.selected;
                });

                select.innerHTML = payload.contacts.map(function (person) {
                    var id = String(person.tawasulPersonID);
                    var selected = previous[id] || memory.choices.indexOf(id) !== -1;
                    return '<option value="' + escapeHtml(id) + '"' + (selected ? ' selected' : '') + '>' +
                        escapeHtml(person.title) + ' — ' + escapeHtml(person.roleName || '') + '</option>';
                }).join('');
            })
            .catch(function () { /* keep whatever the server rendered */ });
    }

    var timer = null;
    field.addEventListener('input', function () {
        window.clearTimeout(timer);
        // Debounced: this is one request per pause in typing rather than one per
        // keystroke, which matters when every keystroke would otherwise hit the
        // database.
        timer = window.setTimeout(search, 250);
    });
})();
