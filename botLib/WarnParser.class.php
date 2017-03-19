<?php
/**
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 *
 * @version 2.0-dev 2.0.0-dev
 * @copyright (c) tf-network.de Jens Dutzi 2012-2017
 * @license MIT
 *
 * @package blog404de\WetterScripts
 *
 * Stand: 05.03.2017
 *
 * Lizenzinformationen (MIT License):
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the "Software"), to deal in the Software
 * without restriction, including without limitation the rights to use, copy, modify,
 * merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies
 * or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace blog404de\WetterScripts;

/**
 * Benötigte Module laden
 *
 * @TODO: Auslagern in autoload.php
 */

require_once "Toolbox.php";

use Exception, DateTime, DateTimeZone, \blog404de\Toolbox;

/**
 * Parser für die Wetter-Warnungen des DWD
 *
 * @package blog404de\WetterScripts\WarnParser
 */
class WarnParser extends ErrorLogging {
	/** @var bool $strictMode Verarbeite Wetterwarnungen im Strict-Modus und unterbreche den Ablauf bei unbekanntem Warn-Typ */
	public $strictMode = false;

	/** @var string Remote Folder auf dem DWD FTP Server mit den Wetterwarnungen */
	private $remoteFolder = "/gds/gds/specials/alerts/cap/GER/community_status_geometry";

	/** @var string Lokaler Ordner in dem die Wetterwarnungen gespeichert werden */
	private $localFolder = "";

	/** @var resource $ftpConnectionId Link identifier der FTP Verbindung */
	private $ftpConnectionId;

	/** @var string $localJsonFile Lokale Datei in der die verarbeiteten Wetterwarnungen gespeichert werden  */
	private $localJsonFile = "";

	/** @var string|bool $tmpFolder Ordner für temporäre Dateien */
	private $tmpFolder = false;

	/** @var array $wetterWarnungen Aktuelle geparsten Wetterwarnungen */
	private $wetterWarnungen = [];

	/** @var array Array mit Bundesländer in Deutschland */
	private $regionames = [
		"BB"  => "Brandenburg",			"BL" => "Berlin",
		"BW"  => "Baden-Würtemberg",	"BY" => "Bayern",
		"HB"  => "Bremen",				"HE" => "Hessen",
		"HH"  => "Hamburg",				"MV" => "Mecklenburg-Vorpommern",
		"NRW" => "Nordrhein-Westfalen",	"NS" => "Niedersachsen",
		"RP"  => "Rheinland-Pfalz",		"SA" => "Sachsen-Anhalt",
		"SH"  => "Schleswig-Holstein",	"SL" => "Saarland",
		"SN"  => "Sachsen",				"TH" => "Thüringen",
		"DE" => "Bundesrepublik Deutschland"
	];

	/**
	 * WarnParser constructor.
	 */
	function __construct() {
		// Setze Location-Informationen für Deutschland
		setlocale(LC_TIME, "de_DE.UTF-8");
		date_default_timezone_set("Europe/Berlin");

		// Via CLI gestartet?
		if(php_sapi_name() !== "cli") {
			throw new Exception("Script darf ausschließlich über die Kommandozeile gestartet werden.");
		}

		// Root-User Check
		if (0 == posix_getuid()) {
			throw new Exception("Script darf nicht mit root-Rechten ausgeführt werden");
		}

		// FTP Modul vorhanden?
		if(!extension_loaded("ftp")) {
			throw new Exception("PHP Modul 'ftp' steht nicht zur Verfügung");
		}

		// PHP Version prüfen
		if (version_compare(PHP_VERSION, "7.0.0") < 0) {
			throw new Exception("Für das Script wird mindestens PHP7 vorrausgesetzt (PHP 5.6.x wird nur noch mit Sicherheitsupdates bis 31.12.2018 versorgt");
		}

		// Array leeren
		$this->wetterWarnungen = [];
	}

