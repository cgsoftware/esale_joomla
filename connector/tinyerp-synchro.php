<?php
define( '_JEXEC', 1); 
$my_path = dirname(__FILE__); 

if( file_exists($my_path."/configuration.php")) {
	define( 'JPATH_BASE', $my_path );
} else {
	die( "Joomla Configuration File not found!" );
} 


include("xmlrpc.inc");
include("xmlrpcs.inc");

require_once( 'configuration.php' );
//require_once( 'includes/joomla.php' );
require_once( 'administrator/components/com_virtuemart/virtuemart.cfg.php' );

$temp = new JConfig;
foreach (get_object_vars($temp) as $k => $v) {
	$name = 'mosConfig_'.$k;
    $GLOBALS[$name] = $v;
}


$con = mysql_pconnect($mosConfig_host, $mosConfig_user,$mosConfig_password );
mysql_select_db($mosConfig_db);

function debug($s) {
	$printdebug='Yes'; //Stampa il Debug sul file Oerp2VM.log
	//$printdebug='No';
	
	if($printdebug == 'Yes') {
		$fp = fopen("Oerp2VM.log","a");
		if(is_array($s)){
			fwrite($fp, 'Questo e\' un array di '.count($s)." elementi \n");
			foreach($s as $key => $value) {
				$aa = $key." : ";
				fwrite($fp, $aa);
				for($j = 0 ; $j < count($s[$key]) ; $j++) {
					//$bb = $s[$key][$j] . ' ';
					$bb = $s[$key]." \n";
					fwrite($fp, $bb);
				}
				fwrite($fp, "");
			}
		}
		else {
			fwrite($fp, $s."\n");
		}
		fclose($fp);
	}
	return;
}

function printLOG($s) {
	$fp = fopen("Oerp2VM.log","a");
	if(is_array($s)){
		fwrite($fp, 'Questo e\' un array di '.count($s)." elementi ");
		foreach($s as $key => $value) {
			$aa = $key." : ";
			fwrite($fp, $aa);
			for($j = 0 ; $j < count($s[$key]) ; $j++) {
				//$bb = $s[$key][$j] . ' ';
				$bb = $s[$key]." \n";
				fwrite($fp, $bb);
			}
			fwrite($fp, "");
		}
	}
	else {
		fwrite($fp, $s."\n");
	}
	fclose($fp);

	return;
}


function get_taxes() {
	//debug('get_taxes ');
	global $mosConfig_dbprefix;
	$taxes=array();

	$result=mysql_query("select tax_rate_id, tax_rate*100 from ".$mosConfig_dbprefix."vm_tax_rate;");
	if ($result) while ($row=mysql_fetch_row($result)) {
		$taxes[]=new xmlrpcval(array(new xmlrpcval($row[0], "int"), new xmlrpcval("Tax ".$row[1]."%", "string")), "array");
	}
	return new xmlrpcresp( new xmlrpcval($taxes, "array"));
}

function delete_products() {
	//debug('delete_products ');
	global $mosConfig_dbprefix;

	mysql_query("truncate  table ".$mosConfig_dbprefix."vm_product_attribute;");
	mysql_query("truncate  table ".$mosConfig_dbprefix."vm_product_price;");
	mysql_query("truncate  table ".$mosConfig_dbprefix."vm_product_tax;");
	mysql_query("truncate  table ".$mosConfig_dbprefix."vm_product_category_xref;");
	mysql_query("delete from ".$mosConfig_dbprefix."jf_content where reference_table='vm_product' and reference_field in ('product_s_desc','product_desc') ;");
	$result=mysql_query("truncate  table ".$mosConfig_dbprefix."vm_product;");
	//$result=mysql_query("update ".$mosConfig_dbprefix."vm_product set `product_publish` = 'N';");
	//$result=mysql_query("update ".$mosConfig_dbprefix."vm_product set ".
	//  "product_publish='N' ".
	//  ";");

	return new xmlrpcresp( new xmlrpcval($result, "int"));
}

function unlink_products($product_id) {
	//debug('unlink_products ');
	global $mosConfig_dbprefix;
	//self.debug("Products Ids for Unlink: ".implode(",",$product_ids));
	mysql_query("update ".$mosConfig_dbprefix."vm_product set product_publish='N' where product_id in (".implode(",",$product_id).");");
	return new xmlrpcresp(new xmlrpcval(1,"int"));
}

function delete_product_categories() {
	//debug('delete_product_categories');
	global $mosConfig_dbprefix;

	mysql_query("truncate  table ".$mosConfig_dbprefix."vm_category_xref;");
	$result = mysql_query("truncate  table ".$mosConfig_dbprefix."vm_category;");


	return new xmlrpcresp(new xmlrpcval($result, "int"));
}

function get_languages() {
	//debug('get_languages ');
	$languages = array();

	$languages[] = new xmlrpcval(array(new xmlrpcval(1, "int"), new xmlrpcval("Unique", "string")), "array");

	return new xmlrpcresp( new xmlrpcval($languages, "array"));
}

