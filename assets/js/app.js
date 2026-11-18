// assets/js/app.js
// 담당: 프론트
// 공통 스크립트. 계산과 정렬은 JS가 아니라 PHP + SQL에서 처리한다 (과제 요건).

// data-confirm 속성이 있는 폼은 제출 전에 한 번 확인한다 (삭제 버튼 등)
document.addEventListener('submit', function (event) {
    var message = event.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
