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

async function fetchSchedule() {
    try {
        const response = await fetch('api/schedule.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            // Render assigned courses
            const courseList = document.getElementById('courseList');
            courseList.innerHTML = '';
            if (data.courses.length === 0) {
                courseList.innerHTML = '<p>No courses assigned.</p>';
            } else {
                data.courses.forEach(course => {
                    const div = document.createElement('div');
                    div.className = 'schedule-item';
                    div.innerHTML = `
                        <h4>${course.course_code} - ${course.course_name}</h4>
                        <p>Level: ${course.level}</p>
                    `;
                    courseList.appendChild(div);
                });
            }

            // Render timetable
            const tbody = document.getElementById('timetableBody');
            tbody.innerHTML = '';
            const startHour = 8;
            const endHour = 18;
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

            for (let hour = startHour; hour <= endHour; hour++) {
                const row = document.createElement('tr');
                row.innerHTML = `<td>${hour.toString().padStart(2, '0')}:00</td>`;
                
                days.forEach(day => {
                    let cellContent = '';
                    data.timetable.forEach(entry => {
                        const start = new Date(`1970-01-01T${entry.start_time}Z`);
                        const end = new Date(`1970-01-01T${entry.end_time}Z`);
                        const current = new Date(`1970-01-01T${hour.toString().padStart(2, '0')}:00:00Z`);
                        
                        if (entry.day_of_week === day && current >= start && current < end) {
                            cellContent += `
                                <div class="schedule-item">
                                    <h4>${entry.course_code} - ${entry.course_name}</h4>
                                    <p>${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
                                    <p>Venue: ${entry.venue}</p>
                                    <p>Level: ${entry.level}</p>
                                </div>
                            `;
                        }
                    });
                    row.innerHTML += `<td>${cellContent}</td>`;
                });
                
                tbody.appendChild(row);
            }
        } else {
            alert(data.message || 'Failed to load schedule');
        }
    } catch (error) {
        console.error('Error fetching schedule:', error);
        alert('Failed to load schedule');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLecturerData();
    fetchSchedule();
});