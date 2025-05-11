function togglePassword() {
    const password = document.getElementById('password');
    const toggleIcon = document.querySelector('.password-toggle');
    
    if (password.type === 'password') {
        password.type = 'text';
        toggleIcon.classList.remove('bx-show');
        toggleIcon.classList.add('bx-hide');
    } else {
        password.type = 'password';
        toggleIcon.classList.remove('bx-hide');
        toggleIcon.classList.add('bx-show');
    }
}

function validateInput(input, groupId) {
    const group = document.getElementById(groupId);
    if (input.value.trim() !== '') {
        group.classList.add('valid');
    } else {
        group.classList.remove('valid');
    }
}

function checkPassword(input) {
    const val = input.value;
    document.getElementById('rule1').classList.toggle('valid', val.length >= 8);
    document.getElementById('rule2').classList.toggle('valid', /[0-9\W]/.test(val));
    document.getElementById('rule3').classList.toggle('valid', /[a-z]/.test(val) && /[A-Z]/.test(val));
    validateInput(input, 'password-group');
}

function autoCompleteEmail(input) {
    const value = input.value;
    // Check if the input contains '@' and no domain is typed yet
    if (value.includes('@') && !value.includes('@central.edu.gh') && !value.substring(value.indexOf('@') + 1).includes('.')) {
        const username = value.substring(0, value.indexOf('@'));
        input.value = username + '@central.edu.gh';
        // Move cursor to just before '@central.edu.gh'
        input.setSelectionRange(username.length, username.length);
        validateInput(input, 'email-group');
    } else {
        validateInput(input, 'email-group');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation
    const emailInput = document.querySelector('input[type="email"]');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.querySelector('.password-toggle');
    
    // Pre-fill email from sessionStorage if available (after signup redirect)
    if (emailInput && sessionStorage.getItem('signup_email')) {
        emailInput.value = sessionStorage.getItem('signup_email');
        validateInput(emailInput, 'email-group');
        // Clear the stored email to prevent reuse after login
        sessionStorage.removeItem('signup_email');
    }
    
    // Initialize password validation if content exists
    if (passwordInput && passwordInput.value) {
        checkPassword(passwordInput);
    }
    
    // Set up event listeners
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            autoCompleteEmail(this);
        });
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            checkPassword(this);
        });
    }
    
    if (toggleIcon) {
        toggleIcon.addEventListener('click', togglePassword);
    }
    
    // Login form submission
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = this.email.value;
            const password = this.password.value;
            const errorElement = document.getElementById('loginError');
            
            // Reset error display
            errorElement.style.display = 'none';
            errorElement.textContent = '';
            
            // Basic client-side validation
            if (!email) {
                errorElement.textContent = 'Please enter your email address';
                errorElement.style.display = 'block';
                return;
            }
            
            if (!email.endsWith('@central.edu.gh')) {
                errorElement.textContent = 'Email must end with @central.edu.gh';
                errorElement.style.display = 'block';
                return;
            }
            
            if (!password) {
                errorElement.textContent = 'Please enter your password';
                errorElement.style.display = 'block';
                return;
            }
            
            try {
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.textContent = 'Authenticating...';
                submitBtn.disabled = true;
                
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(new FormData(this))
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Store user data in sessionStorage
                    sessionStorage.setItem('user_id', data.user_id || '');
                    sessionStorage.setItem('email', email);
                    sessionStorage.setItem('role', data.role);
                    
                    // Redirect based on role
                    if (data.role === 'student') {
                        window.location.href = 'student-dashboard.html';
                    } else if (data.role === 'lecturer') {
                        window.location.href = 'lecturer-dashboard.html';
                    } else {
                        window.location.href = 'dashboard.html';
                    }
                } else {
                    // Append email as query parameter to signup link for "No password set" error
                    let message = data.message;
                    if (message.includes('No password set')) {
                        message = `No password set. Please <a href="signup.html?email=${encodeURIComponent(email)}">create one</a>.`;
                    }
                    errorElement.innerHTML = message;
                    errorElement.style.display = 'block';
                }
            } catch (error) {
                console.error('Login error:', error);
                errorElement.textContent = 'An error occurred. Please try again.';
                errorElement.style.display = 'block';
            } finally {
                const submitBtn = document.querySelector('#loginForm button[type="submit"]');
                if (submitBtn) {
                    submitBtn.textContent = 'Login';
                    submitBtn.disabled = false;
                }
            }
        });
    }
});