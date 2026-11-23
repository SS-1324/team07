"""Rebuild the standalone preview from its editable source files (Python 3)."""
from pathlib import Path
import base64

root = Path(__file__).resolve().parent
css = (root / 'assets/style.css').read_text(encoding='utf-8') + '\n' + (root / 'assets/modern.css').read_text(encoding='utf-8')
shell = (root / 'shell.html').read_text(encoding='utf-8')
shell = shell.replace('assets/stadium.webp', 'data:image/webp;base64,' + base64.b64encode((root / 'assets/stadium.webp').read_bytes()).decode('ascii'))
js = 'window.DIAMOND_BOOT={preview:true};\n' + '\n'.join(
    (root / name).read_text(encoding='utf-8')
    for name in ['assets/snapshot.js', 'assets/preview-data.js', 'assets/app.js']
)
html = ('<!doctype html><html lang="ko"><head><meta charset="utf-8">'
        '<meta name="viewport" content="width=device-width,initial-scale=1">'
        '<title>DIAMONDATA · 프론트엔드 공유 미리보기</title>'
        '<style>body{margin:0}' + css + '</style></head><body>' + shell +
        '<script>' + js + '</script></body></html>')
(root / 'index.html').write_text(html, encoding='utf-8')
print('Built docs/frontend-preview/index.html')
