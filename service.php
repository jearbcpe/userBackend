<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
//header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
date_default_timezone_set("Asia/Bangkok");

require 'connect_db.php';
require '../vendor/autoload.php';

$app = new Slim\App();

$app->get('/divisions', function ($request, $response, $args) use ($app) {

	$conn = conndb();
	$sql = "select * from divisions divn where divn.flag = '1' ";
	$query = $conn->query($sql);
	
	$arrResult = array();
	while($item = $query->fetch_array())
	{
		array_push($arrResult,array(
			'divnId' => $item['divnId'],
			'divnName' => $item['divnName'],
			'divnShortName' => $item['divnShortName']
        	));
	}
	echo json_encode($arrResult);
});	
$app->post('/searchUser', function ($request, $response, $args) use ($app) {
	
	$cond = "";
	$json = $request->getBody();
	$data = json_decode($json, true); 
	
	if(trim($data['name']) != "")
		$cond .= " and u.fullName like '%".$data['name']."%'";
	if($data['divn'] != "0")
		$cond .= " and divn.divnId = '".$data['divn']."'";
	if($data['flag'] != "0")
	{
		if($data['flag']=="1")
			$cond .= " and u.flag='1'";
		if($data['flag']=="2")
			$cond .= " and u.flag='0'";
	}
	$conn = conndb();
	$sql = "select u.userId,u.username,u.fullName,u.position,divn.divnName,u.flag from users u join divisions divn on u.divnId=divn.divnId where 1 ".$cond;
	$query = $conn->query($sql);
	
	$arrResult = array();
	
	while($item = $query->fetch_array())
	{
		array_push($arrResult,array(
			'userId' => $item['userId'],
			'username' => $item['username'],
			'fullName' => $item['fullName'],
			'position' => $item['position'],
			'divnName' => $item['divnName'],
			'flag' => $item['flag'],
        	));
	}
	echo json_encode($arrResult);
});	

$app->post('/getUserDetail', function ($request, $response, $args) use ($app) {

	$json = $request->getBody();
	$data = json_decode($json, true); 
	$conn = conndb();
	$sql = "select u.userId,u.username,u.fullName,u.position,divn.divnId,divn.divnName,u.flag from users u join divisions divn on u.divnId=divn.divnId where u.userId=".$data['userId']." LIMIT 1 ";
	$query = $conn->query($sql);
 	$row = $query->fetch_assoc();


	$arrResult = array();
	$arrResult = array('userId' => $row['userId'],'username' => $row['username'],'fullName' => $row['fullName'],'position' => $row['position'],'divnId'=>$row['divnId'],'divnName' => $row['divnName'],'flag' => $row['flag']);
	echo json_encode($arrResult);
});	

$app->post('/newUser', function ($request, $response, $args) use ($app) {

	$json = $request->getBody();
	$data = json_decode($json, true); 
	$conn = conndb();
	$sql = "insert into users(fullName,position,divnId,flag,username,password) ";
	$sql .= "values('".$data['fullName']."','".$data['position']."',".$data['divn'].",'".$data['status']."','".$data['username']."',MD5('".$data['password']."'))";
	if($conn->query($sql))
		echo json_encode(array('status' => 'success'));
	else
		echo json_encode(array('status' => 'fail'));

});	

$app->post('/editUser', function ($request, $response, $args) use ($app) {

	$json = $request->getBody();
	$data = json_decode($json, true); 
	$conn = conndb();

	$sql = "update users set fullName='".$data['fullName']."',position='".$data['position']."',divnId=".$data['divn'].",flag='".$data['status']."'";

	if(trim($data['password']) != "")
		$sql .= ",password=MD5('".$data['password']."') ";

	$sql .= " where userId=".$data['userId'];
	
	if($conn->query($sql))
		echo json_encode(array('status' => 'success'));
	else
		echo json_encode(array('status' => 'fail'));

});	

$app->post('/verifyUser', function ($request, $response, $args) use ($app) {

	$json = $request->getBody();
	$data = json_decode($json, true); 
	$username = $data['username'];
	$password = $data['password'];

	$userFullName = "";
	$conn = conndb();
	$sql = "select u.userId,u.username,u.fullName ";
	$sql .= "from users u ";
	$sql .= "where u.username = '".$username."' and u.password = MD5('".$password."') and u.flag='1'";
		
	$query = $conn->query($sql);

	if($row = $query->fetch_assoc())
	{
		$tokenStr = md5($row['username'].date('Y-m-d H:i:s'));
		$userId = $row['userId'];
		$fullName = $row['fullName'];
		$conn = conndb();
		$sql = "insert into userToken(userId,tokenStr,createDT,lastActive) ";
		$sql .= "values(".$userId.",'".$tokenStr."','".date('Y-m-d H:i:s')."','".date('Y-m-d H:i:s')."')";

		if($conn->query($sql))
		{
			$sql = "update userToken set flag='0' where userId=".$userId." and tokenStr != '".$tokenStr."' ";
			$conn->query($sql);

			echo json_encode(array('status' => 'success','token' =>$tokenStr ,'fullName' =>  $fullName));
		}
		else
			echo json_encode(array('status' => 'fail','token' =>''));
	}
	else
		echo json_encode(array('status' => 'fail','token' =>''));

});	

$app->post('/verifyToken', function ($request, $response, $args) use ($app) {

	$json = $request->getBody();
	$data = json_decode($json, true); 
	$token = $data['token'];
	$diffTime = "";
	$pass = false;

	$conn = conndb();
	$sql = "select ROUND(time_to_sec((TIMEDIFF(NOW(), lastActive))) / 60) as diffTime from userToken where tokenStr = '".$token."' and flag = '1'";
		
	$query = $conn->query($sql);

	if($row = $query->fetch_assoc())
		$diffTime = $row['diffTime'];

	if($diffTime != "")
	{
		if(intval($diffTime) < 30 )
		{		
			$pass = true;
			$sql = "update userToken set lastActive='".date('Y-m-d H:i:s')."' where tokenStr = '".$token."' ";
			$conn->query($sql);
		}		
	}

	if($pass) 		
		echo json_encode(array('status' => 'success'));
	else
		echo json_encode(array('status' => 'expired'));		
});	

$app->post('/logout', function ($request, $response, $args) use ($app) {

	$json = $request->getBody();
	$data = json_decode($json, true); 

	$conn = conndb();
	$sql = "update userToken set flag='0' where tokenStr = '".$data['token']."' ";

	if($conn->query($sql))
		echo json_encode(array('status' => 'success'));
	else
		echo json_encode(array('status' => 'fail'));
		

});	

$app->run();

?>