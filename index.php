<?php
include("config.php");
header('Content-type: text/xml');
if ($_GET["userid"] != "" && strlen($_GET["userid"]) < 15) {
  $userid = htmlspecialchars($_GET["userid"]);
  $templateParamNames = array('{user_id}');
  $templateParamValues = array(urlencode($userid));
  $userrecord = new DOMDocument();
  $userrecord->formatOutput = true;
  $userrecord->loadXML("<userdata/>");
  $f = $userrecord->createDocumentFragment();

  $ch = curl_init();
  $url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/users/{user_id}';
  $url = str_replace($templateParamNames, $templateParamValues, $url);
  $queryParams = '?' . urlencode('user_id_type') . '=' . urlencode('all_unique') . '&' . urlencode('view') . '=' . urlencode('full') . '&' . urlencode('expand') . '=' . urlencode('loans,requests,fees') . '&' . urlencode('apikey') . '=' . urlencode($apikey);
  curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HEADER, FALSE);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
  $alluserdata = curl_exec($ch);
  curl_close($ch);

  $alluserdatafragment = substr($alluserdata, strpos($alluserdata, '?'.'>') + 2);
  $f->appendXML($alluserdatafragment);

  $userrecord->documentElement->appendChild($f);
  $user = $userrecord->saveXML(); 
  $user = simplexml_load_string($user);
  //print_r($user);

  if ($user != "") {
    //start xml document
    $useraddressfull = $user->xpath('/userdata/user/contact_info/addresses/address[@preferred="true"]');
    $userstaddress2 = "";
    $email1 = $user->xpath('/userdata/user/contact_info/emails/email[@preferred="true"]');
    $phone1 = $user->xpath('/userdata/user/contact_info/phones/phone[@preferred="true"]');
    $dept1 = $user->xpath('/userdata/user/user_statistics/user_statistic[@segment_type="External"]');
    if (count($dept1) == 0){
      $dept1 = $user->xpath('/userdata/user/user_statistics/user_statistic');
    }
    echo '<?xml version="1.0" encoding="utf-8"?>'. "\n";
    $xml_output = "";
    $xml_output .= "\t<entry>\n";
    $xml_output .= "\t\t<fname>" . $user->user->first_name . "</fname>\n";
    $xml_output .= "\t\t<lname>" . $user->user->last_name . "</lname>\n";
    foreach ($user->user->identifiers as $id) {
      $xml_output .= "\t\t<" . $id->type . ">" . $id->value . "</" . $id->type . ">\n";
    }
    $xml_output .= "\t\t<username>" . $user->user->primary_id . "</username>\n";
    $xml_output .= "\t\t<groupid>" . $user->user->user_group . "</groupid>\n";
    $xml_output .= "\t\t<email>" . $email1[0]->email_address . "</email>\n";
    $xml_output .= "\t\t<phone>" . $phone1[0]->phone_number . "</phone>\n";

    $userstaddress1 = $useraddressfull[0]->line1;
    if (strlen($userstaddress1) > 40) {
      $userstaddressSplit = str_split($userstaddress1, strrpos(substr($userstaddress1, 0, 39), ' '));
      $userstaddress1 = $userstaddressSplit[0];
      $userstaddress2 .= $userstaddressSplit[1];
    }
    if ($useraddressfull[0]->line2 != ""){
      $userstaddress2 .= ", " . $useraddressfull[0]->line2;
    }
    $xml_output .= "\t\t<streetaddr1>" . htmlspecialchars($userstaddress1) . "</streetaddr1>\n";
    $xml_output .= "\t\t<streetaddr2>" . htmlspecialchars($userstaddress2) . "</streetaddr2>\n";
    $xml_output .= "\t\t<city>" . $useraddressfull[0]->city . "</city>\n";
    if (strlen($useraddressfull[0]->state_province) > 2){
      $stateabbr = convert_state_to_abbreviation($useraddressfull[0]->state_province);
    } else {
      $stateabbr = $useraddressfull[0]->state_province;
    }
    $xml_output .= "\t\t<state>" . $stateabbr . "</state>\n";
    $xml_output .= "\t\t<zipcode>" . $useraddressfull[0]->postal_code . "</zipcode>\n";
    $expyear = substr($user->user->expiry_date, 0, 4);
    $expmonth = substr($user->user->expiry_date, 5, 2);
    $expday = substr($user->user->expiry_date, 8, 2);
    $xml_output .= "\t\t<expdate>" . $expmonth . "/" . $expday . "/" . $expyear . "</expdate>\n";
    $modyear = substr($user->moddate, 0, 4);
    $modmonth = substr($user->moddate, 4, 2);
    $modday = substr($user->moddate, 6, 2);
    $xml_output .= "\t\t<moddate>06/01/2016</moddate>\n";
    $xml_output .= "\t\t<deptid>" . $dept1[0]->statistic_category . "</deptid>\n";
    $xml_output .= "\t\t<blocks>";
    $blockstatues = array();
    foreach ($user->user->blocks as $block) {
      $xml_output .= "\t" . $block->code . " " . $block->_type . " " . $block->status . " ";
      $xml_output .= $block->creation_date . " " . $block->modification_date . "\n";
      $blockstatues[] = $block->status;
    }
    $xml_output .= "\t\t</blocks>\n";
    if (in_array("Active", $blockstatues)){
      $blockstatus = "Active";
    } else{
      $blockstatus = "-";
    }
    $xml_output .= "\t\t<blockstatus>" . $blockstatus . "</blockstatus>\n";
    $xml_output .= "\t\t<fines>\n";
    $xml_output .= "\t\t</fines>\n";
    $xml_output .= "\t</entry>\n";

    echo $xml_output;
  } else {
    echo $user_services->lastError()->message . "\n";
  }
}

