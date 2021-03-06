formCustom = document.getElementById('formCustom');
buttonForm = document.getElementById("buttonForm");

formAuth = document.getElementById('formAuth');
buttonAuth = document.getElementById("buttonAuth");



buttonDelete = document.getElementById("deleteCheckCustom");
buttonUpload = document.getElementById("uploadCheckCustom");
buttonLog = document.getElementById("getLog");
buttonRestoreTable  = document.getElementById('restoreTable');
buttonClose = document.getElementById("buttonClose");
buttonCheckboxAll = document.getElementById('checkboxCustom-1');


divContainer = document.getElementById('container');
divNotifyIsFileRestore = document.getElementById('notifyIsFileRestore');
divNotifyDisabledCustom = document.getElementById('notifyDisabledCustom');
divRestoreTable = document.getElementById('divRestoreTable');
divNotifyMissingMySQLi = document.getElementById('notifyMissingMySQLi');
divNotifyMissingZIP = document.getElementById('notifyMissingZIP');

chrome.tabs.query({active:true, currentWindow: true},function(tabsArray)
{
    let tab = tabsArray[0];
    let tabUrl = tab.url;

    let curlURL = tabUrl.split('/');
    let domainName =  curlURL[0] + '//' + curlURL[2] + '/';

    formCustom.action = domainName + 'support24.php';
    formAuth.action = domainName + 'support24.php';
    buttonDelete.href =  domainName + 'support24.php?delFile=Y';
    buttonUpload.href = domainName + 'bitrix/admin/php_command_line.php';
    buttonRestoreTable.href = domainName + 'support24.php?backupTable=restore';

    buttonLog.addEventListener("click", async () => {
        let params =    'scrollbars = no, resizable = no,' +
                        'status = no, location = no,' +
                        'toolbar = no, menubar = no,' +
                        'width = 700, height = 500,' +
                        'left = 90, top = 120';

        open(domainName + 'logSupport24.txt?upd=' + (0|Math.random()*9e6).toString(36), 'Log', params);
    });

    function checkAuth()
    {
        let xhr = new XMLHttpRequest();

        xhr.open('GET', domainName + 'support24.php',  true)
        xhr.send()

        xhr.onreadystatechange = function()
        {
            if (xhr.readyState != 4) { return }

            if (xhr.status === 200) {
                formCustom.style.display = 'block';
            } else {
                formAuth.style.display = 'block';
            }
        }
    }

    function checkIsFileRestore()
    {
        let xhr = new XMLHttpRequest();

        xhr.open('GET', domainName + 'bitrix/bx_fmt.php',  true)
        xhr.send()

        xhr.onreadystatechange = function()
        {
            if (xhr.readyState != 4) { return }
            if (xhr.status !== 200) { divNotifyIsFileRestore.style.display = 'block'; }
            else { divNotifyIsFileRestore.style.display = 'none'; }
        }
    }

    function checkDisabledCustom()
    {
        let xhr = new XMLHttpRequest();

        xhr.open('GET', domainName + 'support24.php?checkDisabledCustom=Y',  true)
        xhr.send()

        xhr.onreadystatechange = function()
        {
            if (xhr.status === 200) { divNotifyDisabledCustom.style.display = 'block'; }
            else { divNotifyDisabledCustom.style.display = 'none'; }
        }
    }

    function checkBackupTable()
    {
        let xhr = new XMLHttpRequest();

        xhr.open('GET', domainName + 'support24.php?checkBackupTable=Y',  true)
        xhr.send()

        xhr.onreadystatechange = function()
        {
            if (xhr.status === 200) { divRestoreTable.style.display = 'block'; }
            else { divRestoreTable.style.display = 'none'; }
        }
    }

    function checkMySQLi()
    {
        let xhr = new XMLHttpRequest();

        xhr.open('GET', domainName + 'support24.php?mysqli=Y',  true)
        xhr.send()

        xhr.onreadystatechange = function()
        {
            if (xhr.status === 204) { divNotifyMissingMySQLi.style.display = 'block'; }
            else { divNotifyMissingMySQLi.style.display = 'none'; }
        }
    }

    function checkZIP()
    {
        let xhr = new XMLHttpRequest();

        xhr.open('GET', domainName + 'support24.php?zip=Y',  true)
        xhr.send()

        xhr.onreadystatechange = function()
        {
            if (xhr.status === 204) { divNotifyMissingZIP.style.display = 'block'; }
            else { divNotifyMissingZIP.style.display = 'none'; }
        }
    }

    checkAuth();
    checkIsFileRestore();
    checkMySQLi();
    checkZIP();

    checkDisabledCustom();
    checkBackupTable();

    setInterval(checkDisabledCustom, 1000);
    setInterval(checkBackupTable, 3000);
});

