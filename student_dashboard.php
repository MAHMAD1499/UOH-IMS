<!-- FLASH MESSAGES -->
<?php if (!empty($flashMessage)): ?>
    <div class="card" style="margin-bottom: 20px; border-color: <?php echo $flashType === 'error' ? '#fecaca' : '#bbf7d0'; ?>;">
        <div class="card-body" style="padding: 14px 18px; color: <?php echo $flashType === 'error' ? '#991b1b' : '#166534'; ?>; background: <?php echo $flashType === 'error' ? '#fef2f2' : '#f0fdf4'; ?>;">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- TAB 1: PERSONAL (Academic & Profile Info)  -->
<!-- ========================================== -->
<div id="student-dashboard" class="tab-content active">
    <!-- Academic Details -->
    <div class="card">
        <div class="card-header">Academic Details</div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="rollno">Roll No</label>
                        <input type="text" id="rollno" name="rollno" value="<?php echo htmlspecialchars($rollno ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="session">Session</label>
                        <input type="text" id="session" name="session" value="<?php echo htmlspecialchars($semesterDetail['session'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <input type="text" id="semester" name="semester" value="<?php echo htmlspecialchars($semesterDetail['semester'] ?? ''); ?>" required>
                    </div>
                </div>
                <button type="submit" name="save_academic" class="btn-submit">Save Academic Details</button>
            </form>
        </div>
    </div>

    <!-- Personal Profile Details -->
    <div class="card">
        <div class="card-header">User Profile Details</div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="fname">Father Name</label>
                        <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($profile['fname'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cnic">CNIC</label>
                        <input type="text" id="cnic" name="cnic" value="<?php echo htmlspecialchars($profile['cnic'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cellno">Cell No</label>
                        <input type="text" id="cellno" name="cellno" value="<?php echo htmlspecialchars($profile['cell_no'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 10px;">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="2"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="save_profile" class="btn-submit">Save Profile</button>
            </form>
        </div>
    </div>
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
                                    <a href="<?php echo htmlspecialchars($latestReport['report_ref_img']); ?>" target="_blank" style="color: #2e7d5b; text-decoration: underline;">
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
    <div class="table-header-bar">
        <button class="btn-primary-action" onclick="openModal('letterModal')">
            <i class="fa-solid fa-envelope-open-text"></i> Internship Letter
        </button>
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
                        <td><span class="badge-status badge-approved">Draft Ready</span></td>
                        <td>
                            <button class="btn-table-action" onclick="openModal('letterModal')">
                                <i class="fa-solid fa-eye"></i> View Draft
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
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