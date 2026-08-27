$(document).ready(function() {
    // Initialize the jQuery DataTable with server-side processing configurations
    $('#secureOrgTable').DataTable({
        "processing": true,  // Display loading indicator
        "serverSide": true,  // Enable server-side processing
        "ajax": {
            "url": "fetch_data.php",
            "type": "POST",  // Safe POST method for querying
            "error": function(xhr, error, code) {
                // Handle Ajax query failure gracefully
                console.error("AJAX Error occurred while retrieving DataTable payload.", error, code);
                alert("An error occurred loading the directory data. Please try again later.");
            }
        },
        // Columns setup mapping to JSON properties returned by fetch_data.php
        "columns": [
            { "data": "org_id", "orderable": true },
            { "data": "org_name", "orderable": true },
            { "data": "address", "orderable": true },
            { "data": "category", "orderable": true },
            { "data": "contact_person_name", "orderable": true },
            { "data": "contact_person_email", "orderable": true }
        ],
        // Default sort configuration (ID ascending)
        "order": [[0, "asc"]],
        "pageLength": 10,
        "lengthMenu": [5, 10, 25, 50]
    });
});
