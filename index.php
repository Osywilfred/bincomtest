<?php
require('dbcon.php');
$selected_lga_id = '';
$selected_polling_unit_id = '';
$total_party_score = 0;
$polling_units = []; // To store polling units for the selected LGA

// --- Step 1: Handle LGA Selection ---
if (isset($_POST['lga_id'])) {
    $selected_lga_id = $_POST['lga_id'];

    // Fetch Polling Units for the selected LGA
    if (!empty($selected_lga_id) && is_numeric($selected_lga_id)) {
        $sql_pu = "SELECT polling_unit_id, polling_unit_name FROM polling_unit WHERE lga_id = ? ORDER BY polling_unit_name ASC";
        $stmt_pu = $con->prepare($sql_pu);
        $stmt_pu->bind_param("i", $selected_lga_id);
        $stmt_pu->execute();
        $result_pu = $stmt_pu->get_result();

        if ($result_pu->num_rows > 0) {
            while ($row_pu = $result_pu->fetch_assoc()) {
                $polling_units[] = $row_pu;
            }
        }
        $stmt_pu->close();
    }
}

// --- Step 2: Handle Polling Unit Selection and Sum Scores ---
if (isset($_POST['polling_unit_id'])) {
    $selected_polling_unit_id = $_POST['polling_unit_id'];

    // Sum scores for the selected Polling Unit
    if (!empty($selected_polling_unit_id) && is_numeric($selected_polling_unit_id)) {
        $sql_score = "SELECT SUM(party_score) AS total_score FROM announced_pu_results WHERE polling_unit_uniqueid = ?";
        $stmt_score = $con->prepare($sql_score);
        $stmt_score->bind_param("i", $selected_polling_unit_id);
        $stmt_score->execute();
        $result_score = $stmt_score->get_result();
        $row_score = $result_score->fetch_assoc();

        if ($row_score && $row_score['total_score'] !== null) {
            $total_party_score = (int) $row_score['total_score'];
        }
        $stmt_score->close();
    }
}

// Fetch all LGAs for the initial dropdown
$sql_lga = "SELECT lga_id, lga_name FROM lga ORDER BY lga_name ASC";
$result_lga = $con->query($sql_lga);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results (Pure PHP)</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        a{
            text-decoration: none;
            color: white;
        }
    </style>
</head>
<body>

    <h1>Polling Unit Result Viewer</h1>
    <button type= "button" class= "btn btn-primary"><a href="add_results.php">Add new results</a></button>
    <button type= "button" class= "btn btn-primary"><a href="polling_units.php">List of polling units</a></button>
    

    <form method="POST" action="index.php">
        <label for="lga_select">Select LGA:</label>
        <select class="form-select" id="lga_select" name="lga_id" onchange="this.form.submit()">
            <option value="">-- Select LGA --</option>
            <?php
            if ($result_lga->num_rows > 0) {
                while ($row_lga = $result_lga->fetch_assoc()) {
                    $selected = ($row_lga["lga_id"] == $selected_lga_id) ? 'selected' : '';
                    echo "<option value='" . $row_lga["lga_id"] . "' " . $selected . ">" . htmlspecialchars($row_lga["lga_name"]) . "</option>";
                }
            }
            ?>
        </select>
    </form>

    <br><br>

    <?php if (!empty($selected_lga_id)): ?>
        <form method="POST" action="index.php">
            <input type="hidden" name="lga_id" value="<?php echo htmlspecialchars($selected_lga_id); ?>">

            <label for="polling_unit_select">Select Polling Unit:</label>
            <select id="polling_unit_select" name="polling_unit_id" onchange="this.form.submit()">
                <option value="">-- Select Polling Unit --</option>
                <?php
                if (!empty($polling_units)) {
                    foreach ($polling_units as $pu) {
                        $selected = ($pu["polling_unit_id"] == $selected_polling_unit_id) ? 'selected' : '';
                        echo "<option value='" . $pu["polling_unit_id"] . "' " . $selected . ">" . htmlspecialchars($pu["polling_unit_name"]) . "</option>";
                    }
                }
                ?>
            </select>
        </form>
    <?php endif; ?>

    <br><br>

    <div id="total_score_display">
        <h2>Total Party Score: <span id="total_score"><?php echo $total_party_score; ?></span></h2>
    </div>

</body>
</html>

<?php
$con->close();
?>