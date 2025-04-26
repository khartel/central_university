// Global variables
let currentUser = null;

// Fetch lecturer data from PHP backend
async function fetchLecturerData() {
    try {
        const response = await fetch('lecturer-dashboard.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            
            // Update UI with user data
            document.getElementById('userName').textContent = currentUser.full_name;
            document.getElementById('userAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.initials)}&background=random&color=fff&size=64`;
            document.getElementById('welcomeMessage').textContent = `Welcome back, ${currentUser.full_name.split(' ')[0]}!`;
        } else {
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Error fetching lecturer data:', error);
        window.location.href = 'index.html';
    }
}

// View Class Schedule
async function viewClassSchedule() {
    try {
        const response = await fetch('api/courses.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const scheduleList = document.getElementById('scheduleList');
            scheduleList.innerHTML = '';
            
            data.data.forEach(course => {
                const div = document.createElement('div');
                div.className = 'schedule-item';
                div.innerHTML = `
                    <h4>${course.name}</h4>
                    <p><i class="fas fa-clock"></i> ${course.schedule}</p>
                `;
                scheduleList.appendChild(div);
            });
            
            document.getElementById('scheduleModal').style.display = 'block';
        }
    } catch (error) {
        console.error('Error fetching schedule:', error);
        alert('Failed to load schedule');
    }
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

// View Student List
async function viewStudentList() {
    try {
        const response = await fetch('api/courses.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const courseSelect = document.getElementById('courseSelect');
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            
            data.data.forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = course.name;
                courseSelect.appendChild(option);
            });
            
            document.getElementById('studentsModal').style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading courses:', error);
        alert('Failed to load courses');
    }
}

// Load students for selected course
async function loadStudentsForCourse(courseId) {
    try {
        const response = await fetch(`api/courses.php?course_id=${courseId}&action=students`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const studentsList = document.getElementById('studentsList');
            studentsList.innerHTML = '';
            
            data.students.forEach(student => {
                const div = document.createElement('div');
                div.className = 'student-item';
                div.innerHTML = `
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(student.full_name)}&background=random&color=fff&size=64" 
                         alt="${student.full_name}'s avatar"
                         class="student-avatar">
                    <div>
                        <h4>${student.full_name}</h4>
                        <p>${student.email}</p>
                    </div>
                `;
                studentsList.appendChild(div);
            });
        }
    } catch (error) {
        console.error('Error loading students:', error);
        alert('Failed to load students');
    }
}

function closeStudentsModal() {
    document.getElementById('studentsModal').style.display = 'none';
}

// Download Attendance Report
async function downloadAttendanceReport() {
    try {
        const response = await fetch('api/attendance.php?action=report', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            let csv = 'Course,Date,Student,Status\n';
            data.attendance.forEach(record => {
                csv += `${record.course_name},${record.date},${record.student_name},${record.status}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'attendance_report.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
    } catch (error) {
        console.error('Error downloading report:', error);
        alert('Failed to download report');
    }
}

// Generate attendance code
async function generateCode() {
    const courseId = document.getElementById('courseSelect').value;
    if (!courseId) {
        alert('Please select a course');
        return;
    }

    try {
        const response = await fetch('api/attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ 
                action: 'generate_code',
                course_id: courseId 
            })
        });

        const data = await response.json();
        if (data.success) {
            document.getElementById('codeDisplay').style.display = 'block';
            document.getElementById('attendanceCode').textContent = data.code;
            startCodeTimer();
        } else {
            alert(data.message || 'Failed to generate attendance code');
        }
    } catch (error) {
        console.error('Error generating attendance code:', error);
        alert('An error occurred while generating the code');
    }
}

// Timer for attendance code
function startCodeTimer() {
    let timeLeft = 300;
    const timerDisplay = document.getElementById('codeTimer');
    const timer = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            document.getElementById('codeDisplay').style.display = 'none';
            document.getElementById('generateButton').disabled = false;
        }
        timeLeft--;
    }, 1000);
}

// Modal controls
function showGenerateCodeModal() {
    document.getElementById('generateCodeModal').style.display = 'block';
}

function closeGenerateCodeModal() {
    document.getElementById('generateCodeModal').style.display = 'none';
}

// Fetch courses for dropdown
async function fetchCourses() {
    try {
        const response = await fetch('api/courses.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const courseSelect = document.getElementById('courseSelect');
            const courseFilter = document.getElementById('courseFilter');
            
            data.data.forEach(course => {
                const option = new Option(course.name, course.id);
                courseSelect.add(option.cloneNode(true));
                courseFilter.add(option.cloneNode(true));
            });
        }
    } catch (error) {
        console.error('Error fetching courses:', error);
    }
}

// Fetch attendance statistics
async function fetchAttendanceStats() {
    try {
        const response = await fetch('api/attendance.php?action=stats', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalStudents').textContent = data.stats.total_students;
            document.getElementById('todayClasses').textContent = data.stats.today_classes;
            document.getElementById('avgAttendance').textContent = data.stats.average_attendance + '%';
        }
    } catch (error) {
        console.error('Error fetching attendance stats:', error);
    }
}

// Fetch today's attendance
async function fetchTodayAttendance() {
    try {
        const response = await fetch('api/attendance.php?action=today', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const attendanceTable = document.getElementById('attendanceTable');
            attendanceTable.innerHTML = '';

            data.attendance.forEach(record => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${record.course_name}</td>
                    <td>${record.time}</td>
                    <td class="text-green-600">${record.present_count}</td>
                    <td class="text-red-600">${record.absent_count}</td>
                    <td><button onclick="viewAttendanceDetails(${record.course_id})" class="text-blue-600">View Details</button></td>
                `;
                attendanceTable.appendChild(tr);
            });
        }
    } catch (error) {
        console.error('Error fetching today\'s attendance:', error);
    }
}

// View attendance details
async function viewAttendanceDetails(courseId) {
    try {
        const response = await fetch(`api/attendance.php?action=details&course_id=${courseId}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            alert(`Attendance details for course ${courseId}:\n\n${JSON.stringify(data.details, null, 2)}`);
        }
    } catch (error) {
        console.error('Error fetching attendance details:', error);
        alert('Failed to load attendance details');
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', () => {
    fetchLecturerData();
    fetchCourses();
    fetchAttendanceStats();
    fetchTodayAttendance();

    // Event listener for course selection change
    document.getElementById('courseSelect').addEventListener('change', (e) => {
        if (e.target.value) {
            loadStudentsForCourse(e.target.value);
        } else {
            document.getElementById('studentsList').innerHTML = '';
        }
    });

    // Refresh data every 5 minutes
    setInterval(() => {
        fetchAttendanceStats();
        fetchTodayAttendance();
    }, 300000);
});