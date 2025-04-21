document.addEventListener('DOMContentLoaded', function () {
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const statusMessage = document.getElementById('statusMessage');
  const passwordSection = document.getElementById('passwordSection');
  const checkEmailBtn = document.getElementById('checkEmailBtn');
  const createPasswordBtn = document.getElementById('createPasswordBtn');
  const eyeIcon = document.querySelector('.eye-icon');

  // Toggle password visibility
  eyeIcon.addEventListener('click', () => {
      passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
  });

  // Check email
  checkEmailBtn.addEventListener('click', async () => {
      const email = emailInput.value.trim();

      if (!validateEmail(email)) {
          showMessage('Please enter a valid email address.', 'error');
          return;
      }

      if (!email.endsWith('@central.edu.gh')) {
          showMessage('Email must end with @central.edu.gh', 'error');
          return;
      }

      try {
          const response = await fetch('signup.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'checkEmail', email })
          });

          const data = await response.json();

          if (data.success) {
              if (data.hasPassword) {
                  showMessage('Email already registered. Redirecting to login...', 'info');
                  setTimeout(() => window.location.href = 'index.html', 2000);
              } else {
                  showMessage(`Email verified (${data.role}). Please create a password.`, 'info');
                  passwordSection.style.display = 'block';
                  checkEmailBtn.style.display = 'none';
              }
          } else {
              showMessage(data.message || 'Email not found. Please contact admin to register.', 'error');
          }
      } catch (err) {
          console.error(err);
          showMessage('An error occurred. Please try again later.', 'error');
      }
  });

  // Create password
  createPasswordBtn.addEventListener('click', async () => {
      const email = emailInput.value.trim();
      const password = passwordInput.value;

      const validation = validatePassword(password);
      if (!validation.valid) {
          let message = 'Password must meet the following:\n';
          if (!validation.rules.length) message += '• At least 8 characters\n';
          if (!validation.rules.hasNumberOrSymbol) message += '• At least one number or symbol\n';
          if (!validation.rules.hasUpperLower) message += '• Both uppercase and lowercase letters\n';

          showMessage(message, 'error');
          return;
      }

      try {
          const response = await fetch('signup.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'createPassword', email, password })
          });

          const data = await response.json();

          if (data.success) {
              showMessage('Password created successfully. Redirecting to login...', 'info');
              setTimeout(() => window.location.href = 'index.html', 2000);
          } else {
              showMessage(data.message || 'Failed to create password. Please try again.', 'error');
          }
      } catch (err) {
          console.error(err);
          showMessage('An error occurred. Please try again later.', 'error');
      }
  });

  function validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email.toLowerCase());
  }

  function validatePassword(password) {
      const rules = {
          length: password.length >= 8,
          hasNumberOrSymbol: /[0-9\W]/.test(password),
          hasUpperLower: /[a-z]/.test(password) && /[A-Z]/.test(password)
      };

      return {
          valid: Object.values(rules).every(Boolean),
          rules
      };
  }

  function showMessage(message, type) {
      statusMessage.textContent = message;
      statusMessage.className = `status-message ${type}`;
  }
});
