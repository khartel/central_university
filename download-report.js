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
        } else {
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Error fetching lecturer data:', error);
        window.location.href = 'index.html';
    }
}

async function fetchCourses() {
    try {
        const response = await fetch('api/attendance.php?action=courses', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const courseSelect = document.getElementById('courseSelect');
            data.courses.forEach(course => {
                const option = new Option(`${course.course_code} - ${course.course_name}`, course.course_code);
                courseSelect.add(option);
            });
        }
    } catch (error) {
        console.error('Error fetching courses:', error);
    }
}

async function downloadReport() {
    const courseCode = document.getElementById('courseSelect').value;
    const period = document.getElementById('periodSelect').value;
    let week = '';

    if (!courseCode) {
        alert('Please select a course');
        return;
    }

    if (period === 'specific_week') {
        week = document.getElementById('weekPicker').value;
        if (!week) {
            alert('Please select a week');
            return;
        }
    }

    try {
        const url = new URL('api/attendance.php', window.location.origin);
        url.searchParams.append('action', 'download_report');
        url.searchParams.append('course_code', courseCode);
        url.searchParams.append('period', period);
        if (week) {
            url.searchParams.append('week', week);
        }

        const response = await fetch(url, {
            credentials: 'include'
        });

        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            if (!data.success) {
                alert(data.message || 'Failed to generate report');
                return;
            }
        }

        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status} ${response.statusText}`);
        }

        const blob = await response.blob();
        const urlObj = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = urlObj;
        a.download = `attendance_report_${courseCode}_${period}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(urlObj);
        document.body.removeChild(a);
    } catch (error) {
        console.error('Error downloading report:', error);
        alert(`Failed to download report: ${error.message}`);
    }
}

function goBack() {
    window.location.href = 'lecturer-dashboard.html';
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLecturerData();
    fetchCourses();

    const periodSelect = document.getElementById('periodSelect');
    const weekPickerContainer = document.getElementById('weekPickerContainer');

    periodSelect.addEventListener('change', (e) => {
        weekPickerContainer.style.display = e.target.value === 'specific_week' ? 'block' : 'none';
    });
});