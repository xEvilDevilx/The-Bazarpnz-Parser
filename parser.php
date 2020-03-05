<?php
require('phpQuery.php');
require 'php-export-data.class.php';

$baseLink = 'http://niva4x4.i58.ru';

set_time_limit(3600); // php execution timeout

/*
++++++++++++++++++++++++++++++++++++++++++
    Получаем все ссылки на объявления
++++++++++++++++++++++++++++++++++++++++++
*/
$proxyUrls = file('proxies.txt');
$links = array(); 
$countPerPage = 45;
$pageNum = 0;
$proxyCounter = 0;

while(count($links) >= ($pageNum * $countPerPage))
{
    $newLink = $baseLink . '/?&p=' . ($pageNum * $countPerPage);
    while(true)
    {
        $proxy = $proxyUrls[$proxyCounter];
        //echo $proxy . '<br>';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $newLink);
        curl_setopt($curl, CURLOPT_PROXY, $proxy);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        $document = phpQuery::newDocument($result);
        $norm = $document->find('.norm a');
        //echo count($norm) . '<br>';
        if(count($norm) < 1)
        {
            $proxyCounter += 1;
            continue;
        }

        foreach($norm as $element) 
        {
            $pq_element = pq($element);
            if(strpos($pq_element->attr('href'), 'ann') !== false){
                $links[] = $pq_element->attr('href');       
            }
        }

        curl_close($curl);
        break;
    }
    $proxyCounter += 1;
    $pageNum += 1;
}

/*
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    Получаем по каждой ссылке из массива объявление и парсим его
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
*/
$fileName = 'export ' . date("Y-m-d") . '.xls';
$exporter = new ExportDataExcel('file', $fileName); // 'browser', 'string'
$exporter->initialize();

$i = 0;
$proxyCounter = 0;

foreach($links as $link)
{
    while(parse_link($baseLink, $link, $exporter, $proxyUrls[$proxyCounter]) == false)
    {
        $proxyCounter += 1;
        if($proxyCounter == count($proxyUrls))
        {
            $proxyCounter = 0;
            break;
        }
    }
    $proxyCounter += 1;
}

echo 'Done!<br>';
$exporter->finalize();
phpQuery::unloadDocuments();
exit();

function parse_link($baseLink, $path, $exporter, $proxyUrl)
{
    $link = $baseLink . $path;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $link);
    curl_setopt($curl, CURLOPT_PROXY, $proxyUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    $linkHtml = phpQuery::newDocument($result);

    // Название
    $name = $linkHtml->find('h1');
    $nameTrim = trim($name->text());
    if(($nameTrim == "") || (strpos($nameTrim, 'error') !== false) || (strpos($nameTrim, 'ERROR') !== false) || (strpos($nameTrim, 'Error') !== false))
        return false;

    // Цена
    $price = $linkHtml->find('span.price');

    // Таблица характеристик
    $avtoInfo = $linkHtml->find('table.avtoinfo');
    $avtoInfoResult = iconv("Windows-1251", "UTF-8", $avtoInfo->html());
    //echo $avtoInfoResult . '<br>';

    // Описание
    $advText = $linkHtml->find('p.adv_text');

    // Остальные картинки
    $otherPhotos = $linkHtml->find('a.big_photo');
    $photosString = '';
    foreach($otherPhotos as $photo)
    {
        $pq_photo = pq($photo);
        if($photosString !== '')
        {
            $photosString .= '|';
        }

        $photosString .= $baseLink . $pq_photo->attr('href');
    }
    
    $exporter->addRow(array(trim($name->text()), trim($price->text()), $avtoInfoResult, trim($advText->text()), $photosString)); 

    curl_close($curl);
    return true;
}
?>