// Global variables
let currentUser = null;

// Fetch user data from PHP backend
async function fetchUserData() {
    try {
        const response = await fetch('student-dashboard.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            
            // Update UI with user data
            document.getElementById('userName').textContent = currentUser.full_name;
            document.getElementById('userAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.initials)}&background=random&color=fff&size=64`;
            document.getElementById('welcomeMessage').textContent = `Welcome back, ${currentUser.full_name.split(' ')[0]}!`;
            
            // Set popup content
            document.getElementById('popupUserName').textContent = currentUser.full_name;
            document.getElementById('popupUserAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.initials)}&background=random&color=fff&size=128`;
            document.getElementById('popupUserEmail').textContent = currentUser.email;
            document.getElementById('popupUserIndex').textContent = currentUser.index_no;
            document.getElementById('popupUserProgram').textContent = currentUser.program;
            document.getElementById('popupUserLevel').textContent = currentUser.level;
        } else {
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Error fetching user data:', error);
        window.location.href = 'index.html';
    }
}

// Mark attendance using attendance code
async function markAttendance() {
    const attendanceCode = document.getElementById('attendanceCode').value;
    if (!attendanceCode) {
        alert('Please enter the attendance code');
        return;
    }

    try {
        const response = await fetch('api/attendance/mark.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ attendance_code: attendanceCode })
        });

        const data = await response.json();
        if (data.success) {
            alert('Attendance marked successfully!');
            document.getElementById('attendanceCode').value = '';
            // Refresh the displays
            fetchAttendanceStats();
            fetchTodayClasses();
            fetchAttendanceHistory();
        } else {
            alert(data.message || 'Failed to mark attendance. Please check the code and try again.');
        }
    } catch (error) {
        console.error('Error marking attendance:', error);
        alert('An error occurred while marking attendance. Please try again.');
    }
}

// Fetch and display attendance statistics
async function fetchAttendanceStats() {
    try {
        const response = await fetch('api/attendance/stats.php', {
            credentials: 'include'
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('attendanceRate').textContent = data.stats.rate + '%';
            document.getElementById('totalClasses').textContent = data.stats.total;
            document.getElementById('classesAttended').textContent = data.stats.attended;
            document.getElementById('classesMissed').textContent = data.stats.missed;
        }
    } catch (error) {
        console.error('Error fetching attendance stats:', error);
    }
}

// Fetch and display today's classes
async function fetchTodayClasses() {
    try {
        const response = await fetch('api/courses/today.php', {
            credentials: 'include'
        });
        const data = await response.json();
        if (data.success) {
            const todayClassesList = document.getElementById('todayClasses');
            todayClassesList.innerHTML = '';

            data.classes.forEach(course => {
                const li = document.createElement('li');
                li.className = 'class-item';
                li.innerHTML = `
                    <div class="class-header">
                        <i class="fas fa-book-reader text-blue-600"></i>
                        <h3>${course.name}</h3>
                        <span class="status-badge ${course.attended ? 'present' : 'absent'}">
                            ${course.attended ? 'Attended' : 'Pending'}
                        </span>
                    </div>
                    <div class="class-details">
                        <p><i class="fas fa-user-tie"></i> ${course.lecturer}</p>
                        <p><i class="fas fa-clock"></i> ${course.schedule}</p>
                    </div>
                `;
                todayClassesList.appendChild(li);
            });
        }
    } catch (error) {
        console.error('Error fetching today\'s classes:', error);
    }
}

// Fetch and display attendance history
async function fetchAttendanceHistory() {
    try {
        const response = await fetch('api/attendance/history.php', {
            credentials: 'include'
        });
        const data = await response.json();
        if (data.success) {
            const historyTable = document.getElementById('attendanceHistory');
            historyTable.innerHTML = '';

            data.history.forEach(record => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${record.course_name}</td>
                    <td>${record.date}</td>
                    <td>
                        <span class="status-badge ${record.status}">
                            ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                        </span>
                    </td>
                `;
                historyTable.appendChild(tr);
            });
        }
    } catch (error) {
        console.error('Error fetching attendance history:', error);
    }
}

// Show profile popup
function showProfilePopup() {
    document.getElementById('profilePopup').classList.add('active');
}

// Hide profile popup
function hideProfilePopup() {
    document.getElementById('profilePopup').classList.remove('active');
}

// Toggle mobile menu
function toggleMobileMenu() {
    const mobileMenu = document.querySelector('.mobile-menu');
    mobileMenu.classList.toggle('active');
}

// Close menu when clicking outside
function setupClickOutsideMenu() {
    document.addEventListener('click', (e) => {
        const mobileMenu = document.querySelector('.mobile-menu');
        const hamburgerBtn = document.querySelector('.hamburger-btn');
        
        if (mobileMenu.classList.contains('active')){
            if (!mobileMenu.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                mobileMenu.classList.remove('active');
            }
        }
    });
}

// Show course enrollment interface
function showCourseEnrollment() {
    // This would be replaced with actual enrollment logic
    alert('Course enrollment functionality will be implemented here');
    // Close the menu after selection
    document.querySelector('.mobile-menu').classList.remove('active');
}

// Show registered courses
function showRegisteredCourses() {
    // This would be replaced with actual registered courses display
    alert('Registered courses functionality will be implemented here');
    // Close the menu after selection
    document.querySelector('.mobile-menu').classList.remove('active');
}

// Initialize the dashboard
document.addEventListener('DOMContentLoaded', () => {
    fetchUserData();
    fetchAttendanceStats();
    fetchTodayClasses();
    fetchAttendanceHistory();

    // Add event listeners for profile popup
    document.querySelector('.user-profile').addEventListener('click', showProfilePopup);
    document.querySelector('.close-popup-btn').addEventListener('click', hideProfilePopup);
    document.getElementById('profilePopup').addEventListener('click', function(e) {
        if (e.target === this) {
            hideProfilePopup();
        }
    });

    // Add event listener for hamburger menu
    document.querySelector('.hamburger-btn').addEventListener('click', toggleMobileMenu);

    // Add click handlers for menu items
    document.querySelector('.mobile-menu-item.enroll-link').addEventListener('click', showCourseEnrollment);
    document.querySelector('.mobile-menu-item.registered-link').addEventListener('click', showRegisteredCourses);

    // Setup click outside menu to close
    setupClickOutsideMenu();

    // Refresh data every 5 minutes
    setInterval(() => {
        fetchAttendanceStats();
        fetchTodayClasses();
        fetchAttendanceHistory();
    }, 300000);
});