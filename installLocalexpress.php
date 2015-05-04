<?php
define("BOOTSTRAP", "INSTALL SCRIPT");
define("DIR_ROOT",__DIR__);
include_once("config.php");
include_once("config.local.php");
$newCarrier = "localexpress";
$desc       = "Sameday";
$tracking   = "";

function overWriteFile($file,$newCarrier,$tracking){
   $lines      = file($file);
   $hit        = false;
   $tmpLines   = array();
   foreach ($lines as $line_num => $line) {
      if($line == "{elseif \$carrier == \"".$newCarrier."\"}\n")
         $hit = true;
      if(substr($line,0,6) == '{else}' && !$hit){

         $tmpLines[] = "{elseif \$carrier == \"".$newCarrier."\"}\n";
         //$tmpLines[] = "    {\$url = \"https://temando.com/education-centre/support/track-your-item?token=`\$tracking_number`\"}";
         $tmpLines[] = "    {\$url = \"\"}\n";
         $tmpLines[] = "    {\$carrier_name = __(\"".$newCarrier."\")}\n";
      }
      $tmpLines[] = $line;
   }
   if(!$hit){
      file_put_contents($file, implode("",$tmpLines));
   }
}


$d = dir(__DIR__."/design/themes/");
while (false !== ($entry = $d->read())) {
   if($entry != "." && $entry != ".." ){
      overWriteFile(__DIR__."/design/themes/".$entry.'/mail/templates/common/carriers.tpl',$newCarrier,$tracking);
      overWriteFile(__DIR__."/design/themes/".$entry.'/templates/common/carriers.tpl',$newCarrier,$tracking);
   }
}
$d->close();
$d = dir(__DIR__."/var/themes_repository/");
while (false !== ($entry = $d->read())) {
   if($entry != "." && $entry != ".."  && $entry != "index.php" && $entry != ".htaccess"){
      overWriteFile(__DIR__."/var/themes_repository/".$entry.'/mail/templates/common/carriers.tpl',$newCarrier,$tracking);
      overWriteFile(__DIR__."/var/themes_repository/".$entry.'/templates/common/carriers.tpl',$newCarrier,$tracking);
   }
}
$d->close();
if($config['database_backend'] != 'mysqli'){
   die("This developer was lazy and only build mysqli db connector");
}
$mysqli = new mysqli($config['db_host'],$config['db_user'],$config['db_password'],$config['db_name']) or die("invalid db user or password");
$result = $mysqli->query("SELECT * FROM `".$config['table_prefix']."shipping_services` WHERE `module`='".$newCarrier."'");

if($result->num_rows == 0){
   $mysqli->query("INSERT INTO `".$config['table_prefix']."shipping_services` (`service_id`, `status`, `module`, `code`, `sp_file`) VALUES (null, 'A', '".$newCarrier."', 'S', '')");
   $result = $mysqli->query("INSERT INTO `".$config['table_prefix']."settings_descriptions (`object_id`, `object_type`, `lang_code`, `value`, `tooltip`) VALUES (null, 'O', 'EN', 'Enable ".$newCarrier."', '')");
   $mysqli->query("INSERT INTO `".$config['table_prefix']."settings_objects (`object_id`, `edition_type`, `name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`, `handler`) VALUES ('".$result->insert_id."', 'ROOT', '".$newCarrier."_enabled', '7', '0', 'C', 'N', '70', 'Y', '')";
   $mysqli->query("INSERT INTO `".$config['table_prefix']."cscart_shipping_service_descriptions (`service_id`,`description`,`lang_code`) VALUES (null,'".ucfirst($newCarrier)." ".$desc."','en')");
}
$result->close();
$mysqli->close();
?>