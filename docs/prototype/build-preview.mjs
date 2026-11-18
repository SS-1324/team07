import {readFile,writeFile} from 'node:fs/promises';
const load=n=>readFile(new URL(n,import.meta.url),'utf8');
let html=await load('index.html');const css=(await load('styles.css'))+'\n'+(await load('refinements.css'))+'\n'+(await load('chart.css'));html=html.replace('<link rel="stylesheet" href="refinements.css">','').replace('<link rel="stylesheet" href="chart.css">','');const model=(await load('model.mjs')).replaceAll('export ','');const app=(await load('app.mjs')).replace(/^import .*?;\s*/gm,'');
const chart=(await load('analysis-chart.mjs')).replaceAll('export ','');
html=html.replace('<link rel="stylesheet" href="styles.css">',()=>'<style>'+css+'</style>').replace('<script type="module" src="app.mjs"></script>',()=>'<script type="module">'+model+'\n'+chart+'\n'+app+'</script>');
await writeFile(new URL('preview.html',import.meta.url),html);console.log('Created preview.html — double click to open without a server.');
