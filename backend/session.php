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
