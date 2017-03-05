<?php
/*
 * Wetterwarn-Bot für neuthardwetter.de by Jens Dutzi
 * Version 1.0
 * 30.11.2015
 * (c) tf-network.de Jens Dutzi 2012-2015
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

/**
 * Umwandeln des Länerkürzel in den vollen Namen des Bundeslandes
 *
 * @param string $state
 * @return string
 */
function getNameFromState($state) {
	switch ($state) {
		case "BW":
			$stateLong = "Baden-Würtemberg";
			break;
		case "BY":
			$stateLong = "Bayern";
			break;
		case "BE":
			$stateLong = "Berlin";
			break;
		case "BB":
			$stateLong = "Brandenburg";
			break;
		case "HB":
			$stateLong = "Bremen";
			break;
		case "HH":
			$stateLong = "Hamburg";
			break;
		case "HE":
			$stateLong = "Hessen";
			break;
		case "MV":
			$stateLong = "Mecklenburg-Vorpommern";
			break;
		case "NI":
			$stateLong = "Niedersachsen";
			break;
		case "NW":
			$stateLong = "Nordrhein-Westfalen";
			break;
		case "RP":
			$stateLong = "Rheinland-Pfalz";
			break;
		case "SL":
			$stateLong = "Saarland";
			break;
		case "SN":
			$stateLong = "Sachsen";
			break;
		case "ST":
			$stateLong = "Sachsen-Anhalt";
			break;
		case "SH":
			$stateLong = "Schleswig-Holstein";
			break;
		case "TH":
			$stateLong = "Thüringen";
			break;
		case "DE":
			$stateLong = "Bundesrepublik Deutschland";
			break;
		default:
			$stateLong = $state;
	}

	return $stateLong;
}

/**
 * Methode zum generieren der Klartext-Fehlermeldung beim Zugriff auf ZIP-Dateien
 *
 * @param $errCode
 * @return string
 */
function getZipErrorMessage($errCode) {
    switch ($errCode) {
        case ZipArchive::ER_EXISTS:
            return "File already exists.";
            break;
        case ZipArchive::ER_INCONS:
            return "Zip archive inconsistent.";
            break;
        case ZipArchive::ER_INVAL:
            return "Invalid argument.";
            break;
        case ZipArchive::ER_MEMORY:
            return "Malloc failure.";
            break;
        case ZipArchive::ER_NOENT:
            return "No such file.";
            break;
        case ZipArchive::ER_NOZIP:
            return "Not a zip archive.";
            break;
        case ZipArchive::ER_OPEN:
            return "Can't open file.";
            break;
        case ZipArchive::ER_READ:
            return "Read error.";
            break;
        case ZipArchive::ER_SEEK:
            return "Seek error.";
            break;
        default:
            return "Unknown error.";
    }
}

/**
 * Methode zum ausgeben einer Fehlermeldung mit dem abbruch des Scripts
 *
 * @param array $optFehlerMail
 * @param string $fehlerdetails
 */
function sendErrorMessage($optFehlerMail, $fehlerdetails) {
	// Fehler-Handling
	fwrite(STDOUT, PHP_EOL);
	fwrite(STDERR, date("Y-m-d H:i:s") . ": " . $fehlerdetails . PHP_EOL);
	fwrite(STDOUT, PHP_EOL);

	// Optional Mail an den Admin absenden
	if(count($optFehlerMail) == 2) {
		if(array_key_exists("absender", $optFehlerMail) &&  array_key_exists("empfaenger", $optFehlerMail)) {
			if(!empty($optFehlerMail["absender"]) && !empty($optFehlerMail["empfaenger"])) {
				$mailBetreff = "Fehler beim Ablauf des WetterBot-Cronjobs";

				$message  = "Fehler beim Ablauf des Wetter-Cronjobs. Der Cronjob wurde daher abgebrochen.\r\n" .
							"\r\n" .
							"Details:\r\n" .
							$fehlerdetails;

				sendmail($optFehlerMail["absender"], $optFehlerMail["empfaenger"], $mailBetreff, $message);
			} else {
				fwrite(STDOUT, PHP_EOL);
				fwrite(STDERR, date("Y-m-d H:i:s") . ": Versand der Fehler-Mail wurde nicht durchgeführt - bitte prüfen Sie die Konfiguration des Wetter-Bots." . PHP_EOL);
			}
		} else {
			fwrite(STDOUT, PHP_EOL);
			fwrite(STDERR, date("Y-m-d H:i:s") . ": Versand der Fehler-Mail wurde nicht durchgeführt - bitte prüfen Sie die Konfiguration des Wetter-Bots." . PHP_EOL);
		}
	}

	exit(-1);
}

/**
 * Methode zum senden einer E-Mail
 *
 * @param $absender
 * @param $empfaenger
 * @param $betreff
 * @param $message
 */
function sendmail($absender, $empfaenger, $betreff, $message) {
	// Header zusammenstellen
	$mailHeader = 	"From: Wetter-Bot <". $absender . ">\r\n" .
		"Reply-To: Wetter-Bot <". $absender . ">\r\n" .
		"X-Mailer: Wetter-Bot by tfnApps.de\r\n" .
		"X-Priority: 1 (Higuest)\r\n" .
		"X-MSMail-Priority: High\r\n" .
		"Importance: High\r\n";

	mail($empfaenger, $betreff, $message, $mailHeader);
}

/**
 * Creates a random unique temporary directory, with specified parameters,
 * that does not already exist (like tempnam(), but for dirs).
 *
 * Created dir will begin with the specified prefix, followed by random
 * numbers.
 *
 * @link https://php.net/manual/en/function.tempnam.php
 * @link http://stackoverflow.com/questions/1707801/making-a-temporary-dir-for-unpacking-a-zipfile-into
 *
 * @param string|null $dir Base directory under which to create temp dir.
 *     If null, the default system temp dir (sys_get_temp_dir()) will be
 *     used.
 * @param string $prefix String with which to prefix created dirs.
 * @param int $mode Octal file permission mask for the newly-created dir.
 *     Should begin with a 0.
 * @param int $maxAttempts Maximum attempts before giving up (to prevent
 *     endless loops).
 * @return string|bool Full path to newly-created dir, or false on failure.
 */
function tempdir($dir = null, $prefix = 'tmp_', $mode = 0700, $maxAttempts = 1000) {
    /* Use the system temp dir by default. */
    if (is_null($dir)) {
        $dir = sys_get_temp_dir();
    }

    /* Trim trailing slashes from $dir. */
    $dir = rtrim($dir, '/');

    /* If we don't have permission to create a directory, fail, otherwise we will
     * be stuck in an endless loop.
     */
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    /* Make sure characters in prefix are safe. */
    if (strpbrk($prefix, '\\/:*?"<>|') !== false) {
        return false;
    }

    /* Attenot to create a random directory until it works. Abort if we reach
     * $maxAttempts. Something screwy could be happening with the filesystem
     * and our loop could otherwise become endless.
     */
    $attempts = 0;
    do {
        $path = sprintf('%s/%s%s', $dir, $prefix, mt_rand(100000, mt_getrandmax()));
    } while (
        !mkdir($path, $mode) &&
        $attempts++ < $maxAttempts
    );

    return $path;
}

?>