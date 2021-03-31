<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/rr.go/include.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/rr.go/prolog.php");
IncludeModuleLangFile(__FILE__);
set_time_limit(0);
CModule::IncludeModule("iblock");
$IBLOCK_ID = 6;
$IBLOCK_TYPE_ID = "catalog";
$CODE_PROP = "ARTNUMBER";
$CODE_WHERE = $_REQUEST['IMG_CODE_WHERE'];
$strError = "";
$io = CBXVirtualIo::GetInstance();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["backButton"]) && strlen($_POST["backButton"]) > 0) {
	$DATA_FILE_NAME = "";
	if (strlen($_REQUEST['URL_DATA_FILE']) > 0)
	{
		$URL_DATA_FILE = $_REQUEST['URL_DATA_FILE'];
		$URL_DATA_FILE = trim(str_replace("\\", "/", trim($URL_DATA_FILE)) , "/");
		$FILE_NAME = rel2abs($_SERVER["DOCUMENT_ROOT"], "/".$URL_DATA_FILE);
		if (
			(strlen($FILE_NAME) > 1)
			&& ($FILE_NAME === "/".$URL_DATA_FILE)
			&& $io->FileExists($_SERVER["DOCUMENT_ROOT"].$FILE_NAME)
			&& ($APPLICATION->GetFileAccessPermission($FILE_NAME) >= "W")
		)
		{
			$DATA_FILE_NAME = $FILE_NAME;
		}
	}
	if (strlen($DATA_FILE_NAME) <= 0)
		$strError.= GetMessage("GOART_ADM_IMP_NO_DATA_FILE")."<br>";
	/*if (!CIBlockRights::UserHasRightTo($IBLOCK_ID, $IBLOCK_ID, "element_edit_any_wf_status"))
		$strError.= GetMessage("GOART_ADM_IMP_NO_IBLOCK")."<br>";*/
	if ($IBLOCK_ID=='') 
		$strError.= GetMessage("GOART_NO_IBLOCK_ID")."<br>";
	if ($CODE_PROP=='') 
		$strError.= GetMessage("GOART_NO_CODE_PROP")."<br>";
	if ($IBLOCK_ID!='' && $CODE_PROP!='') {
		$res = CIBlockProperty::GetByID($CODE_PROP, $IBLOCK_ID, $IBLOCK_TYPE_ID);
		if(!$ar_res = $res->GetNext())
			$strError.= GetMessage("GOART_NO_CODE_PROP_IBLOCK")."<br>";
	}
	if(strlen($strError) <= 0) {
		//  тут парсинг xsl файла
		$res_set = CRrGo::uploadGo($FILE_NAME);		
	}
}

$APPLICATION->SetTitle(GetMessage("PAGE_TITLE"));

$aTabs = array();
$aTabs[] = array("DIV" => "edit", "TAB" => GetMessage('TITLE'), "ICON"=>"iblock_type", "TITLE"=>GetMessage('TITLE'));
    
$tabControl = new CAdminTabControl("tabControl", $aTabs);

$bVarsFromForm = false;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

CJSCore::Init(array("jquery"));

$aContext = array(
    array(
        "ICON" => "btn_list",
        "TEXT"=>GetMessage("MAIN_ADMIN_MENU_LIST"),
        "LINK"=>$back_url,
        "TITLE"=>GetMessage("MAIN_ADMIN_MENU_LIST")
    ),
);

$context = new CAdminContextMenu($aContext);
$context->Show();
CAdminMessage::ShowMessage($strError);
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["backButton"]) && strlen($_POST["backButton"]) > 0 && $strError=='') {
		$str_success_detail = str_replace("#COL_SUCCESS#",$res_set["count_succes"],GetMessage("GOART_ADM_EXP_LINES_EXPORTED"));
		$str_success_detail = str_replace("#COL_ERROR#",$res_set["count_false"],$str_success_detail);
		echo CAdminMessage::ShowMessage(array(
			"TYPE" => "PROGRESS",
			"MESSAGE" => GetMessage("GOART_ADM_EXP_SUCCESS"),
			"DETAILS" => $str_success_detail,
			"HTML" => true,
		));
}
?>
<form method="POST" action="<?echo $sDocPath ?>?lang=<?echo LANG ?>" ENCTYPE="multipart/form-data" name="dataload" id="dataload">
	<?$aTabs = array(
		array(
			"DIV" => "edit1",
			"TAB" => GetMessage("GOART_ADM_IMP_TAB1") ,
			"ICON" => "iblock",
			"TITLE" => GetMessage("GOART_ADM_IMP_TAB1_ALT"),
		) ,
	);
	$tabControl = new CAdminTabControl("tabControl", $aTabs, false, true);
	$tabControl->Begin();
	$tabControl->BeginNextTab();
	?>
	<tr>
		<td width="40%"><?echo GetMessage("GOART_ADM_IMP_DATA_FILE"); ?></td>
		<td width="60%">
			<input type="text" name="URL_DATA_FILE" value="<?echo htmlspecialcharsbx($URL_DATA_FILE); ?>" size="30">
			<input type="button" value="<?echo GetMessage("GOART_ADM_IMP_OPEN"); ?>" OnClick="BtnClick()">
			<?CAdminFileDialog::ShowScript(array(
					"event" => "BtnClick",
					"arResultDest" => array(
						"FORM_NAME" => "dataload",
						"FORM_ELEMENT_NAME" => "URL_DATA_FILE",
					),
					"arPath" => array(
						"SITE" => SITE_ID,
						"PATH" => "/".COption::GetOptionString("main", "upload_dir", "upload"),
					),
					"select" => 'F', // F - file only, D - folder only
					"operation" => 'O', // O - open, S - save
					"showUploadTab" => true,
					"showAddToMenuTab" => false,
					"fileFilter" => 'csv',
					"allowAllFiles" => true,
					"SaveConfig" => true,
				));
			?>
		</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input type="submit" name="backButton" value="<?echo GetMessage("GOART_ADM_IMG_LOAD"); ?>" class="adm-btn-save"></td>
	</tr>
</form>
<?
$tabControl->End();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>