<?php
function validateEmail($email)
{
   $pattern = '/^([0-9a-z]([-.\w]*[0-9a-z])*@(([0-9a-z])+([-\w]*[0-9a-z])*\.)+[a-z]{2,6})$/i';
   return preg_match($pattern, $email);
}
function addColumnIfNotExists($db, $mysql_table, $column_name, $column_type)
{
   $result = mysqli_query($db, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$mysql_table' AND COLUMN_NAME = '$column_name'");
   if ($result)
   {
      $row = mysqli_fetch_row($result);
      $count = $row[0];
      if ($count == 0) 
      {
         $sql = "ALTER TABLE $mysql_table ADD $column_name $column_type";
         mysqli_query($db, $sql);
      }
   }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['formid']) && $_POST['formid'] == 'form1')
{
   $mailto = 'yourname@yourdomain.com';
   $mailfrom = isset($_POST['email']) ? $_POST['email'] : $mailto;
   $subject = 'Website form';
   $message = 'Values submitted from website form:';
   $success_url = '';
   $error_url = '';
   $mysql_server = 'localhost';
   $mysql_database = 'transaction';
   $mysql_table = 'mytrans';
   $mysql_username = 'merchantbnk';
   $mysql_password = 'satriosoft';
   $eol = "\r\n";
   $error = '';
   $internalfields = array ("submit", "reset", "send", "filesize", "formid", "captcha", "recaptcha_challenge_field", "recaptcha_response_field", "g-recaptcha-response", "h-captcha-response");
   $boundary = md5(uniqid(time()));
   $header  = 'From: '.$mailfrom.$eol;
   $header .= 'Reply-To: '.$mailfrom.$eol;
   $header .= 'MIME-Version: 1.0'.$eol;
   $header .= 'Content-Type: multipart/mixed; boundary="'.$boundary.'"'.$eol;
   $header .= 'X-Mailer: PHP v'.phpversion().$eol;

   try
   {
      if (!validateEmail($mailfrom))
      {
         $error .= "The specified email address (" . $mailfrom . ") is invalid!\n<br>";
         throw new Exception($error);
      }
      $message .= $eol;
      $message .= "IP Address : ";
      $message .= $_SERVER['REMOTE_ADDR'];
      $message .= $eol;
      foreach ($_POST as $key => $value)
      {
         if (!in_array(strtolower($key), $internalfields))
         {
            if (is_array($value))
            {
               $message .= ucwords(str_replace("_", " ", $key)) . " : " . implode(",", $value) . $eol;
            }
            else
            {
               $message .= ucwords(str_replace("_", " ", $key)) . " : " . $value . $eol;
            }
         }
      }
      $body  = 'This is a multi-part message in MIME format.'.$eol.$eol;
      $body .= '--'.$boundary.$eol;
      $body .= 'Content-Type: text/plain; charset=UTF-8'.$eol;
      $body .= 'Content-Transfer-Encoding: 8bit'.$eol;
      $body .= $eol.stripslashes($message).$eol;
      if (!empty($_FILES))
      {
         foreach ($_FILES as $key => $value)
         {
             if ($_FILES[$key]['error'] == 0)
             {
                $body .= '--'.$boundary.$eol;
                $body .= 'Content-Type: '.$_FILES[$key]['type'].'; name='.$_FILES[$key]['name'].$eol;
                $body .= 'Content-Transfer-Encoding: base64'.$eol;
                $body .= 'Content-Disposition: attachment; filename='.$_FILES[$key]['name'].$eol;
                $body .= $eol.chunk_split(base64_encode(file_get_contents($_FILES[$key]['tmp_name']))).$eol;
             }
         }
      }
      $body .= '--'.$boundary.'--'.$eol;
      if ($mailto != '')
      {
         mail($mailto, $subject, $body, $header);
      }
      $search = array("ä", "Ä", "ö", "Ö", "ü", "Ü", "ß", "!", "§", "$", "%", "&", "/", "\x00", "^", "°", "\x1a", "-", "\"", " ", "\\", "\0", "\x0B", "\t", "\n", "\r", "(", ")", "=", "?", "`", "*", "'", ":", ";", ">", "<", "{", "}", "[", "]", "~", "²", "³", "~", "µ", "@", "|", "<", "+", "#", ".", "´", "+", ",");
      $replace = array("ae", "Ae", "oe", "Oe", "ue", "Ue", "ss");
      foreach($_POST as $name=>$value)
      {
         $name = str_replace($search, $replace, $name);
         $name = strtoupper($name);
         if (is_array($value))
         {
            $form_data[$name] = implode(",", $value);
         }
         else
         {
            $form_data[$name] = $value;
         }
      }
      $db = mysqli_connect($mysql_server, $mysql_username, $mysql_password) or die('Failed to connect to database server!<br>'.mysqli_error($db));
      mysqli_query($db, "CREATE DATABASE IF NOT EXISTS $mysql_database");
      mysqli_select_db($db, $mysql_database) or die('Failed to select database<br>'.mysqli_error($db));
      mysqli_query($db, "CREATE TABLE IF NOT EXISTS $mysql_table (ID int(9) NOT NULL auto_increment, PRIMARY KEY (id))");
      addColumnIfNotExists($db, $mysql_table, 'DATESTAMP', 'DATE');
      addColumnIfNotExists($db, $mysql_table, 'TIME', 'VARCHAR(8)');
      addColumnIfNotExists($db, $mysql_table, 'IP', 'VARCHAR(15)');
      addColumnIfNotExists($db, $mysql_table, 'BROWSER', 'VARCHAR(255)');
      foreach($form_data as $name=>$value)
      {
         addColumnIfNotExists($db, $mysql_table, $name, 'VARCHAR(255)');
      }
      $stmt = mysqli_prepare($db, "INSERT INTO $mysql_table (`DATESTAMP`, `TIME`, `IP`, `BROWSER`) VALUES (?, ?, ?, ?)");
      $datestamp = date("Y-m-d");
      $time = date("G:i:s");
      $ip = $_SERVER['REMOTE_ADDR'];
      $browser = $_SERVER['HTTP_USER_AGENT'];
      mysqli_stmt_bind_param($stmt, "ssss", $datestamp, $time, $ip, $browser);
      mysqli_stmt_execute($stmt) or die('Failed to insert data into table!<br>'.mysqli_error($db));
      $id = mysqli_insert_id($db);
      foreach($form_data as $name=>$value)
      {
         mysqli_query($db, "UPDATE $mysql_table SET $name='".mysqli_real_escape_string($db, $value)."' WHERE ID=$id") or die('Failed to update table!<br>'.mysqli_error($db));
      }
      mysqli_close($db);
      header('Location: '.$success_url);
   }
   catch (Exception $e)
   {
      $errorcode = file_get_contents($error_url);
      $replace = "##error##";
      $errorcode = str_replace($replace, $e->getMessage(), $errorcode);
      echo $errorcode;
   }
   exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Untitled Page</title>
<meta name="generator" content="WYSIWYG Web Builder 20 - https://www.wysiwygwebbuilder.com">
<link href="Untitled1.css" rel="stylesheet">
<link href="page1.css" rel="stylesheet">
</head>
<body>
<div id="wb_Form1" style="position:absolute;left:5px;top:109px;width:376px;height:287px;z-index:12;">
<form name="transaction" method="post" action="<?php echo basename(__FILE__); ?>" enctype="multipart/form-data" id="Form1">
<input type="hidden" name="formid" value="form1">
<label for="date" id="Date-label" style="position:absolute;left:10px;top:15px;width:60px;height:18px;line-height:18px;z-index:0;">Date:</label>
<input type="text" id="date" style="position:absolute;left:118px;top:15px;width:230px;height:18px;z-index:1;" name="name" value="" spellcheck="false">
<label for="amount" id="Address-label" style="position:absolute;left:10px;top:48px;width:60px;height:18px;line-height:18px;z-index:2;">Amount:</label>
<input type="text" id="amount" style="position:absolute;left:118px;top:48px;width:230px;height:18px;z-index:3;" name="address" value="" spellcheck="false">
<label for="type" id="City-label" style="position:absolute;left:10px;top:81px;width:60px;height:18px;line-height:18px;z-index:4;">Type:</label>
<input type="text" id="type" style="position:absolute;left:118px;top:81px;width:230px;height:18px;z-index:5;" name="city" value="" spellcheck="false">
<label for="description" id="State-label" style="position:absolute;left:10px;top:114px;width:90px;height:18px;line-height:18px;z-index:6;">Description:</label>
<input type="text" id="description" style="position:absolute;left:118px;top:114px;width:230px;height:18px;z-index:7;" name="state" value="" spellcheck="false">
<label for="status" id="Label5" style="position:absolute;left:10px;top:147px;width:60px;height:18px;line-height:18px;z-index:8;">Status:</label>
<input type="text" id="status" style="position:absolute;left:118px;top:147px;width:230px;height:18px;z-index:9;" name="code" value="" spellcheck="false">
<input type="submit" id="Button1" name="" value="Send" style="position:absolute;left:46px;top:244px;width:96px;height:25px;z-index:10;">
<input type="reset" id="Button2" name="" value="Reset" style="position:absolute;left:224px;top:244px;width:96px;height:25px;z-index:11;">
</form>
</div>
</body>
</html>