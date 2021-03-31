<?
Class CRrGood
{
	function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
	{
		$MODULE_ID = basename(dirname(__FILE__));
		$aMenu = array(
			//"parent_menu" => "global_menu_services",
			"parent_menu" => "global_menu_settings",
			"section" => $MODULE_ID,
			"sort" => 50,
			"text" => $MODULE_ID,
			"title" => '',
			"url" => "partner_modules.php?module=".$MODULE_ID,
			"icon" => "",
			"page_icon" => "",
			"items_id" => $MODULE_ID."_items",
			"more_url" => array(),
			"items" => array()
		);

		if (file_exists($path = dirname(__FILE__).'/admin'))
		{
			if ($dir = opendir($path))
			{
				$arFiles = array();

				while(false !== $item = readdir($dir))
				{
					if (in_array($item,array('.','..','menu.php')))
						continue;

					if (!file_exists($file = $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.$MODULE_ID.'_'.$item))
						file_put_contents($file,'<'.'? require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/'.$MODULE_ID.'/admin/'.$item.'");?'.'>');

					$arFiles[] = $item;
				}

				sort($arFiles);

				foreach($arFiles as $item)
					$aMenu['items'][] = array(
						'text' => $item,
						'url' => $MODULE_ID.'_'.$item,
						'module_id' => $MODULE_ID,
						"title" => "",
					);
			}
		}
		$aModuleMenu[] = $aMenu;
	} 

	static function uploadGoods($FILE_NAME = false){

		if(!$FILE_NAME){
			$FILE_NAME = "/parser/csv.csv";
		}
		$comfirm_flags = array(1, 2, 3);
		$flag = 1; // 1 - запчасти; 2 - увлажнители; 3 - автоматика 

		if(!in_array($flag, $comfirm_flags)){
			return array("count_insert" => "0", "count_update" => "0","count_false" => "Выгрузка НЕВОЗМОЖНА,  обратитесь к специалисту");
		}

		$res_pack = self::parserCSV($FILE_NAME, $flag);
		$count = 0;
		$count_false = 0;
		$count_insert = 0;
		$count_update = 0;
		$ii = 0;
		if(!empty($res_pack)){
			$params = Array(
		       "max_len" => "100", // обрезает символьный код до 100 символов
		       "change_case" => "L", // буквы преобразуются к нижнему регистру
		       "replace_space" => "_", // меняем пробелы на нижнее подчеркивание
		       "replace_other" => "_", // меняем левые символы на нижнее подчеркивание
		       "delete_repeat_replace" => "true", // удаляем повторяющиеся нижние подчеркивания
		       "use_google" => "false", // отключаем использование google
			);

			foreach($res_pack as $key=>$item) {
				$ii++;
				// if($ii > 5){
				// 	wa_dump($key);
				// 	wa_dump("Добавлено");
				// 	wa_dump($count_insert);
				// 	wa_dump("Обновлено");
				// 	wa_dump($count_update);
				// 	wa_dump("Ошибки");
				// 	wa_dump($count_false);
				// 	exit;
				// }
				$el = new CIBlockElement;
				$property_name = "PROPERTY_ARTNUMBER";

				$dbRes = CIBlockElement::GetList(array(), array("IBLOCK_ID" => MAIN_IBLOCK, "=PROPERTY_ARTNUMBER" => $key), false, false, Array("IBLOCK_ID", "ID", "PROPERTY_ARTNUMBER"));
				$good = $dbRes->Fetch();
				$PID = $good["ID"];

				$arLoadProductArray = $item;
				
				unset($arLoadProductArray["PRICE"]);

				$arLoadProductArray["MODIFIED_BY"] = 1;
				$arLoadProductArray["PREVIEW_TEXT"] = $item["DETAIL_TEXT"];

				$arFields = array(
                	"AVAILABLE" => "Y", 
                	"QUANTITY" => 1, 
                	"QUANTITY_TRACE" => "D", 
                	"CAN_BUY_ZERO" => "D", 
                	"SUBSCRIBE" => "D", 
                );

				// обновление
				if($PID){
					$PRODUCT_ID = $PID;
					$res = $el->Update($PRODUCT_ID, $arLoadProductArray);
					if($res){
						$count_update++;
					}
					else{
						$count_false++;
					}
				}
				else{ // добавление
					$arLoadProductArray["IBLOCK_ID"] = MAIN_IBLOCK;
					$arLoadProductArray["CODE"] = CUtil::translit($item["NAME"], "ru" , $params);
					$arLoadProductArray["ACTIVE"] = "Y";
					$PRODUCT_ID = $el->Add($arLoadProductArray, false, false, false);
					if($PRODUCT_ID){
						$count_insert++;
					}
					else{
						$count_false++;
						continue;
					}
					$arFields["ID"] = $PRODUCT_ID; 
				}


                if(CModule::IncludeModule("catalog")){

					if($PID){ // обновляем
						CCatalogProduct::Update($PRODUCT_ID, $arFields);
					}
					else{ // добавляем
                		CCatalogProduct::Add($arFields);
					}             	


					$PRICE_TYPE_ID = 1;
					$currency = $item["CURRENCY"]?$item["CURRENCY"]:"EUR";

					$arFields = Array(
					    "PRODUCT_ID" => $PRODUCT_ID,
					    "CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
					    "PRICE" => $item["PRICE"],
					    "CURRENCY" => $currency,
					    "QUANTITY_FROM" => false,
					    "QUANTITY_TO" => false,
					);

					$res = CPrice::GetList(
				        array(),
				        array(
				            "PRODUCT_ID" => $PRODUCT_ID,
				            "CATALOG_GROUP_ID" => $PRICE_TYPE_ID
				        )
				    );

					if ($arr = $res->Fetch())
					{
					    CPrice::Update($arr["ID"], $arFields);
					}
					else
					{
					    CPrice::Add($arFields);
					}
					unset($el);
					$count++;
				}
			}
		}
		return array("count_update" => $count_update, "count_insert" => $count_insert, "count_false" => $count_false);
	}

	static function parserCSV($FILE_NAME, $flag){

		// настройки
		$delim = DELIM;
		
		// не свойства
		$noProp = array(
			"NAME",
			"DETAIL_TEXT",
			"PREVIEW_TEXT",
			"PRICE",
			"SECTION",
			"SUB_SECTION",
			"SERIA",
		);

		// свойства имещие в названии один из элементов этого массива объединяются в это свойства ({ELEC_MATERIAL, NASOS_MATERIAL} -> MATERIAL)
		$joinProps = array(
			"MATERIAL",
		);

		// иницилизация библиотеки
		require_once ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/csv_data.php");
		$arry = array();
		$csvFile = new CCSVData('R', false);
		$csvFile->LoadFile($_SERVER["DOCUMENT_ROOT"].$FILE_NAME);
		$csvFile->SetDelimiter(';');
		$headers = array();
		$arRows = array();


		// дерево категорий
		$sectTree = self::getTreeSection();

		$tree_types = $sectTree[0]["CHILD"][SID_TYPE]["CHILD"]; // дерево запчастей по типу
		$tree_uvla 	= $sectTree[0]["CHILD"][SID_UVLA]["CHILD"]; // дерево запчастей по увлажнителю
		$tree_uvl 	= $sectTree[0]["CHILD"][SID_UVL]["CHILD"];  // дерево увлажнителей
		$tree_auto 	= $sectTree[0]["CHILD"][SID_AUTO]["CHILD"]; // дерево автоматики

		// свойства 
		$iprops = self::getAllProperties();	

		$ia = 0;
		while ($arRes = $csvFile->Fetch()) {
			$item_props = array();
		   	if (empty($headers)) {
		   		foreach ($arRes as $ke => $val) {
		   			$vl = explode("(", $val);
		   			$vls = trim(str_replace(" ", "", iconv("CP1251", "UTF8", $vl[0])));
		   			$headers[$ke] = $vls;
		   		}
		   	}
		   	else{
		   		$arRow = array();
		   		$seria = array();
		   		$section_val = array();
		   		foreach ($headers as $key => $value) {
		   			$v = trim(iconv("CP1251", "UTF8", $arRes[$key]));
		   			if($v != ""){ // если не пустое поле
		   				if(in_array($value, $noProp)){ // это не свойство
		   					if($value == "SERIA"){
	   							$serval = explode($delim, $v);
	   							foreach ($serval as $kes => $ser){
	   								if($ser){	
	   									$se = explode(" ", $ser);
	   									$s = trim($se[1]);
	   									if($s){
	   										$seria[] = $s;
	   									}
	   									else{
	   										$seria[] = trim($se[0]);
	   									}
	   								}
	   							}
	   						}
		   					elseif($value == "SECTION"){
		   						$section_val["SECTION"] = $v;
		   					}
		   					elseif($value == "SUB_SECTION"){
		   						$subval = explode($delim, $v);
		   						foreach ($subval as $kese => $sec) {
		   						 	if($sec){
		   								$section_val["SUB_SECTION"][] = trim($v);
		   						 	}
		   						} 
		   					}
		   					else{
		   						$arRow[$value] = $v;
		   					}
		   				}
		   				else{
				   			foreach ($joinProps as $jprop) {
		   						if(!(strpos($value, $jprop) === false)){
		   							$value = $jprop;
		   						}
		   					}		   					
		   					$prop_key_val = self::getItemProps($v, $value, $iprops);
		   					// wa_dump($prop_key_val);
		   					if($prop_key_val["key"]){
		   						if(is_array($prop_key_val["result"])){
		   							if(($prop_key_val["result"][0] !== "") && (!is_null($prop_key_val["result"][0]))){
		   								$item_props[$prop_key_val["key"]] = $prop_key_val["result"];
		   							}
		   							elseif(isset($prop_key_val["result"]["VALUE"]) && $prop_key_val["result"]["VALUE"]){
		   								// wa_dump($prop_key_val["result"]);
		   								$item_props[$prop_key_val["key"]] = $prop_key_val["result"];
		   							}
		   						}
		   						else{
		   							if($prop_key_val["result"] !== ""){
		   								$item_props[$prop_key_val["key"]] = $prop_key_val["result"];
		   							}
		   						}
		   					}
		   				}
		   			}
		   		}
		   		if($flag == 1){ // запчасти
		   			$section_ids = self::getSectionForGood(array("section" => $section_val, "seria" => $seria), array("seria" => $tree_uvla, "section" => $tree_types), $flag);
		   		}
		   		elseif ($flag == 2) { // увлажнители
		   			
		   		}
		   		else{ // автоматика
		   			
		   		}
		   		$arRow["IBLOCK_SECTION"] = $section_ids;
		   		$arRow["PROPERTY_VALUES"] = $item_props;

		   		if($arRows[$arRow["PROPERTY_VALUES"][47]]){
		   			$kia = $ia+1;
		   			$actikules[$kia] = $arRow["PROPERTY_VALUES"][47];
		   		}

		   		$arRow["PROPERTY_VALUES"][$iprops["SERIA_UVLAZHNITEL"]["ID"]] = $seria;
		   		$arRows[$arRow["PROPERTY_VALUES"][47]] = $arRow;
		   	
		   	}
		  	$ia++;
		}
		// wa_dump($arRows); exit;
		unlink($_SERVER["DOCUMENT_ROOT"].$FILE_NAME);

		if($actikules){
			wa_dump("Найдены дубли");
			wa_dump($actikules); 
			exit;
		}
		return $arRows; 
	}

	static function getItemProps($value, $proper, $data){
		$prop = $data[$proper];
		$key = $prop["ID"];

		if($key){
			if($prop["MULTIPLE"]){ // нужно вернуть массив
				$result = array();
				$valarr = explode(DELIM, $value);
				foreach ($valarr as $va) {
					$va = trim($va);
					if($va && $va != DELIM){
						if($prop["ENUM"]){
							$result[] = self::getPropEnumValues($va, $prop["ENUM"]["VALUES"]);
						}
						elseif($prop["NUM"]){
							$va = str_replace(",", ".", $va);
							$float_va = preg_replace('/[^\d.]/','',$va);
							$result[] = (float)$float_va;
						}
						else{
							$result[] = trim($va);
						}
					}
				}
				$result = array_unique($result);
			}
			else{ // одиночное значение
				$result = "";
				$va = trim($value);
				
				if($va && $va != DELIM){
					if($prop["ENUM"]){
						$result = self::getPropEnumValues($va, $prop["ENUM"]["VALUES"]);
					}
					elseif($prop["NUM"]){
						$va = trim($va);
						if($proper == "AVAILABLE"){
							$result = 1;
							if($va == "под заказ" || $va == "0") $result = 0;
						}
						elseif ($proper == "ACCESSORY"){
							$result = 1;
							if($va == "0") $result = 0;
						}
						else{
							$va = str_replace(",", ".", $va);
							$float_va = preg_replace('/[^\d.]/','',$va);
							$result = (float)$float_va;
						}
					}
					elseif ($prop["TEXTAREA"]) {
						$type = mb_strtolower($prop["TEXTAREA"]);
						$result = array('VALUE'=>array('TYPE'=>$type, 'TEXT'=>$va));
					}
					else{
						$result = $va;
					}
				}
				else{
					if($prop["NUM"]){
						$va = trim($va);
						if($proper == "AVAILABLE"){
							$result = 1;
							if($va == "под заказ" || $va == "0") $result = 0;
						}
						elseif ($proper == "ACCESSORY"){
							$result = 1;
							if($va == "0") $result = 0;
						}
					}
				}
			}
			return array("key" => $key, "result" =>$result);
		}
		return array("key" => false);
	}

	static function getPropEnumValues($value, $data_enum){
		$result = "";
		foreach ($data_enum as $key => $val) {
			if($val["VALUE"] == $value){
				$result = $val["ID"];
			}
		}
		return $result;
	}

	static function getAllProperties(){
		$iprops = array();
		$properties = CIBlockProperty::GetList(Array("sort"=>"asc"), Array("ACTIVE"=>"Y", "IBLOCK_ID"=>MAIN_IBLOCK));
		while ($prop_fields = $properties->Fetch())
		{
			$iprops[$prop_fields["CODE"]]["NAME"] = $prop_fields["NAME"];
			$iprops[$prop_fields["CODE"]]["ID"] = $prop_fields["ID"];
			$iprops[$prop_fields["CODE"]]["ENUM"] = false;
			$iprops[$prop_fields["CODE"]]["MULTIPLE"] = false;
			$iprops[$prop_fields["CODE"]]["TEXTAREA"] = false;
			$iprops[$prop_fields["CODE"]]["NUM"] = false;
			
			if($prop_fields["PROPERTY_TYPE"] == "N"){
				$iprops[$prop_fields["CODE"]]["NUM"] = true;
			}

			if($prop_fields["PROPERTY_TYPE"] == "L"){
				$property_enums = CIBlockPropertyEnum::GetList(Array("SORT"=>"ASC"), Array("IBLOCK_ID"=>MAIN_IBLOCK, "CODE"=>$prop_fields["CODE"]));
				$ji = 0;
				while($enum_fields = $property_enums->Fetch())
				{
					$iprops[$prop_fields["CODE"]]["ENUM"]["VALUES"][$ji]["ID"] = $enum_fields["ID"];
					$iprops[$prop_fields["CODE"]]["ENUM"]["VALUES"][$ji]["VALUE"] = $enum_fields["VALUE"];
					$ji++;
				}
			}

			if($prop_fields["MULTIPLE"] == "Y"){
				$iprops[$prop_fields["CODE"]]["MULTIPLE"] = true;
			}
			
			if(isset($prop_fields["DEFAULT_VALUE"]["TYPE"])){
				$iprops[$prop_fields["CODE"]]["TEXTAREA"] = $prop_fields["DEFAULT_VALUE"]["TYPE"];
			}
		}
		return $iprops;
	}

	static function getSectionForGood($values, $trees , $flag){
		$section_ids = array();
		if($flag == 1){
			if($values["section"]["SECTION"]){
				foreach ($trees["section"] as $key => $section) {
					if($section["NAME"] == $values["section"]["SECTION"]){
						foreach($values["section"]["SUB_SECTION"] as $kese => $sec){
							foreach ($section["CHILD"] as $k => $sub_section) {
								if($sub_section["NAME"] == $sec){
									$section_ids[] = $sub_section["ID"];
								}
							}
						}
					}
				}
			}

			foreach ($values["seria"] as $ke => $val) {
				foreach ($trees["seria"] as $key => $seria) {
					foreach ($seria["CHILD"] as $k => $pod_seria) {
						if($pod_seria["NAME"] == $val){
							$section_ids[] = $pod_seria["ID"];
						}
					}
				}
			}
			return $section_ids;
		}
	}


	static function getTreeSection(){
		$arFilter = array(		
		    'ACTIVE' => 'Y',
		    'IBLOCK_ID' => MAIN_IBLOCK,
		    'GLOBAL_ACTIVE'=>'Y',
		);

		$arSelect = array('IBLOCK_ID','ID','NAME','DEPTH_LEVEL','IBLOCK_SECTION_ID');
		$arOrder = array('DEPTH_LEVEL'=>'ASC','SORT'=>'ASC');
		$rsSections = CIBlockSection::GetList($arOrder, $arFilter, false, $arSelect);
		
		$sectionLinc = array();
		
		$arResult['ROOT'] = array();
		
		$sectionLinc[0] = &$arResult['ROOT'];
		
		while($arSection = $rsSections->GetNext()) {
		    $sectionLinc[intval($arSection['IBLOCK_SECTION_ID'])]['CHILD'][$arSection['ID']] = $arSection;
		    $sectionLinc[$arSection['ID']] = &$sectionLinc[intval($arSection['IBLOCK_SECTION_ID'])]['CHILD'][$arSection['ID']];
		}
		$result = $sectionLinc; 
		unset($sectionLinc);
		return $result;
	}

}
?>
