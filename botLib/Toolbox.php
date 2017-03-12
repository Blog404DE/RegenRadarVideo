<?php
/**
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 *
 * @version 1.0.0 1.0.0-Stable
 * @copyright (c) tf-network.de Jens Dutzi 2012-2017
 * @license MIT
 *
 * @package blog404de\Toolbox
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

namespace blog404de;


/**
 * Class Toolbox
 * @package blog404de\ToolBox
 */
class Toolbox {
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
	 * @param string|null $dir 	Base directory under which to create temp dir. If null, the default system temp dir (sys_get_temp_dir()) will be used.
	 * @param string $prefix String with which to prefix created dirs.
	 * @param int $mode Octal file permission mask for the newly-created dir. Should begin with a 0.
	 * @param int $maxAttempts Maximum attempts before giving up (to prevent endless loops).
	 * @return string|bool Full path to newly-created dir, or false on failure.
	 */
	public static function tempdir(string $dir = null, string $prefix = 'tmp_', int $mode = 0700, int $maxAttempts = 1000) {
		// Use the system temp dir by default.
		if (is_null($dir)) {
			$dir = sys_get_temp_dir();
		}

		/// Trim trailing slashes from $dir.
		$dir = rtrim($dir, '/');

		// If we don't have permission to create a directory, fail, otherwise we will be stuck in an endless loop.
		if (!is_dir($dir) || !is_writable($dir)) {
			return false;
		}

		// Make sure characters in prefix are safe.
		if (strpbrk($prefix, '\\/:*?"<>|') !== false) {
			return false;
		}

		// Tries to create a random directory until it works. Abort if we reach $maxAttempts.
		// Something screwy could be happening with the filesystem and our loop could otherwise become endless.
		$attempts = 0;
		do {
			$path = sprintf('%s/%s%s', $dir, $prefix, mt_rand(100000, mt_getrandmax()));
		} while (
			!mkdir($path, $mode) &&
			$attempts++ < $maxAttempts
		);

		return $path;
	}


	/**
	 * Löschen des Temporär-Verzeichnisses
	 *
	 * @param string $dir Temporär-Verezichnis
	 * @return bool
	 */
	public static function removeTempDir(string $dir) {
		// TMP Ordner löschen (sofern möglich)
		if($dir !== FALSE && $dir !== NULL) {
			// Prüfe ob Verzeichnis existiert
			if(is_dir($dir)) {
				// Lösche Inhalt des Verzeichnis und Verzeichnis selber
				array_map('unlink', glob($dir. DIRECTORY_SEPARATOR . "*.xml"));
				if (@rmdir($dir)) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}