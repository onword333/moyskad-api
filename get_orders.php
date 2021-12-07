<?php
date_default_timezone_set('Europe/Moscow');

$urlCustomerOrderEndPoint = 'https://online.moysklad.ru/api/remap/1.2/entity/customerorder';
$auth = ''; // логин и пароль в base64
$limit = 100; // максимальное количество записей за один запрос

$headers = [
  'Authorization: Basic ' . $auth
];

$columns = [
  'Номер', 
  'Время', 
  'Контрагент', 
  'Сумма', 
  'Статус', 
  'Склад', 
  'Комментарий менеджера', 
  'Менеджер', 
  'Курьер', 
  'Источник', 
  'Комментарий', 
  'roistat', 
  'Способ оплаты',
  'Дата создания',
  'Дата шага',
  'Курьерские',
  'Город',
  'Трек код',
  'Курьер Назначен',
  'Товары'
];


$startPeriod = date('Y-m-d 00:00:00');
$endPeriod = date('Y-m-d 23:59:59');

$fileName = __DIR__ . '/files/export_order_' . date('Ymd__H') . '.csv';

// Исключаем заказы со статусами: 
// Некорректная заявка, СПАМ, Отказ
$filterForStatus = 'state!=https://online.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/bcc89425-57f0-11eb-0a80-079c00392bd1;state!=https://online.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/17648163-50e1-11ec-0a80-09cd001c83a1;state!=https://online.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/bcc892ae-57f0-11eb-0a80-079c00392bd0';

$data = [];
$data[] = $columns;

$allOrderSelected = false;
$counterOrder = 0;

while ($allOrderSelected == false) {

  $params = [
    'limit' => $limit,
    'offset' => $counterOrder,
    'expand' => 'agent,store,state,positions.assortment',
    'filter' => 'moment>=' . $startPeriod . ';' . $filterForStatus,
    'order' => 'moment,asc'
  ];

  $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

  $url = $urlCustomerOrderEndPoint . '?' . $query;
  
  $jsonStr = curl_send($url, [], $headers, 'GET');

  $jsonObj = json_decode($jsonStr, true);

  if (isset($jsonObj['errors'])) {
    $allOrderSelected = true;
    $errorStr = count($jsonObj['errors']) > 0 ? $jsonObj['errors'][0]['error'] : 'unknown error';
    file_put_contents('export_order_errors.csv', date('Y-m-d H:i:s', time()) . ';'. $errorStr . PHP_EOL, FILE_APPEND);
    break;
  }

  if (count($jsonObj['rows']) < 1 || !isset($jsonObj['rows'])) {
    $allOrderSelected = true;
  }

  foreach ($jsonObj['rows'] as $row) {

    $managerComment = '';
    $courier = '';
    $manager = '';
    $source = '';
    $roistat = '';
    $paymentMethod = '';
    $createDate = '';
    $stepDate = '';
    $courierMoney = '';
    $city = '';
    $trackCode = '';
    $courierIsAssigned = '';

    if (isset($row['attributes'])) {
      foreach ($row['attributes'] as $attr) {
        if ($attr['id'] == 'fb4b5ce9-58b6-11eb-0a80-04f200432869') { // Комментарий менеджер
          $managerComment = trim($attr['value']);
        }

        if ($attr['id'] == '19d41c54-7b27-11eb-0a80-03040003e7b0') { // Курьер
          $courier = isset($attr['value']['name']) ? $attr['value']['name'] : '';
        }

        if ($attr['id'] == '92fcc938-7b5c-11eb-0a80-0304000ee38b') {  // менеджер
          $manager = isset($attr['value']['name']) ? $attr['value']['name'] : '';
        }

        if ($attr['id'] == '99aa464e-070b-11ec-0a80-096700065a21') { // источник
          $source = isset($attr['value']['name']) ? $attr['value']['name'] : '';
        }

        if ($attr['id'] == '34a07532-567b-11eb-0a80-087200232b2e') { // roistat
          $roistat = isset($attr['value']) ? $attr['value'] : '';
        }

        if ($attr['id'] == 'd3a3a1ff-7a90-11eb-0a80-09730032841e') { // Способ оплаты
          $paymentMethod = isset($attr['value']['name']) ? $attr['value']['name'] : '';
        }

        if ($attr['id'] == '1a621d6a-8194-11eb-0a80-09ac000d0c98') { // Дата создания
          $createDate = isset($attr['value']) ? date('Y-m-d H:i:s', strtotime($attr['value'])) : '';
        }

        if ($attr['id'] == '58d0b225-58bb-11eb-0a80-079c00409eae') { // Дата шага
          $stepDate = isset($attr['value']) ? date('Y-m-d H:i:s', strtotime($attr['value'])) : ''; 
        }

        if ($attr['id'] == '6f32e743-f5bf-11eb-0a80-03ab00065a4e') { // Курьерские
          $courierMoney = isset($attr['value']) ? $attr['value'] : '';
        }

        if ($attr['id'] == '20d0935e-567c-11eb-0a80-01de0022c52e') { // Город
          $city = isset($attr['value']) ? $attr['value'] : '';
        }

        if ($attr['id'] == '94065fc6-cdc8-11eb-0a80-06ef003936fb') { // ТРЕК КОД
          $trackCode = isset($attr['value']) ? $attr['value'] : '';
        }

        if ($attr['id'] == '51b11f72-a5e6-11eb-0a80-035a0023a471') { // Курьер назначен
          $courierIsAssigned = isset($attr['value']) ? $attr['value'] : '';
        }        
      }
    }

    $itemsStr = '';

    if (isset($row['positions']['rows'])) {
      foreach ($row['positions']['rows'] as $item) {
        $price = $item['price'] / 100;
        $sum = $price * $item['quantity'];
        $itemsStr .= $item['assortment']['name'] . '|' . $item['quantity'] . '|' . $price . '|' . $sum . PHP_EOL;
      }
    }

    $data[] = [
      'number' => $row['name'],
      'created' => date('Y-m-d H:i:s', strtotime($row['moment'])),
      'client' => isset($row['agent']['name']) ? $row['agent']['name'] : '',
      'sum' => empty($row['sum']) ? '' : $row['sum'] / 100,
      'status' => isset($row['state']['name']) ? $row['state']['name'] : '',
      'store' => isset($row['store']['name']) ? $row['store']['name'] : '',
      'comment_manager' => $managerComment,
      'manager' => $manager,
      'courier' => $courier,
      'source' => $source,
      'description' => isset($row['description']) ? $row['description'] : '',
      'roistat' => $roistat,
      'payment_method' => $paymentMethod,
      'create_date' => $createDate,
      'step_date' => $stepDate,
      'courier_money' => $courierMoney,
      'city' => $city,
      'track_code' => $trackCode,
      'courier_is_assigned' => $courierIsAssigned,
      'goods' => $itemsStr
    ];
    $counterOrder++;
  }
}

if (count($data) > 1) {
  saveToCsv($fileName, $data);
}


function saveToCsv($fileName, $data) {
  $fp = fopen($fileName, 'w');
  fputs($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
  foreach ($data as $row) {
    fputcsv($fp, $row, ';');
  }
  fclose($fp);
}


function curl_send($url, $fields, $headers, $method = 'POST') {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  //for debug only!
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }

  if ($method == 'POST') {
    curl_setopt($ch, CURLOPT_POST, 1);
    if (!empty($fields)) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
  }

  $output = curl_exec($ch);
  curl_close($ch);
  return $output;
}
