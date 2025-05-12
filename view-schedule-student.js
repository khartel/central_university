let currentUser = null;

async function fetchStudentData() {
    try {
        const response = await fetch('student-dashboard.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            document.getElementById('userName').textContent = currentUser.full_name;
            document.getElementById('userAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.initials)}&background=random&color=fff&size=64`;
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
        console.error('Error fetching student data:', error);
        window.location.href = 'index.html';
    }
}

async function fetchSchedule() {
    try {
        const response = await fetch('api/schedule.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            // Render timetable as a list
            const timetableList = document.getElementById('timetableList');
            timetableList.innerHTML = '';
            
            if (data.timetable.length === 0) {
                timetableList.innerHTML = '<p>No scheduled classes found.</p>';
                return;
            }

            data.timetable.forEach(entry => {
                const startTime = new Date(`1970-01-01T${entry.start_time}Z`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const endTime = new Date(`1970-01-01T${entry.end_time}Z`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                const div = document.createElement('div');
                div.className = 'course-item';
                div.innerHTML = `
                    <label>
                        <h3>${entry.course_name}</h3>
                        <p><i class="fas fa-code"></i> ${entry.course_code}</p>
                        <p><i class="fas fa-graduation-cap"></i> Level ${entry.level}</p>
                        <p><i class="fas fa-clock"></i> ${entry.day_of_week}, ${startTime} - ${endTime}</p>
                        <p><i class="fas fa-user-tie"></i> ${entry.lecturer_name || 'TBA'}</p>
                    </label>
                `;
                timetableList.appendChild(div);
            });
        } else {
            alert(data.message || 'Failed to load timetable');
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Error fetching schedule:', error);
        alert('Failed to load timetable');
        window.location.href = 'index.html';
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

document.addEventListener('DOMContentLoaded', () => {
    fetchStudentData();
    fetchSchedule();

    document.querySelector('.user-profile').addEventListener('click', showProfilePopup);
    document.querySelector('.close-popup-btn').addEventListener('click', hideProfilePopup);
    document.getElementById('profilePopup').addEventListener('click', function(e) {
        if (e.target === this) {
            hideProfilePopup();
        }
    });

    document.querySelector('.hamburger-btn').addEventListener('click', toggleMobileMenu);
    document.querySelector('.mobile-menu-item.enroll-link').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'enroll-courses.html';
        document.querySelector('.mobile-menu').classList.remove('active');
    });
    document.querySelector('.mobile-menu-item.registered-link').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'registered-courses.html';
        document.querySelector('.mobile-menu').classList.remove('active');
    });
    document.querySelector('.mobile-menu-item.logout-link').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'index.html';
        document.querySelector('.mobile-menu').classList.remove('active');
    });

    setupClickOutsideMenu();
});