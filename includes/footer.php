</div> <!-- End of content-area -->
    </div> <!-- End of main-wrapper -->

    <script>
        function switchTab(tabId, element) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => item.classList.remove('active'));

            const targetTab = document.getElementById(tabId);
            if (targetTab) {
                targetTab.classList.add('active');
                localStorage.setItem('activeTab', tabId);
            }
            if (element) {
                element.classList.add('active');
                // Save the index or identifier of the clicked element
                const navIndex = Array.from(navItems).indexOf(element);
                localStorage.setItem('activeNavIndex', navIndex);
            }
        }

        function switchToProfileTab() {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => item.classList.remove('active'));

            const studentDashboard = document.getElementById('student-dashboard');
            if (studentDashboard) {
                studentDashboard.classList.add('active');
                localStorage.setItem('activeTab', 'student-dashboard');
                localStorage.setItem('activeNavIndex', -1);
            }
        }

        // Restore active tab and nav items on load
        document.addEventListener("DOMContentLoaded", function() {
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
                if (navItems.length > 0) {
                    navItems.forEach(item => item.classList.remove('active'));
                    const index = parseInt(savedNavIndex);
                    if (index >= 0 && navItems[index]) {
                        navItems[index].classList.add('active');
                    }
                }
            }
        });

        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('collapsed');
            });
        }
    </script>
</body>
</html>