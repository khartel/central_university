let currentUser = null;
let selectedCourses = [];
let coursesByLevel = {};
let selectedLevel = null;

async function fetchUserData() {
    try {
        console.log('Fetching user data from student-dashboard.php');
        const response = await fetch('student-dashboard.php', {
            credentials: 'include'
        });
        const data = await response.json();
        console.log('User data response:', data);
        if (data.success) {
            currentUser = data.user;
            console.log('User data loaded:', currentUser);
            document.getElementById('userName').textContent = currentUser.full_name;
            const initials = currentUser.initials || currentUser.full_name.charAt(0);
            document.getElementById('userAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(initials)}&background=random&color=fff&size=64`;
            document.getElementById('popupUserName').textContent = currentUser.full_name;
            document.getElementById('popupUserAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(initials)}&background=random&color=fff&size=128`;
            document.getElementById('popupUserEmail').textContent = currentUser.email;
            document.getElementById('popupUserIndex').textContent = currentUser.index_no;
            document.getElementById('popupUserProgram').textContent = currentUser.program;
            document.getElementById('popupUserLevel').textContent = currentUser.level;
        } else {
            console.log('User data fetch failed:', data);
            alert('Failed to load user data: ' + (data.message || 'Please log in again.'));
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        }
    } catch (error) {
        console.error('Error fetching user data:', error);
        alert('Error loading user data. Please check your connection and try again.');
    }
}

async function fetchAllCourses() {
    try {
        console.log('Fetching courses from api/all.php');
        const response = await fetch('api/all.php', {
            credentials: 'include'
        });
        const data = await response.json();
        console.log('Courses response:', data);
        if (data.success) {
            if (data.courses.length === 0) {
                document.getElementById('coursesList').innerHTML = '<p>No courses available for enrollment.</p>';
                document.getElementById('levelSelector').innerHTML = '<option value="" disabled selected>No levels available</option>';
                return;
            }
            coursesByLevel = {};
            data.courses.forEach(course => {
                if (!coursesByLevel[course.level]) {
                    coursesByLevel[course.level] = [];
                }
                coursesByLevel[course.level].push(course);
            });
            populateLevelDropdown();
        } else {
            console.error('Failed to fetch courses:', data.message);
            document.getElementById('coursesList').innerHTML = '<p>Error loading courses: ' + (data.message || 'Unknown error') + '</p>';
            alert('Failed to load courses: ' + (data.message || 'Unknown error'));
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        }
    } catch (error) {
        console.error('Error fetching courses:', error);
        document.getElementById('coursesList').innerHTML = '<p>Error loading courses. Please try again.</p>';
        alert('Failed to load courses. Please try again.');
    }
}

function populateLevelDropdown() {
    const levelDropdown = document.getElementById('levelSelector');
    levelDropdown.innerHTML = '<option value="" disabled selected>Select a Course Level</option>';

    Object.keys(coursesByLevel).sort().forEach(level => {
        const option = document.createElement('option');
        option.value = level;
        option.textContent = `Level ${level}`;
        levelDropdown.appendChild(option);
    });

    levelDropdown.addEventListener('change', (e) => {
        if (e.target.value) {
            selectLevel(e.target.value);
        }
    });
}

function selectLevel(level) {
    selectedLevel = level;
    selectedCourses = [];
    const coursesList = document.getElementById('coursesList');
    coursesList.innerHTML = '';
    document.getElementById('enrollButton').style.display = 'block';

    coursesByLevel[level].forEach(course => {
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
}

function openPasswordModal() {
    if (selectedCourses.length === 0) {
        alert('Please select at least one course to enroll.');
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
        const response = await fetch('api/enroll.php', {
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
            alert('Courses enrolled successfully!');
            closePasswordModal();
            selectedCourses = [];
            document.getElementById('coursesList').innerHTML = '';
            document.getElementById('enrollButton').style.display = 'none';
            selectedLevel = null;
            document.getElementById('levelSelector').value = '';
        } else {
            alert(data.message || 'Failed to enroll courses. Please check your password and try again.');
        }
    } catch (error) {
        console.error('Error enrolling courses:', error);
        alert('An error occurred while enrolling courses. Please try again.');
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
    console.log('Page loaded, starting fetchUserData and fetchAllCourses');
    fetchUserData();
    fetchAllCourses();

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
        document.querySelector('.mobile-menu').classList.remove('active');
    });
    document.querySelector('.mobile-menu-item.registered-link').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'registered-courses.html';
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