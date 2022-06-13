<?php
error_reporting(0);
session_start();
header("Access-Control-Allow-Origin: *");

/*******-------*******[   Authentication  ]*******-------*******/
/*if(!isset($_COOKIE['UNIQUE_ID']) && !empty($_POST['UNIQUE_ID']))
{
    setcookie("UNIQUE_ID", $_POST['UNIQUE_ID'], time()+3600);
}*/
if(!empty($_POST['login']) && !empty($_POST['password']))
{
    SecureData::auth($_POST['login'], $_POST['password']);
}

if (!isset($_COOKIE['hash']) || $_COOKIE['hash'] != md5($_COOKIE['login']))
{
    SecureData::checkAuth();
}

/*******-------*******[   Work area   ]*******-------*******/
if(!empty($_GET['custom']) && !empty($_GET['selector']))
{
    CheckCustom::operationsPerformer($_GET['custom'], $_GET['selector']);
}

if($_GET['backupTable'] === "restore")
{
    CheckCustom::backupTable("restore");
}

if($_GET['delFile'] === 'Y')
{
    CheckCustom::removeTraces();
}

# Notifications
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

if($_GET['checkBackupTable'] === 'Y')
{
    empty($_SESSION['backupTable']) ? header("HTTP/1.1 404 Not Found") : print_r($_SESSION['backupTable']);
}

if($_GET['zip'] === 'Y' && !extension_loaded('zip'))
{
    header("HTTP/1.1 204 No Content");
}

if($_GET['mysqli'] === 'Y' && !extension_loaded('mysqli'))
{
    header("HTTP/1.1 204 No Content");
}

/*******-------*******[   Classes   ]*******-------*******/
class CheckCustom
{
    protected static function getConnection()
    {
        $settings = include("./bitrix/.settings.php");

        $servername = $settings["connections"]["value"]["default"]["host"];
        $database = $settings["connections"]["value"]["default"]["database"];
        $username = $settings["connections"]["value"]["default"]["login"];
        $password = $settings["connections"]["value"]["default"]["password"];

        if (!extension_loaded('mysqli'))
        {
            self::logSupport24("Module mysqli not found", "extension will not be able to disable handlers in DB");
            exit;
        }

        $DB = new mysqli($servername, $username, $password, $database);

        if ($DB->connect_error)
        {
            self::addLog("Connection MySQLi", $DB->connect_error);
            exit;
        }

        return $DB;
    }

    protected static function sendQuery(string $query)
    {
        $DB = self::getConnection();

        $resultQuery = $DB->query($query);

        if (is_bool($resultQuery))
        {
            return $resultQuery;
        }

        $data = $resultQuery->fetch_all();
        $resultQuery->free();

        return call_user_func_array("array_merge", $data);
    }


    public static function addLog($action, $data):void
    {
        $log = date("d/m H:i") . " | {$action}: " . print_r($data, true) . PHP_EOL;
        $fileLog = "./logSupport24.txt";

        file_put_contents($fileLog, $log, FILE_APPEND);
    }


    # Scanning directory and return all files or those in the filesList
    private static function existFile(string $dir, array $filesList = []): array
    {
        if(!file_exists($dir))
        {
            self::addLog("Scanned path not found", $dir);
            return [];
        }

        $dirFiles = scandir($dir);
        $dirFiles = array_diff($dirFiles, array('..', '.'));

        foreach ($filesList as $file)
        {
            if(!file_exists($dir . $file))
            {
                unset($filesList[$file]);

                self::addLog("Object not found", $dir . $file);
            }
        }

        !empty($filesList) ? $result = array_intersect($dirFiles, $filesList) : $result = $dirFiles;

        return array_values($result);
    }

    private static function renameExternalFiles(string $dir, array $externalFiles, string $selector): void
    {
        $extFiles = self::existFile($dir, $externalFiles);

        if(empty($extFiles)) return;

        switch ($selector)
        {
            case "off":
                $rename = fn($file) => rename($dir . $file, $dir . '_bx_' . $file);
                array_map($rename, $externalFiles);

                $session = fn($file) => $_SESSION['offCustom'][] = $file;
                array_map($session, $externalFiles);

                self::addLog("Disabled objects in {$dir}", $externalFiles);
                break;

            case "on":
                $rename = fn($file) => rename($dir . $file, $dir . mb_substr($file, 4));
                array_map($rename, $externalFiles);

                $session = fn($file) => $_SESSION['onCustom'][] = mb_substr($file, 4);
                array_map($session, $externalFiles);

                self::addLog("Enabled objects in {$dir}", $externalFiles);
                break;
        }
    }

