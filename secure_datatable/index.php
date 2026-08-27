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
    
    <style>
        :root {
            --primary-color: #2e6652;
            --secondary-color: #26294d;
            --background-color: #f1f5f9;
            --card-background: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 1100px;
            background: var(--card-background);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            padding: 30px;
            border: 1px solid var(--border-color);
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 13.5px;
            color: var(--text-secondary);
        }

        /* Customize DataTables layout styling */
        .dataTables_wrapper {
            padding-top: 10px;
        }

        table.dataTable {
            border-collapse: collapse !important;
            width: 100% !important;
            margin: 15px 0 !important;
        }

        table.dataTable thead th {
            background-color: var(--primary-color) !important;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 13.5px;
            padding: 12px 10px !important;
            border-bottom: none !important;
        }

        table.dataTable tbody td {
            font-size: 13.5px;
            padding: 12px 10px !important;
            border-bottom: 1px solid var(--border-color) !important;
            color: #334155;
        }

        table.dataTable tbody tr:hover {
            background-color: #f8fafc !important;
        }

        /* Search input design styling */
        .dataTables_filter input {
            border: 1px solid var(--border-color) !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            outline: none !important;
            font-size: 13px !important;
            background-color: #f8fafc !important;
            transition: all 0.2s ease;
        }

        .dataTables_filter input:focus {
            border-color: var(--primary-color) !important;
            background-color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(46, 102, 82, 0.15) !important;
        }

        /* Length menu select design styling */
        .dataTables_length select {
            border: 1px solid var(--border-color) !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            outline: none !important;
            font-size: 13px !important;
        }

        /* Pagination buttons custom design */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-color) !important;
            color: #ffffff !important;
            border: 1px solid var(--primary-color) !important;
            border-radius: 4px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--secondary-color) !important;
            color: #ffffff !important;
            border: 1px solid var(--secondary-color) !important;
        }

        .security-badge {
            background-color: rgba(46, 102, 82, 0.1);
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>
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
