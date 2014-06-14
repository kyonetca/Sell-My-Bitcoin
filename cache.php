<?php
/**
 * Sell-My-Bitcoin
 * 2012-2014 (c) Studio-MouGE.org, All Rights Reserved.
 * $Id$
 **/

if(file_exists('./data.json')){
  $data = json_decode(file_get_contents('./data.json'), true);
  if((time() - $data['time']) <= 5){
    die(json_encode($data));
  }
}

//先設定各大網站的 API：
//define(     'HUOBI_API', 'https://market.huobi.com/staticmarket/detail.html');
define(     'HUOBI_API', 'https://market.huobi.com/staticmarket/detail_btc_json.js');
define(      'BTCE_API', 'https://btc-e.com/api/3/ticker/btc_usd');
define(        'OK_API', 'https://www.okcoin.com/api/ticker.do');
define(  'BITSTAMP_API', 'https://www.bitstamp.net/api/ticker/');
define('YAHOO_USD_RATE', 'http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=USDTWD=X');
define('YAHOO_CNY_RATE', 'http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=CNYTWD=X');

//分別抓取三處的 API 的買一價跟賣一價
   $huobi_return = runCurl(HUOBI_API, true);
    $btce_return = runCurl(BTCE_API, true);
      $ok_return = runCurl(OK_API, true);
$bitstamp_return = runCurl(BITSTAMP_API, true);
//抓取美金與人民幣對臺幣之匯率
//美金=>臺幣 = 美金×匯率
$usd_rate = explode(',', runCurl(YAHOO_USD_RATE));
$usd_rate = $usd_rate[1];
$cny_rate = explode(',', runCurl(YAHOO_CNY_RATE));
$cny_rate = $cny_rate[1];

$weighted = array();

//注意火幣網為人民幣價錢。
$huobi = array('buy' => convert($huobi_return['buys'][0], $cny_rate),
              'sell' => convert($huobi_return['sells'][0], $cny_rate),
            'amount' => $huobi_return['amount'][0]);

$weighted['huobi']['buy'] = $huobi['buy'] * $huobi['amount'];
$weighted['huobi']['sell'] = $huobi['sell'] * $huobi['amount'];

$btce = array('buy' => convert($btce_return['btc_usd']['buy'], $usd_rate),
             'sell' => convert($btce_return['btc_usd']['sell'], $usd_rate),
           'amount' => $btce_return['btc_usd']['vol']);

$weighted['btce']['buy'] = $btce['buy'] * $btce['amount'];
$weighted['btce']['sell'] = $btce['sell'] * $btce['amount'];

$ok = array('buy' => convert($ok_return['ticker']['buy'], $cny_rate),
           'sell' => convert($ok_return['ticker']['sell'], $cny_rate),
         'amount' => $ok_return['ticker']['vol']);

$weighted['ok']['buy'] = $ok['buy'] * $ok['amount'];
$weighted['ok']['sell'] = $ok['sell'] * $ok['amount'];

$bitstamp = array('buy' => $bitstamp_return['bid'],
                 'sell' => $bitstamp_return['ask'],
               'amount' => $bitstamp_return['volume']);


$weighted['bitstamp']['buy'] = $bitstamp['buy'] * $bitstamp['amount'];
$weighted['bitstamp']['sell'] = $bitstamp['sell'] * $bitstamp['amount'];

$totalMount = $huobi['amount'] + $btce['amount'] + $ok['amount'] + $bitstamp['amount'];
$totalBuy = $weighted['huobi']['buy'] + $weighted['btce']['buy'] + $weighted['ok']['buy'] + $weighted['bitstamp']['buy'];
$totalSell = $weighted['huobi']['sell'] + $weighted['btce']['sell'] + $weighted['ok']['sell'] + $weighted['bitstamp']['sell'];

//計算買一價加權平均值（注意價錢均已換臺幣）
$mount = array();
$mount['buy'] = $totalBuy / $totalMount;
$mount['sell'] = $totalSell / $totalMount;

//Set up the array for output.
$output = array();
$output['price'] = $mount;
$output['rate']['usd'] = $usd_rate;
$output['rate']['cny'] = $cny_rate;
$output['time'] = time();

$fh = fopen('./data.json', 'w');
$output_text = json_encode($output);
fwrite($fh, $output_text);

function runCurl($url = '', $isJson = false) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Google Bot');
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  $output = curl_exec($ch);
  curl_close($ch);

  if($isJson) {
    return json_decode($output, true);
  } else {
    return $output;
  }
}

function convert($value, $rate) {
  $result = (int)$value * (int)$rate;
  return $result;
}
