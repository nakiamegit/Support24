<?php
session_start();
header("Access-Control-Allow-Origin: *");

class CheckCustom
{
    # The directory is scanned and if there are externalFiles in it, they are renamed
    public static function renameExternalFiles($dir, $externalFiles, $selector)
    {
        if(file_exists($dir))
        {
            $files = scandir($dir);
            $i = 0;

            foreach ($files as $file)
            {
                if (file_exists($dir . $externalFiles[$i]) && in_array($file, $externalFiles))
                {
                    if ($selector === 'Off')
                    {
                        if(empty($_SESSION['offCustom'])) $_SESSION['offCustom'] = array();
                        array_push($_SESSION['offCustom'], $dir . $file);

                        rename($dir . $file, $dir . '_bx_' . $file);
                    }
                    if ($selector === 'On')
                    {
                        if(empty($_SESSION['onCustom'])) $_SESSION['onCustom'] = array();
                        array_push($_SESSION['onCustom'], $dir . substr($file, 4));

                        rename($dir . $file, $dir . substr($file, 4));
                    }

                    $i++;
                }
            }
        }
    }
    # A list of files to be renamed is formed
    public static function getList($custom, $selector)
    {
        if (in_array('local', $custom))
        {
            $selector == 'Off' ? $externalFiles = ['local'] : $externalFiles = ['_bx_local'];
            self::renameExternalFiles('./', $externalFiles, $selector);
            //echo 'local:'; print_r($externalFiles);echo '<br>';
        }
        if (in_array('init', $custom))
        {
            $selector == 'Off' ? $externalFiles = ['init.php'] : $externalFiles = ['_bx_init.php'];
            self::renameExternalFiles('./local/php_interface/', $externalFiles, $selector);
            self::renameExternalFiles('./bitrix/php_interface/', $externalFiles, $selector);
            //echo 'init:'; print_r($externalFiles);echo '<br>';
        }
        if (in_array('templateDefault', $custom))
        {
            $selector == 'Off' ? $externalFiles = ['bitrix'] : $externalFiles = ['_bx_bitrix'];
            self::renameExternalFiles('./bitrix/templates/.default/components/', $externalFiles, $selector);
            //echo 'templateDefault:'; print_r($externalFiles);echo '<br>';
        }
        if (in_array('customModules', $custom))
        {
            $listModules = scandir('./bitrix/modules/');
            $externalFiles = array();
            if($selector === 'Off') {
                foreach ($listModules as $module) {
                    if (stristr($module, '.') && !is_file('./bitrix/modules/' . $module) && substr($module, 0, 4) != '_bx_' ) {
                        $externalFiles[] = $module;
                    }
                }
            } else {
                foreach ($listModules as $module) {
                    if (stristr($module, '.') && !is_file('./bitrix/modules/' . $module) && substr($module, 0, 4) === '_bx_') {
                        $externalFiles[] = $module;
                    }
                }
            }
            $externalFiles = array_values(array_diff($externalFiles, array('..', '.')));
            // echo 'customModules:'; print_r($externalFiles);echo '<br>';
            self::renameExternalFiles('./bitrix/modules/', $externalFiles, $selector);
        }
    }
}

# Work area
if(!empty($_GET['custom']) && !empty($_GET['selector'])) CheckCustom::getList($_GET['custom'], $_GET['selector']);

if(!empty($_GET['delFile']) && $_GET['delFile'] === 'Y')
{
    session_destroy();
    unlink('./checkCustom.php');
    echo '<script>window.close()</script>';
}

if(!empty($_GET['getListCustom']) && $_GET['getListCustom'] === 'Y')
{
    $listModules = scandir('./bitrix/modules/');
    //$customModules = array();
    foreach ($listModules as $module) {
        if (stristr($module, '.') && !is_file('./bitrix/modules/' . $module)) {
            //$customModules[] = $module;
            echo $module . '<br/>';
        }
    }
}

if($_GET['checkDisabledCustom'] === 'Y')
{
    if(empty($_SESSION['offCustom']) && empty($_SESSION['onCustom']))
    {
        header("HTTP/1.1 404 Not Found");
    }
    elseif (!empty($_SESSION['offCustom']) && !empty($_SESSION['onCustom']))
    {
        $_SESSION['offCustom'] = array_diff(array_unique($_SESSION['offCustom']), array_unique($_SESSION['onCustom']));
        $_SESSION['onCustom'] = array();

        print_r($_SESSION['offCustom']);
    }
    elseif (empty($_SESSION['offCustom']))
    {
        header("HTTP/1.1 404 Not Found");
    }
    else {
        print_r($_SESSION['offCustom']);
    }
}