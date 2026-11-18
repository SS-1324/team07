import http from 'node:http';
import {readFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root=fileURLToPath(new URL('.',import.meta.url));
const mime={'.html':'text/html; charset=utf-8','.css':'text/css; charset=utf-8','.js':'text/javascript; charset=utf-8','.mjs':'text/javascript; charset=utf-8','.svg':'image/svg+xml'};
const server=http.createServer(async(req,res)=>{try{const u=new URL(req.url,'http://localhost');const p=path.resolve(root,'.'+decodeURIComponent(u.pathname==='/'?'/index.html':u.pathname));if(!p.startsWith(root)){res.writeHead(403);return res.end('Forbidden')}const data=await readFile(p);res.writeHead(200,{'Content-Type':mime[path.extname(p)]||'text/plain; charset=utf-8','Cache-Control':'no-store'});res.end(data)}catch{res.writeHead(404);res.end('Not found')}});
server.listen(Number(process.env.PORT||5173),'127.0.0.1',()=>console.log('DIAMONDATA: http://127.0.0.1:'+server.address().port));
