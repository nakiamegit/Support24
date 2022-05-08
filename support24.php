<?php
session_start();
header("Access-Control-Allow-Origin: *");

/********---[Classes]---********/
class CheckTemplateBitrix24
{
    protected static function recursiveScanDir($dir, &$structure = array(), &$filesInfo = array()): array
    {
        if(!file_exists($dir))
        {
            self::logSupport24("Scanned path not found", $dir);
            die();
        }

        $elements = scandir($dir);

        foreach($elements as $element)
        {
            $path = $dir . $element;

            if(!is_dir($path))
            {
                $structure['files'][] = $path;

                $filesInfo[$path] = [
                    'size' => filesize($path),
                    'hash' => md5_file($path),
                    'type' => filetype($path),
                ];
            }

            elseif($element != "." && $element != "..")
            {
                $structure['directories'][] = $path . "/";

                self::recursiveScanDir($path . "/", $structure, $filesInfo);
            }
        }

        return [
            'structure' => $structure,
            'filesInfo' => $filesInfo
        ];
    }

    # Comparing the current template with the original
    protected static function compare(array $original, array $curl): array
    {
        $originalDir = $original['structure']['directories'];
        $curlDir = $curl['structure']['directories'];

        $originalFiles = $original['structure']['files'];
        $curlFiles = $curl['structure']['files'];

        $compareStructure['exist']['directories'] = array_diff($originalDir, $curlDir);
        $compareStructure['exist']['files'] = array_diff( $originalFiles, $curlFiles);

        $compareStructure['notExist']['directories'] = array_diff($curlDir, $originalDir);
        $compareStructure['notExist']['files'] = array_diff($curlFiles,  $originalFiles);

        $compareFilesInfo = array();

        if(!empty($curl['filesInfo']))
        {
            foreach ($curl['filesInfo'] as $fileName => $data)
            {
                if(array_key_exists($fileName, $original['filesInfo']))
                {
                    if ($data['hash'] !== $original['filesInfo'][$fileName]['hash']
                        || $data['size'] !== $original['filesInfo'][$fileName]['size']
                        || $data['type'] !== $original['filesInfo'][$fileName]['type'])
                    {
                        $compareFilesInfo[] = $fileName;
                    }
                }
            }
        }

        return [
            'compareStructure' => $compareStructure,
            'compareFiles' => $compareFilesInfo
        ];
    }

    private static function removeDirectory(string $dir)
    {
        if(file_exists($dir))
        {
            $files = array_diff(scandir($dir), ['.','..']);

            foreach ($files as $file)
            {
                (is_dir($dir.'/'.$file)) ? self::removeDirectory($dir.'/'.$file) : unlink($dir.'/'.$file);
            }

            return rmdir($dir);
        }
    }

    protected static function createArchive(string $name, string $path):void
    {
        if(!extension_loaded('zip'))
        {
            CheckCustom::logSupport24("Zip module", "not installed.");
            return;
        }

        $zip = new ZipArchive();
        $zip->open($name, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);

        $files = self::recursiveScanDir($path);

        foreach($files['structure']['files'] as $file)
        {
            if($zip->addFile($file, mb_substr($file, 28)) !== true)
            {
                CheckCustom::logSupport24("File {$file}", "failed to archive.");
                # Остановил на первом файле, чтобы не забивать лог
                return;
            }
        }

        $zip->close();

        $_SESSION['backupArchive'] = true;
    }

    protected static function extractArchive(string $archive, string $pathto):void
    {
        $zip = new ZipArchive();
        $zip->open($archive);

        if($zip->extractTo($pathto) !== true)
        {
            CheckCustom::logSupport24("Archive {$archive}", "failed to unpack.");
            return;
        }

        self::removeDirectory($pathto);
        $zip->extractTo($pathto);

        $zip->close();
    }

