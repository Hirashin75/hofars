// Combined and robust script.js for HOFARS
// Initialize app on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Load dark mode preference (client-side)
    loadDarkMode();

    // Setup event listeners based on current page
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';

    if (currentPage === 'index.php' || currentPage === '') {
        setupLoginPage();
    } else if (currentPage === 'student_hofars.php') {
        setupStudentDashboard();
    } else if (currentPage === 'student_profile.php') {
        setupStudentProfile();
    } else if (currentPage === 'technician_hofars.php') {
        setupTechnicianDashboard();
    } else if (currentPage === 'technician_profile.php') {
        setupTechnicianProfile();
    } else if (currentPage === 'admin_hofars.php') {
        setupAdminDashboard();
    } else if (currentPage === 'admin_profile.php') {
        setupAdminProfile();
    }

    // Setup common elements (theme toggle, user menu, logout)
    setupCommonElements();
}

// ---------------- Dark Mode ----------------
function loadDarkMode() {
    const darkMode = localStorage.getItem('darkMode') === 'true';
    if (darkMode) {
        document.body.classList.add('dark-mode');
        updateThemeIcon(true);
    }
}

function toggleDarkMode() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', isDark);
    updateThemeIcon(isDark);
}

function updateThemeIcon(isDark) {
    const themeIcons = document.querySelectorAll('.theme-icon');
    themeIcons.forEach(icon => {
        icon.textContent = isDark ? '☀️' : '🌙';
    });
}

// ---------------- Common Elements ----------------
function setupCommonElements() {
    // Theme toggle
    const themeToggles = document.querySelectorAll('#themeToggle');
    themeToggles.forEach(toggle => {
        toggle.addEventListener('click', toggleDarkMode);
    });

    // User menu dropdown
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function() {
            userDropdown.classList.remove('show');
        });
    }

    // Logout functionality (redirect to PHP logout)
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'logout.php';
        });
    }

    // Load user profile image (AJAX)
    loadUserProfileImage();
}

function loadUserProfileImage() {
    const userProfileImg = document.getElementById('userProfileImg');
    if (!userProfileImg) return;

    fetch('get_user_data.php')
        .then(response => response.json())
        .then(data => {
            if (data && data.profilePicture) {
                userProfileImg.src = data.profilePicture;
            }
        })
        .catch(error => console.error('Error loading user data:', error));
}

// ---------------- Login Page ----------------
function setupLoginPage() {
    const showSignupBtn = document.getElementById('showSignup');
    const showLoginBtn = document.getElementById('showLogin');
    const loginBox = document.getElementById('loginBox');
    const signupBox = document.getElementById('signupBox');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const signupPassword = document.getElementById('signupPassword');
    const signupConfirmPassword = document.getElementById('signupConfirmPassword');

    // Safe guards
    if (showSignupBtn && loginBox && signupBox) {
        showSignupBtn.addEventListener('click', function(e) {
            e.preventDefault();
            loginBox.style.display = 'none';
            signupBox.style.display = 'block';
        });
    }

    if (showLoginBtn && loginBox && signupBox) {
        showLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            signupBox.style.display = 'none';
            loginBox.style.display = 'block';
        });
    }

    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Password reset link would be sent to your email.');
        });
    }

    if (signupPassword && signupConfirmPassword) {
        signupPassword.addEventListener('input', validatePassword);
        signupConfirmPassword.addEventListener('input', validatePassword);
    }

    // Password visibility toggles (robust: supports emoji or FontAwesome <i>)
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            // Try to find the associated input: prefer previousElementSibling, fallback to querySelector
            let input = this.previousElementSibling;
            if (!input || (input.tagName && input.tagName.toLowerCase() !== 'input')) {
                // fallback: look inside parent for an input
                input = this.parentElement ? this.parentElement.querySelector('input[type="password"], input[type="text"]') : null;
            }
            if (!input) return;

            // icon could be <span class="eye-icon">emoji</span> or <span class="eye-icon"><i class="fa ..."></i></span>
            const iconSpan = this.querySelector('.eye-icon');
            const iconI = iconSpan ? iconSpan.querySelector('i') : null;

            if (input.type === 'password') {
                input.type = 'text';
                // FontAwesome path (toggle classes)
                iconI.classList.remove('fa-eye');
                iconI.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                iconI.classList.remove('fa-eye-slash');
                iconI.classList.add('fa-eye');
            }
        });
    });
}

