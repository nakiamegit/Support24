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

//####  *  ####  *  ####    Архив   ####  *  ####  *  ####//
//*********************[ Проверить нажата ли кнопка (1) ]*********************//
// let buttonClose = document.getElementById("test");
// if (buttonClose) {
//     buttonClose.onclick = function() {}
// };

// async function getCurrentTab() {
//     let queryOptions = { active: true, currentWindow: true };
//     let [tab] = await chrome.tabs.query(queryOptions);
//     return tab;
// }
// a = getCurrentTab();
// console.log(a);