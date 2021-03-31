<?
IncludeModuleLangFile(__FILE__);
if ($APPLICATION->GetGroupRight("rr.good") >= "D")
{
    $aMenu = array(
        "parent_menu" => "global_menu_store",
        "section" => GetMessage("MODULE_TITLE"),
        "sort" => 10,
        "text" => "Выгрузка товаров",
        "title" => '',
        "icon" => "",
        "url" => "rr.good.php?lang=".LANGUAGE_ID,
        "more_url" => array(),
        "items" => array(
            
        )
    );   
    return $aMenu;
}

?>