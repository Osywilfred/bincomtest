<?php
session_start(); 

require('dbcon.php');

// --- Initialize variables ---
$message = ''; // For user feedback messages
$new_polling_unit_uniqueid = $_SESSION['current_pu_uniqueid'] ?? null; // Get ID from session
$new_polling_unit_name = $_SESSION['current_pu_name'] ?? '';
$current_lga_id_for_form = ''; // To repopulate LGA dropdown on error

// --- Handle clearing session for a new entry ---
if (isset($_GET['clear_session']) && $_GET['clear_session'] == 'true') {
    unset($_SESSION['current_pu_uniqueid']);
    unset($_SESSION['current_pu_name']);
    $new_polling_unit_uniqueid = null;
    $new_polling_unit_name = '';
    $message = "<p style='color: blue;'>Ready to create a new polling unit!</p>";
}

// --- Function to generate a simple unique ID (for demonstration) ---
// In a real system, you might use UUIDs, database auto-increment on uniqueid,
// or a more robust unique ID generation strategy.
function generateSimpleUniqueId() {
    return 'PU_' . uniqid() . '_' . rand(1000, 9999);
}

// --- Get user IP address ---
function getUserIpAddress() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}
$user_ip_address = getUserIpAddress();
$entered_by_user = "AdminUser"; // Replace with actual logged-in user's name/ID if you have a user system

// --- STEP 1: Handle Polling Unit Creation ---
if (isset($_POST['action']) && $_POST['action'] === 'create_polling_unit') {
    $pu_name = trim($_POST['polling_unit_name']);
    $pu_description = trim($_POST['polling_unit_description'] ?? ''); // Optional field
    $lga_id = (int)$_POST['lga_id'];
    $ward_id = (int)$_POST['ward_id']; // New: Get ward_id
    $pu_number = trim($_POST['polling_unit_number'] ?? ''); // Optional field
    $uniquewardid = trim($_POST['uniquewardid'] ?? ''); // Optional field

    // Basic validation
    if (empty($pu_name) || empty($lga_id) || empty($ward_id)) {
        $message = "<p style='color: red;'>Polling Unit Name, LGA, and Ward are required.</p>";
        $current_lga_id_for_form = $lga_id; // Keep selected LGA
    } else {
        // Generate a new uniqueid for the polling unit
        $generated_uniqueid = generateSimpleUniqueId();
        // Generate a simple sequential polling_unit_id (you might have a different scheme)
        // For demonstration, let's find the max and increment, or use a simple random for new units.
        // A real system would likely use AUTO_INCREMENT for polling_unit_id or a specific numbering system.
        $stmt_max_pu_id = $con->query("SELECT MAX(polling_unit_id) FROM polling_unit");
        $next_pu_id = $stmt_max_pu_id->fetch_row()[0] + 1;
        if ($next_pu_id === null) $next_pu_id = 1; // Start from 1 if no existing units

        // Check for duplicate polling_unit_name within the same ward/LGA before inserting
        $check_sql = "SELECT COUNT(*) FROM polling_unit WHERE polling_unit_name = ? AND ward_id = ? AND lga_id = ?";
        $stmt_check = $con->prepare($check_sql);
        $stmt_check->bind_param("sii", $pu_name, $ward_id, $lga_id);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            $message = "<p style='color: orange;'>A polling unit with this name already exists in the selected Ward and LGA.</p>";
            $current_lga_id_for_form = $lga_id; // Keep selected LGA
        } else {
            // Insert the new polling unit
            $insert_sql = "INSERT INTO `polling_unit` (`uniqueid`, `polling_unit_id`, `ward_id`, `lga_id`, `uniquewardid`, `polling_unit_number`, `polling_unit_name`, `polling_unit_description`, `entered_by_user`, `date_entered`, `user_ip_address`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            $stmt = $con->prepare($insert_sql);
            // 'sissssssss' for (uniqueid, pu_id, ward_id, lga_id, uniquewardid, pu_number, pu_name, pu_description, entered_by_user, user_ip_address)
            $stmt->bind_param("siiissssss",
                $generated_uniqueid,
                $next_pu_id,
                $ward_id,
                $lga_id,
                $uniquewardid,
                $pu_number,
                $pu_name,
                $pu_description,
                $entered_by_user,
                $user_ip_address
            );

            if ($stmt->execute()) {
                $new_polling_unit_uniqueid = $generated_uniqueid;
                $new_polling_unit_name = $pu_name;
                $_SESSION['current_pu_uniqueid'] = $new_polling_unit_uniqueid; // Store in session
                $_SESSION['current_pu_name'] = $new_polling_unit_name;
                $message = "<p style='color: green;'>Polling Unit '" . htmlspecialchars($pu_name) . "' created successfully. Now enter results.</p>";
            } else {
                $message = "<p style='color: red;'>Error creating polling unit: " . $stmt->error . "</p>";
                $current_lga_id_for_form = $lga_id; // Keep selected LGA
            }
            $stmt->close();
        }
    }
}

