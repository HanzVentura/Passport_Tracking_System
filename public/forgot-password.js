document.addEventListener('DOMContentLoaded', () => {
    const openBtn = document.getElementById('openForgotPasswordBtn');
    const modal = document.getElementById('forgotPasswordModal');
    const closeBtn = document.getElementById('closeForgotPasswordModal');
    const backToLoginLink = document.getElementById('fpBackToLoginLink');
    const doneBtn = document.getElementById('fpDoneBtn');

    const requestStep = document.getElementById('fpRequestStep');
    const confirmationStep = document.getElementById('fpConfirmationStep');
    const confirmationText = document.getElementById('fpConfirmationText');

    const form = document.getElementById('forgotPasswordForm');
    const emailInput = document.getElementById('fpEmailInput');
    const loginEmailInput = document.getElementById('login-email');
    const submitBtn = document.getElementById('fpSubmitBtn');
    const errorMsg = document.getElementById('fpErrorMessage');

    if (!openBtn || !modal || !form) return;

    function resetModalState() {
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
        resetModalState();

        // Pre-fill email if user already typed an email in the login input
        if (emailInput && loginEmailInput && loginEmailInput.value.includes('@')) {
            emailInput.value = loginEmailInput.value.trim();
        }

        modal.classList.remove('hidden');
        if (emailInput) emailInput.focus();
    }

    function closeModal(e) {
        if (e) e.preventDefault();
        modal.classList.add('hidden');
    }

    openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backToLoginLink) backToLoginLink.addEventListener('click', closeModal);
    if (doneBtn) doneBtn.addEventListener('click', closeModal);

    // Close when clicking the dark backdrop outside the card
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    if (emailInput) {
        emailInput.addEventListener('input', () => {
            if (errorMsg) {
                errorMsg.textContent = '';
                errorMsg.classList.add('hidden');
            }
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

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