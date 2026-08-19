</div> <!-- End of content-area -->
    </div> <!-- End of main-wrapper -->

    <script>
        function switchTab(tabId, element) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => item.classList.remove('active'));

            document.getElementById(tabId).classList.add('active');
            element.classList.add('active');
        }

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
        });
    </script>
</body>
</html>