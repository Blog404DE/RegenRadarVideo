<?php

/*
 *  RegenRadar Autoloader für neuthardwetter.de by Jens Dutzi - autoload.php
 *
 *  @package    blog404de\RegenRadar
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2019 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/RegenRadarVideo/blob/master/LICENSE.md
 *  @version    3.2.0-stable
 *  @link       https://github.com/Blog404DE/RegenRadarVideo
 */

spl_autoload_register(function ($class) {
    // Basis-Verzeichnis ermitteln
    $base_dir = __DIR__ . DIRECTORY_SEPARATOR;

    // Klassen-Pfad anhand des Namespace hinzufügen
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';

    // Prüfe ob Klassen-Datei vorhanden ist und falls ja, lade diese über den Autoloader,
    /** @noinspection PhpIncludeInspection */
    require $file;
});