function reloadTab() {
    window.location.reload();
}

buttonAuth.addEventListener("click", async () => {
    setInterval( function(){ reloadTab() }, 100 );
});

buttonForm.addEventListener("click", async () => {
    let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    chrome.scripting.executeScript({
        target: { tabId: tab.id },
        function: reloadTab,
    });
});

buttonRestoreTable.addEventListener("click", async () => {
    let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    chrome.scripting.executeScript({
        target: { tabId: tab.id },
        function: reloadTab,
    });
});

buttonDelete.addEventListener("click", async () => {
    let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    chrome.scripting.executeScript({
        target: { tabId: tab.id },
        function: reloadTab,
    });

    setInterval( function(){ reloadTab() }, 100 );
});

buttonClose.addEventListener("click", async () => {
    window.close();
});

buttonUpload.addEventListener("click", async () => {
    let text = `$file = 'https://raw.githubusercontent.com/nakiamegit/Support24/main/support24.php';
                if(ini_get('allow_url_fopen') != true)
                {
                    $arrURL = parse_url($file); $port = $arrURL["port"] ?? "443";
                    $host = $arrURL["host"]; $query = $arrURL["path"] . $arrURL["query"];
                    $fp = fsockopen("ssl://{$host}", $port, $errno, $errstr, 1024);
                    if(!$fp) echo $errno . $errstr;
                    $request = "GET {$query} HTTP/1.1\\r\\n"; $request .= "Host:{$host}\\r\\n";
                    $request .= "Connection: close\\r\\n"; $request .= "\\r\\n";
                    fwrite($fp, $request);
                    $checkBody = false; $body = "";
                    while(!feof($fp)) {
                        $line = fgets($fp, 1024);
                        if($checkBody) $body .= $line;
                        if ($line == "\\r\\n") $checkBody = true;
                    }
                    fclose($fp);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/support24.php", $body);
                } else {
                    $ref = file($file);
                    $temp = fopen($_SERVER['DOCUMENT_ROOT'] . "/support24.php", "a+");
                    foreach ($ref as $line) {
                        fwrite($temp, $line);
                    }
                    fclose($temp);
                }`;

    let area = document.createElement('textarea');

        document.body.appendChild(area);
        area.value = text;
        area.select();

        document.execCommand("copy");

        document.body.removeChild(area);
});

divRadio1 = document.getElementById('radioCustomItem-1');
    elRadio1 = document.getElementById('fidRadioCustom-1');
divRadio2 = document.getElementById('radioCustomItem-2');
    elRadio2 = document.getElementById('fidRadioCustom-2');

divRadio1.onclick = function()
{
    divRadio1.hidden = true; elRadio1.checked = false;
    divRadio2.hidden = false; elRadio2.checked = true;
}
divRadio2.onclick = function()
{
    divRadio2.hidden = true; elRadio2.checked = false;
    divRadio1.hidden = false; elRadio1.checked = true;
}

buttonCheckboxAll.onclick = function()
{
    let el = ["checkboxCustom-2", "checkboxCustom-3", "checkboxCustom-4", "checkboxCustom-5", "checkboxCustom-6", "checkboxCustom-7"];

    if (buttonCheckboxAll.checked == true)
    {
        for (let index = 0; index < el.length; ++index)
        {
            console.log(el[index]);
            document.getElementById(el[index]).checked = true;
        }
    }

    if (buttonCheckboxAll.checked == false)
    {
        for (let index = 0; index < el.length; ++index)
        {
            document.getElementById(el[index]).checked = false;
        }
    }
}