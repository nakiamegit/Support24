//*********************[ Add action for form from active tab ][ Delete checkCustom.php ]*********************//
chrome.tabs.query({active:true, currentWindow: true},function(tabsArray) {
// Current URL
    let tab = tabsArray[0];
    let tabUrl = tab.url;
// Parsing by '/'
    let domainName = tabUrl.split('/');
// Adding an action to the form
    let f = document.getElementById('formCustom');
    f.action = domainName[0] + '//' + domainName[2] + '/checkCustom.php';

    let buttonDelete = document.getElementById("deleteCheckCustom");
    buttonDelete.href = domainName[0] + '//' + domainName[2] + '/checkCustom.php?delFile=Y';

    let getListCustom = document.getElementById("getListCustom");
    getListCustom.href = domainName[0] + '//' + domainName[2] + '/checkCustom.php?getListCustom=Y';

});

//*********************[ Refresh active tab after form submit ]*********************//
let buttonForm = document.getElementById("buttonForm");
buttonForm.addEventListener("click", async () => {
// Get current tab ID
    let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
// Execute script in tabID
    chrome.scripting.executeScript({
        target: { tabId: tab.id },
        function: reloadTab,
    });
});
function reloadTab() {
    window.location.reload();
}

//*********************[ Close extension ]*********************//
let buttonClose = document.getElementById("buttonClose");
buttonClose.addEventListener("click", async () => {
    window.close();
});

//*********************[ Upload checkCustom.php ]*********************//
let buttonUpload = document.getElementById("uploadCheckCustom");
chrome.tabs.query({active:true, currentWindow: true},function(tabsArray) {
    let tab = tabsArray[0];
    let tabUrl = tab.url;
    let domainName = tabUrl.split('/');
    buttonUpload.href = domainName[0] + '//' + domainName[2] + '/bitrix/admin/php_command_line.php';
});
buttonUpload.addEventListener("click", async () => {
    let text = "$referenceCheckCustom = file('https://raw.githubusercontent.com/nakiamegit/Support24/main/checkCustom.php');\n" +
        "$tempCheckCustom = fopen($_SERVER['DOCUMENT_ROOT'] . \"/checkCustom.php\", \"a+\");\n" +
        "foreach ($referenceCheckCustom as $line) {\n" +
        "    fwrite($tempCheckCustom, $line);\n" +
        "}\n" +
        "fclose($tempCheckCustom);";
        let area = document.createElement('textarea');
        document.body.appendChild(area);
        area.value = text;
        area.select();
        document.execCommand("copy");
        document.body.removeChild(area);
});

//*********************[ Radio ]*********************//
divRadio1 = document.getElementById('radioCustomItem-1'); divRadio2 = document.getElementById('radioCustomItem-2');
elRadio1 = document.getElementById('fidRadioCustom-1'); elRadio2 = document.getElementById('fidRadioCustom-2');
divRadio1.onclick = function() {
    divRadio1.hidden = true; elRadio1.checked = false;
    divRadio2.hidden = false; elRadio2.checked = true;
}
divRadio2.onclick = function() {
    divRadio2.hidden = true; elRadio2.checked = false;
    divRadio1.hidden = false; elRadio1.checked = true;
}

//*********************[ Checking file exists ]*********************//
divNotifyIsFileCustom = document.getElementById('notifyIsFileCustom');
function checkIsFileCustom(){
    chrome.tabs.query({active:true, currentWindow: true},function(tabsArray) {
        let tab = tabsArray[0];
        let tabUrl = tab.url;
        let domainNameSplit = tabUrl.split('/');
        let domainName = domainNameSplit[0] + '//' + domainNameSplit[2] + '/checkCustom.php';

        let xhr = new XMLHttpRequest();
        xhr.open('GET', domainName,  true)
        xhr.send()

        xhr.onreadystatechange = function() {
            if (xhr.readyState != 4) { return }
            if (xhr.status === 200) {
                divNotifyIsFileCustom.style.display = 'block';
            } else {
                divNotifyIsFileCustom.style.display = 'none';
            }
        }
    });
}
setInterval(checkIsFileCustom, 1000);

//*********************[ Checking disabled custom ]*********************//
divNotifyDisabledCustom = document.getElementById('notifyDisabledCustom');
function checkDisabledCustom(){
    chrome.tabs.query({active:true, currentWindow: true},function(tabsArray) {
        let tab = tabsArray[0];
        let tabUrl = tab.url;
        let domainNameSplit = tabUrl.split('/');
        let domainName = domainNameSplit[0] + '//' + domainNameSplit[2] + '/checkCustom.php?checkDisabledCustom=Y';

        let xhr = new XMLHttpRequest();
        xhr.open('GET', domainName,  true)
        xhr.send()

        xhr.onreadystatechange = function() {
            if (xhr.status === 200) {
                divNotifyDisabledCustom.style.display = 'block';
            } else {
                divNotifyDisabledCustom.style.display = 'none';
            }
        }
    });
}
setInterval(checkDisabledCustom, 1000);