	/**
	 * Verbindung zum DWD FTP Server aufbauen
	 *
	 * @param $host
	 * @param $username
	 * @param $password
	 * @param $passiv
	 */
	public function connectToFTP($host, $username, $password, $passiv = false) {
		try {
			echo "*** Baue Verbindung zum DWD-FTP Server auf." . PHP_EOL;

			// FTP-Verbindung aufbauen
			$this->ftpConnectionId = ftp_connect($host);
			if($this->ftpConnectionId === false) {
				throw new Exception( "FTP Verbindungsaufbau zu " . $host . " ist fehlgeschlagen" . PHP_EOL);
			}

			// Login mit Benutzername und Passwort
			$login_result = @ftp_login($this->ftpConnectionId, $username, $password);

			// Verbindung überprüfen
			if ((!($this->ftpConnectionId)) || (!$login_result)) {
				throw new Exception("Verbindungsaufbau zu zu " . $host . " mit Benutzername " . $username . " fehlgeschlagen.");
			} else {
				echo "\t-> Verbindungsaufbau zu " . $host . " mit Benutzername " . $username . " erfolgreich" . PHP_EOL;
			}

			// Auf Passive Nutzung umschalten
			if($passiv == true) {
				echo "\t-> Schalte auf Passive Verbindung" . PHP_EOL;
				@ftp_pasv(($this->ftpConnectionId), true);
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}
	}

	/**
	 * Verbindung vom FTP Server trennen
	 */
	public function disconnectFromFTP() {
		// Schließe Verbindung sofern notwendig
		if(is_resource($this->ftpConnectionId)) {
			echo PHP_EOL . "*** Schließe Verbindung zum DWD-FTP Server" . PHP_EOL;
			ftp_close($this->ftpConnectionId);
			echo "\t-> Verbindung zu erfolgreich geschlossen." . PHP_EOL;
		}
	}

	/**
	 * Lade aktuelle Wetterwarnungen vom DWD FTP Server
	 */
	public function updateFromFTP() {
		try {
			// Starte Verarbeitung der Dateien
			echo PHP_EOL . "*** Verarbeite alle Dateien auf dem DWD FTP Server" . PHP_EOL;

			// Prüfe ob Verbindung aktiv ist
			if(!is_resource($this->ftpConnectionId)) {
				throw new Exception("FTP Verbindung steht nicht mehr zur Verfügung.");
			};

			// Versuche, in das benötigte Verzeichnis zu wechseln
			if (@ftp_chdir($this->ftpConnectionId, $this->remoteFolder)) {
				echo "-> Wechsle in das Verzeichnis: " . ftp_pwd($this->ftpConnectionId) . PHP_EOL;
			} else {
				throw new Exception("Fehler beim Wechsel in das Verzeichnis '" . $this->remoteFolder . "' auf dem DWD FTP-Server.");
			}

			// Verzeichnisliste auslesen und sortieren
			$arrFTPContent = @ftp_nlist($this->ftpConnectionId, ".");
			if ($arrFTPContent === FALSE) {
				throw new Exception("Fehler beim auslesen des Verezichnis " . $this->remoteFolder  . " auf dem DWD FTP-Server.");
			} else {
				echo("-> Liste der auf dem DWD Server vorhandenen Wetterdaten herunterladen" . PHP_EOL);
			}

			// Filtern der Dateinamen um nicht für alle den Zeitstempel ermittelen zu müssen
			$searchTime = new \DateTime( "now", new \DateTimeZone('GMT'));
			$fileFilter = $searchTime->format("Ymd");

			// Ermittle das Datum für die Dateien
			if (count($arrFTPContent) > 0) {
				echo("-> Erzeuge Download-Liste für " . @ftp_pwd($this->ftpConnectionId) . ":" . PHP_EOL);
				foreach ($arrFTPContent as $filename) {
					// Filtere nach den Wetterwarnungen vom heutigen Tag
					if (strpos($filename , $fileFilter) !== FALSE) {
						// Übernehme Datei in zu-bearbeiten Liste
						if (preg_match('/^(?<Prefix>\w_\w{3}_\w_\w{4}_)(?<Datum>\d{14})(?<Postfix>_\w{3}_STATUS_AREA_UNION)(?<Extension>\.zip)$/' , $filename , $regs)) {
							$dateFileM = \DateTime::createFromFormat("YmdHis" , $regs['Datum'] , new \DateTimeZone("UTC"));
							if ($dateFileM === FALSE) {
								$fileDate = @ftp_mdtm($this->ftpConnectionId, $filename);
								$detectMode = "via FTP / Lesen des Datums fehlgeschlagen";
							} else {
								$fileDate = $dateFileM->getTimestamp();
								$detectMode = "via RegExp";
							}
						} else {
							$fileDate = @ftp_mdtm($this->ftpConnectionId , $filename);
							$detectMode = "via FTP / Lesen des Dateinamens fehlgeschlagen";
						}
						echo "\t" . $filename . " => " . date("d.m.Y H:i" , $fileDate) . " (" . $detectMode . ")" . PHP_EOL;
						$arrDownloadList[$filename] = $fileDate;
					}
				}
			}

			// Dateiliste sortieren
			arsort($arrDownloadList , SORT_NUMERIC);
			array_splice($arrDownloadList , 1);

			// Starte Download der aktuellsten Warn-Datei
			if (count($arrDownloadList) > 0) {
				// Beginne Download
				echo("-> Starte den Download der aktuellsten Warn-Datei:" . PHP_EOL);

				foreach ($arrDownloadList as $filename => $remoteFileMTime) {
					$localFile = $this->localFolder . DIRECTORY_SEPARATOR . $filename;

					// Ermittle Zeitpunkt der letzten Modifikation der lokalen Datei
					$localFileMTime = @filemtime($localFile);

					if ($localFileMTime === FALSE) {
						// Da keine lokale Datei existiert, Zeitpunkt in die Vergangenheit setzen
						$localFileMTime = -1;
					}

					if ($remoteFileMTime !== $localFileMTime) {
						// Öffne lokale Datei
						$localFileHandle = fopen($localFile , 'w');
						if(!$localFileHandle) throw new Exception("Kann " . $localFile . " nicht zum schreiben öffnen");

						if (ftp_fget($this->ftpConnectionId , $localFileHandle , $filename , FTP_BINARY , 0)) {
							if ($localFileMTime === -1) {
								echo sprintf("\tDatei %s wurde erfolgreich heruntergeladen (Remote: %s).", $localFile, date("d.m.Y H:i:s" , $remoteFileMTime)) . PHP_EOL;
							} else {
								echo sprintf("\tDatei %s wurde erneut erfolgreich heruntergeladen (Lokal: %s / Remote: %s).",
									$localFile, date("d.m.Y H:i:s" , $localFileMTime), date("d.m.Y H:i:s" , $remoteFileMTime)) . PHP_EOL;
							}
						} else {
							throw new Exception(sprintf("\tDatei %s wurde erneut erfolgreich heruntergeladen.", $localFile));
						}

						// Schließe Datei-Handle
						@fclose($localFileHandle);

						// Zeitstempel der lokalen Datei identisch zur Remote-Datei setzen (für Cache-Funktion)
						touch($localFile , $remoteFileMTime);
					} else {
						echo sprintf("\tDatei %s existiert bereits im lokalen Download-Ordner.", $localFile) . PHP_EOL;
					}


				}
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}
	}

	/**
	 * Lösche nicht mehr benötigte Dateien
	 */
	public function cleanLocalDownloadFolder() {
		try {
			// Starte Verarbeitung der Dateien
			echo PHP_EOL . "*** Lösche veraltete Wetterwarnungen aus Cache-Ordner." . PHP_EOL;

			// Prüfe Existenz der lokalen Verzeichnisse
			if (!is_writeable($this->localFolder)) {
				throw new Exception("Zugriff auf den Cache-Ordner " . $this->localFolder . " für das automatische aufräumen fehlgeschlagen.");
			}

			// Erzeuge Array mit allen bereits vorhandenen Dateien
			$localFiles = [];
			$handle = opendir($this->localFolder);
			if ($handle) {
				while (FALSE !== ($entry = readdir($handle))) {
					if (!is_dir($this->localFolder . DIRECTORY_SEPARATOR . $entry)) {
						$fileinfo = pathinfo($this->localFolder . DIRECTORY_SEPARATOR . $entry);
						if ($fileinfo["extension"] == "zip") {
							$localFileMTime = @filemtime($this->localFolder . DIRECTORY_SEPARATOR . $entry);
							if ($localFileMTime !== FALSE) $localFiles[$localFileMTime] = $entry;
						}
					}
				}
				closedir($handle);
			} else {
				throw new Exception("Fehler beim aufräumen des Cache in " . $this->localFolder);
			}

			// Dateiliste sortieren
			asort($localFiles , SORT_NUMERIC);
			$localFiles = array_reverse($localFiles);

			// Array $localFiles aufsplitten in zu behaltende und zu löschende Dateien
			$obsoletFiles = array_splice($localFiles, 1);

			// Starte Löschvorgang
			if (count($obsoletFiles) > 0) {
				echo "-> Starte Löschvorgang " . PHP_EOL;
				foreach ($obsoletFiles as $filename) {
					echo "\tLösche veraltete Wetterwarnung-Datei " . $filename . ": ";
					if (!@unlink($this->localFolder . DIRECTORY_SEPARATOR . $filename)) {
						throw new Exception(PHP_EOL . "Fehler beim aufräumen des Caches: '" . $this->localFolder . DIRECTORY_SEPARATOR . $filename . "'' konnte nicht erfolgreich gelöscht werden.");
					} else {
						echo "-> Datei gelöscht." . PHP_EOL;
					}
				}
			} else {
				echo("\tEs muss keine Datei gelöscht werden" . PHP_EOL);
			}
		} catch (\Exception $e) {
			// Fehler-Handling
			$this->logError($e);
		}
	}

	/**
	 * Bereinige lokalen Cache-Ordner
	 */
	public function cleanLocalCache() {
		try {
			// Abschließende Arbeiten ausführen
			echo PHP_EOL . "*** Führe abschließende Arbeiten durch: " . PHP_EOL;

			// Lösche Cache-Folder
			if($this->tmpFolder !== FALSE) {
				echo "-> Lösche angelegten temporären Ordner" . PHP_EOL;
				if(!Toolbox::removeTempDir($this->tmpFolder)) {
					echo "\tLöschen des Ordner " . $this->tmpFolder . " fehlgeschlagen" . PHP_EOL;
					throw new Exception("Löschen des Temporären Ordner (" . $this->tmpFolder . ") ist fehlgeschlagen.");
				};
				echo "\tLöschen des Ordner " . $this->tmpFolder . " erfolgreich" . PHP_EOL;
			} else {
				echo "-> Keine abschließenden Arbeiten otwendigArbeiten notwendig" . PHP_EOL;
			}
		} catch (\Exception $e) {
			// Fehler-Handling
			$this->logError($e);
		}
	}

	/**
	 * Parse lokale Wetterwarnungen nach WarnCellID
	 */
	public function prepareWetterWarnungen() {
		try {
			// Starte verabeiten der Wetterwarnungen des DWD
			echo PHP_EOL . "*** Starte Vorbereitungen::" . PHP_EOL;

			echo "-> Bereite das Vearbeiten der Wetterwarnungen vor" . PHP_EOL;
			echo "\tPrüfe Konfiguration" . PHP_EOL;

			// Prüfe Existenz der lokalen Verzeichnisse
			if (! is_readable($this->localFolder)) {
				throw new Exception("Zugriff auf das Verzeichnis " . $this->localFolder . " mit den lokalen Wetterwarnungen fehlgeschlagen");
			}

			// Zugriff auf JSON Datei möglich
			if(empty($this->localJsonFile)) {
				throw new Exception("Es wurde keine JSON-Datei als Ziel für die Wetterwarnungen angegeben.");
			} else {
				if(file_exists($this->localJsonFile) && !is_writeable($this->localJsonFile)) {
					throw new Exception("Auf die JSON Datei " . $this->localJsonFile . " mit den geparsten Wetterwarnungen kann nicht schreibend zugegriffen werden");
				} else if (!is_writeable(dirname($this->localJsonFile))) {
					throw new Exception("JSON Datei für die geparsten Wetterwarnungen kann nicht in " . dirname($this->localJsonFile) . " geschrieben werden");
				}
			}

			echo "\tLege temporären Ordner an: ";
			$this->tmpFolder = Toolbox::tempdir();
			if(!$this->tmpFolder && is_string($this->tmpFolder)) {
				// Temporär-Ordner kann nicht angelegt werden
				echo "fehlgeschlagen" . PHP_EOL;
				throw new Exception("Temporär-Ordner kann nicht angelegt werden. Bitte prüfen Sie ob in der php.ini 'sys_tmp_dir' oder die Umgebungsvariable 'TMPDIR' gesetzt ist.");
			}
			echo "erfolgreich (" . $this->tmpFolder . ")" . PHP_EOL;

			// ZIP-Dateien in Temporär-Ordner entpacken
			echo "-> Entpacke die Wetterwarnungen des DWD" . PHP_EOL;
			Toolbox::extractAllZipFiles($this->localFolder, $this->tmpFolder, 1);
		} catch (\Exception $e) {
			// Fehler an Logging-Modul übergeben
			$this->logError($e, $this->tmpFolder);
		}
	}

	/**
	 * Parsen der Wetterwarnungen
	 *
	 * @param int $warnCellId Warn-Region mittels WarnCellID festlegen
	 * @param bool $append Zu bestehenden Warnungen hinzufügen
	 */
	public function parseWetterWarnungen(int $warnCellId, bool $append=TRUE) {
		try {
			// Starte parsen der Wetterwarnungen des DWD
			echo PHP_EOL . "*** Verarbeite die Wetterwarnungen:" . PHP_EOL;
			echo "-> Lese die heruntergeladenen Wetterwarnungen ein und suche nach der WarnCellID " . $warnCellId . PHP_EOL;

			// WarnCellID gültig?
			if(!is_numeric($warnCellId)) {
				throw new Exception("Die übergebene WarnCellID beinhaltete keine gültige Nummer");
			}

			// Lese Verzeichnis mit XML Dateien ein
			$localXmlFiles = array();
			$handle = @opendir($this->tmpFolder);
			if ($handle) {
				while (false !== ($entry = readdir($handle))) {
					if (! is_dir($this->tmpFolder . DIRECTORY_SEPARATOR . $entry)) {
						$fileinfo = pathinfo($this->tmpFolder . DIRECTORY_SEPARATOR . $entry);
						if ($fileinfo["extension"] == "xml")
							$localXmlFiles[] = $this->tmpFolder . DIRECTORY_SEPARATOR . $entry;
					}
				}
				closedir($handle);
			} else {
				throw new Exception("Fehler beim zusammenstellen der Wetterwarnungen aus dem Temporär-Ordner.");
			}

			// Lege Array an für die ermittelten Roh-Warnungen
			$arrRohWarnungen = array();

			// Parse XML Dateien
			foreach ($localXmlFiles as $xmlFile) {
				echo "\tPrüfe " . basename($xmlFile) . " (" . round(filesize($xmlFile) / 1024 , 2) . " kbyte): ";

				// Datei kann geöffnet werden?
				if (!is_readable($xmlFile)) {
					throw new Exception(PHP_EOL . "Die XML Datei " . $xmlFile . " konnte nicht geöffnet werden.");
				}

				// Öffne XML Datei zum lesen
				$content = @file_get_contents($xmlFile);
				if (!$content) {
					throw new Exception(PHP_EOL . "Fehler beim lesen der XML Datei " . $xmlFile);
				}

				// XML Datei in Parser laden
				$xml = new \SimpleXMLElement($content, LIBXML_NOERROR|LIBXML_NOWARNING|LIBXML_NONET);
				if (!$xml) {
					throw new Exception(PHP_EOL . "Fehler in parseWetterWarnung: Die XML Datei konnte nicht verarbeitet werden.");
				}

				// Prüfe ob die Warnung nicht vom Typ "cancel" ist:
				if (!property_exists($xml, "msgType")) {
					throw new Exception(PHP_EOL . "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'msgType'-Node.");
				}

				// Prüfe um welche Art von Wetter-Warnung es sich handelt (Alert oder Cancel)
				if (strtolower($xml->{"msgType"}) == "alert") {
					// Verarbeite Inhalt der XML Datei (Typ: Alert)

					// Prüfe ob Info-Node existiert
					if (!property_exists($xml, "info")) {
						throw new Exception(PHP_EOL . "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'info'-Node.");
					} else {
						$info = $xml->{"info"};
					}

					// Verarbeite den Info-Node in einer Schleife (falls mehrere einmal existieren für eine Alert-Datei) und prüfe ob eine Wetter-Warnung für die angegebene WarnCellId vorhanden ist
					foreach ($info as $wetterWarnung) {
						// Prüfe ob es sich um eine Testwarnung handelt
						if (!property_exists($wetterWarnung, "eventCode")) {
							throw new Exception(PHP_EOL . "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'eventCode'-Node.");
						} else {
							$testWarnung = false;
							foreach ($wetterWarnung->{"eventCode"} as $eventCode) {
								if (! isset($eventCode->{"valueName"}) || ! isset($eventCode->{"value"})) {
									throw new Exception(PHP_EOL . "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'eventCode'->'valueName' bzw. 'value'-Node.");
								}

								// Schaue nach EventName = "II" und prüfe ob der Wert auf 98/99 steht (=Testwarnung)
								if ((string)$eventCode->{"valueName"} == "II") {
									if ((string)$eventCode->{"value"} == "98" || (string)$eventCode->{"value"} == "99") $testWarnung = true;
								}
							}
						}

						if (!$testWarnung) {
							// Da keine Test-Warnung: beginne Suche nach WarnCellID
							if(property_exists($info, "area")) {
								$warnRegonFound = $this->searchForWarnAreaInCAP($info->{"area"}, $warnCellId);
								if($warnRegonFound) {
									// Treffer gefunden
									echo sprintf("\tTreffer für %s (%s) / WarnCellID %d gefunden", $warnRegonFound->{"areaDesc"}, $warnRegonFound->{"stateCode"}, $warnRegonFound->{"warncellid"}) . PHP_EOL;
									$arrRohWarnungen[basename($xmlFile)] = ["warnung" => $wetterWarnung, "region" => $warnRegonFound];
								} else {
									// Kein Treffer
									echo "\tKein Treffer" . PHP_EOL;
								}
							} else {
								throw new Exception("Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'area'-Node.");
							}
						} else {
							// Da Test-Warnung, diese Warnung nicht zur weiteren Verarbeitung übernehmen
							echo "\t\t-> Testwarnung (ignoriere Inhalt)" . PHP_EOL;
						}
					}
				} else if (strtolower($xml->{"msgType"}) == "cancel") {
					// Verarbeite Inhalt der XML Datei (Typ: Cancel)
					echo "\t\t-> Stoppe Verarbeitung der Wetterwarnung-Datei (Auflösungs-Nachricht muss nicht versendet werden)" . PHP_EOL;
				} else {
					// Verarbeite Inhalt der XML Datei (Typ: Unbekannt)
					echo "\t\t-> Stoppe Verarbeitung da der Warn-Typ unbekannt ist" . PHP_EOL;
					if($this->strictMode) throw new Exception("Strict-Mode Fehler: Wetterwarnung mit unbekannten Wetter-Typ " . (string)$xml->{"msgType"});
				}
			}

			echo "-> Verarbeite alle gefundenen Wetterwarnungen (Anzahl: " . count($arrRohWarnungen) . ")" . PHP_EOL;
			if (count($arrRohWarnungen) > 0) {
				// Sollen die Wetterwarnungen hinzugefügt werden zu bestehenden?
				if($append !== true) $this->wetterWarnungen = [];

				// Durchlaufe alle Warnungen
				foreach ($arrRohWarnungen as $filename => $rawWarnung) {
					echo("\tWetterwarnung aus " . $filename . ":" . PHP_EOL);
					$parsedWarnInfo = [];

					if (array_key_exists("warnung", $rawWarnung) && array_key_exists("region", $rawWarnung)) {
						$currentWarnung = $rawWarnung["warnung"];
						$currentGeo	= $rawWarnung["region"];
					} else {
						throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung ist nicht vollständig - 'warnung' oder 'geoinfo' fehlt.");
					}

					// Prüfe-Geo-Felder
					$geoFields = ["warncellid", "areaDesc", "stateCode", "stateName", "altitude", "ceiling"];
					foreach ($geoFields as $field) {
						if(!property_exists($currentGeo, $field)) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'region' das XML-Node '" . $field . "'");
						}
					}

					// Ermittle Event-Typ
					if (!property_exists($currentWarnung, "event")) {
						throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'event.");
					} else {
						$parsedWarnInfo["event"] = (string)$currentWarnung->{"event"};
					}

					// Start- und Ablaufdatum ermitteln
					if (!property_exists($currentWarnung, "onset")) {
						throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'onset'.");
					} else {
						$strRawDate = str_replace("+00:00", "", (string)$currentWarnung->{"onset"});
						$objDateOnset = DateTime::createFromFormat('Y-m-d*H:i:s', $strRawDate, new DateTimeZone("UTC"));
						if (!$objDateOnset) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung beinhaltet ungültige Daten im XML-Node 'warnung'->'onset'.");
						} else {
							// Zeitzone auf Deutschland umstellen
							$objDateOnset->setTimezone(new DateTimeZone("Europe/Berlin"));
						}
						$parsedWarnInfo["startzeit"] = serialize($objDateOnset);
					}

					// Expire-Zeitpunkt setzen (entweder aus der Wetterwarnung oder geschätzt)
					if (!property_exists($currentWarnung, "expires")) {
						$objDateExpires = $objDateOnset;
						$parsedWarnInfo["endzeit"] = $parsedWarnInfo["startzeit"];
					} else {
						$strRawDate = str_replace("+00:00", "", (string)$currentWarnung->{"expires"});
						$objDateExpires = DateTime::createFromFormat('Y-m-d*H:i:s', $strRawDate, new DateTimeZone("UTC"));
						if (!$objDateExpires) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung beinhaltet ungültige Daten im XML-Node 'warnung'->'expires'.");
						} else {
							$objDateExpires->setTimezone(new DateTimeZone("Europe/Berlin"));
						}
						$parsedWarnInfo["endzeit"] = serialize($objDateExpires);
					}

					// Aktuelle Uhrzeit
					$dateCurrent = new DateTime("now", new DateTimeZone("Europe/Berlin"));

					// Prüfe ob Warnung bereits abgelaufen ist und übersprungen werden kann
					if ($objDateExpires->getTimestamp() <= $dateCurrent->getTimestamp() && $objDateExpires->getTimestamp() != $objDateOnset->getTimestamp()) {
						// Warnung ist bereits abgelaufen
						echo ("\t\t* Hinweis: Warnung über " . $parsedWarnInfo["event"] . " ist bereits am " . $parsedWarnInfo["expires"] . " abgelaufen und wird ingoriert" . PHP_EOL);
					} else {
						// Warnung ist aktuell -> verarbeite Warnung

						// Warnstufe
						if (!property_exists($currentWarnung, "severity")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'severity'.");
						} else {
							// Severity ermitteln und auf die DWD "Sprache" übersetzen
							$severity = (string)$currentWarnung->{"severity"};
							switch ($severity) {
								case "Minor":
									$parsedWarnInfo["severity"] = "Wetterwarnung";
									$parsedWarnInfo["warnstufe"] = 1;
									break;
								case "Moderate":
									$parsedWarnInfo["severity"] = "Markante Wetterwarnung";
									$parsedWarnInfo["warnstufe"] = 2;
									break;
								case "Severe":
									$parsedWarnInfo["severity"] = "Unwetterwarnung";
									$parsedWarnInfo["warnstufe"] = 3;
									break;
								case "Extreme":
									$parsedWarnInfo["severity"] = "Extreme Unwetterwarnung";
									$parsedWarnInfo["warnstufe"] = 4;
									break;
								default:
									$parsedWarnInfo["severity"] = "Unbekannt";
									$parsedWarnInfo["warnstufe"] = -1;
							}
						}

						// Dringlichkeit - handelt es sich um eien Vorab-Warnung
						if (!property_exists($currentWarnung, "urgency")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'urgency'.");
						} else {
							// Im Fall einer Vorhersage (Urgency == Future oder OnSet-Zeitpunkt in der Zukunft)
							$parsedWarnInfo["urgency"] = (string)$currentWarnung->{"urgency"};
							if((string)$currentWarnung->{"urgency"} == "Future" || $objDateOnset->getTimestamp() > time()) {
								$parsedWarnInfo["warnstufe"] = 0;
								$parsedWarnInfo["severity"] = "Vorwarnung";
							}
						}

						// Ermittle die Inhalt für das erstellen des Textes der Wetterwarnung selber
						if (!property_exists($currentWarnung, "headline")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'headline'.");
						} else {
							$parsedWarnInfo["headline"] = (string)$currentWarnung->{"headline"};
						}
						if (!property_exists($currentWarnung, "description")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'description'.");
						} else {
							$parsedWarnInfo["description"] = (string)$currentWarnung->{"description"};
						}
						if (!property_exists($currentWarnung, "instruction")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'instruction'.");
						} else {
							$parsedWarnInfo["instruction"] = (string)$currentWarnung->{"instruction"};
						}
						if (!property_exists($currentWarnung, "senderName")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'senderName'.");
						} else {
							$parsedWarnInfo["sender"] = (string)$currentWarnung->{"senderName"};
						}
						if (!property_exists($currentWarnung, "web")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'web'.");
						} else {
							$parsedWarnInfo["web"] = (string)$currentWarnung->{"web"};
						}

						// Warnregion ermitteln samt des ausgeschriebenen Ländernamen
						if (!property_exists($rawWarnung["region"], "warncellid")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'region' das XML-Node 'warncellid'.");
						} else {
							$parsedWarnInfo["warncellid"] = (string)$rawWarnung["region"]->{"warncellid"};
						}
						if (!property_exists($rawWarnung["region"], "areaDesc")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'region' das XML-Node 'areaDesc'.");
						} else {
							$parsedWarnInfo["area"] = (string)$rawWarnung["region"]->{"areaDesc"};
						}
						if (!property_exists($rawWarnung["region"], "stateName")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'region' das XML-Node 'stateName'.");
						} else {
							$parsedWarnInfo["stateLong"] = (string)$rawWarnung["region"]->{"stateName"};
						}
						if (!property_exists($rawWarnung["region"], "stateCode")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'stateCode' das XML-Node 'stateName'.");
						} else {
							$parsedWarnInfo["stateShort"] = (string)$rawWarnung["region"]->{"stateCode"};
						}

						// Höhenangnaben ermitteln umd umrechnen
						if (!property_exists($rawWarnung["region"], "altitude") || !property_exists($rawWarnung["region"], "ceiling")) {
							throw new Exception("Die aktuell verarbeitete Roh-Wetterwarnung fehlen XML-Nodes 'region'->'altitude' und/oder 'region'->'ceiling.");
						} else {
							// abrunden, anstatt wie laut CAPS Doku aufruden -> das Ergebnis passt sonst nicht zum Text
							$parsedWarnInfo["altitude"] = floor($rawWarnung["region"]->{"altitude"} * 0.3048);
							$parsedWarnInfo["ceiling"] = floor($rawWarnung["region"]->{"ceiling"} * 0.3048 );
							if($rawWarnung["region"]->{"altitude"} == 0 & $rawWarnung["region"]->{"ceiling"} != 9842.5197) {
								$parsedWarnInfo["hoehenangabe"] = "Höhenlagen unter " . $parsedWarnInfo["ceiling"] . "m";
							} else if($rawWarnung["region"]->{"altitude"} != 0 & $rawWarnung["region"]->{"ceiling"} == 9842.5197) {
								$parsedWarnInfo["hoehenangabe"] = "Höhenlagen über " . $parsedWarnInfo["altitude"] . "m";
							} else {
								$parsedWarnInfo["hoehenangabe"] = "Alle Höhenlagen";
							}
						}

						// MD5Hash erzeugen aus Angaben der Wetterwarnung
						$strForHash  = $parsedWarnInfo["warnstufe"] . $parsedWarnInfo["event"] . $objDateOnset->getTimestamp() . $objDateExpires->getTimestamp();
						$strForHash .= $parsedWarnInfo["area"] . $parsedWarnInfo["headline"] . $parsedWarnInfo["description"] . $parsedWarnInfo["instruction"];
						$parsedWarnInfo["hash"] = md5($strForHash);

						// Ausgabe der Anwendung:
						echo "\t\t* Wetterwarnung für '" . $parsedWarnInfo["event"] . "' verarbeitet" . PHP_EOL;
					}

					// Wetterwarnung übernehmen und neu sortieren
					$this->wetterWarnungen[$parsedWarnInfo["hash"]] = $parsedWarnInfo;
					asort($this->wetterWarnungen);
				}
			} else {
				echo ("\tKeine Warnmeldungen zum verarbeiten vorhanden" . PHP_EOL);
			}
		} catch (Exception $e) {
			// Fehler an Logging-Modul übergeben
			$this->logError($e, $this->tmpFolder);
		}
	}

	/**
	 * Speichern der Wetterwarnung in JSON Datei
	 * @return bool
	 */
	public function saveToLocalJsonFile() {
		try {
			echo PHP_EOL . "*** Beginne speichern der Wetterwarnungen:" . PHP_EOL;

			// Prüfe ob Zugriff auf json-Datei existiert
			if(empty($this->localJsonFile) || !is_writeable($this->localJsonFile)) {
				throw new Exception("Es ist kein Pfad zu der lokalen JSON-Datei mit den Wetterwarnungen vorhanden oder es besteht kein Schreibzugriff auf die Datei (Pfad: " . $this->localJsonFile . ")");
			}

			// Wetterwarnungen aufbereiten (Key entfernen)
			$wetterWarnungen = array("anzahl" => count($this->wetterWarnungen), "wetterwarnungen" => array_values($this->wetterWarnungen));

			// Wandle in JSON um
			echo "-> Konvertiere Wetterwarnungen in JSON-Daten" . PHP_EOL;
			$jsonWetterWarnung = @json_encode( $wetterWarnungen, JSON_PRETTY_PRINT);
			if(json_last_error() > 0) {
				throw new Exception("Fehler während der JSON Kodierung der Wetter-Warnungen (Fehler: " . Toolbox::getJsonErrorMessage(json_last_error()) . ")");
			}

			// Ermittle MD5-Hashes der bisherigen und ehemaligen Wetterwarnungen
			echo "-> Ermittle MD5-Hashs der bisherigen Wetterwarnung und der neuen Wetterwarnung um Änderungen festzustellen" . PHP_EOL;
			$md5hashes = [];
			$md5hashes["new"] = @md5($jsonWetterWarnung);
			if(empty($md5hashes["new"] || $md5hashes["new"] === FALSE)) {
				throw new Exception("Fehler beim erzeugen des MD5-Hashs der neuen Wetterwarnungen");
			}

			$md5hashes["old"] = @md5_file($this->localJsonFile);
			if(empty($md5hashes["old"] || $md5hashes["old"] === FALSE)) {
				throw new Exception("Fehler beim erzeugen des MD5-Hashs der bisherigen Wetterwarnungen");
			}

			echo "\t\tMD5-Hashs der bisherigen Wetterwarnungen:\t" . $md5hashes["old"] . PHP_EOL;
			echo "\t\tMD5-Hashs der neuenWetterwarnungen:\t\t" . $md5hashes["new"] . PHP_EOL;


			// Gab es eine Änderung?
			if($md5hashes["old"] !== $md5hashes["new"]) {
				echo "-> Änderung bei den Wetterwarnungen gefunden - speichere neue Wetterwarnung" . PHP_EOL;
				$saveJson = file_put_contents($this->localJsonFile, $jsonWetterWarnung);
				if(!$saveJson) {
					throw new Exception("Fehler beim speichern der verarbeiteten Wetterwarnungen (Pfad: " . $this->localJsonFile . ")");
				}

				$fileupdated = true;
			} else {
				echo "-> Keine Änderung bei den Wetterwarnungen vorhanden - kein speichern notwendig" . PHP_EOL;
				$fileupdated = false;
			}

			return $fileupdated ;
		} catch (Exception $e) {
			// Fehler an Logging-Modul übergeben
			$this->logError($e, $this->tmpFolder);

			return false;
		}
	}

	/*
	 *  Private Methoden
	 */

	/**
	 * @param \SimpleXMLElement $WarnInfoNode Info-Block der zu prüfenden Wetter-Warnung
	 * @param int $warnCellId WarnCellID nach der gesucht werden soll
	 * @return \SimpleXMLElement|bool
	 */
	private function searchForWarnAreaInCAP(\SimpleXMLElement $WarnInfoNode, int $warnCellId) {
		try {
			// Lege Result- und Hits-Variable an
			$result = false;
			$hits = 0;

			// Durchlaufe den gesamten Info-Node
			foreach ($WarnInfoNode as $area) {
				// Ermittle WarnCell-ID und State
				$currentStateCode = null;
				$currentWarnCellID = null;

				// Prüfe ob es sich um kein Node mit Polygon-Informationen ist, sondern mit Orts-Informationen
				if (!property_exists($area , "polygon")) {
					// Keine polygon-Informationen gefunden -> verarbeite Daten
					$hits++;

					// Speichere areaDesc
					if (! isset($area->{"areaDesc"})) {
						throw new Exception("Fehler beim durchsuchen der Geo-Informationen: XML-Nodes 'areaDesc' fehlt.");
					} else {
						$currentAreaDesc = (string)$area->{"areaDesc"};
					}

					// Speichere Höhenangaben
					if (! isset($area->{"altitude"})) {
						throw new Exception("Fehler beim durchsuchen der Geo-Informationen: XML-Nodes 'altitude' fehlt");
					} else {
						$currentAltitude = (float)$area->{"altitude"};
					}
					if (! isset($area->{"ceiling"})) {
						throw new Exception("Fehler beim durchsuchen der Geo-Informationen: XML-Nodes 'ceiling' fehlt.");
					} else {
						$currentCeiling = (float)$area->{"ceiling"};
					}

					// Prüfe auf Vorkommen der WarnCell ID und übernehme die Geo-Informationen
					foreach ($area->{"geocode"} as $geocode) {
						// Prüfe ob Nodes vorhanden sind
						if (!property_exists($geocode, "valueName") || !property_exists($geocode, "value")) {
							throw new Exception("Fehler beim durchsuchen der Geo-Informationen: XML-Nodes 'area'->'geocode'->'valueName' oder 'value' fehlen.");
						}

						if ($geocode->{"valueName"} == "STATE") {
							$currentStateCode = (string)$geocode->{"value"};
						} else if ($geocode->{"valueName"} == "WARNCELLID") {
							$currentWarnCellID = (string)$geocode->{"value"};
						}

					}

					// Prüfe ob mindestens ein gültiger Eintrag existiert in dem geprüften Area-Node
					if (is_null($currentStateCode) || is_null($currentWarnCellID)) {
						throw new Exception("Fehler beim durchsuchen der Geo-Informationen: Ein 'GeoCode'-Node beinhaltete keine State/WarncellID-Informationen");
					}

					// Gehört die WarnCellID zu der gesuchten und existiert noch kein Result-Objekt?
					if ($warnCellId == $currentWarnCellID && !is_object($result) ) {
						// Klartext-Ländername ermitteln
						if(array_key_exists(strtoupper($currentStateCode), $this->regionames)) {
							$currentStateName = $this->regionames[strtoupper($currentStateCode)];
						} else {
							$currentStateName = $currentStateCode;
						}

						// Treffer als XML Objekt zusammenstellen (XML um durchgehend den gleichen Objekt-Typ zu haben) falls noch keine Geo-Informationen existieren
						$result = new \SimpleXMLElement("<geoInfo/>");
						$result->addChild("warncellid",	$currentWarnCellID);
						$result->addChild("areaDesc",		$currentAreaDesc);
						$result->addChild("stateCode",	$currentStateCode);
						$result->addChild("stateName",	$currentStateName);
						$result->addChild("altitude",		$currentAltitude);
						$result->addChild("ceiling",		$currentCeiling);
					}
				}
			}

			// Prüfe ob überhaupt ein Feld mit Orts-Informationen gefunden wurden.
			if($hits == 0) {
				throw new Exception("Fehler beim durchsuchen der Geo-Informationen: Die Geo-Informationen beinhalteten überhaupt keine State/WarnCellID-Informationen");
			}

			// Ergebnis übergeben
			return $result;
		} catch (Exception $e) {
			// Fehler-Handling
			$this->logError($e, $this->tmpFolder);

			return false;
		}
	}

	/*
	 *  Getter / Setter Funktionen
	 */

	/**
	 * Getter für $localFolder
	 * @return string
	 */
	public function getLocalFolder(): string {
		return $this->localFolder;
	}

	/**
	 * Setter für $localFolder
	 * @param string $localFolder
	 */
	public function setLocalFolder(string $localFolder) {
		try {
			if (is_dir($localFolder)) {
				if(is_writeable($localFolder)) {
					$this->localFolder = $localFolder;
				} else {
					throw new Exception("In den lokale Ordner " . $localFolder . " kann nicht geschrieben werden.");
				}
			} else {
				throw new Exception("In den lokale Ordner " . $localFolder . " existiert nicht.");
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}
	}

	/**
	 * Getter für $localJsonFile
	 * @return string
	 */
	public function getLocalJsonFile(): string {
		return $this->localJsonFile;
	}

	/**
	 * Setter für $localJsonFile
	 * @param string $localJsonFile
	 */
	public function setLocalJsonFile(string $localJsonFile) {
		try {
			if(empty($localJsonFile)) {
				throw new Exception("Es wurde keine JSON-Datei zals Ziel für die Wetterwarnungen angegeben.");
			} else {
				if(file_exists($localJsonFile) && !is_writeable($localJsonFile)) {
					// Datei existiert - aber die Schreibrechte fehlen
					throw new Exception("Auf die JSON Datei " . $localJsonFile . " mit den geparsten Wetterwarnungen kann nicht schreibend zugegriffen werden");
				} else if (is_writeable(dirname($localJsonFile))) {
					// Datei existiert nicht - Schreibrechte auf den Ordner existieren
					if(!@touch($localJsonFile)) {
						// Leere Datei anlegen ist nicht erfolgreich
						throw new Exception("Leere JSON Datei für die geparsten Wetterwarnungen konnte nicht angelegt werden");
					}
				} else {
					// Kein Zugriff auf Datei möglich
					throw new Exception("JSON Datei für die geparsten Wetterwarnungen kann nicht in " . dirname($localJsonFile) . " geschrieben werden");
				}
			}

			// Variable setzen
			$this->localJsonFile = $localJsonFile;
		} catch (\Exception $e) {
			$this->logError($e);
		}
	}

	/**
	 * Getter für $remoteFolder
	 * @return string
	 */
	public function getRemoteFolder(): string {
		return $this->remoteFolder;
	}

	/**
	 * Setter für $remoteFolder
	 * @param string $remoteFolder
	 */
	public function setRemoteFolder(string $remoteFolder) {
		$this->remoteFolder = $remoteFolder;
	}

	/**
	 * Getter-Methode für WetterWarnungen
	 * @return array Liste mit Wetterwarnungen
	 */
	public function getWetterWarnungen(): array {
		return $this->wetterWarnungen;
	}
}

