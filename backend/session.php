<?php
// backend/session.php
// 담당: 백엔드
// PHP 세션: 관심 선수 목록(watchlist)과 한 번만 보여줄 알림 메시지(flash)를 저장한다.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- 관심 선수
function watchlist_ids(): array
{
    return $_SESSION['watchlist'] ?? [];
}

function watchlist_has(string $playerId): bool
{
    return in_array($playerId, watchlist_ids(), true);
}

function watchlist_toggle(string $playerId): void
{
    $ids = watchlist_ids();
    if (in_array($playerId, $ids, true)) {
        $ids = array_values(array_diff($ids, [$playerId]));
    } else {
        $ids[] = $playerId;
    }
    $_SESSION['watchlist'] = $ids;
}

// ---- 최근 조회 조건 (담당: 백엔드 신수연)
// 분석 화면에서 고른 조건을 세션에 저장해 두었다가, 다른 화면에 갔다 와도 같은 조건으로 다시 보여준다.
// 사용: $f = filters_recall('ranking', ['league' => 'MLB', ...]); ... filters_remember('ranking', $f);
function filters_remember(string $page, array $values): void
{
    $_SESSION['filters'][$page] = $values;
}

function filters_recall(string $page, array $defaults = []): array
{
    return array_merge($defaults, $_SESSION['filters'][$page] ?? []);
}

// ---- CSRF 토큰 (담당: 백엔드 신수연)
// POST 폼에는 csrf_field() 를 넣고, 처리 파일(actions)은 require_post() 로 검사한다.
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

// ---- 알림 메시지: 처리 파일에서 저장하고, 이동한 화면에서 한 번 꺼내 보여준다
function flash_set(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_take(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
