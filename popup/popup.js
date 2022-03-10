//*********************[ Добавить action для формы из активной вкладки ]*********************//
chrome.tabs.query({active:true, currentWindow: true},function(tabsArray) {
    // Текущий URL
    let tab = tabsArray[0];
    let tabUrl = tab.url;

    // Парсинг по '/'
    let domainName = tabUrl.split('/');

    // Добавляем action к форме
    let f = document.getElementById('formCustom');
    f.action = domainName[0] + '//' + domainName[2] + '/checkCustom.php';
});


//*********************[ Обновить активную вкладку после отправки формы ]*********************//
let buttonForm = document.getElementById("buttonForm");
buttonForm.addEventListener("click", async () => {
    // Получить ID текущей вкладки
    let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    // Выполнить скрипт в tabID
    chrome.scripting.executeScript({
        target: { tabId: tab.id },
        function: reloadTab,
    });
});
function reloadTab() {
    window.location.reload();
}

//*********************[ Закрыть расширение ]*********************//
let buttonClose = document.getElementById("buttonClose");
buttonClose.addEventListener("click", async () => {
    window.close();
});

//*********************[ Загрузить checkCustom.php ]*********************//
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

//*********************[ Удалить checkCustom.php ]*********************//
let buttonDelete = document.getElementById("deleteCheckCustom");

chrome.tabs.query({active:true, currentWindow: true},function(tabsArray) {
    let tab = tabsArray[0];
    let tabUrl = tab.url;

    let domainName = tabUrl.split('/');

    buttonDelete.href = domainName[0] + '//' + domainName[2] + '/checkCustom.php?delFile=Y';
});