/**
 * Error-Logging Klasse
 *
 * @package blog404de\WetterScripts\WarnParser
 */
class ErrorLogging {
	/** @var array E-Mail Absender/Empfänger in ["empfaenger"] und ["absender"] */
	private $logToMail = [];

	/** @var string Pfad zur Log-Datei */
	private $logToFile = "";

	/**
	 * Fehler innerhalb der Anwendung verarbeiten
	 *
	 * @param \Exception $e
	 * @param string $tmpPath
	 */
	protected function logError(\Exception $e, string $tmpPath = NULL) {
		// Zeitpunkt
		$strDate = date("Y-m-d H:i:s");

		// Fehler-Ausgabe erzeugen:
		$longText = sprintf("Fehler im Programmablauf:" . PHP_EOL .
							"\tZeitpunkt: %s" . PHP_EOL .
							"\tFehlermeldung: %s" . PHP_EOL .
							"\tPosition: %s:%d",
			$strDate, $e->getMessage(), $e->getFile(), $e->getLine()
		);


		$shortText = sprintf("%s - %s:%d - %s",
			date("Y-m-d H:i:s"),
			$e->getFile(),
			$e->getLine(),
			$e->getMessage()
		);

		// Lösche evntuell vorhandenes Temporäre Verzeichnis
		if($tmpPath !== FALSE && !is_null($tmpPath)) {
			$tmpclean = Toolbox::removeTempDir($tmpPath);
			if(!$tmpclean) {
				$shortText = $shortText . " - Cleanup: temporärer Ordner (" . $tmpPath . ") konnte nicht gelöscht werden";
				$longText = $longText . PHP_EOL . "\tCleanup: Fehler beim löschen des temporären Ordner (" . $tmpPath . ")";
			} else {
				$shortText = $shortText . " - Cleanup: temporärer Ordner gelöscht";
				$longText = $longText . PHP_EOL . "\tCleanup:temporärer Ordner gelöscht";
			}
		}

		// Loggen in Datei
		if(!empty($this->logToFile)) {
			$writeFile = file_put_contents($this->logToFile, $shortText . PHP_EOL, FILE_APPEND);
			if($writeFile === FALSE) {
				$longText = $longText . PHP_EOL . "\tLogdatei schreiben: Fehler beim schreiben der Log-Datei in: " . $this->logToFile . PHP_EOL;
			}
		}

		// Loggen per E-Mail
		if (is_array($this->logToMail)) {
			if (array_key_exists("empfaenger" , $this->logToMail) && array_key_exists("absender", $this->logToMail )) {
				$mailHeader = sprintf("From: Wetterwarn-Bot <%s>\r\n" .
					"Reply-To: Wetter-Bot <%s>\r\n" .
					"X-Mailer: Wetter-Bot by tfnApps.de\r\n" .
					"X-Priority: 1 (Higuest)\r\n" .
					"X-MSMail-Priority: High\r\n" .
					"Importance: High\r\n" ,
					$this->logToMail["absender"] ,
					$this->logToMail["empfaenger"]
				);
				$mailBetreff = "Fehler beim verarbeiten der Wetterwarndaten: " . $strDate;

				$sentMail = mail($this->logToMail["empfaenger"] , $mailBetreff , $longText , $mailHeader);
				if ($sentMail === FALSE) {
					$longText = $longText . PHP_EOL . "\tE-Mail Versand: Fehler beim senden der Fehler E-Mail an: " . $this->logToMail["empfaenger"] . PHP_EOL;
				} else {
					$longText = $longText . PHP_EOL . "\tE-Mail Versand: Fehler E-Mail wurde erfolgreich an " . $this->logToMail["empfaenger"] . " versendet." . PHP_EOL;
				}
			}
		}

		// Ausgabe auf die Konsole
		fwrite(STDOUT, PHP_EOL);
		fwrite(STDERR, $longText);
		fwrite(STDOUT, PHP_EOL);

		exit(1);
	}

