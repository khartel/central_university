let currentUser = null;

async function fetchUserData() {
    try {
        const response = await fetch('student-dashboard.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            document.getElementById('userName').textContent = currentUser.full_name;
            document.getElementById('userAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.initials)}&background=random&color=fff&size=64`;
            document.getElementById('welcomeMessage').textContent = `Welcome back, ${currentUser.full_name.split(' ')[0]}!`;
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

async function markAttendance() {
    const attendanceCode = document.getElementById('attendanceCode').value.trim().toUpperCase();
    if (!attendanceCode || attendanceCode.length !== 6) {
        alert('Please enter a valid 6-character attendance code');
        return;
    }

    try {
        const position = await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            });
        });

        const { latitude, longitude } = position.coords;

        const response = await fetch('api/attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'submit_attendance',
                code: attendanceCode,
                latitude: latitude,
                longitude: longitude
            })
        });

        const data = await response.json();
        if (data.success) {
            alert('Attendance recorded successfully!');
            document.getElementById('attendanceCode').value = '';
            fetchAttendanceStats();
            fetchTodayClasses();
            fetchAttendanceHistory();
        } else {
            alert(data.message || 'Failed to record attendance');
        }
    } catch (error) {
        console.error('Error submitting attendance:', error);
        alert('Failed to get location or submit attendance. Please enable location services and try again.');
    }
}

async function fetchAttendanceStats() {
    try {
        const response = await fetch('api/attendance.php?action=stats', {
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

async function fetchTodayClasses() {
    try {
        const response = await fetch('api/attendance.php?action=today', {
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

async function fetchAttendanceHistory() {
    try {
        const response = await fetch('api/attendance.php?action=history', {
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

function showProfilePopup() {
    document.getElementById('profilePopup').classList.add('active');
}

function hideProfilePopup() {
    document.getElementById('profilePopup').classList.remove('active');
}

function toggleMobileMenu() {
    const mobileMenu = document.querySelector('.mobile-menu');
    mobileMenu.classList.toggle('active');
}

function setupClickOutsideMenu() {
    document.addEventListener('click', (e) => {
        const mobileMenu = document.querySelector('.mobile-menu');
        const hamburgerBtn = document.querySelector('.hamburger-btn');
        
        if (mobileMenu.classList.contains('active')) {
            if (!mobileMenu.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                mobileMenu.classList.remove('active');
            }
        }
    });
}

function showCourseEnrollment() {
    window.location.href = 'enroll-courses.html';
    document.querySelector('.mobile-menu').classList.remove('active');
}

function showRegisteredCourses() {
    window.location.href = 'registered-courses.html';
    document.querySelector('.mobile-menu').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', () => {
    fetchUserData();
    fetchAttendanceStats();
    fetchTodayClasses();
    fetchAttendanceHistory();

    document.querySelector('.user-profile').addEventListener('click', showProfilePopup);
    document.querySelector('.close-popup-btn').addEventListener('click', hideProfilePopup);
    document.getElementById('profilePopup').addEventListener('click', function(e) {
        if (e.target === this) {
            hideProfilePopup();
        }
    });

    document.querySelector('.hamburger-btn').addEventListener('click', toggleMobileMenu);
    document.querySelector('.mobile-menu-item.enroll-link').addEventListener('click', showCourseEnrollment);
    document.querySelector('.mobile-menu-item.registered-link').addEventListener('click', showRegisteredCourses);

    setupClickOutsideMenu();

    setInterval(() => {
        fetchAttendanceStats();
        fetchTodayClasses();
        fetchAttendanceHistory();
    }, 300000);
});