function get_categories() {
	//debug('get_categories ');
	global $mosConfig_dbprefix;
	$categories=array();

	$result=mysql_query("select category_id, category_name from ".$mosConfig_dbprefix."vm_category;");

	if ($result) while ($row=mysql_fetch_row($result)) {
		$row0 = $row[0];
		$row1 = urlencode($row[1]);
		$categories[] = new xmlrpcval(array(new xmlrpcval($row[0], "int"), new xmlrpcval(parent_category($row0, $row1), "string")), "array");
	}

	return new xmlrpcresp( new xmlrpcval($categories, "array"));
}

function parent_category($id, $name) {
	//debug('parent_category ');
	global $mosConfig_dbprefix;
	$result=mysql_query("select category_parent_id from ".$mosConfig_dbprefix."vm_category_xref where category_child_id=".$id.";");
	if ($result && $row=mysql_fetch_row($result)) {
		if ($row[0]==0) {
			return $name;
		} else {
			$resultb=mysql_query("select category_name from ".$mosConfig_dbprefix."vm_category where category_id=".$row[0].";");
			if ($resultb && $rowb=mysql_fetch_row($resultb)) {
				$name=parent_category($row[0], $rowb[0] . " \\ ". $name);
				return $name;
			}
		}
	}
	return $name;
}

function set_product_stock($tiny_product) {
	global $mosConfig_dbprefix;	
	//debug("===> ID:".$tiny_product['ID']." - Code:".$tiny_product['code']." - esale_joomla_id:".$tiny_product['esale_joomla_id']." - Quantity:".$tiny_product['quantity']);
	mysql_query("update ".$mosConfig_dbprefix."vm_product set product_in_stock=".$tiny_product['quantity']." where product_id=".$tiny_product['esale_joomla_id'].";");
	return new xmlrpcresp(new xmlrpcval(1,"int"));
}





function _update_category_name_by_lang($lang_id, $lang_str, $osc_id, $tiny_category) {
	//debug('_update_category_name_by_lang ');
	$res = 0;
	global $mosConfig_dbprefix;

	$original_name = ($tiny_category['name']);
	$name_value = ($tiny_category['name:' . $lang_str]);

	if($name_value == '' and $original_name != '') {
		$name_value = $original_name;
	} elseif ($original_name == '' and $name_value != '') {
		$original_name = $name_value;
	}

	if($original_name != '') {
		$name_value = str_replace('"', '\"', $name_value);
		$result = mysql_query("select id from ".$mosConfig_dbprefix."jf_content where reference_id=".$osc_id." and reference_table='vm_category' and reference_field='category_name';");

		$res = -1;
		if ($result && $row=mysql_fetch_row($result)) {
			$res = mysql_query("update ".$mosConfig_dbprefix."jf_content set reference_id=".$osc_id." ,language_id='".$lang_id."',published=1,value=\"".$name_value."\",modified='".date( "Y-m-d H:i:s" )."',original_value='".md5($original_name)."' where id=".$row[0].";");
		} else {
			$res = mysql_query("insert into  ".$mosConfig_dbprefix."jf_content(language_id,reference_id,reference_table,reference_field,value,original_value,modified_by,modified,published) values (".$lang_id.",".$osc_id.",'vm_category','category_name',\"".$name_value."\",'".md5($original_name)."',70,'".date( "Y-m-d H:i:s" )."',1);");
		}
	}
}

function set_category($tiny_category){
	//debug('set_category ');
	global $mosConfig_dbprefix;



	if($tiny_category['esale_joomla_id'] != 0) {
		$result = mysql_query("update ".$mosConfig_dbprefix."vm_category set ".
				"category_name='". ($tiny_category['name'])."', ".
				"category_description='". ($tiny_category['name'])."'".
				"where category_id=".$tiny_category['esale_joomla_id'].";");
		$osc_id = $tiny_category['esale_joomla_id'];

	} else {
		mysql_query("insert into  ".$mosConfig_dbprefix."vm_category  (category_name,category_description,category_publish,vendor_id) values (\"". ($tiny_category['name'])."\",\"". ($tiny_category['name'])."\",\"Y\",'1');");
		$category_child_id = mysql_insert_id();
		$upd = "update ".$mosConfig_dbprefix."vm_category set list_order=".$category_child_id." where category_id=".$category_child_id.";";
		//debug($upd);
		mysql_query($upd);
		$category_parent_id=$tiny_category['parent_id'];
		mysql_query("insert into  ".$mosConfig_dbprefix."vm_category_xref  (category_parent_id,category_child_id) values (".$category_parent_id.",".$category_child_id.");");
		$osc_id = $category_child_id;

	}
	$result = mysql_query("select id from ".$mosConfig_dbprefix."languages where iso='it';");
	$lang_id = -1;
	if ($result && $row=mysql_fetch_row($result)) {
		$lang_id = $row[0];

	}

	// Retrieve Italian language:
	$res = mysql_query("select id from ".$mosConfig_dbprefix."languages where iso='it';");
	$lang_it_id = -1;
	$lang_it_str = 'it_IT';
	if ($res && $row=mysql_fetch_row($res)) {
		$lang_it_id = $row[0];
	}

	// Retrieve ENGLISH language:
	$res = mysql_query("select id from ".$mosConfig_dbprefix."languages where iso='en';");
	$lang_en_id = -1;
	$lang_en_str = 'en_US';
	if ($res && $row=mysql_fetch_row($res)) {
		$lang_en_id = $row[0];
	}

	_update_category_name_by_lang($lang_it_id, $lang_it_str, $osc_id, $tiny_category);
	_update_category_name_by_lang($lang_en_id, $lang_en_str, $osc_id, $tiny_category);

	return new xmlrpcresp(new xmlrpcval($osc_id, "int"));
}

