</div> <!-- End of content-area -->
<footer
    style="background: linear-gradient(90deg, var(--color2), var(--color3)); color: rgba(255,255,255,0.9); padding: 12px 24px; text-align: center; font-size: 13px; font-weight: 500; flex-shrink: 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); letter-spacing: 0.3px;">
    Copyright &copy; 2023 The University of Haripur. All rights reserved.
</footer>
</div> <!-- End of main-wrapper -->

<script>
    function toggleSidebarDropdown(dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;
        dropdown.classList.toggle('open');
        const toggleBtn = dropdown.previousElementSibling;
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('.dropdown-chevron');
            if (icon) {
                if (dropdown.classList.contains('open')) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }
        }
    }

    function switchTab(tabId, element) {
        const tabs = document.querySelectorAll('.tab-content');
        tabs.forEach(tab => tab.classList.remove('active'));

        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => item.classList.remove('active'));

        const navSubitems = document.querySelectorAll('.nav-subitem');
        navSubitems.forEach(item => item.classList.remove('active'));

        const targetTab = document.getElementById(tabId);
        if (targetTab) {
            targetTab.classList.add('active');
            localStorage.setItem('activeTab', tabId);
        }
        if (element) {
            element.classList.add('active');

            // If it is a subitem, ensure the parent dropdown is open and style it
            if (element.classList.contains('nav-subitem')) {
                const dropdown = element.closest('.nav-dropdown');
                if (dropdown) {
                    dropdown.classList.add('open');
                    const toggleBtn = dropdown.previousElementSibling;
                    if (toggleBtn) {
                        toggleBtn.classList.add('active');
                        const icon = toggleBtn.querySelector('.dropdown-chevron');
                        if (icon) {
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-up');
                        }
                    }
                }
            }

            // Save index of the element
            const allClickables = [...navItems, ...navSubitems];
            const navIndex = allClickables.indexOf(element);
            localStorage.setItem('activeNavIndex', navIndex);
        }
    }

    function switchToProfileTab() {
        const tabs = document.querySelectorAll('.tab-content');
        tabs.forEach(tab => tab.classList.remove('active'));

        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => item.classList.remove('active'));

        const navSubitems = document.querySelectorAll('.nav-subitem');
        navSubitems.forEach(item => item.classList.remove('active'));

        let targetTabId = null;
        let viewProfileSubitemId = null;
        let profileDropdownId = null;

        if (document.getElementById('student-dashboard')) {
            targetTabId = 'student-dashboard';
            viewProfileSubitemId = 'nav-subitem-view-profile';
            profileDropdownId = 'profile-dropdown';
        } else if (document.getElementById('focal-profile')) {
            targetTabId = 'focal-profile';
            viewProfileSubitemId = 'nav-item-focal-profile';
            profileDropdownId = 'focal-profile-dropdown';
        } else if (document.getElementById('faculty-profile')) {
            targetTabId = 'faculty-profile';
            viewProfileSubitemId = 'nav-item-faculty-profile';
            profileDropdownId = 'faculty-profile-dropdown';
        }

        if (targetTabId) {
            const targetTab = document.getElementById(targetTabId);
            if (targetTab) {
                targetTab.classList.add('active');
                localStorage.setItem('activeTab', targetTabId);
            }

            const viewProfileSubitem = document.getElementById(viewProfileSubitemId);
            if (viewProfileSubitem) {
                viewProfileSubitem.classList.add('active');
                const allClickables = [...navItems, ...navSubitems];
                localStorage.setItem('activeNavIndex', allClickables.indexOf(viewProfileSubitem));
            } else {
                localStorage.setItem('activeNavIndex', -1);
            }

            const profileDropdown = document.getElementById(profileDropdownId);
            if (profileDropdown) {
                profileDropdown.classList.add('open');
                const toggleBtn = profileDropdown.previousElementSibling;
                if (toggleBtn) {
                    toggleBtn.classList.add('active');
                    const icon = toggleBtn.querySelector('.dropdown-chevron');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                }
            }
        }
    }

    // Restore active tab and nav items on load
    document.addEventListener("DOMContentLoaded", function () {
        const savedTab = localStorage.getItem('activeTab');
        const savedNavIndex = localStorage.getItem('activeNavIndex');

        if (savedTab) {
            const targetTab = document.getElementById(savedTab);
            if (targetTab) {
                const tabs = document.querySelectorAll('.tab-content');
                tabs.forEach(tab => tab.classList.remove('active'));
                targetTab.classList.add('active');
            }
        }

        if (savedNavIndex !== null) {
            const navItems = document.querySelectorAll('.nav-item');
            const navSubitems = document.querySelectorAll('.nav-subitem');
            const allClickables = [...navItems, ...navSubitems];

            if (allClickables.length > 0) {
                navItems.forEach(item => item.classList.remove('active'));
                navSubitems.forEach(item => item.classList.remove('active'));
                const index = parseInt(savedNavIndex);
                if (index >= 0 && allClickables[index]) {
                    const element = allClickables[index];
                    element.classList.add('active');

                    // If it's a subitem, expand parent dropdown and set it active
                    if (element.classList.contains('nav-subitem')) {
                        const dropdown = element.closest('.nav-dropdown');
                        if (dropdown) {
                            dropdown.classList.add('open');
                            const toggleBtn = dropdown.previousElementSibling;
                            if (toggleBtn) {
                                toggleBtn.classList.add('active');
                                const icon = toggleBtn.querySelector('.dropdown-chevron');
                                if (icon) {
                                    icon.classList.remove('fa-chevron-down');
                                    icon.classList.add('fa-chevron-up');
                                }
                            }
                        }
                    }
                }
            }
        }
    });

    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (window.innerWidth <= 992) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 992 && sidebar.classList.contains('mobile-open')) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

        // Prevent clicks inside the sidebar from closing it
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // PDF Download function using html2pdf
    function downloadPDF(elementSelector, filename = 'Internship_Letter.pdf') {
        const element = document.querySelector(elementSelector);
        if (!element) return;
        
        // Options for html2pdf
        const opt = {
            margin:       15,
            filename:     filename,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, logging: false },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Temporarily adjust styles for better PDF rendering
        const originalBoxShadow = element.style.boxShadow;
        const originalBorder = element.style.border;
        element.style.boxShadow = 'none';
        
        // Generate PDF
        html2pdf().set(opt).from(element).save().then(() => {
            // Restore styles
            element.style.boxShadow = originalBoxShadow;
        });
    }
</script>
<!-- html2pdf.js for generating PDFs directly -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</body>

</html>