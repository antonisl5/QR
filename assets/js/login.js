/**
 * Login JavaScript Engine
 * Handles AJAX login requests, error display, and redirection based on role.
 */
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const errorMessage = document.getElementById('error-message');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    if (!loginForm) return;

    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Hide previous errors
        errorMessage.style.display = 'none';
        errorMessage.textContent = '';

        const email = emailInput.value.trim();
        const password = passwordInput.value;

        if (!email || !password) {
            showError('Παρακαλώ συμπληρώστε email και κωδικό.');
            return;
        }

        // Set button to loading state
        const originalBtnText = loginBtn.innerHTML;
        loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Παρακαλώ περιμένετε...';
        loginBtn.disabled = true;

        // Prepare data
        const payload = {
            email: email,
            password: password
        };

        // Perform AJAX request using Fetch API
        fetch('/api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            // Check if network response is ok
            if (!response.ok) {
                // Return json promise to extract error message
                return response.json().then(errData => {
                    throw new Error(errData.error || 'Σφάλμα σύνδεσης. Ελέγξτε τα στοιχεία σας.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Show success momentarily (optional)
                loginBtn.innerHTML = '<i class="bi bi-check-circle"></i> Επιτυχία!';
                loginBtn.classList.remove('btn-primary');
                loginBtn.classList.add('btn-success');

                // Redirect user based on server instruction
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    // Fallback redirect if none provided
                    if (data.user && data.user.role === 'store_staff') {
                        window.location.href = '/store/confirm.php';
                    } else {
                        window.location.href = '/admin/dashboard.php';
                    }
                }
            } else {
                showError(data.error || 'Παρουσιάστηκε άγνωστο σφάλμα.');
                resetButton();
            }
        })
        .catch(error => {
            console.error('Login error:', error);
            showError(error.message || 'Πρόβλημα επικοινωνίας με τον διακομιστή.');
            resetButton();
        });

        function showError(msg) {
            errorMessage.textContent = msg;
            errorMessage.style.display = 'block';
            // Optional: shake animation
            loginForm.classList.add('shake');
            setTimeout(() => loginForm.classList.remove('shake'), 500);
        }

        function resetButton() {
            loginBtn.innerHTML = originalBtnText;
            loginBtn.disabled = false;
        }
    });
});

// Optional CSS for shake animation on error
const style = document.createElement('style');
style.innerHTML = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    .shake { animation: shake 0.5s; }
`;
document.head.appendChild(style);