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

set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
spl_autoload_extensions('.php');
spl_autoload_register('spl_autoload');
