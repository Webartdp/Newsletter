(function () {
    'use strict';

    if (window.DnepritNewsletterSubscribeLoaded) {
        return;
    }

    window.DnepritNewsletterSubscribeLoaded = true;

    function closestForm(element) {
        while (element && element !== document) {
            if (
                element.matches &&
                element.matches('[data-dneprit-newsletter-form]')
            ) {
                return element;
            }

            element = element.parentNode;
        }

        return null;
    }

    function setMessage(form, message, success) {
        var box = form.querySelector(
            '[data-dneprit-newsletter-message]'
        );

        if (!box) {
            return;
        }

        box.textContent = message || '';

        box.className =
            'dneprit-newsletter-message ' +
            (
                success
                    ? 'dneprit-newsletter-message-success'
                    : 'dneprit-newsletter-message-error'
            );
    }

    function setLoading(form, loading) {
        var button = form.querySelector(
            'button[type="submit"]'
        );

        if (button) {
            button.disabled = loading;

            if (loading) {
                button.setAttribute('aria-busy', 'true');
            } else {
                button.removeAttribute('aria-busy');
            }
        }

        if (loading) {
            form.classList.add('is-loading');

            form.setAttribute(
                'data-dneprit-newsletter-submitting',
                '1'
            );
        } else {
            form.classList.remove('is-loading');

            form.removeAttribute(
                'data-dneprit-newsletter-submitting'
            );
        }
    }

    function isSubmitting(form) {
        return form.getAttribute(
            'data-dneprit-newsletter-submitting'
        ) === '1';
    }

    function getField(form, name) {
        return form.querySelector(
            '[name="' + name + '"]'
        );
    }

    function getFieldValue(form, name) {
        var field = getField(form, name);

        if (!field) {
            return '';
        }

        return typeof field.value !== 'undefined'
            ? field.value
            : '';
    }

    function getToken(form) {
        var field = getField(
            form,
            'form_token'
        );

        if (!field) {
            return '';
        }

        var token = String(
            field.value || ''
        ).trim();

        if (!token) {
            token = String(
                field.getAttribute('value') || ''
            ).trim();
        }

        return token;
    }

    function updateToken(form, token) {
        if (!token) {
            return;
        }

        var field = getField(
            form,
            'form_token'
        );

        if (!field) {
            return;
        }

        field.value = token;
        field.setAttribute(
            'value',
            token
        );
    }

    function clearFieldErrors(form) {
        var fields = form.querySelectorAll(
            '[aria-invalid="true"]'
        );

        for (
            var index = 0;
            index < fields.length;
            index++
        ) {
            fields[index].removeAttribute(
                'aria-invalid'
            );
        }
    }

    function markFieldErrors(
        form,
        response
    ) {
        var errors =
            response.fieldErrors ||
            response.errors ||
            [];

        if (
            (!errors || !errors.length) &&
            response.object &&
            response.object.fieldErrors
        ) {
            errors =
                response.object.fieldErrors;
        }

        if (!errors || !errors.length) {
            return;
        }

        for (
            var index = 0;
            index < errors.length;
            index++
        ) {
            var key =
                errors[index].field ||
                errors[index].name ||
                errors[index].id ||
                '';

            if (!key) {
                continue;
            }

            var field = form.querySelector(
                '[name="' +
                key.replace(/"/g, '') +
                '"]'
            );

            if (field) {
                field.setAttribute(
                    'aria-invalid',
                    'true'
                );
            }
        }
    }

    function dispatchSuccess(
        form,
        response
    ) {
        var customEvent;

        if (
            typeof window.CustomEvent ===
            'function'
        ) {
            customEvent = new CustomEvent(
                'dnepritNewsletter:success',
                {
                    detail: response,
                    bubbles: true
                }
            );
        } else {
            customEvent =
                document.createEvent(
                    'CustomEvent'
                );

            customEvent.initCustomEvent(
                'dnepritNewsletter:success',
                true,
                true,
                response
            );
        }

        form.dispatchEvent(
            customEvent
        );
    }

    function buildFormData(
        form,
        token
    ) {
        var data = new FormData();

        data.append(
            'form_token',
            token
        );

        data.append(
            'email',
            getFieldValue(
                form,
                'email'
            )
        );

        data.append(
            'website',
            getFieldValue(
                form,
                'website'
            )
        );

        var nameField = getField(
            form,
            'name'
        );

        if (nameField) {
            data.append(
                'name',
                nameField.value || ''
            );
        }

        var consentField = getField(
            form,
            'consent'
        );

        if (
            consentField &&
            consentField.checked
        ) {
            data.append(
                'consent',
                '1'
            );
        }

        return data;
    }

    function submitForm(
        form,
        token
    ) {
        if (isSubmitting(form)) {
            return;
        }

        if (!token) {
            setMessage(
                form,
                'Не вдалося отримати токен форми. Оновіть сторінку.',
                false
            );

            return;
        }

        clearFieldErrors(form);

        if (
            typeof form.checkValidity ===
                'function' &&
            !form.checkValidity()
        ) {
            if (
                typeof form.reportValidity ===
                'function'
            ) {
                form.reportValidity();
            }

            return;
        }

        setLoading(
            form,
            true
        );

        setMessage(
            form,
            '',
            false
        );

        var formData = buildFormData(
            form,
            token
        );

        var request =
            new XMLHttpRequest();

        request.open(
            'POST',
            form.action,
            true
        );

        request.setRequestHeader(
            'X-Requested-With',
            'XMLHttpRequest'
        );

        request.setRequestHeader(
            'Accept',
            'application/json'
        );

        request.withCredentials = true;

        request.onreadystatechange =
            function () {
                if (
                    request.readyState !== 4
                ) {
                    return;
                }

                setLoading(
                    form,
                    false
                );

                var response;

                try {
                    response = JSON.parse(
                        request.responseText ||
                        '{}'
                    );
                } catch (error) {
                    response = {
                        success: false,
                        message:
                            'Не вдалося прочитати відповідь сервера.'
                    };
                }

                markFieldErrors(
                    form,
                    response
                );

                if (
                    request.status >= 200 &&
                    request.status < 300 &&
                    response.success
                ) {
                    var newToken = '';

                    if (
                        response.object &&
                        response.object.form_token
                    ) {
                        newToken =
                            response.object.form_token;
                    }

                    form.reset();

                    if (newToken) {
                        updateToken(
                            form,
                            newToken
                        );
                    }

                    setMessage(
                        form,
                        response.message,
                        true
                    );

                    dispatchSuccess(
                        form,
                        response
                    );
                } else {
                    updateToken(
                        form,
                        token
                    );

                    setMessage(
                        form,
                        response.message ||
                            'Помилка відправлення.',
                        false
                    );
                }
            };

        request.onerror =
            function () {
                setLoading(
                    form,
                    false
                );

                updateToken(
                    form,
                    token
                );

                setMessage(
                    form,
                    'Помилка мережі. Спробуйте ще раз.',
                    false
                );
            };

        request.send(
            formData
        );
    }

    document.addEventListener(
        'submit',
        function (event) {
            var form = closestForm(
                event.target
            );

            if (!form) {
                return;
            }

            var token = getToken(
                form
            );

            event.preventDefault();
            event.stopPropagation();

            if (
                typeof event.stopImmediatePropagation ===
                'function'
            ) {
                event.stopImmediatePropagation();
            }

            submitForm(
                form,
                token
            );
        },
        true
    );

}());
