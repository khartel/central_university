/**
 * Universal Auth Check for Both Student and Lecturer Dashboards
 * Must be included in all protected pages with: <script src="auth-check.js" defer></script>
 */

document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Show loading state
        document.body.classList.add('auth-check-loading');
        
        const response = await fetch('auth-check.php', {
            credentials: 'include',
            headers: {
                'X-Requested-Page': window.location.pathname.split('/').pop()
            }
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const authData = await response.json();
        
        // Determine if access should be allowed
        const currentPage = window.location.pathname.split('/').pop();
        let shouldAllowAccess = false;
        
        // Role-specific access rules
        if (authData.authenticated) {
            switch(currentPage) {
                case 'lecturer-dashboard.html':
                    shouldAllowAccess = ['lecturer', 'admin'].includes(authData.role);
                    break;
                case 'student-dashboard.html':
                    shouldAllowAccess = authData.role === 'student';
                    break;
                default:
                    shouldAllowAccess = false;
            }
        }
        
        if (!shouldAllowAccess) {
            // Redirect to login with redirect back parameter
            const loginUrl = new URL('index.html', window.location.origin);
            loginUrl.searchParams.set('redirect', window.location.pathname);
            window.location.href = loginUrl.toString();
            return;
        }
        
        // Set role-specific classes on body
        document.body.classList.add(`${authData.role}-dashboard`);
        document.body.classList.remove('auth-check-loading');
        
        // Dispatch custom event for other scripts
        document.dispatchEvent(new CustomEvent('auth-verified', {
            detail: {
                role: authData.role,
                userId: authData.userId
            }
        }));
        
    } catch (error) {
        console.error('Authentication check failed:', error);
        window.location.href = 'index.html?error=auth_check_failed';
    }
});

// Optional: Add a function to check auth state from other scripts
window.checkAuthState = async () => {
    try {
        const response = await fetch('auth-check.php', {
            credentials: 'include'
        });
        return await response.json();
    } catch (error) {
        return { authenticated: false };
    }
};