// ---------------- Password Validation ----------------
function validatePassword() {
    const pwEl = document.getElementById('signupPassword');
    if (!pwEl) return;
    const password = pwEl.value;

    const validLength = document.getElementById('validLength');
    const validUppercase = document.getElementById('validUppercase');
    const validNumber = document.getElementById('validNumber');

    if (validLength) {
        if (password.length >= 8) {
            validLength.classList.add('valid');
            validLength.style.color = 'green';
        } else {
            validLength.classList.remove('valid');
            validLength.style.color = 'red';
        }
    }

    if (validUppercase) {
        if (/[A-Z]/.test(password)) {
            validUppercase.classList.add('valid');
            validUppercase.style.color = 'green';
        } else {
            validUppercase.classList.remove('valid');
            validUppercase.style.color = 'red';
        }
    }

    if (validNumber) {
        if (/[0-9]/.test(password)) {
            validNumber.classList.add('valid');
            validNumber.style.color = 'green';
        } else {
            validNumber.classList.remove('valid');
            validNumber.style.color = 'red';
        }
    }
}


function isPasswordValid(password) {
    return password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password);
}

// ---------------- Student Dashboard ----------------
function setupStudentDashboard() {
    const navReport = document.getElementById('navReport');
    const navMyReport = document.getElementById('navMyReport');
    const reportSection = document.getElementById('reportSection');
    const myReportSection = document.getElementById('myReportSection');
    const photoUpload = document.getElementById('photoUpload');
    const fileName = document.getElementById('fileName');

    if (navReport && navMyReport && reportSection && myReportSection) {
        navReport.addEventListener('click', function(e) {
            e.preventDefault();
            navReport.classList.add('active');
            navMyReport.classList.remove('active');
            reportSection.classList.add('active');
            myReportSection.classList.remove('active');
        });

        navMyReport.addEventListener('click', function(e) {
            e.preventDefault();
            navMyReport.classList.add('active');
            navReport.classList.remove('active');
            myReportSection.classList.add('active');
            reportSection.classList.remove('active');
            loadMyReports();
        });
    }

    if (photoUpload && fileName) {
        photoUpload.addEventListener('change', function() {
            if (this.files.length > 0) fileName.textContent = this.files[0].name;
        });
    }
}

