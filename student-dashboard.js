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

            // Add attendance marked notification
            const courseCode = data.course_code || 'UNKNOWN'; // Assume attendance.php returns course_code
            const message = `Attendance marked as present for ${data.course_name || 'the course'} on ${new Date().toLocaleDateString()}`;
            await fetch('api/notifications.php?action=add_attendance_notification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    course_code: courseCode,
                    message: message
                })
            });

            fetchAttendanceStats();
            fetchTodayClasses();
            fetchAttendanceHistory();
            fetchNotifications(); // Refresh notifications
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
        if (data.success && data.stats) {
            document.getElementById('attendanceRate').textContent = (data.stats.rate || 0) + '%';
            document.getElementById('totalClasses').textContent = data.stats.total || 0;
            document.getElementById('classesAttended').textContent = data.stats.attended || 0;
            document.getElementById('classesMissed').textContent = data.stats.missed || 0;
        } else {
            console.error('Failed to fetch attendance stats:', data.message || 'No data returned');
            document.getElementById('attendanceRate').textContent = '0%';
            document.getElementById('totalClasses').textContent = '0';
            document.getElementById('classesAttended').textContent = '0';
            document.getElementById('classesMissed').textContent = '0';
        }
    } catch (error) {
        console.error('Error fetching attendance stats:', error);
        document.getElementById('attendanceRate').textContent = '0%';
        document.getElementById('totalClasses').textContent = '0';
        document.getElementById('classesAttended').textContent = '0';
        document.getElementById('classesMissed').textContent = '0';
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

async function fetchNotifications() {
    try {
        const response = await fetch('api/notifications.php?action=get_notifications', {
            credentials: 'include'
        });
        const data = await response.json();
        if (data.success && data.notifications) {
            const notificationList = document.getElementById('notificationList');
            notificationList.innerHTML = '';

            // Missed Classes (Previous Day)
            if (data.notifications.missed_classes_previous_day.length > 0) {
                const missedHeader = document.createElement('h3');
                missedHeader.textContent = 'Missed Classes (Yesterday)';
                missedHeader.className = 'notification-header';
                notificationList.appendChild(missedHeader);

                data.notifications.missed_classes_previous_day.forEach(cls => {
                    const div = document.createElement('div');
                    div.className = 'notification-item missed';
                    div.innerHTML = `
                        <div class="notification-content">
                            <strong>${cls.course_name}</strong>
                            <p><i class="fas fa-clock"></i> ${cls.schedule}</p>
                            <p><i class="fas fa-user-tie"></i> ${cls.lecturer}</p>
                            <p><i class="fas fa-map-marker-alt"></i> ${cls.venue}</p>
                        </div>
                    `;
                    notificationList.appendChild(div);
                });
            }

            // Attended Classes (Today)
            if (data.notifications.attended_classes.length > 0) {
                const attendedHeader = document.createElement('h3');
                attendedHeader.textContent = 'Attended Classes (Today)';
                attendedHeader.className = 'notification-header';
                notificationList.appendChild(attendedHeader);

                data.notifications.attended_classes.forEach(cls => {
                    const div = document.createElement('div');
                    div.className = 'notification-item attended';
                    div.innerHTML = `
                        <div class="notification-content">
                            <strong>${cls.course_name}</strong>
                            <p><i class="fas fa-clock"></i> ${cls.schedule}</p>
                            <p><i class="fas fa-user-tie"></i> ${cls.lecturer}</p>
                            <p><i class="fas fa-map-marker-alt"></i> ${cls.venue}</p>
                        </div>
                    `;
                    notificationList.appendChild(div);
                });
            }

            // Upcoming Classes (Today)
            if (data.notifications.upcoming_classes.length > 0) {
                const upcomingHeader = document.createElement('h3');
                upcomingHeader.textContent = 'Upcoming Classes (Today)';
                upcomingHeader.className = 'notification-header';
                notificationList.appendChild(upcomingHeader);

                data.notifications.upcoming_classes.forEach(cls => {
                    const div = document.createElement('div');
                    div.className = 'notification-item upcoming';
                    div.innerHTML = `
                        <div class="notification-content">
                            <strong>${cls.course_name}</strong>
                            <p><i class="fas fa-clock"></i> ${cls.schedule}</p>
                            <p><i class="fas fa-user-tie"></i> ${cls.lecturer}</p>
                            <p><i class="fas fa-map-marker-alt"></i> ${cls.venue}</p>
                        </div>
                    `;
                    notificationList.appendChild(div);
                });
            }

            // Attendance Marked Notifications
            if (data.notifications.attendance_marked.length > 0) {
                const markedHeader = document.createElement('h3');
                markedHeader.textContent = 'Attendance Marked';
                markedHeader.className = 'notification-header';
                notificationList.appendChild(markedHeader);

                data.notifications.attendance_marked.forEach(notif => {
                    const div = document.createElement('div');
                    div.className = 'notification-item attendance-marked';
                    div.innerHTML = `
                        <div class="notification-content">
                            <strong>${notif.message}</strong>
                        </div>
                    `;
                    notificationList.appendChild(div);
                });
            }

            // Update notification badge
            const notificationBadge = document.querySelector('.notification-badge');
            const totalNotifications = data.notifications.missed_classes_previous_day.length + 
                                     data.notifications.attended_classes.length + 
                                     data.notifications.upcoming_classes.length +
                                     data.notifications.attendance_marked.length;
            notificationBadge.textContent = totalNotifications;
            notificationBadge.style.display = totalNotifications > 0 ? 'flex' : 'none';
        } else {
            console.error('Failed to fetch notifications:', data.message || 'No data returned');
            document.getElementById('notificationList').innerHTML = '<p>No notifications available.</p>';
            document.querySelector('.notification-badge').style.display = 'none';
        }
    } catch (error) {
        console.error('Error fetching notifications:', error);
        document.getElementById('notificationList').innerHTML = '<p>Error loading notifications.</p>';
        document.querySelector('.notification-badge').style.display = 'none';
    }
}

function showNotificationPopup() {
    fetchNotifications();
    document.getElementById('notificationPopup').classList.add('active');
}

function hideNotificationPopup() {
    document.getElementById('notificationPopup').classList.remove('active');
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
        const notificationPopup = document.getElementById('notificationPopup');
        const notificationBtn = document.querySelector('.notification-btn');
        
        if (mobileMenu.classList.contains('active')) {
            if (!mobileMenu.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                mobileMenu.classList.remove('active');
            }
        }

        if (notificationPopup.classList.contains('active')) {
            if (!notificationPopup.contains(e.target) && !notificationBtn.contains(e.target)) {
                hideNotificationPopup();
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

function showSchedule() {
    window.location.href = 'view-schedule-student.html';
    document.querySelector('.mobile-menu').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', () => {
    fetchUserData();
    fetchAttendanceStats();
    fetchTodayClasses();
    fetchAttendanceHistory();
    fetchNotifications(); // Initial fetch for notification badge

    document.querySelector('.user-profile').addEventListener('click', showProfilePopup);
    document.querySelector('.close-popup-btn').addEventListener('click', hideProfilePopup);
    document.getElementById('profilePopup').addEventListener('click', function(e) {
        if (e.target === this) {
            hideProfilePopup();
        }
    });

    document.querySelector('.notification-btn').addEventListener('click', showNotificationPopup);
    document.getElementById('notificationPopup').addEventListener('click', function(e) {
        if (e.target === this) {
            hideNotificationPopup();
        }
    });
    document.querySelector('.close-notification-btn').addEventListener('click', hideNotificationPopup);

    document.querySelector('.hamburger-btn').addEventListener('click', toggleMobileMenu);
    document.querySelector('.mobile-menu-item.enroll-link').addEventListener('click', showCourseEnrollment);
    document.querySelector('.mobile-menu-item.registered-link').addEventListener('click', showRegisteredCourses);
    document.querySelector('.mobile-menu-item.schedule-link').addEventListener('click', showSchedule);

    setupClickOutsideMenu();

    setInterval(() => {
        fetchAttendanceStats();
        fetchTodayClasses();
        fetchAttendanceHistory();
        fetchNotifications();
    }, 300000);
});