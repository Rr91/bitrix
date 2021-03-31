<?
Class CRrGo
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

	static function uploadGo($FILE_NAME = false){

		if(!$FILE_NAME){
			$FILE_NAME = "/parser/price.csv";
		}
		
		$noProp = array(
			"PRICE",
		);

		$res_pack = self::parserCSV($FILE_NAME, $flag);
		
		$count_false = 0;
		$count_succes = 0;
		$ii = 0;
		if(!empty($res_pack)){
			foreach($res_pack as $key=>$item) {
				$ii++;
				$property_name = "PROPERTY_IDENTIFICATOR";
				$dbRes = CIBlockElement::GetList(array(), array("IBLOCK_ID" => MAIN_IBLOCK, "=PROPERTY_IDENTIFICATOR" => $key), false, false, Array("IBLOCK_ID", "ID", "PROPERTY_IDENTIFICATOR"));
				
				$good = $dbRes->Fetch();
				$PID = $good["ID"];
				// обновление
				if($PID){
					foreach ($item as $k => $field) {
						if(!in_array($k, $noProp)){
							$PROPERTY_CODE = $k;  // код свойства
							$PROPERTY_VALUE = $field;  // значение свойства
							// Установим новое значение для данного свойства данного элемента
							$dbr = CIBlockElement::GetList(array(), array("=ID"=>$PID), false, false, array("ID", "IBLOCK_ID"));
							if ($dbr_arr = $dbr->Fetch())
							{
							  $IBLOCK_ID = $dbr_arr["IBLOCK_ID"];
							  CIBlockElement::SetPropertyValues($PID, $IBLOCK_ID, $PROPERTY_VALUE, $PROPERTY_CODE);
							}
						}
					}
					$PRODUCT_ID = $PID;
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
					$count_succes++;
				}
				else{
					$count_false++;
				}
			}
		}
		return array("count_succes" => $count_succes, "count_false" => $count_false);
	}

	static function parserCSV($FILE_NAME, $flag){
		// иницилизация библиотеки
		require_once ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/csv_data.php");
		$arry = array();
		$csvFile = new CCSVData('R', true);
		$csvFile->LoadFile($_SERVER["DOCUMENT_ROOT"].$FILE_NAME);
		$csvFile->SetDelimiter(';');
		$arRows = array();

		while ($arRes = $csvFile->Fetch()) {
		   	$arRow = array();
		   	$identificator = self::parseUT(trim(iconv("CP1251", "UTF8", $arRes[0])));
		   	$new_price = doubleval(str_replace(",", ".", str_replace(" ", "", iconv("CP1251", "UTF8", $arRes[1]))));
		   	$available = 1;
		   	$va = trim(iconv("CP1251", "UTF8", $arRes[2]));
		   	if($va == "под заказ" || $va == "0"){
		   		$available = 0;
		   	}
		   	$valgar = trim(iconv("CP1251", "UTF8", $arRes[3]));
	   		$garantia = "";
	   		if($valgar == 0){
	   			$garantia = "";
	   		}
	   		else{
	   			$garantia = strtolower($valgar);
	   		}
	   		$arRow["PRICE"] = $new_price;
	   		$arRow["AVAILABLE"] = $available;
	   		$arRow["GARANTIA"] = $garantia;
	   		$arRows[$identificator] = $arRow;
		}
		return $arRows; 
	}

	public static function parseUT($idn){
		$newidn = str_replace("“’", "УТ", $idn);
		return $newidn;
	}

}
?>
