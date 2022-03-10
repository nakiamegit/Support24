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
                    if ($selector === 'Off')  rename($dir . $file, $dir . '_' . $file);
                    if ($selector === 'On')  rename($dir . $file, $dir . substr($file, 1));
                    $i++;
                }
            }
            $log = fopen("checkCustom.txt", "a+");
            $content = $selector. ': ' . json_encode($externalFiles, JSON_UNESCAPED_UNICODE) . PHP_EOL;
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
            self::renameExternalFiles('./local/php_interface/', $externalFiles, $selector);
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
                    if (stristr($module, '.') && !is_file('./bitrix/modules/' . $module) && substr($module, 0, 1) != '_' ) {
                        $externalFiles[] = $module;
                    }
                }
            } else {
                foreach ($listModules as $module) {
                    if (stristr($module, '.') && !is_file('./bitrix/modules/' . $module) && substr($module, 0, 1) === '_') {
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

if(!empty($_GET['custom']) && !empty($_GET['selector'])) CheckCustom::getList($_GET['custom'], $_GET['selector']);

if(!empty($_GET['delFile']) && $_GET['delFile'] === 'Y')
{
    unlink('./checkCustom.php');
    echo '<script>window.close()</script>';
}