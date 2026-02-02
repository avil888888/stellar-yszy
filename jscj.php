<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
error_reporting(0);
ini_set('max_execution_time', 30);

// 源接口配置
define('API_HOST', 'https://jszyapi.com');
define('PLAY_FROM', 'jsm3u8');
define('PAGE_LIMIT', 20);

// CURL请求封装
function httpGet($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
            'Referer: ' . API_HOST . '/'
        ]
    ]);
    $res = curl_exec($ch);
    curl_errno($ch) && exit(json_encode(['code'=>0,'msg'=>'网络错误:'.curl_error($ch)]));
    curl_close($ch);
    return $res;
}

// 参数解析
$params = $_GET ?: [];
$ids = intval($params['ids'] ?? 0);
$ep = intval($params['ep'] ?? 0);
$ac = strtolower(trim($params['ac'] ?? 'list'));
$pg = max(1, intval($params['pg'] ?? 1));
$t = intval($params['t'] ?? 0);
$wd = trim($params['wd'] ?? '');

// 接口1：单集播放URL（点击选集）
if ($ids > 0 && isset($params['ep'])) {
    // 适配源接口的ac=detail详情地址
    $detailUrl = API_HOST . "/api.php/provide/vod/from/" . PLAY_FROM . "/?ac=detail&ids=$ids";
    $detailRes = json_decode(httpGet($detailUrl), true) ?: [];
    
    if ($detailRes['code'] == 1 && !empty($detailRes['list'][0]['vod_play_url'])) {
        $episodes = explode('#', $detailRes['list'][0]['vod_play_url']);
        if (isset($episodes[$ep])) {
            list($epName, $epUrl) = explode('$', $episodes[$ep]);
            exit(json_encode([
                'code' => 1,
                'msg' => '成功获取播放链接',
                'ep_name' => $epName,
                'play_url' => $epUrl
            ]));
        }
        exit(json_encode(['code'=>0,'msg'=>'该集数不存在']));
    }
    exit(json_encode(['code'=>0,'msg'=>'视频详情获取失败']));
}

// 接口2：列表/详情接口（适配ac=detail）
$apiUrl = '';
if ($ids > 0) {
    // 视频详情：使用源接口的ac=detail地址
    $apiUrl = API_HOST . "/api.php/provide/vod/from/" . PLAY_FROM . "/?ac=detail&ids=$ids";
} else {
    $limitParam = "&limit=" . PAGE_LIMIT;
    if ($t > 0) {
        // 分类列表
        $apiUrl = API_HOST . "/api.php/provide/vod/from/" . PLAY_FROM . "?ac=list&t=$t&pg=$pg" . $limitParam;
    } elseif ($wd) {
        // 搜索列表
        $apiUrl = API_HOST . "/api.php/provide/vod/from/" . PLAY_FROM . "?ac=videolist&wd=" . urlencode($wd) . "&pg=$pg" . $limitParam;
    } else {
        // 主页列表
        $apiUrl = API_HOST . "/api.php/provide/vod/from/" . PLAY_FROM . "?ac=list&pg=$pg" . $limitParam;
    }
}

// 采集并返回数据
$res = json_decode(httpGet($apiUrl), true) ?: [];
if (empty($res['code']) || $res['code'] != 1) {
    $res = [
        'code' => 0,
        'msg' => $ids > 0 ? '详情页加载失败' : ($t > 0 ? '分类无数据' : '主页数据加载失败'),
        'list' => []
    ];
}
exit(json_encode($res, JSON_UNESCAPED_UNICODE));