    protected static function restore(array $lists):void
    {
        $exDir = $lists['compareStructure']['exist'];
        $notExDir = $lists['compareStructure']['notExist'];

        array_map(fn($dir) => mkdir($dir, 0777, true), $exDir['directories']);

        array_map(fn($file) => unlink($file), $notExDir['files']);
            #CheckCustom::logSupport24("Removed files", $notExDir['files']);

        array_map(fn($dir) => self::removeDirectory($dir), $notExDir['directories']);
            #CheckCustom::logSupport24("Removed directories", $notExDir['directories']);

        $files = array_merge($lists['compareStructure']['exist']['files'], $lists['compareFiles']);

        foreach ($files as $file)
        {
            $originalFile = "https://raw.githubusercontent.com/nakiamegit/templateBitrix24/main" . mb_substr($file, 18);

            $checkStatus = get_headers($originalFile);

            if($checkStatus[0] != "HTTP/1.1 200 OK")
            {
                CheckCustom::logSupport24("Recoverable file {$file}", "not found on the server.");
                continue;
            }

            $curlFile = fopen($file, 'w+');

            if(extension_loaded('curl'))
            {
                $resource = curl_init($originalFile);

                $options = array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FILE => $curlFile,
                    CURLOPT_ENCODING => "",
                    CURLOPT_SSL_VERIFYPEER => false,
                );

                curl_setopt_array($resource, $options);
                curl_exec($resource);

                curl_close($resource);
            }
            else
            {
                $resource = file($originalFile);

                array_map(fn($line) => fwrite($curlFile, $line), $resource);
            }

            #CheckCustom::logSupport24("File {$file}", "restored successfully");

            fclose($curlFile);
        }
        CheckCustom::logSupport24("Restore template Bitrix24", "success");
    }
}

class CheckCustom extends CheckTemplateBitrix24
{
    public static function logSupport24($action, $data):void
    {
        $log = date("d/m H:i") . " | {$action}: " . print_r($data, true) . PHP_EOL;
        $fileLog = "./logSupport24.txt";

        file_put_contents($fileLog, $log, FILE_APPEND);
    }

    # Connecting to the database and executing a query
    private static function queryDatabase(string $query):array
    {
        $settings = include("./bitrix/.settings.php");

        $servername = $settings["connections"]["value"]["default"]["host"];
        $database = $settings["connections"]["value"]["default"]["database"];
        $username = $settings["connections"]["value"]["default"]["login"];
        $password = $settings["connections"]["value"]["default"]["password"];

        $result = array();


        if (!extension_loaded('mysqli'))
        {
            self::logSupport24("Module mysqli not found", "extension will not be able to disable handlers in DB");
            die();
        }

        $DB = new mysqli($servername, $username, $password, $database);

        if ($DB->connect_error)
        {
            self::logSupport24("Connection MySQLi", $DB->connect_error);
            die();
        }

        $resultQuery = $DB->query($query);

        if (!is_bool($resultQuery))
        {
            $result = $resultQuery->fetch_all();
            $resultQuery->free();
        }

        $DB->close();

        return $result;
    }

    public static function backupTable(string $action):void
    {
        $tableName = "b_module_to_module";
        $temptTable = "backup_" . $tableName;

        switch ($action)
        {
            case "create":
                $queryCreatStructure = "CREATE TABLE {$temptTable}  LIKE {$tableName}";
                self::queryDatabase($queryCreatStructure);

                $queryCopyDate = "INSERT INTO {$temptTable} SELECT * FROM {$tableName}";
                self::queryDatabase($queryCopyDate);

                $_SESSION['backupTable'] = "Y";

                self::logSupport24("Backup created", "{$tableName} => {$temptTable}");
                break;

            case "restore":
                $queryCheckTable = "SHOW TABLES LIKE '{$temptTable}'";
                if(empty(self::queryDatabase($queryCheckTable)))
                {
                    self::logSupport24("Table {$temptTable} does not exist", "please create the backup");
                    die();
                }

                $queryTruncate = "TRUNCATE {$tableName}";
                self::queryDatabase($queryTruncate);

                $queryRestore = "INSERT INTO {$tableName} SELECT * FROM {$temptTable}";
                self::queryDatabase($queryRestore);

                unset($_SESSION['offCustom'][array_search("EventHandlers",$_SESSION['offCustom'])]);

                self::logSupport24("Restoring table {$temptTable}", "success");
                break;

            case "delete":
                $queryDeleteTable = "DROP TABLE IF EXISTS {$temptTable}";
                self::queryDatabase($queryDeleteTable);

                $_SESSION['backupTable'] = NULL;

                self::logSupport24("Delete table", $temptTable);
                break;
        }
    }