function set_tax($tiny_tax){
	//debug('set_tax ');
	global $mosConfig_dbprefix;
	$country_id = '-';
	$state_id = '-';
	$type = 'fixamount';
	if ($tiny_tax['type'] == 'percent')
		$type = 'percentage';

	if ($tiny_tax['country']) {
		$result = mysql_query("select country_3_code from ".$mosConfig_dbprefix."vm_country where country_name='".$tiny_tax['country']."';");
		if ($result && $row=mysql_fetch_row($result)) {
			$country_id = $row[0];
		}
	}

	if($tiny_tax['esale_joomla_id'] != 0) {
		$result=mysql_query("update ".$mosConfig_dbprefix."vm_tax_rate set ".
				"tax_name='".$tiny_tax['name']."', ".
				"tax_type='".$type."', ".
				"tax_rate=".$tiny_tax['rate'].", ".
				"inc_base_price=".$tiny_tax['include_base_amount'].", ".
				"tax_country='".$country_id."', ".
				"tax_state='".$state_id."', ".
				"priority=".$tiny_tax['sequence'].
				" where tax_rate_id=".$tiny_tax['esale_joomla_id'].";");

		$osc_id=$tiny_tax['esale_joomla_id'];
	} else {
		mysql_query("insert into  ".$mosConfig_dbprefix."vm_tax_rate  (tax_name,tax_type,tax_rate,inc_base_price,priority,tax_country,tax_state,vendor_id) values (\"".$tiny_tax['name']."\",\"".$type."\",".$tiny_tax['rate'].",".$tiny_tax['include_base_amount'].",".$tiny_tax['sequence'].",'".$country_id."','".$state_id."','1');");
		$osc_id = mysql_insert_id();
	}

	return new xmlrpcresp(new xmlrpcval($osc_id, "int"));
}

