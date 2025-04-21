function togglePassword() {
  const password = document.getElementById('password');
  password.type = password.type === 'password' ? 'text' : 'password';
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

document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const email = this.email.value;
  const password = this.password.value;
  const errorElement = document.getElementById('loginError');
  
  // Reset error display
  errorElement.style.display = 'none';
  
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
    const originalBtnText = submitBtn.textContent;
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
      // Redirect based on role
      window.location.href = data.role === 'student' 
        ? 'student-dashboard.html' 
        : 'lecturer-dashboard.html';
    } else {
      errorElement.innerHTML = data.message; // Allows HTML in error messages
      errorElement.style.display = 'block';
    }
  } catch (error) {
    console.error('Login error:', error);
    errorElement.textContent = 'An error occurred. Please try again.';
    errorElement.style.display = 'block';
  } finally {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.textContent = 'Login';
      submitBtn.disabled = false;
    }
  }
});

// Initialize form validation on page load
document.addEventListener('DOMContentLoaded', function() {
  const emailInput = document.querySelector('input[type="email"]');
  const passwordInput = document.getElementById('password');
  
  if (emailInput) {
    emailInput.addEventListener('input', function() {
      validateInput(this, 'email-group');
    });
  }
  
  if (passwordInput) {
    passwordInput.addEventListener('input', function() {
      checkPassword(this);
    });
  }
});