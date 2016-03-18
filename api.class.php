<?php
/**
 * @license MIT
 * @version siehe define API_VERSION
 */
 
/* Konfig: */
define(API_KEY_LEN, "10");
define(API_VERSION, "1.00");
define(API_KEY_DB, "/var/www/mediadb.ivaya.de/Key.sqlite");
define(API_Rights, "/var/www/mediadb.ivaya.de/Rights.json");

if (!isset($_GET['Pretty'])) {
	error_reporting(0); // PHP Fehler nur ausgeben wenn &Pretty gesetzt ist
}

class MediaDBAPI{
	private $API_KEY;
	private $API_Rechte;
	private $DB_Server;
	private $DB_Username;
	private $DB_Passwort;
	private $DB_Database;
	private $Statistik = array("QueryCounter" => 0);
	private $Querys;
	private $Tabellen = array("Filme","Serien", "Staffeln", "Episoden");
	public  $SpaltenFilme = array("name", "tagline",  "imdbID", "3d","titelOriginal","titelDeutsch", "collection", "year", "fsk", "rating",  "youtube", "resolution", "duration", "size", "hdd", "added", "lastView", "lastUpdate",
           "Genre", "Schauspieler", "views", "checked", "width", "height", "totalbitrate", "vcodec","acodecger", "abitrateger", "channelsger", "acodeceng", "abitrateeng", "channelseng", "comment",
            "md5" , "summary");
    public  $SpaltenEpisoden = array("episodenumber", "season_nr", "series_nr", "name", "source", "duration", "size", "hdd", "lastView", "added", "views", "checked",
            "width", "height", "totalbitrate", "vcodec", "acodecger", "abitrateger", "channelsger", "acodeceng", "abitrateeng", "channelseng", "comment", "md5");
	public $StatistikViews = array("watchStatistic", "belegterSpeicher", "freierSpeicher", "laufzeitGesehen", "prozentualGesehen", "prozentualDefekt",
									"defekteFilme", "defekteEpisoden", "DBstatistik", "GenreFilmanzahl", "SchauspielerFilmanzahl", "lastMD5Check", "Collections");
	private $FilterEinfach = array("imdbID" => "imdbID", "acodecger" =>  "acodecger", "acodeceng" => "acodeceng", "vcodec" => "vcodec",
									"resolution" => "resolution", "channelsger" => "channelsger", "channelseng" => "channelseng", "hdd" => "hdd");
	private $FilterKomplex = array("Jahr" => "year", "Groesse" => "size", "Laufzeit" => "duration", "Hinzugefuegt" => "added", "Gesehen" => "lastView", 
									"Gesehenzaehler" => "views", "FSK"=>"fsk");
	/* Speicher für Rechte Arrays*/
	private $SpaltenFil = array();
	private $SpaltenEpi = array();
	private $StatiViews = array();
	private $WebAppSite = array(); //TODO Rechte für WebApp Seiten vergeben
	public  $Update = FALSE;
	private $DebugOutput = FALSE;
	public  $webapp = FALSE;
	public $DB_Objekt;

