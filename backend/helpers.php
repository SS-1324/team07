<?php
// backend/helpers.php
// 담당: 백엔드 (프론트도 h(), url() 은 화면에서 사용)

// 화면에 출력하는 모든 값은 h()로 감싼다 (XSS 방지)
function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// 프로젝트 안의 주소 만들기: url('pages/teams/list.php')
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// ---- 입력값 읽기 (GET/POST 공통). 값이 없거나 형식이 틀리면 기본값을 돌려준다.
function input_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key, ?int $default = null): ?int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return (is_string($value) && preg_match('/^-?\d+$/', $value)) ? (int) $value : $default;
}

// 정렬 열처럼 SQL에 직접 들어가는 값은 허용 목록에 있는 것만 통과시킨다
function input_choice(string $key, array $allowed, string $default): string
{
    $value = input_str($key, $default);
    return in_array($value, $allowed, true) ? $value : $default;
}

// 체크박스 여러 개 (name="group[]"): 허용 목록에 있는 값만, 순서를 지켜 돌려준다
function input_choices(string $key, array $allowed): array
{
    $values = $_POST[$key] ?? $_GET[$key] ?? [];
    if (!is_array($values)) {
        $values = [$values];
    }
    $picked = [];
    foreach ($values as $value) {
        if (is_string($value) && in_array($value, $allowed, true) && !in_array($value, $picked, true)) {
            $picked[] = $value;
        }
    }
    return $picked;
}

// 처리 파일(actions) 첫 줄에서 호출: POST 가 아니거나 CSRF 토큰이 틀리면 $back 화면으로 돌려보낸다
function require_post(string $back): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($back);
    }
    if (!csrf_valid()) {
        flash_set('요청이 만료되었습니다. 화면을 새로고침한 뒤 다시 시도하세요.', 'error');
        redirect($back);
    }
}

// 금액 표시: USD는 달러, KRW는 만원 단위로 저장되어 있다
function format_money($amount, string $currency = 'USD'): string
{
    if ($amount === null) {
        return '—';
    }
    if ($currency === 'KRW') {
        // 만원 단위 저장값. 1억(=10,000만원) 이상은 억원으로 보여준다
        return abs((float) $amount) >= 10000
            ? number_format($amount / 10000, 1) . '억원'
            : number_format((float) $amount) . '만원';
    }
    return '$' . number_format($amount / 1000000, 1) . 'M';
}
