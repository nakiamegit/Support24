<?php
class CheckCustom
{
    # Сканируем диреткорию и при наличии externalFiles переименовываем его
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
                    //self::renameExternalFiles($dir, $file, $externalFiles[$i], $selector);

                    if ($selector === 'Off')  rename($dir . $file, $dir . '_' . $file);
                    if ($selector === 'On')  rename($dir . $file, $dir . substr($file, 1));
                    $i++;
                }
            }
        }
    }

    # Формируем список файлов, которые необходимо переименовать
    public static function getList($custom, $selector)
    {
        if (in_array('local', $custom))
        {
            $selector == 'Off' ? $externalFiles = ['local'] : $externalFiles = ['_local'];
            self::renameExternalFiles('./', $externalFiles, $selector);
            //echo 'local:'; print_r($externalFiles);echo '<br>';
        }

        if (in_array('init', $custom))
        {
            $selector == 'Off' ? $externalFiles = ['init.php'] : $externalFiles = ['_init.php'];
            self::renameExternalFiles('./local/', $externalFiles, $selector);
            self::renameExternalFiles('./bitrix/php_interface/', $externalFiles, $selector);
            //echo 'init:'; print_r($externalFiles);echo '<br>';
        }

        if (in_array('templateDefault', $custom))
        {
            $selector == 'Off' ? $externalFiles = ['bitrix'] : $externalFiles = ['_bitrix'];
            self::renameExternalFiles('./bitrix/templates/.default/components/', $externalFiles, $selector);
            //echo 'templateDefault:'; print_r($externalFiles);echo '<br>';
        }

        if (in_array('customModules', $custom))
        {
            $listModules = scandir('./bitrix/modules/');
            $externalFiles = array();

            if($selector === 'Off') {
                foreach ($listModules as $module) {
                    if (stristr($module, '.') && !is_file('./bitrix/modules/' . $module) && (substr($module, 0, 1) != '_') ) {
                        $externalFiles[] = $module;
                    }
                }
            } else {
                foreach ($listModules as $module) {
                    if (stristr($module, '.') && !is_file('./bitrix/modules/_' . $module)) {
                        $externalFiles[] = $module;
                    }
                }
            }
            $externalFiles = array_values(array_diff($externalFiles, array('..', '.')));
            //echo 'customModules:'; print_r($externalFiles);echo '<br>';

            self::renameExternalFiles('./bitrix/modules/', $externalFiles, $selector);
        }
    }
}

$custom = $_GET['custom'];
$selector = $_GET['selector'];

if(!empty($custom) && !empty($selector)) CheckCustom::getList($custom, $selector);



















####  *  ####  *  ####    Архив   ####  *  ####  *  ####
# Проверка на существование файла/директории.
/* public static function checkEntityExistence($existence): bool
 {
     if(!is_array($existence)) {
         return file_exists($existence);
     } else {
         foreach ($existence as $ex) {
             return file_exists($ex);
         }
     }
 }*/

/* public static function renameExternalFiles($dir, $file, $externalFiles, $selector)
 {

 }*/

/*    public static function getList($custom, $selector)
{
    extract($custom);

    switch (true)
    {
        case isset($local):
            $selector == 'Off' ? $externalFiles = ['local'] : $externalFiles = ['_local'];
            self::renameExternalFiles('./', $externalFiles, $selector);

        case isset($init):
            $selector == 'Off' ? $externalFiles = ['init.php'] : $externalFiles = ['_init.php'];
            self::renameExternalFiles('./local/', $externalFiles, $selector);
            self::renameExternalFiles('./bitrix/php_interface/', $externalFiles, $selector);

        case isset($bitrix):
            $selector == 'Off' ? $externalFiles = ['bitrix'] : $externalFiles = ['_bitrix'];
            self::renameExternalFiles('./bitrix/templates/.default/components/', $externalFiles, $selector);

        case isset($customModules):
            $listModules = scandir('./bitrix/modules/');

            if($selector == 'Off') {
                foreach ($listModules as $module) {
                    if (stristr($module, '.') && !is_file('./bitrix/modules/' . $module) && (substr($module, 0, 1) != '_') ) {
                        //$externalFiles[] = $module;
                    }
                }
            } else {
                foreach ($listModules as $module) {
                    if (stristr($module, '.') && !is_file('./bitrix/modules/_' . $module)) {
                        //$externalFiles[] = $module;
                    }
                }
            }
            $externalFiles = array_values(array_diff($externalFiles, array('..', '.')));
            self::renameExternalFiles('./bitrix/modules/', $externalFiles, $selector);
            break;
    }*/
