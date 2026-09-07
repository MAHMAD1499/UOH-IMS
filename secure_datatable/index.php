<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Organization Directory - IMS</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1><i class="fa-solid fa-shield-halved text-success"></i> Secure Organizations Directory</h1>
            <p>PDO Parametric Search Engine with Server-Side processing and dynamic input sanitization</p>
        </div>
        <div class="security-badge">
            <i class="fa-solid fa-lock"></i> Protected by SQLi & XSS Shields
        </div>
    </div>

    <!-- Defined HTML Table Structure -->
    <table id="secureOrgTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Organization Name</th>
                <th>Placement Address</th>
                <th>Category</th>
                <th>Contact Representative</th>
                <th>Official Email</th>
            </tr>
        </thead>
        <tbody>
            <!-- Loaded dynamically via DataTables AJAX -->
        </tbody>
    </table>
</div>

<!-- Load jQuery and DataTables dependencies -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- Initialize server-side DataTable -->
<script src="app.js"></script>
</body>
</html>