// --- STEP 2: Handle Party Score Submission ---
if (isset($_POST['action']) && $_POST['action'] === 'save_results' && $new_polling_unit_uniqueid !== null) {
    // Check if results already exist for this polling unit's uniqueid
    $check_sql = "SELECT COUNT(*) FROM announced_pu_results WHERE polling_unit_uniqueid = ?";
    $stmt_check = $con->prepare($check_sql);
    $stmt_check->bind_param("s", $new_polling_unit_uniqueid);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        $message = "<p style='color: orange;'>Results for this Polling Unit already exist. Navigate to an 'Edit Results' page if you want to modify them.</p>";
    } else {
        $con->begin_transaction(); // Start transaction

        try {
            $insert_success = true;
            // Loop through submitted party scores
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'party_score_') === 0) {
                    $partyid = (int)str_replace('party_score_', '', $key); // Extract party ID

                    // Get party abbreviation for insertion into announced_pu_results
                    $stmt_party_abbr = $con->prepare("SELECT partyname FROM party WHERE partyid = ?");
                    $stmt_party_abbr->bind_param("i", $partyid);
                    $stmt_party_abbr->execute();
                    $stmt_party_abbr->bind_result($partyname);
                    $stmt_party_abbr->fetch();
                    $stmt_party_abbr->close();

                    $score = (int)$value;

                    if ($score < 0) $score = 0; // Ensure non-negative

                    // Insert into announced_pu_results
                    $sql_insert_result = "INSERT INTO `announced_pu_results` (`polling_unit_uniqueid`, `party_abbreviation`, `party_score`, `entered_by_user`, `date_entered`, `user_ip_address`) VALUES (?, ?, ?, ?, NOW(), ?)";
                    $stmt_insert = $con->prepare($sql_insert_result);
                    // 'ssisss' for (uniqueid, abbr, score, user, ip)
                    $stmt_insert->bind_param("ssiss",
                        $new_polling_unit_uniqueid,
                        $partyname,
                        $score,
                        $entered_by_user,
                        $user_ip_address
                    );

                    if (!$stmt_insert->execute()) {
                        $insert_success = false;
                        error_log("Error inserting result for party " . htmlspecialchars($partyname) . " at polling unit " . htmlspecialchars($new_polling_unit_uniqueid) . ": " . $stmt_insert->error);
                        break; // Stop on first error
                    }
                    $stmt_insert->close();
                }
            }

            if ($insert_success) {
                $con->commit();
                $message = "<p style='color: green;'>All results for '" . htmlspecialchars($new_polling_unit_name) . "' saved successfully!</p>";
                // Clear session data so the next visit starts fresh for a new PU
                unset($_SESSION['current_pu_uniqueid']);
                unset($_SESSION['current_pu_name']);
                $new_polling_unit_uniqueid = null; // Reset for display
                $new_polling_unit_name = '';       // Reset for display
            } else {
                $con->rollback();
                $message = "<p style='color: red;'>An error occurred while saving results. Please try again.</p>";
            }
        } catch (Exception $e) {
            $con->rollback();
            $message = "<p style='color: red;'>Database transaction error: " . $e->getMessage() . "</p>";
            error_log("Transaction error: " . $e->getMessage());
        }
    }
}

// --- Fetch LGAs (for new polling unit creation form) ---
$lga_options = [];
$sql_lga = "SELECT lga_id, lga_name FROM lga ORDER BY lga_name ASC";
$result_lga = $con->query($sql_lga);
if ($result_lga->num_rows > 0) {
    while ($row_lga = $result_lga->fetch_assoc()) {
        $lga_options[] = $row_lga;
    }
}

// --- Fetch Wards (conditionally, based on selected LGA if there's an error) ---
$ward_options = [];
if (!empty($current_lga_id_for_form)) {
    $sql_wards = "SELECT ward_id, ward_name FROM ward WHERE lga_id = ? ORDER BY ward_name ASC";
    $stmt_wards = $con->prepare($sql_wards);
    $stmt_wards->bind_param("i", $current_lga_id_for_form);
    $stmt_wards->execute();
    $result_wards = $stmt_wards->get_result();
    if ($result_wards->num_rows > 0) {
        while ($row_ward = $result_wards->fetch_assoc()) {
            $ward_options[] = $row_ward;
        }
    }
    $stmt_wards->close();
}


// --- Fetch Parties (for score input fields) ---
$parties = [];
$sql_parties = "SELECT partyid, partyname FROM party ORDER BY partyname ASC";
$result_parties = $con->query($sql_parties);
if ($result_parties->num_rows > 0) {
    while ($row_party = $result_parties->fetch_assoc()) {
        $parties[] = $row_party;
    }
}