function loadMyReports() {
    fetch('get_my_reports.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('myReportsTable');
            if (!tbody) return;
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">No reports submitted yet</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(report => `
                <tr>
                    <td>${report.date}</td>
                    <td>${report.time || '-'}</td>
                    <td>${report.student_name}</td>
                    <td>${report.matric_number}</td>
                    <td>${report.room_number}</td>
                    <td>${report.block_number}</td>
                    <td>${report.facility}</td>
                    <td>${report.description}</td>
                    <td><span class="status-badge status-${(report.status || '').toLowerCase()}">${report.status || '-'}</span></td>
                </tr>
            `).join('');
        })
        .catch(error => console.error('Error loading reports:', error));
}

// ---------------- Student Profile ----------------
function setupStudentProfile() {
    // Load current data via AJAX
    fetch('get_user_data.php')
        .then(response => response.json())
        .then(data => {
            if (!data) return;
            const profileUsername = document.getElementById('profileUsername');
            const profileName = document.getElementById('profileName');
            const profileEmail = document.getElementById('profileEmail');
            const profilePicture = document.getElementById('profilePicture');

            if (profileUsername) profileUsername.value = data.username || '';
            if (profileName) profileName.value = data.name || '';
            if (profileEmail) profileEmail.value = data.email || '';
            if (profilePicture && data.profile_picture) profilePicture.src = data.profile_picture;
        });

    const changePictureBtn = document.getElementById('changePictureBtn');
    const profilePictureUpload = document.getElementById('profilePictureUpload');
    const saveProfileBtn = document.getElementById('saveProfileBtn');

    if (changePictureBtn && profilePictureUpload) {
        changePictureBtn.addEventListener('click', function() {
            profilePictureUpload.click();
        });
    }

    if (profilePictureUpload) {
        profilePictureUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const profilePicture = document.getElementById('profilePicture');
                    if (profilePicture) profilePicture.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    if (saveProfileBtn) {
        saveProfileBtn.addEventListener('click', function() {
            const username = document.getElementById('profileUsername') ? document.getElementById('profileUsername').value : '';
            fetch('update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username })
            })
            .then(response => response.json())
            .then(data => alert(data.message || 'Saved'))
            .catch(err => console.error(err));
        });
    }
}

// ---------------- Technician Dashboard ----------------
function setupTechnicianDashboard() {
    loadTechnicianJobs();
}

function loadTechnicianJobs() {
    fetch('get_assigned_jobs.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('jobsTable');
            if (!tbody) return;
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="no-data">No jobs assigned yet</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(job => `
                <tr>
                    <td>${job.date}</td>
                    <td>${job.time || '-'}</td>
                    <td>${job.student_name}</td>
                    <td>${job.room_number}</td>
                    <td>${job.block_number}</td>
                    <td>${job.facility}</td>
                    <td>${job.description}</td>
                    <td><span class="status-badge status-assigned">Assigned</span></td>
                </tr>
            `).join('');
        })
        .catch(err => console.error('Error loading jobs:', err));
}

// ---------------- Admin Dashboard ----------------
function setupAdminDashboard() {
    loadAdminReports();
    loadTechnicianReports();
}

function loadAdminReports() {
    fetch('get_admin_reports.php')
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('adminReportsTable');
            if (!tbody) return;

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="no-data">No reports available</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(report => `
                <tr>
                    <td>${report.date || new Date(report.created_at).toISOString().slice(0,10)}</td>
                    <td>${report.time || new Date(report.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
                    <td>${report.student_name}</td>
                    <td>${report.matric_number}</td>
                    <td>${report.room_number}</td>
                    <td>${report.block_number}</td>
                    <td>${report.facility}</td>
                    <td>${report.description}</td>
                    <td><span class="status-badge status-${(report.status||'').toLowerCase()}">${report.status}</span></td>
                    <td>
                        <select class="technician-select" data-report-id="${report.id}" ${report.status !== 'Pending' ? 'disabled' : ''}>
                            <option value="">Select Technician</option>
                            ${window.technicians.map(t => `<option value="${t.id}" ${t.id==report.technician_id?'selected':''}>${t.name}</option>`).join('')}
                        </select>
                    </td>
                    <td>
                        <button class="btn-assign" onclick="assignToTechnician(${report.id})" ${report.status !== 'Pending' ? 'disabled' : ''}>
                            ${report.status === 'Pending' ? 'Assign' : 'Assigned'}
                        </button>
                    </td>
                </tr>
            `).join('');
        })
        .catch(err => console.error(err));
}

function loadTechnicianReports() {
    fetch('get_technician_reports.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('technicianReportsTable');
            if (!tbody) return;
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="no-data">No technician reports available</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(report => `
                <tr>
                    <td>${report.date}</td>
                    <td>${report.time || '-'}</td>
                    <td>${report.technician_name}</td>
                    <td>${report.job_description}</td>
                    <td>${report.progress_description}</td>
                    <td>${report.files ? report.files.split(',').map(f => `<button onclick="downloadFile('${f}')">📥 ${f}</button>`).join('') : 'No files'}</td>
                    <td><span class="status-badge status-${(report.status || '').toLowerCase()}">${report.status || '-'}</span></td>
                    <td>
                        <button class="btn-approve" onclick="approveTechnicianReport(${report.id})" ${report.status === 'Approved' ? 'disabled' : ''}>
                            ${report.status === 'Approved' ? 'Approved' : 'Approve'}
                        </button>
                    </td>
                </tr>
            `).join('');
        })
        .catch(err => console.error('Error loading technician reports:', err));
}

// ---------------- Actions ----------------
function assignToTechnician(reportId) {
    const select = document.querySelector(`.technician-select[data-report-id="${reportId}"]`);
    const techId = select.value;
    if (!techId) { alert('Select technician first'); return; }

    fetch('assign_report.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({reportId, technicianId: techId})
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        loadAdminReports(); // refresh table without page reload
    })
    .catch(err => console.error(err));
}

function approveTechnicianReport(reportId) {
    fetch('approve_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reportId })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message || 'Updated');
        loadTechnicianReports();
        loadAdminReports();
    })
    .catch(err => console.error('Error approving report:', err));
}

function downloadFile(fileName) {
    // Demo placeholder
    alert(`Downloading file: ${fileName}\n\nNote: This is a demo system. In a real system, the file would be downloaded.`);
}

// Admin Profile Setup (reuses student profile behavior)
function setupAdminProfile() {
    setupStudentProfile();
}
