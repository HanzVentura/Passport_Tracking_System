document.addEventListener('DOMContentLoaded', () => {
    function getModalElements() {
        return {
            modal: document.getElementById('forgotPasswordModal'),
            requestStep: document.getElementById('fpRequestStep'),
            confirmationStep: document.getElementById('fpConfirmationStep'),
            confirmationText: document.getElementById('fpConfirmationText'),
            form: document.getElementById('forgotPasswordForm'),
            emailInput: document.getElementById('fpEmailInput'),
            loginEmailInput: document.getElementById('login-email'),
            submitBtn: document.getElementById('fpSubmitBtn'),
            errorMsg: document.getElementById('fpErrorMessage')
        };
    }

    function resetModalState() {
        const { errorMsg, confirmationStep, requestStep, submitBtn } = getModalElements();
        if (errorMsg) {
            errorMsg.textContent = '';
            errorMsg.classList.add('hidden');
        }
        if (confirmationStep) confirmationStep.classList.add('hidden');
        if (requestStep) requestStep.classList.remove('hidden');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Reset Link';
        }
    }

    function openModal(e) {
        if (e) e.preventDefault();
        const { modal, emailInput, loginEmailInput } = getModalElements();
        if (!modal) return;

        resetModalState();

        if (emailInput && loginEmailInput && loginEmailInput.value.includes('@')) {
            emailInput.value = loginEmailInput.value.trim();
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        if (emailInput) emailInput.focus();
    }

    function closeModal(e) {
        if (e) e.preventDefault();
        const { modal } = getModalElements();
        if (!modal) return;

        modal.classList.add('hidden');
        modal.style.display = '';
    }

    // Delegated click listener so Forgot Password & modal controls always stay clickable
    document.addEventListener('click', (e) => {
        const openTrigger = e.target.closest('#openForgotPasswordBtn, .forgot-password-link');
        if (openTrigger) {
            openModal(e);
            return;
        }

        const closeTrigger = e.target.closest('#closeForgotPasswordModal, #fpBackToLoginLink, #fpDoneBtn');
        if (closeTrigger) {
            closeModal(e);
            return;
        }

        const { modal } = getModalElements();
        if (modal && e.target === modal) {
            closeModal(e);
        }
    });

    // Clear error message while typing
    document.addEventListener('input', (e) => {
        if (e.target && e.target.id === 'fpEmailInput') {
            const { errorMsg } = getModalElements();
            if (errorMsg) {
                errorMsg.textContent = '';
                errorMsg.classList.add('hidden');
            }
        }
    });

    // Delegated form submission handler
    document.addEventListener('submit', async (e) => {
        if (!e.target || e.target.id !== 'forgotPasswordForm') return;
        e.preventDefault();

        const { emailInput, submitBtn, errorMsg, requestStep, confirmationStep, confirmationText } = getModalElements();
        const email = emailInput ? emailInput.value.trim() : '';

        if (!email) {
            if (errorMsg) {
                errorMsg.textContent = 'Please enter your email address.';
                errorMsg.classList.remove('hidden');
            }
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
        }
        if (errorMsg) errorMsg.classList.add('hidden');

        try {
            const response = await fetch('../api/forgot-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });
            const result = await response.json();

            if (result.success) {
                if (requestStep) requestStep.classList.add('hidden');
                if (confirmationText) {
                    confirmationText.textContent =
                        result.message ||
                        `If an account exists for ${email}, we've sent password reset instructions. Please check your inbox and spam folder.`;
                }
                if (confirmationStep) confirmationStep.classList.remove('hidden');
            } else {
                if (errorMsg) {
                    errorMsg.textContent = result.message || 'Please enter a valid email address.';
                    errorMsg.classList.remove('hidden');
                }
            }
        } catch (err) {
            if (errorMsg) {
                errorMsg.textContent = 'Connection error. Make sure XAMPP Apache is running.';
                errorMsg.classList.remove('hidden');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Reset Link';
            }
        }
    });
});