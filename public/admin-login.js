const adminLoginForm = document.getElementById('adminLoginForm');
const adminEmailInput = document.getElementById('admin-email');
const adminPasswordInput = document.getElementById('admin-password');
const adminPasscodeInput = document.getElementById('admin-passcode');
const adminTwoFaContainer = document.getElementById('admin-2fa-container');
const adminErrorBox = document.getElementById('admin-login-error');
const adminSubmitBtn = document.getElementById('admin-login-btn');

function showAdminMessage(message, isWarning = false) {
    if (!adminErrorBox) return;
    adminErrorBox.textContent = message;
    adminErrorBox.style.color = isWarning ? '#b45309' : '#dc2626';
    adminErrorBox.style.backgroundColor = isWarning ? '#fffbeb' : '#fef2f2';
    adminErrorBox.style.borderColor = isWarning ? '#fde68a' : '#fecaca';
    adminErrorBox.classList.remove('hidden');
}

function hideAdminMessage() {
    if (!adminErrorBox) return;
    adminErrorBox.textContent = '';
    adminErrorBox.classList.add('hidden');
}

document.querySelectorAll('.toggle-visibility-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (!input) return;

        const nextType = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', nextType);

        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = nextType === 'password' ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
        }
    });
});

if (adminLoginForm && adminEmailInput && adminPasswordInput) {
    adminEmailInput.addEventListener('input', hideAdminMessage);
    adminPasswordInput.addEventListener('input', hideAdminMessage);
    adminPasscodeInput?.addEventListener('input', hideAdminMessage);

    adminLoginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideAdminMessage();

        const email = adminEmailInput.value.trim();
        const password = adminPasswordInput.value;
        const passcode = adminPasscodeInput ? adminPasscodeInput.value.trim() : '';

        if (!email || !password) {
            showAdminMessage('Please enter both your admin email and password.');
            return;
        }

        if (adminSubmitBtn) {
            adminSubmitBtn.disabled = true;
        }

        try {
            const response = await fetch('../api/admin-login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password, passcode })
            });
            const result = await response.json();

            if (result.require_2fa) {
                if (adminTwoFaContainer) {
                    adminTwoFaContainer.classList.remove('hidden');
                }
                adminPasscodeInput?.focus();
                showAdminMessage(result.message || 'Two-factor authentication passcode required.', true);
            } else if (result.success) {
                localStorage.setItem('userEmail', email);
                localStorage.setItem('userRole', 'admin');
                window.location.href = 'admin.html';
            } else {
                showAdminMessage(result.message || 'Invalid administrator credentials.');
            }
        } catch (error) {
            showAdminMessage('Connection error. Make sure the server is running.');
        } finally {
            if (adminSubmitBtn) {
                adminSubmitBtn.disabled = false;
            }
        }
    });
}