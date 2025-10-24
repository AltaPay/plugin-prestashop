<?php
$p = $_POST['param'];
$sql = 'SELECT name, color, calories FROM fruit ORDER BY ' . $p;
foreach ($conn->query($sql) as $row) {
    print $row['name'] . "\t";
    print $row['color'] . "\t";
    print $row['calories'] . "\n";
}
?>
