let currentUser = null;
const urlParams = new URLSearchParams(window.location.search);
const courseCode = urlParams.get('course_code');

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
        } else {
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Error fetching lecturer data:', error);
        window.location.href = 'index.html';
    }
}

async function fetchAttendanceDetails(status = 'present') {
    try {
        const response = await fetch(`api/attendance.php?action=details&course_code=${courseCode}&status=${status}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const attendanceTable = document.getElementById('attendanceDetailsTable');
            attendanceTable.innerHTML = '';
            document.getElementById('courseTitle').textContent = `Attendance Details for ${data.course_name}`;

            if (data.details.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td colspan="3" style="text-align: center;">No ${status} students found</td>`;
                attendanceTable.appendChild(tr);
                return;
            }

            data.details.forEach(record => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${record.student_name}</td>
                    <td>${record.index_no}</td>
                    <td class="${record.status === 'present' ? 'text-green-600' : 'text-red-600'}">${record.status.charAt(0).toUpperCase() + record.status.slice(1)}</td>
                `;
                attendanceTable.appendChild(tr);
            });
        } else {
            alert(data.message || 'Failed to load attendance details');
        }
    } catch (error) {
        console.error('Error fetching attendance details:', error);
        alert('Failed to load attendance details');
    }
}

function goBack() {
    window.location.href = 'lecturer-dashboard.html';
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLecturerData();
    fetchAttendanceDetails('present'); // Default to present

    const statusFilter = document.getElementById('statusFilter');
    statusFilter.addEventListener('change', (e) => {
        fetchAttendanceDetails(e.target.value);
    });
});