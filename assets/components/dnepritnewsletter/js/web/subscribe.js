(function () {
    'use strict';

    function closestForm(element) {
        while (element && element !== document) {
            if (element.matches && element.matches('[data-dneprit-newsletter-form]')) {
                return element;
            }
            element = element.parentNode;
        }
        return null;
    }

    function setMessage(form, message, success) {
        var box = form.querySelector('[data-dneprit-newsletter-message]');
        if (!box) {
            return;
        }

        box.textContent = message || '';
        box.className = 'dneprit-newsletter-message ' +
            (success ? 'dneprit-newsletter-message-success' : 'dneprit-newsletter-message-error');
    }

    function setLoading(form, loading) {
        var button = form.querySelector('button[type="submit"]');
        if (!button) {
            return;
        }

        button.disabled = loading;
        if (loading) {
            button.setAttribute('aria-busy', 'true');
            form.classList.add('is-loading');
        } else {
            button.removeAttribute('aria-busy');
            form.classList.remove('is-loading');
        }
    }

    function clearFieldErrors(form) {
        var fields = form.querySelectorAll('[aria-invalid="true"]');
        for (var index = 0; index < fields.length; index++) {
            fields[index].removeAttribute('aria-invalid');
        }
    }

    function markFieldErrors(form, response) {
        var errors = response.fieldErrors || response.errors || [];
        if ((!errors || !errors.length) && response.object && response.object.fieldErrors) {
            errors = response.object.fieldErrors;
        }

        if (!errors || !errors.length) {
            return;
        }

        for (var index = 0; index < errors.length; index++) {
            var key = errors[index].field || errors[index].name || '';
            var field = key ? form.querySelector('[name="' + key.replace(/"/g, '') + '"]') : null;
            if (field) {
                field.setAttribute('aria-invalid', 'true');
            }
        }
    }

    function updateToken(form, response) {
        var token = response && response.object ? response.object.form_token : '';
        var field = form.querySelector('[name="form_token"]');
        if (field && token) {
            field.value = token;
        }
    }

    function dispatchSuccess(form, response) {
        var event;
        if (typeof window.CustomEvent === 'function') {
            event = new CustomEvent('dnepritNewsletter:success', {detail: response});
        } else {
            event = document.createEvent('CustomEvent');
            event.initCustomEvent('dnepritNewsletter:success', true, true, response);
        }
        form.dispatchEvent(event);
    }

    document.addEventListener('submit', function (event) {
        var form = closestForm(event.target);
        if (!form) {
            return;
        }

        event.preventDefault();
        clearFieldErrors(form);

        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            if (typeof form.reportValidity === 'function') {
                form.reportValidity();
            }
            return;
        }

        setLoading(form, true);
        setMessage(form, '', false);

        var request = new XMLHttpRequest();
        request.open('POST', form.action, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');
        request.withCredentials = true;

        request.onreadystatechange = function () {
            if (request.readyState !== 4) {
                return;
            }

            setLoading(form, false);
            var response;
            try {
                response = JSON.parse(request.responseText || '{}');
            } catch (error) {
                response = {success: false, message: 'Server response could not be read.'};
            }

            updateToken(form, response);
            markFieldErrors(form, response);

            if (request.status >= 200 && request.status < 300 && response.success) {
                var tokenField = form.querySelector('[name="form_token"]');
                var newToken = tokenField ? tokenField.value : '';
                form.reset();
                if (tokenField) {
                    tokenField.value = newToken;
                }
                setMessage(form, response.message, true);
                dispatchSuccess(form, response);
            } else {
                setMessage(form, response.message || 'Request failed.', false);
            }
        };

        request.onerror = function () {
            setLoading(form, false);
            setMessage(form, 'Network error. Please try again.', false);
        };

        request.send(new FormData(form));
    });
}());
