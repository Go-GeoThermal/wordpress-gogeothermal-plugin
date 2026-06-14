(function () {
    'use strict';

    var checkbox = document.getElementById('ggt_linked_to_company');
    var row = document.getElementById('ggt_company_email_row');
    var input = document.getElementById('ggt_company_email');
    var validation = document.getElementById('ggt_company_email_validation');

    if (!checkbox || !row || !input || !validation || !window.ggtRegistrationCompanyLink) {
        return;
    }

    var validationState = {
        email: '',
        exists: false,
        pending: false,
        allowSubmit: false
    };

    /**
     * Display or clear the company-link validation message.
     */
    function setMessage(message) {
        validation.textContent = message || '';
        validation.hidden = !message;
    }

    /**
     * Toggle the company email row and clear validation when association is off.
     */
    function syncVisibility() {
        row.hidden = !checkbox.checked;

        if (!checkbox.checked) {
            validationState.allowSubmit = false;
            setMessage('');
            input.setCustomValidity('');
        }
    }

    /**
     * Validate the selected company email with WordPress AJAX.
     */
    function validateCompanyEmail() {
        var email = input.value.trim();

        if (!checkbox.checked) {
            return Promise.resolve(true);
        }

        if (!email) {
            input.setCustomValidity(window.ggtRegistrationCompanyLink.rejectedMessage);
            setMessage(window.ggtRegistrationCompanyLink.rejectedMessage);
            return Promise.resolve(false);
        }

        if (validationState.email === email && validationState.exists) {
            input.setCustomValidity('');
            setMessage('');
            return Promise.resolve(true);
        }

        validationState.pending = true;
        validationState.allowSubmit = false;

        var body = new URLSearchParams();
        body.append('action', 'ggt_validate_registration_company_email');
        body.append('nonce', window.ggtRegistrationCompanyLink.nonce);
        body.append('company_email', email);

        return fetch(window.ggtRegistrationCompanyLink.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                var data = payload && payload.data ? payload.data : {};
                var exists = Boolean(payload && payload.success && data.exists);
                var message = exists ? '' : (data.message || window.ggtRegistrationCompanyLink.rejectedMessage);

                validationState.email = email;
                validationState.exists = exists;
                validationState.pending = false;
                validationState.allowSubmit = exists;

                input.setCustomValidity(message);
                setMessage(message);

                return exists;
            })
            .catch(function () {
                validationState.email = email;
                validationState.exists = false;
                validationState.pending = false;
                validationState.allowSubmit = false;

                input.setCustomValidity(window.ggtRegistrationCompanyLink.validationErrorMessage);
                setMessage(window.ggtRegistrationCompanyLink.validationErrorMessage);

                return false;
            });
    }

    checkbox.addEventListener('change', function () {
        syncVisibility();
        if (checkbox.checked && input.value.trim()) {
            validateCompanyEmail();
        }
    });

    input.addEventListener('input', function () {
        validationState.email = '';
        validationState.exists = false;
        validationState.allowSubmit = false;
        input.setCustomValidity('');
        setMessage('');
    });

    input.addEventListener('blur', validateCompanyEmail);

    if (input.form) {
        input.form.addEventListener('submit', function (event) {
            if (!checkbox.checked || validationState.allowSubmit) {
                return;
            }

            var submitter = event.submitter || null;

            event.preventDefault();

            validateCompanyEmail().then(function (isValid) {
                if (!isValid) {
                    input.reportValidity();
                    return;
                }

                validationState.allowSubmit = true;
                if (typeof input.form.requestSubmit === 'function' && submitter) {
                    input.form.requestSubmit(submitter);
                } else if (typeof input.form.requestSubmit === 'function') {
                    input.form.requestSubmit();
                } else {
                    input.form.submit();
                }
            });
        });
    }

    syncVisibility();
}());