﻿<?php
require_once('../pma.php'); // wap phpmyadmin// ionutvmi@gmail.com
// master-land.net
include "lib/settings.php";
connect_db($db);
$check = $db->query("SHOW DATABASES LIKE '".$db->real_escape_string($_GET['db'])."'");
$check = $check->num_rows;
$db_name=trim($_GET['db']);
// if no db exit
if($db_name == '' OR $check == 0) { 
	header("Location: main.php"); exit;}
// select db
$db->select_db($db_name);
$check = $db->query("SHOW TABLE STATUS LIKE '".$db->real_escape_string($_GET['tb'])."'");
$check = $check->num_rows;
$tb_name=trim($_GET['tb']);
// if no tb exit
if($tb_name == '' OR $check == 0) { 
	header("Location: main.php"); exit;}
// define url query
$_url="db=".urlencode($db_name)."&tb=".urlencode($tb_name);
	
$check_cl = $db->query("SHOW FULL COLUMNS FROM ".PMA_bkq($tb_name)." LIKE '".$db->real_escape_string($_GET['col'])."'");
$check = $check_cl->num_rows;
$col_name=trim($_GET['col']);
// if no col exit
if($col_name == '' OR $check == 0) { 
	header("Location: table.php?$_url"); exit;}
// adding extra info to $_url
$_url.="&col=".urlencode($col_name);
$act=$_GET['act'];
if($act=='drop') {
	if($_POST['ok']){
		$_q="ALTER TABLE ".PMA_bkq($tb_name)." DROP ".PMA_bkq($col_name);
		if($result = $db->query($_q)) 
			{
				$_msg = htmlentities($col_name);
			} else {
				$_err=1; $_msg=$db->error;
			}
	}
}elseif($act=='edit') {
	if($_POST['ok']){
		$length=(trim($_POST['length']) != "" ? "(".trim($_POST['length']).")" : ""); 
		$null = $_POST['null'] == 1 ? "NULL" : "NOT NULL";
		if($_POST['default'] == "USER_DEFINED") {
		$default= "DEFAULT '".$db->real_escape_string($_POST['default2'])."'";
		} elseif($_POST['default'] !='NONE') {
		$default="DEFAULT ".$_POST['default'];
		}
		if(trim($_POST['collation']) !='') {
		$coll=explode("_",$_POST['collation']);
		$collation = "CHARACTER SET ".$coll[0]." COLLATE ".$_POST['collation'];
		}
		if($_POST['auto'] == 1) $auto='AUTO_INCREMENT';
		
		if($_POST['pos'] == 1) 
			$pos="FIRST";
			elseif($_POST['pos'] == 2)
				$pos="AFTER ".PMA_bkq($_POST['pos2']);
		
		if(trim($_POST['comments']) !='') $comments="COMMENT  '".$db->real_escape_string($_POST['comments'])."'"; 
		$_q="ALTER TABLE ".PMA_bkq($tb_name)." CHANGE ".PMA_bkq($col_name)." ".PMA_bkq($_POST['name'])." ".$_POST['type']."$length ".trim($_POST['attribute'])." $collation $null $default $auto $comments $pos";
		if($result = $db->query($_q)) 
			{
				$_msg = htmlentities($_POST['name']);
			} else {
				$_err=1; $_msg=$db->error;
			}
	} else {
		$col_data=$check_cl->fetch_array();
		$_q="SHOW FULL COLUMNS FROM ".PMA_bkq($tb_name);
		if($data = $db->query($_q)) {
			while($_d = $data->fetch_object()) {
				$_cols[]=$_d->Field;
			}
		}
		
		
		//ok here we go :)
		
		//grab the type and lenght
		$extracted_fieldspec=PMA_extractFieldSpec($col_data['Type']);
		 $type = $extracted_fieldspec['type'];
		 if ('set' == $extracted_fieldspec['type'] || 'enum' == $extracted_fieldspec['type']) {
		$length = $extracted_fieldspec['spec_in_brackets'];
	   } else {
		   // strip the "BINARY" attribute, except if we find "BINARY(" because
		   // this would be a BINARY or VARBINARY field type
		   $type   = preg_replace('@BINARY([^\(])@i', '', $type);
		   $type   = preg_replace('@ZEROFILL@i', '', $type);
		   $type   = preg_replace('@UNSIGNED@i', '', $type);
		   $length = $extracted_fieldspec['spec_in_brackets'];
	   } // end if else
			// some types, for example longtext, are reported as
			// "longtext character set latin7" when their charset and / or collation
			// differs from the ones of the corresponding database.
		$tmp = strpos($type, 'character set');
		if ($tmp) {
		$type = substr($type, 0, $tmp - 1); //Longdaik





		}
		// rtrim the type, for cases like "float unsigned"
		$type = rtrim($type);
		
		
		// default value
		switch ($col_data['Default']) {
			case null:
				if ($col_data['Null'] == 'YES') {
					$col_data['DefaultType']  = 'NULL';
					$col_data['DefaultValue'] = '';
				} else {
					$col_data['DefaultType']  = 'NONE';
					$col_data['DefaultValue'] = '';
				}
				break;
			case 'CURRENT_TIMESTAMP':
				$col_data['DefaultType']  = 'CURRENT_TIMESTAMP';
				$col_data['DefaultValue'] = '';
				break;
			default:
				$col_data['DefaultType']  = 'USER_DEFINED';
				$col_data['DefaultValue'] = $col_data['Default'];
				break;
		}
		// Collation
		 $collation = empty($col_data['Collation']) ? null : $col_data['Collation'];
		
		
		
		// attribute
		if (isset($extracted_fieldspec) && ('set' == $extracted_fieldspec['type'] || 'enum' == $extracted_fieldspec['type'])) {
			$binary           = 0;
			$unsigned         = 0;
			$zerofill         = 0;
		} else {
			$binary           = false;
			$unsigned         = stristr($col_data['Type'], 'unsigned');
			$zerofill         = stristr($col_data['Type'], 'zerofill');
		}
		if ($binary) {
			$attribute = 'BINARY';
		}
		if ($unsigned) {
			$attribute = 'UNSIGNED';
		}
		if ($zerofill) {
			$attribute = 'UNSIGNED ZEROFILL';
		}
		if (isset($col_data['Extra']) && $col_data['Extra'] == 'on update CURRENT_TIMESTAMP') {
			$attribute = 'on update CURRENT_TIMESTAMP';
		}
		// is null ?
		if (! empty($col_data['Null']) && $col_data['Null'] != 'NO' && $col_data['Null'] != 'NOT NULL')
			$isnull=1;
			
		// auto increment ?
		if (isset($col_data['Extra']) && strtolower($col_data['Extra']) == 'auto_increment')
			$isAI=1;
		
		// comment
		$comment=$col_data['Comment'];
		
		// done !!
	
	
	} // end else
}else{
$col_data=$check_cl->fetch_object();
}
$pma->title=$lang->Column;
include $pma->tpl."header.tpl";
include $pma->tpl."tbl_column.tpl";
include $pma->tpl."footer.tpl";