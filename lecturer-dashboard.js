let currentUser = null;

async function fetchLecturerData() {
    try {
        const response = await fetch('lecturer-dashboard.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
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

function viewClassSchedule() {
    window.location.href = 'view-schedule-lecturer.html';
}

function viewStudentList() {
    window.location.href = 'view-students.html';
}

function downloadAttendanceReport() {
    window.location.href = 'download-report.html';
}

async function generateCode() {
    const courseCode = document.getElementById('courseSelect').value;
    if (!courseCode) {
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
                course_code: courseCode
            })
        });

        const data = await response.json();
        if (data.success) {
            document.getElementById('codeDisplay').style.display = 'block';
            document.getElementById('attendanceCode').textContent = data.code;
            startCodeTimer();
        } else {
            console.error('Failed to generate code:', data.message);
            alert(data.message || 'Failed to generate attendance code. Please try again.');
        }
    } catch (error) {
        console.error('Error generating attendance code:', error);
        alert('An error occurred while generating the code. Check your connection and try again.');
    }
}

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

function showGenerateCodeModal() {
    document.getElementById('generateCodeModal').style.display = 'block';
}

function closeGenerateCodeModal() {
    document.getElementById('generateCodeModal').style.display = 'none';
}

async function fetchCourses() {
    try {
        const response = await fetch('api/attendance.php?action=courses', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const courseSelect = document.getElementById('courseSelect');
            const courseFilter = document.getElementById('courseFilter');
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            courseFilter.innerHTML = '<option value="">All Courses</option>';
            
            data.courses.forEach(course => {
                const option = new Option(`${course.course_code} - ${course.course_name}`, course.course_code);
                courseSelect.add(option.cloneNode(true));
                courseFilter.add(option.cloneNode(true));
            });
        } else {
            console.error('Failed to fetch courses:', data.message);
        }
    } catch (error) {
        console.error('Error fetching courses:', error);
    }
}

async function fetchAttendanceStats() {
    try {
        const response = await fetch('api/attendance.php?action=stats', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalStudents').textContent = data.stats.total_students || 0;
            document.getElementById('todayClasses').textContent = data.stats.today_classes;
            document.getElementById('avgAttendance').textContent = data.stats.average_attendance + '%';
        } else {
            console.error('Failed to fetch attendance stats:', data.message);
        }
    } catch (error) {
        console.error('Error fetching attendance stats:', error);
    }
}

async function fetchTodayAttendance() {
    try {
        const response = await fetch('api/attendance.php?action=today', {
            credentials: 'include'
        });
        const data = await response.json();
        
        const attendanceTable = document.getElementById('attendanceTable');
        attendanceTable.innerHTML = '';
        
        if (data.success) {
            if (data.attendance.length === 0) {
                attendanceTable.innerHTML = '<tr><td colspan="5" class="text-center">No classes scheduled for today</td></tr>';
            } else {
                data.attendance.forEach(record => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${record.course_name}</td>
                        <td>${record.time}</td>
                        <td class="text-green-600">${record.present_count}</td>
                        <td class="text-red-600">${record.absent_count}</td>
                        <td><button onclick="viewAttendanceDetails('${record.course_code}')" class="text-blue-600">View Details</button></td>
                    `;
                    attendanceTable.appendChild(tr);
                });
            }
        } else {
            console.error('Failed to fetch today\'s attendance:', data.message);
            attendanceTable.innerHTML = '<tr><td colspan="5" class="text-center">Error fetching data</td></tr>';
        }
    } catch (error) {
        console.error("Error fetching today's attendance:", error);
        document.getElementById('attendanceTable').innerHTML = '<tr><td colspan="5" class="text-center">Error fetching data</td></tr>';
    }
}

function viewAttendanceDetails(courseCode) {
    window.location.href = `view-attendance-details.html?course_code=${encodeURIComponent(courseCode)}`;
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLecturerData();
    fetchCourses();
    fetchAttendanceStats();
    fetchTodayAttendance();

    setInterval(() => {
        fetchAttendanceStats();
        fetchTodayAttendance();
    }, 300000);
});