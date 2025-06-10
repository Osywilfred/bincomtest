<?php
require("dbcon.php");
$query = "SELECT * FROM `polling_unit`";

$result = $con->query($query);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=
    , initial-scale=1.0">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <title>Document</title>
    <style>
        a{
            text-decoration: none;
            color: white;
        }
    </style>
</head>
<body>
    <h1>POLLING UNITS</h1>
    <button type= "button" class= "btn btn-primary"><a href="add_results.php">Add new results</a></button>
    <button type= "button" class= "btn btn-primary"><a href="index.php">Sum of polling units result in a LGA</a></button>
    


 
        <table class="table table-striped">
        <tr>
            <th>Unique ID</th>
            <th>Polling unit ID</th>
            <th>Ward ID</th>
            <th>L.G.A ID</th>
            <th>Unique Ward ID</th>
            <th>Pooling Unit Number</th>
            <th>Pooling unit name</th>
            <th>Polling unit description</th>
            <th>Latitude</th>
            <th>Longitude</th>
            
        </tr>
        <?php
           if($result->num_rows > 0){
            while($row = $result->fetch_assoc()){
                $unique_id =  $row['uniqueid'];
                $polling_unit_id = $row['polling_unit_id'];
                $ward_id = $row['ward_id'];
                $lga_id = $row['lga_id'];
                $unique_ward_id = $row['uniquewardid'];
                $polling_unit_number = $row['polling_unit_number'];
                $polling_unit_name= $row['polling_unit_name'];
                $polling_unit_description = $row['polling_unit_description'];
                $latitude = $row['lat'];
                $longitude = $row['long'];
        
        ?>
        <tr>
            <?php               

                        echo "<td>$unique_id</td>";                        
                        echo "<td>$polling_unit_id</td>";
                        echo "<td>$ward_id</td>";
                        echo "<td>$lga_id</td>";
                        echo "<td>$unique_ward_id</td>";
                        echo "<td>$polling_unit_number</td>";
                        echo "<td>$polling_unit_name</td>";
                        echo "<td>$polling_unit_description</td>";
                        echo "<td>$latitude</td>";
                        echo "<td>$longitude</td>";
                        
                    }
                }

            ?>
        
        </tr>
    </table>
        </div>
  

</body>
</html>