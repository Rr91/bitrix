<?
IncludeModuleLangFile(__FILE__);
if ($APPLICATION->GetGroupRight("rr.go") >= "D")
{
    $aMenu = array(
        "parent_menu" => "global_menu_store",
        "section" => GetMessage("MODULE_TITLE"),
        "sort" => 10,
        "text" => "Обновление цен",
        "title" => '',
        "icon" => "",
        "url" => "rr.go.php?lang=".LANGUAGE_ID,
        "more_url" => array(),
        "items" => array(
            
        )
    );   
    return $aMenu;
}

?>