function _update_product_name_by_lang($lang_id, $lang_str, $osc_id, $tiny_product) {
	//debug("_update_product_name_by_lang");
	$res = 0;
	global $mosConfig_dbprefix;

	$original_name = ($tiny_product['name']);
	$name_value = ($tiny_product['name:' . $lang_str]);

	//debug($original_name." - ".$name_value);


	if($name_value=='' and $original_name!='') {
		$name_value = $original_name;
	} elseif ($original_name=='' and $name_value!='') {
		$original_name = $name_value;
	}

	if($original_name != '') {
		$name_value = str_replace('"', '\"', $name_value);
		$result = mysql_query("select id from ".$mosConfig_dbprefix."jf_content where reference_id=".$osc_id." and reference_table='vm_product' and reference_field='product_name' and language_id = ".$lang_id.";");

		$res = -1;
		if ($result && $row=mysql_fetch_row($result)) {
			$q1="update ".$mosConfig_dbprefix."jf_content set reference_id=".$osc_id." ,language_id='".$lang_id."',published=1,value=\"".$name_value."\",modified='".date( "Y-m-d H:i:s" )."',original_value='".md5($original_name)."' where id=".$row[0].";";
			//debug($q1);
			$res = mysql_query($q1);

			//self.debug("update ".$mosConfig_dbprefix."jf_content set reference_id=".$osc_id." ,language_id='".$lang_id."',published=1,value=\"".$name_value."\",modified='".date( "Y-m-d H:i:s" )."',original_value='".md5($original_name)."' where id=".$row[0].";");
			//self.debug("res_update: " . $res);
		} else {
			$q2="insert into ".$mosConfig_dbprefix."jf_content 																												(language_id,reference_id,reference_table,reference_field,value,original_value,modified_by,modified,published) 
				values (".$lang_id.",".$osc_id.",'vm_product','product_name',\"".$name_value."\",'".md5($original_name)."',70,'".date( "Y-m-d H:i:s" )."',1);";
			//debug($q2);
			$res = mysql_query($q2);
			//self.debug("insert into  ".$mosConfig_dbprefix."jf_content(language_id,reference_id,reference_table,reference_field,value,original_value,modified_by,modified,published) values (".$lang_id.",".$osc_id.",'vm_product','product_name',\"".$name_value."\",'".md5($original_name)."',70,'".date( "Y-m-d H:i:s" )."',1);");
			//self.debug("res_insert: " . $res);
		}
	}
}

function _update_product_long_desc_by_lang($lang_id, $lang_str, $osc_id, $tiny_product) {
	//debug('_update_product_long_desc_by_lang ');
	$res = 0;
	global $mosConfig_dbprefix;

	$original_name = ($tiny_product['description']);
	$desc_value = ($tiny_product['description:' . $lang_str]);

	if ($desc_value=='' and $original_name!='') {
		$desc_value = $original_name;
	} elseif ($original_name=='' and $desc_value!='') {
		$original_name = $desc_value;
	}

	if ($original_name!='') {
		$desc_value = str_replace('"', '\"', $desc_value);
		$result = mysql_query("select id from ".$mosConfig_dbprefix."jf_content where reference_id=".$osc_id." and reference_table='vm_product' and reference_field='product_desc' and language_id = ".$lang_id.";");

		$res = -1;
		if ($result && $row=mysql_fetch_row($result)) {
			$res = mysql_query("update ".$mosConfig_dbprefix."jf_content set reference_id=".$osc_id." ,language_id='".$lang_id."',published=1,value=\"".$desc_value."\",modified='".date( "Y-m-d H:i:s" )."',original_value='".md5($original_name)."' where id=".$row[0].";");
		} else {
			$res = mysql_query("insert into  ".$mosConfig_dbprefix."jf_content(language_id,reference_id,reference_table,reference_field,value,original_value,modified_by,modified,published) values (".$lang_id.",".$osc_id.",'vm_product','product_desc',\"".$desc_value."\",'".md5($original_name)."',70,'".date( "Y-m-d H:i:s" )."',1);");
		}
	}
}

function _update_product_short_desc_by_lang($lang_id, $lang_str, $osc_id, $tiny_product) {
	//debug('_update_product_short_desc_by_lang ');
	$res = 0;
	global $mosConfig_dbprefix;

	$original_name = ($tiny_product['short_description']);
	$desc_value = ($tiny_product['short_description:' . $lang_str]);

	if($desc_value=='' and $original_name!=''){
		$desc_value = $original_name;
	} elseif ($original_name=='' and $desc_value!='') {
		$original_name = $desc_value;
	}

	if ($original_name!='') {
		$desc_value = str_replace('"','\"',$desc_value);
		$result = mysql_query("select id from ".$mosConfig_dbprefix."jf_content where reference_id=".$osc_id." and reference_table='vm_product' and reference_field='product_s_desc' and language_id = ".$lang_id.";");
		$temp = -1;
		if ($result && $row=mysql_fetch_row($result)) {
			$temp = mysql_query("update ".$mosConfig_dbprefix."jf_content set reference_id=".$osc_id.",language_id='".$lang_id."',published=1,value=\"".$desc_value."\",modified='".date( "Y-m-d H:i:s" )."',original_value='".md5($original_name)."' where id=".$row[0].";");
		} else {
			$temp = mysql_query("insert into  ".$mosConfig_dbprefix."jf_content(language_id,reference_id,reference_table,reference_field,value,original_value,modified_by,modified,published) values (".$lang_id.",".$osc_id.",'vm_product','product_s_desc',\"".$desc_value."\",'".md5($original_name)."',70,'".date( "Y-m-d H:i:s" )."',1);");
		}
	}
}

function set_product($tiny_product){
	$rpc_error = 0;

	global $mosConfig_dbprefix;
	$prod = Array(
			'vendor_id'=>0
		     );
	printLOG("Export => ".$tiny_product['prod_update_count']." - ".$tiny_product['model']." - ".$tiny_product['article']."");
    //debug($tiny_product);

	$result = mysql_query("select vendor_id, vendor_currency from ".$mosConfig_dbprefix."vm_vendor;");
	if ($result && $row=mysql_fetch_row($result)) {
		$prod['vendor_id']=$row[0];
		$prod['vendor_currency']=$row[1];
	}

	$result=mysql_query("select shopper_group_id from ".$mosConfig_dbprefix."vm_shopper_group where vendor_id=".$vendor_id." and shopper_group_name='-default-';");
	if ($result && $row=mysql_fetch_row($result))
		$prod['shopper_group']=$row[0];



	if ( $tiny_product['esale_joomla_id']) {
		$result = mysql_query("select count(*) from ".$mosConfig_dbprefix."vm_product where product_id=". $tiny_product['esale_joomla_id']);
		$row = mysql_fetch_row($result);
		if (! $row[0] )
			$tiny_product['esale_joomla_id'] = 0;
	}

	if (! $tiny_product['esale_joomla_id']) {
		$res = mysql_query("select shopper_group_id,vendor_id from ".$mosConfig_dbprefix."vm_shopper_group where `default` = 1");
		$shoperid = mysql_fetch_array($res);
		$shopper_group = $shoperid['shopper_group_id'];
		$vendorid = $shoperid['vendor_id'];

		$res = mysql_query("select vendor_currency from ".$mosConfig_dbprefix."vm_vendor where vendor_id = ".$vendorid);
		$vcurrency = mysql_fetch_array($res);
		$vendor_currency = $vcurrency['vendor_currency'];

		mysql_query("insert into ".$mosConfig_dbprefix."vm_product () values ()");
		$osc_id = mysql_insert_id();
		mysql_query("insert into ".$mosConfig_dbprefix."vm_product_price (product_id, product_price, product_currency, product_price_vdate, product_price_edate, shopper_group_id) values (".$osc_id.", ".$tiny_product['price'].", '".$vendor_currency."', 0, 0, ".$shopper_group.");");
		mysql_query("insert into ".$mosConfig_dbprefix."vm_product_category_xref (product_id, category_id) values (".$osc_id.", ".$tiny_product['category_id'].");");

		foreach ($tiny_product['tax_rate_id'] as $taxes=>$values) {
			mysql_query("insert into ".$mosConfig_dbprefix."vm_product_tax (product_id, tax_rate_id) values (".$osc_id.", ".$values["id"].");");
		}
	} else {
		$osc_id = $tiny_product['esale_joomla_id'];
	}
	
	if($tiny_product['product_special']==1){$tiny_product['product_special']='Y';} else {$tiny_product['product_special']='N';}
	
	
	$query = "update ".$mosConfig_dbprefix."vm_product set ".
		"product_in_stock=".$tiny_product['quantity'].",".
		"product_weight='".$tiny_product['product_weight']."',".
		"product_weight_uom='".$tiny_product['weight_uom']."',".
		"product_length='".$tiny_product['product_length']."',".
		"product_width='".$tiny_product['product_width']."',".
		"product_height='".$tiny_product['product_height']."',".
		"product_lwh_uom='".$tiny_product['lenght_uom']."',".
		"product_special='".$tiny_product['product_special']."',".
		"mdate='".time()."',".
		"product_sku='".mysql_escape_string($tiny_product['model'])."',".
		"product_name='".mysql_escape_string( ($tiny_product['article']))."',". 
		"vendor_id='".$prod['vendor_id']."',".
		"product_desc='".mysql_escape_string( ($tiny_product['description']))."', ".
		"product_unit='".mysql_escape_string( ($tiny_product['product_unit']))."', ".
		"product_publish='Y',".
		"slideshow='".$tiny_product['slideshow']."', ".
		"product_packaging=".$tiny_product['product_packaging_qty'].",".
		//"product_packaging_type='".$tiny_product['product_packaging_type']."',".
		//"product_s_desc='".mysql_escape_string( (substr($tiny_product['short_description'],0,200)))."' ". //Original
		"product_s_desc='".mysql_escape_string( ($tiny_product['name']))."' ".
		"where product_id=".$osc_id.";";

	debug("Query inserimento prodotto ===> ".$query."");
	
	if(!mysql_query($query)) {
		$query_add = "ALTER TABLE ".$mosConfig_dbprefix."vm_product ADD COLUMN slideshow VARCHAR(64)  AFTER product_order_levels;";
		mysql_query($query_add);
		mysql_query($query);
	}
	else {
		mysql_query($query);
	}
	
	//debug($tiny_product['product_attribute']);
	//setta electronic_product
	if(!mysql_query("SELECT * FROM ".$mosConfig_dbprefix."vm_product_electronic_attribute;")) {
		debug('111');
		$query_add = "CREATE TABLE ".$mosConfig_dbprefix."vm_product_electronic_attribute (
  					id int(9) NOT NULL auto_increment,
  					create_uid int(4) default NULL,
  					create_date timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  					`name` varchar(255) default NULL,
  					`value` varchar(255) default NULL,
  					product_id int(9) default NULL,
  					PRIMARY KEY  (id)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Tabella popolata da OpenERP by Luca Subiaco' AUTO_INCREMENT=1084;";
		mysql_query($query_add);
		//mysql_query($query);
	}
	
	if(is_array($tiny_product['product_attribute'])) {
		$q_electric = "DELETE FROM ".$mosConfig_dbprefix."vm_product_electronic_attribute WHERE product_id LIKE ".$osc_id.";";
		$res = mysql_query($q_electric);
		//debug("Query cancella record ==> ".$q_electric);
		foreach($tiny_product['product_attribute'] as $key => $value) {
			$value = str_replace('|min|','<',$value);
			$value = str_replace('|mag|','>',$value);
			$value = str_replace('|uppersend|','&',$value);
			$q_electric1 = "INSERT INTO ".$mosConfig_dbprefix."vm_product_electronic_attribute (name, value, product_id) VALUES('".$key."','".$value."','".$osc_id."');";
			$res = mysql_query($q_electric1);
			//debug("Query paramentri: ".$q_electric1."");

		}
	}


	//SETTA QUANTITA
	$q4="update ".$mosConfig_dbprefix."vm_product set product_in_stock=".$tiny_product['quantity']." where
		product_id=".$osc_id.";";
	//debug('Setta qty --> '.$q4);
	mysql_query($q4);

	if (!$res) {
		$rpc_error = 1<<0;
	}

	// Replace or delete old values

	mysql_query("update ".$mosConfig_dbprefix."vm_product_price set product_price='".$tiny_product['price']."' where product_id=".$osc_id.";");
	mysql_query("update ".$mosConfig_dbprefix."vm_product_category_xref set category_id='".$tiny_product['category_id']."' where product_id=".$osc_id.";");
	mysql_query("delete from ".$mosConfig_dbprefix."vm_product_tax where product_id=".$osc_id.";");
	foreach ($tiny_product['tax_rate_id'] as $taxes=>$values) {
		mysql_query("insert into ".$mosConfig_dbprefix."vm_product_tax (product_id, tax_rate_id) values (".$osc_id.", ".$values["id"].");");
	}

	// Retrieve ITALIAN language:
	$res = mysql_query("select id from ".$mosConfig_dbprefix."languages where iso='it';");
	$lang_it_id = -1;
	$lang_it_str = 'it_IT';
	if ($res && $row=mysql_fetch_row($res)) {
		$lang_it_id = $row[0];
		update($lang_it_id);
	}

	// Retrieve ENGLISH language:
	$res = mysql_query("select id from ".$mosConfig_dbprefix."languages where iso='en';");
	$lang_en_id = -1;
	$lang_en_str = 'en_US';
	if ($res && $row=mysql_fetch_row($res)) {
		$lang_en_id = $row[0];
		update($lang_en_id);
	}

	// Product name:
	_update_product_name_by_lang($lang_it_id, $lang_it_str, $osc_id, $tiny_product);
	_update_product_name_by_lang($lang_en_id, $lang_en_str, $osc_id, $tiny_product);

	// Product long description:
	_update_product_long_desc_by_lang($lang_it_id, $lang_it_str, $osc_id, $tiny_product);
	_update_product_long_desc_by_lang($lang_en_id, $lang_en_str, $osc_id, $tiny_product);

	// Product short description:
	_update_product_short_desc_by_lang($lang_it_id, $lang_it_str, $osc_id, $tiny_product);
	_update_product_short_desc_by_lang($lang_en_id, $lang_en_str, $osc_id, $tiny_product);

	if ($tiny_product['haspic']==1 OR $tiny_product['img_small']==1) {
		//Image big
		if ($tiny_product['haspic']==1) {

			//$filename = tempnam('components/com_virtuemart/shop_image/product/', 'tiny_');
			$filename='components/com_virtuemart/shop_image/product/'.str_replace('/','-',$tiny_product['code']).'.png';
			//$extension = strrchr($tiny_product['code'].'.png','.');
			//$filename.=$extension;
			//debug("Filename IMG_BIG ==============================================>: ".$filename);
			//debug("Img_Big: ".$tiny_product['picture']."");

			$hd = fopen($filename, "w");
			fwrite($hd, base64_decode($tiny_product['picture']));
			fclose($hd);
			//$short = strrchr($filename,'/');
			//$short = substr($short, 1, strlen($short));
			$tiny_product['code'] = str_replace('/','-',$tiny_product['code']);
			//debug($tiny_product['code']);
			$q10 = "update ".$mosConfig_dbprefix."vm_product set product_full_image='".str_replace('/','-',$tiny_product['code']).".png' where product_id=".$osc_id.";";
			//debug("Query Update Image Big: $q10 ");
			mysql_query($q10);
			unlink(substr($filename,0,strlen($filename)-4));

		}

		//Image small
		if ($tiny_product['img_small']==1) {
			//debug("Filename IMG_THUMB ==============================================>: ".$filename);
			//debug("Img_Thumb: ".$tiny_product['picture']."");
			$filename='components/com_virtuemart/shop_image/product/resized/'.str_replace('/','-',$tiny_product['code']).'.png';
			//$filename = tempnam('components/com_virtuemart/shop_image/product/resized', 'tiny_');
			//$extension = strrchr($tiny_product['code'].'.png','.');
			//$filename .= $extension;
			//debug("Filename=".$filename);
			$hd = fopen($filename, "w");
			//debug('picture_thumb: '.$tiny_product['picture_thumb']);
			fwrite($hd, base64_decode($tiny_product['picture_thumb']));
			fclose($hd);
			$q11 = "update ".$mosConfig_dbprefix."vm_product set product_thumb_image='".str_replace('/','-',$tiny_product['code']).".png' where product_id=".$osc_id.";";
			//debug("Query_Picture_Thumb ===> $q11 ");
			mysql_query($q11);
			unlink(substr($filename,0,strlen($filename)-4));
			//debug("Filename Image: ".$filename."");
		}


		/*
		   if ($tiny_product['haspic']==1) {
		//Image small
		$newxsize = PSHOP_IMG_WIDTH;
		if (!$newxsize) {
		$newxsize=90;
		}
		$newysize = PSHOP_IMG_HEIGHT;
		if (!$newysize) {
		$newysize=60;
		}

		$filename = tempnam('components/com_virtuemart/shop_image/product/resized', 'tiny_');
		$extension = strrchr($tiny_product['code'].'.jpg','.');
		$extension=strtolower($extension);
		debug("Estenzione file immagine: ");
		debug($extension);
		debug("");
		if (in_array($extension, array('.jpeg', '.jpe', '.jpg', '.gif', '.png'))) {
		if (in_array($extension, array('.jpeg', '.jpe', '.jpg'))) {
		$extension='.jpeg';
		}
		elseif (in_array($extension, array('.png', '.PNG'))) {
		$extension='.png';
		}
		$thumb=tempnam('components/com_virtuemart/shop_image/product/resized', 'tiny_').$extension;

		$load='imagecreatefrom'.substr($extension,1,strlen($extension)-1);
		debug("LOAD---> ".$load."");
		$save='image'.substr($extension,1,strlen($extension)-1);
		debug("SAVE---> ".$save."");
		$tmp_img=$load($thumb);
		$imgsize = getimagesize($thumb);
		if ($imgsize[0] > $newxsize || $imgsize[1] > $newysize) {
		if ($imgsize[0]*$newysize > $imgsize[1]*$newxsize) {
		$ratio=$imgsize[0]/$newxsize;
		} else {
		$ratio=$imgsize[1]/$newysize;
		}
		} else {
		$ratio=1;
		}
		$tn=imagecreatetruecolor (floor($imgsize[0]/$ratio),floor($imgsize[1]/$ratio));
		imagecopyresized($tn,$tmp_img,0,0,0,0,floor($imgsize[0]/$ratio),floor($imgsize[1]/$ratio),$imgsize[0],$imgsize[1]);
		$short=strrchr($thumb,'/');
		$short=substr($short,1,strlen($short));
		$save($tn, $thumb);
		$q11 = "update ".$mosConfig_dbprefix."vm_product set product_thumb_image='".$short."' where product_id=".$osc_id.";";
		debug("$q11 ");
		mysql_query($q11); 
		unlink(substr($thumb,0,strlen($thumb)-4));
		debug("Filename Thumb: ".$thumb."");

		}
		}*/

	}

	$rpc_msg = "";
	if ($rpc_error > 0) {
		$rpc_msg = "Error in sync script";
	};

	$resp = new xmlrpcresp(new xmlrpcval($osc_id, "int"), $rpc_error, $rpc_msg);
	//$resp = new xmlrpcresp(new xmlrpcval($osc_id, "int"));
	return $resp;
}

function get_saleorders($last_so) {
	//debug('get_saleorders ');
	global $mosConfig_dbprefix;
	$saleorders=array();

	/*$result=mysql_query(
	  "SELECT
	  o.`order_id`, c.`last_name`, c.`address_1`, c.`city`, c.`zip`, c.`state`,
	  c.`country`, c.`phone_1`, c.`user_email`, d.`last_name` , d.`address_1` ,
	  d.`city`, d.`zip`, d.`state`, d.`country`, o.`cdate`,
	  c.title, c.first_name, d.title, d.first_name,
	  d.user_id, c.user_id, o.customer_note
	  FROM ".
	  $mosConfig_dbprefix."vm_orders as o,".
	  $mosConfig_dbprefix."vm_user_info as c, ".
	  $mosConfig_dbprefix."vm_user_info as d
	  where
	  o.order_id>".$last_so." and
	  o.user_id=c.user_id and
	  (c.address_type_name is NULL or c.address_type_name='-default-') and
	  o.user_info_id=d.user_info_id;
	  ");*/
	
	//Query che seleziona il cliente
	$query = "SELECT
			o.`order_id`, c.`last_name`, c.`address_1`, c.`city`, c.`zip`, c.`state`,
   			cn.`country_2_code` as `country`, c.`phone_1`, c.`user_email`, d.`last_name` , d.`address_1` ,
   			d.`city`, d.`zip`, d.`state`, d.`country`, o.`cdate`,
   			c.title, c.first_name, d.title, d.first_name,
   			d.user_id, c.user_id, o.customer_note,
   			o.order_discount,o.order_shipping,o.order_shipping_tax FROM ".
			$mosConfig_dbprefix."vm_orders as o,".
			$mosConfig_dbprefix."vm_user_info as c, ".
			$mosConfig_dbprefix."vm_country as cn, ".
			$mosConfig_dbprefix."vm_user_info as d
			where
			o.order_id>".$last_so." and
			o.user_id=c.user_id and
			(c.address_type_name is NULL or c.address_type_name='-default-') and
			( cn.country_3_code = c.country) and
			o.user_info_id=d.user_info_id;";
	//debug($query); 	
	$result=mysql_query($query);

	
			if ($result) while ($row=mysql_fetch_row($result)) {
				$orderlines = array();
				//Query che seleziona i prodotti
				$query1="select product_id, product_quantity, product_item_price from ".$mosConfig_dbprefix."vm_order_item where order_id=".$row[0].";";
				//debug("\n\n".$query1);
				$resultb = mysql_query($query1);
				if ($resultb) while ($rowb=mysql_fetch_row($resultb)) {
					//debug($rowb);
					$orderlines[] = new xmlrpcval( array(
								"product_id" => new xmlrpcval($rowb[0], "int"),
								"product_qty" =>  new xmlrpcval($rowb[1], "int"),
								"price" =>  new xmlrpcval($rowb[2], "string")
								), "struct");
				}
				
				$fee = $row[23];

				$shipping_fee = $row[24];	//costo di spedizione
				//debug("\n Shipping ".$shipping_fee);
				if ($shipping_fee!=0){
					
					$aa = array(
								"product_is_shipping" => new xmlrpcval(True, "boolean"),
							"product_name" => new xmlrpcval("Shipping fees", "string"),
									"product_qty" =>  new xmlrpcval(0, "int"),
											"price" =>  new xmlrpcval($shipping_fee, "string")
							   );
					
					$orderlines[] = new xmlrpcval( $aa, "struct");
					
				}
				$other_fee = (-1 * $fee);
				if ($other_fee != 0){
					$orderlines[] = new xmlrpcval( array(
								"product_name" => new xmlrpcval("Other fees", "string"),
								"product_qty" =>  new xmlrpcval(0, "int"),
								"price" =>  new xmlrpcval($other_fee, "string")
								), "struct");
				}
				//$orderlines[]=new xmlrpcval( array(
				//    "product_name" => new xmlrpcval("Shipping tax", "string"),
				//    "product_qty" =>  new xmlrpcval(0, "int"),
				//    "price" =>  new xmlrpcval($row[25], "string")
				//  ), "struct");

				$saleorders[] = new xmlrpcval( array(
							"id" => new xmlrpcval( $row[0], "int"),
							"note" => new xmlrpcval( $row[22], "string"),
							"lines" =>    new xmlrpcval( $orderlines, "array"),
							"address" =>  new xmlrpcval( array(
									"name"    => new xmlrpcval($row[16]." ".$row[1]." ".$row[17], "string"),
									"address"  => new xmlrpcval($row[2], "string"),
									"city"    => new xmlrpcval(urlencode($row[3]), "string"),
									"zip"    => new xmlrpcval($row[4], "string"),
									"state"    => new xmlrpcval($row[5], "string"),
									"country"  => new xmlrpcval($row[6], "string"),
									"phone"    => new xmlrpcval($row[7], "string"),
									"email"    => new xmlrpcval($row[8], "string"),
									"esale_id"  => new xmlrpcval($row[20], "string")
									), "struct"),
							"delivery" =>  new xmlrpcval( array(
									"name"    => new xmlrpcval($row[18]." ".$row[9]." ".$row[19], "string"),
									"address"  => new xmlrpcval($row[10], "string"),
									"city"    => new xmlrpcval(urlencode($row[11]), "string"),
									"zip"    => new xmlrpcval($row[12], "string"),
									"state"    => new xmlrpcval($row[13], "string"),
									"country"  => new xmlrpcval($row[14], "string"),
									"email"    => new xmlrpcval($row[8], "string"),
									"esale_id"  => new xmlrpcval($row[21], "string")
									), "struct"),
							"billing" =>new xmlrpcval( array(
										"name"    => new xmlrpcval($row[16]." ".$row[1]." ".$row[17], "string"),
										"address"  => new xmlrpcval($row[2], "string"),
										"city"    => new xmlrpcval(urlencode($row[3]), "string"),
										"zip"    => new xmlrpcval($row[4], "string"),
										"state"    => new xmlrpcval($row[5], "string"),
										"country"  => new xmlrpcval($row[6], "string"),
										"email"    => new xmlrpcval($row[8], "string"),
										"esale_id"  => new xmlrpcval($row[20], "string")
										), "struct"),
							"date" =>    new xmlrpcval( date('YmdHis',$row[15]), "date")
								), "struct");
			}
			
			debug($saleorders['delivery']);
			
			$resp = new xmlrpcresp(new xmlrpcval($saleorders, "array"));

			return $resp;
}

function process_order($order_id) {
	//debug('process_order ');
	global $mosConfig_dbprefix;
	mysql_query("update ".$mosConfig_dbprefix."vm_orders set order_status='C' where order_id=".$order_id.";");
	mysql_query("update ".$mosConfig_dbprefix."vm_order_item set oerder_status='C' where order_id=".$order_id.";");
	return new xmlrpcresp(new xmlrpcval(0, "int"));
}

function close_order($order_id) {
	//debug('close_order ');
	global $mosConfig_dbprefix;
	mysql_query("update ".$mosConfig_dbprefix."vm_orders set order_status='S' where order_id=".$order_id.";");
	mysql_query("update ".$mosConfig_dbprefix."vm_order_item set oerder_status='S' where order_id=".$order_id.";");
	return new xmlrpcresp(new xmlrpcval(0, "int"));
}

$server = new xmlrpc_server( array(
			"get_taxes" => array(
				"function" => "get_taxes",
				"signature" => array(array($xmlrpcArray))
				),
			"get_languages" => array(
				"function" => "get_languages",
				"signature" => array(array($xmlrpcArray))
				),
			"get_categories" => array(
				"function" => "get_categories",
				"signature" => array(array($xmlrpcArray))
				),
			"get_saleorders" => array(
				"function" => "get_saleorders",
				"signature" => array(array($xmlrpcArray, $xmlrpcInt))
				),
			"set_product" => array(
				"function" => "set_product",
				"signature" => array(array($xmlrpcInt, $xmlrpcStruct))
				),
			"set_category" => array(
					"function" => "set_category",
					"signature" => array(array($xmlrpcInt, $xmlrpcStruct))
					),
			"set_tax" => array(
					"function" => "set_tax",
					"signature" => array(array($xmlrpcInt, $xmlrpcStruct))
					),

			"set_product_stock" => array(
					"function" => "set_product_stock",
					"signature" => array(array($xmlrpcInt, $xmlrpcStruct))
					),
			"process_order" => array(
					"function" => "process_order",
					"signature" => array(array($xmlrpcInt, $xmlrpcInt))
					),
			"delete_products" => array(
					"function" => "delete_products",
					"signature" => array(array($xmlrpcArray))
					),
			"delete_product_categories" => array(
					"function" => "delete_product_categories",
					"signature" => array(array($xmlrpcArray))
					),
			"close_order" => array(
					"function" => "close_order",
					"signature" => array(array($xmlrpcInt, $xmlrpcInt))
					),
			"unlink_products"=>array(
					"function" => "unlink_products",
					"signature" => array(array($xmlrpcInt, $xmlrpcArray))
					)
			), false);
			$server->functions_parameters_type= 'phpvals';
			$server->service();
			?>