function check_zip_state_in_addrtwo($streetaddr2){
  if ($streetaddr2 != ""){

    $lookforzip = substr($streetaddr2, -5);
    $lookforstate = substr($streetaddr2, -8, 2);
    if (is_numeric($lookforzip) && preg_match('/[A-Z]/', $lookforstate) && strstr($streetaddr2, ",")){
      $zipstate_array;
    }
    $cityholder = explode(",", $streetaddr2);
    $lookforcity = $cityholder[0];
    return array($lookforcity, $lookforstate, $lookforzip);
  }
}
function convert_state_to_abbreviation($state_name) {
    switch ($state_name) {
      case "Alabama":
        return "AL";
        break;
      case "Alaska":
        return "AK";
        break;
      case "Arizona":
        return "AZ";
        break;
      case "Arkansas":
        return "AR";
        break;
      case "California":
        return "CA";
        break;
      case "Colorado":
        return "CO";
        break;
      case "Connecticut":
        return "CT";
        break;
      case "Delaware":
        return "DE";
        break;
      case "Florida":
        return "FL";
        break;
      case "Georgia":
        return "GA";
        break;
      case "Hawaii":
        return "HI";
        break;
      case "Idaho":
        return "ID";
        break;
      case "Illinois":
        return "IL";
        break;
      case "Indiana":
        return "IN";
        break;
      case "Iowa":
        return "IA";
        break;
      case "Kansas":
        return "KS";
        break;
      case "Kentucky":
        return "KY";
        break;
      case "Louisana":
        return "LA";
        break;
      case "Maine":
        return "ME";
        break;
      case "Maryland":
        return "MD";
        break;
      case "Massachusetts":
        return "MA";
        break;
      case "Michigan":
        return "MI";
        break;
      case "Minnesota":
        return "MN";
        break;
      case "Mississippi":
        return "MS";
        break;
      case "Missouri":
        return "MO";
        break;
      case "Montana":
        return "MT";
        break;
      case "Nebraska":
        return "NE";
        break;
      case "Nevada":
        return "NV";
        break;
      case "New Hampshire":
        return "NH";
        break;
      case "New Jersey":
        return "NJ";
        break;
      case "New Mexico":
        return "NM";
        break;
      case "New York":
        return "NY";
        break;
      case "North Carolina":
        return "NC";
        break;
      case "North Dakota":
        return "ND";
        break;
      case "Ohio":
        return "OH";
        break;
      case "Oklahoma":
        return "OK";
        break;
      case "Oregon":
        return "OR";
        break;
      case "Pennsylvania":
        return "PA";
        break;
      case "Rhode Island":
        return "RI";
        break;
      case "South Carolina":
        return "SC";
        break;
      case "South Dakota":
        return "SD";
        break;
      case "Tennessee":
        return "TN";
        break;
      case "Texas":
        return "TX";
        break;
      case "Utah":
        return "UT";
        break;
      case "Vermont":
        return "VT";
        break;
      case "Virginia":
        return "VA";
        break;
      case "Washington":
        return "WA";
        break;
      case "Washington D.C.":
        return "DC";
        break;
      case "West Virginia":
        return "WV";
        break;
      case "Wisconsin":
        return "WI";
        break;
      case "Wyoming":
        return "WY";
        break;
      case "Alberta":
        return "AB";
        break;
      case "British Columbia":
        return "BC";
        break;
      case "Manitoba":
        return "MB";
        break;
      case "New Brunswick":
        return "NB";
        break;
      case "Newfoundland & Labrador":
        return "NL";
        break;
      case "Northwest Territories":
        return "NT";
        break;
      case "Nova Scotia":
        return "NS";
        break;
      case "Nunavut":
        return "NU";
        break;
      case "Ontario":
        return "ON";
        break;
      case "Prince Edward Island":
        return "PE";
        break;
      case "Quebec":
        return "QC";
        break;
      case "Saskatchewan":
        return "SK";
        break;
      case "Yukon Territory":
        return "YT";
        break;
      default:
        return "";
    }
  }
?>
