<?php
// Talis API tool created by Michael Whitton (mw2@soton.ac.uk) based on the 'LCN Updater' 

print("</br><a href='edition_updater.html'>Back to Edition Updater tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";

//*****************GRAB_INPUT_DATA**********

$uploaddir = '../uploads/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

echo '<pre>';
if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
	echo "File is valid, and was successfully uploaded.\n";
} else {
	echo "File is invalid, and failed to upload - Please try again. -\n";
}
echo "</br>";
print_r($uploadfile);
echo "</br>";
echo "</br>";

/**
 * Get the user config file. This script will fail disgracefully if it has not been created and nothing will happen.
 */
require('../../user.config.php');

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

echo "Client ID set: " . $clientID;
echo "</br>";

echo "User GUID to use: " . $TalisGUID;
echo "</br>";


//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/edition_updater_output.log", "a") or die("Unable to open edition_updater_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "Item ID" . "\t" . "Old LCN" . "\t" . "New LCN" . "\t" . "Resource ID" . "\t" . "Update Status?" . "\r\n");


function getToken($clientID, $secret) {
	$tokenURL = 'https://users.talis.com/oauth/tokens';
	$content = "grant_type=client_credentials";

	//*********GET DATE**********************

	$date = date('Y-m-d\TH:i:s');
	// $date1 = "2015-12-21T15:44:36";

	//************GET_TOKEN***************

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $tokenURL);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_USERPWD, "$clientID:$secret");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

	$return = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if ($info !== 200){
		echo "<p>ERROR: There was an error getting a token:</p><pre>" . var_export($return, true) . "</pre>";
	} else {
		echo "Got Token</br>";
	}

	curl_close($ch);

	$jsontoken = json_decode($return);

	if (!empty($jsontoken->access_token)){
		$token = $jsontoken->access_token;
	} else {
		echo "<p>ERROR: Unable to get an access token</p>";
		exit;
	}

	return $token;
}


function updateResource($shortCode, $resource_id, $TalisGUID, $token, $new_lcn, $new_isbn10, $new_isbn13, $myfile) {
	$url = 'https://rl.talis.com/3/' . $shortCode . '/resources/' . $resource_id;

// Update the resource with the new lcn and new 10/13 digit isbns. Delete any dates and edition fields. 
	$body = '{
			"data": {
				"type": "resources",
				"id": "' . $resource_id . '",
				"attributes": {
					"lcn": ' . $new_lcn . ',
					"isbn10s": [' . $new_isbn10 . '],
					"isbn13s": [' . $new_isbn13 . '],
					"edition": null,
					"dates": []
				}
			}
			}';
	//echo $body;
	// var_export($body);
	
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	
	));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	// echo $info;
	$output_json = json_decode($output);
	curl_close($ch);
	if ($info !== 200){
		echo "<p>ERROR: There was an error updating the Edition:</p><pre>" . var_export($output, true) . "</pre>";
		fwrite($myfile, "ERROR: There was an error updating the Edition" ."\t\r\n");
	} else {
		echo " - LCN Updated Successfully to $new_lcn</br>";
		fwrite($myfile, "Edition Updated Successfully" ."\t\r\n");
	}

}

function getResource($shortCode, $item_id, $TalisGUID, $token) {
	$url = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/' . $item_id ;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	
	));
	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	// echo $info;
	$output_json = json_decode($output);
	curl_close($ch);
	
	if ($info !== 200){
		echo "<p>ERROR: There was an error getting the resource:</p><pre>" . var_export($output, true) . "</pre>";
	} else {
		echo "Resource details acquired </br>";
	}

	$resource_id = $output_json->data->relationships->resource->data->id;
	return $resource_id;
}

function getitem($shortCode, $resource_id, $TalisGUID, $token) {
	$url = 'https://rl.talis.com/3/' . $shortCode . '/resources/' . $resource_id ;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	
	));
	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	echo $info;
	$output_json = json_decode($output);
	curl_close($ch);
	
	if ($info !== 200){
		echo "<p>ERROR: There was an error getting the resource:</p><pre>" . var_export($output, true) . "</pre>";
	} else {
		echo "Resource details acquired </br>";
	}
    echo $output;
	return $resource_id;
}

$token = getToken($clientID, $secret);

//***********Running Code******************
$file_handle = fopen($uploadfile, "rb");

while (!feof($file_handle) )  {

	$line_of_text = fgets($file_handle);
	$parts = explode(",", $line_of_text);

// The Talis item id is the first csv field
		$item_id = trim($parts[0]);
// The Old LCN is the second csv field
		$old_lcn = trim($parts[1]);
// The New LCN is the third csv field - set to null if this is blank
		if (!empty(trim($parts[2]))) {
			$new_lcn = '"' . trim($parts[2]) . '"';
		} else {
			$new_lcn = "null";
			echo "no new LCN found. Removing $old_lcn from $item_id.</br>";
		}
// The New ISBN-10 is the fourth csv field - set to an empty string if this is blank		
		if (!empty(trim($parts[3]))) {
			$new_isbn10 = '"' . trim($parts[3]) . '"';
		} else {
			$new_isbn10 = "";
		}
// The New ISBN-13 is the fifth csv field - set to an empty string if this is blank		
		if (!empty(trim($parts[4]))) {
			$new_isbn13 = '"' . trim($parts[4]) . '"';
		} else {
			$new_isbn13 = "";
		}
		
	//echo "this is the item_id: $item_id";
	fwrite($myfile, $item_id ."\t");
	//echo "</br>";
	//echo "this is the old_lcn: $old_lcn";
	fwrite($myfile, $old_lcn ."\t");
	//echo "</br>";
	//echo "this is the new_lcn: $new_lcn";
	fwrite($myfile, $new_lcn ."\t");
	//echo "this is the new isbns: $new_isbn10 $new_isbn13";
	fwrite($myfile, $new_isbn10 . $new_isbn13 ."\t");
	//echo "</br>";

	$resource_id = getResource($shortCode, $item_id, $TalisGUID, $token);
	//getitem($shortCode, $resource_id, $TalisGUID, $token);
	echo "Resource: $resource_id";
	fwrite($myfile, $resource_id ."\t");
	updateResource($shortCode, $resource_id, $TalisGUID, $token, $new_lcn, $new_isbn10, $new_isbn13, $myfile);
	echo "----------- </br>";
}


fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

?>