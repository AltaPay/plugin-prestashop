<?php
spl_autoload_register('autoLoader');

/**
 * Method for autoload all the classes within directory
 * @param $class
 * @param null $dir
 */
function autoLoader($class, $dir = null)
{
    require_once __DIR__ . DIRECTORY_SEPARATOR . "IAltapayCommunicationLogger.class.php";
    if (false === strpos($class, 'Altapay')) {
        return;
    }

    if (is_null($dir)) {
        $dir = __DIR__;
    }

    $listDir = scandir(realpath($dir));
    if (isset($listDir) && !empty($listDir)) {
        foreach ($listDir as $listDirkey => $subDir) {
            if ($subDir === '.' || $subDir === '..') {
                continue;
            }
            $file = $dir . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . $class . '.class.php';
            if (file_exists($file)) {
                require_once $file;
                break;
            }
        }
    }
}