$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Polling Unit & Add Results</title>
    <link rel="stylesheet" href="css/add_results.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        a{
            text-decoration: none;
            color: white;
        }
    </style>
</head>
<body>
    <

    <div class="container">
        <h1>Create New Polling Unit & Enter Results</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, 'green') !== false ? 'success' : (strpos($message, 'red') !== false ? 'error' : (strpos($message, 'blue') !== false ? 'info' : 'warning')); ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($new_polling_unit_uniqueid === null): ?>
            <h2>1. Create New Polling Unit Details</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_polling_unit">

                <div class="two-column-form">
                    <div>
                        <label for="lga_id">Select LGA:</label>
                        <select id="lga_id" name="lga_id" required onchange="this.form.submit()">
                            <option value="">-- Select LGA --</option>
                            <?php foreach ($lga_options as $lga): ?>
                                <option value="<?php echo htmlspecialchars($lga['lga_id']); ?>"
                                    <?php echo ($lga['lga_id'] == ($current_lga_id_for_form ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lga['lga_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="ward_id">Select Ward:</label>
                        <select id="ward_id" name="ward_id" required>
                            <option value="">-- Select Ward --</option>
                            <?php if (!empty($ward_options)): ?>
                                <?php foreach ($ward_options as $ward): ?>
                                    <option value="<?php echo htmlspecialchars($ward['ward_id']); ?>"
                                        <?php echo (isset($_POST['ward_id']) && $_POST['ward_id'] == $ward['ward_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ward['ward_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No wards for selected LGA</option>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($ward_options) && !empty($current_lga_id_for_form)): ?>
                            <p style="color:red; font-size:0.9em;">Please select an LGA first to load wards, or add wards to the selected LGA.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <label for="polling_unit_name">Polling Unit Name:</label>
                <input type="text" id="polling_unit_name" name="polling_unit_name" required
                       value="<?php echo isset($_POST['polling_unit_name']) ? htmlspecialchars($_POST['polling_unit_name']) : ''; ?>">

                <label for="polling_unit_number">Polling Unit Number (Optional):</label>
                <input type="text" id="polling_unit_number" name="polling_unit_number"
                       value="<?php echo isset($_POST['polling_unit_number']) ? htmlspecialchars($_POST['polling_unit_number']) : ''; ?>">

                <label for="uniquewardid">Unique Ward ID (Optional):</label>
                <input type="text" id="uniquewardid" name="uniquewardid"
                       value="<?php echo isset($_POST['uniquewardid']) ? htmlspecialchars($_POST['uniquewardid']) : ''; ?>">

                <label for="polling_unit_description">Description/Address (Optional):</label>
                <textarea id="polling_unit_description" name="polling_unit_description"><?php echo isset($_POST['polling_unit_description']) ? htmlspecialchars($_POST['polling_unit_description']) : ''; ?></textarea>

                <input type="submit" value="Create Polling Unit & Proceed">
            </form>

        <?php else: ?>
            <hr>
            <h2>2. Enter Results for: <span style="color: #28a745;"><?php echo htmlspecialchars($new_polling_unit_name); ?></span></h2>

            <form method="POST" action="">
                <input type="hidden" name="action" value="save_results">
                <input type="hidden" name="polling_unit_uniqueid_hidden" value="<?php echo htmlspecialchars($new_polling_unit_uniqueid); ?>">

                <?php if (!empty($parties)): ?>
                    <?php foreach ($parties as $party): ?>
                        <div class="party-score-row">
                            <label for="party_score_<?php echo htmlspecialchars($party['partyid']); ?>">
                                <?php echo htmlspecialchars($party['partyname']); ?> Score:
                            </label>
                            <input type="number"
                                   id="party_score_<?php echo htmlspecialchars($party['partyid']); ?>"
                                   name="party_score_<?php echo htmlspecialchars($party['partyid']); ?>"
                                   min="0" value="0" required>
                        </div>
                    <?php endforeach; ?>
                    <input type="submit" value="Save All Party Scores">
                <?php else: ?>
                    <p class="message warning">No parties found in the database. Please add parties first.</p>
                <?php endif; ?>
            </form>
            <p style="text-align: center; margin-top: 30px;">
                <a href="?clear_session=true" style="color: #007bff; text-decoration: none;">Start New Polling Unit Entry</a>
            </p>
        <?php endif; ?>

    </div>
    
   
    <button type= "button" class= "btn btn-primary"><a href="polling_units.php">List of polling units</a></button>
    <button type= "button" class= "btn btn-primary"><a href="index.php">Sum of polling units result in a LGA</a></button>
    

</body>
</html>