<?php
@error_reporting(0);
@ini_set('display_errors', 0);
$D = __DIR__ . '/data';

function out($a, $c = 200) {
    http_response_code($c);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_dir($D)) { @mkdir($D, 0755, true); }
if (!is_dir($D) || !is_writable($D)) {
    out(['e' => 'تعذّر إنشاء مجلد data بجوار api.php. أعطِ مجلد الموقع صلاحية الكتابة (755 أو 775).'], 500);
}
@mkdir($D . '/v', 0755, true);
@mkdir($D . '/t', 0755, true);
if (!file_exists($D . '/.htaccess')) {
    file_put_contents($D . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    file_put_contents($D . '/index.html', '');
}

session_set_cookie_params(['lifetime' => 2592000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
@ini_set('session.gc_maxlifetime', 2592000);
session_start();

/* ---------- أدوات ---------- */
function s($v, $n = 2000) { $v = trim((string)$v); return function_exists('mb_substr') ? mb_substr($v, 0, $n) : substr($v, 0, $n); }
function idc($v) { $v = preg_replace('/[^A-Za-z0-9]/', '', (string)$v); $v = substr($v, 0, 16); return $v !== '' ? $v : bin2hex(random_bytes(4)); }
function fname($v) { return preg_match('/^[a-f0-9]{24}\.(mp4|webm|mov|m4v|pdf)$/', (string)$v) ? $v : ''; }
function norm($v) { $v = strtr((string)$v, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']); return preg_replace('/[^A-Z0-9]/', '', strtoupper($v)); }
function inp() { static $j = null; if ($j === null) { $j = json_decode((string)file_get_contents('php://input'), true); if (!is_array($j)) $j = []; } return $j; }
function adm() { return !empty($_SESSION['adm']); }
function need_post() { if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQ'])) out(['e' => 'طلب غير صالح'], 400); }
function need_adm() { need_post(); if (!adm()) out(['e' => 'غير مصرّح، سجّل دخول المدير'], 401); }

function load() {
    global $D;
    $f = $D . '/db.php';
    if (!file_exists($f)) {
        $db = ['pw' => password_hash('ABCDEFG', PASSWORD_DEFAULT), 'defpw' => true,
            'settings' => ['name' => 'العكريشي', 'tag' => 'دروس اللغة العربية لكل مرحلة دراسية', 'pay' => '01151045032', 'wa' => '201151045032',
                'legal' => ['terms' => 'للاستخدام الشخصي فقط ويُمنع تسجيل الدروس أو مشاركة كود التفعيل. [عدّل النص]', 'privacy' => 'نحفظ بيانات الموقع على خادمنا ولا نبيعها لأحد. [عدّل النص]', 'refund' => '[اكتب سياسة الاسترجاع]']],
            'courses' => []];
        save($db);
        return $db;
    }
    $t = (string)file_get_contents($f);
    $db = json_decode(substr($t, strpos($t, "\n") + 1), true);
    if (!is_array($db)) out(['e' => 'ملف البيانات تالف. استرجع نسخة data/db.php الاحتياطية.'], 500);
    if (!isset($db['courses'])) $db['courses'] = [];
    return $db;
}
function save($db) {
    global $D;
    file_put_contents($D . '/db.php', "<?php http_response_code(403);exit;?>\n" . json_encode($db, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function codes_ok($c) {
    $own = isset($_SESSION['codes']) ? $_SESSION['codes'] : [];
    foreach ($c['codes'] as $k) { if (in_array($k['code'], $own, true)) return true; }
    return false;
}
function can($c, $l) { return adm() || !empty($l['free']) || codes_ok($c); }
function find_course($db, $id) { foreach ($db['courses'] as $c) { if ($c['id'] === $id) return $c; } return null; }
function find_lesson($c, $id) { foreach ($c['lessons'] as $l) { if ($l['id'] === $id) return $l; } return null; }

function rl_get() { global $D; $a = json_decode((string)@file_get_contents($D . '/rl.json'), true); return is_array($a) ? $a : []; }
function rl_put($a) { global $D; @file_put_contents($D . '/rl.json', json_encode($a), LOCK_EX); }
function rl_blocked($k) { $a = rl_get(); return isset($a[$k]) && $a[$k]['u'] > time(); }
function rl_fail($k, $m) { $a = rl_get(); $n = (isset($a[$k]) ? $a[$k]['n'] : 0) + 1; $a[$k] = ['n' => $n >= $m ? 0 : $n, 'u' => $n >= $m ? time() + 900 : 0]; rl_put($a); }
function rl_clear($k) { $a = rl_get(); unset($a[$k]); rl_put($a); }

function mimeof($fn) {
    $e = substr($fn, strrpos($fn, '.') + 1);
    $m = ['mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'pdf' => 'application/pdf'];
    return isset($m[$e]) ? $m[$e] : 'application/octet-stream';
}
function stream($path, $mime) {
    @set_time_limit(0);
    while (ob_get_level()) { @ob_end_clean(); }
    $size = filesize($path); $start = 0; $end = $size - 1;
    header('Accept-Ranges: bytes');
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') { $start = (int)$m[1]; if ($m[2] !== '') $end = (int)$m[2]; }
        elseif ($m[2] !== '') { $start = max(0, $size - (int)$m[2]); }
        $end = min($end, $size - 1);
        if ($start > $end || $start >= $size) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    header('Content-Length: ' . ($end - $start + 1));
    $f = fopen($path, 'rb'); fseek($f, $start); $left = $end - $start + 1;
    while ($left > 0 && !feof($f)) {
        $r = fread($f, min(131072, $left));
        if ($r === false || $r === '') break;
        echo $r; $left -= strlen($r); flush();
    }
    fclose($f); exit;
}

/* ---------- المسارات ---------- */
$a = isset($_GET['a']) ? $_GET['a'] : '';

if ($a === 'state') {
    $db = load();
    $o = ['settings' => $db['settings'], 'admin' => adm(), 'courses' => []];
    foreach ($db['courses'] as $c) {
        $ok = adm() || codes_ok($c);
        $r = ['id' => $c['id'], 'title' => $c['title'], 'grade' => $c['grade'], 'price' => $c['price'], 'desc' => $c['desc'], 'unlocked' => $ok, 'lessons' => []];
        foreach ($c['lessons'] as $l) {
            $op = $ok || !empty($l['free']);
            $x = ['id' => $l['id'], 'title' => $l['title'], 'unit' => $l['unit'], 'free' => !empty($l['free']), 'open' => $op];
            if ($op) {
                $x['video'] = $l['video']; $x['pdf'] = $l['pdf'];
                $x['hasV'] = !empty($l['vfile']); $x['hasP'] = !empty($l['pfile']);
                $x['quiz'] = array_map(function ($q) { return ['q' => $q['q'], 'o' => $q['o']]; }, $l['quiz']);
            }
            $r['lessons'][] = $x;
        }
        $o['courses'][] = $r;
    }
    out($o);
}
elseif ($a === 'admin') {
    if (!adm()) out(['e' => 'غير مصرّح'], 401);
    $db = load();
    out(['settings' => $db['settings'], 'courses' => $db['courses'], 'defpw' => !empty($db['defpw'])]);
}
elseif ($a === 'login') {
    need_post();
    $k = 'l' . $_SERVER['REMOTE_ADDR'];
    if (rl_blocked($k)) out(['e' => 'محاولات كثيرة، حاول بعد 15 دقيقة'], 429);
    $db = load(); $i = inp();
    if (password_verify((string)(isset($i['password']) ? $i['password'] : ''), $db['pw'])) {
        rl_clear($k); session_regenerate_id(true); $_SESSION['adm'] = 1;
        out(['ok' => 1]);
    }
    rl_fail($k, 5);
    out(['e' => 'كلمة السر غير صحيحة'], 401);
}
elseif ($a === 'logout') {
    need_post(); unset($_SESSION['adm']); out(['ok' => 1]);
}
elseif ($a === 'redeem') {
    need_post();
    $k = 'c' . $_SERVER['REMOTE_ADDR'];
    if (rl_blocked($k)) out(['e' => 'محاولات كثيرة، حاول بعد 15 دقيقة'], 429);
    $i = inp(); $code = norm(isset($i['code']) ? $i['code'] : ''); $db = load();
    if (strlen($code) >= 8) {
        foreach ($db['courses'] as $ci => $c) {
            foreach ($c['codes'] as $ki => $kk) {
                if (hash_equals($kk['code'], $code)) {
                    if (!isset($_SESSION['codes'])) $_SESSION['codes'] = [];
                    if (!in_array($code, $_SESSION['codes'], true)) {
                        $_SESSION['codes'][] = $code;
                        $db['courses'][$ci]['codes'][$ki]['uses'] = (isset($kk['uses']) ? $kk['uses'] : 0) + 1;
                        save($db);
                    }
                    rl_clear($k);
                    out(['ok' => 1, 'course' => $c['id']]);
                }
            }
        }
    }
    rl_fail($k, 10);
    out(['e' => 'الكود غير صحيح'], 400);
}
elseif ($a === 'quiz') {
    need_post(); $i = inp(); $db = load();
    $c = find_course($db, isset($i['c']) ? (string)$i['c'] : '');
    $l = $c ? find_lesson($c, isset($i['l']) ? (string)$i['l'] : '') : null;
    if (!$l || !can($c, $l)) out(['e' => 'غير مسموح'], 403);
    $ans = isset($i['ans']) && is_array($i['ans']) ? $i['ans'] : [];
    $w = [];
    foreach ($l['quiz'] as $n => $q) { if ((int)(isset($ans[$n]) ? $ans[$n] : -1) !== (int)$q['a']) $w[] = $n; }
    out(['score' => count($l['quiz']) - count($w), 'total' => count($l['quiz']), 'wrong' => $w]);
}
elseif ($a === 'video' || $a === 'file') {
    $db = load();
    $c = find_course($db, isset($_GET['c']) ? (string)$_GET['c'] : '');
    $l = $c ? find_lesson($c, isset($_GET['l']) ? (string)$_GET['l'] : '') : null;
    if (!$l) { http_response_code(404); exit; }
    if (!can($c, $l)) { http_response_code(403); exit; }
    session_write_close();
    $fn = (isset($_GET['k']) && $_GET['k'] === 'p') ? (isset($l['pfile']) ? $l['pfile'] : '') : (isset($l['vfile']) ? $l['vfile'] : '');
    $path = $D . '/v/' . $fn;
    if (!fname($fn) || !is_file($path)) { http_response_code(404); exit; }
    stream($path, mimeof($fn));
}
elseif ($a === 'up') {
    need_adm();
    $id = isset($_GET['id']) ? $_GET['id'] : ''; $i = (int)(isset($_GET['i']) ? $_GET['i'] : 0); $n = (int)(isset($_GET['n']) ? $_GET['n'] : 1);
    $ext = strtolower(isset($_GET['ext']) ? $_GET['ext'] : '');
    if (!preg_match('/^[a-f0-9]{24}$/', $id) || $n < 1 || $n > 5000 || $i < 0 || $i >= $n || !in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'pdf'], true)) out(['e' => 'بيانات الرفع غير صحيحة'], 400);
    session_write_close();
    if ($i === 0) { $old = glob($D . '/t/*'); if ($old) foreach ($old as $f) { if (filemtime($f) < time() - 86400) @unlink($f); } }
    $body = (string)file_get_contents('php://input');
    if ($body === '' || strlen($body) > 4 * 1048576) out(['e' => 'حجم الجزء غير صالح'], 400);
    if (file_put_contents($D . '/t/' . $id . '.' . $i, $body) === false) out(['e' => 'تعذّر الكتابة على الخادم'], 500);
    if ($i < $n - 1) out(['ok' => 1]);
    for ($j = 0; $j < $n; $j++) { if (!file_exists($D . '/t/' . $id . '.' . $j)) out(['e' => 'ينقص جزء من الملف، أعد الرفع'], 400); }
    $dst = $D . '/v/' . $id . '.' . $ext; $o = fopen($dst, 'wb');
    for ($j = 0; $j < $n; $j++) { $p = $D . '/t/' . $id . '.' . $j; $in = fopen($p, 'rb'); stream_copy_to_stream($in, $o); fclose($in); unlink($p); }
    fclose($o);
    out(['ok' => 1, 'done' => 1, 'file' => $id . '.' . $ext, 'size' => filesize($dst)]);
}
elseif ($a === 'save') {
    need_adm(); $in = inp(); $db = load();
    $old = [];
    foreach ($db['courses'] as $c) foreach ($c['codes'] as $k) $old[$k['code']] = $k;
    $cs = []; $refs = [];
    foreach ((array)(isset($in['courses']) ? $in['courses'] : []) as $c) {
        $c = (array)$c; $L = [];
        foreach ((array)(isset($c['lessons']) ? $c['lessons'] : []) as $l) {
            $l = (array)$l; $Q = [];
            foreach ((array)(isset($l['quiz']) ? $l['quiz'] : []) as $q) {
                $q = (array)$q; $o = array_values(array_map('s', (array)(isset($q['o']) ? $q['o'] : [])));
                if (count($o) < 2) continue;
                $ai = (int)(isset($q['a']) ? $q['a'] : 0);
                $Q[] = ['q' => s(isset($q['q']) ? $q['q'] : ''), 'o' => $o, 'a' => ($ai >= 0 && $ai < count($o)) ? $ai : 0];
            }
            $vf = fname(isset($l['vfile']) ? $l['vfile'] : ''); $pf = fname(isset($l['pfile']) ? $l['pfile'] : '');
            if ($vf) $refs[$vf] = 1; if ($pf) $refs[$pf] = 1;
            $L[] = ['id' => idc(isset($l['id']) ? $l['id'] : ''), 'title' => s(isset($l['title']) ? $l['title'] : '', 200), 'unit' => s(isset($l['unit']) ? $l['unit'] : '', 200),
                'free' => !empty($l['free']), 'video' => s(isset($l['video']) ? $l['video'] : '', 500), 'pdf' => s(isset($l['pdf']) ? $l['pdf'] : '', 500),
                'vfile' => $vf, 'vname' => s(isset($l['vname']) ? $l['vname'] : '', 200), 'pfile' => $pf, 'pname' => s(isset($l['pname']) ? $l['pname'] : '', 200), 'quiz' => $Q];
        }
        $K = [];
        foreach ((array)(isset($c['codes']) ? $c['codes'] : []) as $k) {
            $k = (array)$k; $code = norm(isset($k['code']) ? $k['code'] : '');
            if (strlen($code) < 8) continue;
            $K[] = ['code' => $code, 'label' => s(isset($k['label']) ? $k['label'] : '', 100), 'at' => (int)(isset($k['at']) ? $k['at'] : time()), 'uses' => isset($old[$code]['uses']) ? (int)$old[$code]['uses'] : 0];
        }
        $cs[] = ['id' => idc(isset($c['id']) ? $c['id'] : ''), 'title' => s(isset($c['title']) ? $c['title'] : '', 200), 'grade' => s(isset($c['grade']) ? $c['grade'] : '', 100),
            'price' => max(0, (int)(isset($c['price']) ? $c['price'] : 0)), 'desc' => s(isset($c['desc']) ? $c['desc'] : '', 1000), 'lessons' => $L, 'codes' => $K];
    }
    $st = (array)(isset($in['settings']) ? $in['settings'] : []); $lg = (array)(isset($st['legal']) ? $st['legal'] : []);
    $db['settings'] = ['name' => s(isset($st['name']) ? $st['name'] : '', 100), 'tag' => s(isset($st['tag']) ? $st['tag'] : '', 200), 'pay' => s(isset($st['pay']) ? $st['pay'] : '', 30),
        'wa' => preg_replace('/\D/', '', (string)(isset($st['wa']) ? $st['wa'] : '')),
        'legal' => ['terms' => s(isset($lg['terms']) ? $lg['terms'] : '', 3000), 'privacy' => s(isset($lg['privacy']) ? $lg['privacy'] : '', 3000), 'refund' => s(isset($lg['refund']) ? $lg['refund'] : '', 3000)]];
    $db['courses'] = $cs;
    save($db);
    $files = glob($D . '/v/*');
    if ($files) foreach ($files as $f) { if (!isset($refs[basename($f)]) && filemtime($f) < time() - 7200) @unlink($f); }
    out(['ok' => 1]);
}
elseif ($a === 'pw') {
    need_adm(); $i = inp(); $db = load();
    if (!password_verify((string)(isset($i['old']) ? $i['old'] : ''), $db['pw'])) out(['e' => 'كلمة السر الحالية غير صحيحة'], 400);
    $n = (string)(isset($i['new']) ? $i['new'] : '');
    if (strlen($n) < 6) out(['e' => 'كلمة السر الجديدة قصيرة (6 أحرف على الأقل)'], 400);
    $db['pw'] = password_hash($n, PASSWORD_DEFAULT); $db['defpw'] = false; save($db);
    out(['ok' => 1]);
}
else { out(['e' => 'غير معروف'], 404); }