	function APIinit($KEY) {
		if(isset($KEY) AND strlen($KEY) == API_KEY_LEN){
			if ($db = new SQLite3(API_KEY_DB)) { 			
				$result = $db->query('select * from Keys WHERE Schuessel = "'.$KEY.'"');
				$entry = $result->fetchArray();
				if($entry['Schuessel'] != null){
					$this->DB_Server = $entry['Server'];
					$this->DB_Username = $entry['Username'];
					$this->DB_Passwort = $entry['Passwort'];
					$this->DB_Database = $entry['Database'];
					$this->API_Rechte  = $entry['Rechte'];
					$this->API_KEY     = $entry['Schuessel'];
					$this->DB_Objekt = new mysqli($this->DB_Server, $this->DB_Username,$this->DB_Passwort, $this->DB_Database);
					$this->SetKeyRights();
					if($this->DB_Objekt->connect_error != ""){
						$ret = false;
					}else{
						$this->query("SET NAMES 'utf8'");
						$ret = true;			}
				}else{				$ret = false;			}
			}else{					$ret = false;			}
			$db->close();
		}else {						$ret = false;			}
		return  $ret;
	}
	private function SetKeyRights(){
		if(file_exists(API_Rights)){
			$Data = json_decode(file_get_contents(API_Rights), true);
			foreach ($Data as $value) {
				if(($value["from"] >= $this->API_Rechte) AND ($value["to"] <= $this->API_Rechte)){
					foreach ($value["SpaltenFil"] as $value2){
						array_push($this->SpaltenFil,$value2);
					}
					foreach ($value["SpaltenEpi"] as $value2){
						array_push($this->SpaltenEpi,$value2);
					}
					foreach ($value["StatiViews"] as $value2){
						array_push($this->StatiViews,$value2);
					}
					
					$this->Update = ($value["Update"] OR $this->Update);
					$this->DebugOutput = ($value["DebugO"] OR $this->Update);
					$this->webapp = ($value["webapp"] OR $this->webapp);
				}
			}
		}
		else{	return false;}
		//Dafür den Array mit allein Spalten für die Reinfolge verwenden
		$tempFilme= array();
		foreach ($this->SpaltenFilme as $value) {
			if(in_array($value, $this->SpaltenFil)){
				array_push($tempFilme, $value);                 
			}
		}
		$this->SpaltenFil = $tempFilme;
		$tempEpisoden= array();
		foreach ($this->SpaltenEpisoden as $value) {
			if(in_array($value, $this->SpaltenEpi)){
				array_push($tempEpisoden, $value);
			}
		}
		$this->SpaltenEpi = $tempEpisoden;
	}
	public function API_GetKeyRights($GET_arr, $POST_arr =""){
		$array["SpaltenFilme"] = $this->SpaltenFil;
		$array["SpaltenEpisoden"] = $this->SpaltenEpi;
		//$array["FilterEinfach"] = $this->FilterEinf;
		//$array["FilterKomplex"] = $this->FilterKomp;
		//$array["FilterAndere"] = $this->FilterAnde;
		$array["StatistikViews"] = $this->StatiViews;
		$array["Update"] = $this->Update;
		return $array;
	}
	public function API_GetRightsLevel(){
		return $this->API_Rechte;
	}
	public function API_GetDataList($GET_arr, $POST_arr =""){
		if(!isset($GET_arr['Tabelle']) OR ($GET_arr['Tabelle'] == "") OR !isset($GET_arr['Spalten']) OR ($GET_arr['Spalten'] == "")) {
			return $this->error(1004,"Erwarteter Parameter: Tabelle");
		}
		else{
			if(in_array($GET_arr['Tabelle'], $this->Tabellen)){
				$Array = array_merge(array_intersect_key($GET_arr, $this->FilterKomplex),array_intersect_key($GET_arr, $this->FilterEinfach));
				// Wenn irgenein filter gesetzt ist  Filterung nur für Tabelle Filme!
				if(	(isset($GET_arr['GenreID']) OR isset($GET_arr['Genre']) 		OR isset($GET_arr['SchauspielerID'])	OR isset($GET_arr['Schauspieler'])	OR 
					 isset($GET_arr['Suche'])   OR isset($GET_arr['SchauspielerSuche']) OR isset($GET_arr['Englisch']) 		OR isset($GET_arr['Deutsch'])  		OR 
					  isset($GET_arr['3d']) OR isset($GET_arr['checked']) OR isset($GET_arr['Youtube']) OR (count($Array)>0)) AND $GET_arr['Tabelle'] == "Filme" ){
					$first = true;
					$imdbArrToCompar = 0;
					$Where = "WHERE ";
					// istgleich Filter:
					foreach ($this->FilterEinfach as $key => $value) {
						if(isset($GET_arr[$key]) ){ 
							$Where .= ($first?"":" AND ").$value.' = "'.$GET_arr[$key].'"';
							$first = false;
						}
					}
					// größergleich oder kleinergleich Filter:
					foreach ($this->FilterKomplex as $key => $value) {
						if(isset($GET_arr[$key]) ){
							if ((substr($GET_arr[$key],0,1) == "<") OR (substr($GET_arr[$key],0,1) == ">") OR !strpos($GET_arr[$key], ",")) {
								// Kleiner oder Größer und kein Komma!
								$Number = str_replace(">", "", $GET_arr[$key]);
								$Number = str_replace("<", "", $Number);
								$Where .= ($first?"":" AND ").$value.' '.((substr($GET_arr[$key],0,1) == ">")?">":"").((substr($GET_arr[$key],0,1) == "<")?"<":"").'="'.$Number.'"';
								$first = false;
							}else if(strpos($GET_arr[$key], ",")>0){
								// Bereich angegeben
								$NumberArr = explode(",", $GET_arr[$key]);
								$Where .= ($first?"":" AND ").$value.' >="'.$NumberArr[0].'"';
								$first = false;
								$Where .= ($first?"":" AND ").$value.' <="'.$NumberArr[1].'"';
							}
						}
					}
					// Sonstige Filter:
					if(isset($GET_arr['3d']) ){
						$Where .= ($first?"":" AND ").'3d '.($GET_arr['3d']?"!=":"=").'""';
						$first = false;
					}
					if(isset($GET_arr['Englisch']) ){
						$Where .= ($first?"":" AND ").'acodeceng'.($GET_arr['Englisch']?" IS NOT ":" IS ").'null';
						$first = false;
					}
					if(isset($GET_arr['Deutsch']) ){
						$Where .= ($first?"":" AND ").'acodecger'.($GET_arr['Deutsch']?" IS NOT ":" IS ").'null';
						$first = false;
					}
					if(isset($GET_arr['checked']) ){
						if($GET_arr['checked'] == "NULL"){		$Where .= ($first?"":" AND ").'checked IS NULL';			}
						else{									$Where .= ($first?"":" AND ").'checked = "'.$GET_arr['checked'].'"';					}
						$first = false;
					}
					if(isset($GET_arr["Youtube"])){
						if($GET_arr['Youtube'] == "DE"){		$Where .= ($first?"":" AND ").'youtube LIKE "%DE%"';		}
						else if($GET_arr['Youtube'] == "EN"){	$Where .= ($first?"":" AND ").'youtube LIKE "%EN%"';		}
						else{									$Where .= ($first?"":" AND ").'youtube '.($GET_arr['Youtube']=="1"?"!=":"=").'""';		}
					}
					if(isset($GET_arr['Suche'])){
						$SucheWort = $GET_arr['Suche'];
						if(in_array("titelOriginal", $this->SpaltenFilme)){
							$SucheAdd .=" OR `titelOriginal` LIKE '%".$GET_arr['Suche']."%'";
						}
						if(in_array("titelDeutsch", $this->SpaltenFilme)){
							$SucheAdd .=" OR `titelDeutsch` LIKE '%".$GET_arr['Suche']."%'";
						}
						$Suche = "	(`name` LIKE '%".$SucheWort."%' OR `md5` LIKE '%".$SucheWort."%' OR `comment` LIKE '%".$SucheWort."%' ".$SucheAdd.")";
						//$this->DebugOut($Suche);
						$Where .= (($first?"":" AND ").$Suche);
						$first = false;
					}
					// In Filter:
					// Attribute von Filmen die nur über eine M:N Verbindung vorhanden sind:
					if(isset($GET_arr['GenreID']) OR isset($GET_arr['Genre']) OR isset($GET_arr['SchauspielerID']) OR isset($GET_arr['Schauspieler']) OR isset($GET_arr['SchauspielerSuche'])){
						if(isset($GET_arr['GenreID'])){
							foreach (explode(",", $GET_arr['GenreID']) as $value) {
								$Querys[] = 'SELECT group_concat(fg.imdbID) AS imdbIDs  FROM FilmGenre AS fg JOIN Genre AS g ON  fg.genreID = g.genreID WHERE g.genreID = "'.$value.'" ';
							}
						}
						if(isset($GET_arr['Genre'])){
							foreach (explode(",", $GET_arr['Genre']) as $value) {
								$Querys[] = 'SELECT group_concat(fg.imdbID) AS imdbIDs  FROM FilmGenre AS fg JOIN Genre AS g ON  fg.genreID = g.genreID WHERE g.engname LIKE "%'.$value.'%" ';
							}
						}
						if(isset($GET_arr['SchauspielerID'])){
							foreach (explode(",", $GET_arr['SchauspielerID']) as $value) {
								$Querys[] = 'SELECT group_concat(fs.imdbID) AS imdbIDs  FROM FilmSchauspieler AS fs JOIN Schauspieler AS s ON  fs.schauspielerID = s.schauspielerID WHERE s.schauspielerID = "'.$value.'" ';
							}
						}	
						if(isset($GET_arr['Schauspieler'])){
							foreach (explode(",", $GET_arr['Schauspieler']) as $value) {
								$Querys[] = 'SELECT group_concat(fs.imdbID) AS imdbIDs  FROM FilmSchauspieler AS fs JOIN Schauspieler AS s ON  fs.schauspielerID = s.schauspielerID WHERE s.name LIKE "%'.$value.'%" ';
							}
						}
						// Alle Querys Ausführen und Ergebnis in einen Großen Arry Speichern
						foreach ($Querys as $Query) {
							$result = $this->query($Query);
							if($this->DB_Objekt->error != ""){		return $this->error(1005, $this->DB_Objekt->error);}
							$imdbIDString = $result->fetch_array();
							$imdbArr[$imdbArrToCompar++] = explode(",", $imdbIDString["imdbIDs"]);
						}
						switch ($imdbArrToCompar){ // Wenn es nur eine imDB Array Liste gibt...
							case 1:		$imdbStr = implode(",",$imdbArr[0]);																															break;
							case 2:		$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1]));																								break;
							case 3:		$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1],$imdbArr[2]));																					break;
							case 4:		$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1],$imdbArr[2],$imdbArr[3]));																		break;
							case 5:		$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1],$imdbArr[2],$imdbArr[3],$imdbArr[4]));															break;
							case 6:		$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1],$imdbArr[2],$imdbArr[3],$imdbArr[4],$imdbArr[5]));												break;
							case 7:		$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1],$imdbArr[2],$imdbArr[3],$imdbArr[4],$imdbArr[5],$imdbArr[6]));									break;
							case 8:		$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1],$imdbArr[2],$imdbArr[3],$imdbArr[4],$imdbArr[5],$imdbArr[6],$imdbArr[7]));						break;
							case 9:		$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1],$imdbArr[2],$imdbArr[3],$imdbArr[4],$imdbArr[5],$imdbArr[6],$imdbArr[7],$imdbArr[8]));			break;
							case 10:	$imdbStr .=implode(",",array_intersect($imdbArr[0],$imdbArr[1],$imdbArr[2],$imdbArr[3],$imdbArr[4],$imdbArr[5],$imdbArr[6],$imdbArr[7],$imdbArr[8],$imdbArr[9]));		break;
						}
						if ($imdbArrToCompar > 0) {
							$Where .= (strlen($imdbStr)>0?($first?"":" AND ").str_replace(",)", ")", 'imdbID in ('.$imdbStr.')'): ($first?" 0  ":""));
							$first = false;
						}
					}
				}
				// Wenn irgendein filter gesetzt ist Filterung nur für Tabllen Staffeln und Episoden!!
				if(	(isset($GET_arr['series_nr'])	OR isset($GET_arr['season_nr']) OR isset($GET_arr['episodenumber']) OR isset($GET_arr['Checked'])	)
						AND (($GET_arr['Tabelle'] == "Staffeln") OR (($GET_arr['Tabelle'] == "Episoden"))) ){ // Filterung nur für Tablle Episoden!!
					
					$first = true;
					$Where = "WHERE ";		
					if(isset($GET_arr['series_nr']) ){
						$Where .= ($first?"":" AND ").'series_nr = "'.$GET_arr['series_nr'].'"';
						$first = false;
					}
					if(isset($GET_arr['season_nr']) ){
						$Where .= ($first?"":" AND ").'season_nr = "'.$GET_arr['season_nr'].'"';
						$first = false;
					}
					if(isset($GET_arr['episodenumber']) ){
						$Where .= ($first?"":" AND ").'episodenumber = "'.$GET_arr['episodenumber'].'"';
						$first = false;
					}
				}
				// Sonderlösung für nur imdbID (wird für den Coverdownload benötigt)
				if($GET_arr['Spalten'] == "imdbID"){
					$Group = "GROUP BY imdbID";
				}
				if($GET_arr['Spalten'] == "*"){
					return $this->error(1007,"Keine Rechte für Spalten= *");
				}
				// Überprüfen ob Schauspieler oder Genre gefordert wird
				$SpaltenArr       = explode(",", $GET_arr['Spalten']);
				$ListGenre        = (in_array("Genre", $SpaltenArr)?true:false);
				$ListSchauspieler = (in_array("Schauspieler", $SpaltenArr)?true:false);
				$Statistik        = (in_array("Statistik", $SpaltenArr)?true:false);
				//Spalten entfernen worauf keine Rechte sind...
				$first = true;
				$Spalten = "";
				if($GET_arr["Tabelle"] == "Episoden"){
					foreach ($SpaltenArr as $value) {
						if(!(in_array($value, $this->SpaltenEpi)==1)){
							unset($SpaltenArr[$value]);
						}else{
							$Spalten .= ($first?"":",").$value;
							$first = false;
						}
					}
				}else if( $GET_arr["Tabelle"] == "Filme"){
					foreach ($SpaltenArr as $value) {
						if(!(in_array($value, $this->SpaltenFil)==1)){
							unset($SpaltenArr[$value]);
						}else{
							$Spalten .= ($first?"":",").$value;
							$first = false;
						}
					}
				}
				else {
					$Spalten = $GET_arr['Spalten'];
				}
				// Lösche Schauspieler und Genre aus der Select Anweisung
				$Spalten = str_replace("Genre,", "", $Spalten);
				$Spalten = str_replace(",Genre", "", $Spalten);
				$Spalten = str_replace("Genre", "", $Spalten);
				$Spalten = str_replace("Schauspieler,", "", $Spalten);
				$Spalten = str_replace(",Schauspieler", "", $Spalten);
				$Spalten = str_replace("Schauspieler", "", $Spalten);
				$Spalten = str_replace("Statistik,", "", $Spalten);
				$Spalten = str_replace(",Statistik", "", $Spalten);
				$Spalten = str_replace("Statistik", "", $Spalten);
				// wenn Genre oder Schauspieler gefordert sind, aber die imdbID nicht in der Spaltenliste ist...
				$NoimdbID = false;
				if((($ListGenre OR $ListSchauspieler) AND !in_array("imdbID", $SpaltenArr) )){
					$Select = "imdbID,".$Spalten;
					$NoimdbID = true;
				}else{
					$Select = $Spalten;
				}
				
				$Noseries_nr = false;
				if((($Statistik) AND !in_array("series_nr", $SpaltenArr) )){
					$Select = "series_nr,".$Spalten;
					$NoimdbID = true;
				}else{
					$Select = $Spalten;
				}
				// Zusammenbauen des Auszuführenden Querys:
				$Query= "SELECT ".$Select." FROM ".$GET_arr['Tabelle']." "
						.(strlen($Where)<=6?"":$Where)
						.$this->iset($GET_arr['Sortierung'], " ", " ORDER BY ", " ")
						.$this->iset($GET_arr['Anzahl'],"",  " Limit "," ")
						." ".$Group.";";
				
				$entrys = $this->query($Query);
				if($this->DB_Objekt->error != ""){		return $this->error(1005, $this->DB_Objekt->error);}
				$TitelArray = array();
				$finfo = $entrys->fetch_fields();
				foreach ($finfo as $val) {
					array_push($TitelArray, $val->name);
				}
				$entryArray = array();
				while($entry = $entrys->fetch_array()){
					$tempArray = array();
					foreach ($finfo as $val) {
						if(!(($val->name == "imdbID") AND $NoimdbID)){
							$tempArray[$val->name] = ($entry[$val->name] == NULL?"":$entry[$val->name]);
						}
					}
					// Genre oder Schauspielerblock hinzufügen wenn es gewünscht ist
					if($ListGenre){
						$Query = 'SELECT group_concat(g.gername ORDER BY g.gername) AS Genre  FROM FilmGenre AS fg JOIN Genre AS g ON  fg.genreID = g.genreID WHERE fg.imdbID="'.$entry["imdbID"].'"';
						$Genre = $this->query($Query);
						if($this->DB_Objekt->error != ""){		return $this->error(1005, $this->DB_Objekt->error);}
						$GenreArr = $Genre->fetch_array();
						$tempArray["Genre"] =  explode(",", ($GenreArr["Genre"]));
					}
					if($ListSchauspieler){
						//TODO Rolle einbaue
						/*
						$Query = 'SELECT group_concat(s.name ORDER BY s.name) AS Schauspieler '.
						'FROM FilmSchauspieler AS fs JOIN Schauspieler AS s ON  fs.schauspielerID = s.schauspielerID WHERE fs.imdbID="'.$entry["imdbID"].'"';
						$Schauspieler = $this->query($Query);
						if($this->DB_Objekt->error != ""){		return $this->error(1005, $this->DB_Objekt->error);}
						$SchauspielerArr = $Schauspieler->fetch_array();
						$tempArray["Schauspieler"] =  explode(",", ($SchauspielerArr["Schauspieler"]));
						*/
						$Query = 'SELECT s.name AS Schauspieler, fs.role AS Rolle  '.
								'FROM FilmSchauspieler AS fs JOIN Schauspieler AS s ON  fs.schauspielerID = s.schauspielerID WHERE fs.imdbID="'.$entry["imdbID"].'"';
						
						$Schauspieler = $this->query($Query);
						if($this->DB_Objekt->error != ""){		return $this->error(1005, $this->DB_Objekt->error);}
						while($entry = $Schauspieler->fetch_array()){
							$tempArray["Schauspieler"][$entry["Schauspieler"]] = $entry["Rolle"];
						}
								
						
						
						
					}
					if ($Statistik == true) {
						if (($GET_arr['Tabelle'] == "Staffeln")) { //TODO hier ist auch was geändert aber noch nicht getestet!!
							$Query = 'SELECT '.
									(in_array("checked", $this->SpaltenEpi)==1?'avg(e.checked) AS Checked,':'').' '.
									(in_array("views", $this->SpaltenEpi)==1?'avg(e.views) AS Views,':'').' '.
									(in_array("size", $this->SpaltenEpi)==1?'SUM(e.size) AS Size,':'').' SUM(e.duration) AS Duration, COUNT(e.name) AS Count '.
									 'FROM Staffeln AS s JOIN Episoden AS e ON s.season_nr = e.season_nr '.
									 'WHERE s.season_nr = "'.$entry["season_nr"].'"';
						}else if (($GET_arr['Tabelle'] == "Serien")){
							$Query = 'SELECT '.(in_array("checked", $this->SpaltenEpi)==1?'avg(e.checked) AS Checked,':'').' '.
											(in_array("views", $this->SpaltenEpi)==1?'avg(e.views) AS Views,':'').' '.
											(in_array("size", $this->SpaltenEpi)==1?'SUM(e.size) AS Size,':'').' SUM(e.duration) AS Duration, COUNT(e.name) AS Count '.
									'FROM Serien AS se JOIN Staffeln AS st ON se.series_nr=st.series_nr JOIN Episoden AS e ON st.season_nr = e.season_nr '.
									'WHERE se.series_nr = '.$entry["series_nr"];
						}
						$result = $this->query($Query);
						if($this->DB_Objekt->error != ""){		return $this->error(1005, $this->DB_Objekt->error);}
						$resultArr = $result->fetch_array();
						if(in_array("checked", $this->SpaltenEpi)==1){
							$tempArray["Checked"] = ($resultArr["Checked"]== null?"":$resultArr["Checked"]);
						}
						if(in_array("views", $this->SpaltenEpi)==1){
							$tempArray["Views"] = ($resultArr["Views"] == null?0:$resultArr["Views"]);
						}
						if(in_array("size", $this->SpaltenEpi)==1){
							$tempArray["Size"] = ($resultArr["Size"] == null?0:$resultArr["Size"]);
						}
						$tempArray["Duration"] = ($resultArr["Duration"] == null?0:$resultArr["Duration"]);
						$tempArray["Count"] = ($resultArr["Count"] == null?0:$resultArr["Count"]);
					}
					array_push($entryArray,$tempArray);
				}
				return array("Spalten"=>$TitelArray, "Data" =>$entryArray);
			}
			else{
				return $this->error(1007, "Keine Rechte auf die Tabelle!");
			}
		}
	}
	public function API_SetData($GET_arr, $POST_arr =""){
		if( ($this->Update)){  // Wenn das Recht auf Update gesetzt ist...
			if(isset($GET_arr["Tabelle"])){
				if((isset($POST_arr["imdbID"]) AND isset($POST_arr["3d"]) AND ($GET_arr["Tabelle"] == "Filme")) OR 
						(isset($POST_arr["season_nr"]) AND isset($POST_arr["episodenumber"]) AND ($GET_arr["Tabelle"] == "Episoden"))){
					if(($GET_arr["Tabelle"] == "Filme")){
						$Where = "WHERE imdbID = '".$POST_arr["imdbID"]."' AND `3d` = '".$POST_arr["3d"]."'";
					}elseif(($GET_arr["Tabelle"] == "Episoden")){
						$Where = "WHERE season_nr = '".$POST_arr["season_nr"]."' AND `episodenumber` = '".$POST_arr["episodenumber"]."'";
					}
					$Set = "SET ";
					$first = true;
					if(isset($POST_arr["Gesehen"])){
						$Set .= ($first?"":" , ")."views = views+".$POST_arr["Gesehen"];
						$first = false;
					}
					if(isset($POST_arr["checked"])){
						$Set .= ($first?"":" , ")."checked = '".$POST_arr["checked"]."'";
						$first = false;
					}
					if(isset($POST_arr["comment"])){
						$Set .= ($first?"":" , ")."comment = '".$POST_arr["comment"]."'";
						$first = false;
					}
					if($first){// Fehler...
						return $this->error(1004, "Mindestens einer der folgenden Paramter war erwartet = Gesehen, checked, comment!");
					}else{
						$Query = "UPDATE ".$GET_arr["Tabelle"]." ".$Set." ".$Where.";";
						$result = $this->query($Query);
						if($this->DB_Objekt->error != ""){		return $this->error(1005, $this->DB_Objekt->error);}
						return $this->error(1008,$Query);
					}
				}
				else {
					return $this->error(1004, "Erwartet war Tabelle=(Filme und imdbID, 3d) ODER (Episoden und season_nr,episodenumber) ODER (Staffeln und season_nr)!");
				}
			}
			else {
				return $this->error(1004, "Erwartet war Tabelle!");
			}	
		}else{
			return $this->error(1007,"Keine Rechte um die Filminfos zu setzen!");
		}
	}
	public function API_GetStatistic($GET_arr, $POST_arr =""){
		if(isset($GET_arr['Statistik']) AND in_array($GET_arr['Statistik'], $this->StatiViews)){
			$Query= "SELECT * FROM ".$GET_arr['Statistik'].";";
			$entrys = $this->query($Query);
			if($this->DB_Objekt->error != ""){		return $this->error(1005, $this->DB_Objekt->error);}
			$TitelArray = array();
			$finfo = $entrys->fetch_fields();
			foreach ($finfo as $val) {
				array_push($TitelArray, $val->name);
			}
			$entryArray = array();
			while($entry = $entrys->fetch_array()){
				$tempArray = array();
				foreach ($finfo as $val) {
					if(!(($val->name == "imdbID") AND $NoimdbID)){
						$tempArray[$val->name] = ($entry[$val->name]);
					}
				}
				array_push($entryArray,$tempArray);
			}
			return array("Spalten"=>$TitelArray, "Data" =>$entryArray);
		}
		else{
			return $this->error(1004, "Erwartet war Statistik!");
		}
	}
	public function API_serverinfo($GET_arr = "", $POST_arr =""){
		if( ($this->API_Rechte >=3)){
			return array(	"time" => time(),
							"time_h" => date("",time()),
							"HTTP_USER_AGENT" => $_SERVER['HTTP_USER_AGENT'],
							"REMOTE_ADDR" => $_SERVER['REMOTE_ADDR'],
							"Datenbank" => array(	"Datenbankserver" => $this->DB_Server,
													"Datenbankbenutzer" => $this->DB_Username,
													"Datenbank" => $this->DB_Database)
			);
		}else{
			return $this->error(1007,"Keine Rechte um die Serverinfo abzufragen!");
		}
	}
	/*
	 * Ab hier Helfer-Funktionen
	 */
	private function iset($Check, $default = "",$Insertbefor = "",$Insertafter = ""){
		return ((!isset($Check)OR($Check==""))?$default:$Insertbefor.$Check.$Insertafter);
	}
	private function DebugOut($Output){
		echo (strpos($_SERVER["HTTP_USER_AGENT"], "Android")==0?$Output."\r\n":"");
	}
	private function query($Query){
		$ret = $this->DB_Objekt->query($Query);
		$this->Querys[$this->Statistik["QueryCounter"]++]= array(	"Query" => ($Query),
																	"MySQLnum_rows" => $ret->num_rows,
																	"MySQLfield_count" => $this->DB_Objekt->field_count	,
																	"MySQLerror" => $this->DB_Objekt->error		);
		return $ret;
	}
	/*
	 * Funktion nur ändern, Um Markus zu ärgern ;-)
	 */
	public function APIrespons($Laufzeit = 0, $respons = ""){
		if((strpos($_SERVER["HTTP_USER_AGENT"], "Android")==0) AND ($this->DebugOutput)){
			$this->Statistik["Querys"] = $this->Querys;
			return array(	"API_VERSION"=> API_VERSION,
					"API_KEY"=>$this->API_KEY,
					"API_Laufzeit" =>$Laufzeit,
					"Statistik" => $this->Statistik,
					"Antwort" => $respons
			);
		}else {
			return array(	"API_VERSION"=> API_VERSION,
					"API_KEY"=>$this->API_KEY,
					"API_Laufzeit" =>$Laufzeit,
					"Antwort" => $respons
			);
		}
		$this->DB_Objekt->close();
	}
	public function error($ErrID, $Description = ""){
		switch ($ErrID) {
			case 1001:
				return array(	"FehlerID"=> $ErrID,
								"FehlerText" => "Dies ist ein Platzhalter! Funktion ist noch nicht fertig Implementiert!",
								"FehlerBeschreibung" => $Description);
				break;
			case 1002:
				return array(	"FehlerID"=> $ErrID,
								"FehlerText" => "Es wurde kein bzw. kein gültiger API_KEY übergeben oder DB nicht erreichbar!");
				break;
			case 1003:
				return array(	"FehlerID"=> $ErrID,
								"FehlerText" => "Die gewünschte 'action' ".$_GET['action']." exisitiert nicht!",
								"FehlerBeschreibung" => $Description);
				break;
			case 1004:
				return array(	"FehlerID"=> $ErrID,
								"FehlerText" => "Nicht alle Erwarteten Parameter übergeben!",
								"FehlerBeschreibung" => $Description);
				break;
			case 1005:
				return array(	"FehlerID"=> $ErrID,
								"FehlerText" => "MySQL Fehler!",
								"FehlerBeschreibung" => $Description);
				break;
			case 1006:
				return array(	"FehlerID"=> $ErrID,
								"FehlerText" => "Folgende Daten wurden Erfolgreich empfangen!",
								"FehlerBeschreibung" => $Description);
			case 1007:
				return array(	"FehlerID"=> $ErrID,
								"FehlerText" => "Keine Rechte für diese Aktion!!",
								"FehlerBeschreibung" => $Description);
				break;
			case 1008:
				return array(	"FehlerID"=> $ErrID,
								"FehlerText" => "Alles Gut! Erfolgreich ausgeführt!",
								"FehlerBeschreibung" => $Description);
				break;
			default:
				return array(	"FehlerID"=> 1000,
						"FehlerText" => "Ein unbekannter Fehler ist aufgetreten!");
				break;
		}
	}
}
