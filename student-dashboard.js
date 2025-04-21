// Fetch user data and update UI
async function fetchUserData() {
    try {
        const response = await fetch('/api/user', {
            credentials: 'include'
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('userName').textContent = data.user.full_name;
            document.getElementById('userAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(data.user.full_name)}`;
            document.getElementById('welcomeMessage').textContent = `Welcome back, ${data.user.full_name.split(' ')[0]}!`;
        }
    } catch (error) {
        console.error('Error fetching user data:', error);
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
        const response = await fetch('/api/attendance/mark', {
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
        const response = await fetch('/api/attendance/stats', {
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
        const response = await fetch('/api/courses/today', {
            credentials: 'include'
        });
        const data = await response.json();
        if (data.success) {
            const todayClassesList = document.getElementById('todayClasses');
            todayClassesList.innerHTML = '';

            data.classes.forEach(course => {
                const li = document.createElement('li');
                li.className = 'px-4 py-4 sm:px-6';
                li.innerHTML = `
                    <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-book-reader text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">${course.name}</h3>
                            <p class="text-sm text-gray-500">
                                <i class="fas fa-user-tie mr-1"></i> ${course.lecturer}
                            </p>
                            <p class="text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i> ${course.schedule}
                            </p>
                        </div>
                    </div>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                            course.attended ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                        }">
                            ${course.attended ? 'Attended' : 'Not Marked'}
                        </span>
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
        const response = await fetch('/api/attendance/history', {
            credentials: 'include'
        });
        const data = await response.json();
        if (data.success) {
            const historyTable = document.getElementById('attendanceHistory');
            historyTable.innerHTML = '';

            data.history.forEach(record => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${record.course_name}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-500">${record.date}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                            record.status === 'present' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }">
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

// Initialize the dashboard
document.addEventListener('DOMContentLoaded', () => {
    fetchUserData();
    fetchAttendanceStats();
    fetchTodayClasses();
    fetchAttendanceHistory();

    // Refresh data every 5 minutes
    setInterval(() => {
        fetchAttendanceStats();
        fetchTodayClasses();
        fetchAttendanceHistory();
    }, 300000);
});