	/*
	 * Getter / Setter-Methoden
	 */

	/**
	 * Setter-Methode für logToMail
	 *
	 * @param array $logToMail
	 * @return ErrorLogging
	 */
	public function setLogToMail(array $logToMail): ErrorLogging {

		try {
			if (is_array($logToMail)) {
				if (array_key_exists("empfaenger" , $logToMail) && array_key_exists("absender", $logToMail )) {
					if (filter_var($logToMail["empfaenger"], FILTER_VALIDATE_EMAIL) && filter_var($logToMail["absender"], FILTER_VALIDATE_EMAIL)) {
						$this->logToMail = $logToMail;
					} else {
						throw new Exception("LogToMail beinhaltet für die Array-Keys 'empfaenger' oder 'absender' keine gültige E-Mail Adresse");
					}
				} else {
					// Error-Logging Konfiguration ist falsch
					$this->logToMail = [];
					throw new Exception("LogToMail benötigt ein Array mit den Keys 'empfaenger' und 'absender'");
				}
			} else {
				// Error Logging an E-Mail deaktivieren
				$this->logToMail = [];
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}

		return $this;
	}

	/**
	 * Setter-Methode für LogToFile
	 *
	 * @param string $logToFile
	 * @return ErrorLogging
	 * @throws \Exception
	 */
	public function setLogToFile(string $logToFile): ErrorLogging {
		try {
			if(file_exists($logToFile) && is_writeable($logToFile)) {
				// Datei existiert und kann geschrieben werden
				$this->logToFile = $logToFile;
			} else if (!file_exists($logToFile) && is_writeable(dirname($logToFile))) {
				if(touch($logToFile)) {
					// Nicht existierende Log-Datei erfolgreich angelegt
					$this->logToFile = $logToFile;
				} else {
					// Log-Datei kann nicht neu angelegt werden
					throw new Exception("Fehler beim anlegen der Log-Datei in: " . $logToFile);
				}
			} else {
				throw new Exception("Fehler beim schreiben der Log-Datei in: " . $logToFile);
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}

		return $this;
	}

	/**
	 * Getter-Methode für logToMail
	 * @return array
	 */
	public function getLogToMail(): array {
		return $this->logToMail;
	}

	/**
	 * Getter-Methode für LogToFile
	 * @return string
	 */
	public function getLogToFile(): string {
		return $this->logToFile;
	}
}