    private static function moveExternalModules(string $dir, string $selector): void
    {
        $modulesList = self::existFile($dir);
        $extModules = array();

        foreach ($modulesList as $module)
        {
            if (stristr($module, '.') && !is_file($dir . $module)) $extModules[] = $module;
        }

        if(empty($extModules)) return;

        $tempDir = "./bitrix/modules/_bx_/";
        $originalDir = "./bitrix/modules/";

        if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

        $pathModules = array_map(fn($arrModules) => $originalDir . $arrModules, $extModules);
        $tempPathModules = array_map(fn($arrModules) => $tempDir . $arrModules, $extModules);

        $callback = fn($a, $b) => rename($a, $b);

        switch ($selector)
        {
            case "off":
                array_map($callback, $pathModules, $tempPathModules);

                $_SESSION['offCustom'][] = "ExternalModules";

                self::addLog("Disabled external modules", $extModules);
                break;

            case "on":
                array_map($callback, $tempPathModules, $pathModules);

                $_SESSION['onCustom'][] = "ExternalModules";

                self::addLog("Enabled external modules", $extModules);
                break;
        }
    }

    private static function disableEventHandlers(string $handlersID, string $selector): void
    {
        $getHandlers = "
                            SELECT 
                                ID, TO_MODULE_ID, MESSAGE_ID
                            FROM 
                                b_module_to_module 
                            WHERE 
                                ID IN ('{$handlersID}')
        ";

        switch ($selector)
        {
            case "off":
                $offHandlers = "
                                    UPDATE 
                                        b_module_to_module 
                                    SET 
                                        MESSAGE_ID = CONCAT('_bx_', MESSAGE_ID)
                                    WHERE 
                                          ID IN ('{$handlersID}')
                ";

                $dataHandlers = self::sendQuery($getHandlers);
                self::sendQuery($offHandlers);

                $_SESSION['offCustom'][] = "eventHandlers";

                self::addLog("Disabled event handlers", $dataHandlers);
                break;

            case "on":
                $onHandlers = "
                                    UPDATE 
                                        b_module_to_module 
                                    SET 
                                        MESSAGE_ID = REPLACE(MESSAGE_ID, '_bx_', '')
                                    WHERE 
                                          ID IN ('{$handlersID}')";

                self::sendQuery($onHandlers);
                $dataHandlers = self::sendQuery($getHandlers);

                $_SESSION['onCustom'][] = "eventHandlers";

                self::addLog("Enabled event handlers", $dataHandlers);
                break;
        }
    }


