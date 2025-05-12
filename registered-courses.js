let currentUser = null;
let selectedCourses = [];

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

async function fetchRegisteredCourses() {
    try {
        const response = await fetch('registered.php', {
            credentials: 'include'
        });
        const data = await response.json();
        const coursesList = document.getElementById('coursesList');
        coursesList.innerHTML = '';
        
        if (data.success) {
            if (data.courses.length === 0) {
                coursesList.innerHTML = '<p>No courses registered yet.</p>';
                document.getElementById('dropButton').style.display = 'none';
                return;
            }
            
            data.courses.forEach(course => {
                const div = document.createElement('div');
                div.className = 'course-item';
                div.innerHTML = `
                    <input type="checkbox" id="course-${course.course_code}" value="${course.course_code}">
                    <label for="course-${course.course_code}">
                        <h3>${course.course_name}</h3>
                        <p><i class="fas fa-code"></i> ${course.course_code}</p>
                        <p><i class="fas fa-graduation-cap"></i> Level ${course.level}</p>
                        <p><i class="fas fa-clock"></i> ${course.credit_hours} Credit Hours</p>
                    </label>
                `;
                coursesList.appendChild(div);
                document.getElementById(`course-${course.course_code}`).addEventListener('change', (e) => {
                    if (e.target.checked) {
                        selectedCourses.push(course.course_code);
                    } else {
                        selectedCourses = selectedCourses.filter(code => code !== course.course_code);
                    }
                });
            });
            document.getElementById('dropButton').style.display = 'block';
        } else {
            console.error('Failed to fetch registered courses:', data.message);
            coursesList.innerHTML = '<p>Error loading courses.</p>';
            document.getElementById('dropButton').style.display = 'none';
        }
    } catch (error) {
        console.error('Error fetching registered courses:', error);
        document.getElementById('coursesList').innerHTML = '<p>Error loading courses.</p>';
        document.getElementById('dropButton').style.display = 'none';
    }
}

function openPasswordModal() {
    if (selectedCourses.length === 0) {
        alert('Please select at least one course to drop.');
        return;
    }
    document.getElementById('passwordModal').classList.add('active');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.remove('active');
    document.getElementById('passwordInput').value = '';
}

async function verifyPassword() {
    const password = document.getElementById('passwordInput').value;
    if (!password) {
        alert('Please enter your password.');
        return;
    }

    try {
        const response = await fetch('drop.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                password: password,
                courses: selectedCourses
            })
        });
        const data = await response.json();
        if (data.success) {
            alert('Courses dropped successfully!');
            closePasswordModal();
            selectedCourses = [];
            document.getElementById('coursesList').innerHTML = '';
            document.getElementById('dropButton').style.display = 'none';
            fetchRegisteredCourses(); // Refresh course list
        } else {
            alert(data.message || 'Failed to drop courses. Please check your password and try again.');
        }
    } catch (error) {
        console.error('Error dropping courses:', error);
        alert('An error occurred while dropping courses. Please try again.');
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
    fetchUserData();
    fetchRegisteredCourses();

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
        document.querySelector('.mobile-menu').classList.remove('active');
    });
    document.querySelector('.mobile-menu-item.schedule-link').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'view-schedule-student.html';
        document.querySelector('.mobile-menu').classList.remove('active');
    });
    document.querySelector('.mobile-menu-item.logout-link').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'index.html';
        document.querySelector('.mobile-menu').classList.remove('active');
    });

    setupClickOutsideMenu();
});