    # Scanning directory and return all files or those in the filesList
    private static function scanDir(string $dir, array $filesList = array()): array
    {
        if(!file_exists($dir))
        {
            self::logSupport24("Scanned path not found", $dir);
            return array();
        }

        $dirFiles = scandir($dir);

        foreach ($filesList as $file)
        {
            if(!file_exists($dir . $file))
            {
                unset($filesList[$file]);

                self::logSupport24("Object not found", $dir . $file);
            }
        }

        $dirFiles = array_diff($dirFiles, array('..', '.'));

        !empty($filesList) ? $result = array_intersect($dirFiles, $filesList) : $result = $dirFiles;

        return array_values($result);
    }

    private static function renameExternalFiles(string $dir, array $externalFiles, string $selector): void
    {
        $extFiles = self::scanDir($dir, $externalFiles);

        if(empty($extFiles)) return;

        switch ($selector)
        {
            case "off":
                $rename = fn($file) => rename($dir . $file, $dir . '_bx_' . $file);
                array_map($rename, $externalFiles);

                $session = fn($file) => $_SESSION['offCustom'][] = $file;
                array_map($session, $externalFiles);

                self::logSupport24("Disabled objects in {$dir}", $externalFiles);
                break;

            case "on":
                $rename = fn($file) => rename($dir . $file, $dir . mb_substr($file, 4));
                array_map($rename, $externalFiles);

                $session = fn($file) => $_SESSION['onCustom'][] = mb_substr($file, 4);
                array_map($session, $externalFiles);

                self::logSupport24("Enabled objects in {$dir}", $externalFiles);
                break;
        }
    }

    private static function moveExternalModules(string $dir, array $extModules, string $selector): void
    {
        if(empty($extModules))
        {
            self::logSupport24("External modules", "not found");
            return;
        }

        $tempDir = "./bitrix/modules/_bx_/";
        $originalDir = "./bitrix/modules/";

        if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

        $extModules = self::scanDir($dir, $extModules);

        $pathModules = array_map(fn($arrModules) => $originalDir . $arrModules, $extModules);
        $tempPathModules = array_map(fn($arrModules) => $tempDir . $arrModules, $extModules);

        $callback = fn($a, $b) => rename($a, $b);

        switch ($selector)
        {
            case "off":
                array_map($callback, $pathModules, $tempPathModules);

                $_SESSION['offCustom'][] = "ExternalModules";

                self::logSupport24("Disabled external modules", $extModules);
                break;

            case "on":
                array_map($callback, $tempPathModules, $pathModules);

                $_SESSION['onCustom'][] = "ExternalModules";

                self::logSupport24("Enabled external modules", $extModules);
                break;
        }
    }

    # Disable event handlers from an array extModules
    private static function disableEventHandlers(string $handlersID, string $selector): void
    {
        switch ($selector)
        {
            case "off":
                $queryDisableHandlers = "
                                    UPDATE 
                                        b_module_to_module 
                                    SET 
                                        MESSAGE_ID = CONCAT('_bx_', MESSAGE_ID)
                                    WHERE 
                                          ID IN ('{$handlersID}')
                ";

                self::queryDatabase($queryDisableHandlers);

                $_SESSION['offCustom'][] = "EventHandlers";

                self::logSupport24("Disabled event handlers", $handlersID);
                break;

            case "on":
                $queryDisableHandlers = "
                                    UPDATE 
                                        b_module_to_module 
                                    SET 
                                        MESSAGE_ID = REPLACE(MESSAGE_ID, '_bx_', '')
                                    WHERE 
                                          ID IN ('{$handlersID}')";

                self::queryDatabase($queryDisableHandlers);

                $_SESSION['onCustom'][] = "EventHandlers";

                self::logSupport24("Enabled event handlers", $handlersID);
                break;
        }
    }