    # Builder
    public static function operationsPerformer(array $custom, string $selector):void
    {
        if (in_array("local", $custom))
        {
            $selector === "off" ? $externalFiles = ['local'] : $externalFiles = ['_bx_local'];

            self::renameExternalFiles("./", $externalFiles, $selector);
        }

        if (in_array("init", $custom))
        {
            $selector === "off" ? $externalFiles = ['init.php'] : $externalFiles = ['_bx_init.php'];

            self::renameExternalFiles("./local/php_interface/", $externalFiles, $selector);
            self::renameExternalFiles("./bitrix/php_interface/", $externalFiles, $selector);
        }

        if (in_array("eventHandlers", $custom))
        {
            $selector === "off" ? $condition = "NOT LIKE('_bx_%')" : $condition = "LIKE('_bx_%')";

            $queryExModules = "SELECT ID FROM b_module_to_module WHERE TO_MODULE_ID LIKE('%.%') AND MESSAGE_ID {$condition}";
            $resultExModules = self::sendQuery($queryExModules);

            if(!empty($resultExModules))
            {
                $handlersID = implode("','", $resultExModules);

                if (empty($_SESSION['backupTable']) && $selector === "off")
                {
                    self::backupTable("create");
                }

                self::disableEventHandlers($handlersID, $selector);
            }
        }

        if (in_array("customModules", $custom))
        {
            $selector === "off" ? $dir = "./bitrix/modules/" : $dir = "./bitrix/modules/_bx_/";

            self::moveExternalModules($dir, $selector);
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
                    $_SESSION['backupArchive'] ?? TemplateBitrix24::createArchive($backup, $path);

                    $resource = "https://raw.githubusercontent.com/nakiamegit/templateBitrix24/main/originalTemplate.txt";

                    $originalTemplate = json_decode(file_get_contents($resource), true);
                    if(ini_get('allow_url_fopen') != true)
                    {
                        $originalTemplate = json_decode(TemplateBitrix24::openFileViaSockets($resource), true);
                    }

                    $curlTemplate = TemplateBitrix24::recursiveScanDir($path);

                    $compare = TemplateBitrix24::compare($originalTemplate, $curlTemplate);

                    TemplateBitrix24::restore($compare);
                    break;

                case "on":
                    TemplateBitrix24::extractArchive($backup, $path);
                    break;

                default:
                    self::addLog("Selector passed incorrectly", $selector);
                    break;
            }
        }
    }

    public static function backupTable(string $action):void
    {
        $tableName = "b_module_to_module";
        $temptTable = "backup_" . $tableName;

        switch ($action)
        {
            case "create":
                $createStructure = "CREATE TABLE {$temptTable}  LIKE {$tableName}";
                self::sendQuery($createStructure);

                $copyDate = "INSERT INTO {$temptTable} SELECT * FROM {$tableName}";
                self::sendQuery($copyDate);

                $_SESSION['backupTable'] = "Y";

                self::addLog("Backup created", "{$tableName} => {$temptTable}");
                break;

            case "restore":
                $checkTable = "SHOW TABLES LIKE '{$temptTable}'";

                if(empty(self::sendQuery($checkTable)))
                {
                    self::addLog("Table {$temptTable} does not exist", "please create the backup");
                    exit;
                }

                self::sendQuery("TRUNCATE {$tableName}");

                $queryRestore = "INSERT INTO {$tableName} SELECT * FROM {$temptTable}";
                self::sendQuery($queryRestore);

                unset($_SESSION['offCustom'][array_search("eventHandlers",$_SESSION['offCustom'])]);

                self::addLog("Restoring table {$temptTable}", "success");
                break;

            case "delete":
                $queryDeleteTable = "DROP TABLE {$temptTable}";

                if(self::sendQuery($queryDeleteTable) != false)
                {
                    $_SESSION['backupTable'] = NULL;
                    self::addLog("Delete table", $temptTable);
                }
                break;
        }
    }

    public static function removeTraces():void
    {
        session_destroy();

        self::backupTable("delete");

        if(file_exists("./bitrix/modules/_bx_")) rmdir("./bitrix/modules/_bx_");
        if(file_exists("./bitrix/templates/_bx_bitrix24.zip")) unlink("./bitrix/templates/_bx_bitrix24.zip");
        if(file_exists("./logSupport24.txt")) unlink("./logSupport24.txt");
        if(file_exists("./support24.php")) unlink("./support24.php");
    }
}

