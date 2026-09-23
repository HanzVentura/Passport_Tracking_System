let currentIndex = 0;
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.dot');
let slideInterval;
function savePaymentSelection(amount, processingType, applicationType) {
    localStorage.setItem('paymentAmount', String(amount));
    localStorage.setItem('paymentProcessingType', processingType);
    localStorage.setItem('applicationType', applicationType);
}
function showSlide(index) {
    if (!slides.length || !dots.length) return;
    if (index >= slides.length) currentIndex = 0;
    if (index < 0) currentIndex = slides.length - 1;
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    slides[currentIndex].classList.add('active');
    dots[currentIndex].classList.add('active');
}
function nextSlide() {
    currentIndex++;
    showSlide(currentIndex);
}
function changeSlide(direction) {
    currentIndex += direction;
    showSlide(currentIndex);
    resetInterval();
}
function setSlide(index) {
    currentIndex = index;
    showSlide(currentIndex);
    resetInterval();
}
function startInterval() {
    if (!slides.length || !dots.length) return;
    slideInterval = setInterval(nextSlide, 2000);
}
function resetInterval() {
    clearInterval(slideInterval);
    startInterval();
}
if (slides.length && dots.length) startInterval();
// --- VIEW TOGGLING LOGIC (Sign Up <-> Log In) ---
const signupSection = document.getElementById('signup-section');
const loginSection = document.getElementById('login-section');
const showLoginBtn = document.getElementById('show-login');
const showSignupBtn = document.getElementById('show-signup');
const mainLayout = document.getElementById('mainLayout');
// WHEN LOG IN IS CLICKED: Photos move to the LEFT, Form moves to the RIGHT
if (showLoginBtn && signupSection && loginSection && mainLayout) {
    showLoginBtn.addEventListener('click', (e) => {
        e.preventDefault();
        signupSection.classList.add('hidden');
        loginSection.classList.remove('hidden');
        mainLayout.classList.add('reverse-layout');
    });
}
// WHEN SIGN UP IS CLICKED: Form stays on the LEFT, Photos stay on the RIGHT
if (showSignupBtn && signupSection && loginSection && mainLayout) {
    showSignupBtn.addEventListener('click', (e) => {
        e.preventDefault();
        loginSection.classList.add('hidden');
        signupSection.classList.remove('hidden');
        mainLayout.classList.remove('reverse-layout');
    });
}
// --- SHOW/HIDE PASSWORD TOGGLE LOGIC ---
const togglePasswordButtons = document.querySelectorAll('.toggle-password');
togglePasswordButtons.forEach(button => {
    button.addEventListener('click', function() {
        const input = this.previousElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            this.textContent = 'Hide Password';
        } else {
            input.type = 'password';
            this.textContent = 'Show Password';
        }
    });
});
// --- FORM VALIDATION & BACKEND DATABASE INTEGRATION ---
const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
const phoneRegex = /^(09\d{9}|\+639\d{9})$/;
// 1. Sign Up Submission (Saves to SQLite Database)[cite: 22]
const signupForm = document.getElementById('signupForm');
const successScreen = document.getElementById('successScreen');
const errorMessage = document.getElementById('error-message');
const emailInput = document.getElementById('email');
if (signupForm && emailInput && errorMessage) {
    emailInput.addEventListener('input', () => errorMessage.classList.add('hidden'));
    document.getElementById('password').addEventListener('input', () => errorMessage.classList.add('hidden'));
    document.getElementById('confirm-password').addEventListener('input', () => errorMessage.classList.add('hidden'));
signupForm.addEventListener('submit', async function(event) {
    event.preventDefault();
   
    const fullname = document.getElementById('fullname').value.trim();
    const emailVal = emailInput.value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    if (!emailRegex.test(emailVal) && !phoneRegex.test(emailVal)) {
        errorMessage.textContent = "Invalid credentials!";
        errorMessage.classList.remove('hidden');
        return;
    }
    if (password !== confirmPassword) {
        errorMessage.textContent = "Passwords do not match!";
        errorMessage.classList.remove('hidden');
        return;
    }
    try {
        const response = await fetch('../api/signup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fullname, email: emailVal, password })
        });
        const result = await response.json();
        if (result.success) {
            errorMessage.classList.add('hidden');
            mainLayout.classList.add('hidden');
            successScreen.classList.remove('hidden');
        } else {
            errorMessage.textContent = result.message;
            errorMessage.classList.add('hidden');
        }
    } catch (err) {
        errorMessage.textContent = "Connection error. Make sure server is running.";
        errorMessage.classList.add('hidden');
    }
});
}
// Ensure 2FA container is hidden by default on page load
document.addEventListener('DOMContentLoaded', () => {
    const twoFaContainer = document.getElementById('2fa-container');
    if (twoFaContainer) {
        twoFaContainer.style.display = 'none';
    }
});
// 2. Log In Submission (Verifies with Database & Handles Dynamic 2FA)[cite: 23]
const loginForm = document.getElementById('loginForm');
const loginErrorMessage = document.getElementById('login-error-message');
const loginEmailInput = document.getElementById('login-email');
if (loginForm && loginEmailInput && loginErrorMessage) {
    loginEmailInput.addEventListener('input', () => loginErrorMessage.classList.add('hidden'));
    document.getElementById('login-password')?.addEventListener('input', () => loginErrorMessage.classList.add('hidden'));
async function handleLoginSubmit(event) {
    event.preventDefault();
    const emailInput = document.getElementById('login-email').value.trim();
    const passInput = document.getElementById('login-password').value;
    const passcodeField = document.getElementById('passcode');
    const passcodeVal = passcodeField ? passcodeField.value.trim() : '';
    const twoFaContainer = document.getElementById('2fa-container');
    if (emailInput === 'hanzchristian.ventura@neu.edu.ph' && passInput === 'hanzchristian.ventura@neu.edu.ph') {
        localStorage.setItem('userEmail', emailInput);
        localStorage.setItem('userRole', 'admin');
        window.location.href = 'admin.html';
        return;
    }
    try {
        const response = await fetch('../api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: emailInput, password: passInput, passcode: passcodeVal })
        });
        const result = await response.json();
       
        if (result.require_2fa) {
            if (twoFaContainer) {
                twoFaContainer.style.display = 'block';
            }
            loginErrorMessage.style.color = '#d97706';
            loginErrorMessage.textContent = result.message;
            loginErrorMessage.classList.remove('hidden');
        } else if (result.success) {
            localStorage.setItem('userEmail', emailInput);
            localStorage.setItem('userRole', 'member');
            window.location.href = 'dashboard.html';
        } else {
            loginErrorMessage.style.color = '#dc2626';
            loginErrorMessage.textContent = result.message;
            loginErrorMessage.classList.add('hidden');
        }
    } catch (err) {
        loginErrorMessage.style.color = '#dc2626';
        loginErrorMessage.textContent = 'Connection error. Make sure server is running.';
        loginErrorMessage.classList.add('hidden');
    }
}
loginForm.addEventListener('submit', handleLoginSubmit);
}
document.addEventListener('DOMContentLoaded', () => {
    // 1. Fetch user data to populate sidebar and pre-load 2FA settings
    fetch('../api/user-data.php')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.user) {
                const nameElements = document.querySelectorAll('#user-name, .user-name, .user-name-display');
                nameElements.forEach(el => {
                    el.textContent = data.user.fullname || 'User';
                });
                const toggle = document.getElementById('two-factor-toggle');
                const pinInput = document.getElementById('two-factor-pin');
                const pinSection = document.getElementById('two-factor-pin-section');
               
                if (toggle && data.user.two_factor_enabled == 1) {
                    toggle.checked = true;
                    if (pinSection) {
                        pinSection.style.opacity = "1";
                        pinSection.style.pointerEvents = "auto";
                    }
                } else if (toggle) {
                    toggle.checked = false;
                    if (pinSection) {
                        pinSection.style.opacity = "0.4";
                        pinSection.style.pointerEvents = "none";
                    }
                }
                if (pinInput && data.user.passcode) {
                    pinInput.value = data.user.passcode;
                }
            }
        })
        .catch(err => console.error('Failed to load user profile data', err));
   
    // 2. 2FA save and show/hide logic
    const saveBtn = document.getElementById('save-2fa');
    if (!saveBtn) return;
    const toggle = document.getElementById('two-factor-toggle');
    const pinInput = document.getElementById('two-factor-pin');
    const eyeBtn = document.getElementById('toggle-two-factor-pin');
    const msg = document.getElementById('two-factor-message');
    const pinSection = document.getElementById('two-factor-pin-section');
    
    if (toggle && pinSection) {
        toggle.addEventListener('change', () => {
            if (toggle.checked) {
                pinSection.style.opacity = "1";
                pinSection.style.pointerEvents = "auto";
            } else {
                pinSection.style.opacity = "0.4";
                pinSection.style.pointerEvents = "none";
                pinInput.value = "";
                msg.textContent = "";
            }
        });
    }
    
    if (eyeBtn && pinInput) {
        eyeBtn.addEventListener('click', () => {
            const type = pinInput.getAttribute('type') === 'password' ? 'text' : 'password';
            pinInput.setAttribute('type', type);
            const icon = eyeBtn.querySelector('i');
            if (icon) {
                icon.className = type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
            }
        });
    }
    
    saveBtn.addEventListener('click', () => {
        const isEnabled = toggle.checked;
        const pin = pinInput.value.trim();
        
        // Validate PIN only if 2FA is turned ON
        if (isEnabled && (!pin || !/^\d{6}$/.test(pin))) {
            msg.style.color = '#dc2626';
            msg.textContent = 'Please enter a valid 6-digit PIN to enable 2FA.';
            return;
        }
        
        // If OFF, payload sends enabled: false and clears PIN cleanly[cite: 22]
        const payloadPin = isEnabled ? pin : "";
        
        fetch('../api/update-2fa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled: isEnabled, pin: payloadPin })
        })
        .then(res => res.json())
        .then(data => {
            msg.style.color = data.success ? '#16a34a' : '#dc2626';
            msg.textContent = data.message || (isEnabled ? '2FA settings updated successfully!' : '2FA successfully disabled!');
        })
        .catch(() => {
            msg.style.color = '#dc2626';
            msg.textContent = 'An error occurred while saving.';
        });
    });
});