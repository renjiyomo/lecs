<?php
include 'lecs_db.php';

if(isset($_GET['municipality'])){
    $municipality = $conn->real_escape_string($_GET['municipality']);
    $result = $conn->query("SELECT DISTINCT barangay FROM ph_addresses WHERE municipality='$municipality' ORDER BY barangay ASC");
    
    echo '<option value="">Select Barangay</option>';
    while($row = $result->fetch_assoc()){
        echo "<option value='{$row['barangay']}'>{$row['barangay']}</option>";
    }
}