class TemplateBitrix24 extends CheckCustom
{
    protected static function recursiveScanDir($dir, &$structure = array(), &$filesInfo = array()): array
    {
        if(!file_exists($dir))
        {
            CheckCustom::addLog("Scanned path not found", $dir);
            exit;
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

    private static function removeDirectory(string $dir):void
    {
        if(file_exists($dir))
        {
            $files = array_diff(scandir($dir), ['.','..']);

            foreach ($files as $file)
            {
                (is_dir($dir.'/'.$file)) ? self::removeDirectory($dir.'/'.$file) : unlink($dir.'/'.$file);
            }

            rmdir($dir);
        }
    }

    protected static function createArchive(string $name, string $path):void
    {
        if(!extension_loaded('zip'))
        {
            CheckCustom::addLog("Zip module", "not installed.");
            return;
        }

        $zip = new ZipArchive();
        $zip->open($name, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);

        $files = self::recursiveScanDir($path);

        foreach($files['structure']['files'] as $file)
        {
            if($zip->addFile($file, mb_substr($file, 28)) !== true)
            {
                CheckCustom::addLog("File {$file}", "failed to archive.");
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
            CheckCustom::addLog("Archive {$archive}", "failed to unpack.");
            return;
        }

        self::removeDirectory($pathto);
        $zip->extractTo($pathto);

        $zip->close();
    }

    protected static function openFileViaSockets(string $url):string
    {
        $arrURL = parse_url($url);

        $port = $arrURL["port"] ?? "443";
        $query = $arrURL["path"] . $arrURL["query"];

        $fp = fsockopen("ssl://{$arrURL["host"]}", $port, $errno, $errstr, 1024);

        if(!$fp)
        {
            CheckCustom::addLog("Fsockopen - {$errno}", $errstr);
            exit;
        }

        $request = "GET {$query} HTTP/1.1\r\n";
        $request .= "Host:{$arrURL["host"]}\r\n";
        $request .= "Connection: close\r\n";
        $request .= "\r\n";

        fwrite($fp, $request);

        $checkBody = false;
        $body = "";

        while(!feof($fp))
        {
            $line = fgets($fp, 1024);

            if($checkBody) $body .= $line;
            if ($line == "\r\n") $checkBody = true;
        }

        fclose($fp);

        return $body;
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
            $curlFile = fopen($file, 'w+');

            if(extension_loaded('curl'))
            {
                $checkResource = curl_init($originalFile);

                curl_setopt($checkResource, CURLOPT_NOBODY, true);
                curl_exec($checkResource);

                $checkStatus = curl_getinfo($checkResource, CURLINFO_HTTP_CODE);

                curl_close($checkResource);

                if($checkStatus != 200)
                {
                    CheckCustom::addLog("Recoverable file {$file}", "not found on the server.");
                    continue;
                }

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
            elseif (ini_get('allow_url_fopen') != true)
            {
                if (@file_get_contents() === false)
                {
                    CheckCustom::addLog("Recoverable file {$file}", "not found on the server.");
                    continue;
                }

                $resource = self::openFileViaSockets($originalFile);
                file_put_contents($file, $resource);
            }
            else
            {
                $checkStatus = get_headers($originalFile);

                if($checkStatus[0] != "HTTP/1.1 200 OK")
                {
                    CheckCustom::addLog("Recoverable file {$file}", "not found on the server.");
                    continue;
                }

                $resource = file($originalFile);

                array_map(fn($line) => fwrite($curlFile, $line), $resource);
            }
            #CheckCustom::logSupport24("File {$file}", "restored successfully");
            fclose($curlFile);
        }
        CheckCustom::addLog("Restore template Bitrix24", "completed");
    }
}

class SecureData extends CheckCustom
{
    public static function auth(string $login, string $password):void
    {
        $query = "
            SELECT 
                U.ID, U.LOGIN, U.ACTIVE, U.PASSWORD, U.LOGIN_ATTEMPTS, U.CONFIRM_CODE, U.EMAIL
            FROM 
                b_user U 
                    LEFT JOIN 
                        b_user_group UG 
                    ON 
                        U.ID=UG.USER_ID 
                            AND 
                                ((UG.DATE_ACTIVE_FROM IS NULL) OR (UG.DATE_ACTIVE_FROM <= NOW())) 
                            AND 
                                ((UG.DATE_ACTIVE_TO IS NULL) OR (UG.DATE_ACTIVE_TO >= NOW()))
            WHERE 
                U.LOGIN = '". self::escapeInput($login) ."'
                    AND 
                        UG.GROUP_ID = 1 
                    AND 
                        U.ACTIVE = 'Y' 
                    AND 
                        (EXTERNAL_AUTH_ID IS NULL OR EXTERNAL_AUTH_ID = '')
			";

        $data = CheckCustom::sendQuery($query);

        $hash = $data[3];
        $hashLength = mb_strlen($hash);

        switch (true)
        {
            case ($hashLength > 100):
                $salt = mb_substr($hash, 3, 16);
                $hashEntered = crypt($password, '$6$' . $salt . '$');
                break;

            case ($hashLength > 32):
                $salt = mb_substr($hash, 0, -32);
                $hashEntered = $salt.md5($salt . $password);
                break;

            default:
                $hashEntered = md5($password);
        }

        if(function_exists('hash_equals') && hash_equals($hash, $hashEntered))
        {
            setcookie('login', $_COOKIE['login'] = $login);
            setcookie('hash', $_COOKIE['hash'] = md5($_COOKIE['login']));
        }
        elseif ($hash == $hashEntered)
        {
            setcookie('login', $_COOKIE['login'] = $login);
            setcookie('hash', $_COOKIE['hash'] = md5($_COOKIE['login']));
        }
        else
        {
            CheckCustom::addLog("Authorization attempt", $_SERVER['REMOTE_ADDR']);

            header('HTTP/1.0 403 Forbidden', true, 403);
            exit;
        }
    }

    public static function checkAuth():void
    {
        if (isset($_SESSION['SESS_AUTH']) && $_SESSION['SESS_AUTH']['AUTHORIZED'] == 'Y' && $_SESSION["SESS_AUTH"]["ADMIN"] === true)
        {
            setcookie('login', $_COOKIE['login'] = $_SESSION['SESS_AUTH']['LOGIN']);
            setcookie('hash', $_COOKIE['hash'] = md5($_COOKIE['login']));
        }
        else
        {
            header('HTTP/1.0 403 Forbidden', true, 403);
            exit;
        }
    }

    public static function escapeInput(string $string):string
    {
        $newString = strip_tags($string);
        $newString = htmlentities($string, ENT_QUOTES, 'UTF-8');
        $newString = mysqli_real_escape_string(CheckCustom::getConnection(), $string);

        return $newString;
    }
}