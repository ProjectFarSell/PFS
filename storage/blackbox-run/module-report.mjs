import fs from 'node:fs/promises';
import {FileBlob,SpreadsheetFile} from '@oai/artifact-tool';
import {modules,logisticsRows,logisticsFirst,rules} from './module-results.mjs';
const inputs=JSON.parse(await fs.readFile('storage/blackbox-run/module-inputs.json','utf8'));
const previewOnly=process.argv.includes('--preview');
const outputDir='outputs/blackbox-modules-20261018';
const totals={Pass:0,Fail:0,Blocked:0,'Not Run':0};
const manifest=[];
await fs.mkdir(outputDir,{recursive:true});
for(const item of inputs){
 const role=item.path.match(/Cases_(\w+)_Modules/)[1];
 const wb=await SpreadsheetFile.importXlsx(await FileBlob.load(item.path));
 const counts={Pass:0,Fail:0,Blocked:0,'Not Run':0};
 const caseSheets=item.sheets.filter(s=>s.rows[0].length===13);
 for(const src of caseSheets){
  const sheet=wb.worksheets.getItem(src.name);
  const rows=role==='Logistics'?logisticsRows(src.rows.length-1):modules[src.name];
  if(!rows||rows.length!==src.rows.length-1)throw new Error(`Count mismatch ${src.name}: ${rows?.length} vs ${src.rows.length-1}`);
  if(role==='Logistics'&&src.name==='L1 Dashboard')rows[0]=logisticsFirst;
  if(!previewOnly){
   sheet.getRange(`K2:M${rows.length+1}`).values=rows;
   for(let i=0;i<rows.length;i++){
    const row=i+2;
    const needed=Math.max(Math.ceil(rows[i][0].length/30),Math.ceil(rows[i][2].length/24))*14+12;
    if(needed>(src.heights[String(row)]||15))sheet.getRange(`A${row}:M${row}`).format.rowHeight=needed;
    counts[rows[i][1]]++;totals[rows[i][1]]++;
   }
  }
  const tag=src.name.replace(/[^A-Za-z0-9]/g,'_');
  const path=`storage/blackbox-run/modules-${role}-${tag}-${previewOnly?'before':'after'}.png`;
  const preview=await wb.render({sheetName:src.name,range:previewOnly?'I1:M3':'K1:M4',scale:1,format:'png'});
  await fs.writeFile(path,new Uint8Array(await preview.arrayBuffer()));
  manifest.push({role,sheet:src.name,path});
 }
 if(!previewOnly){
  const summary=wb.worksheets.getItem('Summary');
  summary.getRange('B5').values=[['FarSell module assessment']];
  summary.getRange('B6').values=[['9950500 / Laravel 13.26.1']];
  summary.getRange('B7').values=[['SQLite; HTTP/tests/inspection']];
  summary.getRange('B8').values=[['Codex']];
  summary.getRange('B9').values=[[new Date('2026-10-18T00:00:00Z')]];
  summary.getRange('B9').setNumberFormat('yyyy-mm-dd');
  const assumptions=wb.worksheets.getItem('Assumptions');
  const src=item.sheets.find(s=>s.name==='Assumptions');
  for(let i=4;i<src.rows.length;i++){
   const text=rules[src.rows[i][0]];
   if(!text)continue;
   assumptions.getRange(`D${i+1}`).values=[[text]];
   assumptions.getRange(`D${i+1}`).format.wrapText=true;
   const needed=Math.ceil(text.length/40)*14+12;
   if(needed>(src.heights[String(i+1)]||15))assumptions.getRange(`A${i+1}:D${i+1}`).format.rowHeight=needed;
  }
  wb.recalculate();
  console.log(role,JSON.stringify(counts));
  console.log((await wb.inspect({kind:'match',searchTerm:'#REF!|#DIV/0!|#VALUE!|#NAME\\?|#NUM!|#NULL!|#SPILL!|#CALC!',options:{useRegex:true,maxResults:20},maxChars:1500,summary:'Final formula error scan'})).ndjson);
  const last=12+caseSheets.length+1;
  console.log((await wb.inspect({kind:'table',range:`Summary!A${last}:G${last}`,include:'values,formulas',tableMaxRows:1,maxChars:1800})).ndjson);
  for(const [sheetName,range,label] of [['Summary',`A4:G${last}`,'summary'],['Assumptions','C5:D8','rules']]){
   const p=await wb.render({sheetName,range,scale:1,format:'png'});
   await fs.writeFile(`storage/blackbox-run/modules-${role}-${label}.png`,new Uint8Array(await p.arrayBuffer()));
  }
  const file=await SpreadsheetFile.exportXlsx(wb);
  await file.save(`${outputDir}/Blackbox_Test_Results_${role}_Modules.xlsx`);
 }
}
await fs.writeFile(`storage/blackbox-run/module-${previewOnly?'before':'after'}-manifest.json`,JSON.stringify(manifest));
console.log('TOTAL',JSON.stringify(totals));
