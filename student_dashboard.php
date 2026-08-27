<!-- FLASH MESSAGES -->
<?php if (!empty($flashMessage)): ?>
    <div class="card" style="margin-bottom: 20px; border-color: <?php echo $flashType === 'error' ? '#fecaca' : '#bbf7d0'; ?>;">
        <div class="card-body" style="padding: 14px 18px; color: <?php echo $flashType === 'error' ? '#991b1b' : '#166534'; ?>; background: <?php echo $flashType === 'error' ? '#fef2f2' : '#f0fdf4'; ?>;">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- TAB 0: WELCOME DASHBOARD                   -->
<!-- ========================================== -->
<div id="student-welcome-dashboard" class="tab-content active">

    <div class="welcome-banner">
        <h2>Welcome back, <?php echo htmlspecialchars($profile['name'] ?: 'Student'); ?>!</h2>
        <p>Roll Number: <strong><?php echo htmlspecialchars($rollno); ?></strong> | Department: <strong><?php echo htmlspecialchars($semesterDetail['department'] ?: 'IT/CS'); ?></strong></p>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-left">
            <h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 15px;">Quick Actions</h3>
            <div class="action-boxes-container">
                <!-- Action 1: Reports -->
                <div class="action-box" onclick="switchTab('student-reports', document.getElementById('nav-item-student-reports'))">
                    <div class="action-icon-wrapper">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                    <h3>Weekly Internship Reports</h3>
                    <p>Submit weekly tasks, track supervisor remarks, and view evaluation grades.</p>
                </div>
                
                <!-- Action 2: Recommendation Letters -->
                <div class="action-box" onclick="switchTab('student-letters', document.getElementById('nav-item-student-letters'))">
                    <div class="action-icon-wrapper">
                        <i class="fa-solid fa-file-contract"></i>
                    </div>
                    <h3>Internship Letter</h3>
                    <p>View, print, and download your official department-approved internship recommendation letter.</p>
                </div>

                <!-- Action 3: Site Supervisor -->
                <div class="action-box" onclick="switchTab('student-site-supervisor', document.getElementById('nav-item-student-site-supervisor'))">
                    <div class="action-icon-wrapper">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <h3>Site Supervisor Details</h3>
                    <p>View your assigned placement organization, contact person, and site supervisor contact information.</p>
                </div>
                
                <!-- Action 4: Profile Settings -->
                <div class="action-box" onclick="switchToProfileTab()">
                    <div class="action-icon-wrapper">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                    <h3>My Profile & Settings</h3>
                    <p>Manage and update your personal info, contact information, CNIC, and portal credentials.</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-right">
            <!-- Announcements Card -->
            <div class="announcements-card">
                <div class="announcements-header">
                    <i class="fa-solid fa-bullhorn"></i> Important Announcements
                </div>
                <div class="announcements-body">
                    <?php
                    $stdAnnQuery = mysqli_query($conn, "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
                    $stdAnnouncements = [];
                    if ($stdAnnQuery) {
                        while ($row = mysqli_fetch_assoc($stdAnnQuery)) {
                            $stdAnnouncements[] = $row;
                        }
                    }
                    if (empty($stdAnnouncements)):
                    ?>
                        <p style="font-size: 13px; color: #64748b; text-align: center; padding: 10px 0;">No active announcements from the department.</p>
                    <?php else: ?>
                        <?php foreach ($stdAnnouncements as $ann): ?>
                            <div class="announcement-item">
                                <div class="announcement-meta">
                                    <span><i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($ann['created_by']); ?></span>
                                    <span><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></span>
                                </div>
                                <div class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                <div class="announcement-content">
                                    <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 1: PERSONAL (Academic & Profile Info)  -->
<!-- ========================================== -->
<div id="student-dashboard" class="tab-content">


    <?php
    // Helper to generate dynamic credential string
    $deptAbbr = 'DEPT';
    if (!empty($semesterDetail['department'])) {
        $dept = strtolower($semesterDetail['department']);
        if (strpos($dept, 'information technology') !== false) {
            $deptAbbr = 'IT';
        } elseif (strpos($dept, 'computer science') !== false) {
            $deptAbbr = 'CS';
        } elseif (strpos($dept, 'software engineering') !== false) {
            $deptAbbr = 'SE';
        } else {
            $words = explode(' ', $semesterDetail['department']);
            $deptAbbr = '';
            foreach ($words as $w) {
                $deptAbbr .= strtoupper($w[0] ?? '');
            }
        }
    }
    
    $progAbbr = 'PROG';
    if (!empty($semesterDetail['program'])) {
        $prog = $semesterDetail['program'];
        if (strtolower($prog) === 'bachelor of science in artificial intelligence') {
            $progAbbr = 'BS(AI)';
        } elseif (strtolower($prog) === 'bs gy') {
            $progAbbr = 'BS(GY)';
        } elseif (strtolower($prog) === 'bs ce') {
            $progAbbr = 'BS(CE)';
        } elseif (strtolower($prog) === 'bs ng') {
            $progAbbr = 'BS(NG)';
        } else {
            $progAbbr = $prog;
        }
    }
    $credString = ($rollno ?: 'ROLLNO') . '-' . $deptAbbr . '-' . $progAbbr . '/UOH';
    ?>

    <div class="student-profile-wrapper">
        <!-- LEFT COLUMN: Profile Sidebar -->
        <div class="student-profile-sidebar">
            <div class="profile-pic-frame">
                <!-- Fallback user icon with styling similar to screenshot -->
                <i class="fa-solid fa-user"></i>
            </div>
            
            <div class="student-name-title"><?php echo htmlspecialchars($profile['name'] ?: 'Student Name'); ?></div>
            <div class="student-dept-subtitle"><?php echo htmlspecialchars($semesterDetail['department'] ?: 'Department'); ?></div>
            
            <hr class="profile-divider">
            <div class="sidebar-info-text"><?php echo htmlspecialchars($profile['fname'] ?: 'Father Name'); ?></div>
            
            <hr class="profile-divider">
            <div class="sidebar-info-text"><?php echo htmlspecialchars($rollno); ?></div>
            
            <hr class="profile-divider">
            <div class="sidebar-info-text" style="font-size: 13.5px; font-weight: 600; color: #475569;"><?php echo htmlspecialchars($credString); ?></div>
            
            <div class="sidebar-cred-badge">Official Email Credentials</div>
            
            <div class="cred-label">Email Address</div>
            <div class="cred-val"><?php echo htmlspecialchars($profile['email'] ?: ($rollno . '@student.uoh.edu.pk')); ?></div>
            
            <div class="cred-label">Password</div>
            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span id="password-display" style="font-weight: 600; font-size: 15px; color: #334155;">••••••••</span>
                <button type="button" onclick="togglePasswordVisibility()" style="border: none; background: none; cursor: pointer; padding: 0; display: inline-flex;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; background-color: #198754; color: #ffffff; width: 26px; height: 26px; border-radius: 50%;">
                        <i class="fa-solid fa-eye" id="password-toggle-icon" style="font-size: 12px;"></i>
                    </span>
                </button>
            </div>
        </div>
        
        <!-- RIGHT COLUMN: Information Card / Form -->
        <div class="student-profile-main">
            <div class="info-card-header">Information</div>
            <div class="info-card-body">
                <form action="" method="POST">
                    <div class="info-row">
                        <label class="info-label" for="name">Name</label>
                        <div class="info-value">
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="fname">Father Name</label>
                        <div class="info-value">
                            <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($profile['fname'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($profile['fname'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="cnic">CNIC</label>
                        <div class="info-value">
                            <input type="text" id="cnic" name="cnic" value="<?php echo htmlspecialchars($profile['cnic'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($profile['cnic'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="session">Session</label>
                        <div class="info-value">
                            <input type="text" id="session" name="session" value="<?php echo htmlspecialchars($semesterDetail['session'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($semesterDetail['session'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="department">Department</label>
                        <div class="info-value">
                            <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($semesterDetail['department'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($semesterDetail['department'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="program">Program</label>
                        <div class="info-value">
                            <input type="text" id="program" name="program" value="<?php echo htmlspecialchars($semesterDetail['program'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($semesterDetail['program'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="semester">Current Semester</label>
                        <div class="info-value">
                            <input type="text" id="semester" name="semester" value="<?php echo htmlspecialchars($semesterDetail['semester'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($semesterDetail['semester'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="batch">Batch</label>
                        <div class="info-value">
                            <input type="text" id="batch" name="batch" value="<?php echo htmlspecialchars($semesterDetail['batch'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($semesterDetail['batch'] ?? ''); ?>" readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="section">Section</label>
                        <div class="info-value">
                            <input type="text" id="section" name="section" value="<?php echo htmlspecialchars($semesterDetail['section'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($semesterDetail['section'] ?? ''); ?>" readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="dob">Date of Birth</label>
                        <div class="info-value">
                            <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($profile['dob'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($profile['dob'] ?? ''); ?>" readonly class="info-input-field">
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label class="info-label" for="cellno">Cell No</label>
                        <div class="info-value">
                            <input type="text" id="cellno" name="cellno" value="<?php echo htmlspecialchars($profile['cell_no'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($profile['cell_no'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>

                    <div class="info-row">
                        <label class="info-label" for="email">Email Address</label>
                        <div class="info-value">
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required readonly class="info-input-field">
                        </div>
                    </div>

                    <div class="info-row">
                        <label class="info-label" for="city">City</label>
                        <div class="info-value">
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" readonly class="info-input-field">
                        </div>
                    </div>

                    <div class="info-row" style="align-items: flex-start;">
                        <label class="info-label" for="address" style="margin-top: 8px;">Address</label>
                        <div class="info-value">
                            <textarea id="address" name="address" rows="2" class="info-input-field" readonly style="resize: vertical;" data-original="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div id="edit-btn-container" style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="enableEditMode()" class="btn-save-info" style="background: linear-gradient(135deg, #2e6652 0%, #26294d 100%);">Edit Profile</button>
                    </div>
                    <div id="save-btn-container" style="display: none; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                        <button type="button" onclick="disableEditMode()" class="btn-cancel" style="margin-top: 0; padding: 10px 20px;">Cancel</button>
                        <button type="submit" name="save_student_dashboard" class="btn-save-info" style="margin-top: 0;">Save Information</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function togglePasswordVisibility() {
            const pwdSpan = document.getElementById('password-display');
            const icon = document.getElementById('password-toggle-icon');
            if (pwdSpan.innerText === '••••••••') {
                pwdSpan.innerText = '<?php echo htmlspecialchars($rollno); ?>';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pwdSpan.innerText = '••••••••';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function enableEditMode() {
            document.querySelectorAll('.info-input-field').forEach(input => {
                input.removeAttribute('readonly');
            });
            document.getElementById('edit-btn-container').style.display = 'none';
            document.getElementById('save-btn-container').style.display = 'flex';
        }

        function disableEditMode() {
            document.querySelectorAll('.info-input-field').forEach(input => {
                input.setAttribute('readonly', 'true');
                if (input.hasAttribute('data-original')) {
                    input.value = input.getAttribute('data-original');
                }
            });
            document.getElementById('edit-btn-container').style.display = 'block';
            document.getElementById('save-btn-container').style.display = 'none';
        }
    </script>
</div>

<!-- ========================================== -->
<!-- TAB 2: INTERNSHIP REPORTS                  -->
<!-- ========================================== -->
<div id="student-reports" class="tab-content">
    <div class="table-header-bar">
        <button class="btn-primary-action" onclick="openModal('reportModal')">
            <i class="fa-solid fa-plus"></i> Add Report
        </button>
    </div>

    <div class="card">
        <div class="card-header">Submitted Internship Reports</div>
        <div class="card-body" style="padding: 0;">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Sr#</th>
                        <th>Session</th>
                        <th>Semester</th>
                        <th>Report Detail</th>
                        <th>Attachment</th>
                        <th>Obtained Marks</th>
                        <th>Total Marks</th>
                        <th>Feedback / Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($latestReport['report_detail'])): ?>
                        <tr>
                            <td>1</td>
                            <td><?php echo htmlspecialchars($latestReport['session'] ?? $semesterDetail['session'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($latestReport['semester'] ?? $semesterDetail['semester'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($latestReport['report_detail'], 0, 40, '...')); ?></td>
                            <td>
                                <?php if (!empty($latestReport['report_ref_img'])): ?>
                                    <a href="<?php echo htmlspecialchars($latestReport['report_ref_img']); ?>" target="_blank" style="color: #2e6652; text-decoration: underline;">
                                        <i class="fa-solid fa-paperclip"></i> View File
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">None</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($latestMarks['intern_total_obt_marks'] ?: $latestReport['report_marks'] ?: 'Pending'); ?></strong></td>
                            <td><?php echo htmlspecialchars($latestMarks['total_marks'] ?: 'Pending'); ?></td>
                            <td>
                                <?php if (!empty($latestReport['report_feedback'])): ?>
                                    <span style="font-size: 13px;"><?php echo htmlspecialchars($latestReport['report_feedback']); ?></span>
                                <?php else: ?>
                                    <span class="badge-status badge-pending">Pending Review</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #777; padding: 25px;">No internship reports submitted yet. Click <strong>Add Report</strong> above to create one.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 3: INTERNSHIP LETTERS                  -->
<!-- ========================================== -->
<div id="student-letters" class="tab-content">
    <?php $isLetterApproved = (int)($semesterDetail['letter_approved'] ?? 0) === 1; ?>
    <div class="table-header-bar">
        <?php if ($isLetterApproved): ?>
            <div class="btn-primary-action" style="cursor: default; pointer-events: none;">
                <i class="fa-solid fa-envelope-open-text"></i> Internship Letter
            </div>
        <?php else: ?>
            <div class="btn-primary-action" style="background-color: #94a3b8; cursor: default; opacity: 0.7; pointer-events: none;">
                <i class="fa-solid fa-lock"></i> Internship Letter (Locked)
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">Internship Recommendation Letters & Drafts</div>
        <div class="card-body" style="padding: 0;">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Sr#</th>
                        <th>Student Name</th>
                        <th>Roll No</th>
                        <th>Session</th>
                        <th>Letter Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><?php echo htmlspecialchars($profile['name'] ?: 'Student User'); ?></td>
                        <td><?php echo htmlspecialchars($rollno); ?></td>
                        <td><?php echo htmlspecialchars($semesterDetail['session'] ?: 'N/A'); ?></td>
                        <td>Official Internship Request Letter</td>
                        <td>
                            <?php if ($isLetterApproved): ?>
                                <span class="badge-status badge-approved">Approved</span>
                            <?php else: ?>
                                <span class="badge-status badge-pending">Pending Approval</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isLetterApproved): ?>
                                <button class="btn-table-action" onclick="openModal('letterModal')">
                                    <i class="fa-solid fa-eye"></i> View Draft
                                </button>
                            <?php else: ?>
                                <button class="btn-table-action" disabled style="background-color: #94a3b8; cursor: not-allowed; opacity: 0.7;">
                                    <i class="fa-solid fa-lock"></i> Locked
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 4: SITE SUPERVISOR                     -->
<!-- ========================================== -->
<div id="student-site-supervisor" class="tab-content">
    <div class="table-header-bar">
        <button class="btn-primary-action" onclick="openModal('placementModal')" style="background: linear-gradient(135deg, #2e6652 0%, #26294d 100%);">
            <i class="fa-solid fa-pen-to-square"></i> <?php echo empty($placement['org_name']) ? 'Insert Site Supervisor Details' : 'Edit Placement Details'; ?>
        </button>
    </div>

    <div class="card">
        <div class="card-header">
            <span><i class="fa-solid fa-user-tie"></i> Site Supervisor & Placement Details</span>
            <span style="font-size: 13px; opacity: 0.9;">My Industry Assignment</span>
        </div>
        <div class="card-body">
            <?php if (empty($placement['org_name'])): ?>
                <div style="text-align: center; color: #64748b; padding: 30px;">
                    <i class="fa-solid fa-building-circle-exclamation fa-2x" style="display: block; margin-bottom: 8px;"></i>
                    No internship placement or site supervisor has been assigned to you yet. Please click the button above to insert your details.
                </div>
            <?php else: ?>
                <div class="org-info-card" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; background: #ffffff;">
                    <div class="org-info-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 15px;">
                        <div>
                            <h4 style="font-size: 17px; font-weight: 700; color: #1e293b; margin: 0;">
                                <i class="fa-solid fa-building text-success" style="margin-right: 5px;"></i> 
                                <?php echo htmlspecialchars($placement['org_name']); ?>
                            </h4>
                            <div style="display: flex; gap: 6px; margin-top: 6px;">
                                <span class="status-pill pill-submitted" style="font-size: 11px; padding: 3px 8px; border-radius: 12px; background: #e0f2fe; color: #0369a1; font-weight: 700;">
                                    Category: <?php echo htmlspecialchars($placement['category'] ?? 'General'); ?>
                                </span>
                                <?php if (!empty($placement['type'])): ?>
                                    <span class="status-pill" style="font-size: 11px; padding: 3px 8px; border-radius: 12px; background-color: #3b82f6; color: white; font-weight: 700;">
                                        Type: <?php echo htmlspecialchars($placement['type']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="revision-badge" style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 4px; font-size: 12px; border: 1px solid #cbd5e1; font-weight: 600;">
                                <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($placement['address']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="org-details-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; font-size: 13.5px;">
                        <!-- Organization Contact Person Block -->
                        <div class="org-detail-block" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">
                            <h5 style="font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 10px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
                                <i class="fa-solid fa-address-book"></i> Organization Contact Person
                            </h5>
                            <p style="margin-bottom: 6px;"><strong>Name:</strong> <?php echo htmlspecialchars($placement['contact_person_name'] ?? 'N/A'); ?></p>
                            <p style="margin-bottom: 6px;"><strong>Designation:</strong> <?php echo htmlspecialchars($placement['contact_person_designation'] ?? 'N/A'); ?></p>
                            <p style="margin-bottom: 6px;"><strong>Cell No:</strong> <?php echo htmlspecialchars($placement['contact_person_phone'] ?? 'N/A'); ?></p>
                            <p style="margin-bottom: 6px;"><strong>Email:</strong> <?php echo htmlspecialchars($placement['contact_person_email'] ?? 'N/A'); ?></p>
                        </div>

                        <!-- Site Supervisor Block -->
                        <div class="org-detail-block" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">
                            <h5 style="font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 10px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
                                <i class="fa-solid fa-user-tie"></i> Placed Site Supervisor
                            </h5>
                            <p style="margin-bottom: 6px;"><strong>Name:</strong> <?php echo htmlspecialchars($placement['site_supervisor_name'] ?? 'Not Assigned'); ?></p>
                            <p style="margin-bottom: 6px;"><strong>Designation:</strong> <?php echo htmlspecialchars($placement['site_supervisor_designation'] ?? 'N/A'); ?></p>
                            <p style="margin-bottom: 6px;"><strong>Cell No:</strong> <?php echo htmlspecialchars($placement['site_supervisor_phone'] ?? 'N/A'); ?></p>
                            <p style="margin-bottom: 6px;"><strong>Email:</strong> <?php echo htmlspecialchars($placement['site_supervisor_email'] ?? 'N/A'); ?></p>
                        </div>

                        <!-- Internship Project Placement Block -->
                        <div class="org-detail-block" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">
                            <h5 style="font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 10px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
                                <i class="fa-solid fa-briefcase"></i> Placed Internship & Project
                            </h5>
                            <p style="margin-bottom: 6px;"><strong>Project Title:</strong> <?php echo htmlspecialchars($placement['internship_title'] ?? 'N/A'); ?></p>
                            <p style="margin-bottom: 6px;"><strong>Duration:</strong> <?php echo (int)($placement['duration_weeks'] ?? 0); ?> Weeks</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 5: RESET PASSWORD                      -->
<!-- ========================================== -->
<div id="student-change-password" class="tab-content" style="background-color: #e3efea; padding: 20px; border-radius: 6px;">
    <h2 style="font-size: 22px; font-weight: 600; color: #1e293b; margin-bottom: 20px;">Reset Password</h2>
    
    <div class="student-profile-wrapper" style="margin-top: 0;">
        <!-- LEFT COLUMN: Profile Sidebar -->
        <div class="student-profile-sidebar" style="border: 1px solid #c2dbd0;">
            <div class="profile-pic-frame" style="border-color: #2e6652; background: linear-gradient(135deg, #2e6652 0%, #26294d 100%);">
                <i class="fa-solid fa-user"></i>
            </div>
            
            <div class="student-name-title" style="color: #2e6652;"><?php echo htmlspecialchars($profile['name'] ?: 'Student Name'); ?></div>
            <div class="student-dept-subtitle" style="color: #2e6652; font-weight: 600;"><?php echo htmlspecialchars($semesterDetail['department'] ?: 'Department'); ?></div>
            
            <hr class="profile-divider">
            <div class="sidebar-info-text"><?php echo htmlspecialchars($profile['fname'] ?: 'Father Name'); ?></div>
            
            <hr class="profile-divider">
            <div class="sidebar-info-text"><?php echo htmlspecialchars($rollno); ?></div>
            
            <hr class="profile-divider">
            <div class="sidebar-info-text" style="font-size: 13.5px; font-weight: 600; color: #26294d;"><?php echo htmlspecialchars($credString); ?></div>
        </div>
        
        <!-- RIGHT COLUMN: Reset Password Card -->
        <div class="student-profile-main" style="border: 1px solid #c2dbd0;">
            <div class="info-card-header" style="background: linear-gradient(135deg, #2e6652 0%, #26294d 100%); padding: 14px 20px;">Reset Password</div>
            <div class="info-card-body" style="padding: 25px 20px;">
                <form action="" method="POST" id="change-password-form">
                    
                    <div class="info-row" style="margin-bottom: 20px;">
                        <label class="info-label" style="width: 25%; font-weight: bold; color: #2e6652;" for="old_password">Old Password</label>
                        <div class="info-value" style="width: 75%; position: relative;">
                            <input type="password" id="old_password" name="old_password" required class="info-input-field" style="background-color: #ffffff; border: 1px solid #cbd5e1; padding-right: 40px; width: 100%;" placeholder="Old Password">
                            <span onclick="toggleFieldPassword('old_password', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #dc3545; display: inline-flex; align-items: center;">
                                <i class="fa-solid fa-eye" style="font-size: 14px;"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row" style="margin-bottom: 8px;">
                        <label class="info-label" style="width: 25%; font-weight: bold; color: #2e6652;" for="new_password">New Password</label>
                        <div class="info-value" style="width: 75%; position: relative;">
                            <input type="password" id="new_password" name="new_password" required class="info-input-field" style="background-color: #ffffff; border: 1px solid #cbd5e1; padding-right: 40px; width: 100%;" placeholder="New Password" oninput="validateNewPassword()">
                            <span onclick="toggleFieldPassword('new_password', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #dc3545; display: inline-flex; align-items: center;">
                                <i class="fa-solid fa-eye" style="font-size: 14px;"></i>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Password requirements info checklist -->
                    <div class="info-row" style="margin-bottom: 20px;">
                        <div class="info-label" style="width: 25%;"></div>
                        <div class="info-value" style="width: 75%; font-size: 12.5px; color: #64748b; line-height: 1.5;">
                            Password must contain:
                            <ul style="margin: 5px 0 0 15px; padding: 0; list-style-type: disc;">
                                <li id="req-length" style="color: #64748b;">At least 6 characters</li>
                                <li id="req-uppercase" style="color: #64748b;">At least 1 uppercase letter</li>
                                <li id="req-number" style="color: #64748b;">At least 1 number</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="info-row" style="margin-bottom: 25px;">
                        <label class="info-label" style="width: 25%; font-weight: bold; color: #2e6652;" for="confirm_password">Confirm Password</label>
                        <div class="info-value" style="width: 75%; position: relative;">
                            <input type="password" id="confirm_password" name="confirm_password" required class="info-input-field" style="background-color: #ffffff; border: 1px solid #cbd5e1; padding-right: 40px; width: 100%;" placeholder="Confirm Password">
                            <span onclick="toggleFieldPassword('confirm_password', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #dc3545; display: inline-flex; align-items: center;">
                                <i class="fa-solid fa-eye" style="font-size: 14px;"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div style="padding-left: 25%; text-align: left;">
                        <button type="submit" name="change_student_password" class="btn-save-info" style="background: linear-gradient(135deg, #2e6652 0%, #26294d 100%); padding: 12px 30px; font-size: 15px; border-radius: 4px;">Reset Password</button>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function toggleFieldPassword(fieldId, iconContainer) {
            const inputField = document.getElementById(fieldId);
            const icon = iconContainer.querySelector('i');
            if (inputField && icon) {
                if (inputField.type === 'password') {
                    inputField.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    inputField.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }
        
        function validateNewPassword() {
            const val = document.getElementById('new_password').value;
            
            // At least 6 characters
            const reqLen = document.getElementById('req-length');
            if (val.length >= 6) {
                reqLen.style.color = '#155724';
                reqLen.style.fontWeight = 'bold';
            } else {
                reqLen.style.color = '#64748b';
                reqLen.style.fontWeight = 'normal';
            }
            
            // At least 1 uppercase letter
            const reqUpper = document.getElementById('req-uppercase');
            if (/[A-Z]/.test(val)) {
                reqUpper.style.color = '#155724';
                reqUpper.style.fontWeight = 'bold';
            } else {
                reqUpper.style.color = '#64748b';
                reqUpper.style.fontWeight = 'normal';
            }
            
            // At least 1 number
            const reqNum = document.getElementById('req-number');
            if (/[0-9]/.test(val)) {
                reqNum.style.color = '#155724';
                reqNum.style.fontWeight = 'bold';
            } else {
                reqNum.style.color = '#64748b';
                reqNum.style.fontWeight = 'normal';
            }
        }
        
        // Add form validation
        document.getElementById('change-password-form').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass.length < 6 || !/[A-Z]/.test(newPass) || !/[0-9]/.test(newPass)) {
                e.preventDefault();
                alert('Please ensure your new password satisfies all validation criteria.');
                return;
            }
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('New password and confirm password do not match.');
                return;
            }
        });
    </script>
</div>

<!-- ========================================== -->
<!-- MODAL 1: ADD REPORT POPUP                   -->
<!-- ========================================== -->
<div id="reportModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-circle-plus"></i> Submit Internship Report</h3>
            <span class="modal-close" onclick="closeModal('reportModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="pop_rollno">Roll No</label>
                        <input type="text" id="pop_rollno" value="<?php echo htmlspecialchars($rollno ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="pop_session">Session</label>
                        <input type="text" id="pop_session" name="session" value="<?php echo htmlspecialchars($semesterDetail['session'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="pop_semester">Semester</label>
                        <input type="text" id="pop_semester" name="semester" value="<?php echo htmlspecialchars($semesterDetail['semester'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 12px;">
                    <label for="pop_report_detail">Report Detail</label>
                    <textarea id="pop_report_detail" name="report_detail" rows="5" placeholder="Type your internship weekly report details here..." required><?php echo htmlspecialchars($latestReport['report_detail'] ?? ''); ?></textarea>
                </div>
                <div class="form-group" style="margin-top: 12px;">
                    <label for="pop_report_ref_img">Reference Image / File</label>
                    <input type="file" id="pop_report_ref_img" name="report_ref_img" accept="image/png, image/jpeg">
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn-cancel" onclick="closeModal('reportModal')">Cancel</button>
                    <button type="submit" name="submit_report" class="btn-submit" style="margin-top: 0;">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL 2: INTERNSHIP LETTER DRAFT POPUP    -->
<!-- ========================================== -->
<div id="letterModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-contract"></i> Internship Recommendation Letter</h3>
            <span class="modal-close" onclick="closeModal('letterModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="letter-paper">
                <div style="text-align: center; border-bottom: 2px solid #1d2243; padding-bottom: 10px; margin-bottom: 15px;">
                    <h2 style="font-size: 18px; color: #1d2243; text-transform: uppercase; margin: 0;">University of Haripur</h2>
                    <p style="font-size: 12px; color: #666; margin-top: 2px;">Department of Information Technology / Computer Science</p>
                </div>
                
                <p style="text-align: right; font-size: 12px; color: #555; margin-bottom: 15px;">Date: <strong><?php echo date('F d, Y'); ?></strong></p>
 
                <p style="font-weight: bold; margin-bottom: 10px; font-size: 13px;">To Whom It May Concern,</p>
 
                <p style="font-size: 13px; line-height: 1.6; color: #333; margin-bottom: 12px;">
                    This is to certify that <strong><?php echo htmlspecialchars($profile['name'] ?: '[Student Name]'); ?></strong>, Son/Daughter of <strong><?php echo htmlspecialchars($profile['fname'] ?: '[Father Name]'); ?></strong> bearing Roll No: <strong><?php echo htmlspecialchars($rollno); ?></strong>, is a bona fide student of Session <strong><?php echo htmlspecialchars($semesterDetail['session'] ?: '[Session]'); ?></strong> at our institution.
                </p>
 
                <p style="font-size: 13px; line-height: 1.6; color: #333; margin-bottom: 15px;">
                    As part of our degree program requirements, the student is required to complete an internship to gain practical industry exposure. We highly recommend them for an internship position at your esteemed organization.
                </p>
 
                <div style="margin-top: 30px; display: flex; justify-content: space-between; font-size: 12px; color: #444;">
                    <div>
                        <p>_______________________</p>
                        <p><strong>Department Focal Person</strong></p>
                    </div>
                    <div style="text-align: right;">
                        <p>_______________________</p>
                        <p><strong>Head of Department</strong></p>
                    </div>
                </div>
            </div>
 
            <div style="margin-top: 20px; text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="closeModal('letterModal')">Close</button>
                <button type="button" class="btn-submit" style="margin-top: 0;" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print / Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL 3: PLACEMENT & SITE SUPERVISOR POPUP -->
<!-- ========================================== -->
<div id="placementModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 750px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-tie"></i> <?php echo empty($placement['org_name']) ? 'Insert Site Supervisor Details' : 'Edit Placement Details'; ?></h3>
            <span class="modal-close" onclick="closeModal('placementModal')">&times;</span>
        </div>
        <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto; padding-right: 8px;">
            <form action="" method="POST">
                <div class="org-info-card" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; background: #ffffff; margin-bottom: 20px;">
                    <h4 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 8px;">
                        <i class="fa-solid fa-building text-success" style="margin-right: 5px;"></i> Organization details
                    </h4>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label for="modal_org_name">Organization Name</label>
                            <input type="text" id="modal_org_name" name="org_name" value="<?php echo htmlspecialchars($placement['org_name']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_org_address">Address</label>
                            <input type="text" id="modal_org_address" name="address" value="<?php echo htmlspecialchars($placement['address']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_org_category">Category</label>
                            <input type="text" id="modal_org_category" name="category" value="<?php echo htmlspecialchars($placement['category']); ?>" placeholder="e.g. Software House, Telecom" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_org_type">Type</label>
                            <input type="text" id="modal_org_type" name="type" value="<?php echo htmlspecialchars($placement['type']); ?>" placeholder="e.g. IT, Non-IT" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                    </div>
                </div>

                <div class="org-info-card" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; background: #ffffff; margin-bottom: 20px;">
                    <h4 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 8px;">
                        <i class="fa-solid fa-address-book" style="color: #3b82f6; margin-right: 5px;"></i> Organization Contact Person
                    </h4>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label for="modal_cp_name">Name</label>
                            <input type="text" id="modal_cp_name" name="contact_person_name" value="<?php echo htmlspecialchars($placement['contact_person_name']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_cp_designation">Designation</label>
                            <input type="text" id="modal_cp_designation" name="contact_person_designation" value="<?php echo htmlspecialchars($placement['contact_person_designation']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_cp_phone">Cell No</label>
                            <input type="text" id="modal_cp_phone" name="contact_person_phone" value="<?php echo htmlspecialchars($placement['contact_person_phone']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_cp_email">Email</label>
                            <input type="email" id="modal_cp_email" name="contact_person_email" value="<?php echo htmlspecialchars($placement['contact_person_email']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                    </div>
                </div>

                <div class="org-info-card" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; background: #ffffff; margin-bottom: 20px;">
                    <h4 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 8px;">
                        <i class="fa-solid fa-user-tie" style="color: #f59e0b; margin-right: 5px;"></i> Site Supervisor details
                    </h4>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label for="modal_ss_name">Name</label>
                            <input type="text" id="modal_ss_name" name="site_supervisor_name" value="<?php echo htmlspecialchars($placement['site_supervisor_name']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_ss_designation">Designation</label>
                            <input type="text" id="modal_ss_designation" name="site_supervisor_designation" value="<?php echo htmlspecialchars($placement['site_supervisor_designation']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_ss_phone">Cell No</label>
                            <input type="text" id="modal_ss_phone" name="site_supervisor_phone" value="<?php echo htmlspecialchars($placement['site_supervisor_phone']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_ss_email">Email</label>
                            <input type="email" id="modal_ss_email" name="site_supervisor_email" value="<?php echo htmlspecialchars($placement['site_supervisor_email']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                    </div>
                </div>

                <div class="org-info-card" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; background: #ffffff; margin-bottom: 20px;">
                    <h4 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 15px; border-bottom: 1.5px solid #cbd5e1; padding-bottom: 8px;">
                        <i class="fa-solid fa-briefcase" style="color: #6366f1; margin-right: 5px;"></i> Placed Internship & Project
                    </h4>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label for="modal_internship_title">Project Title</label>
                            <input type="text" id="modal_internship_title" name="internship_title" value="<?php echo htmlspecialchars($placement['internship_title']); ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group">
                            <label for="modal_duration_weeks">Duration (Weeks)</label>
                            <input type="number" id="modal_duration_weeks" name="duration_weeks" value="<?php echo (int)$placement['duration_weeks']; ?>" required class="info-input-field" style="background-color: #fff; border: 1px solid #cbd5e1;">
                        </div>
                    </div>
                </div>

                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-cancel" onclick="closeModal('placementModal')" style="margin-top: 0;">Cancel</button>
                    <button type="submit" name="save_placement_details" class="btn-submit" style="margin-top: 0; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">Save Placement Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
};
</script>