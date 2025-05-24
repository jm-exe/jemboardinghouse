<?php
session_start();
require 'db.php';

function clean($x) {
    return trim(htmlspecialchars($x));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $invoice_number = clean($_POST['invoice_number']);
    $tenant_id      = intval($_POST['tenant_id']);
    $room_no        = clean($_POST['room_no']);
    $bed_no         = clean($_POST['bed_no']);
    $room_rate      = floatval($_POST['room_rate']);
    $electricity_bill = floatval($_POST['electricity_bill']);
    $water_bill     = floatval($_POST['water_bill']);
    $total_due      = floatval($_POST['total_due']);
    $year           = intval($_POST['year']);
    $month          = clean($_POST['month']);
    $remarks        = clean($_POST['remarks']);
    $status         = clean($_POST['status']);

    // Create table if not exists (for demo, remove in production)
    $conn->query("CREATE TABLE IF NOT EXISTS invoices (
        invoice_id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(20) UNIQUE,
        tenant_id INT,
        room_no VARCHAR(10),
        bed_no VARCHAR(10),
        room_rate DECIMAL(10,2),
        electricity_bill DECIMAL(10,2),
        water_bill DECIMAL(10,2),
        total_due DECIMAL(10,2),
        year YEAR,
        month VARCHAR(10),
        remarks TEXT,
        status ENUM('Paid', 'Unpaid') DEFAULT 'Unpaid',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert
    $sql = "INSERT INTO invoices
        (invoice_number, tenant_id, room_no, bed_no, room_rate, electricity_bill, water_bill, total_due, year, month, remarks, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sissddddisss",
        $invoice_number, $tenant_id, $room_no, $bed_no, $room_rate,
        $electricity_bill, $water_bill, $total_due, $year, $month, $remarks, $status
    );
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Invoice created successfully!";
    } else {
        $_SESSION['msg'] = "Failed to create invoice: " . $stmt->error;
    }
    header("Location: invoice.php");
    exit;
}
