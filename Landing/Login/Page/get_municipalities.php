<?php
include 'lecs_db.php';

if(isset($_GET['province'])){
    $province = $conn->real_escape_string($_GET['province']);
    $result = $conn->query("SELECT DISTINCT municipality FROM ph_addresses WHERE province='$province' ORDER BY municipality ASC");
    
    echo '<option value="">Select Municipality</option>';
    while($row = $result->fetch_assoc()){
        echo "<option value='{$row['municipality']}'>{$row['municipality']}</option>";
    }
}
