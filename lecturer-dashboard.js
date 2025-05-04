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
    window.location.href = 'view-schedule.html';
}

function viewStudentList() {
    window.location.href = 'view-students.html';
}

async function downloadAttendanceReport() {
    try {
        const response = await fetch('attendance.php?action=report', {
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
            alert(data.message || 'Failed to generate attendance code');
        }
    } catch (error) {
        console.error('Error generating attendance code:', error);
        alert('An error occurred while generating the code');
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
                    <td><button onclick="viewAttendanceDetails('${record.course_code}')" class="text-blue-600">View Details</button></td>
                `;
                attendanceTable.appendChild(tr);
            });
        }
    } catch (error) {
        console.error("Error fetching today's attendance:", error);
    }
}

async function viewAttendanceDetails(courseCode) {
    try {
        const response = await fetch(`api/attendance.php?action=details&course_code=${courseCode}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            alert(`Attendance details for course ${courseCode}:\n\n${JSON.stringify(data.details, null, 2)}`);
        }
    } catch (error) {
        console.error('Error fetching attendance details:', error);
        alert('Failed to load attendance details');
    }
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