    # Method call builder
    public static function operationsPerformer(array $custom, string $selector):void
    {
        if (in_array("local", $custom))
        {
            $selector === "off" ? $externalFiles = ['local'] : $externalFiles = ['_bx_local'];

            self::renameExternalFiles("./", $externalFiles, $selector);
        }

        if (in_array("init", $custom))
        {
            switch ($selector)
            {
                case "off":
                    $externalFiles = ['init.php'];
                    $selective = "AND MESSAGE_ID NOT LIKE('_bx_%')";
                    break;

                case "on":
                    $externalFiles = ['_bx_init.php'];
                    $selective = "AND MESSAGE_ID LIKE('_bx_%')";
                    break;

                default:
                    self::logSupport24("Selector passed incorrectly", $selector);
                    break;
            }

            $queryExModules = "SELECT ID FROM b_module_to_module WHERE TO_MODULE_ID LIKE('%.%') {$selective}";
            $resultExModules = self::queryDatabase($queryExModules);

            if(!empty($resultExModules))
            {
                $outArray = call_user_func_array("array_merge", $resultExModules);
                $handlersID = implode("','", $outArray);

                if (empty($_SESSION['backupTable']) && $selector === "off")
                {
                    self::backupTable("create");
                }


                self::disableEventHandlers($handlersID, $selector);
            }

            self::renameExternalFiles("./local/php_interface/", $externalFiles, $selector);
            self::renameExternalFiles("./bitrix/php_interface/", $externalFiles, $selector);
        }

        if (in_array("customModules", $custom))
        {
            $selector === "off" ? $dir = "./bitrix/modules/" : $dir = "./bitrix/modules/_bx_/";

            $modulesList = self::scanDir($dir);
            $extModules = array();

            foreach ($modulesList as $module)
            {
                if (stristr($module, '.') && !is_file($dir . $module)) $extModules[] = $module;
            }

            self::moveExternalModules($dir, $extModules, $selector);
        }

        if (in_array("templateDefault", $custom))
        {
            $selector == "off" ? $externalFiles = ['bitrix'] : $externalFiles = ['_bx_bitrix'];

            self::renameExternalFiles("./bitrix/templates/.default/components/", $externalFiles, $selector);
        }

        if (in_array("templateBitrix24", $custom))
        {
            $backup = "./bitrix/templates/_bx_bitrix24.zip";
            $path = "./bitrix/templates/bitrix24/";

            switch ($selector)
            {
                case "off":
                    $_SESSION['backupArchive'] ?? CheckTemplateBitrix24::createArchive($backup, $path);

                    $originalTemplate = json_decode(file_get_contents("https://raw.githubusercontent.com/nakiamegit/templateBitrix24/main/originalTemplate.txt"), true);
                    $curlTemplate = CheckTemplateBitrix24::recursiveScanDir($path);

                    $compare = CheckTemplateBitrix24::compare($originalTemplate, $curlTemplate);

                    CheckTemplateBitrix24::restore($compare);
                    break;

                case "on":
                    CheckTemplateBitrix24::extractArchive($backup, $path);
                    break;

                default:
                    self::logSupport24("Selector passed incorrectly", $selector);
                    break;
            }
        }
    }
}

/********---[Work area]---********/
if(!empty($_GET['custom']) && !empty($_GET['selector']))
{
    CheckCustom::operationsPerformer($_GET['custom'], $_GET['selector']);
}

if($_GET['checkDisabledCustom'] === 'Y')
{
    if(empty($_SESSION['offCustom']))
    {
        header("HTTP/1.1 404 Not Found");
    }
    elseif(!empty($_SESSION['onCustom']))
    {
        $_SESSION['offCustom'] = array_diff(array_unique($_SESSION['offCustom']), array_unique($_SESSION['onCustom']));
        $_SESSION['onCustom'] = array();

        print_r($_SESSION['offCustom']);
    }
}

if($_GET['backupTable'] === "restore")
{
    CheckCustom::backupTable("restore");
}

if($_GET['checkBackupTable'] === 'Y')
{
    empty($_SESSION['backupTable']) ? header("HTTP/1.1 404 Not Found") : print_r($_SESSION['backupTable']);
}

if($_GET['delFile'] === 'Y')
{
    session_destroy();

    if (file_exists("./bitrix/modules/_bx_")) rmdir("./bitrix/modules/_bx_");

    CheckCustom::backupTable("delete");

    unlink("./bitrix/templates/_bx_bitrix24.zip");
    unlink("./logSupport24.txt");
    unlink("./support24.php");

    echo '<script>window.close()</script>';
}

if($_GET['zip'] === 'Y' && !extension_loaded('zip'))
{
    header("HTTP/1.1 204 No Content");
}

if($_GET['mysqli'] === 'Y' && !extension_loaded('mysqli'))
{
    header("HTTP/1.1 204 No Content");
}