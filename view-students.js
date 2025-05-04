let currentUser = null;
let allStudents = [];

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

async function fetchLevels() {
    try {
        const response = await fetch('students.php?action=levels', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const levelSelect = document.getElementById('levelSelect');
            levelSelect.innerHTML = '<option value="">Select Level</option>';
            data.levels.forEach(level => {
                const option = document.createElement('option');
                option.value = level;
                option.textContent = `Level ${level}`;
                levelSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error fetching levels:', error);
    }
}

async function fetchCourses() {
    try {
        const response = await fetch('students.php?action=courses', {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const courseSelect = document.getElementById('courseSelect');
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            data.courses.forEach(course => {
                const option = document.createElement('option');
                option.value = course.course_code;
                option.textContent = course.course_name;
                courseSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error fetching courses:', error);
    }
}

async function fetchStudentsByLevel(level) {
    try {
        const response = await fetch(`students.php?action=students_by_level&level=${level}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            allStudents = data.students;
            displayStudents(allStudents);
        } else {
            document.getElementById('studentTable').innerHTML = '<tr><td colspan="4">No students found</td></tr>';
            document.getElementById('totalStudents').textContent = '0';
        }
    } catch (error) {
        console.error('Error fetching students:', error);
        document.getElementById('studentTable').innerHTML = '<tr><td colspan="4">Error loading students</td></tr>';
        document.getElementById('totalStudents').textContent = '0';
    }
}

async function fetchStudentsByCourse(courseCode) {
    try {
        const response = await fetch(`students.php?action=students_by_course&course_code=${courseCode}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            allStudents = data.students;
            displayStudents(allStudents);
        } else {
            document.getElementById('studentTable').innerHTML = '<tr><td colspan="4">No students found</td></tr>';
            document.getElementById('totalStudents').textContent = '0';
        }
    } catch (error) {
        console.error('Error fetching students:', error);
        document.getElementById('studentTable').innerHTML = '<tr><td colspan="4">Error loading students</td></tr>';
        document.getElementById('totalStudents').textContent = '0';
    }
}

function displayStudents(students) {
    const studentTable = document.getElementById('studentTable');
    studentTable.innerHTML = '';
    
    if (students.length === 0) {
        studentTable.innerHTML = '<tr><td colspan="4">No students found</td></tr>';
        document.getElementById('totalStudents').textContent = '0';
        return;
    }
    
    students.forEach(student => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${student.full_name}</td>
            <td>${student.index_no}</td>
            <td>${student.level}</td>
            <td>${student.program}</td>
        `;
        studentTable.appendChild(tr);
    });
    
    document.getElementById('totalStudents').textContent = students.length;
}

function searchStudents() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const filteredStudents = allStudents.filter(student => 
        student.full_name.toLowerCase().includes(searchTerm) || 
        student.index_no.toLowerCase().includes(searchTerm)
    );
    displayStudents(filteredStudents);
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLecturerData();
    fetchLevels();
    fetchCourses();

    document.getElementById('levelSelect').addEventListener('change', (e) => {
        document.getElementById('courseSelect').value = ''; // Reset course dropdown
        if (e.target.value) {
            fetchStudentsByLevel(e.target.value);
            document.getElementById('searchInput').value = ''; // Reset search
        } else {
            document.getElementById('studentTable').innerHTML = '';
            document.getElementById('totalStudents').textContent = '0';
        }
    });

    document.getElementById('courseSelect').addEventListener('change', (e) => {
        document.getElementById('levelSelect').value = ''; // Reset level dropdown
        if (e.target.value) {
            fetchStudentsByCourse(e.target.value);
            document.getElementById('searchInput').value = ''; // Reset search
        } else {
            document.getElementById('studentTable').innerHTML = '';
            document.getElementById('totalStudents').textContent = '0';
        }
    });

    document.getElementById('searchInput').addEventListener('input', searchStudents);
});