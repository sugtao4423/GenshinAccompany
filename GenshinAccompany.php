<?php

declare(strict_types=1);

if (!isset($argv[1])) {
    echo 'Please set HOYOLAB_COOKIE' . PHP_EOL;
    exit(1);
}
define('HOYOLAB_COOKIE', $argv[1]);

define('THRESHOLD_ACCOMPANY_DAYS', 365);

$genshinAccompanyList = getGenshinAccompanyList();
foreach ($genshinAccompanyList as $role) {
    usleep(500_000);

    echo "Accompany {$role['name']}: ";

    $info = getAccompanyInfo($role['topic_id']);
    if ($info === null) {
        echo 'Failed to get accompany info. Skip.' . PHP_EOL;
        continue;
    }
    if ($info['accompany_days'] >= THRESHOLD_ACCOMPANY_DAYS) {
        echo "{$info['accompany_days']} days. Skip." . PHP_EOL;
        continue;
    }
    if ($info['is_accompany_today']) {
        echo "{$info['accompany_days']} days. Already accompanied today. Skip." . PHP_EOL;
        continue;
    }
    if (!$info['can_accompany']) {
        echo "{$info['accompany_days']} days. Cannot accompany. Skip." . PHP_EOL;
        continue;
    }

    usleep(500_000);

    $accompany = doAccompany($role['role_id'], $role['topic_id']);
    if ($accompany === null) {
        echo 'Failed to accompany. Skip.' . PHP_EOL;
        continue;
    }
    if ($accompany['accompany_days'] !== ($info['accompany_days'] + 1)) {
        echo "{$accompany['accompany_days']} days. Accompany failed. Skip." . PHP_EOL;
        continue;
    }
    echo "{$info['accompany_days']} days -> {$accompany['accompany_days']} days. Done." . PHP_EOL;
}



function doAccompany(string $roleId, string $topicId): ?array
{
    $query = http_build_query([
        'role_id' => $roleId,
        'topic_id' => $topicId,
    ]);
    $path = '/community/apihub/api/user/accompany/role?' . $query;
    $method = 'GET';
    $json = request($path, $method);
    if ($json === null) {
        return null;
    }

    $data = $json['data'];
    return [
        'accompany_days' => (int)$data['accompany_days'],
        'accompany_quarter_days' => (int)$data['accompany_quarter_days'],
        'increase_accompany_point' => (int)$data['increase_accompany_point'],
    ];
}

function getAccompanyInfo(string $topicId): ?array
{
    $query = http_build_query([
        'scene' => 'SceneAll',
        'topic_id' => $topicId,
    ]);
    $path = '/community/painter/api/topic/info?' . $query;
    $method = 'GET';
    $json = request($path, $method);
    if ($json === null) {
        return null;
    }

    $info = $json['data']['info']['role_info']['accompany_info'];
    return [
        'accompany_days' => (int)$info['accompany_days'],
        'is_accompany_today' => $info['is_accompany_today'],
        'can_accompany' => $info['can_accompany'],
    ];
}

function getGenshinAccompanyList(): array
{
    $path = '/community/painter/api/getChannelRoleList';
    $method = 'POST';
    $json = request($path, $method);
    if ($json === null) {
        throw new Exception("Request failed: $path");
    }

    // 2: Genshin
    $genshinRoleKey = array_search(2, array_column($json['data']['game_roles_list'], 'game_id'));
    if ($genshinRoleKey === false) {
        throw new Exception('Genshin role not found');
    }

    $genshinRoles = $json['data']['game_roles_list'][$genshinRoleKey]['role_list'];
    return array_map(fn($role) => [
        'role_id' => $role['basic']['role_id'],
        'topic_id' => $role['basic']['topic_id'],
        'name' => $role['basic']['name'],
    ], $genshinRoles);
}

function request(string $path, string $method): ?array
{
    $baseUrl = 'https://bbs-api-os.hoyolab.com';
    $url = $baseUrl . $path;

    $header = [
        'Accept-Language: ja',
        'Accept: application/json, text/plain, */*',
        'Cookie: ' . HOYOLAB_COOKIE,
        'Origin: https://act.hoyolab.com',
        'Referer: https://act.hoyolab.com/',
        'x-rpc-language: ja-jp',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'x-rpc-app_version: 3.0.1',
        'x-rpc-client_type: 1',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    $output = curl_exec($ch);
    curl_close($ch);
    if ($output === false) {
        return null;
    }
    $json = json_decode($output, true);
    if ($json === null || $json['retcode'] !== 0 || !isset($json['data'])) {
        return null;
    }
    return $json;
}
