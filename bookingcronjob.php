<?php

$host = 'localhost';
$username = 'root';
//$username = 'foureflights';
$password="";
//$password="NQd5@yhJ+QrU";
$database="metrocab";
//$database="foureflights";
$mysqli = new mysqli($host,$username,$password,$database);

// Check connection
if ($mysqli -> connect_errno) {
  echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
  exit();
}else{
    $sql = "SELECT * FROM Ft_bookings";
    $result = $mysqli->query($sql);
    $id= array();
    while($row = mysqli_fetch_array($result)){
        $id[] = $row;
//       $sqlDel = "DELETE FROM `cancel_booking_requests` WHERE id=$id";
//       $Del = $mysqli->query($sqlDel);
//       if($Del){
//          echo 'Deleted Successfully';
//       }else{
//          echo 'There Was an error'; 
//       }
    }
    print_r($id); die();
    
    
}

